const REDACTED = '***REDACTED***';

const SENSITIVE_KEYS = new Set([
  'authorization',
  'cookie',
  'set-cookie',
  'password',
  'token',
  'access_token',
  'refresh_token',
  'secret',
  'api-key',
  'apikey',
  'x-api-key',
]);

function isSensitiveKey(key: string): boolean {
  return SENSITIVE_KEYS.has(key.toLowerCase());
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
  if (isEntriesLike(value)) {
    return sanitize(Object.fromEntries(value.entries()));
  }

  if (Array.isArray(value)) {
    return value.map(sanitize);
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
