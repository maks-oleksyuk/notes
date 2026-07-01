import type { ApiErrorInfo } from './types';

/**
 * Base class for all API-related errors.
 * It captures the status code, URL, and the data returned by the server.
 */
export class ApiError extends Error {
  readonly status: number;
  readonly statusText: string;
  readonly url: string;
  readonly method: string;
  readonly data: unknown;
  readonly headers?: Headers;

  constructor(message: string, info: ApiErrorInfo) {
    // Pass the message to the base Error class
    super(message);

    this.name = 'ApiError';
    this.status = info.status;
    this.statusText = info.statusText;
    this.url = info.url;
    this.method = info.method;
    this.data = info.data;
    this.headers = info.headers;

    // This is a TypeScript "gotcha": when extending built-in classes like Error,
    // we need to manually restore the prototype chain so that 'instanceof' works correctly.
    Object.setPrototypeOf(this, ApiError.prototype);
  }

  /**
   * Helps when logging the error or sending it to Sentry/Analytics.
   */
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

/**
 * Thrown when the request fails due to network issues (no internet, DNS, etc.).
 * There is no response from the server in this case.
 */
export class NetworkError extends Error {
  constructor(
    message: string,
    public url: string,
  ) {
    super(message);
    this.name = 'NetworkError';
    Object.setPrototypeOf(this, NetworkError.prototype);
  }
}

/**
 * Thrown when the request takes longer than the specified timeout.
 */
export class TimeoutError extends Error {
  constructor(
    public url: string,
    public timeout: number,
  ) {
    super(`Request timeout after ${timeout}ms: ${url}`);
    this.name = 'TimeoutError';
    Object.setPrototypeOf(this, TimeoutError.prototype);
  }
}
