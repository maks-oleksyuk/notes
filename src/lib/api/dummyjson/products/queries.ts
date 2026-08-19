import { queryOptions } from '@tanstack/react-query';

import { getProduct, getProducts, searchProducts } from './requests';

/**
 * `queryOptions` factories for the products entity — same object shared
 * between a server `fetchQuery` (RSC, no hook) and a client `useQuery`.
 */
export const productsQueries = {
  all: () => ['dummyjson', 'products'] as const,

  list: (skip = 0, limit = 10) =>
    queryOptions({
      queryKey: [...productsQueries.all(), 'list', { skip, limit }] as const,
      queryFn: async ({ signal }) =>
        (await getProducts(skip, limit, { signal })).data,
      staleTime: 60_000,
    }),

  detail: (id: number) =>
    queryOptions({
      queryKey: [...productsQueries.all(), 'detail', id] as const,
      queryFn: async ({ signal }) => (await getProduct(id, { signal })).data,
    }),

  search: (query: string) =>
    queryOptions({
      queryKey: [...productsQueries.all(), 'search', query] as const,
      queryFn: async ({ signal }) =>
        (await searchProducts(query, { signal })).data,
      enabled: query.length > 0,
    }),
};
