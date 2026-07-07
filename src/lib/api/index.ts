/**
 * One import point for the ready-made client instances. There is deliberately
 * NO root `src/lib/api/index.ts` barrel above this one: a root barrel that
 * re-exports both `core` and `clients` lets a core/plugin file accidentally
 * import itself back through the clients (root -> clients -> core -> root),
 * which crashes at module load with "HttpClient is not a constructor"
 * (review.md A4 — happened twice before the root barrel was removed).
 * Import each subpackage directly instead: `@/lib/http-client/core`,
 * `@/lib/http-client/plugins`, `@/lib/api/clients`, `@/lib/http-client/query-client`.
 */
export { backendApi } from './backend';
export * from './blog';
// Named (not `export *`) — evexia's `RequestOverrides` would otherwise clash
// with blog's under this barrel. Pages import from
// `@/lib/api/evexia` directly for the rest.
// export { evexiaApi, evexiaQueries, evexiaUrls } from './evexia';
