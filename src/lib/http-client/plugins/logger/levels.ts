const RANK: Record<LogLevel, number> = {
  silent: 0,
  error: 1,
  warn: 2,
  info: 3,
  debug: 4,
};

export type LogLevel = 'silent' | 'error' | 'warn' | 'info' | 'debug';

// Falls back to `fallback` when the value is not a known level (e.g., a typo in
// a log-level env var). `Object.hasOwn`, not `in` — `in` also matches
// inherited keys, so an invalid level string such as "toString" would pass the check and then
// resolve to an undefined rank in the filter.
export function resolveLevel<F extends LogLevel | undefined = undefined>(
  value: unknown,
  fallback?: F,
): LogLevel | F {
  return typeof value === 'string' && Object.hasOwn(RANK, value)
    ? (value as LogLevel)
    : (fallback as F);
}

// Returns a predicate: a message at `level` is emitted when it is at least as
// severe as the configured threshold.
export function createLevelFilter(
  threshold: LogLevel,
): (level: LogLevel) => boolean {
  const max = RANK[threshold];
  return (level) => RANK[level] <= max;
}
