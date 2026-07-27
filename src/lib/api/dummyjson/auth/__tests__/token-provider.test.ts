import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import {
  clearDummyJsonTokens,
  dummyJsonTokenProvider,
  getDummyJsonAccessToken,
  setDummyJsonTokens,
} from '../token-provider';

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });
}

describe('dummyJsonTokenProvider', () => {
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

  it('getToken returns null before any tokens are set', () => {
    expect(dummyJsonTokenProvider.getToken()).toBeNull();
  });

  it('getToken returns the stored access token', () => {
    setDummyJsonTokens({ accessToken: 'a1', refreshToken: 'r1' });
    expect(dummyJsonTokenProvider.getToken()).toBe('a1');
  });

  it('refreshToken POSTs the stored refresh token and stores the response', async () => {
    setDummyJsonTokens({ accessToken: 'stale', refreshToken: 'r1' });
    fetchMock.mockResolvedValueOnce(
      jsonResponse({ accessToken: 'fresh', refreshToken: 'r2' }),
    );

    const newToken = await dummyJsonTokenProvider.refreshToken?.();

    expect(newToken).toBe('fresh');
    expect(getDummyJsonAccessToken()).toBe('fresh');
    const [url, init] = fetchMock.mock.calls[0];
    expect(String(url)).toBe('https://dummyjson.com/auth/refresh');
    expect(init.method).toBe('POST');
    expect(JSON.parse(init.body as string)).toEqual({ refreshToken: 'r1' });
  });

  it('refreshToken throws without ever calling fetch when there is no refresh token', async () => {
    await expect(dummyJsonTokenProvider.refreshToken?.()).rejects.toThrow(
      'No refresh token available',
    );
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('refreshToken clears the pair and throws on a non-ok response', async () => {
    setDummyJsonTokens({ accessToken: 'stale', refreshToken: 'dead' });
    fetchMock.mockResolvedValueOnce(jsonResponse({}, 401));

    await expect(dummyJsonTokenProvider.refreshToken?.()).rejects.toThrow(
      'Refresh failed: 401',
    );
    expect(getDummyJsonAccessToken()).toBeNull();
  });

  it('onAuthFailure clears the token pair', () => {
    setDummyJsonTokens({ accessToken: 'a1', refreshToken: 'r1' });
    dummyJsonTokenProvider.onAuthFailure?.(new Error('dead session'));
    expect(getDummyJsonAccessToken()).toBeNull();
  });
});
