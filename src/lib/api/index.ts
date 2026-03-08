// ── Core ApiClient ───────────────────────────────────────────────
// Only export the base client, not concrete implementations

export { ApiClient } from './client';
export { ApiError, NetworkError, RateLimitError, TimeoutError } from './errors';
export type {
  ApiClientConfig,
  ApiResponse,
  AuthConfig,
  CacheConfig,
  HttpMethod,
  LoggerConfig,
  LogLevel,
  RequestOptions,
  ResponseType,
  RetryConfig,
} from './types';
