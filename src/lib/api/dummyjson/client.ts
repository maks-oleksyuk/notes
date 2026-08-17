// Import from `@/lib/http-client/core` / `@/lib/http-client/plugins` directly —
// a barrel that re-exported both core and this client would be a circular
// import (review.md A4); the root barrel was removed for exactly that reason.

import { HttpClient } from '@/lib/http-client/core';
import { auth, logger, sentry } from '@/lib/http-client/plugins';

import { dummyJsonTokenProvider } from './auth/token-provider';
import { getDummyJsonBaseUrl } from './base-url';

import type { ApiRequestOptions } from '@/lib/http-client/core';

/**
 * The one `HttpClient` instance for the dummyjson.com demo API — a real,
 * live backend used specifically to exercise the `auth` plugin's login →
 * 401 → refresh → replay flow against an actual token lifecycle, not a mock.
 *
 * In the browser this talks to our own `/api/dummyjson` proxy, never
 * dummyjson.com directly (see `base-url.ts` / `server-client.ts`).
 */
export const dummyJsonApi = new HttpClient(getDummyJsonBaseUrl(), {
  timeout: 10_000,
  retry: { limit: 3 },
  plugins: [
    logger({ level: 'info', prefix: 'dummyjson' }),
    auth(dummyJsonTokenProvider),
    sentry(),
  ],
});

/** Only what callers (e.g. a `queries.ts` threading a TanStack Query `signal`)
 * need to override per call — not the full `ApiRequestOptions`, so a caller
 * can't accidentally clobber `params` an entity's request already sets. */
export type RequestOverrides = Pick<ApiRequestOptions, 'signal'>;
