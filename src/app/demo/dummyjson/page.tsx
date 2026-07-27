'use client';

import {
  Alert,
  Badge,
  Button,
  Container,
  Group,
  Paper,
  PasswordInput,
  ScrollArea,
  Select,
  Stack,
  Table,
  Text,
  TextInput,
  Title,
} from '@mantine/core';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';

import {
  getCurrentUser,
  login,
  logout,
  productsQueries,
  setDummyJsonTokens,
} from '@/lib/api/dummyjson';

interface LogEntry {
  id: number;
  text: string;
}

const PAGE_SIZE = 15;
const meQueryKey = ['dummyjson-demo', 'me'] as const;

export default function DummyJsonDemoPage() {
  const [username, setUsername] = useState('emilys');
  const [password, setPassword] = useState('emilyspass');
  // Captured from the login response purely to stage the "corrupt token"
  // demo below — the client itself never needs this outside token-provider.ts.
  const [refreshToken, setRefreshToken] = useState<string | null>(null);
  const [page, setPage] = useState(0);
  const [sortBy, setSortBy] = useState<'title' | 'price'>('title');
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

  const products = [...(productsQuery.data?.products ?? [])].sort((a, b) =>
    sortBy === 'price' ? a.price - b.price : a.title.localeCompare(b.title),
  );
  const total = productsQuery.data?.total ?? 0;

  return (
    <Container py='xl' size='sm'>
      <Stack gap='xl'>
        <Stack gap={4}>
          <Title order={1}>dummyjson.com — http-client demo</Title>
          <Text c='dimmed' size='sm'>
            A real, live backend — exercises the http-client library&apos;s
            `auth` plugin (login, Bearer header, 401 → refresh → replay) through
            TanStack Query&apos;s `useQuery`/`useMutation`, no mocks.
          </Text>
        </Stack>

        <Stack gap='sm'>
          <Title order={2} size='h3'>
            Auth
          </Title>

          {user ? (
            <Paper p='md' radius='md' withBorder>
              <Stack gap='sm'>
                <Text size='sm'>
                  Signed in as{' '}
                  <Text fw={700} span>
                    {user.username}
                  </Text>{' '}
                  ({user.email})
                </Text>
                <Group gap='xs'>
                  <Button
                    disabled={busy}
                    onClick={handleWhoAmI}
                    size='xs'
                    variant='default'
                  >
                    GET /auth/me
                  </Button>
                  <Button
                    color='orange'
                    disabled={busy}
                    onClick={handleForceRefresh}
                    size='xs'
                  >
                    Corrupt token &amp; refetch (live refresh)
                  </Button>
                  <Button
                    disabled={busy}
                    onClick={handleLogout}
                    size='xs'
                    variant='default'
                  >
                    Logout
                  </Button>
                </Group>
              </Stack>
            </Paper>
          ) : (
            <Paper
              component='form'
              onSubmit={handleLogin}
              p='md'
              radius='md'
              withBorder
            >
              <Stack gap='sm'>
                <Group grow>
                  <TextInput
                    onChange={(e) => setUsername(e.currentTarget.value)}
                    placeholder='username'
                    required
                    radius='xl'
                    value={username}
                  />
                  <PasswordInput
                    onChange={(e) => setPassword(e.currentTarget.value)}
                    placeholder='password'
                    radius='xl'
                    required
                    value={password}
                  />
                </Group>
                <Button loading={busy} type='submit' radius='xl'>
                  Sign in
                </Button>
              </Stack>
            </Paper>
          )}
        </Stack>

        <Stack gap='sm'>
          <Title order={2} size='h3'>
            Products (public, no token)
          </Title>
          <Group gap='xs'>
            <Button
              disabled={productsQuery.isFetching || page === 0}
              onClick={() => setPage((p) => p - 1)}
              size='xs'
              variant='default'
            >
              ← Prev
            </Button>
            <Button
              disabled={productsQuery.isFetching}
              loading={productsQuery.isFetching}
              onClick={() => productsQuery.refetch()}
              size='xs'
              variant='default'
            >
              Reload
            </Button>
            <Button
              disabled={
                productsQuery.isFetching || (page + 1) * PAGE_SIZE >= total
              }
              onClick={() => setPage((p) => p + 1)}
              size='xs'
              variant='default'
            >
              Next →
            </Button>
            <Badge color='gray' variant='light'>
              {total} total
            </Badge>
            <Select
              allowDeselect={false}
              data={[
                { value: 'title', label: 'Sort: Title' },
                { value: 'price', label: 'Sort: Price' },
              ]}
              onChange={(value) =>
                setSortBy((value as 'title' | 'price') ?? 'title')
              }
              size='xs'
              value={sortBy}
              w={140}
            />
          </Group>
          <Paper radius='md' withBorder>
            <Table highlightOnHover striped>
              <Table.Tbody>
                {products.map((product) => (
                  <Table.Tr key={product.id}>
                    <Table.Td>{product.title}</Table.Td>
                    <Table.Td ta='right'>${product.price}</Table.Td>
                  </Table.Tr>
                ))}
              </Table.Tbody>
            </Table>
          </Paper>
        </Stack>

        {error ? (
          <Alert color='red' variant='light'>
            {error instanceof Error ? error.message : 'Request failed'}
          </Alert>
        ) : null}

        <Stack gap='xs'>
          <Title order={2} size='h3'>
            Log
          </Title>
          <Paper p='sm' radius='md' withBorder>
            <ScrollArea.Autosize mah={200}>
              <Stack gap={4}>
                {log.map((entry) => (
                  <Text c='dimmed' ff='monospace' key={entry.id} size='xs'>
                    {entry.text}
                  </Text>
                ))}
              </Stack>
            </ScrollArea.Autosize>
          </Paper>
        </Stack>
      </Stack>
    </Container>
  );
}
