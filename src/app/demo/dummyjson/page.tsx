'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';

import { getCurrentUser, login, logout } from '@/lib/api/dummyjson/auth';
import { setDummyJsonTokens } from '@/lib/api/dummyjson/auth/token-provider';
import { productsQueries } from '@/lib/api/dummyjson/products';

interface LogEntry {
  id: number;
  text: string;
}

const PAGE_SIZE = 5;
const meQueryKey = ['dummyjson-demo', 'me'] as const;

export default function DummyJsonDemoPage() {
  const [username, setUsername] = useState('emilys');
  const [password, setPassword] = useState('emilyspass');
  // Captured from the login response purely to stage the "corrupt token"
  // demo below — the client itself never needs this outside token-provider.ts.
  const [refreshToken, setRefreshToken] = useState<string | null>(null);
  const [page, setPage] = useState(0);
  const [log, setLog] = useState<LogEntry[]>([]);
  const queryClient = useQueryClient();

  function pushLog(text: string) {
    setLog((prev) =>
      [{ id: Date.now() + Math.random(), text }, ...prev].slice(0, 8),
    );
  }

  // Public read — a real `useQuery` off the client's own `queryOptions`
  // factory, cached/deduped/staleTime'd by TanStack exactly like a real page
  // would use it, not a hand-rolled fetch+useState.
  const productsQuery = useQuery(
    productsQueries.list(page * PAGE_SIZE, PAGE_SIZE),
  );

  // Login is a real mutation (side effect on the server, not a cacheable
  // read) — `useMutation` gives isPending/error for free instead of manual
  // busy/error state.
  const loginMutation = useMutation({
    mutationFn: () => login({ username, password }).then((res) => res.data),
    onSuccess: (data) => {
      setRefreshToken(data.refreshToken);
      pushLog(
        `Logged in as ${data.username} — real access + refresh token issued.`,
      );
    },
  });

  // `enabled: false` — this is a GET, so it's a query, but the demo only
  // wants it to run when the "GET /auth/me" or "corrupt token" buttons are
  // clicked, not automatically on mount/refetch-interval.
  const meQuery = useQuery({
    queryKey: meQueryKey,
    queryFn: async () => (await getCurrentUser()).data,
    enabled: false,
  });

  const user = meQuery.data ?? loginMutation.data;
  const error = loginMutation.error ?? meQuery.error;
  const busy = loginMutation.isPending || meQuery.isFetching;

  function handleLogout() {
    logout();
    loginMutation.reset();
    queryClient.removeQueries({ queryKey: meQueryKey });
    setRefreshToken(null);
    pushLog('Logged out — token pair cleared.');
  }

  async function handleWhoAmI() {
    const res = await meQuery.refetch();
    if (res.data) pushLog(`GET /auth/me succeeded as ${res.data.username}.`);
  }

  /**
   * Deliberately corrupts the in-memory access token (keeping the real
   * refresh token) and refetches `/auth/me` — the live backend rejects the
   * bad token with a real 401, the `auth` plugin calls the real
   * `/auth/refresh`, and the request replays automatically. This reaches
   * into `token-provider.ts` directly (not part of the client's public
   * surface) purely to stage the demo.
   */
  async function handleForceRefresh() {
    if (!refreshToken) return;
    // Only the access token is broken — the real refresh token stays, so
    // the refresh call the auth plugin makes underneath still succeeds.
    setDummyJsonTokens({
      accessToken: 'deliberately-invalid-token',
      refreshToken,
    });
    pushLog('Corrupted the access token on purpose…');

    const res = await meQuery.refetch();
    if (res.data) {
      pushLog(
        'Backend returned a live 401 → auth plugin refreshed → request replayed and succeeded.',
      );
    }
  }

  function handleLogin(e: React.FormEvent) {
    e.preventDefault();
    loginMutation.mutate();
  }

  return (
    <main className='max-w-2xl mx-auto p-8 space-y-8'>
      <div>
        <h1 className='text-3xl font-bold'>dummyjson.com — http-client demo</h1>
        <p className='text-sm text-gray-500'>
          A real, live backend — exercises the http-client library&apos;s `auth`
          plugin (login, Bearer header, 401 → refresh → replay) through TanStack
          Query&apos;s `useQuery`/`useMutation`, no mocks.
        </p>
      </div>

      <section className='space-y-4'>
        <h2 className='text-xl font-semibold'>Auth</h2>

        {user ? (
          <div className='space-y-3 border rounded p-4'>
            <p className='text-sm'>
              Signed in as <strong>{user.username}</strong> ({user.email})
            </p>
            <div className='flex flex-wrap gap-2'>
              <button
                className='border rounded px-3 py-1.5 text-sm disabled:opacity-50'
                disabled={busy}
                onClick={handleWhoAmI}
                type='button'
              >
                GET /auth/me
              </button>
              <button
                className='border rounded px-3 py-1.5 text-sm bg-amber-600 text-white disabled:opacity-50'
                disabled={busy}
                onClick={handleForceRefresh}
                type='button'
              >
                Corrupt token &amp; refetch (live refresh)
              </button>
              <button
                className='border rounded px-3 py-1.5 text-sm disabled:opacity-50'
                disabled={busy}
                onClick={handleLogout}
                type='button'
              >
                Logout
              </button>
            </div>
          </div>
        ) : (
          <form className='space-y-3 border rounded p-4' onSubmit={handleLogin}>
            <div className='flex gap-3'>
              <input
                className='flex-1 border rounded px-3 py-2 text-sm'
                onChange={(e) => setUsername(e.target.value)}
                placeholder='username'
                required
                type='text'
                value={username}
              />
              <input
                className='flex-1 border rounded px-3 py-2 text-sm'
                onChange={(e) => setPassword(e.target.value)}
                placeholder='password'
                required
                type='password'
                value={password}
              />
            </div>
            <button
              className='border rounded px-4 py-2 text-sm bg-blue-600 text-white disabled:opacity-50'
              disabled={busy}
              type='submit'
            >
              {busy ? 'Signing in…' : 'Sign in'}
            </button>
          </form>
        )}
      </section>

      <section className='space-y-4'>
        <h2 className='text-xl font-semibold'>Products (public, no token)</h2>
        <div className='flex gap-2'>
          <button
            className='border rounded px-3 py-1.5 text-sm disabled:opacity-50'
            disabled={productsQuery.isFetching || page === 0}
            onClick={() => setPage((p) => p - 1)}
            type='button'
          >
            ← Prev
          </button>
          <button
            className='border rounded px-3 py-1.5 text-sm disabled:opacity-50'
            disabled={productsQuery.isFetching}
            onClick={() => productsQuery.refetch()}
            type='button'
          >
            Reload
          </button>
          <button
            className='border rounded px-3 py-1.5 text-sm disabled:opacity-50'
            disabled={
              productsQuery.isFetching ||
              (page + 1) * PAGE_SIZE >= (productsQuery.data?.total ?? 0)
            }
            onClick={() => setPage((p) => p + 1)}
            type='button'
          >
            Next →
          </button>
        </div>
        <ul className='divide-y border rounded'>
          {(productsQuery.data?.products ?? []).map((product) => (
            <li className='p-3 text-sm flex justify-between' key={product.id}>
              <span>{product.title}</span>
              <span className='text-gray-500'>${product.price}</span>
            </li>
          ))}
        </ul>
      </section>

      {error ? (
        <p className='text-red-600 text-sm'>
          {error instanceof Error ? error.message : 'Request failed'}
        </p>
      ) : null}

      <section className='space-y-2'>
        <h2 className='text-xl font-semibold'>Log</h2>
        <ul className='text-xs text-gray-500 space-y-1 font-mono'>
          {log.map((entry) => (
            <li key={entry.id}>{entry.text}</li>
          ))}
        </ul>
      </section>
    </main>
  );
}
