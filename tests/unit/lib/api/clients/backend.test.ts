import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

// Regression test for the exact bug class caught while writing the auth plugin
// tests: `backendApi` is built by importing `HttpClient` from the root `@/lib/api`
// barrel, which also re-exports the client instances themselves — a real
// circular import (root -> clients/backend -> root) that can throw
// "HttpClient is not a constructor" at module-load time depending on evaluation
// order. This only surfaces at runtime, never in `tsc`, so it needs an actual
// import to catch.
//
// The duck-typed checks below, not `instanceof HttpClient` — `vi.resetModules()`
// (needed so each test re-reads `NEXT_PUBLIC_API_TOKEN`) re-evaluates
// `core/client.ts` too, so a statically imported `HttpClient` class reference
// would be a different identity than the one used inside the freshly
// re-imported module graph. Not a bug, just a test-isolation artifact.
describe('backendApi', () => {
  let fetchMock: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);
    vi.resetModules();
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('loads without throwing and exposes the HttpClient method surface', async () => {
    const { backendApi } = await import('@/lib/api/clients/backend/client');
    expect(typeof backendApi.get).toBe('function');
    expect(typeof backendApi.post).toBe('function');
  });

  it('attaches no Authorization header when NEXT_PUBLIC_API_TOKEN is unset', async () => {
    vi.stubEnv('NEXT_PUBLIC_API_TOKEN', '');
    fetchMock.mockResolvedValueOnce(new Response('{}', { status: 200 }));
    const { backendApi } = await import('@/lib/api/clients/backend/client');

    await backendApi.get('/x');

    const headers = fetchMock.mock.calls[0][1].headers as Headers;
    expect(headers.has('Authorization')).toBe(false);
  });

  it('attaches Authorization: Bearer <token> when NEXT_PUBLIC_API_TOKEN is set', async () => {
    vi.stubEnv('NEXT_PUBLIC_API_TOKEN', 'test-token');
    fetchMock.mockResolvedValueOnce(new Response('{}', { status: 200 }));
    const { backendApi } = await import('@/lib/api/clients/backend/client');

    await backendApi.get('/x');

    const headers = fetchMock.mock.calls[0][1].headers as Headers;
    expect(headers.get('Authorization')).toBe('Bearer test-token');
  });

  it('has no refresh endpoint — a 401 fails through refreshToken(), not a loop', async () => {
    vi.stubEnv('NEXT_PUBLIC_API_TOKEN', 'test-token');
    fetchMock.mockResolvedValue(new Response('{}', { status: 401 }));
    const { backendApi } = await import('@/lib/api/clients/backend/client');

    // The auth plugin's onError catches the refresh failure and it becomes the
    // final error (client.ts: a thrown pluginError replaces finalError) — so the
    // caller sees *this* message, not the original 401.
    await expect(backendApi.get('/x')).rejects.toThrow(
      'backendApi has no refresh endpoint configured (static token only)',
    );
    // Exactly one call: the auth plugin tried to refresh, refreshToken() threw
    // immediately (api_old parity — no refresh endpoint), no replay happened.
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });
});
