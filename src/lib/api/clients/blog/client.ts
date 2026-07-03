// Import from `@/lib/api/core` directly, never the root barrel — the root
// re-exports this very client tree, so importing it here would be a real
// circular import (root -> clients/blog -> root), review.md A4.
import { HttpClient } from '@/lib/api/core';
import { logger } from '@/lib/api/plugins';

import type { ApiRequestOptions } from '@/lib/api/core';

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
