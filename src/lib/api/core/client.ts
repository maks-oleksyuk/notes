import { ApiError, NetworkError, TimeoutError } from './errors';
import type {
  ApiRequestOptions,
  ApiResponse,
  HttpMethod,
  ResponseType,
} from './types';

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
    const url = this.buildUrl(mergedOptions);
    const headers = this.buildHeaders(mergedOptions);
    const body = this.buildBody(mergedOptions, headers);

    const start = performance.now();

    try {
      // 1. Handle Request Hooks (Interceptors)
      if (mergedOptions.onRequest) {
        await mergedOptions.onRequest(mergedOptions);
      }

      const response = await fetch(url, {
        ...mergedOptions,
        method: mergedOptions.method,
        headers,
        body,
      });

      const duration = Math.round(performance.now() - start);

      // 2. Handle HTTP Errors
      if (!response.ok) {
        const errorData = await this.parseErrorData(response);
        const error = new ApiError(
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

        if (mergedOptions.onResponseError) {
          await mergedOptions.onResponseError(error);
        }

        throw error;
      }

      // 3. Parse Successful Response
      const data = await this.parseResponseData<T>(
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

      // 4. Handle Response Hooks
      if (mergedOptions.onResponse) {
        await mergedOptions.onResponse(apiResponse as ApiResponse<unknown>);
      }

      return apiResponse;
    } catch (error) {
      // If it's already an ApiError, just re-throw it
      if (error instanceof ApiError) throw error;

      // Handle native Fetch Abort/Timeout
      if (error instanceof DOMException && error.name === 'AbortError') {
        throw new TimeoutError(url, 0);
      }

      // Generic Network Failure
      throw new NetworkError(
        error instanceof Error ? error.message : 'Unknown network error',
        url,
      );
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
      headers: {
        ...this.headersToRecord(this.defaultOptions.headers),
        ...this.headersToRecord(options.headers),
      },
    };
  }

  private buildUrl(options: ApiRequestOptions): string {
    const { baseUrl, path, params } = options;
    const base = baseUrl || this.baseUrl;

    // Smart URL joining (inspired by ofetch/ufo)
    // 1. Remove trailing slash from base
    // 2. Remove leading slash from path
    // 3. Join with a single slash
    const cleanBase = base.replace(/\/+$/, '');
    const cleanPath = path?.replace(/^\/+/, '') || '';

    let urlStr = cleanPath.startsWith('http')
      ? cleanPath
      : `${cleanBase}/${cleanPath}`;

    // Fix potential double slashes if base was empty
    if (!cleanBase && !cleanPath.startsWith('http')) {
      urlStr = cleanPath;
    }

    const urlObj = new URL(urlStr, 'http://localhost');

    // Add query parameters
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null) {
          urlObj.searchParams.set(key, String(value));
        }
      });
    }

    // If it was a relative path, return the path part, otherwise full URL
    return cleanPath.startsWith('http') || cleanBase.startsWith('http')
      ? urlObj.toString()
      : `${urlObj.pathname}${urlObj.search}`;
  }

  private buildHeaders(options: ApiRequestOptions): Headers {
    const headers = new Headers(options.headers as HeadersInit);

    // Auto-set Content-Type for objects, but not for files/blobs
    if (options.body && !headers.has('Content-Type')) {
      if (!(options.body instanceof FormData || options.body instanceof Blob)) {
        headers.set('Content-Type', 'application/json');
      }
    }

    return headers;
  }

  private buildBody(
    options: ApiRequestOptions,
    _headers: Headers,
  ): BodyInit | null {
    if (!options.body) return null;

    if (options.body instanceof FormData || options.body instanceof Blob) {
      return options.body;
    }

    if (typeof options.body === 'object') {
      return JSON.stringify(options.body);
    }

    return String(options.body);
  }

  private async parseResponseData<T>(
    response: Response,
    type: ResponseType,
  ): Promise<T> {
    if (response.status === 204) return null as unknown as T;

    try {
      switch (type) {
        case 'json':
          return (await response.json()) as T;
        case 'text':
          return (await response.text()) as unknown as T;
        case 'blob':
          return (await response.blob()) as unknown as T;
        case 'arraybuffer':
          return (await response.arrayBuffer()) as unknown as T;
        case 'stream':
          return response.body as unknown as T;
        default:
          return (await response.json()) as T;
      }
    } catch {
      return null as unknown as T;
    }
  }

  private async parseErrorData(response: Response): Promise<unknown> {
    try {
      // Try JSON first, fall back to text
      const text = await response.text();
      try {
        return JSON.parse(text);
      } catch {
        return text;
      }
    } catch {
      return null;
    }
  }

  private headersToRecord(headers?: HeadersInit): Record<string, string> {
    if (!headers) return {};
    if (headers instanceof Headers)
      return Object.fromEntries(headers.entries());
    if (Array.isArray(headers)) return Object.fromEntries(headers);
    return headers as Record<string, string>;
  }
}
