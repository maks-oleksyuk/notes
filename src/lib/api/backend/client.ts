// `HttpClient` from `@/lib/http-client/core` directly — a barrel re-exporting both
// core and this client instance is a circular import that throws "HttpClient
// is not a constructor" at module-load time (review.md A4, caught live by
// tests/lib/api/clients/backend.test.ts); the root barrel was removed for
// exactly that reason.
import { HttpClient } from '@/lib/http-client/core';
import { auth } from '@/lib/http-client/plugins';

import type { TokenProvider } from '@/lib/http-client/plugins';

// api_old parity: a static bearer token from env, no refresh endpoint — matches
// the old client's `auth: { type: 'bearer', token: env.NEXT_PUBLIC_API_TOKEN }`.
// Note: NEXT_PUBLIC_* is inlined into the client bundle — the token is visible
// to anyone opening DevTools. Acceptable for this test API (parity with the
// old client); a real secret belongs in a server-only env var + Route Handler.
//
// No `refreshToken` on purpose: with nothing to refresh against, the auth
// plugin reports a 401 via `onAuthFailure` and lets the original ApiError
// (status 401, server data) propagate — a throwing stub here used to replace
// it with a generic "no refresh endpoint" error, hiding the status from
// callers. A real refresh flow (if the backend gets one) extends this
// provider, not the plugin.
const staticToken = process.env.NEXT_PUBLIC_API_TOKEN || null;

const tokenProvider: TokenProvider = {
  getToken: () => staticToken,
};

/**
 * Pre-configured instance of HttpClient for our Backend API.
 */
export const backendApi = new HttpClient(
  process.env.NEXT_PUBLIC_API_URL || 'http://localhost:3001/api',
  {
    headers: {
      'Accept-Language': 'uk',
    },
    // api_old parity: every request had a ceiling (30s), so a hung backend never
    // hangs the caller forever. The new core makes `timeout` opt-in per client/
    // request — this client opts in explicitly instead of silently having none.
    timeout: 30_000,
    plugins: staticToken ? [auth(tokenProvider)] : [],
  },
);
