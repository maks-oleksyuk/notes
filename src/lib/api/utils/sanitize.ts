const REDACTED = '***REDACTED***';

// Substrings, not exact keys — catches `AuthToken`, `sessionId`, `X-Api-Key`,
// `refreshToken`, etc. without listing every casing/prefix combo. Matched
// against the key with separators stripped, so `api-key`/`apiKey`/`API_KEY`
// all normalize to `apikey` and hit the same `apikey` entry.
const SENSITIVE_SUBSTRINGS = [
  'authorization',
  'cookie',
  'password',
  'token',
  'secret',
  'apikey',
  'session',
];

function isSensitiveKey(key: string): boolean {
  const normalized = key.toLowerCase().replace(/[^a-z0-9]/g, '');
  return SENSITIVE_SUBSTRINGS.some((needle) => normalized.includes(needle));
}

// Headers, FormData and URLSearchParams expose values via .entries(), not as own keys.
function isEntriesLike(
  value: unknown,
): value is { entries(): IterableIterator<[string, unknown]> } {
  return (
    typeof value === 'object' &&
    value !== null &&
    typeof (value as { entries?: unknown }).entries === 'function'
  );
}

// Recursively replaces values of sensitive keys with REDACTED.
function sanitize(value: unknown): unknown {
  // Arrays also expose `.entries()` (it's on Array.prototype), so this check
  // must come before `isEntriesLike` — otherwise an array would match that
  // branch first and `Object.fromEntries` would silently turn it into an
  // index-keyed plain object ({0: ..., 1: ...}) instead of staying an array.
  if (Array.isArray(value)) {
    return value.map(sanitize);
  }

  if (isEntriesLike(value)) {
    return sanitize(Object.fromEntries(value.entries()));
  }

  if (typeof value === 'object' && value !== null) {
    const result: Record<string, unknown> = {};
    for (const [key, val] of Object.entries(value)) {
      result[key] = isSensitiveKey(key) ? REDACTED : sanitize(val);
    }
    return result;
  }

  return value;
}

// Masks sensitive fields and drops empty/nullish entries before logging.
// Only structured bodies (object/FormData/URLSearchParams) are masked;
// a pre-serialized JSON string passes through untouched.
export function cleanMetadata(
  meta: Record<string, unknown>,
): Record<string, unknown> {
  const clean: Record<string, unknown> = {};
  for (const [key, rawValue] of Object.entries(meta)) {
    if (rawValue === undefined || rawValue === null) continue;

    const value = isSensitiveKey(key) ? REDACTED : sanitize(rawValue);

    if (typeof value === 'object') {
      if (Object.keys(value as object).length > 0) clean[key] = value;
      continue;
    }

    clean[key] = value;
  }
  return clean;
}
