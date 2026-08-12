import { dummyJsonApi } from '../client';
import { dummyJsonUrls } from '../urls';
import { clearDummyJsonTokens, setDummyJsonTokens } from './token-provider';

import type { RequestOverrides } from '../client';
import type { AuthUser, LoginCredentials, LoginResponse } from './types';

/** Raw request functions — one HTTP call each, throw-style (see
 * core/safe.ts for the error-as-value alternative). */

export async function login(
  credentials: LoginCredentials,
  overrides?: RequestOverrides,
) {
  const response = await dummyJsonApi.post<LoginResponse>(
    dummyJsonUrls.auth.login(),
    credentials,
    overrides,
  );
  setDummyJsonTokens(response.data);
  return response;
}

/** Clears the in-memory token pair — no server-side call, dummyjson has no
 * logout endpoint (tokens just expire / get rotated on refresh). */
export function logout(): void {
  clearDummyJsonTokens();
}

/** Requires a valid access token — the `auth` plugin attaches it and
 * transparently refreshes+replays once on a 401. */
export function getCurrentUser(overrides?: RequestOverrides) {
  return dummyJsonApi.get<AuthUser>(dummyJsonUrls.auth.me(), overrides);
}
