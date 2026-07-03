import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status });
}

/** `queryFn` is typed optional on `QueryOptions` (TQ supports omitting it),
 * but every `usersQueries` factory always sets one. */
function callQueryFn<T extends { queryFn?: (ctx: never) => unknown }>(
  options: T,
  signal = new AbortController().signal,
): ReturnType<NonNullable<T['queryFn']>> {
  if (!options.queryFn) throw new Error('expected queryFn to be set');
  return options.queryFn({ signal } as never) as ReturnType<
    NonNullable<T['queryFn']>
  >;
}

describe('usersQueries', () => {
  let fetchMock: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('detail() builds a queryKey scoped by id', async () => {
    const { usersQueries } = await import('@/lib/api/clients/blog/users');

    expect(usersQueries.detail(3).queryKey).toEqual(['users', 'detail', 3]);
  });

  it('detail() resolves a single user', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse({ id: 3, name: 'Ann' }));
    const { usersQueries } = await import('@/lib/api/clients/blog/users');

    const data = await callQueryFn(usersQueries.detail(3));

    expect(data).toEqual({ id: 3, name: 'Ann' });
  });
});
