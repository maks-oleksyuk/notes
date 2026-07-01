/**
 * Supported HTTP methods.
 * Using a union type ensures we don't have typos in our request calls.
 */
export type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE' | 'HEAD';

/**
 * How we want the client to parse the response.
 * 'json' is the most common, but we might need 'blob' for images or 'text' for logs.
 */
export type ResponseType = 'json' | 'text' | 'blob' | 'arraybuffer' | 'stream';

/**
 * Options for every single request.
 * We extend the native RequestInit (from fetch) but make it better.
 */
export interface ApiRequestOptions
  extends Omit<RequestInit, 'method' | 'body'> {
  /**
   * Base URL to override the client default if needed.
   */
  baseUrl?: string;

  /**
   * The request path (e.g., /posts).
   */
  path?: string;

  /**
   * The HTTP method. Defaults to GET.
   */
  method?: HttpMethod;

  /** 
   * Query parameters. 

   * Object like { page: 1, search: 'test' } becomes ?page=1&search=test
   */
  params?: Record<string, string | number | boolean | undefined | null>;

  /**
   * Request body. Can be an object (for JSON), FormData, or Blob.
   */
  body?: unknown;

  /**
   * Expected response format. Defaults to 'json'.
   */
  responseType?: ResponseType;

  /**
   * Unique ID for tracing this specific request in logs.
   */
  requestId?: string;

  /**
   * Current attempt index, set by the core retry loop (0 = first try).
   * Observers (e.g. the logger) read it to mark retried requests.
   */
  retryAttempt?: number;

  /**
   * Retry policy for transient failures. Omit or set `false` to disable.
   * Retries live in the core request loop, not in a plugin.
   */
  retry?: RetryOptions | false;

  /**
   * Next.js specific options for the fetch cache (App Router).
   */
  next?: {
    revalidate?: number | false;
    tags?: string[];
  };

  /**
   * Hooks (Interceptors) - simple version
   */
  onRequest?: (options: ApiRequestOptions) => void | Promise<void>;
  onResponse?: (response: ApiResponse<unknown>) => void | Promise<void>;
  onResponseError?: (error: Error) => void | Promise<void>;

  /**
   * List of plugins to extend the request lifecycle.
   */
  plugins?: ApiPlugin[];
}

/**
 * Retry policy for transient failures, handled by the core request loop.
 */
export interface RetryOptions {
  limit?: number;
  /** Base delay in ms; grows exponentially per attempt. */
  delay?: number;
  /** Upper bound for a single backoff wait, in ms. */
  maxDelay?: number;
  /** Give up (don't retry) if `Retry-After` asks for longer than this, in ms. */
  maxRetryAfter?: number;
  statusCodes?: number[];
  /** Honor the server's `Retry-After` header on 429/503. Default true. */
  respectRetryAfter?: boolean;
}

/** Details of a retry, passed to the `onRetry` observer before it is scheduled. */
export interface RetryInfo {
  /** Retry number, 1-based (1 = first retry). */
  attempt: number;
  limit: number;
  /** Milliseconds this retry will wait before firing. */
  wait: number;
  /** Whether `wait` came from the server's `Retry-After` header. */
  fromRetryAfter: boolean;
  error: Error;
  requestId?: string;
  method?: string;
  path?: string;
}

/**
 * Interface representing a plugin that hooks into the request lifecycle.
 *
 * Two roles, kept separate:
 *  - **recovery** — `onError` may return a value to recover the request (e.g. auth
 *    refreshing a token). The first plugin that returns stops the chain.
 *  - **observation** — `onRetry` / `onFinalError` are notifications for observers
 *    (e.g. the logger); they can't change the outcome, so their order is irrelevant.
 */
export interface ApiPlugin {
  name: string;
  onRequest?: (options: ApiRequestOptions) => void | Promise<void>;
  onResponse?: (
    response: ApiResponse<any>,
    options: ApiRequestOptions,
  ) => void | Promise<void>;
  onError?: (
    error: Error,
    context: {
      options: ApiRequestOptions;
      retry: () => Promise<ApiResponse<any>>;
    },
  ) => undefined | Promise<any>;
  /** Notified before a transient error is retried by the core loop. */
  onRetry?: (info: RetryInfo) => void | Promise<void>;
  /** Notified when a request fails for good (no recovery, no retries left). */
  onFinalError?: (
    error: Error,
    options: ApiRequestOptions,
  ) => void | Promise<void>;
}

/**
 * The standardized response object that every request returns.
 * T is the generic type for the data (e.g., User, Post[]).
 */
export interface ApiResponse<T = unknown> {
  /** The parsed data (JSON, string, etc.) */
  data: T;
  /** HTTP Status code (200, 201, etc.) */
  status: number;
  /** Status text from the server */
  statusText: string;
  /** Response headers */
  headers: Headers;
  /** The final URL after resolving base and params */
  url: string;
  /** How long the request took in milliseconds */
  duration: number;
}

/**
 * Internal info used to construct rich Error objects.
 */
export interface ApiErrorInfo {
  status: number;
  statusText: string;
  url: string;
  method: string;
  data?: unknown;
  headers?: Headers;
}
