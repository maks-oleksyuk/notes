import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { getProduct, getProducts, searchProducts } from '../requests';

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });
}

const page = { products: [], total: 194, skip: 0, limit: 10 };

describe('dummyjson products requests', () => {
  let fetchMock: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    fetchMock = vi.fn().mockResolvedValue(jsonResponse(page));
    vi.stubGlobal('fetch', fetchMock);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('getProducts defaults to skip=0, limit=10', async () => {
    await getProducts();
    const url = new URL(String(fetchMock.mock.calls[0][0]));
    expect(url.pathname).toBe('/products');
    expect(url.searchParams.get('skip')).toBe('0');
    expect(url.searchParams.get('limit')).toBe('10');
  });

  it('getProducts forwards explicit skip/limit', async () => {
    await getProducts(20, 5);
    const url = new URL(String(fetchMock.mock.calls[0][0]));
    expect(url.searchParams.get('skip')).toBe('20');
    expect(url.searchParams.get('limit')).toBe('5');
  });

  it('getProduct requests the detail path for the given id', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse({ id: 42 }));
    await getProduct(42);
    const url = new URL(String(fetchMock.mock.calls[0][0]));
    expect(url.pathname).toBe('/products/42');
  });

  it('searchProducts sends the query as ?q=', async () => {
    await searchProducts('phone');
    const url = new URL(String(fetchMock.mock.calls[0][0]));
    expect(url.pathname).toBe('/products/search');
    expect(url.searchParams.get('q')).toBe('phone');
  });
});
