import { getDummyJsonBaseUrl } from '../base-url';
import { dummyJsonUrls } from '../urls';

import type { TokenProvider } from '@/lib/http-client/plugins';
import type { AuthTokens } from './types';

// Module-scope, not per-request state — this is a single demo client with one
// shared `dummyJsonApi` instance, so one in-memory token pair is enough.
let tokens: AuthTokens | null = null;

/**
 * Raw `fetch`, not `dummyJsonApi.post(...)` — `client.ts` imports this file
 * to build the `auth` plugin, so calling back into `dummyJsonApi` from here
 * would import `client.ts` back, closing a `client.ts -> token-provider.ts ->
 * client.ts` cycle. (The "HttpClient is not a constructor" class of bug the
 * http-client library guards against — see `no-cross-barrel-imports.test.ts`
 * in `@/lib/http-client`.)
 */
async function refreshDummyJsonToken(): Promise<string> {
  const current = tokens;
  if (!current?.refreshToken) {
    throw new Error('No refresh token available');
  }
  const res = await fetch(
    `${getDummyJsonBaseUrl()}${dummyJsonUrls.auth.refresh()}`,
    {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ refreshToken: current.refreshToken }),
    },
  );
  // The session can change while the request is in flight (logout, a new
  // login). Only touch `tokens` if it's still the pair this refresh started
  // from — otherwise a late response would revive a logged-out session.
  if (tokens !== current) {
    throw new Error('Session changed during refresh');
  }
  if (!res.ok) {
    clearDummyJsonTokens();
    throw new Error(`Refresh failed: ${res.status}`);
  }

  const next: AuthTokens = await res.json();
  if (tokens !== current) {
    throw new Error('Session changed during refresh');
  }
  tokens = next;
  return next.accessToken;
}

export function setDummyJsonTokens(next: AuthTokens): void {
  tokens = next;
}

export function clearDummyJsonTokens(): void {
  tokens = null;
}

export function getDummyJsonAccessToken(): string | null {
  return tokens?.accessToken ?? null;
}

export const dummyJsonTokenProvider: TokenProvider = {
  getToken: getDummyJsonAccessToken,
  refreshToken: refreshDummyJsonToken,
  onAuthFailure() {
    // Refresh token itself rejected (dead session) — nothing left to do but
    // drop the pair; the next `login()` starts clean.
    clearDummyJsonTokens();
  },
};
