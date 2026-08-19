import { beforeEach, describe, expect, it, vi } from 'vitest';

import { ApiError } from '@/lib/http-client/core';

const captureException = vi.hoisted(() => vi.fn());
vi.mock('@sentry/react', () => ({ captureException }));

const { sentry } = await import('@/lib/http-client/plugins');

describe('sentry plugin', () => {
  beforeEach(() => {
    captureException.mockClear();
  });

  it('reports the error with request context on a final error', () => {
    const plugin = sentry();
    const error = new Error('boom');

    plugin.onFinalError?.(error, { method: 'POST', path: '/posts' });

    expect(captureException).toHaveBeenCalledWith(error, {
      contexts: { http: { method: 'POST', path: '/posts' } },
    });
  });

  it('includes the status for a 5xx ApiError', () => {
    const plugin = sentry();
    const error = new ApiError('Internal Server Error', {
      status: 500,
      statusText: 'Internal Server Error',
      url: 'https://api.test/posts/1',
      method: 'GET',
    });

    plugin.onFinalError?.(error, { path: '/posts/1' });

    expect(captureException).toHaveBeenCalledWith(error, {
      contexts: { http: { method: 'GET', path: '/posts/1', status: 500 } },
    });
  });

  it("skips a 4xx ApiError — the caller's mistake, not a bug", () => {
    const plugin = sentry();
    const error = new ApiError('Not Found', {
      status: 404,
      statusText: 'Not Found',
      url: 'https://api.test/posts/1',
      method: 'GET',
    });

    plugin.onFinalError?.(error, { path: '/posts/1' });

    expect(captureException).not.toHaveBeenCalled();
  });

  it('skips a cancelled request', () => {
    const plugin = sentry();
    const error = new Error('aborted');
    error.name = 'AbortError';

    plugin.onFinalError?.(error, { path: '/posts' });

    expect(captureException).not.toHaveBeenCalled();
  });
});
