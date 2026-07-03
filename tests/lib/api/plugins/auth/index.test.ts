import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { HttpClient } from '@/lib/api/core/client';
import { auth } from '@/lib/api/plugins/auth';
import type { TokenProvider } from '@/lib/api/plugins/auth/types';

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });
}

// A provider whose `refreshToken` we can inspect: how many times it was *actually*
// invoked (not how many 401s asked for it — that's the single-flight guarantee).
function createTestProvider(refreshImpl: () => Promise<string>) {
  let token: string | null = 'stale-token';
  let refreshCalls = 0;
  const provider: TokenProvider = {
    getToken: () => token,
    async refreshToken() {
      refreshCalls++;
      const next = await refreshImpl();
      token = next;
      return next;
    },
    onAuthFailure: vi.fn(),
  };
  return {
    provider,
    getRefreshCalls: () => refreshCalls,
    getToken: () => token,
  };
}

describe('auth plugin', () => {
  let fetchMock: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('attaches Authorization: Bearer <token> from the provider', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse({ ok: true }));
    const { provider } = createTestProvider(async () => 'new-token');
    const client = new HttpClient('https://api.test', {
      plugins: [auth(provider)],
    });

    await client.get('/me');

    const headers = fetchMock.mock.calls[0][1].headers as Headers;
    expect(headers.get('Authorization')).toBe('Bearer stale-token');
  });

  it('refreshes once on 401 and replays with the new token', async () => {
    fetchMock
      .mockResolvedValueOnce(jsonResponse({}, 401))
      .mockResolvedValueOnce(jsonResponse({ ok: true }));
    const { provider, getRefreshCalls } = createTestProvider(
      async () => 'fresh-token',
    );
    const client = new HttpClient('https://api.test', {
      plugins: [auth(provider)],
    });

    const res = await client.get('/me');

    expect(res.data).toEqual({ ok: true });
    expect(getRefreshCalls()).toBe(1);
    const replayHeaders = fetchMock.mock.calls[1][1].headers as Headers;
    expect(replayHeaders.get('Authorization')).toBe('Bearer fresh-token');
  });

  it('single-flight: N parallel 401s collapse into exactly one real refresh call', async () => {
    let resolveRefresh!: (token: string) => void;
    const refreshImpl = vi.fn(
      () =>
        new Promise<string>((resolve) => {
          resolveRefresh = resolve;
        }),
    );
    const { provider, getRefreshCalls } = createTestProvider(refreshImpl);

    // Every /me call 401s once, then succeeds (after the shared refresh resolves).
    fetchMock.mockImplementation((_url: string, init: RequestInit) => {
      const headers = init.headers as Headers;
      if (headers.get('Authorization') === 'Bearer stale-token') {
        return Promise.resolve(jsonResponse({}, 401));
      }
      return Promise.resolve(jsonResponse({ ok: true }));
    });

    const client = new HttpClient('https://api.test', {
      plugins: [auth(provider)],
    });

    const requests = Promise.all([
      client.get('/me'),
      client.get('/me'),
      client.get('/me'),
      client.get('/me'),
      client.get('/me'),
    ]);

    // Let all 5 hit the 401 and reach `onError` before the refresh resolves.
    await vi.waitFor(() => expect(refreshImpl).toHaveBeenCalledTimes(1));
    resolveRefresh('fresh-token');

    const results = await requests;

    expect(results).toHaveLength(5);
    for (const res of results) expect(res.data).toEqual({ ok: true });
    // The whole point: 5 concurrent 401s, but only one real network refresh.
    expect(getRefreshCalls()).toBe(1);
  });

  it('guards against an infinite loop: a second 401 after replay goes to onAuthFailure, not a second refresh', async () => {
    fetchMock.mockResolvedValue(jsonResponse({}, 401)); // always 401, session is just dead
    const { provider, getRefreshCalls } = createTestProvider(
      async () => 'fresh-token',
    );
    const client = new HttpClient('https://api.test', {
      plugins: [auth(provider)],
    });

    await expect(client.get('/me')).rejects.toMatchObject({ status: 401 });

    // Attempt 1 -> 401 -> refresh -> replay (attempt 2) -> 401 again -> guard trips.
    expect(fetchMock).toHaveBeenCalledTimes(2);
    expect(getRefreshCalls()).toBe(1);
    expect(provider.onAuthFailure).toHaveBeenCalledTimes(1);
  });

  it('calls onAuthFailure (not a retry loop) when the refresh itself fails', async () => {
    fetchMock.mockResolvedValue(jsonResponse({}, 401));
    const { provider, getRefreshCalls } = createTestProvider(async () => {
      throw new Error('refresh endpoint down');
    });
    const client = new HttpClient('https://api.test', {
      plugins: [auth(provider)],
    });

    await expect(client.get('/me')).rejects.toThrow('refresh endpoint down');

    expect(fetchMock).toHaveBeenCalledTimes(1); // no replay — refresh never succeeded
    expect(getRefreshCalls()).toBe(1);
    expect(provider.onAuthFailure).toHaveBeenCalledTimes(1);
  });

  it('does not attach Authorization when the provider has no token', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse({ ok: true }));
    const provider: TokenProvider = {
      getToken: () => null,
      refreshToken: async () => 'x',
    };
    const client = new HttpClient('https://api.test', {
      plugins: [auth(provider)],
    });

    await client.get('/public');

    const headers = fetchMock.mock.calls[0][1].headers as Headers;
    expect(headers.has('Authorization')).toBe(false);
  });

  it('ignores non-401 errors entirely (e.g. leaves a 500 to the core retry policy)', async () => {
    fetchMock.mockResolvedValue(jsonResponse({}, 500));
    const { provider, getRefreshCalls } = createTestProvider(async () => 'x');
    const client = new HttpClient('https://api.test', {
      retry: { limit: 1, delay: 1 },
      plugins: [auth(provider)],
    });

    await expect(client.get('/x')).rejects.toMatchObject({ status: 500 });
    expect(getRefreshCalls()).toBe(0);
  });
});
