import { NextRequest } from 'next/server';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { HttpClient } from '@/lib/http-client/core';
import { toNextJsProxyHandler } from '@/lib/http-client/next-proxy';

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });
}

function req(
  url: string,
  init?: Omit<RequestInit, 'signal'>,
  path: string[] = ['x'],
): { req: NextRequest; params: Promise<{ path: string[] }> } {
  return { req: new NextRequest(url, init), params: Promise.resolve({ path }) };
}

describe('toNextJsProxyHandler', () => {
  let fetchMock: ReturnType<typeof vi.fn>;
  let api: HttpClient;

  beforeEach(() => {
    fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);
    // No `retry` option — resolveRetry(undefined) disables retries, so every
    // error-path test below resolves in exactly one fetch call.
    api = new HttpClient('https://api.test');
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('joins the path segments and forwards the method', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse({ ok: true }));
    const { GET } = toNextJsProxyHandler(api);

    const { req: request, params } = req(
      'http://localhost/proxy/x',
      undefined,
      ['products', '1'],
    );
    await GET(request, { params });

    const [url, init] = fetchMock.mock.calls[0];
    expect(String(url)).toBe('https://api.test/products/1');
    expect(init.method).toBe('GET');
  });

  it('forwards repeated query keys as arrays and single keys as strings', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse({ ok: true }));
    const { GET } = toNextJsProxyHandler(api);

    const { req: request, params } = req(
      'http://localhost/proxy/x?tag=a&tag=b&limit=2',
    );
    await GET(request, { params });

    const [url] = fetchMock.mock.calls[0];
    const searchParams = new URL(String(url)).searchParams;
    expect(searchParams.getAll('tag')).toEqual(['a', 'b']);
    expect(searchParams.get('limit')).toBe('2');
  });

  it('forwards the Authorization header by default', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse({ ok: true }));
    const { GET } = toNextJsProxyHandler(api);

    const { req: request, params } = req('http://localhost/proxy/x', {
      headers: { Authorization: 'Bearer abc' },
    });
    await GET(request, { params });

    const [, init] = fetchMock.mock.calls[0];
    expect((init.headers as Headers).get('Authorization')).toBe('Bearer abc');
  });

  it('sends no Authorization header when the browser sent none', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse({ ok: true }));
    const { GET } = toNextJsProxyHandler(api);

    const { req: request, params } = req('http://localhost/proxy/x');
    await GET(request, { params });

    const [, init] = fetchMock.mock.calls[0];
    expect((init.headers as Headers).has('Authorization')).toBe(false);
  });

  it('uses a custom headers option instead of the default', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse({ ok: true }));
    const { GET } = toNextJsProxyHandler(api, {
      headers: () => ({ 'X-Custom': 'yes' }),
    });

    const { req: request, params } = req('http://localhost/proxy/x', {
      headers: { Authorization: 'Bearer abc' },
    });
    await GET(request, { params });

    const [, init] = fetchMock.mock.calls[0];
    const headers = init.headers as Headers;
    expect(headers.get('X-Custom')).toBe('yes');
    expect(headers.has('Authorization')).toBe(false);
  });

  it('forwards a JSON body on POST', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse({ id: 1 }));
    const { POST } = toNextJsProxyHandler(api);

    const { req: request, params } = req('http://localhost/proxy/x', {
      method: 'POST',
      body: JSON.stringify({ a: 1 }),
    });
    await POST(request, { params });

    const [, init] = fetchMock.mock.calls[0];
    expect(JSON.parse(init.body as string)).toEqual({ a: 1 });
  });

  it('sends no body for a body-method request with an empty payload', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse({ ok: true }));
    const { POST } = toNextJsProxyHandler(api);

    const { req: request, params } = req('http://localhost/proxy/x', {
      method: 'POST',
      body: '',
    });
    await POST(request, { params });

    const [, init] = fetchMock.mock.calls[0];
    expect(init.body).toBeNull();
  });

  it('treats a body-method request with no Content-Type header as non-multipart', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse({ ok: true }));
    const { POST } = toNextJsProxyHandler(api, { supportsFiles: true });

    const { req: request, params } = req('http://localhost/proxy/x', {
      method: 'POST',
    });
    await POST(request, { params });

    const [, init] = fetchMock.mock.calls[0];
    expect(init.body).toBeNull();
  });

  it('rejects an invalid JSON body without calling fetch', async () => {
    const { POST } = toNextJsProxyHandler(api);

    const { req: request, params } = req('http://localhost/proxy/x', {
      method: 'POST',
      body: '{not json',
    });
    const res = await POST(request, { params });

    expect(res.status).toBe(400);
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('forwards a multipart body as FormData when supportsFiles is on', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse({ ok: true }));
    const { POST } = toNextJsProxyHandler(api, { supportsFiles: true });

    const form = new FormData();
    form.set('file', new Blob(['hi']), 'hi.txt');
    const { req: request, params } = req('http://localhost/proxy/x', {
      method: 'POST',
      body: form,
    });
    await POST(request, { params });

    const [, init] = fetchMock.mock.calls[0];
    expect(init.body).toBeInstanceOf(FormData);
  });

  it('passes through a null-body status without parsing JSON', async () => {
    fetchMock.mockResolvedValueOnce(new Response(null, { status: 204 }));
    const { DELETE } = toNextJsProxyHandler(api);

    const { req: request, params } = req('http://localhost/proxy/x', {
      method: 'DELETE',
    });
    const res = await DELETE(request, { params });

    expect(res.status).toBe(204);
    expect(await res.text()).toBe('');
  });

  it('passes through a 200 with a null body without parsing JSON', async () => {
    fetchMock.mockResolvedValueOnce(new Response(null, { status: 200 }));
    const { GET } = toNextJsProxyHandler(api);

    const { req: request, params } = req('http://localhost/proxy/x');
    const res = await GET(request, { params });

    expect(res.status).toBe(200);
    expect(await res.text()).toBe('');
  });

  it('returns the upstream JSON body and status', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse({ products: [] }, 201));
    const { GET } = toNextJsProxyHandler(api);

    const { req: request, params } = req('http://localhost/proxy/x');
    const res = await GET(request, { params });

    expect(res.status).toBe(201);
    await expect(res.json()).resolves.toEqual({ products: [] });
  });

  describe('raw/blob passthrough (supportsFiles)', () => {
    it('streams back a Blob with Content-Type and Content-Disposition', async () => {
      fetchMock.mockResolvedValueOnce(
        new Response(new Blob(['pdf-bytes']), {
          status: 200,
          headers: {
            'Content-Type': 'application/pdf',
            'Content-Disposition': 'attachment; filename="x.pdf"',
          },
        }),
      );
      const { GET } = toNextJsProxyHandler(api, { supportsFiles: true });

      const { req: request, params } = req('http://localhost/proxy/x', {
        headers: { 'x-proxy-response': 'raw' },
      });
      const res = await GET(request, { params });

      expect(res.status).toBe(200);
      expect(res.headers.get('Content-Type')).toBe('application/pdf');
      expect(res.headers.get('Content-Disposition')).toBe(
        'attachment; filename="x.pdf"',
      );
      expect(await res.text()).toBe('pdf-bytes');
    });

    it('omits Content-Disposition when upstream did not send one', async () => {
      fetchMock.mockResolvedValueOnce(
        new Response(new Blob(['x']), {
          status: 200,
          headers: { 'Content-Type': 'application/octet-stream' },
        }),
      );
      const { GET } = toNextJsProxyHandler(api, { supportsFiles: true });

      const { req: request, params } = req('http://localhost/proxy/x', {
        headers: { 'x-proxy-response': 'raw' },
      });
      const res = await GET(request, { params });

      expect(res.headers.has('Content-Disposition')).toBe(false);
    });

    it('omits Content-Type when upstream did not send one', async () => {
      fetchMock.mockResolvedValueOnce(
        new Response(new Blob(['x']), { status: 200 }),
      );
      const { GET } = toNextJsProxyHandler(api, { supportsFiles: true });

      const { req: request, params } = req('http://localhost/proxy/x', {
        headers: { 'x-proxy-response': 'raw' },
      });
      const res = await GET(request, { params });

      expect(res.headers.has('Content-Type')).toBe(false);
    });

    it('ignores the raw marker header when supportsFiles is off', async () => {
      fetchMock.mockResolvedValueOnce(jsonResponse({ ok: true }));
      const { GET } = toNextJsProxyHandler(api);

      const { req: request, params } = req('http://localhost/proxy/x', {
        headers: { 'x-proxy-response': 'raw' },
      });
      const res = await GET(request, { params });

      await expect(res.json()).resolves.toEqual({ ok: true });
    });
  });

  describe('error mapping', () => {
    it('relays an upstream ApiError with its JSON body and status', async () => {
      fetchMock.mockResolvedValueOnce(
        jsonResponse({ message: 'Not found' }, 404),
      );
      const { GET } = toNextJsProxyHandler(api);

      const { req: request, params } = req('http://localhost/proxy/x');
      const res = await GET(request, { params });

      expect(res.status).toBe(404);
      await expect(res.json()).resolves.toEqual({ message: 'Not found' });
    });

    it('falls back to the error message when the upstream body is not JSON', async () => {
      fetchMock.mockResolvedValueOnce(
        new Response('<html>bad gateway page</html>', {
          status: 500,
          headers: { 'Content-Type': 'text/html' },
        }),
      );
      const { GET } = toNextJsProxyHandler(api);

      const { req: request, params } = req('http://localhost/proxy/x');
      const res = await GET(request, { params });

      expect(res.status).toBe(500);
      const body = await res.json();
      expect(body.message).toMatch(/HTTP Error 500/u);
    });

    it('forwards Retry-After from a 429', async () => {
      fetchMock.mockResolvedValueOnce(
        new Response(JSON.stringify({ message: 'Too many requests' }), {
          status: 429,
          headers: { 'Content-Type': 'application/json', 'Retry-After': '30' },
        }),
      );
      const { GET } = toNextJsProxyHandler(api);

      const { req: request, params } = req('http://localhost/proxy/x');
      const res = await GET(request, { params });

      expect(res.headers.get('Retry-After')).toBe('30');
    });

    it('omits Retry-After when the upstream error did not send one', async () => {
      fetchMock.mockResolvedValueOnce(jsonResponse({ message: 'nope' }, 400));
      const { GET } = toNextJsProxyHandler(api);

      const { req: request, params } = req('http://localhost/proxy/x');
      const res = await GET(request, { params });

      expect(res.headers.has('Retry-After')).toBe(false);
    });

    it('maps an aborted request to 499', async () => {
      fetchMock.mockRejectedValueOnce(
        Object.assign(new Error('aborted'), { name: 'AbortError' }),
      );
      const { GET } = toNextJsProxyHandler(api);

      const { req: request, params } = req('http://localhost/proxy/x');
      const res = await GET(request, { params });

      expect(res.status).toBe(499);
      expect(await res.text()).toBe('');
    });

    it('maps a timed-out upstream call to 504', async () => {
      fetchMock.mockRejectedValueOnce(
        Object.assign(new Error('timeout'), { name: 'TimeoutError' }),
      );
      const { GET } = toNextJsProxyHandler(api);

      const { req: request, params } = req('http://localhost/proxy/x');
      const res = await GET(request, { params });

      expect(res.status).toBe(504);
    });

    it('maps an unclassified network failure to 502', async () => {
      fetchMock.mockRejectedValueOnce(new Error('DNS lookup failed'));
      const { GET } = toNextJsProxyHandler(api);

      const { req: request, params } = req('http://localhost/proxy/x');
      const res = await GET(request, { params });

      expect(res.status).toBe(502);
    });
  });
});
