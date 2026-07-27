import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { getCurrentUser, login } from '../auth/requests';
import {
  clearDummyJsonTokens,
  getDummyJsonAccessToken,
} from '../auth/token-provider';

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });
}

/**
 * End-to-end wiring test through the *real* `dummyJsonApi` + real
 * `dummyJsonTokenProvider` (not a fake `TokenProvider`, unlike the generic
 * `auth` plugin unit tests in `@/lib/http-client`) — proves this client's own
 * pieces are actually wired together correctly, not just the library
 * mechanism in isolation.
 */
describe('dummyjson client: login -> 401 -> refresh -> replay', () => {
  let fetchMock: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);
    clearDummyJsonTokens();
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    clearDummyJsonTokens();
  });

  it('transparently refreshes an expired access token and replays the request', async () => {
    fetchMock.mockResolvedValueOnce(
      jsonResponse({
        id: 1,
        username: 'emilys',
        accessToken: 'stale-access',
        refreshToken: 'valid-refresh',
      }),
    );
    await login({ username: 'emilys', password: 'emilyspass' });
    expect(getDummyJsonAccessToken()).toBe('stale-access');

    // 1) /auth/me with the stale token -> 401
    fetchMock.mockResolvedValueOnce(
      jsonResponse({ message: 'Invalid/Expired Token!' }, 401),
    );
    // 2) auth plugin's onError -> tokenProvider.refreshToken() -> POST /auth/refresh
    fetchMock.mockResolvedValueOnce(
      jsonResponse({
        accessToken: 'fresh-access',
        refreshToken: 'fresh-refresh',
      }),
    );
    // 3) replay of /auth/me with the fresh token -> 200
    fetchMock.mockResolvedValueOnce(
      jsonResponse({ id: 1, username: 'emilys' }),
    );

    const res = await getCurrentUser();

    expect(res.data).toEqual({ id: 1, username: 'emilys' });
    expect(getDummyJsonAccessToken()).toBe('fresh-access');
    expect(fetchMock).toHaveBeenCalledTimes(4); // login + 401 + refresh + replay

    const firstMeHeaders = fetchMock.mock.calls[1][1].headers as Headers;
    expect(firstMeHeaders.get('Authorization')).toBe('Bearer stale-access');

    const refreshCall = fetchMock.mock.calls[2];
    expect(String(refreshCall[0])).toBe('https://dummyjson.com/auth/refresh');
    expect(JSON.parse(refreshCall[1].body as string)).toEqual({
      refreshToken: 'valid-refresh',
    });

    const replayHeaders = fetchMock.mock.calls[3][1].headers as Headers;
    expect(replayHeaders.get('Authorization')).toBe('Bearer fresh-access');
  });

  it('signs out (clears tokens) when the refresh token itself is rejected', async () => {
    fetchMock.mockResolvedValueOnce(
      jsonResponse({
        id: 1,
        username: 'emilys',
        accessToken: 'stale-access',
        refreshToken: 'dead-refresh',
      }),
    );
    await login({ username: 'emilys', password: 'emilyspass' });

    fetchMock.mockResolvedValueOnce(jsonResponse({}, 401)); // /auth/me
    fetchMock.mockResolvedValueOnce(jsonResponse({}, 401)); // /auth/refresh itself rejects

    // The refresh call failing throws a plain Error (not an ApiError, no
    // `.status`) — see `token-provider.ts`'s `refreshDummyJsonToken` — so the
    // caller sees "refresh failed", not the original 401's shape.
    await expect(getCurrentUser()).rejects.toThrow('Refresh failed: 401');
    expect(getDummyJsonAccessToken()).toBeNull();
  });
});
