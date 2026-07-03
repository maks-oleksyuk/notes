'use client';

import { QueryClientProvider } from '@tanstack/react-query';
import { ReactQueryDevtools } from '@tanstack/react-query-devtools';
import { useState } from 'react';

import { makeQueryClient } from '@/lib/api/query-client';

import type { QueryClient } from '@tanstack/react-query';
import type React from 'react';

// A fresh QueryClient per browser tab, created once via `useState`'s lazy
// initializer (not `useMemo` — that's not guaranteed to skip re-creation
// across renders, `useState` is). One instance for the whole app lifetime;
// the server gets its own QueryClient per request instead (see blog-example/
// page.tsx) — sharing one across requests would leak cached data between
// users, the same reasoning as "no mutable user state in module scope" for
// the HttpClient token providers.
let browserQueryClient: QueryClient | undefined;

function getQueryClient(): QueryClient {
  if (typeof window === 'undefined') {
    // Server: always a new client, never reused across requests/users.
    return makeQueryClient();
  }
  // Browser: reuse the same client across re-renders (e.g. Suspense/Fast
  // Refresh remounting this component) instead of creating one per render.
  browserQueryClient ??= makeQueryClient();
  return browserQueryClient;
}

export function Providers({ children }: { children: React.ReactNode }) {
  const [queryClient] = useState(getQueryClient);

  return (
    <QueryClientProvider client={queryClient}>
      {children}
      <ReactQueryDevtools initialIsOpen={false} />
    </QueryClientProvider>
  );
}
