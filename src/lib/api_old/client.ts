import { ApiError, NetworkError, RateLimitError, TimeoutError } from './errors';
import { ApiLogger } from './logger';
import type {
  ApiClientConfig,
  ApiResponse,
  AuthConfig,
  CacheConfig,
  HttpMethod,
  RequestOptions,
  ResponseType,
  RetryConfig,
} from './types';

/**
 * Universal API client built on native fetch.
 */
export class ApiClient {
  private readonly baseUrl: string;
  private readonly defaultHeaders: Headers;
  private readonly auth?: AuthConfig;
  private readonly timeout: number;
  private readonly cache: CacheConfig;
  private readonly retry: RetryConfig;
  private readonly logger: ApiLogger;

  constructor(config: ApiClientConfig) {
    this.baseUrl = config.baseUrl.replace(/\/+$/, '');
    this.timeout = config.timeout ?? 30_000;
    this.cache = config.cache ?? {};
    this.retry = config.retry ?? {
      maxAttempts: 3,
      initialDelay: 1_000,
      maxDelay: 10_000,
    };
    this.logger = new ApiLogger(config.logger ?? { level: 'error' });
    this.auth = config.auth;

    this.defaultHeaders = new Headers({
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...this.headersToRecord(config.headers),
    });
  }

  // ── Public Methods ───────────────────────────────────────────

  get<T = unknown>(
    path: string,
    options?: Omit<RequestOptions, 'method' | 'body'>,
  ): Promise<ApiResponse<T>> {
    return this.request<T>(path, { ...options, method: 'GET' });
  }

  post<T = unknown>(
    path: string,
    body?: unknown,
    options?: Omit<RequestOptions, 'method' | 'body'>,
  ): Promise<ApiResponse<T>> {
    return this.request<T>(path, { ...options, method: 'POST', body });
  }

  put<T = unknown>(
    path: string,
    body?: unknown,
    options?: Omit<RequestOptions, 'method' | 'body'>,
  ): Promise<ApiResponse<T>> {
    return this.request<T>(path, { ...options, method: 'PUT', body });
  }

  patch<T = unknown>(
    path: string,
    body?: unknown,
    options?: Omit<RequestOptions, 'method' | 'body'>,
  ): Promise<ApiResponse<T>> {
    return this.request<T>(path, { ...options, method: 'PATCH', body });
  }

  delete<T = unknown>(
    path: string,
    options?: Omit<RequestOptions, 'method' | 'body'>,
  ): Promise<ApiResponse<T>> {
    return this.request<T>(path, { ...options, method: 'DELETE' });
  }

  // ── Core Request ──────────────────────────────────────────────

  async request<T = unknown>(
    path: string,
    options: RequestOptions = {},
  ): Promise<ApiResponse<T>> {
    const method = options.method ?? 'GET';
    const url = this.buildUrl(path, options.params);
    const headers = await this.buildHeaders(options);
    const fetchOptions = this.buildFetchOptions(method, headers, options);

    const maxAttempts = options.skipRetry ? 1 : this.retry.maxAttempts;
    let lastError: Error | undefined;

    for (let attempt = 1; attempt <= maxAttempts; attempt++) {
      const start = performance.now();

      try {
        this.logger.logRequest(method, url, options.body);

        const response = await this.fetchWithTimeout(
          url,
          fetchOptions,
          options.signal,
        );
        const duration = Math.round(performance.now() - start);

        // Rate limiting with retry
        if (response.status === 429 && attempt < maxAttempts) {
          const retryAfter = this.parseRetryAfter(response);
          this.logger.warn('Rate limited, retrying', {
            url,
            attempt,
            retryAfter,
          });
          await this.delay(
            retryAfter ? retryAfter * 1000 : this.getBackoffDelay(attempt),
          );
          continue;
        }

        // Handle errors
        if (!response.ok) {
          const error = await this.handleError(
            response,
            url,
            method,
            duration,
            options,
          );
          if (error) throw error;
        }

        // Handle 204 No Content
        if (response.status === 204) {
          this.logger.logResponse(method, url, response.status, duration, null);
          return {
            data: null as T,
            status: response.status,
            statusText: response.statusText,
            headers: response.headers,
            duration,
          };
        }

        // Parse response
        const data = await this.parseResponseBody<T>(
          response,
          options.responseType ?? 'json',
        );
        this.logger.logResponse(method, url, response.status, duration, data);

        return {
          data,
          status: response.status,
          statusText: response.statusText,
          headers: response.headers,
          duration,
        };
      } catch (error) {
        const duration = Math.round(performance.now() - start);
        lastError = error instanceof Error ? error : new Error(String(error));

        // Don't retry on non-retryable errors
        if (this.isNonRetryable(error)) {
          this.logger.logError(method, url, error, duration, attempt);
          throw error;
        }

        this.logger.logError(method, url, error, duration, attempt);

        if (attempt < maxAttempts) {
          const backoff = this.getBackoffDelay(attempt);
          this.logger.warn('Retrying request', {
            url,
            attempt,
            nextDelay: backoff,
          });
          await this.delay(backoff);
        }
      }
    }

    throw (
      lastError ?? new NetworkError('All retry attempts failed', url, method)
    );
  }

  // ── Private Helpers ───────────────────────────────────────────

  private async handleError(
    response: Response,
    url: string,
    method: string,
    duration: number,
    options: RequestOptions,
  ): Promise<Error | null> {
    // Try token refresh on 401
    if (
      response.status === 401 &&
      this.auth?.refreshToken &&
      !options._isRetry
    ) {
      this.logger.warn('Token expired, attempting refresh', { url });
      try {
        await this.auth.refreshToken();
        return null; // Signal to retry
      } catch {
        this.logger.error('Token refresh failed', {});
      }
    }

    const errorBody = await this.safeReadBody(response);
    const errorInfo = {
      status: response.status,
      statusText: response.statusText,
      url,
      method,
      body: errorBody,
      duration,
    };

    if (response.status === 429) {
      return new RateLimitError(errorInfo, this.parseRetryAfter(response));
    }

    const errorMessage =
      typeof errorBody === 'object' &&
      errorBody !== null &&
      'message' in errorBody
        ? String((errorBody as Record<string, unknown>).message)
        : `HTTP ${response.status}: ${response.statusText}`;

    return new ApiError(errorMessage, errorInfo);
  }

  private isNonRetryable(error: unknown): boolean {
    if (error instanceof TimeoutError || this.isAbortError(error)) return true;
    if (error instanceof ApiError) {
      return error.status < 500 && error.status !== 429;
    }
    return false;
  }

  private async buildHeaders(options: RequestOptions): Promise<Headers> {
    const headers = new Headers(this.defaultHeaders);

    if (options.headers) {
      for (const [key, value] of Object.entries(
        this.headersToRecord(options.headers),
      )) {
        headers.set(key, value);
      }
    }

    if (!options.body) {
      headers.delete('Content-Type');
    }

    const authHeader = await this.getAuthHeader();
    if (authHeader) {
      headers.set(authHeader[0], authHeader[1]);
    }

    return headers;
  }

  private async getAuthHeader(): Promise<[string, string] | null> {
    if (!this.auth) return null;

    const token =
      typeof this.auth.token === 'function'
        ? await this.auth.token()
        : this.auth.token;
    if (!token) return null;

    const headerDefaults = {
      bearer: ['Authorization', `Bearer ${token}`] as const,
      'api-key': ['X-API-Key', token] as const,
      custom: [this.auth.headerName ?? 'Authorization', token] as const,
    };

    const [name, value] = headerDefaults[this.auth.type];
    return [this.auth.headerName ?? name, value];
  }

  private buildUrl(
    path: string,
    params?: Record<string, string | number | boolean | undefined>,
  ): string {
    const base = path.startsWith('http') ? '' : this.baseUrl;
    const separator = path.startsWith('/') ? '' : '/';
    const url = new URL(`${base}${separator}${path}`);

    if (params) {
      for (const [key, value] of Object.entries(params)) {
        if (value !== undefined) {
          url.searchParams.set(key, String(value));
        }
      }
    }

    return url.toString();
  }

  private buildFetchOptions(
    method: HttpMethod,
    headers: Headers,
    options: RequestOptions,
  ): RequestInit {
    const cache = { ...this.cache, ...options.cache };
    const init: RequestInit & { next?: Record<string, unknown> } = {
      method,
      headers,
    };

    if (options.body) {
      const isBinary =
        options.body instanceof FormData ||
        options.body instanceof File ||
        options.body instanceof Blob;

      init.body = (
        isBinary ? options.body : JSON.stringify(options.body)
      ) as BodyInit;

      if (isBinary) {
        headers.delete('Content-Type');
      }
    }

    if (cache.revalidate === false) {
      init.cache = 'no-store';
    } else {
      init.next = {
        ...(cache.revalidate !== undefined
          ? { revalidate: cache.revalidate }
          : {}),
        ...(cache.tags ? { tags: cache.tags } : {}),
      };
    }

    return init;
  }

  private fetchWithTimeout(
    url: string,
    options: RequestInit,
    externalSignal?: AbortSignal,
  ): Promise<Response> {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), this.timeout);

    if (externalSignal) {
      externalSignal.addEventListener('abort', () => controller.abort());
    }

    return fetch(url, { ...options, signal: controller.signal })
      .finally(() => clearTimeout(timeoutId))
      .catch((error) => {
        if (this.isAbortError(error)) {
          if (externalSignal?.aborted) throw error;
          throw new TimeoutError(url, this.timeout);
        }
        throw new NetworkError(
          error instanceof Error ? error.message : 'Network request failed',
          url,
          options.method ?? 'GET',
        );
      });
  }

  private parseResponseBody<T>(
    response: Response,
    responseType: ResponseType,
  ): Promise<T> {
    switch (responseType) {
      case 'json':
        return response.json() as Promise<T>;
      case 'text':
        return response.text() as Promise<T>;
      case 'blob':
        return response.blob() as Promise<T>;
      case 'arraybuffer':
        return response.arrayBuffer() as Promise<T>;
    }
  }

  private getBackoffDelay(attempt: number): number {
    const base = this.retry.initialDelay * 2 ** (attempt - 1);
    const jitter = Math.random() * 0.3 * base;
    return Math.min(base + jitter, this.retry.maxDelay);
  }

  private parseRetryAfter(response: Response): number | null {
    const header = response.headers.get('Retry-After');
    if (!header) return null;

    const seconds = Number.parseInt(header, 10);
    if (!Number.isNaN(seconds)) return seconds;

    const date = Date.parse(header);
    if (!Number.isNaN(date)) {
      return Math.max(0, Math.ceil((date - Date.now()) / 1000));
    }

    return null;
  }

  private delay(ms: number): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, ms));
  }

  private headersToRecord(headers?: HeadersInit): Record<string, string> {
    if (!headers) return {};
    if (headers instanceof Headers)
      return Object.fromEntries(headers.entries());
    if (Array.isArray(headers)) return Object.fromEntries(headers);
    return headers as Record<string, string>;
  }

  private async safeReadBody(response: Response): Promise<unknown> {
    try {
      return await response.json();
    } catch {
      try {
        return await response.text();
      } catch {
        return null;
      }
    }
  }

  private isAbortError(error: unknown): boolean {
    return error instanceof DOMException && error.name === 'AbortError';
  }
}
