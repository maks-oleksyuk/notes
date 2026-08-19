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

// Backoff wait that the caller's AbortSignal can interrupt — otherwise a canceled request
// sits out the full backoff (Retry-After can ask for minutes) before one more doomed attempt.
const sleep = (ms: number, signal?: AbortSignal) =>
  new Promise<void>((resolve, reject) => {
    // `??`: hand-rolled AbortSignal-likes may lack a reason; AbortController.abort() always sets one.
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

// Concatenates default + request-level plugins, deduped by `name` (first wins) — see mergeOptions().
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

// mergeOptions() always sets these — required here so callers skip the non-null assertion.
type ResolvedOptions = ApiRequestOptions & {
  method: HttpMethod;
  path: string;
  requestId: string;
  plugins: NonNullable<ApiRequestOptions['plugins']>;
};

export class HttpClient {
  readonly #baseUrl: string;
  readonly #defaultOptions: ApiRequestOptions;

  // Keyed by resolved URL; populated only for GET requests opting into `dedupe: true` (see request()).
  // Opt-in, not default — sharing a response across callers is a behavior change.
  readonly #inFlightGets = new Map<string, Promise<ApiResponse>>();

  constructor(baseUrl = '', defaultOptions: ApiRequestOptions = {}) {
    this.#baseUrl = baseUrl.replace(/\/+$/u, '');
    this.#defaultOptions = {
      responseType: 'json',
      ...defaultOptions,
    };
  }

  /** The main method that wraps the native fetch. */
  request<T = unknown>(
    path: string,
    options: ApiRequestOptions = {},
  ): Promise<ApiResponse<T>> {
    const mergedOptions: ResolvedOptions = this.mergeOptions(path, options);

    if (
      mergedOptions.method === 'GET' &&
      mergedOptions.dedupe &&
      // Dedup steps aside for any per-user identity — on the server this client is a
      // singleton shared across requests, so a URL-only key can't tell two users apart
      // and would leak user A's response to user B.
      !this.hasAuthIdentity(mergedOptions)
    ) {
      const key = buildUrl(
        mergedOptions.baseUrl ?? this.#baseUrl,
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

  // Split out of request() so the recovery-phase replay can re-enter the loop directly —
  // going back through request() would re-check the dedup map for the entry this call is
  // still resolving, deadlocking on itself.
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
        // biome-ignore lint/performance/noAwaitInLoops: each attempt only happens after the previous one failed and backed off — parallel attempts would defeat retry semantics.
        return await this.attempt<T>(path, mergedOptions, plugins);
      } catch (err) {
        let finalError = this.normalizeError(err);

        // Phase 1 — recovery: a plugin (e.g., auth) may fix the cause and return a response.
        // The first that returns short-circuits the rest.
        for (const plugin of plugins) {
          if (!plugin.onError) continue;
          try {
            // biome-ignore lint/performance/noAwaitInLoops: first-match-wins — must stop at the first plugin that recovers, not fire every plugin's recovery attempt concurrently.
            const result = await plugin.onError(finalError, {
              // `mergedOptions`, not the original `options` — a recovery plugin (e.g. auth)
              // mutates it to mark the replay (the `authRetried` guard), and that mark must
              // survive into the replay below.
              options: mergedOptions,
              // `retry: false`: one clean replay. Without it, the inner executeWithRetries
              // would carry its own retry budget, and Phase 2 below would retry the outer
              // loop too — multiplying real fetches up to limit×(limit+1).
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
            // Pure notification, no early exit or shared-state mutation between
            // plugins — safe (and faster) to fire concurrently.
            await Promise.all(
              plugins.map((plugin) =>
                plugin.onRetry?.({
                  attempt: attempt + 1,
                  limit: retryPolicy.limit,
                  wait: decision.wait,
                  fromRetryAfter: decision.fromRetryAfter,
                  error: finalError,
                  requestId: mergedOptions.requestId,
                  method: mergedOptions.method,
                  path: mergedOptions.path,
                }),
              ),
            );
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
                  : typeof abortErr === 'string'
                    ? new Error(abortErr)
                    : new Error('Request aborted', { cause: abortErr });
            }
          }
        }

        // Phase 3 — final failure. Notify observers, then throw.
        // Pure notification, safe to fire concurrently (see onRetry above).
        await Promise.all(
          plugins.map((plugin) =>
            plugin.onFinalError?.(finalError, mergedOptions),
          ),
        );
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
      // biome-ignore lint/performance/noAwaitInLoops: plugins share (and mutate) `mergedOptions` — e.g. auth attaches a header a later plugin may read — order must be preserved.
      if (plugin.onRequest) await plugin.onRequest(mergedOptions);
    }
    if (mergedOptions.onRequest) await mergedOptions.onRequest(mergedOptions);

    // mergeOptions() always sets path/method — no fallback needed here.
    const url = buildUrl(
      mergedOptions.baseUrl ?? this.#baseUrl,
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

    // Per-attempt timeout, combined with the caller's own signal. Built fresh each attempt
    // (never stored on mergedOptions, which is shared across retries) — an abort here must
    // not poison later attempts.
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
      // fetch rejects with the signal's abort reason — AbortSignal.timeout()'s reason is
      // already a DOMException named 'TimeoutError', distinguishing "our timer fired"
      // (retryable) from "the caller canceled" (must propagate as-is).
      if (err instanceof Error && err.name === 'TimeoutError') {
        throw new TimeoutError(url, timeoutMs as number, err);
      }
      // `name` check, not `instanceof DOMException` — some runtimes reject with a plain Error.
      if (err instanceof Error && err.name === 'AbortError') {
        throw err;
      }
      // Classified here (not a catch-all around the whole attempt) so a throwing user hook
      // downstream can't get mislabeled as a retryable network failure.
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
      mergedOptions.responseType ?? 'json',
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
      if (plugin.onResponse) {
        // biome-ignore lint/performance/noAwaitInLoops: plugins share (and mutate) `apiResponse.data` — e.g. validation replaces it with the parsed value before a later plugin (logger) reads it — order must be preserved.
        await plugin.onResponse(apiResponse, mergedOptions);
      }
    }
    if (mergedOptions.onResponse) {
      await mergedOptions.onResponse(apiResponse as ApiResponse);
    }

    return apiResponse;
  }

  // Errors reaching here are already typed at their source (TimeoutError/AbortError/
  // NetworkError from attempt(), ApiError/ParseError thrown typed, ValidationError from the
  // validation plugin). A user hook's own error passes through unwrapped — wrapping it in
  // NetworkError would misclassify a request whose network part already succeeded. Only
  // guarantee enforced here: non-Error throws become an Error.
  private normalizeError(err: unknown): Error {
    return err instanceof Error ? err : new Error(String(err));
  }

  // --- Convenience Methods ---
  // `O` carries the option object's literal type so InferSchema can pick up a `schema`
  // field; `T` is the fallback for the schema-less call shape (`get<Post[]>('/posts')`).

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

  // `{ data, error }` sugar over get/post/put/patch/delete — see safe() in ./safe. A getter,
  // not a constructor field, so it stays bound to `this`; new object per access is fine
  // since it's called per-request, not in a hot loop.
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

  // Backs the dedup gate in request(). Identity can arrive via the `auth` plugin, an
  // explicit Authorization/Cookie header, or `credentials: 'include'`.
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
      options.method ??
      this.#defaultOptions.method ??
      'GET'
    ).toUpperCase() as HttpMethod;

    return {
      ...this.#defaultOptions,
      ...options,
      path,
      method,
      // Fresh per request so every lifecycle hook shares one id — not inherited from
      // defaultOptions, which would collide across requests.
      requestId: options.requestId ?? generateRequestId(),
      headers: {
        ...headersToRecord(this.#defaultOptions.headers),
        ...headersToRecord(options.headers),
      },
      // Concatenated, not replaced, so a per-request `plugins` array doesn't drop the
      // client's defaults (e.g. logger). Deduped by name — the auth recovery replay calls
      // back in with the already-merged list, and without dedup that doubles every plugin.
      plugins: mergePlugins(this.#defaultOptions.plugins, options.plugins),
    };
  }
}
