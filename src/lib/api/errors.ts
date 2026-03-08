import type { ApiErrorInfo } from './types';

/**
 * Base API error with structured info for logging and display.
 */
export class ApiError extends Error {
  readonly status: number;
  readonly statusText: string;
  readonly url: string;
  readonly method: string;
  readonly body?: unknown;
  readonly duration: number;

  constructor(message: string, info: ApiErrorInfo) {
    super(message);
    this.name = 'ApiError';
    this.status = info.status;
    this.statusText = info.statusText;
    this.url = info.url;
    this.method = info.method;
    this.body = info.body;
    this.duration = info.duration;
  }

  toJSON() {
    return {
      name: this.name,
      message: this.message,
      status: this.status,
      statusText: this.statusText,
      url: this.url,
      method: this.method,
      duration: this.duration,
    };
  }
}

/** 429 Too Many Requests — triggers retry logic */
export class RateLimitError extends ApiError {
  /** Seconds to wait before retry (from Retry-After header) */
  readonly retryAfter: number | null;

  constructor(info: ApiErrorInfo, retryAfter: number | null) {
    super(`Rate limited: ${info.url}`, info);
    this.name = 'RateLimitError';
    this.retryAfter = retryAfter;
  }
}

/** Network errors (no response received) */
export class NetworkError extends Error {
  readonly url: string;
  readonly method: string;

  constructor(message: string, url: string, method: string) {
    super(message);
    this.name = 'NetworkError';
    this.url = url;
    this.method = method;
  }
}

/** Request aborted via AbortSignal or timeout */
export class TimeoutError extends Error {
  readonly url: string;
  readonly timeout: number;

  constructor(url: string, timeout: number) {
    super(`Request timed out after ${timeout}ms: ${url}`);
    this.name = 'TimeoutError';
    this.url = url;
    this.timeout = timeout;
  }
}
