import { NextRequest } from 'next/server';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { DELETE, GET, POST } from '../route';

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });
}

function req(
  url: string,
  init?: Omit<RequestInit, 'signal'>,
): { req: NextRequest; params: Promise<{ path: string[] }> } {
  const path = new URL(url).pathname
    .replace(/^\/api\/dummyjson\//, '')
    .split('/');
  return { req: new NextRequest(url, init), params: Promise.resolve({ path }) };
}

describe('dummyjson proxy route', () => {
  let fetchMock: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('forwards query params and relays a 200 JSON response', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse({ products: [], total: 0 }));

    const { req: request, params } = req(
      'http://localhost/api/dummyjson/products?limit=2',
    );
    const res = await GET(request, { params });

    expect(res.status).toBe(200);
    await expect(res.json()).resolves.toEqual({ products: [], total: 0 });
    const [url] = fetchMock.mock.calls[0];
    expect(new URL(String(url)).searchParams.get('limit')).toBe('2');
  });

  it('forwards the Authorization header to the upstream call', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse({ username: 'emilys' }));

    const { req: request, params } = req(
      'http://localhost/api/dummyjson/auth/me',
      { headers: { Authorization: 'Bearer abc123' } },
    );
    await GET(request, { params });

    const [, init] = fetchMock.mock.calls[0];
    const headers = init.headers as Headers;
    expect(headers.get('Authorization')).toBe('Bearer abc123');
  });

  it('sends no Authorization header when the browser sent none', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse({ products: [] }));

    const { req: request, params } = req(
      'http://localhost/api/dummyjson/products',
    );
    await GET(request, { params });

    const [, init] = fetchMock.mock.calls[0];
    const headers = init.headers as Headers;
    expect(headers.has('Authorization')).toBe(false);
  });

  it('forwards a JSON body on POST', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse({ accessToken: 'x' }));

    const { req: request, params } = req(
      'http://localhost/api/dummyjson/auth/login',
      {
        method: 'POST',
        body: JSON.stringify({ username: 'emilys', password: 'emilyspass' }),
      },
    );
    await POST(request, { params });

    const [, init] = fetchMock.mock.calls[0];
    expect(init.method).toBe('POST');
    expect(JSON.parse(init.body as string)).toEqual({
      username: 'emilys',
      password: 'emilyspass',
    });
  });

  it('rejects an invalid JSON body without ever calling fetch', async () => {
    const { req: request, params } = req(
      'http://localhost/api/dummyjson/auth/login',
      { method: 'POST', body: '{not json' },
    );
    const res = await POST(request, { params });

    expect(res.status).toBe(400);
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('passes through a null-body status (e.g. 204 from a DELETE) without parsing JSON', async () => {
    fetchMock.mockResolvedValueOnce(new Response(null, { status: 204 }));

    const { req: request, params } = req(
      'http://localhost/api/dummyjson/products/1',
    );
    const res = await DELETE(request, { params });

    expect(res.status).toBe(204);
    expect(await res.text()).toBe('');
  });

  it('relays an upstream ApiError as the same status + body', async () => {
    fetchMock.mockResolvedValueOnce(
      jsonResponse({ message: 'Invalid/Expired Token!' }, 401),
    );

    const { req: request, params } = req(
      'http://localhost/api/dummyjson/auth/me',
      { headers: { Authorization: 'Bearer garbage' } },
    );
    const res = await GET(request, { params });

    expect(res.status).toBe(401);
    await expect(res.json()).resolves.toEqual({
      message: 'Invalid/Expired Token!',
    });
  });

  it('maps a timed-out upstream call to 504', async () => {
    fetchMock.mockRejectedValueOnce(
      Object.assign(new Error('timeout'), { name: 'TimeoutError' }),
    );

    const { req: request, params } = req(
      'http://localhost/api/dummyjson/products',
    );
    const res = await GET(request, { params });

    expect(res.status).toBe(504);
  });

  it('maps an unclassified network failure to 502', async () => {
    fetchMock.mockRejectedValueOnce(new Error('DNS lookup failed'));

    const { req: request, params } = req(
      'http://localhost/api/dummyjson/products',
    );
    const res = await GET(request, { params });

    expect(res.status).toBe(502);
  });
});
