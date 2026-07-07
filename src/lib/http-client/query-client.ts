import { QueryClient } from '@tanstack/react-query';

/**
 * Shared `QueryClient` factory — used both by RSC pages (a fresh instance per
 * request; see blog-example/page.tsx) and by the browser provider (one
 * instance for the tab's lifetime; see app/providers.tsx). Not `'use client'`
 * so Server Components can import it directly.
 */
export function makeQueryClient(): QueryClient {
  return new QueryClient({
    defaultOptions: {
      queries: {
        // Transport retries (network, 5xx, Retry-After) already happen inside
        // HttpClient — retrying again here would multiply an attempt (3 HttpClient retries × 3 TQ retries = 9).
        retry: false,
        // > 0 is required: hydrating a server-prefetched query with staleTime 0
        // (the TQ default) marks it stale immediately, triggering an instant
        // client refetched and defeating the prefetch.
        staleTime: 60_000,
      },
    },
  });
}
