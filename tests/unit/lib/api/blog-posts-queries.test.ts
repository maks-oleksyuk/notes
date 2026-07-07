import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

function jsonResponse(
  body: unknown,
  init?: { status?: number; headers?: Record<string, string> },
): Response {
  return new Response(JSON.stringify(body), {
    status: init?.status ?? 200,
    headers: init?.headers,
  });
}

/** `queryFn` is typed optional on `QueryOptions` (TQ supports omitting it),
 * but every `postsQueries` factory always sets one. */
function callQueryFn<T extends { queryFn?: (ctx: never) => unknown }>(
  options: T,
  signal = new AbortController().signal,
): ReturnType<NonNullable<T['queryFn']>> {
  if (!options.queryFn) throw new Error('expected queryFn to be set');
  return options.queryFn({ signal } as never) as ReturnType<
    NonNullable<T['queryFn']>
  >;
}

describe('postsQueries', () => {
  let fetchMock: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('list() builds a queryKey scoped by page/limit', async () => {
    const { postsQueries } = await import('@/lib/api/blog/posts');

    expect(postsQueries.list(2, 5).queryKey).toEqual([
      'posts',
      'list',
      { page: 2, limit: 5 },
    ]);
  });

  it('list() resolves posts + totalCount from X-Total-Count', async () => {
    fetchMock.mockResolvedValueOnce(
      jsonResponse([{ id: 1 }], { headers: { 'X-Total-Count': '13' } }),
    );
    const { postsQueries } = await import('@/lib/api/blog/posts');

    const data = await callQueryFn(postsQueries.list(1, 5));

    expect(data).toEqual({ posts: [{ id: 1 }], totalCount: 13 });
  });

  it('list() falls back to totalCount: 0 when X-Total-Count is missing', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse([]));
    const { postsQueries } = await import('@/lib/api/blog/posts');

    const data = await callQueryFn(postsQueries.list(1, 5));

    expect(data.totalCount).toBe(0);
  });

  it('detail() resolves a single post', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse({ id: 7, title: 'x' }));
    const { postsQueries } = await import('@/lib/api/blog/posts');

    const data = await callQueryFn(postsQueries.detail(7));

    expect(data).toEqual({ id: 7, title: 'x' });
  });

  it("comments() resolves a post's comments", async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse([{ id: 1, postId: 7 }]));
    const { postsQueries } = await import('@/lib/api/blog/posts');

    const data = await callQueryFn(postsQueries.comments(7));

    expect(data).toEqual([{ id: 1, postId: 7 }]);
  });
});
