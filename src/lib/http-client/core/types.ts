import type { ZodType } from 'zod';

export type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE' | 'HEAD';

export type ResponseType = 'json' | 'text' | 'blob' | 'arraybuffer' | 'stream';

/** Extends the native `RequestInit`, redefining `method`/`body` below. */
export interface ApiRequestOptions
  extends Omit<RequestInit, 'method' | 'body'> {
  /** Overrides the client's default base URL for this request. */
  baseUrl?: string;

  path?: string;

  /** Defaults to `GET`. */
  method?: HttpMethod;

  /**
   * `{ page: 1, search: 'test' }` becomes `?page=1&search=test`.
   * An array value repeats the key: `{ tag: ['a', 'b'] }` becomes `?tag=a&tag=b`.
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

  body?: unknown;

  /** Defaults to `'json'`. */
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
   * Set by the `auth` plugin's `onError` after it refreshes the token and replays the
   * request once. A second 401 on the replay means the session itself is dead (not just an
   * expired access token) — the plugin checks this flag to stop after one refresh instead
   * of refreshing forever. Typed field, not `(options as any)`.
   */
  authRetried?: boolean;

  /**
   * Set by the `auth` plugin's `onError` to the token `provider.refreshToken()` just
   * returned, so the replay's `onRequest` uses *that* value directly instead of calling
   * `provider.getToken()` again. Necessary because `getToken()` isn't guaranteed to observe
   * a refresh that just happened in the same call chain — e.g., a provider backed by a
   * request-scoped `headers()` snapshot (Next.js) can't see a cookie the refresh just wrote
   * into the response, so a naive re-read would return the *pre-refresh* token and the
   * replay would fail with the same 401 forever. Typed field, not `(options as any)`.
   */
  refreshedToken?: string;

  /**
   * Retry policy for transient failures. Omit or set `false` to disable.
   * Retries live in the core request loop, not in a plugin.
   */
  retry?: RetryOptions | false;

  /**
   * Per-attempt timeout in ms. A fresh `AbortController` is created for every attempt in
   * the core request loop (not a plugin) — see `HttpClient.attempt`. Omit or `0` to disable.
   *
   * Bounds only the time to response *headers*: the timer is cleared once `fetch` resolves,
   * so reading a slow body (`response.json()`) is not covered (same trade-off as ofetch).
   * A caller-supplied `signal` still aborts the body read.
   */
  timeout?: number;

  /**
   * Opt-in: share a single in-flight `GET` request across callers asking for the same URL
   * at the same time (map keyed by the resolved URL, in `HttpClient.request`). Off by
   * default — it changes observable behavior (fewer network calls, one shared response
   * object), so callers must ask for it explicitly. Ignored for non-`GET` methods.
   *
   * Also ignored (silently) when the request carries a per-user identity — the `auth`
   * plugin or an `Authorization` header. The map is keyed by URL only and lives on the
   * client instance, which on the server is a module-scope singleton shared across users;
   * deduping authenticated GETs would hand one user's response to another.
   */
  dedupe?: boolean;

  /**
   * Zod schema the response body must satisfy (checked by the `validation` plugin's
   * `onResponse`, thrown as `ValidationError` — never retried, see `nextRetry`).
   * When present, `HttpClient.get/post/put/patch/delete` infers the response type from it
   * — see `InferSchema`.
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
   * Whether to send the auto-generated `X-Request-Id` correlation header (default: `true`).
   * Next.js's fetch Data Cache keys a cached response on the full request — URL *and*
   * headers included — so a header that's a fresh random value on every call (which
   * `X-Request-Id` always is, see `requestId` above) makes every request look unique to
   * Next and permanently defeats `next.revalidate`, no matter its value. Set `false` on a
   * client whose reads should be cacheable; the backend then simply won't see a
   * correlation id on those specific calls.
   */
  sendRequestIdHeader?: boolean;

  onRequest?: (options: ApiRequestOptions) => void | Promise<void>;

  onResponse?: (response: ApiResponse) => void | Promise<void>;

  /**
   * Called only when the *final* error is an `ApiError` (an HTTP error status) — not for
   * network/timeout/validation/abort failures. For every final error regardless of type,
   * use a plugin's `onFinalError` instead.
   */
  onResponseError?: (error: Error) => void | Promise<void>;

  plugins?: ApiPlugin[];
}

/** Handled by the core request loop, not a plugin. */
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
   * Methods eligible for retry. Defaults to the idempotent ones — a `POST` that timed out
   * may already have been executed server-side, and blindly retrying it can create a
   * duplicate (e.g., a duplicate order). Opt `POST`/`PATCH` in explicitly only where the
   * endpoint is known to be safe to repeat.
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
 * Two roles, kept separate:
 *  - **recovery** — `onError` may return a value to recover the request (e.g., auth
 *    refreshing a token). The first plugin that returns stops the chain.
 *  - **observation** — `onRetry` / `onFinalError` are notifications for observers (e.g.,
 *    the logger); they can't change the outcome, so their order is irrelevant.
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

export interface ApiResponse<T = unknown> {
  data: T;
  status: number;
  statusText: string;
  headers: Headers;
  /** After resolving `baseUrl` + `path` + `params`. */
  url: string;
  /** Milliseconds. */
  duration: number;
}

/**
 * Resolves the response type for `HttpClient.get/post/put/...`: if `O` carries a `schema`,
 * infer from it (better-fetch pattern — no manual generic needed); otherwise fall back to
 * the explicit `T` the caller passed (or `unknown`).
 */
export type InferSchema<O, T> = O extends { schema: ZodType<infer S> } ? S : T;

export interface ApiErrorInfo {
  status: number;
  statusText: string;
  url: string;
  method: string;
  data?: unknown;
  headers?: Headers;
}
