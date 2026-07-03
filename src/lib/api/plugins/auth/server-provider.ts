import { cookies } from 'next/headers';

import type { TokenProvider } from './types';

/**
 * Server-side `TokenProvider` backed by `cookies()` (`next/headers`) — Strategy A
 * from `local/api/auth-plugin.md` §4: refresh only where cookies can actually be
 * written.
 *
 * **RSC rendering cannot set cookies** — Next.js throws if you try. So this
 * provider only works correctly inside a Route Handler or Server Action, where
 * `cookies().set()` writes to the response Next is building. Used from a plain
 * RSC render, `refreshToken()` will throw on the `jar.set(...)` call the moment a
 * refresh is actually needed — treat that as "can't refresh here", and either
 * read-only-render with `getToken()` (a stale/expired token just 401s, which the
 * caller should turn into `redirect('/login')`) or move the auth-aware call into a
 * Route Handler / Server Action instead of the render path.
 */
export interface ServerAuthCookies {
  /** Cookie holding the access token. */
  accessCookieName: string;
  /** httpOnly cookie holding the refresh token. */
  refreshCookieName: string;
  /** Absolute URL of your backend's refresh endpoint. */
  refreshUrl: string;
}

// `refreshToken` is optional on TokenProvider (static-token providers omit it),
// but this provider always has one — the narrowed return type saves callers
// from a needless optional-call dance.
export function createServerTokenProvider(
  config: ServerAuthCookies,
): TokenProvider & Required<Pick<TokenProvider, 'refreshToken'>> {
  return {
    async getToken() {
      const jar = await cookies();
      return jar.get(config.accessCookieName)?.value ?? null;
    },

    async refreshToken() {
      const jar = await cookies();
      const refreshToken = jar.get(config.refreshCookieName)?.value;
      if (!refreshToken) throw new Error('no refresh token cookie');

      const res = await fetch(config.refreshUrl, {
        method: 'POST',
        headers: { Cookie: `${config.refreshCookieName}=${refreshToken}` },
      });
      if (!res.ok) throw new Error(`refresh failed: ${res.status}`);
      const data: { accessToken: string; refreshToken?: string } =
        await res.json();

      // Throws here during an RSC render (Next.js forbids writing cookies outside
      // Route Handlers / Server Actions) — intentional, see the module doc above.
      jar.set(config.accessCookieName, data.accessToken, {
        httpOnly: true,
        path: '/',
      });
      if (data.refreshToken) {
        jar.set(config.refreshCookieName, data.refreshToken, {
          httpOnly: true,
          path: '/',
        });
      }
      return data.accessToken;
    },

    onAuthFailure() {
      // No local state to clear — the cookies belong to the response the caller
      // (Route Handler / Server Action) controls. Clear them and redirect there.
    },
  };
}
