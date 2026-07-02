/**
 * Supplies tokens to the `auth` plugin. The plugin never holds a token itself —
 * on the server, module-scope state outlives one request and is shared across
 * users, so every token read/write goes through this indirection instead.
 */
export interface TokenProvider {
  /** Current access token, or `null` when signed out. */
  getToken(): string | null | Promise<string | null>;
  /** Refreshes the session and returns the new access token. Throws if it can't. */
  refreshToken(): Promise<string>;
  /** Refresh failed for good (dead session) — logout / redirect to login. */
  onAuthFailure?(error: Error): void | Promise<void>;
}
