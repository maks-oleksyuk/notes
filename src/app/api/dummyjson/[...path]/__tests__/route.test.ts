import { NextRequest } from 'next/server';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { DELETE, GET, PATCH, POST, PUT } from '../route';

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
    .replace(/^\/api\/dummyjson\//u, '')
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
    vi.useRealTimers();
  });

  it('exports all five methods, all wired to the real dummyJsonServerApi', async () => {
    // A fresh Response per call — reusing one instance across the five
    // handlers below would fail on the second `.json()` read (body already consumed).
    fetchMock.mockImplementation(() => jsonResponse({ ok: true }));

    const responses = await Promise.all(
      [GET, POST, PUT, PATCH, DELETE].map((handler) => {
        const { req: request, params } = req(
          'http://localhost/api/dummyjson/products?limit=2',
        );
        return handler(request, { params });
      }),
    );
    for (const res of responses) expect(res.status).toBe(200);

    const [url] = fetchMock.mock.calls[0];
    expect(String(url)).toMatch(/^https:\/\/.*\/products\?limit=2$/u);
  });

  it('maps a timed-out upstream call to 504 through the real retry config', async () => {
    vi.useFakeTimers();
    fetchMock.mockRejectedValue(
      Object.assign(new Error('timeout'), { name: 'TimeoutError' }),
    );

    const { req: request, params } = req(
      'http://localhost/api/dummyjson/products',
    );
    const resPromise = GET(request, { params });
    await vi.runAllTimersAsync();
    const res = await resPromise;

    expect(res.status).toBe(504);
    // retry: { limit: 3 } on dummyJsonServerApi — confirms retries actually
    // fire through the real config, not just that the error maps to 504.
    expect(fetchMock.mock.calls.length).toBeGreaterThan(1);
  });
});
