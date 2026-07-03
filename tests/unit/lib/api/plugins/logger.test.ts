import { afterEach, describe, expect, it, vi } from 'vitest';

import { logger } from '@/lib/api/plugins/logger';

import type { ApiRequestOptions, ApiResponse } from '@/lib/api/core/types';

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

  it('logs onRequest with method/path via info (custom logger, level info)', () => {
    const custom = fakeLogger();
    const plugin = logger({ level: 'info', logger: custom });

    plugin.onRequest?.(options());

    expect(custom.info).toHaveBeenCalledTimes(1);
    expect(custom.info.mock.calls[0][0]).toContain('GET');
    expect(custom.info.mock.calls[0][0]).toContain('/x');
  });

  it('skips onRequest for a retried attempt (already announced by onRetry)', () => {
    const custom = fakeLogger();
    const plugin = logger({ level: 'info', logger: custom });

    plugin.onRequest?.(options({ retryAttempt: 1 }));

    expect(custom.info).not.toHaveBeenCalled();
  });

  it('includes debug metadata (headers/params/body) only at debug level', () => {
    const custom = fakeLogger();
    const infoLevel = logger({ level: 'info', logger: custom });
    infoLevel.onRequest?.(options());
    expect(custom.info.mock.calls[0][1]).toBeUndefined();

    const debugLevel = logger({ level: 'debug', logger: custom });
    debugLevel.onRequest?.(options({ headers: { Authorization: 'Bearer x' } }));
    expect(custom.info.mock.calls[1][1]).toBeDefined();
    // Sanity: the sanitizer actually ran on the debug metadata.
    expect(JSON.stringify(custom.info.mock.calls[1][1])).not.toContain(
      'Bearer x',
    );
  });

  it('logs onResponse with status/duration', () => {
    const custom = fakeLogger();
    const plugin = logger({ level: 'info', logger: custom });

    plugin.onResponse?.(response(), options());

    expect(custom.info).toHaveBeenCalledTimes(1);
    expect(custom.info.mock.calls[0][0]).toContain('200');
  });

  it('includes debug metadata (headers/data) on onResponse only at debug level', () => {
    const custom = fakeLogger();
    const plugin = logger({ level: 'debug', logger: custom });

    plugin.onResponse?.(response(), options());

    expect(custom.info.mock.calls[0][1]).toBeDefined();
  });

  it('logs onRetry with attempt/limit/wait, including the Retry-After marker', () => {
    const custom = fakeLogger();
    const plugin = logger({ level: 'info', logger: custom });

    plugin.onRetry?.({
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

  it('logs onFinalError via the logger.error method', () => {
    const custom = fakeLogger();
    const plugin = logger({ level: 'error', logger: custom });

    plugin.onFinalError?.(new Error('dead'), options());

    expect(custom.error).toHaveBeenCalledTimes(1);
    const [msg, err] = custom.error.mock.calls[0];
    expect(msg).toContain('dead');
    expect(err).toBeInstanceOf(Error);
  });

  it('respects level filtering: silent suppresses everything', () => {
    const custom = fakeLogger();
    const plugin = logger({ level: 'silent', logger: custom });

    plugin.onRequest?.(options());
    plugin.onResponse?.(response(), options());
    plugin.onRetry?.({
      attempt: 1,
      limit: 3,
      wait: 1,
      fromRetryAfter: false,
      error: new Error('x'),
    });
    plugin.onFinalError?.(new Error('x'), options());

    expect(custom.info).not.toHaveBeenCalled();
    expect(custom.error).not.toHaveBeenCalled();
  });

  it('prefixes messages when a prefix is configured', () => {
    const custom = fakeLogger();
    const plugin = logger({ level: 'info', logger: custom, prefix: 'blog' });

    plugin.onRequest?.(options());

    expect(custom.info.mock.calls[0][0]).toContain('blog ');
  });

  it('falls back to the default console logger when none is given, without throwing', () => {
    const logSpy = vi.spyOn(console, 'log').mockImplementation(() => {});
    const errorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
    const plugin = logger({ level: 'debug' });

    plugin.onRequest?.(options({ headers: { Authorization: 'Bearer x' } }));
    plugin.onFinalError?.(new Error('dead'), options());

    expect(logSpy).toHaveBeenCalled();
    expect(errorSpy).toHaveBeenCalled();
    logSpy.mockRestore();
    errorSpy.mockRestore();
  });

  it('default console logger groups output in a simulated browser environment', () => {
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

    plugin.onRequest?.(options({ headers: { Authorization: 'Bearer x' } }));
    plugin.onFinalError?.(new Error('dead'), options());

    expect(groupSpy).toHaveBeenCalled();
    expect(groupEndSpy).toHaveBeenCalled();
    vi.restoreAllMocks();
  });

  it('defaults to error level in production and info otherwise, unless overridden', () => {
    vi.stubEnv('NODE_ENV', 'production');
    const custom = fakeLogger();
    const plugin = logger({ logger: custom }); // no explicit level

    plugin.onRequest?.(options()); // info-level, should be suppressed in prod default

    expect(custom.info).not.toHaveBeenCalled();
  });

  it('falls back to .log when the custom logger has no .info', () => {
    const logOnly = { log: vi.fn(), error: vi.fn() };
    const plugin = logger({ level: 'info', logger: logOnly });

    plugin.onRequest?.(options());

    expect(logOnly.log).toHaveBeenCalledTimes(1);
  });

  it('omits the [id] tag when the request has no requestId', () => {
    const custom = fakeLogger();
    const plugin = logger({ level: 'info', logger: custom });

    plugin.onRequest?.(options({ requestId: undefined }));

    expect(custom.info.mock.calls[0][0]).not.toContain('[');
  });

  it('defaults method/path to GET and / when missing on onRequest', () => {
    const custom = fakeLogger();
    const plugin = logger({ level: 'info', logger: custom });

    plugin.onRequest?.({});

    expect(custom.info.mock.calls[0][0]).toContain('GET');
    expect(custom.info.mock.calls[0][0]).toContain(' /');
  });

  it('defaults method/path to GET and / when missing on onResponse', () => {
    const custom = fakeLogger();
    const plugin = logger({ level: 'info', logger: custom });

    plugin.onResponse?.(response(), {});

    expect(custom.info.mock.calls[0][0]).toContain('GET');
  });

  it('defaults method/path to GET and / when missing on onFinalError', () => {
    const custom = fakeLogger();
    const plugin = logger({ level: 'error', logger: custom });

    plugin.onFinalError?.(new Error('dead'), {});

    expect(custom.error.mock.calls[0][0]).toContain('GET');
  });

  it('onRetry: omits the Retry-After marker and defaults method/path when absent', () => {
    const custom = fakeLogger();
    const plugin = logger({ level: 'info', logger: custom });

    plugin.onRetry?.({
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
