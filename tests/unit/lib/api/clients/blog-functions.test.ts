import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status });
}

describe('blog client domain functions', () => {
  let fetchMock: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('getPosts sends _page/_limit params', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse([{ id: 1 }]));
    const { getPosts } = await import('@/lib/api/clients/blog/client');

    const res = await getPosts(2, 5);

    expect(res.data).toEqual([{ id: 1 }]);
    const url = fetchMock.mock.calls[0][0] as string;
    expect(url).toContain('_page=2');
    expect(url).toContain('_limit=5');
  });

  it('getPost fetches a single post by id', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse({ id: 7, title: 'x' }));
    const { getPost } = await import('@/lib/api/clients/blog/client');

    const res = await getPost(7);

    expect(res.data).toEqual({ id: 7, title: 'x' });
    expect(fetchMock.mock.calls[0][0]).toContain('/posts/7');
  });

  it('getPostComments fetches comments for a post', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse([{ id: 1, postId: 7 }]));
    const { getPostComments } = await import('@/lib/api/clients/blog/client');

    const res = await getPostComments(7);

    expect(res.data).toEqual([{ id: 1, postId: 7 }]);
    expect(fetchMock.mock.calls[0][0]).toContain('/posts/7/comments');
  });

  it('getUser fetches a user by id with a longer revalidate override', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse({ id: 3, name: 'Ann' }));
    const { getUser } = await import('@/lib/api/clients/blog/client');

    const res = await getUser(3);

    expect(res.data).toEqual({ id: 3, name: 'Ann' });
    expect(fetchMock.mock.calls[0][0]).toContain('/users/3');
  });
});
