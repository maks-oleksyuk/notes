export { backendApi } from './clients/backend';
export * from './clients/blog';
// Named (not `export *`) — evexia's `RequestOverrides` would otherwise clash
// with blog's under the root barrel. Pages import from
// `@/lib/api/clients/evexia` directly for the rest.
export { evexiaApi, evexiaQueries, evexiaUrls } from './clients/evexia';
export * from './core';
export * from './plugins';
export * from './query-client';
