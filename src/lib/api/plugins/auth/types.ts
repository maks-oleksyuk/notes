/**
 * Supplies tokens to the `auth` plugin. The plugin never holds a token itself —
 * on the server, module-scope state outlives one request and is shared across
 * users, so every token read/write goes through this indirection instead.
 */
export interface TokenProvider {
  /** Current access token, or `null` when signed out. */
  getToken(): string | null | Promise<string | null>;
  /**
   * Refreshes the session and returns the new access token. Throws if it can't.
   *
   * Omit entirely for static-token providers with no refresh endpoint: the
   * plugin then reports the 401 via `onAuthFailure` and lets the original
   * `ApiError` propagate (status/data intact), instead of masking it behind
   * a thrown "can't refresh" error.
   */
  refreshToken?(): Promise<string>;
  /** Refresh failed for good (dead session) — logout / redirect to log in. */
  onAuthFailure?(error: Error): void | Promise<void>;
}
