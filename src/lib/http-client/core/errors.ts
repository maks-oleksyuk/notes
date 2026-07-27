import type { ApiErrorInfo } from './types';

export class ApiError extends Error {
  readonly status: number;
  readonly statusText: string;
  readonly url: string;
  readonly method: string;
  readonly data: unknown;
  readonly headers?: Headers;

  constructor(message: string, info: ApiErrorInfo) {
    super(message);

    this.name = 'ApiError';
    this.status = info.status;
    this.statusText = info.statusText;
    this.url = info.url;
    this.method = info.method;
    this.data = info.data;
    this.headers = info.headers;
  }

  /** Helps when logging the error or sending it to Sentry/Analytics. */
  toJSON() {
    return {
      name: this.name,
      message: this.message,
      status: this.status,
      statusText: this.statusText,
      url: this.url,
      method: this.method,
      data: this.data,
    };
  }
}

/** Thrown when `fetch` itself fails (no internet, DNS, refused connection) — no response to work with. */
export class NetworkError extends Error {
  constructor(
    message: string,
    public url: string,
    cause?: unknown,
  ) {
    super(message, { cause });
    this.name = 'NetworkError';
  }
}

/** Thrown when the request takes longer than the specified timeout. */
export class TimeoutError extends Error {
  constructor(
    public url: string,
    public timeout: number,
  ) {
    super(`Request timeout after ${timeout}ms: ${url}`);
    this.name = 'TimeoutError';
  }
}

/**
 * Thrown by the `validation` plugin when a 2xx response fails its Zod schema.
 * Lives in core (not `plugins/validation.ts`) so `normalizeError` can recognize it and pass
 * it through unwrapped — otherwise it falls into the generic `NetworkError` bucket and gets
 * retried, even though re-fetching never fixes a schema mismatch.
 */
export class ValidationError extends Error {
  constructor(
    message: string,
    public details: { url: string; errors: unknown[]; data: unknown },
  ) {
    super(message, { cause: details.errors });
    this.name = 'ValidationError';
  }
}

/**
 * Thrown when a 2xx response's body can't be parsed as JSON (server bug, proxy returning
 * HTML, truncated body, etc.) — a bad response should be loud, not a `null` that type-checks
 * and blows up somewhere downstream instead.
 */
export class ParseError extends Error {
  constructor(
    public url: string,
    public reason: string,
    cause?: unknown,
  ) {
    super(`Failed to parse response body as JSON: ${url} (${reason})`, {
      cause,
    });
    this.name = 'ParseError';
  }
}
