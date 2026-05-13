/**
 * API Client types and interfaces.
 */

export type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

export type LogLevel = 'debug' | 'info' | 'warn' | 'error' | 'silent';

/**
 * Expected response type.
 * - "json": parse as JSON (default)
 * - "text": plain text
 * - "blob": binary data (files, images)
 * - "arraybuffer": raw binary buffer
 */
export type ResponseType = 'json' | 'text' | 'blob' | 'arraybuffer';

/**
 * Auth configuration.
 *
 * - "bearer": sends Authorization: Bearer <token>
 * - "api-key": sends the token via a custom header (default: X-API-Key)
 * - "custom": provide your own header name and value format
 *
 * Token can be a string (static) or a function (dynamic — e.g. from session).
 * Refresh token is called on 401 responses to get a new token.
 */
export interface AuthConfig {
  type: 'bearer' | 'api-key' | 'custom';
  token: string | (() => string | Promise<string>);
  headerName?: string;
  /** Called on 401 to refresh the token and retry the request */
  refreshToken?: () => string | Promise<string>;
}

export interface CacheConfig {
  /**
   * Next.js revalidation in seconds.
   * - number: revalidate after N seconds (ISR)
   * - 0: always revalidate (like network-first)
   * - false: never cache (no-store)
   */
  revalidate?: number | false;
  /** Next.js cache tags for on-demand revalidation via revalidateTag() */
  tags?: string[];
}

export interface RetryConfig {
  maxAttempts: number;
  initialDelay: number;
  maxDelay: number;
}

export interface LoggerConfig {
  /** Minimum level to log. Default: "error" */
  level: LogLevel;
  /** Include request/response bodies in debug logs. Default: false */
  logBodies?: boolean;
}

export interface ApiClientConfig {
  baseUrl: string;
  headers?: HeadersInit;
  auth?: AuthConfig;
  timeout?: number;
  cache?: CacheConfig;
  retry?: RetryConfig;
  logger?: LoggerConfig;
}

export interface RequestOptions {
  method?: HttpMethod;
  headers?: HeadersInit;
  body?: unknown;
  params?: Record<string, string | number | boolean | undefined>;
  cache?: CacheConfig;
  skipRetry?: boolean;
  signal?: AbortSignal;
  /** How to parse the response. Default: "json" */
  responseType?: ResponseType;
  /** Internal flag to prevent infinite retry loops */
  _isRetry?: boolean;
}

export interface ApiResponse<T = unknown> {
  data: T;
  status: number;
  statusText: string;
  headers: Headers;
  duration: number;
}

export interface ApiErrorInfo {
  status: number;
  statusText: string;
  url: string;
  method: string;
  body?: unknown;
  duration: number;
}
