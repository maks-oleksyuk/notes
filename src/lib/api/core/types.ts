import type { ZodType } from 'zod';

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
   *
   * Object like { page: 1, search: 'test' } becomes ?page=1&search=test.
   * An array value repeats the key: { tag: ['a', 'b'] } becomes ?tag=a&tag=b.
   */
  params?: Record<
    string,
    | string
    | number
    | boolean
    | undefined
    | null
    | Array<string | number | boolean>
  >;

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
   * Observers (e.g., the logger) read it to mark retried requests.
   */
  retryAttempt?: number;

  /**
   * Set by the `auth` plugin's `onError` after it refreshes the token and replays
   * the request once. A second 401 on the replay means the session itself is dead
   * (not just an expired access token) — the plugin checks this flag to stop after
   * one refresh instead of refreshing forever. Typed field, not `(options as any)`.
   */
  authRetried?: boolean;

  /**
   * Retry policy for transient failures. Omit or set `false` to disable.
   * Retries live in the core request loop, not in a plugin.
   */
  retry?: RetryOptions | false;

  /**
   * Per-attempt timeout in ms. A fresh `AbortController` is created for every
   * attempt in the core request loop (not a plugin) — see `HttpClient.attempt`.
   * Omit or `0` to disable.
   *
   * Bounds only the time to response *headers*: the timer is cleared once
   * `fetch` resolves, so reading a slow body (`response.json()`) is not
   * covered (same trade-off as ofetch). A caller-supplied `signal` still
   * aborts the body read.
   */
  timeout?: number;

  /**
   * Opt-in: share a single in-flight `GET` request across callers asking for
   * the same URL at the same time (map keyed by the resolved URL, in
   * `HttpClient.request`). Off by default — it changes observable behavior
   * (fewer network calls, one shared response object), so callers must ask
   * for it explicitly. Ignored for non-`GET` methods.
   *
   * Also ignored (silently) when the request carries a per-user identity —
   * the `auth` plugin or an `Authorization` header. The map is keyed by URL
   * only and lives on the client instance, which on the server is a
   * module-scope singleton shared across users; deduping authenticated GETs
   * would hand one user's response to another.
   */
  dedupe?: boolean;

  /**
   * Zod schema the response body must satisfy (checked by the `validation` plugin's
   * `onResponse`, thrown as `ValidationError` — never retried, see `nextRetry`). When
   * present, `HttpClient.get/post/put/patch/delete` infers the response type from it —
   * see `InferSchema`.
   */
  schema?: ZodType;

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
  onResponse?: (response: ApiResponse) => void | Promise<void>;
  /**
   * Called only when the *final* error is an `ApiError` (an HTTP error
   * status) — not for network/timeout/validation/abort failures. For every
   * final error regardless of type, use a plugin's `onFinalError` instead.
   */
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
  /**
   * Methods eligible for retry. Defaults to the idempotent ones — a `POST` that
   * timed out may already have been executed server-side, and blindly retrying it
   * can create a duplicate (e.g., a duplicate order). Opt `POST`/`PATCH` in explicitly
   * only where the endpoint is known to be safe to repeat.
   */
  methods?: HttpMethod[];
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
 *  - **recovery** — `onError` may return a value to recover the request (e.g., auth
 *    refreshing a token). The first plugin that returns stops the chain.
 *  - **observation** — `onRetry` / `onFinalError` are notifications for observers
 *    (e.g., the logger); they can't change the outcome, so their order is irrelevant.
 */
export interface ApiPlugin {
  name: string;
  onRequest?: (options: ApiRequestOptions) => void | Promise<void>;
  onResponse?: (
    response: ApiResponse,
    options: ApiRequestOptions,
  ) => void | Promise<void>;
  onError?: (
    error: Error,
    context: {
      options: ApiRequestOptions;
      retry: () => Promise<ApiResponse>;
    },
  ) => undefined | Promise<unknown>;
  /** Notified before the core loop retries a transient error. */
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
 * Resolves the response type for `HttpClient.get/post/put/...`: if `O` carries a
 * `schema`, infer from it (better-fetch pattern — no manual generic needed); otherwise
 * fall back to the explicit `T` the caller passed (or `unknown`).
 */
export type InferSchema<O, T> = O extends { schema: ZodType<infer S> } ? S : T;

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
