import { dummyJsonApi } from '../client';
import { dummyJsonUrls } from '../urls';

import type { RequestOverrides } from '../client';
import type { Product, ProductsPage } from './types';

/** Raw request functions — one HTTP call each, throw-style (see
 * core/safe.ts for the error-as-value alternative). No caching here; that's
 * `queries.ts` in this same folder. Unauthenticated — dummyjson's product
 * catalog is public, the `auth` plugin only kicks in when a token exists. */

export function getProducts(
  skip = 0,
  limit = 10,
  overrides?: RequestOverrides,
) {
  return dummyJsonApi.get<ProductsPage>(dummyJsonUrls.products.list(), {
    ...overrides,
    params: { skip, limit },
  });
}

export function getProduct(id: number, overrides?: RequestOverrides) {
  return dummyJsonApi.get<Product>(
    dummyJsonUrls.products.detail(id),
    overrides,
  );
}

export function searchProducts(query: string, overrides?: RequestOverrides) {
  return dummyJsonApi.get<ProductsPage>(dummyJsonUrls.products.search(), {
    ...overrides,
    params: { q: query },
  });
}
