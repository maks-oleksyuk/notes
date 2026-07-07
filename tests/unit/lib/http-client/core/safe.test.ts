import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { HttpClient } from '@/lib/http-client/core/client';
import { ApiError, NetworkError } from '@/lib/http-client/core/errors';
import { safe } from '@/lib/http-client/core/safe';

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });
}

describe('safe()', () => {
  let fetchMock: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('returns { data, error: null } on success', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse({ ok: true }));
    const client = new HttpClient('https://api.test');

    const result = await safe(client.get<{ ok: boolean }>('/x'));

    expect(result.error).toBeNull();
    expect(result.data).toEqual({ ok: true });
  });

  it('returns { data: null, error } on ApiError, without throwing', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse({ message: 'nope' }, 404));
    const client = new HttpClient('https://api.test', { retry: false });

    const result = await safe(client.get('/x'));

    expect(result.data).toBeNull();
    expect(result.error).toBeInstanceOf(ApiError);
    expect((result.error as ApiError).status).toBe(404);
  });

  it('wraps a raw fetch failure as NetworkError', async () => {
    fetchMock.mockRejectedValueOnce(new TypeError('fetch failed'));
    const client = new HttpClient('https://api.test', { retry: false });

    const result = await safe(client.get('/x'));

    expect(result.data).toBeNull();
    expect(result.error).toBeInstanceOf(NetworkError);
  });

  it('wraps a non-Error throw in a plain Error', async () => {
    const result = await safe(Promise.reject('boom'));

    expect(result.data).toBeNull();
    expect(result.error).toBeInstanceOf(Error);
    expect(result.error?.message).toBe('boom');
  });
});

describe('HttpClient.safe', () => {
  let fetchMock: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('safe.get mirrors get() as { data, error }', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse({ ok: true }));
    const client = new HttpClient('https://api.test');

    const result = await client.safe.get<{ ok: boolean }>('/x');

    expect(result.error).toBeNull();
    expect(result.data).toEqual({ ok: true });
  });

  it('safe.post sends the body and reports errors as values', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse({ message: 'bad' }, 400));
    const client = new HttpClient('https://api.test', { retry: false });

    const result = await client.safe.post('/x', { a: 1 });

    expect(fetchMock.mock.calls[0][1]).toMatchObject({ method: 'POST' });
    expect(result.data).toBeNull();
    expect(result.error).toBeInstanceOf(ApiError);
  });

  it('safe.put/patch/delete resolve without throwing on success', async () => {
    fetchMock.mockImplementation(async () => jsonResponse({ ok: true }));
    const client = new HttpClient('https://api.test');

    const put = await client.safe.put('/x/1', { a: 1 });
    const patch = await client.safe.patch('/x/1', { a: 1 });
    const del = await client.safe.delete('/x/1');

    expect(put.error).toBeNull();
    expect(patch.error).toBeNull();
    expect(del.error).toBeNull();
  });
});
