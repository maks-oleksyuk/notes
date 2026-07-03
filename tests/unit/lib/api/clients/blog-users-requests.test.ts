import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status });
}

describe('users requests (raw)', () => {
  let fetchMock: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('getUser fetches a user by id with a longer revalidate override', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse({ id: 3, name: 'Ann' }));
    const { getUser } = await import('@/lib/api/clients/blog/users/requests');

    const res = await getUser(3);

    expect(res.data).toEqual({ id: 3, name: 'Ann' });
    expect(fetchMock.mock.calls[0][0]).toContain('/users/3');
    // The per-request `next` replaces the client default wholesale, so the
    // 'blog' tag must survive here explicitly — or revalidateTag('blog')
    // would skip user fetches (C4).
    expect(fetchMock.mock.calls[0][1].next).toEqual({
      revalidate: 3600,
      tags: ['blog'],
    });
  });
});
