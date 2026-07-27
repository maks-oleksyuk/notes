import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { productsQueries } from '../queries';

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });
}

const page = {
  products: [{ id: 1, title: 'x' }],
  total: 1,
  skip: 0,
  limit: 10,
};

describe('productsQueries', () => {
  let fetchMock: ReturnType<typeof vi.fn>;
  const signal = new AbortController().signal;

  beforeEach(() => {
    fetchMock = vi.fn().mockResolvedValue(jsonResponse(page));
    vi.stubGlobal('fetch', fetchMock);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  describe('list', () => {
    it('keys by skip/limit and unwraps the response data in queryFn', async () => {
      const options = productsQueries.list(20, 5);
      expect(options.queryKey).toEqual([
        'dummyjson',
        'products',
        'list',
        { skip: 20, limit: 5 },
      ]);

      // biome-ignore lint/suspicious/noExplicitAny: queryOptions' queryFn type isn't invoked directly outside TanStack elsewhere in this codebase either
      const data = await (options.queryFn as any)({ signal });
      expect(data).toEqual(page);
      const url = new URL(String(fetchMock.mock.calls[0][0]));
      expect(url.searchParams.get('skip')).toBe('20');
      expect(url.searchParams.get('limit')).toBe('5');
    });

    it('defaults to skip=0, limit=10', () => {
      const options = productsQueries.list();
      expect(options.queryKey).toEqual([
        'dummyjson',
        'products',
        'list',
        { skip: 0, limit: 10 },
      ]);
    });
  });

  describe('detail', () => {
    it('keys by id and unwraps a single product', async () => {
      fetchMock.mockResolvedValueOnce(jsonResponse({ id: 42, title: 'y' }));
      const options = productsQueries.detail(42);
      expect(options.queryKey).toEqual(['dummyjson', 'products', 'detail', 42]);

      // biome-ignore lint/suspicious/noExplicitAny: see list() above
      const data = await (options.queryFn as any)({ signal });
      expect(data).toEqual({ id: 42, title: 'y' });
      const url = new URL(String(fetchMock.mock.calls[0][0]));
      expect(url.pathname).toBe('/products/42');
    });
  });

  describe('search', () => {
    it('keys by query and is disabled for an empty query', () => {
      expect(productsQueries.search('').enabled).toBe(false);
      expect(productsQueries.search('phone').enabled).toBe(true);
      expect(productsQueries.search('phone').queryKey).toEqual([
        'dummyjson',
        'products',
        'search',
        'phone',
      ]);
    });

    it('unwraps the search response data in queryFn', async () => {
      const options = productsQueries.search('phone');
      // biome-ignore lint/suspicious/noExplicitAny: see list() above
      const data = await (options.queryFn as any)({ signal });
      expect(data).toEqual(page);
      const url = new URL(String(fetchMock.mock.calls[0][0]));
      expect(url.searchParams.get('q')).toBe('phone');
    });
  });
});
