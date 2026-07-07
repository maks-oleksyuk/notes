import { afterEach, describe, expect, it, vi } from 'vitest';

import {
  ApiError,
  NetworkError,
  TimeoutError,
  ValidationError,
} from '@/lib/http-client/core/errors';
import { nextRetry, resolveRetry } from '@/lib/http-client/core/retry-policy';

/** Unwraps a resolveRetry() result, failing loudly instead of using `!`. */
function must<T>(value: T | null): T {
  if (value === null)
    throw new Error('expected resolveRetry() to return a value');
  return value;
}

describe('resolveRetry', () => {
  it('returns null when retries are disabled', () => {
    expect(resolveRetry(false)).toBeNull();
    expect(resolveRetry(undefined)).toBeNull();
  });

  it('fills in defaults, including idempotent-only methods (A3)', () => {
    const cfg = resolveRetry({});
    expect(cfg).toMatchObject({
      limit: 3,
      delay: 1000,
      maxDelay: 30_000,
      statusCodes: [408, 429, 500, 502, 503, 504],
      respectRetryAfter: true,
      methods: ['GET', 'PUT', 'HEAD', 'DELETE'],
    });
  });

  describe('maxRetryAfter default (server vs browser, CP-8)', () => {
    afterEach(() => {
      vi.unstubAllGlobals();
    });

    it('defaults to a small cap on the server (no window) to avoid blocking RSC renders for minutes', () => {
      expect(typeof window).toBe('undefined');
      expect(resolveRetry({})?.maxRetryAfter).toBe(15_000);
    });

    it('defaults to a generous cap in the browser', () => {
      vi.stubGlobal('window', {});
      expect(resolveRetry({})?.maxRetryAfter).toBe(3 * 60_000);
    });

    it('lets the caller override the default in either environment', () => {
      expect(resolveRetry({ maxRetryAfter: 1234 })?.maxRetryAfter).toBe(1234);
    });
  });

  it('lets the caller override any default', () => {
    const cfg = resolveRetry({ limit: 5, methods: ['GET', 'POST'] });
    expect(cfg?.limit).toBe(5);
    expect(cfg?.methods).toEqual(['GET', 'POST']);
  });
});

function apiError(status: number, headers?: Headers) {
  return new ApiError(`HTTP ${status}`, {
    status,
    statusText: '',
    url: 'https://api.test/x',
    method: 'GET',
    headers,
  });
}

describe('nextRetry', () => {
  const cfg = must(resolveRetry({ limit: 3, delay: 100, maxDelay: 1000 }));

  it('gives up once the attempt count reaches the limit', () => {
    expect(nextRetry(apiError(503), 3, cfg, 'GET')).toBeNull();
    expect(nextRetry(apiError(503), 4, cfg, 'GET')).toBeNull();
  });

  it('retries a retryable status code, within backoff bounds', () => {
    const decision = nextRetry(apiError(503), 0, cfg, 'GET');
    expect(decision).not.toBeNull();
    // attempt 0: window = min(1000, 100 * 2^0) = 100 -> wait in [50, 100]
    expect(decision?.wait).toBeGreaterThanOrEqual(50);
    expect(decision?.wait).toBeLessThanOrEqual(100);
    expect(decision?.fromRetryAfter).toBe(false);
  });

  it('does not retry a non-retryable status code', () => {
    expect(nextRetry(apiError(404), 0, cfg, 'GET')).toBeNull();
  });

  it('retries NetworkError and TimeoutError', () => {
    expect(
      nextRetry(new NetworkError('boom', 'https://x'), 0, cfg, 'GET'),
    ).not.toBeNull();
    expect(
      nextRetry(new TimeoutError('https://x', 500), 0, cfg, 'GET'),
    ).not.toBeNull();
  });

  it('never retries ValidationError (A2 — a schema mismatch never fixes itself)', () => {
    expect(
      nextRetry(
        new ValidationError('bad', { url: 'x', errors: [], data: null }),
        0,
        cfg,
        'GET',
      ),
    ).toBeNull();
  });

  it('never retries a non-idempotent method by default, regardless of the error (A3)', () => {
    expect(nextRetry(apiError(503), 0, cfg, 'POST')).toBeNull();
  });

  it('retries a non-idempotent method when explicitly opted in', () => {
    const optedIn = must(resolveRetry({ limit: 3, methods: ['GET', 'POST'] }));
    expect(nextRetry(apiError(503), 0, optedIn, 'POST')).not.toBeNull();
  });

  it('skips the method check entirely when no method is passed', () => {
    expect(nextRetry(apiError(503), 0, cfg, undefined)).not.toBeNull();
  });

  describe('Retry-After', () => {
    it('honors a numeric (seconds) Retry-After over local backoff', () => {
      const headers = new Headers({ 'retry-after': '2' });
      const decision = nextRetry(apiError(503, headers), 0, cfg, 'GET');
      expect(decision).toEqual({ wait: 2000, fromRetryAfter: true });
    });

    it('honors an HTTP-date Retry-After', () => {
      const future = new Date(Date.now() + 3000).toUTCString();
      const headers = new Headers({ 'retry-after': future });
      const decision = nextRetry(apiError(503, headers), 0, cfg, 'GET');
      expect(decision?.fromRetryAfter).toBe(true);
      expect(decision?.wait).toBeGreaterThan(0);
    });

    it('gives up (does not fall back to backoff) when Retry-After exceeds maxRetryAfter', () => {
      const headers = new Headers({ 'retry-after': '99999' });
      expect(nextRetry(apiError(503, headers), 0, cfg, 'GET')).toBeNull();
    });

    it('ignores Retry-After when respectRetryAfter is false', () => {
      const noRespect = must(
        resolveRetry({
          limit: 3,
          delay: 100,
          respectRetryAfter: false,
        }),
      );
      const headers = new Headers({ 'retry-after': '2' });
      const decision = nextRetry(apiError(503, headers), 0, noRespect, 'GET');
      expect(decision?.fromRetryAfter).toBe(false);
    });

    it('falls back to backoff when the header is present but unparseable', () => {
      const headers = new Headers({ 'retry-after': 'not-a-date' });
      const decision = nextRetry(apiError(503, headers), 0, cfg, 'GET');
      expect(decision?.fromRetryAfter).toBe(false);
    });
  });

  it('caps backoff at maxDelay for high attempt numbers', () => {
    const highLimit = must(
      resolveRetry({ limit: 20, delay: 100, maxDelay: 1000 }),
    );
    const decision = nextRetry(apiError(503), 10, highLimit, 'GET');
    // attempt 10 would blow past maxDelay uncapped; window must clamp to 1000.
    expect(decision?.wait).toBeLessThanOrEqual(1000);
  });
});
