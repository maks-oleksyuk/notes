/**
 * Server: the real backend, directly (no CORS to navigate server-to-server).
 * Browser: our own same-origin `/api/dummyjson` Route Handler proxy — the
 * browser never calls dummyjson.com itself, even though dummyjson.com's CORS
 * headers would actually allow it.
 */
export function getDummyJsonBaseUrl(): string {
  return typeof window === 'undefined'
    ? 'https://dummyjson.com'
    : '/api/dummyjson';
}
