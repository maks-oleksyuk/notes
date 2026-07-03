import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

// `next/headers`'s `cookies()` only works inside a request scope in real Next.js.
// Mock it with an in-memory jar so the provider's logic can run in isolation.
const store = new Map<string, string>();
const jar = {
  get: (name: string) =>
    store.has(name) ? { value: store.get(name)! } : undefined,
  set: (name: string, value: string) => {
    store.set(name, value);
  },
};
vi.mock('next/headers', () => ({
  cookies: async () => jar,
}));

const CONFIG = {
  accessCookieName: 'access',
  refreshCookieName: 'refresh',
  refreshUrl: 'https://api.test/auth/refresh',
};

describe('createServerTokenProvider', () => {
  let fetchMock: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    store.clear();
    fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('getToken reads the access cookie, or null if unset', async () => {
    const { createServerTokenProvider } = await import(
      '@/lib/api/plugins/auth/server-provider'
    );
    const provider = createServerTokenProvider(CONFIG);

    expect(await provider.getToken()).toBeNull();

    store.set('access', 'stale-token');
    expect(await provider.getToken()).toBe('stale-token');
  });

  it('refreshToken throws when there is no refresh cookie', async () => {
    const { createServerTokenProvider } = await import(
      '@/lib/api/plugins/auth/server-provider'
    );
    const provider = createServerTokenProvider(CONFIG);

    await expect(provider.refreshToken()).rejects.toThrow(
      'no refresh token cookie',
    );
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('refreshToken throws when the refresh endpoint rejects', async () => {
    store.set('refresh', 'refresh-token');
    fetchMock.mockResolvedValueOnce(new Response(null, { status: 401 }));
    const { createServerTokenProvider } = await import(
      '@/lib/api/plugins/auth/server-provider'
    );
    const provider = createServerTokenProvider(CONFIG);

    await expect(provider.refreshToken()).rejects.toThrow(
      'refresh failed: 401',
    );
  });

  it('refreshToken rotates both cookies and returns the new access token', async () => {
    store.set('refresh', 'old-refresh');
    fetchMock.mockResolvedValueOnce(
      new Response(
        JSON.stringify({
          accessToken: 'new-access',
          refreshToken: 'new-refresh',
        }),
        { status: 200 },
      ),
    );
    const { createServerTokenProvider } = await import(
      '@/lib/api/plugins/auth/server-provider'
    );
    const provider = createServerTokenProvider(CONFIG);

    const token = await provider.refreshToken();

    expect(token).toBe('new-access');
    expect(store.get('access')).toBe('new-access');
    expect(store.get('refresh')).toBe('new-refresh');
    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toBe(CONFIG.refreshUrl);
    expect(init.headers.Cookie).toBe('refresh=old-refresh');
  });

  it('refreshToken keeps the old refresh cookie when the server does not rotate it', async () => {
    store.set('refresh', 'old-refresh');
    fetchMock.mockResolvedValueOnce(
      new Response(JSON.stringify({ accessToken: 'new-access' }), {
        status: 200,
      }),
    );
    const { createServerTokenProvider } = await import(
      '@/lib/api/plugins/auth/server-provider'
    );
    const provider = createServerTokenProvider(CONFIG);

    await provider.refreshToken();

    expect(store.get('refresh')).toBe('old-refresh');
  });

  it('onAuthFailure is a callable no-op', async () => {
    const { createServerTokenProvider } = await import(
      '@/lib/api/plugins/auth/server-provider'
    );
    const provider = createServerTokenProvider(CONFIG);

    expect(() => provider.onAuthFailure?.(new Error('x'))).not.toThrow();
  });
});
