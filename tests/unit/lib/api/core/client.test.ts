import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { HttpClient } from '@/lib/api/core/client';
import {
  ApiError,
  NetworkError,
  TimeoutError,
  ValidationError,
} from '@/lib/api/core/errors';

import type { ApiPlugin } from '@/lib/api/core/types';

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });
}

describe('HttpClient', () => {
  let fetchMock: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('returns parsed data on success', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse({ ok: true }));
    const client = new HttpClient('https://api.test');

    const res = await client.get<{ ok: boolean }>('/x');

    expect(res.data).toEqual({ ok: true });
    expect(res.status).toBe(200);
    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(fetchMock.mock.calls[0][0]).toBe('https://api.test/x');
  });

  describe('convenience methods', () => {
    it('put sends the body with method PUT', async () => {
      fetchMock.mockResolvedValueOnce(jsonResponse({ ok: true }));
      const client = new HttpClient('https://api.test');

      await client.put('/x/1', { a: 1 });

      expect(fetchMock.mock.calls[0][1]).toMatchObject({ method: 'PUT' });
    });

    it('patch sends the body with method PATCH', async () => {
      fetchMock.mockResolvedValueOnce(jsonResponse({ ok: true }));
      const client = new HttpClient('https://api.test');

      await client.patch('/x/1', { a: 1 });

      expect(fetchMock.mock.calls[0][1]).toMatchObject({ method: 'PATCH' });
    });

    it('delete sends method DELETE with no body', async () => {
      fetchMock.mockResolvedValueOnce(jsonResponse({ ok: true }));
      const client = new HttpClient('https://api.test');

      await client.delete('/x/1');

      expect(fetchMock.mock.calls[0][1]).toMatchObject({ method: 'DELETE' });
    });

    it('head sends method HEAD and resolves data: null (no body by spec)', async () => {
      fetchMock.mockResolvedValueOnce(new Response(null, { status: 200 }));
      const client = new HttpClient('https://api.test');

      const res = await client.head('/x');

      expect(fetchMock.mock.calls[0][1]).toMatchObject({ method: 'HEAD' });
      expect(res.data).toBeNull();
      expect(res.status).toBe(200);
    });
  });

  describe('simple (non-plugin) hooks', () => {
    it('calls the simple onResponse callback', async () => {
      fetchMock.mockResolvedValueOnce(jsonResponse({ ok: true }));
      const onResponse = vi.fn();
      const client = new HttpClient('https://api.test');

      await client.get('/x', { onResponse });

      expect(onResponse).toHaveBeenCalledTimes(1);
      expect(onResponse.mock.calls[0][0]).toMatchObject({ data: { ok: true } });
    });

    it('calls the simple onResponseError callback on an ApiError', async () => {
      fetchMock.mockResolvedValueOnce(jsonResponse({}, 404));
      const onResponseError = vi.fn();
      const client = new HttpClient('https://api.test');

      await expect(
        client.get('/x', { onResponseError }),
      ).rejects.toBeInstanceOf(ApiError);

      expect(onResponseError).toHaveBeenCalledTimes(1);
    });
  });

  it('wraps a raw fetch failure (not abort/timeout) as NetworkError', async () => {
    fetchMock.mockRejectedValue(
      new TypeError('fetch failed: getaddrinfo ENOTFOUND'),
    );
    const client = new HttpClient('https://api.test');

    await expect(client.get('/x')).rejects.toBeInstanceOf(NetworkError);
  });

  it('wraps a non-Error fetch rejection with a generic message', async () => {
    fetchMock.mockRejectedValue('boom'); // some environments reject with a string
    const client = new HttpClient('https://api.test');

    await expect(client.get('/x')).rejects.toMatchObject({
      message: 'Unknown network error',
    });
  });

  it('calls the simple request-level onRequest callback', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse({ ok: true }));
    const onRequest = vi.fn();
    const client = new HttpClient('https://api.test');

    await client.get('/x', { onRequest });

    expect(onRequest).toHaveBeenCalledTimes(1);
  });

  it('lets a request-level responseType override the client default ("json")', async () => {
    fetchMock.mockResolvedValueOnce(
      new Response('plain text', { status: 200 }),
    );
    const client = new HttpClient('https://api.test');

    const res = await client.get('/x', { responseType: 'text' });

    expect(res.data).toBe('plain text');
  });

  it('falls back to "json" when responseType is explicitly undefined', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse({ ok: true }));
    const client = new HttpClient('https://api.test');

    // A key present with an `undefined` value overrides the constructor's
    // default ('json') during the options spread — the `|| 'json'` fallback in
    // `attempt()` exists specifically for this case.
    const res = await client.get('/x', { responseType: undefined });

    expect(res.data).toEqual({ ok: true });
  });

  it('falls back through defaultOptions.method, then "GET", when request() is called with no method', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse({ ok: true }));
    const withDefaultMethod = new HttpClient('https://api.test', {
      method: 'POST',
    });

    await withDefaultMethod.request('/x');

    expect(fetchMock.mock.calls[0][1]).toMatchObject({ method: 'POST' });

    fetchMock.mockResolvedValueOnce(jsonResponse({ ok: true }));
    const withNoDefault = new HttpClient('https://api.test');

    await withNoDefault.request('/x');

    expect(fetchMock.mock.calls[1][1]).toMatchObject({ method: 'GET' });
  });

  it('treats a plugin onError throwing a non-Error value as no recovery (finalError unchanged)', async () => {
    fetchMock.mockResolvedValue(jsonResponse({}, 503));
    const recovery: ApiPlugin = {
      name: 'flaky-recovery',
      onError() {
        throw 'not an Error instance'; // eslint-disable-line no-throw-literal
      },
    };
    const client = new HttpClient('https://api.test', {
      retry: { limit: 0 },
      plugins: [recovery],
    });

    // Still ends up as the original ApiError (503), not swallowed by the plugin's
    // throw — `finalError` is only replaced when the thrown value is an Error.
    await expect(client.get('/x')).rejects.toBeInstanceOf(ApiError);
  });

  describe('retry', () => {
    it('retries a retryable status and succeeds on a later attempt', async () => {
      fetchMock
        .mockResolvedValueOnce(jsonResponse({ error: true }, 503))
        .mockResolvedValueOnce(jsonResponse({ error: true }, 503))
        .mockResolvedValueOnce(jsonResponse({ ok: true }));

      const onRetry = vi.fn();
      const observer: ApiPlugin = { name: 'observer', onRetry };

      const client = new HttpClient('https://api.test', {
        retry: { limit: 5, delay: 1, maxDelay: 2 },
        plugins: [observer],
      });

      const res = await client.get('/x');

      expect(res.data).toEqual({ ok: true });
      expect(fetchMock).toHaveBeenCalledTimes(3);
      expect(onRetry).toHaveBeenCalledTimes(2);
      expect(onRetry.mock.calls[0][0]).toMatchObject({ attempt: 1, limit: 5 });
      expect(onRetry.mock.calls[1][0]).toMatchObject({ attempt: 2, limit: 5 });
    });

    it('gives up after the retry limit and throws the last error', async () => {
      fetchMock.mockResolvedValue(jsonResponse({ error: true }, 503));
      const onFinalError = vi.fn();

      const client = new HttpClient('https://api.test', {
        retry: { limit: 2, delay: 1 },
        plugins: [{ name: 'observer', onFinalError }],
      });

      await expect(client.get('/x')).rejects.toBeInstanceOf(ApiError);
      // Initial attempt (0) + 2 retries = 3 calls.
      expect(fetchMock).toHaveBeenCalledTimes(3);
      expect(onFinalError).toHaveBeenCalledTimes(1);
    });

    it('does not retry a non-retryable status code (404)', async () => {
      fetchMock.mockResolvedValueOnce(jsonResponse({}, 404));
      const client = new HttpClient('https://api.test', {
        retry: { limit: 3, delay: 1 },
      });

      await expect(client.get('/x')).rejects.toMatchObject({ status: 404 });
      expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('does not retry POST by default (A3 — idempotent methods only)', async () => {
      fetchMock.mockResolvedValue(jsonResponse({}, 503));
      const client = new HttpClient('https://api.test', {
        retry: { limit: 3, delay: 1 },
      });

      await expect(client.post('/orders', { item: 1 })).rejects.toBeInstanceOf(
        ApiError,
      );
      expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('retries POST when explicitly opted in', async () => {
      fetchMock
        .mockResolvedValueOnce(jsonResponse({}, 503))
        .mockResolvedValueOnce(jsonResponse({ ok: true }));
      const client = new HttpClient('https://api.test', {
        retry: { limit: 3, delay: 1, methods: ['GET', 'POST'] },
      });

      const res = await client.post('/orders', { item: 1 });

      expect(res.data).toEqual({ ok: true });
      expect(fetchMock).toHaveBeenCalledTimes(2);
    });

    it('never retries ValidationError thrown by a plugin onResponse hook (A2)', async () => {
      fetchMock.mockResolvedValue(jsonResponse({ bad: true }));
      const failingValidation: ApiPlugin = {
        name: 'validation',
        onResponse() {
          throw new ValidationError('nope', {
            url: 'x',
            errors: [],
            data: null,
          });
        },
      };
      const client = new HttpClient('https://api.test', {
        retry: { limit: 3, delay: 1 },
        plugins: [failingValidation],
      });

      await expect(client.get('/x')).rejects.toBeInstanceOf(ValidationError);
      expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('keeps the same requestId across every retried attempt', async () => {
      fetchMock
        .mockResolvedValueOnce(jsonResponse({}, 503))
        .mockResolvedValueOnce(jsonResponse({ ok: true }));
      const seenIds: (string | undefined)[] = [];
      const client = new HttpClient('https://api.test', {
        retry: { limit: 3, delay: 1 },
        plugins: [
          {
            name: 'observer',
            onRequest: (o) => void seenIds.push(o.requestId),
          },
        ],
      });

      await client.get('/x');

      expect(seenIds).toHaveLength(2);
      expect(seenIds[0]).toBeDefined();
      expect(seenIds[0]).toBe(seenIds[1]);
    });
  });

  describe('timeout', () => {
    it('aborts a hanging request after `timeout` and throws a retryable TimeoutError', async () => {
      fetchMock.mockImplementation((_url: string, init: RequestInit) => {
        return new Promise((_resolve, reject) => {
          init.signal?.addEventListener('abort', () => {
            reject(new DOMException('aborted', 'AbortError'));
          });
        });
      });

      const client = new HttpClient('https://api.test', {
        retry: { limit: 2, delay: 1 },
      });

      await expect(client.get('/x', { timeout: 5 })).rejects.toBeInstanceOf(
        TimeoutError,
      );
      // Initial attempt + 2 retries, each timing out the same way.
      expect(fetchMock).toHaveBeenCalledTimes(3);
    });

    it('does not retry when the caller cancels via their own signal', async () => {
      fetchMock.mockImplementation((_url: string, init: RequestInit) => {
        return new Promise((_resolve, reject) => {
          init.signal?.addEventListener('abort', () => {
            reject(new DOMException('aborted', 'AbortError'));
          });
        });
      });

      const controller = new AbortController();
      const client = new HttpClient('https://api.test', {
        retry: { limit: 3, delay: 1 },
      });

      const promise = client.get('/x', {
        timeout: 5000,
        signal: controller.signal,
      });
      controller.abort();

      await expect(promise).rejects.toMatchObject({ name: 'AbortError' });
      expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('does not time out a request that resolves in time', async () => {
      fetchMock.mockResolvedValueOnce(jsonResponse({ ok: true }));
      const client = new HttpClient('https://api.test');

      const res = await client.get('/x', { timeout: 5000 });

      expect(res.data).toEqual({ ok: true });
    });
  });

  describe('recovery phase (onError)', () => {
    it('lets a recovery plugin fix the error and return a response, short-circuiting retry', async () => {
      fetchMock.mockResolvedValueOnce(jsonResponse({}, 401));
      const recovered = jsonResponse({ recovered: true });
      const recovery: ApiPlugin = {
        name: 'recovery',
        async onError(error) {
          if (error instanceof ApiError && error.status === 401) {
            return {
              data: { recovered: true },
              status: 200,
              statusText: '',
              headers: recovered.headers,
              url: 'x',
              duration: 0,
            };
          }
          return undefined;
        },
      };
      const client = new HttpClient('https://api.test', {
        plugins: [recovery],
      });

      const res = await client.get<{ recovered: boolean }>('/x');

      expect(res.data).toEqual({ recovered: true });
      // Only the original failing call — recovery returned a value directly,
      // it didn't need context.retry() for this test.
      expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('replays via context.retry() with the mutated mergedOptions (not the original options)', async () => {
      fetchMock
        .mockResolvedValueOnce(jsonResponse({}, 401))
        .mockResolvedValueOnce(jsonResponse({ ok: true }));

      const recovery: ApiPlugin = {
        name: 'recovery',
        async onError(error, context) {
          if (!(error instanceof ApiError) || error.status !== 401)
            return undefined;
          if (context.options.authRetried) return undefined;
          context.options.authRetried = true;
          return context.retry();
        },
      };
      const seenAuthRetried: (boolean | undefined)[] = [];
      const client = new HttpClient('https://api.test', {
        plugins: [
          recovery,
          {
            name: 'observer',
            onRequest: (o) => void seenAuthRetried.push(o.authRetried),
          },
        ],
      });

      const res = await client.get('/x');

      expect(res.data).toEqual({ ok: true });
      expect(fetchMock).toHaveBeenCalledTimes(2);
      // First attempt: no mark yet. Replay: the plugin's mutation must have
      // survived into the new request — this is the client.ts fix that makes
      // the auth plugin's loop guard actually work.
      expect(seenAuthRetried).toEqual([undefined, true]);
    });

    it('does not retry (Phase 2) after a failed recovery for a non-retryable status', async () => {
      fetchMock.mockResolvedValue(jsonResponse({}, 401));
      const recovery: ApiPlugin = {
        name: 'recovery',
        async onError() {
          return undefined; // never recovers
        },
      };
      const client = new HttpClient('https://api.test', {
        retry: { limit: 3, delay: 1 }, // 401 isn't in the default retryable statuses
        plugins: [recovery],
      });

      await expect(client.get('/x')).rejects.toMatchObject({ status: 401 });
      expect(fetchMock).toHaveBeenCalledTimes(1);
    });
  });

  describe('hook order', () => {
    it('calls onRequest before fetch and onResponse after a successful fetch', async () => {
      const calls: string[] = [];
      const plugin: ApiPlugin = {
        name: 'tracker',
        onRequest: () => void calls.push('onRequest'),
        onResponse: () => void calls.push('onResponse'),
      };
      fetchMock.mockImplementationOnce(async () => {
        calls.push('fetch');
        return jsonResponse({ ok: true });
      });
      const client = new HttpClient('https://api.test', { plugins: [plugin] });

      await client.get('/x');

      expect(calls).toEqual(['onRequest', 'fetch', 'onResponse']);
    });

    it('runs onFinalError only after retries are exhausted, not per attempt', async () => {
      fetchMock.mockResolvedValue(jsonResponse({}, 503));
      const onFinalError = vi.fn();
      const onRetry = vi.fn();
      const client = new HttpClient('https://api.test', {
        retry: { limit: 2, delay: 1 },
        plugins: [{ name: 'observer', onRetry, onFinalError }],
      });

      await expect(client.get('/x')).rejects.toBeInstanceOf(ApiError);

      expect(onRetry).toHaveBeenCalledTimes(2);
      expect(onFinalError).toHaveBeenCalledTimes(1);
    });
  });

  describe('plugins merge policy (CP-8)', () => {
    it('concatenates request-level plugins with the client default instead of replacing it', async () => {
      fetchMock.mockResolvedValueOnce(jsonResponse({ ok: true }));
      const calls: string[] = [];
      const defaultPlugin: ApiPlugin = {
        name: 'default',
        onRequest: () => void calls.push('default'),
      };
      const requestPlugin: ApiPlugin = {
        name: 'per-request',
        onRequest: () => void calls.push('per-request'),
      };
      const client = new HttpClient('https://api.test', {
        plugins: [defaultPlugin],
      });

      await client.get('/x', { plugins: [requestPlugin] });

      expect(calls).toEqual(['default', 'per-request']);
    });

    it('drops a request-level plugin that shares a name with a default one, keeping the default', async () => {
      fetchMock.mockResolvedValueOnce(jsonResponse({ ok: true }));
      const calls: string[] = [];
      const defaultLogger: ApiPlugin = {
        name: 'logger',
        onRequest: () => void calls.push('default-logger'),
      };
      const shadowingLogger: ApiPlugin = {
        name: 'logger',
        onRequest: () => void calls.push('request-logger'),
      };
      const client = new HttpClient('https://api.test', {
        plugins: [defaultLogger],
      });

      await client.get('/x', { plugins: [shadowingLogger] });

      // First occurrence wins — the client's own `logger` plugin runs, the
      // same-named one passed at the call site is silently dropped.
      expect(calls).toEqual(['default-logger']);
    });

    it('dedupes plugins by name so an auth-recovery replay does not double them', async () => {
      let calls = 0;
      fetchMock.mockImplementation(async () =>
        calls++ === 0 ? jsonResponse({}, 401) : jsonResponse({ ok: true }),
      );
      let onRequestCalls = 0;
      const recovery: ApiPlugin = {
        name: 'recovery',
        onRequest: () => void onRequestCalls++,
        onError: async (_err, { retry }) => {
          if (calls > 1) return undefined;
          return retry();
        },
      };
      const client = new HttpClient('https://api.test', {
        plugins: [recovery],
      });

      const res = await client.get('/x');

      expect(res.data).toEqual({ ok: true });
      // One onRequest per attempt (2 attempts), not 2 per attempt — proves the
      // replay's merged plugin list wasn't duplicated on top of the original.
      expect(onRequestCalls).toBe(2);
    });
  });

  describe('GET dedup (CP-8, opt-in)', () => {
    it('is off by default: concurrent identical GETs each hit the network', async () => {
      fetchMock.mockImplementation(async () => jsonResponse({ ok: true }));
      const client = new HttpClient('https://api.test');

      await Promise.all([client.get('/x'), client.get('/x')]);

      expect(fetchMock).toHaveBeenCalledTimes(2);
    });

    it('shares one in-flight request across concurrent callers when dedupe: true', async () => {
      let resolveFetch!: (r: Response) => void;
      fetchMock.mockImplementationOnce(
        () =>
          new Promise<Response>((resolve) => {
            resolveFetch = resolve;
          }),
      );
      const client = new HttpClient('https://api.test');

      const first = client.get('/x', { dedupe: true });
      const second = client.get('/x', { dedupe: true });

      resolveFetch(jsonResponse({ ok: true }));
      const [a, b] = await Promise.all([first, second]);

      expect(fetchMock).toHaveBeenCalledTimes(1);
      expect(a).toBe(b);
    });

    it('does not dedupe across different URLs or params', async () => {
      fetchMock.mockImplementation(async () => jsonResponse({ ok: true }));
      const client = new HttpClient('https://api.test');

      await Promise.all([
        client.get('/x', { dedupe: true }),
        client.get('/y', { dedupe: true }),
      ]);

      expect(fetchMock).toHaveBeenCalledTimes(2);
    });

    it('never dedupes non-GET methods even with dedupe: true', async () => {
      fetchMock.mockImplementation(async () => jsonResponse({ ok: true }));
      const client = new HttpClient('https://api.test');

      await Promise.all([
        client.post('/x', { a: 1 }, { dedupe: true }),
        client.post('/x', { a: 1 }, { dedupe: true }),
      ]);

      expect(fetchMock).toHaveBeenCalledTimes(2);
    });

    it('allows a fresh request after the in-flight one settles', async () => {
      fetchMock.mockImplementation(async () => jsonResponse({ ok: true }));
      const client = new HttpClient('https://api.test');

      await client.get('/x', { dedupe: true });
      await client.get('/x', { dedupe: true });

      expect(fetchMock).toHaveBeenCalledTimes(2);
    });

    it('does not dedupe when an auth plugin is present — the URL key cannot tell users apart (B2)', async () => {
      fetchMock.mockImplementation(async () => jsonResponse({ ok: true }));
      const client = new HttpClient('https://api.test', {
        plugins: [{ name: 'auth' }],
      });

      await Promise.all([
        client.get('/me', { dedupe: true }),
        client.get('/me', { dedupe: true }),
      ]);

      expect(fetchMock).toHaveBeenCalledTimes(2);
    });

    it('does not dedupe when an Authorization header is set (B2)', async () => {
      fetchMock.mockImplementation(async () => jsonResponse({ ok: true }));
      const client = new HttpClient('https://api.test', {
        headers: { Authorization: 'Bearer user-1' },
      });

      await Promise.all([
        client.get('/me', { dedupe: true }),
        client.get('/me', { dedupe: true }),
      ]);

      expect(fetchMock).toHaveBeenCalledTimes(2);
    });

    it('does not deadlock when a deduped GET goes through the recovery/retry path', async () => {
      let calls = 0;
      fetchMock.mockImplementation(async () =>
        calls++ === 0 ? jsonResponse({}, 401) : jsonResponse({ ok: true }),
      );
      const recovery: ApiPlugin = {
        name: 'recovery',
        onError: async (_err, { retry }) => (calls > 1 ? undefined : retry()),
      };
      const client = new HttpClient('https://api.test', {
        plugins: [recovery],
      });

      const result = await client.get('/x', { dedupe: true });

      expect(result.data).toEqual({ ok: true });
      expect(fetchMock).toHaveBeenCalledTimes(2);
    });
  });

  describe('hook errors are not network errors (A2)', () => {
    it('does not retry when a user onResponse hook throws, and preserves the error type', async () => {
      fetchMock.mockResolvedValue(jsonResponse({ ok: true }));
      const client = new HttpClient('https://api.test', {
        retry: { limit: 3, delay: 1 },
      });

      // The network part succeeded — a bug in the caller's own hook must not
      // re-run the request, and must surface as itself, not as NetworkError.
      await expect(
        client.get('/x', {
          onResponse: () => {
            throw new TypeError('hook bug');
          },
        }),
      ).rejects.toBeInstanceOf(TypeError);

      expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('does not retry when a plugin onRequest hook throws', async () => {
      const boom = new Error('plugin setup failed');
      const broken: ApiPlugin = {
        name: 'broken',
        onRequest() {
          throw boom;
        },
      };
      const client = new HttpClient('https://api.test', {
        retry: { limit: 3, delay: 1 },
        plugins: [broken],
      });

      await expect(client.get('/x')).rejects.toBe(boom);
      // onRequest runs before fetch — the network is never touched.
      expect(fetchMock).toHaveBeenCalledTimes(0);
    });

    it('wraps a non-Error hook throw into an Error', async () => {
      const client = new HttpClient('https://api.test');

      await expect(
        client.get('/x', {
          onRequest: () => {
            // eslint-disable-next-line no-throw-literal
            throw 'string-boom';
          },
        }),
      ).rejects.toMatchObject({ message: 'string-boom' });
    });
  });

  describe('abort during backoff (A3)', () => {
    it('rejects immediately when the signal is already aborted as the wait starts', async () => {
      fetchMock.mockResolvedValue(jsonResponse({}, 503));
      const controller = new AbortController();
      const onFinalError = vi.fn();
      // Aborts from the onRetry observer — after the retry decision, right
      // before the backoff sleep would start.
      const aborter: ApiPlugin = {
        name: 'aborter',
        onRetry: () => controller.abort(),
        onFinalError,
      };
      const client = new HttpClient('https://api.test', {
        // A wait this long would blow the test timeout if it weren't interrupted.
        retry: { limit: 3, delay: 60_000 },
        plugins: [aborter],
      });

      await expect(
        client.get('/x', { signal: controller.signal }),
      ).rejects.toMatchObject({ name: 'AbortError' });

      expect(fetchMock).toHaveBeenCalledTimes(1);
      // The abort still flows through Phase 3, so observers see the request end.
      expect(onFinalError).toHaveBeenCalledTimes(1);
    });

    it('falls back to a DOMException AbortError when the signal carries no reason', async () => {
      fetchMock.mockResolvedValue(jsonResponse({}, 503));
      const controller = new AbortController();
      // Simulates a hand-rolled AbortSignal-like without a reason — a real
      // AbortController.abort() always sets one.
      Object.defineProperty(controller.signal, 'reason', { value: undefined });
      const aborter: ApiPlugin = {
        name: 'aborter',
        onRetry: () => controller.abort(),
      };
      const client = new HttpClient('https://api.test', {
        retry: { limit: 3, delay: 60_000 },
        plugins: [aborter],
      });

      await expect(
        client.get('/x', { signal: controller.signal }),
      ).rejects.toMatchObject({ name: 'AbortError' });
    });

    it('wraps a non-Error abort reason (abort("why")) into an Error', async () => {
      fetchMock.mockResolvedValue(jsonResponse({}, 503));
      const controller = new AbortController();
      const aborter: ApiPlugin = {
        name: 'aborter',
        // `abort(reason)` with a plain string: the signal's reason is not an
        // Error, so the backoff catch must wrap it before Phase 3.
        onRetry: () => controller.abort('user navigated away'),
      };
      const client = new HttpClient('https://api.test', {
        retry: { limit: 3, delay: 60_000 },
        plugins: [aborter],
      });

      await expect(
        client.get('/x', { signal: controller.signal }),
      ).rejects.toMatchObject({ message: 'user navigated away' });

      expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('interrupts a backoff sleep already in progress', async () => {
      fetchMock.mockResolvedValue(jsonResponse({}, 503));
      const controller = new AbortController();
      const aborter: ApiPlugin = {
        name: 'aborter',
        // Fires a few ms into the 60s backoff wait.
        onRetry: () => void setTimeout(() => controller.abort(), 10),
      };
      const client = new HttpClient('https://api.test', {
        retry: { limit: 3, delay: 60_000 },
        plugins: [aborter],
      });

      await expect(
        client.get('/x', { signal: controller.signal }),
      ).rejects.toMatchObject({ name: 'AbortError' });

      expect(fetchMock).toHaveBeenCalledTimes(1);
    });
  });

  describe('recovery replay budget (B5)', () => {
    it('gives a recovery replay exactly one attempt — no transient retries inside the replay', async () => {
      let call = 0;
      fetchMock.mockImplementation(async () =>
        call++ === 0 ? jsonResponse({}, 401) : jsonResponse({}, 503),
      );
      const recovery: ApiPlugin = {
        name: 'recovery',
        onError: async (error, { retry }) =>
          error instanceof ApiError && error.status === 401
            ? retry()
            : undefined,
      };
      const client = new HttpClient('https://api.test', {
        retry: { limit: 2, delay: 1 },
        plugins: [recovery],
      });

      await expect(client.get('/x')).rejects.toMatchObject({ status: 503 });

      // 1 (401) + 1 replay (503, retry: false inside) + 2 outer transient
      // retries = 4. Without the replay's `retry: false`, the replay would
      // also retry the 503 on its own budget, multiplying to 7 fetches.
      expect(fetchMock).toHaveBeenCalledTimes(4);
    });
  });
});
