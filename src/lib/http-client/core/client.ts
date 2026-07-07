import {
  buildBody,
  buildHeaders,
  buildUrl,
  generateRequestId,
  headersToRecord,
  parseErrorData,
  parseResponseData,
  toRequestInit,
} from '@/lib/http-client/utils';

import { ApiError, NetworkError, TimeoutError } from './errors';
import { nextRetry, resolveRetry } from './retry-policy';
import { safe } from './safe';

import type { SafeResult } from './safe';
import type {
  ApiRequestOptions,
  ApiResponse,
  HttpMethod,
  InferSchema,
} from './types';

// Backoff wait the caller's own AbortSignal can interrupt. Without this a canceled request
// would silently sit out the full wait (a server Retry-After can ask for minutes) before
// making one more doomed attempt — with TanStack Query that means the queryFn's signal
// (unmount, page change) is ignored for the whole backoff window.
const sleep = (ms: number, signal?: AbortSignal) =>
  new Promise<void>((resolve, reject) => {
    // The `??` arm is for hand-rolled AbortSignal-likes without a reason —
    // `AbortController.abort()` itself always sets one.
    const abortError = () =>
      signal?.reason ??
      new DOMException('This operation was aborted', 'AbortError');
    if (signal?.aborted) {
      reject(abortError());
      return;
    }
    const onAbort = () => {
      clearTimeout(timer);
      reject(abortError());
    };
    const timer = setTimeout(() => {
      signal?.removeEventListener('abort', onAbort);
      resolve();
    }, ms);
    signal?.addEventListener('abort', onAbort, { once: true });
  });

// Concatenates default + request-level plugins, deduped by `name` (first occurrence wins).
// See the call site in `mergeOptions` for why dedup matters.
function mergePlugins(
  defaults: ApiRequestOptions['plugins'],
  requested: ApiRequestOptions['plugins'],
): NonNullable<ApiRequestOptions['plugins']> {
  const combined = [...(defaults ?? []), ...(requested ?? [])];
  const seen = new Set<string>();
  return combined.filter((plugin) => {
    if (seen.has(plugin.name)) return false;
    seen.add(plugin.name);
    return true;
  });
}

/**
 * `mergeOptions()` unconditionally sets `method`/`path`/`requestId`/`plugins` — narrowing
 * them to require here lets callers use them without a non-null assertion.
 */
type ResolvedOptions = ApiRequestOptions & {
  method: HttpMethod;
  path: string;
  requestId: string;
  plugins: NonNullable<ApiRequestOptions['plugins']>;
};

export class HttpClient {
  readonly #baseUrl: string;
  readonly #defaultOptions: ApiRequestOptions;

  // Keyed by the resolved URL, only ever populated for GET requests that opt in via
  // `dedupe: true` (see `request()`). Off by default — collapsing concurrent identical
  // requests is the right call for read-heavy UI code, but it's a behavior change (fewer
  // network calls, one shared response object across callers), so it stays opt-in rather
  // than silently changing what every existing GET does.
  readonly #inFlightGets = new Map<string, Promise<ApiResponse>>();

  constructor(baseUrl = '', defaultOptions: ApiRequestOptions = {}) {
    this.#baseUrl = baseUrl.replace(/\/+$/, '');
    this.#defaultOptions = {
      responseType: 'json',
      ...defaultOptions,
    };
  }

  /** The main method that wraps the native fetch. */
  async request<T = unknown>(
    path: string,
    options: ApiRequestOptions = {},
  ): Promise<ApiResponse<T>> {
    const mergedOptions: ResolvedOptions = this.mergeOptions(path, options);

    if (
      mergedOptions.method === 'GET' &&
      mergedOptions.dedupe &&
      // Dedup silently steps aside when the request carries a per-user identity: on the
      // server this client (and this map) is a module-scope singleton shared by every
      // incoming request, so handing user A's in-flight response to user B's identical GET
      // would leak data across users. Any sign of auth (the `auth` plugin, or an explicit
      // Authorization header) disables it — the URL-only map key can't tell two users apart.
      !this.hasAuthIdentity(mergedOptions)
    ) {
      const key = buildUrl(
        mergedOptions.baseUrl || this.#baseUrl,
        mergedOptions.path,
        mergedOptions.params,
      );
      const existing = this.#inFlightGets.get(key);
      if (existing) return existing as Promise<ApiResponse<T>>;

      const promise = this.executeWithRetries<T>(path, mergedOptions).finally(
        () => this.#inFlightGets.delete(key),
      );
      this.#inFlightGets.set(key, promise as Promise<ApiResponse>);
      return promise;
    }

    return this.executeWithRetries<T>(path, mergedOptions);
  }

  /**
   * Retry loop over already-merged options. Split out of `request()` so the recovery-phase
   * replay below can re-enter the loop directly — going back through `request()` would
   * re-check the dedup map for the very entry this call is still resolving, which deadlocks on itself.
   */
  private async executeWithRetries<T>(
    path: string,
    mergedOptions: ResolvedOptions,
  ): Promise<ApiResponse<T>> {
    const retryPolicy = resolveRetry(mergedOptions.retry);
    const plugins = mergedOptions.plugins;

    // Core retry loop. Each iteration is one attempt; `continue` retries.
    for (let attempt = 0; ; attempt++) {
      mergedOptions.retryAttempt = attempt;

      try {
        return await this.attempt<T>(path, mergedOptions, plugins);
      } catch (err) {
        let finalError = this.normalizeError(err);

        // Phase 1 — recovery: a plugin (e.g., auth) may fix the cause and return a response.
        // The first that returns short-circuits the rest.
        for (const plugin of plugins) {
          if (!plugin.onError) continue;
          try {
            const result = await plugin.onError(finalError, {
              options: mergedOptions,
              // Replays with `mergedOptions`, not the original `options` — a recovery
              // plugin (e.g., auth) mutates `context.options` (that's `mergedOptions`) to
              // leave a mark for the replay, such as the `authRetried` guard against
              // refreshing forever. Spreading the original `options` here would silently
              // drop that mark, and the guard would never trip.
              // `retry: false` on the replay: recovery gets exactly one clean re-run. The
              // replay is a full inner `executeWithRetries` — if it kept a transient-retry
              // budget of its own, its final error would land back here as `finalError` and
              // Phase 2 below would retry the *outer* loop too, multiplying real fetches up
              // to limit×(limit+1) in the worst case.
              retry: () =>
                this.executeWithRetries<T>(path, {
                  ...mergedOptions,
                  retry: false,
                }),
            });
            if (result) return result as ApiResponse<T>;
          } catch (pluginError) {
            if (pluginError instanceof Error) finalError = pluginError;
          }
        }

        // Phase 2 — transient retry, owned by the core (not a plugin).
        if (retryPolicy) {
          const decision = nextRetry(
            finalError,
            attempt,
            retryPolicy,
            mergedOptions.method,
          );
          if (decision) {
            for (const plugin of plugins) {
              await plugin.onRetry?.({
                attempt: attempt + 1,
                limit: retryPolicy.limit,
                wait: decision.wait,
                fromRetryAfter: decision.fromRetryAfter,
                error: finalError,
                requestId: mergedOptions.requestId,
                method: mergedOptions.method,
                path: mergedOptions.path,
              });
            }
            try {
              // `?? undefined`: RequestInit types `signal` as possibly null.
              await sleep(decision.wait, mergedOptions.signal ?? undefined);
              continue;
            } catch (abortErr) {
              // Caller canceled during the backoff wait. Fall through to Phase 3 with the
              // abort as the final error, so observers (logger) still see the request end
              // — same path a mid-fetch abort takes.
              finalError =
                abortErr instanceof Error
                  ? abortErr
                  : new Error(String(abortErr));
            }
          }
        }

        // Phase 3 — final failure. Notify observers, then throw.
        for (const plugin of plugins) {
          await plugin.onFinalError?.(finalError, mergedOptions);
        }
        if (mergedOptions.onResponseError && finalError instanceof ApiError) {
          await mergedOptions.onResponseError(finalError);
        }
        throw finalError;
      }
    }
  }

  /** A single request attempt: hooks, fetch, response parsing. */
  private async attempt<T>(
    _path: string,
    mergedOptions: ResolvedOptions,
    plugins: NonNullable<ApiRequestOptions['plugins']>,
  ): Promise<ApiResponse<T>> {
    for (const plugin of plugins) {
      if (plugin.onRequest) await plugin.onRequest(mergedOptions);
    }
    if (mergedOptions.onRequest) await mergedOptions.onRequest(mergedOptions);

    // `mergedOptions.path`/`.method` are always set by `mergeOptions()` below (it reassigns
    // them unconditionally after the spread) — no `|| path`/`|| 'GET'` fallback needed here,
    // that path is unreachable through this class's API.
    const url = buildUrl(
      mergedOptions.baseUrl || this.#baseUrl,
      mergedOptions.path,
      mergedOptions.params,
    );
    const headers = buildHeaders(
      mergedOptions.headers,
      mergedOptions.body,
      mergedOptions.sendRequestIdHeader === false
        ? undefined
        : mergedOptions.requestId,
    );
    const body = buildBody(mergedOptions.body);

    // Per-attempt timeout: `AbortSignal.timeout()` self-clears, so there's no timer to
    // track or clean up by hand. Combined with the caller's own signal (if any). Never
    // mutates `mergedOptions` — that object is shared across retries, so a signal aborted
    // here must not poison later attempts; each retry needs its own clean signal.
    const timeoutMs = mergedOptions.timeout;
    const signal = timeoutMs
      ? mergedOptions.signal
        ? AbortSignal.any([
            mergedOptions.signal,
            AbortSignal.timeout(timeoutMs),
          ])
        : AbortSignal.timeout(timeoutMs)
      : mergedOptions.signal;

    const start = performance.now();
    let response: Response;
    try {
      response = await fetch(
        url,
        toRequestInit(mergedOptions, {
          method: mergedOptions.method,
          headers,
          body,
          signal,
        }),
      );
    } catch (err) {
      // `fetch` rejects with the signal's own abort *reason* (spec behavior, verified on
      // Node 24 / modern browsers) — `AbortSignal.timeout()`'s reason is a DOMException
      // already named 'TimeoutError', so no manual timer/controller bookkeeping is needed
      // to tell "our timer fired" (retryable) apart from "the caller canceled" (must
      // propagate as-is, must not be retried).
      if (err instanceof Error && err.name === 'TimeoutError') {
        throw new TimeoutError(url, timeoutMs as number);
      }
      // `name` check, not `instanceof DOMException` — some runtimes/polyfills reject with a plain Error named AbortError.
      if (err instanceof Error && err.name === 'AbortError') {
        throw err;
      }
      // NetworkError wraps *only* what `fetch` itself threw (DNS, refused connection, ...).
      // Classifying right here — not in a catch-all around the whole attempt — is what
      // keeps a throwing user hook from being mislabeled as a (retryable) network failure.
      throw new NetworkError(
        err instanceof Error ? err.message : 'Unknown network error',
        url,
        err,
      );
    }
    const duration = Math.round(performance.now() - start);

    if (!response.ok) {
      const errorData = await parseErrorData(response);
      throw new ApiError(
        `HTTP Error ${response.status}: ${response.statusText}`,
        {
          status: response.status,
          statusText: response.statusText,
          url,
          method: mergedOptions.method,
          data: errorData,
          headers: response.headers,
        },
      );
    }

    const data = await parseResponseData<T>(
      response,
      mergedOptions.responseType || 'json',
    );

    const apiResponse: ApiResponse<T> = {
      data,
      status: response.status,
      statusText: response.statusText,
      headers: response.headers,
      url,
      duration,
    };

    for (const plugin of plugins) {
      if (plugin.onResponse)
        await plugin.onResponse(apiResponse, mergedOptions);
    }
    if (mergedOptions.onResponse) {
      await mergedOptions.onResponse(apiResponse as ApiResponse);
    }

    return apiResponse;
  }

  /**
   * Errors reaching the retry loop are already typed at their source: the fetch call in
   * `attempt()` classifies its own failures (TimeoutError / AbortError / NetworkError),
   * `ApiError`/`ParseError` are thrown typed, and `ValidationError` comes from the
   * validation plugin. Everything else — a user hook or plugin throwing its own error —
   * passes through *unwrapped*: wrapping it in NetworkError would make `nextRetry` re-run a
   * request whose network part already succeeded and would lie about the error's type. The
   * only guarantee left to enforce is `Error`-ness for non-Error throws.
   */
  private normalizeError(err: unknown): Error {
    return err instanceof Error ? err : new Error(String(err));
  }

  // --- Convenience Methods ---
  // `O` carries the option object's literal type so `InferSchema` can pick up a `schema`
  // field when present; `T` is the fallback for the schema-less call shape callers already
  // use (`get<Post[]>('/posts')`).

  get<
    T = unknown,
    O extends Omit<ApiRequestOptions, 'method' | 'body'> = Omit<
      ApiRequestOptions,
      'method' | 'body'
    >,
  >(path: string, options?: O): Promise<ApiResponse<InferSchema<O, T>>> {
    return this.request(path, { ...options, method: 'GET' }) as Promise<
      ApiResponse<InferSchema<O, T>>
    >;
  }

  /**
   * A HEAD response never has a body (`response.body === null`),
   * so `data` resolves to `null` — the useful parts are `status` and `headers`.
   */
  head<
    O extends Omit<ApiRequestOptions, 'method' | 'body'> = Omit<
      ApiRequestOptions,
      'method' | 'body'
    >,
  >(path: string, options?: O): Promise<ApiResponse<null>> {
    return this.request(path, { ...options, method: 'HEAD' }) as Promise<
      ApiResponse<null>
    >;
  }

  post<
    T = unknown,
    O extends Omit<ApiRequestOptions, 'method' | 'body'> = Omit<
      ApiRequestOptions,
      'method' | 'body'
    >,
  >(
    path: string,
    body?: unknown,
    options?: O,
  ): Promise<ApiResponse<InferSchema<O, T>>> {
    return this.request(path, { ...options, method: 'POST', body }) as Promise<
      ApiResponse<InferSchema<O, T>>
    >;
  }

  put<
    T = unknown,
    O extends Omit<ApiRequestOptions, 'method' | 'body'> = Omit<
      ApiRequestOptions,
      'method' | 'body'
    >,
  >(
    path: string,
    body?: unknown,
    options?: O,
  ): Promise<ApiResponse<InferSchema<O, T>>> {
    return this.request(path, { ...options, method: 'PUT', body }) as Promise<
      ApiResponse<InferSchema<O, T>>
    >;
  }

  patch<
    T = unknown,
    O extends Omit<ApiRequestOptions, 'method' | 'body'> = Omit<
      ApiRequestOptions,
      'method' | 'body'
    >,
  >(
    path: string,
    body?: unknown,
    options?: O,
  ): Promise<ApiResponse<InferSchema<O, T>>> {
    return this.request(path, { ...options, method: 'PATCH', body }) as Promise<
      ApiResponse<InferSchema<O, T>>
    >;
  }

  delete<
    T = unknown,
    O extends Omit<ApiRequestOptions, 'method' | 'body'> = Omit<
      ApiRequestOptions,
      'method' | 'body'
    >,
  >(path: string, options?: O): Promise<ApiResponse<InferSchema<O, T>>> {
    return this.request(path, { ...options, method: 'DELETE' }) as Promise<
      ApiResponse<InferSchema<O, T>>
    >;
  }

  /**
   * `{ data, error }` sugar over `get/post/put/patch/delete` — see `safe()` in `./safe`. A
   * getter (not a field set in the constructor) so it stays bound to `this` without extra
   * bookkeeping, at the cost of a new object per access; `client.safe` is called
   * per-request, not in a hot loop, so that's fine.
   */
  get safe() {
    return {
      get: <
        T = unknown,
        O extends Omit<ApiRequestOptions, 'method' | 'body'> = Omit<
          ApiRequestOptions,
          'method' | 'body'
        >,
      >(
        path: string,
        options?: O,
      ): Promise<SafeResult<InferSchema<O, T>>> =>
        safe(this.get<T, O>(path, options)),

      post: <
        T = unknown,
        O extends Omit<ApiRequestOptions, 'method' | 'body'> = Omit<
          ApiRequestOptions,
          'method' | 'body'
        >,
      >(
        path: string,
        body?: unknown,
        options?: O,
      ): Promise<SafeResult<InferSchema<O, T>>> =>
        safe(this.post<T, O>(path, body, options)),

      put: <
        T = unknown,
        O extends Omit<ApiRequestOptions, 'method' | 'body'> = Omit<
          ApiRequestOptions,
          'method' | 'body'
        >,
      >(
        path: string,
        body?: unknown,
        options?: O,
      ): Promise<SafeResult<InferSchema<O, T>>> =>
        safe(this.put<T, O>(path, body, options)),

      patch: <
        T = unknown,
        O extends Omit<ApiRequestOptions, 'method' | 'body'> = Omit<
          ApiRequestOptions,
          'method' | 'body'
        >,
      >(
        path: string,
        body?: unknown,
        options?: O,
      ): Promise<SafeResult<InferSchema<O, T>>> =>
        safe(this.patch<T, O>(path, body, options)),

      delete: <
        T = unknown,
        O extends Omit<ApiRequestOptions, 'method' | 'body'> = Omit<
          ApiRequestOptions,
          'method' | 'body'
        >,
      >(
        path: string,
        options?: O,
      ): Promise<SafeResult<InferSchema<O, T>>> =>
        safe(this.delete<T, O>(path, options)),
    };
  }

  // --- Private Helpers ---

  /**
   * See the dedup gate in `request()` — URL-keyed dedup can't tell two users' identical GETs apart,
   * so any per-user identity opts the request out. Identity can arrive three ways:
   * the `auth` plugin, an explicit `Authorization`/`Cookie` header, or `credentials: 'include'`
   * (the request then carries whatever cookies the runtime holds for the target origin).
   */
  private hasAuthIdentity(options: ResolvedOptions): boolean {
    if (options.plugins.some((plugin) => plugin.name === 'auth')) return true;
    if (options.credentials === 'include') return true;
    return Object.keys(headersToRecord(options.headers)).some((key) => {
      const lower = key.toLowerCase();
      return lower === 'authorization' || lower === 'cookie';
    });
  }

  private mergeOptions(
    path: string,
    options: ApiRequestOptions,
  ): ResolvedOptions {
    const method = (
      options.method ||
      this.#defaultOptions.method ||
      'GET'
    ).toUpperCase() as HttpMethod;

    return {
      ...this.#defaultOptions,
      ...options,
      path,
      method,
      // Generated once per request so every lifecycle hook shares the same id.
      // Not inherited from defaultOptions, which would collide across requests.
      requestId: options.requestId ?? generateRequestId(),
      headers: {
        ...headersToRecord(this.#defaultOptions.headers),
        ...headersToRecord(options.headers),
      },
      // Concatenate, don't replace it — a per-request `plugins` array must not drop the
      // client's default plugins (e.g. `logger`). Deduped by `name`, the first occurrence
      // wins: the auth `onError` recovery path replays through `mergeOptions` with
      // `options` set to the *already merged* plugins list, so without dedup a replay
      // would double every plugin on each retry.
      plugins: mergePlugins(this.#defaultOptions.plugins, options.plugins),
    };
  }
}
