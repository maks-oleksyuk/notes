import type { ApiRequestOptions } from '../core/types';

/**
 * Picks only the fields `fetch` actually understands out of an already-built
 * request. Previously `attempt()` spread `mergedOptions` straight into `fetch()`,
 * which also carries our own config (`retry`, `plugins`, `schema`, `timeout`,
 * `requestId`, ...) — `fetch` silently ignores unknown keys, so it worked, but by
 * luck rather than intent, and any future key risks colliding with a real
 * `RequestInit` field.
 */
export function toRequestInit(
  options: ApiRequestOptions,
  overrides: {
    method: string;
    headers: Headers;
    body: BodyInit | null;
    signal?: AbortSignal | null;
  },
): RequestInit {
  return {
    method: overrides.method,
    headers: overrides.headers,
    body: overrides.body,
    signal: overrides.signal,
    cache: options.cache,
    credentials: options.credentials,
    integrity: options.integrity,
    keepalive: options.keepalive,
    mode: options.mode,
    redirect: options.redirect,
    referrer: options.referrer,
    referrerPolicy: options.referrerPolicy,
    // Next.js App Router's fetch cache extension — not standard `RequestInit`,
    // but the only way to reach `revalidate`/`tags` from inside `fetch()`.
    next: options.next,
  };
}
