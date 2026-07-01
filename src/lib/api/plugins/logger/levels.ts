export type LogLevel = 'silent' | 'error' | 'warn' | 'info' | 'debug';

const RANK: Record<LogLevel, number> = {
  silent: 0,
  error: 1,
  warn: 2,
  info: 3,
  debug: 4,
};

// Falls back to `fallback` when the value is not a known level (e.g. a typo in
// the API_LOG_LEVEL env var).
export function resolveLevel(value: unknown, fallback: LogLevel): LogLevel {
  return typeof value === 'string' && value in RANK
    ? (value as LogLevel)
    : fallback;
}

// Returns a predicate: a message at `level` is emitted when it is at least as
// severe as the configured threshold.
export function createLevelFilter(
  threshold: LogLevel,
): (level: LogLevel) => boolean {
  const max = RANK[threshold];
  return (level) => RANK[level] <= max;
}
