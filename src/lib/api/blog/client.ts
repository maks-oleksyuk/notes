// Import from `@/lib/http-client/core` / `@/lib/http-client/plugins` directly — a barrel that
// re-exported both core and this client tree would be a circular import
// (review.md A4); the root barrel was removed for exactly that reason.
import { HttpClient } from '@/lib/http-client/core';
import { logger } from '@/lib/http-client/plugins';

import type { ApiRequestOptions } from '@/lib/http-client/core';

/**
 * The one `HttpClient` instance for the JSONPlaceholder Blog API — shared by
 * every entity under `clients/blog/` (`posts/`, `users/`, ...). Entities
 * don't get their own `HttpClient`; they just call `blogApi.get/post/...`
 * with their own URLs and types.
 */
export const blogApi = new HttpClient('https://jsonplaceholder.typicode.com', {
  next: {
    revalidate: 60,
    tags: ['blog'],
  },
  plugins: [logger({ level: 'info', prefix: 'blog' })],
});

/** Only what callers (e.g. a `queries.ts` threading a TanStack Query `signal`)
 * need to override per call — not the full `ApiRequestOptions`, so a caller
 * can't accidentally clobber `params`/`next` an entity's client already sets. */
export type RequestOverrides = Pick<ApiRequestOptions, 'signal'>;
