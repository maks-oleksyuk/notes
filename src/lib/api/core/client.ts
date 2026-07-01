import {
  buildBody,
  buildHeaders,
  buildUrl,
  generateRequestId,
  headersToRecord,
  parseErrorData,
  parseResponseData,
} from '../utils';
import { ApiError, NetworkError, TimeoutError } from './errors';
import { nextRetry, resolveRetry } from './retry-policy';
import type { ApiRequestOptions, ApiResponse, HttpMethod } from './types';

const sleep = (ms: number) => new Promise((resolve) => setTimeout(resolve, ms));

export class HttpClient {
  private readonly baseUrl: string;
  private readonly defaultOptions: ApiRequestOptions;

  constructor(baseUrl = '', defaultOptions: ApiRequestOptions = {}) {
    // We normalize the baseUrl so it doesn't end with a trailing slash
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
    const mergedOptions = this.mergeOptions(path, options);
    const retryPolicy = resolveRetry(mergedOptions.retry);
    const plugins = mergedOptions.plugins ?? [];

    // Core retry loop. Each iteration is one attempt; `continue` retries.
    for (let attempt = 0; ; attempt++) {
      mergedOptions.retryAttempt = attempt;

      try {
        return await this.attempt<T>(path, mergedOptions, plugins);
      } catch (err) {
        let finalError = this.normalizeError(err, mergedOptions, path);

        // Phase 1 — recovery. A plugin (e.g. auth) may fix the cause and return
        // a response. The first that returns short-circuits the rest.
        for (const plugin of plugins) {
          if (!plugin.onError) continue;
          try {
            const result = await plugin.onError(finalError, {
              options: mergedOptions,
              retry: () =>
                this.request<T>(path, {
                  ...options,
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
          const decision = nextRetry(finalError, attempt, retryPolicy);
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
    path: string,
    mergedOptions: ApiRequestOptions,
    plugins: NonNullable<ApiRequestOptions['plugins']>,
  ): Promise<ApiResponse<T>> {
    for (const plugin of plugins) {
      if (plugin.onRequest) await plugin.onRequest(mergedOptions);
    }
    if (mergedOptions.onRequest) await mergedOptions.onRequest(mergedOptions);

    const url = buildUrl(
      mergedOptions.baseUrl || this.baseUrl,
      mergedOptions.path || path,
      mergedOptions.params,
    );
    const headers = buildHeaders(
      mergedOptions.headers,
      mergedOptions.body,
      mergedOptions.requestId,
    );
    const body = buildBody(mergedOptions.body);

    const start = performance.now();
    const response = await fetch(url, {
      ...mergedOptions,
      method: mergedOptions.method,
      headers,
      body,
    });
    const duration = Math.round(performance.now() - start);

    if (!response.ok) {
      const errorData = await parseErrorData(response);
      throw new ApiError(`HTTP Error ${response.status}: ${response.statusText}`, {
        status: response.status,
        statusText: response.statusText,
        url,
        method: mergedOptions.method || 'GET',
        data: errorData,
        headers: response.headers,
      });
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
      if (plugin.onResponse) await plugin.onResponse(apiResponse, mergedOptions);
    }
    if (mergedOptions.onResponse) {
      await mergedOptions.onResponse(apiResponse as ApiResponse<unknown>);
    }

    return apiResponse;
  }

  /** Wraps a raw fetch failure into one of our typed errors. */
  private normalizeError(
    err: unknown,
    options: ApiRequestOptions,
    path: string,
  ): Error {
    if (err instanceof ApiError) return err;

    const url = buildUrl(
      options.baseUrl || this.baseUrl,
      options.path || path,
      options.params,
    );

    if (err instanceof DOMException && err.name === 'AbortError') {
      return new TimeoutError(url, 0);
    }
    return new NetworkError(
      err instanceof Error ? err.message : 'Unknown network error',
      url,
    );
  }

  // --- Convenience Methods ---

  get<T = unknown>(
    path: string,
    options: Omit<ApiRequestOptions, 'method' | 'body'> = {},
  ): Promise<ApiResponse<T>> {
    return this.request<T>(path, { ...options, method: 'GET' });
  }

  post<T = unknown>(
    path: string,
    body?: unknown,
    options: Omit<ApiRequestOptions, 'method' | 'body'> = {},
  ): Promise<ApiResponse<T>> {
    return this.request<T>(path, { ...options, method: 'POST', body });
  }

  put<T = unknown>(
    path: string,
    body?: unknown,
    options: Omit<ApiRequestOptions, 'method' | 'body'> = {},
  ): Promise<ApiResponse<T>> {
    return this.request<T>(path, { ...options, method: 'PUT', body });
  }

  patch<T = unknown>(
    path: string,
    body?: unknown,
    options: Omit<ApiRequestOptions, 'method' | 'body'> = {},
  ): Promise<ApiResponse<T>> {
    return this.request<T>(path, { ...options, method: 'PATCH', body });
  }

  delete<T = unknown>(
    path: string,
    options: Omit<ApiRequestOptions, 'method' | 'body'> = {},
  ): Promise<ApiResponse<T>> {
    return this.request<T>(path, { ...options, method: 'DELETE' });
  }

  // --- Private Helpers ---

  private mergeOptions(
    path: string,
    options: ApiRequestOptions,
  ): ApiRequestOptions {
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
