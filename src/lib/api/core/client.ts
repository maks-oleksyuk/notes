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
import type { ApiRequestOptions, ApiResponse, HttpMethod } from './types';

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

    try {
      // 1. Run plugins' onRequest hooks
      if (mergedOptions.plugins) {
        for (const plugin of mergedOptions.plugins) {
          if (plugin.onRequest) {
            await plugin.onRequest(mergedOptions);
          }
        }
      }

      // Run simple onRequest hook
      if (mergedOptions.onRequest) {
        await mergedOptions.onRequest(mergedOptions);
      }

      // 2. Build final request parameters based on potentially mutated options
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

      // 3. Handle HTTP Errors
      if (!response.ok) {
        const errorData = await parseErrorData(response);
        throw new ApiError(
          `HTTP Error ${response.status}: ${response.statusText}`,
          {
            status: response.status,
            statusText: response.statusText,
            url,
            method: mergedOptions.method || 'GET',
            data: errorData,
            headers: response.headers,
          },
        );
      }

      // 4. Parse Successful Response
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

      // 5. Run plugins' onResponse hooks
      if (mergedOptions.plugins) {
        for (const plugin of mergedOptions.plugins) {
          if (plugin.onResponse) {
            await plugin.onResponse(apiResponse, mergedOptions);
          }
        }
      }

      // Run simple onResponse hook
      if (mergedOptions.onResponse) {
        await mergedOptions.onResponse(apiResponse as ApiResponse<unknown>);
      }

      return apiResponse;
    } catch (err) {
      let finalError: Error;

      if (err instanceof ApiError) {
        finalError = err;
      } else {
        const fallbackUrl = buildUrl(
          mergedOptions.baseUrl || this.baseUrl,
          mergedOptions.path || path,
          mergedOptions.params,
        );

        if (err instanceof DOMException && err.name === 'AbortError') {
          finalError = new TimeoutError(fallbackUrl, 0);
        } else {
          finalError = new NetworkError(
            err instanceof Error ? err.message : 'Unknown network error',
            fallbackUrl,
          );
        }
      }

      // Execute plugin onError hooks
      if (mergedOptions.plugins) {
        for (const plugin of mergedOptions.plugins) {
          if (plugin.onError) {
            try {
              const result = await plugin.onError(finalError, {
                options: mergedOptions,
                retry: () => this.request<T>(path, options),
              });
              if (result) {
                return result;
              }
            } catch (pluginError) {
              if (pluginError instanceof Error) {
                finalError = pluginError;
              }
            }
          }
        }
      }

      // Run simple onResponseError hook
      if (mergedOptions.onResponseError && finalError instanceof ApiError) {
        await mergedOptions.onResponseError(finalError);
      }

      throw finalError;
    }
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
