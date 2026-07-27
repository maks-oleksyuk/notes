import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { getCurrentUser, login, logout } from '../requests';
import {
  clearDummyJsonTokens,
  getDummyJsonAccessToken,
} from '../token-provider';

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });
}

const loginBody = {
  id: 1,
  username: 'emilys',
  email: 'emily.johnson@x.dummyjson.com',
  firstName: 'Emily',
  lastName: 'Johnson',
  gender: 'female',
  image: 'https://dummyjson.com/icon/emilys/128',
  accessToken: 'access-1',
  refreshToken: 'refresh-1',
};

describe('dummyjson auth requests', () => {
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

  it('login posts credentials and stores the returned token pair', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(loginBody));

    const res = await login({ username: 'emilys', password: 'emilyspass' });

    expect(res.data).toEqual(loginBody);
    expect(getDummyJsonAccessToken()).toBe('access-1');
    const [url, init] = fetchMock.mock.calls[0];
    expect(String(url)).toBe('https://dummyjson.com/auth/login');
    expect(JSON.parse(init.body as string)).toEqual({
      username: 'emilys',
      password: 'emilyspass',
    });
  });

  it('getCurrentUser attaches the stored access token as a Bearer header', async () => {
    fetchMock
      .mockResolvedValueOnce(jsonResponse(loginBody))
      .mockResolvedValueOnce(jsonResponse({ id: 1, username: 'emilys' }));

    await login({ username: 'emilys', password: 'emilyspass' });
    await getCurrentUser();

    const meHeaders = fetchMock.mock.calls[1][1].headers as Headers;
    expect(meHeaders.get('Authorization')).toBe('Bearer access-1');
  });

  it('logout clears the token pair without any network call', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(loginBody));
    await login({ username: 'emilys', password: 'emilyspass' });
    fetchMock.mockClear();

    logout();

    expect(getDummyJsonAccessToken()).toBeNull();
    expect(fetchMock).not.toHaveBeenCalled();
  });
});
