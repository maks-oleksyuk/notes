// Import from `@/lib/http-client/core` / `@/lib/http-client/plugins` directly —
// a barrel that re-exported both core and this client would be a circular
// import (review.md A4); the root barrel was removed for exactly that reason.
import { HttpClient } from '@/lib/http-client/core';
import { logger } from '@/lib/http-client/plugins';

import { getDummyJsonBaseUrl } from './base-url';

/**
 * Server-only instance — the only thing that ever talks to dummyjson.com
 * directly. Used exclusively by the `/api/dummyjson` proxy Route Handler.
 *
 * No `auth` plugin here: the token stays client-owned (the browser's own
 * `dummyJsonApi` attaches `Authorization`), so the proxy just forwards
 * whatever header it received — it never holds or refreshes a token itself.
 */
export const dummyJsonServerApi = new HttpClient(getDummyJsonBaseUrl(), {
  timeout: 10_000,
  retry: { limit: 3 },
  plugins: [logger({ level: 'info', prefix: 'dummyjson:server' })],
});
