import { getDummyJsonBaseUrl } from '../base-url';
import { dummyJsonUrls } from '../urls';

import type { TokenProvider } from '@/lib/http-client/plugins';
import type { AuthTokens } from './types';

// Module-scope, not per-request state — this is a single demo client with one
// shared `dummyJsonApi` instance, so one in-memory token pair is enough.
let tokens: AuthTokens | null = null;

export function setDummyJsonTokens(next: AuthTokens): void {
  tokens = next;
}

export function clearDummyJsonTokens(): void {
  tokens = null;
}

export function getDummyJsonAccessToken(): string | null {
  return tokens?.accessToken ?? null;
}

/**
 * Raw `fetch`, not `dummyJsonApi.post(...)` — `client.ts` imports this file
 * to build the `auth` plugin, so calling back into `dummyJsonApi` from here
 * would import `client.ts` back, closing a `client.ts -> token-provider.ts ->
 * client.ts` cycle (the "HttpClient is not a constructor" class of bug the
 * http-client library guards against — see `no-cross-barrel-imports.test.ts`
 * in `@/lib/http-client`).
 */
async function refreshDummyJsonToken(): Promise<string> {
  if (!tokens?.refreshToken) throw new Error('No refresh token available');

  // Bypasses `dummyJsonApi` (see the doc above), so the `logger` plugin never
  // sees this call — log it by hand, or a refresh happens invisibly between
  // two identical-looking `/auth/me` log lines.
  console.log('[dummyjson] refreshing access token…');
  const res = await fetch(
    `${getDummyJsonBaseUrl()}${dummyJsonUrls.auth.refresh()}`,
    {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ refreshToken: tokens.refreshToken }),
    },
  );
  if (!res.ok) {
    clearDummyJsonTokens();
    console.log(`[dummyjson] refresh failed: ${res.status}`);
    throw new Error(`Refresh failed: ${res.status}`);
  }

  const next: AuthTokens = await res.json();
  tokens = next;
  console.log('[dummyjson] access token refreshed');
  return next.accessToken;
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
