import { afterEach, describe, expect, it, vi } from 'vitest';

import { logger } from '@/lib/http-client/plugins';

import type { ApiRequestOptions, ApiResponse } from '@/lib/http-client/core';

function fakeLogger() {
  return {
    log: vi.fn(),
    info: vi.fn(),
    warn: vi.fn(),
    error: vi.fn(),
  };
}

function options(
  overrides: Partial<ApiRequestOptions> = {},
): ApiRequestOptions {
  return { method: 'GET', path: '/x', requestId: 'abc123', ...overrides };
}

function response(overrides: Partial<ApiResponse> = {}): ApiResponse {
  return {
    data: { ok: true },
    status: 200,
    statusText: 'OK',
    headers: new Headers(),
    url: 'https://api.test/x',
    duration: 12,
    ...overrides,
  };
}

describe('logger plugin', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
    vi.unstubAllEnvs();
  });

  it('logs onRequest with method/path via info (custom logger, level info)', async () => {
    const custom = fakeLogger();
    const plugin = logger({ level: 'info', logger: custom });

    await plugin.onRequest?.(options());

    expect(custom.info).toHaveBeenCalledTimes(1);
    expect(custom.info.mock.calls[0][0]).toContain('GET');
    expect(custom.info.mock.calls[0][0]).toContain('/x');
  });

  it('skips onRequest for a retried attempt (already announced by onRetry)', async () => {
    const custom = fakeLogger();
    const plugin = logger({ level: 'info', logger: custom });

    await plugin.onRequest?.(options({ retryAttempt: 1 }));

    expect(custom.info).not.toHaveBeenCalled();
  });

  it('includes debug metadata (headers/params/body) only at debug level', async () => {
    const custom = fakeLogger();
    const infoLevel = logger({ level: 'info', logger: custom });
    await infoLevel.onRequest?.(options());
    expect(custom.info.mock.calls[0][1]).toBeUndefined();

    const debugLevel = logger({ level: 'debug', logger: custom });
    await debugLevel.onRequest?.(
      options({ headers: { Authorization: 'Bearer x' } }),
    );
    expect(custom.info.mock.calls[1][1]).toBeDefined();
    // Sanity: the sanitizer actually ran on the debug metadata.
    expect(JSON.stringify(custom.info.mock.calls[1][1])).not.toContain(
      'Bearer x',
    );
  });

  it('logs onResponse with status/duration', async () => {
    const custom = fakeLogger();
    const plugin = logger({ level: 'info', logger: custom });

    await plugin.onResponse?.(response(), options());

    expect(custom.info).toHaveBeenCalledTimes(1);
    expect(custom.info.mock.calls[0][0]).toContain('200');
  });

  it('includes debug metadata (headers/data) on onResponse only at debug level', async () => {
    const custom = fakeLogger();
    const plugin = logger({ level: 'debug', logger: custom });

    await plugin.onResponse?.(response(), options());

    expect(custom.info.mock.calls[0][1]).toBeDefined();
  });

  it('tags a sub-threshold onResponse as a likely cache hit, without hiding it', async () => {
    const custom = fakeLogger();
    const plugin = logger({ level: 'info', logger: custom });

    await plugin.onResponse?.(response({ duration: 2 }), options());

    expect(custom.info).toHaveBeenCalledTimes(1);
    expect(custom.info.mock.calls[0][0]).toContain('(cache?)');
  });

  it('does not tag an onResponse above the cache-hit threshold', async () => {
    const custom = fakeLogger();
    const plugin = logger({ level: 'info', logger: custom });

    await plugin.onResponse?.(response({ duration: 12 }), options());

    expect(custom.info.mock.calls[0][0]).not.toContain('(cache?)');
  });

  it('respects a custom cacheHitThresholdMs', async () => {
    const custom = fakeLogger();
    const plugin = logger({
      level: 'info',
      logger: custom,
      cacheHitThresholdMs: 0,
    });

    await plugin.onResponse?.(response({ duration: 2 }), options());

    expect(custom.info.mock.calls[0][0]).not.toContain('(cache?)');
  });

  it('logs onRetry with attempt/limit/wait, including the Retry-After marker', async () => {
    const custom = fakeLogger();
    const plugin = logger({ level: 'info', logger: custom });

    await plugin.onRetry?.({
      attempt: 1,
      limit: 3,
      wait: 250.4,
      fromRetryAfter: true,
      error: new Error('boom'),
      requestId: 'abc123',
      method: 'GET',
      path: '/x',
    });

    expect(custom.info).toHaveBeenCalledTimes(1);
    const [msg] = custom.info.mock.calls[0];
    expect(msg).toContain('retry 1/3');
    expect(msg).toContain('Retry-After');
    expect(msg).toContain('boom');
  });

  it('logs onFinalError via the logger.error method', async () => {
    const custom = fakeLogger();
    const plugin = logger({ level: 'error', logger: custom });

    await plugin.onFinalError?.(new Error('dead'), options());

    expect(custom.error).toHaveBeenCalledTimes(1);
    const [msg, err] = custom.error.mock.calls[0];
    // Header carries the message; the Error is still passed for custom sinks.
    expect(msg).toContain('dead');
    expect(msg).toContain('/x');
    expect(err).toBeInstanceOf(Error);
  });

  it('includes status/data in the metadata when the error is an ApiError (e.g. a 422 validation body)', async () => {
    const { ApiError } = await import('@/lib/http-client/core');
    const custom = fakeLogger();
    const plugin = logger({ level: 'error', logger: custom });

    const err = new ApiError('HTTP Error 422: Unprocessable Content', {
      status: 422,
      statusText: 'Unprocessable Content',
      url: 'https://api.test/bug-reports',
      method: 'POST',
      data: {
        message: 'The title field must be at least 5 characters.',
        errors: { title: ['The title field must be at least 5 characters.'] },
      },
    });

    await plugin.onFinalError?.(err, options());

    const meta = custom.error.mock.calls[0][2];
    expect(meta.status).toBe(422);
    expect(meta.data).toMatchObject({
      errors: { title: ['The title field must be at least 5 characters.'] },
    });
  });

  it('does not add status/data metadata for a plain (non-ApiError) failure', async () => {
    const custom = fakeLogger();
    const plugin = logger({ level: 'error', logger: custom });

    await plugin.onFinalError?.(new Error('network down'), options());

    const meta = custom.error.mock.calls[0][2];
    expect(meta).not.toHaveProperty('status');
    expect(meta).not.toHaveProperty('data');
  });

  it('skips onFinalError for an AbortError (caller-cancelled, not a failure)', async () => {
    const custom = fakeLogger();
    const plugin = logger({ level: 'error', logger: custom });

    const aborted = new DOMException('signal is aborted', 'AbortError');
    await plugin.onFinalError?.(aborted, options());

    expect(custom.error).not.toHaveBeenCalled();
  });

  it('logs a muted cancelled line for an AbortError at info level (not an error)', async () => {
    const custom = fakeLogger();
    const plugin = logger({ level: 'info', logger: custom });

    const aborted = new DOMException('signal is aborted', 'AbortError');
    await plugin.onFinalError?.(aborted, options());

    expect(custom.error).not.toHaveBeenCalled();
    expect(custom.info).toHaveBeenCalledTimes(1);
    expect(custom.info.mock.calls[0][0]).toContain('cancelled');
  });

  it('default console logger renders errors red via console.error in the browser', async () => {
    vi.stubGlobal('window', {});
    const errorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
    const plugin = logger({ level: 'error' }); // default console logger

    const err = new Error('dead');
    await plugin.onFinalError?.(err, options());

    // Red line = header (with message + %c styles) + cleaned metadata. The Error
    // object itself is NOT printed (its stack is always transport plumbing, not
    // the cause), so there's no `at …` noise.
    const args = errorSpy.mock.calls[0];
    expect(args[0]).toContain('%c');
    expect(args[0]).toContain('dead');
    expect(args).not.toContain(err);
    expect(args.at(-1)).toMatchObject({ method: 'GET', path: '/x' });
    vi.restoreAllMocks();
  });

  it('default console logger errors on the server without %c styling', async () => {
    const errorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
    const plugin = logger({ level: 'error' }); // no window → server branch

    const err = new Error('dead');
    await plugin.onFinalError?.(err, options());

    const args = errorSpy.mock.calls[0];
    expect(args[0]).not.toContain('%c');
    expect(args[0]).toContain('dead');
    expect(args).not.toContain(err);
    vi.restoreAllMocks();
  });

  it('respects level filtering: silent suppresses everything', async () => {
    const custom = fakeLogger();
    const plugin = logger({ level: 'silent', logger: custom });

    await plugin.onRequest?.(options());
    await plugin.onResponse?.(response(), options());
    await plugin.onRetry?.({
      attempt: 1,
      limit: 3,
      wait: 1,
      fromRetryAfter: false,
      error: new Error('x'),
    });
    await plugin.onFinalError?.(new Error('x'), options());

    expect(custom.info).not.toHaveBeenCalled();
    expect(custom.error).not.toHaveBeenCalled();
  });

  it('prefixes messages when a prefix is configured', async () => {
    const custom = fakeLogger();
    const plugin = logger({ level: 'info', logger: custom, prefix: 'blog' });

    await plugin.onRequest?.(options());

    expect(custom.info.mock.calls[0][0]).toContain('blog ');
  });

  it('falls back to the default console logger when none is given, without throwing', async () => {
    const logSpy = vi.spyOn(console, 'log').mockImplementation(() => {});
    const errorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
    const plugin = logger({ level: 'debug' });

    await plugin.onRequest?.(
      options({ headers: { Authorization: 'Bearer x' } }),
    );
    await plugin.onFinalError?.(new Error('dead'), options());

    expect(logSpy).toHaveBeenCalled();
    expect(errorSpy).toHaveBeenCalled();
    logSpy.mockRestore();
    errorSpy.mockRestore();
  });

  it('colors browser logs via %c CSS (the TTY check alone left them colorless)', async () => {
    vi.stubGlobal('window', {});
    const logSpy = vi.spyOn(console, 'log').mockImplementation(() => {});
    const plugin = logger({ level: 'info' }); // default console logger

    await plugin.onRequest?.(options());

    const args = logSpy.mock.calls[0];
    expect(args[0]).toContain('%c'); // CSS directives present
    expect(args[0]).not.toContain('\x1b['); // no raw ANSI reaches the browser
    // Cyan GET, in the same palette DevTools uses for ANSI (theme-aware).
    expect(args.slice(1)).toContain('color:light-dark(#0aa, rgb(18 181 203))');
    vi.restoreAllMocks();
  });

  it('default console logger groups output in a simulated browser environment', async () => {
    vi.stubGlobal('window', {});
    const groupSpy = vi
      .spyOn(console, 'groupCollapsed')
      .mockImplementation(() => {});
    const groupEndSpy = vi
      .spyOn(console, 'groupEnd')
      .mockImplementation(() => {});
    vi.spyOn(console, 'dir').mockImplementation(() => {});
    vi.spyOn(console, 'error').mockImplementation(() => {});
    const plugin = logger({ level: 'debug' });

    await plugin.onRequest?.(
      options({ headers: { Authorization: 'Bearer x' } }),
    );
    await plugin.onFinalError?.(new Error('dead'), options());

    expect(groupSpy).toHaveBeenCalled();
    expect(groupEndSpy).toHaveBeenCalled();
    vi.restoreAllMocks();
  });

  it('defaults to error level in production and info otherwise, unless overridden', async () => {
    vi.stubEnv('NODE_ENV', 'production');
    const custom = fakeLogger();
    const plugin = logger({ logger: custom }); // no explicit level

    await plugin.onRequest?.(options()); // info-level should be suppressed in prod default

    expect(custom.info).not.toHaveBeenCalled();
  });

  it('falls back to .log when the custom logger has no .info', async () => {
    const logOnly = { log: vi.fn(), error: vi.fn() };
    const plugin = logger({ level: 'info', logger: logOnly });

    await plugin.onRequest?.(options());

    expect(logOnly.log).toHaveBeenCalledTimes(1);
  });

  it('omits the [id] tag when the request has no requestId', async () => {
    const custom = fakeLogger();
    const plugin = logger({ level: 'info', logger: custom });

    await plugin.onRequest?.(options({ requestId: undefined }));

    expect(custom.info.mock.calls[0][0]).not.toContain('[');
  });

  it('defaults method/path to GET and / when missing on onRequest', async () => {
    const custom = fakeLogger();
    const plugin = logger({ level: 'info', logger: custom });

    await plugin.onRequest?.({});

    expect(custom.info.mock.calls[0][0]).toContain('GET');
    expect(custom.info.mock.calls[0][0]).toContain(' /');
  });

  it('defaults method/path to GET and / when missing on onResponse', async () => {
    const custom = fakeLogger();
    const plugin = logger({ level: 'info', logger: custom });

    await plugin.onResponse?.(response(), {});

    expect(custom.info.mock.calls[0][0]).toContain('GET');
  });

  it('defaults method/path to GET and / when missing on onFinalError', async () => {
    const custom = fakeLogger();
    const plugin = logger({ level: 'error', logger: custom });

    await plugin.onFinalError?.(new Error('dead'), {});

    expect(custom.error.mock.calls[0][0]).toContain('GET');
  });

  it('onRetry: omits the Retry-After marker and defaults method/path when absent', async () => {
    const custom = fakeLogger();
    const plugin = logger({ level: 'info', logger: custom });

    await plugin.onRetry?.({
      attempt: 1,
      limit: 3,
      wait: 5,
      fromRetryAfter: false,
      error: new Error('boom'),
    });

    const [msg] = custom.info.mock.calls[0];
    expect(msg).not.toContain('Retry-After');
    expect(msg).toContain('GET');
  });
});
