/**
 * Every endpoint the dummyjson client calls, in one file — grouped by entity
 * so "what does this client talk to" is answerable by reading this file
 * alone, without hunting across `auth/`, `products/`, etc.
 */
export const dummyJsonUrls = {
  auth: {
    login: () => '/auth/login',
    refresh: () => '/auth/refresh',
    me: () => '/auth/me',
  },
  products: {
    list: () => '/products',
    detail: (id: number) => `/products/${id}`,
    search: () => '/products/search',
  },
};
