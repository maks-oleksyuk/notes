// `HttpClient` from `@/lib/api/core`, not the root `@/lib/api` barrel — the root
// also re-exports this very client instance, so importing it here is a real
// circular import (root -> clients/backend -> root) that throws
// "HttpClient is not a constructor" at module-load time (review.md A4; caught
// live by tests/lib/api/clients/backend.test.ts).
import { HttpClient } from '@/lib/api/core';
import { auth } from '@/lib/api/plugins';

import type { TokenProvider } from '@/lib/api/plugins';

// api_old parity: a static bearer token from env, no refresh endpoint — matches
// the old client's `auth: { type: 'bearer', token: env.NEXT_PUBLIC_API_TOKEN }`.
// `refreshToken` throws because there's nothing to refresh against; a real
// refresh flow (if the backend gets one) replaces this provider, not the plugin.
const staticToken = process.env.NEXT_PUBLIC_API_TOKEN || null;

const tokenProvider: TokenProvider = {
  getToken: () => staticToken,
  async refreshToken() {
    throw new Error(
      'backendApi has no refresh endpoint configured (static token only)',
    );
  },
};

/**
 * Pre-configured instance of HttpClient for our Backend API.
 */
export const backendApi = new HttpClient(
  process.env.NEXT_PUBLIC_API_URL || 'http://localhost:3001/api',
  {
    // Default headers for all backend requests
    headers: {
      'Accept-Language': 'uk',
    },
    // api_old parity: every request had a ceiling (30s) so a hung backend never
    // hangs the caller forever. The new core makes `timeout` opt-in per client/
    // request — this client opts in explicitly instead of silently having none.
    timeout: 30_000,
    plugins: staticToken ? [auth(tokenProvider)] : [],
  },
);
