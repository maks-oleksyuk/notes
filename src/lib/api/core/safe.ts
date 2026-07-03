import type {
  ApiError,
  NetworkError,
  ParseError,
  TimeoutError,
  ValidationError,
} from './errors';
import type { ApiResponse } from './types';

/** Errors `HttpClient.request` can throw, narrowed for `safe()` consumers. */
export type SafeError =
  | ApiError
  | NetworkError
  | TimeoutError
  | ValidationError
  | ParseError
  | Error;

/**
 * Discriminated union, not `{ data?, error? }` — that shape doesn't let TS narrow
 * `data` from a truthy check on `error` alone. See patterns.md §4.
 */
export type SafeResult<T> =
  | { data: T; error: null }
  | { data: null; error: SafeError };

/**
 * better-fetch-style `{ data, error }` wrapper over the throw-based core. Throw
 * stays the default (try/catch, TanStack Query expect it) — this is an opt-in
 * shim for call sites that prefer error-as-value, e.g. `safe(client.get('/x'))`.
 */
export async function safe<T>(
  promise: Promise<ApiResponse<T>>,
): Promise<SafeResult<T>> {
  try {
    const response = await promise;
    return { data: response.data, error: null };
  } catch (err) {
    const error = err instanceof Error ? err : new Error(String(err));
    return { data: null, error };
  }
}
