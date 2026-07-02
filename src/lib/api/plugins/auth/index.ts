import { ApiError } from '@/lib/api';
import type { ApiPlugin } from '@/lib/api';
import type { TokenProvider } from './types';

export type { TokenProvider } from './types';

/**
 * Attaches `Authorization: Bearer <token>` and recovers from a 401 by refreshing
 * once and replaying the request — see `local/api/auth-plugin.md` for the design.
 *
 * A pure **recovery** plugin (`onError`), the same phase auth-recovery was designed
 * for in the core loop. It never retries transient errors itself — that's the core's
 * job (Phase 2) — it only fixes the *cause* of a 401 and hands back a replayed
 * response.
 */
export function auth(provider: TokenProvider): ApiPlugin {
  // Lives in the closure of this call, not module scope: two `auth(provider)`
  // instances (e.g. two clients, or tests) never share a refresh in flight.
  let refreshPromise: Promise<string> | null = null;
  function singleFlightRefresh(): Promise<string> {
    refreshPromise ??= provider.refreshToken().finally(() => {
      refreshPromise = null;
    });
    return refreshPromise;
  }

  return {
    name: 'auth',

    async onRequest(options) {
      const token = await provider.getToken();
      if (!token) return;
      // `options.headers` is already a plain Record<string, string> by the time
      // onRequest hooks run — `mergeOptions` normalizes it before the attempt loop.
      options.headers = {
        ...(options.headers as Record<string, string> | undefined),
        Authorization: `Bearer ${token}`,
      };
    },

    async onError(error, context) {
      if (!(error instanceof ApiError) || error.status !== 401) return undefined;

      // Second 401 in a row, after we already refreshed once for this request —
      // the session itself is dead, not just an expired access token. Refreshing
      // again would loop forever if the server keeps saying 401.
      if (context.options.authRetried) {
        try {
          await provider.onAuthFailure?.(error);
        } catch {
          // onAuthFailure must never break the error path it's reporting on.
        }
        return undefined;
      }

      // Refresh failing is reported the same way and propagates as the final
      // error (thrown here, caught by the core's plugin loop as `finalError`).
      try {
        await singleFlightRefresh();
      } catch (refreshError) {
        try {
          await provider.onAuthFailure?.(refreshError as Error);
        } catch {
          // see above
        }
        throw refreshError;
      }

      context.options.authRetried = true;
      return context.retry();
    },
  };
}
