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
