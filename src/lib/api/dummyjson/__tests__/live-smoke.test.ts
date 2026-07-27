import { afterEach, describe, expect, it } from 'vitest';

import { getCurrentUser, login } from '../auth/requests';
import {
  clearDummyJsonTokens,
  getDummyJsonAccessToken,
  setDummyJsonTokens,
} from '../auth/token-provider';
import { getProducts } from '../products/requests';

/**
 * Hits the real dummyjson.com — no mocked `fetch`. Skipped unless
 * `RUN_LIVE_TESTS=1`: it depends on network + a third-party service's uptime,
 * so it must never gate CI or a normal local run. Exists to prove the
 * `auth` plugin's refresh-and-replay actually works against a live token
 * lifecycle, not just the shapes asserted in the mocked tests.
 *
 * Run with: RUN_LIVE_TESTS=1 vitest run --config config/utils/vitest.config.ts src/lib/api/dummyjson/__tests__/live-smoke.test.ts
 */
describe.skipIf(!process.env.RUN_LIVE_TESTS)('dummyjson live smoke', () => {
  afterEach(() => {
    clearDummyJsonTokens();
  });

  it('logs in and reads the authenticated user', async () => {
    const res = await login({ username: 'emilys', password: 'emilyspass' });

    expect(res.data.accessToken).toBeTruthy();
    expect(res.data.refreshToken).toBeTruthy();

    const me = await getCurrentUser();
    expect(me.data.username).toBe('emilys');
  }, 15_000);

  it('refreshes and replays automatically when the access token is invalid', async () => {
    const loginRes = await login({
      username: 'emilys',
      password: 'emilyspass',
    });

    // Corrupt only the access token — keep the real refresh token, so the
    // live refresh call the auth plugin makes actually succeeds.
    setDummyJsonTokens({
      accessToken: 'deliberately-invalid-token',
      refreshToken: loginRes.data.refreshToken,
    });

    // Real backend rejects the corrupted token with a live 401, forcing the
    // auth plugin to call the real /auth/refresh, get a real new token pair,
    // and replay this same request.
    const me = await getCurrentUser();
    expect(me.data.username).toBe('emilys');
    expect(getDummyJsonAccessToken()).not.toBe('deliberately-invalid-token');
  }, 15_000);

  it('reads the public products catalog without a token', async () => {
    clearDummyJsonTokens();
    const res = await getProducts(0, 3);
    expect(res.data.products.length).toBe(3);
    expect(res.data.total).toBeGreaterThan(0);
  }, 15_000);
});
