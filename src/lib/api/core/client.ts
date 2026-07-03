import {
  buildBody,
  buildHeaders,
  buildUrl,
  generateRequestId,
  headersToRecord,
  parseErrorData,
  parseResponseData,
  toRequestInit,
} from '../utils';
import {
  ApiError,
  NetworkError,
  ParseError,
  TimeoutError,
  ValidationError,
} from './errors';
import { nextRetry, resolveRetry } from './retry-policy';
import { safe } from './safe';

import type { SafeResult } from './safe';
import type {
  ApiRequestOptions,
  ApiResponse,
  HttpMethod,
  InferSchema,
} from './types';

const sleep = (ms: number) => new Promise((resolve) => setTimeout(resolve, ms));

/**
 * `mergeOptions()` unconditionally sets `method`/`path`/`requestId` — narrowing them
 * to require here lets callers use them without a non-null assertion.
 */
type ResolvedOptions = ApiRequestOptions & {
  method: HttpMethod;
  path: string;
  requestId: string;
};

export class HttpClient {
  private readonly baseUrl: string;
  private readonly defaultOptions: ApiRequestOptions;

  constructor(baseUrl = '', defaultOptions: ApiRequestOptions = {}) {
    this.baseUrl = baseUrl.replace(/\/+$/, '');
    this.defaultOptions = {
      responseType: 'json',
      ...defaultOptions,
    };
  }

  /**
   * The main method that wraps the native fetch.
   */
  async request<T = unknown>(
    path: string,
    options: ApiRequestOptions = {},
  ): Promise<ApiResponse<T>> {
    const mergedOptions: ResolvedOptions = this.mergeOptions(path, options);
    const retryPolicy = resolveRetry(mergedOptions.retry);
    const plugins = mergedOptions.plugins ?? [];

    // Core retry loop. Each iteration is one attempt; `continue` retries.
    for (let attempt = 0; ; attempt++) {
      mergedOptions.retryAttempt = attempt;

      try {
        return await this.attempt<T>(path, mergedOptions, plugins);
      } catch (err) {
        let finalError = this.normalizeError(err, mergedOptions);

        // Phase 1 — recovery. A plugin (e.g., auth) may fix the cause and return
        // a response. The first that returns short-circuits the rest.
        for (const plugin of plugins) {
          if (!plugin.onError) continue;
          try {
            const result = await plugin.onError(finalError, {
              options: mergedOptions,
              // Replays with `mergedOptions`, not the original `options` — a
              // recovery plugin (e.g., auth) mutates `context.options` (that's
              // `mergedOptions`) to leave a mark for the replay, such as the
              // `authRetried` guard against refreshing forever. Spreading the
              // original `options` here would silently drop that mark, and the
              // guard would never trip.
              retry: () =>
                this.request<T>(path, {
                  ...mergedOptions,
                  requestId: mergedOptions.requestId,
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
            await sleep(decision.wait);
            continue;
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

    // `mergedOptions.path`/`.method` are always set by `mergeOptions()` below (it
    // reassigns them unconditionally after the spread) — no `|| path`/`|| 'GET'`
    // fallback needed here, that path is unreachable through this class's API.
    const url = buildUrl(
      mergedOptions.baseUrl || this.baseUrl,
      mergedOptions.path,
      mergedOptions.params,
    );
    const headers = buildHeaders(
      mergedOptions.headers,
      mergedOptions.body,
      mergedOptions.requestId,
    );
    const body = buildBody(mergedOptions.body);

    // Per-attempt timeout: a fresh controller/timer on every call, combined with
    // the caller's own signal (if any). Never mutates `mergedOptions` — that object
    // is shared across retries, so a signal aborted here must not poison later
    // attempts (that was the A1 bug: the old timeout plugin mutated `options.signal`
    // in `onRequest`, which runs per attempt, so an already-aborted signal chained
    // into every subsequent attempt via `AbortSignal.any` and killed retries instantly).
    const timeoutMs = mergedOptions.timeout;
    let timeoutController: AbortController | undefined;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let signal = mergedOptions.signal;

    if (timeoutMs) {
      timeoutController = new AbortController();
      timer = setTimeout(() => timeoutController?.abort(), timeoutMs);
      signal = mergedOptions.signal
        ? AbortSignal.any([mergedOptions.signal, timeoutController.signal])
        : timeoutController.signal;
    }

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
      // Only our own timer firing is an TimeoutError (retryable). The caller's own
      // signal aborting must propagate as-is and must not be retried.
      if (timeoutController?.signal.aborted) {
        // `timeoutController` is only ever created above when `timeoutMs` is truthy,
        // so `timeoutMs` is guaranteed to set here.
        throw new TimeoutError(url, timeoutMs as number);
      }
      throw err;
    } finally {
      if (timer) clearTimeout(timer);
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

  /** Wraps a raw fetch failure into one of our typed errors. */
  private normalizeError(err: unknown, options: ResolvedOptions): Error {
    // TimeoutError is already thrown typed from `attempt()`. ValidationError comes
    // from the `validation` plugin's `onResponse` (refetching never fixes a schema mismatch).
    // ParseError comes from `parseResponseData` (malformed JSON ona 2xx).
    // All three pass through as-is — wrapping any of them into NetworkError
    // would make `nextRetry` treat them as retryable, which they aren't.
    if (
      err instanceof ApiError ||
      err instanceof TimeoutError ||
      err instanceof ValidationError ||
      err instanceof ParseError
    ) {
      return err;
    }

    // `options` here is always `mergedOptions` (see the sole call site below),
    // whose `.path` is unconditionally set by `mergeOptions()` — no fallback needed.
    const url = buildUrl(
      options.baseUrl || this.baseUrl,
      options.path,
      options.params,
    );

    // The caller's own AbortSignal firing (e.g., user canceled in the UI) is not a
    // timeout and not a network failure — propagate as-is so `nextRetry` (which only
    // recognizes ApiError/NetworkError/TimeoutError) doesn't retry a canceled request.
    if (err instanceof DOMException && err.name === 'AbortError') {
      return err;
    }
    return new NetworkError(
      err instanceof Error ? err.message : 'Unknown network error',
      url,
    );
  }

  // --- Convenience Methods ---
  // `O` carries the option object's literal type so `InferSchema` can pick up a
  // `schema` field when present; `T` is the fallback for the schema-less call shape
  // callers already use (`get<Post[]>('/posts')`).

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
   * `{ data, error }` sugar over `get/post/put/patch/delete` — see `safe()` in
   * `./safe`. A getter (not a field set in the constructor) so it stays bound to
   * `this` without extra bookkeeping, at the cost of a new object per access;
   * `client.safe` is called per-request, not in a hot loop, so that's fine.
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

  private mergeOptions(
    path: string,
    options: ApiRequestOptions,
  ): ResolvedOptions {
    const method = (
      options.method ||
      this.defaultOptions.method ||
      'GET'
    ).toUpperCase() as HttpMethod;

    return {
      ...this.defaultOptions,
      ...options,
      path,
      method,
      // Generated once per request so every lifecycle hook shares the same id.
      // Not inherited from defaultOptions, which would collide across requests.
      requestId: options.requestId ?? generateRequestId(),
      headers: {
        ...headersToRecord(this.defaultOptions.headers),
        ...headersToRecord(options.headers),
      },
    };
  }
}
