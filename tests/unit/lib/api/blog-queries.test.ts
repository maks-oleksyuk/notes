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
 * but `blogQueries.pageData` always sets one. */
function callQueryFn<T extends { queryFn?: (ctx: never) => unknown }>(
  options: T,
  signal = new AbortController().signal,
): ReturnType<NonNullable<T['queryFn']>> {
  if (!options.queryFn) throw new Error('expected queryFn to be set');
  return options.queryFn({ signal } as never) as ReturnType<
    NonNullable<T['queryFn']>
  >;
}

describe('blogQueries.pageData', () => {
  let fetchMock: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('builds a queryKey scoped by page', async () => {
    const { blogQueries } = await import('@/lib/api/blog');

    expect(blogQueries.pageData(1).queryKey).toEqual([
      'blog',
      'pageData',
      { page: 1 },
    ]);
    expect(blogQueries.pageData(2).queryKey).toEqual([
      'blog',
      'pageData',
      { page: 2 },
    ]);
  });

  it('resolves posts + author names + totalPages from X-Total-Count', async () => {
    fetchMock
      .mockResolvedValueOnce(
        jsonResponse([{ id: 1, userId: 7, title: 't', body: 'b' }], {
          headers: { 'X-Total-Count': '13' },
        }),
      )
      .mockResolvedValueOnce(jsonResponse({ id: 7, name: 'Ann' }));
    const { blogQueries } = await import('@/lib/api/blog');

    const data = await callQueryFn(blogQueries.pageData(1));

    expect(data).toEqual({
      posts: [{ id: 1, userId: 7, title: 't', body: 'b' }],
      users: { 7: 'Ann' },
      totalPages: 3, // ceil(13 / 5)
    });
  });

  it('falls back to totalPages: 0 when X-Total-Count is missing', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse([]));
    const { blogQueries } = await import('@/lib/api/blog');

    const data = await callQueryFn(blogQueries.pageData(1));

    expect(data.totalPages).toBe(0);
  });

  it('keeps a post when its author lookup fails, without failing the whole page', async () => {
    fetchMock
      .mockResolvedValueOnce(
        jsonResponse([{ id: 1, userId: 7, title: 't', body: 'b' }], {
          headers: { 'X-Total-Count': '1' },
        }),
      )
      .mockResolvedValueOnce(jsonResponse({}, { status: 404 }));
    const { blogQueries } = await import('@/lib/api/blog');

    const data = await callQueryFn(blogQueries.pageData(1));

    expect(data.posts).toHaveLength(1);
    expect(data.users).toEqual({});
  });

  it('lets a failed posts fetch throw, so TanStack Query surfaces it as the query error', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse({}, { status: 500 }));
    const { blogQueries } = await import('@/lib/api/blog');

    await expect(callQueryFn(blogQueries.pageData(1))).rejects.toMatchObject({
      status: 500,
    });
  });

  it('dedupes author lookups: two posts by the same user only fetch that user once', async () => {
    fetchMock
      .mockResolvedValueOnce(
        jsonResponse(
          [
            { id: 1, userId: 7, title: 'a', body: 'x' },
            { id: 2, userId: 7, title: 'b', body: 'y' },
          ],
          { headers: { 'X-Total-Count': '2' } },
        ),
      )
      .mockResolvedValueOnce(jsonResponse({ id: 7, name: 'Ann' }));
    const { blogQueries } = await import('@/lib/api/blog');

    const data = await callQueryFn(blogQueries.pageData(1));

    expect(fetchMock).toHaveBeenCalledTimes(2); // 1 posts call + 1 (not 2) user call
    expect(data.users).toEqual({ 7: 'Ann' });
  });
});
