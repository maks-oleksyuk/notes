import { ApiError, NetworkError, TimeoutError } from './errors';

import type { HttpMethod, RetryOptions } from './types';

export interface ResolvedRetry {
  limit: number;
  delay: number;
  maxDelay: number;
  maxRetryAfter: number;
  statusCodes: number[];
  respectRetryAfter: boolean;
  methods: HttpMethod[];
}

// ky/got default: only methods a server can safely receive twice. POST/PATCH are
// opt-in only, via `retry.methods`.
const IDEMPOTENT_METHODS: HttpMethod[] = ['GET', 'PUT', 'HEAD', 'DELETE'];

export interface RetryDecision {
  wait: number;
  fromRetryAfter: boolean;
}

// A server `Retry-After` can ask for minutes; honoring that in the browser is
// fine (the tab just waits), but on the server it blocks an RSC render for the
// same duration — so the default cap is much smaller there. `typeof window`
// is the project's established server/client branch (see patterns.md §6.3).
// Checked per call (not hoisted to a module-level constant), so it reflects
// the environment `resolveRetry` actually runs in.
function defaultMaxRetryAfter(): number {
  return typeof window === 'undefined' ? 15_000 : 3 * 60_000;
}

// Fills defaults. Returns null when retries are disabled.
export function resolveRetry(
  input?: RetryOptions | false,
): ResolvedRetry | null {
  if (!input) return null;
  return {
    limit: input.limit ?? 3,
    delay: input.delay ?? 1000,
    maxDelay: input.maxDelay ?? 30_000,
    maxRetryAfter: input.maxRetryAfter ?? defaultMaxRetryAfter(),
    statusCodes: input.statusCodes ?? [408, 429, 500, 502, 503, 504],
    respectRetryAfter: input.respectRetryAfter ?? true,
    methods: input.methods ?? IDEMPOTENT_METHODS,
  };
}

// `Retry-After` is either a number of seconds or an HTTP-date. Returns ms, or
// null when absent/unparseable.
function parseRetryAfter(headers?: Headers): number | null {
  const value = headers?.get('retry-after');
  if (!value) return null;

  const seconds = Number(value);
  if (!Number.isNaN(seconds)) return seconds * 1000;

  const date = Date.parse(value);
  if (!Number.isNaN(date)) return Math.max(0, date - Date.now());

  return null;
}

// Exponential backoff with equal jitter: half the window is fixed, half random.
// Jitter spreads retries so many clients don't all hammer the server in sync.
function backoffDelay(base: number, attempt: number, cap: number): number {
  const window = Math.min(cap, base * 2 ** attempt);
  return window / 2 + Math.random() * (window / 2);
}

// Decides whether (and how long) to wait before retrying. Returns null to give up.
export function nextRetry(
  error: Error,
  attempt: number,
  cfg: ResolvedRetry,
  method?: HttpMethod,
): RetryDecision | null {
  if (attempt >= cfg.limit) return null;
  // Non-idempotent methods (POST by default) never retry, regardless of the error —
  // a duplicate side effect is worse than a failed request.
  if (method && !cfg.methods.includes(method)) return null;

  const retryable =
    error instanceof ApiError
      ? cfg.statusCodes.includes(error.status)
      : error instanceof NetworkError || error instanceof TimeoutError;
  if (!retryable) return null;

  // A server Retry-After (429/503) overrides local backoff, but if it asks for
  // longer than we're willing to wait, give up — retrying sooner would just get
  // rejected again and waste an attempt.
  if (cfg.respectRetryAfter && error instanceof ApiError) {
    const retryAfter = parseRetryAfter(error.headers);
    if (retryAfter != null) {
      if (retryAfter > cfg.maxRetryAfter) return null;
      return { wait: retryAfter, fromRetryAfter: true };
    }
  }

  return {
    wait: backoffDelay(cfg.delay, attempt, cfg.maxDelay),
    fromRetryAfter: false,
  };
}
