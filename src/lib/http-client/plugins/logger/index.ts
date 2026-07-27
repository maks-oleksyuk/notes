import { ApiError } from '@/lib/http-client/core';
import { cleanMetadata } from '@/lib/http-client/utils/sanitize';

import {
  ansiToConsoleFormat,
  colorizeMethod,
  colorizeStatus,
  paint,
  supportsColor,
} from './colors';
import { createLevelFilter, resolveLevel } from './levels';

import type { ApiPlugin } from '@/lib/http-client/core';
import type { LogLevel } from './levels';

export type { LogLevel } from './levels';

export interface ApiLogger {
  log: (message: string, metadata?: Record<string, unknown>) => void;
  info?: (message: string, metadata?: Record<string, unknown>) => void;
  warn?: (message: string, metadata?: Record<string, unknown>) => void;
  // `error` and `metadata` are required (not optional): the plugin's only caller
  // — `onFinalError` — always supplies both, so keeping them non-optional means
  // no dead "arg missing" branches to ignore-or-untest in the default sink.
  error: (
    message: string,
    error: Error,
    metadata: Record<string, unknown>,
  ) => void;
}

export interface LoggerOptions {
  level?: LogLevel;
  logger?: ApiLogger;
  prefix?: string;
  // Responses at/under this duration are demoted to `debug` (likely cache hits).
  cacheHitThresholdMs?: number;
}

export function logger({
  level: rawLevel,
  logger: customLogger,
  prefix: rawPrefix = '',
  cacheHitThresholdMs = 10,
}: LoggerOptions = {}): ApiPlugin {
  const prefix = rawPrefix ? `${rawPrefix} ` : '';

  // Priority: explicit option -> API_LOG_LEVEL env -> per-environment default.
  const defaultLevel: LogLevel =
    process.env.NODE_ENV === 'production' ? 'error' : 'info';
  const level = resolveLevel(
    rawLevel ?? process.env.API_LOG_LEVEL,
    defaultLevel,
  );
  const allow = createLevelFilter(level);

  // Groups render nicely in the browser but get mangled (header duplicated) by
  // Next's RSC console forwarding on the server — so group only in the browser.
  const isBrowser = typeof window !== 'undefined';

  // Browser consoles render `%c` CSS, not raw ANSI (Chromium tolerates ANSI,
  // Firefox/Safari show the escapes as garbage) — convert at this boundary so
  // the message-building code above stays sink-agnostic.
  function toConsoleArgs(msg: string): [string, ...string[]] {
    if (!isBrowser) return [msg];
    const { text, styles } = ansiToConsoleFormat(msg);
    return [text, ...styles];
  }

  function print(
    level: 'log' | 'warn',
    msg: string,
    meta?: Record<string, unknown>,
  ) {
    const cleanMeta = meta ? cleanMetadata(meta) : undefined;
    if (!cleanMeta || Object.keys(cleanMeta).length === 0) {
      console[level](...toConsoleArgs(msg));
      return;
    }
    if (isBrowser) {
      console.groupCollapsed(...toConsoleArgs(msg));
      console.dir(cleanMeta, { depth: null });
      console.groupEnd();
    } else {
      console[level](msg, cleanMeta);
    }
  }

  // Only `log` and `error` — the two the plugin actually drives (`logFn` resolves
  // to `log`; `onFinalError` calls `error`). No `info`/`warn` stubs that would
  // never run through this plugin and only exist to be coverage-ignored.
  const defaultConsoleLogger: ApiLogger = {
    log: (msg, meta) => print('log', msg, meta),
    // `_err` is intentionally not printed — the header already carries the
    // message, and the Error's stack is always the same transport plumbing, not
    // the cause. Only the cleaned metadata rides along as an expandable arg.
    // (Custom sinks still receive the Error via `activeLogger.error` and can log
    // its stack themselves.)
    error: (msg, _err, meta) => {
      const cleanMeta = cleanMetadata(meta);
      // `console.error` (not `groupCollapsed`) so the line renders red with
      // devtools' error styling/icon — a `groupCollapsed` header prints as a
      // plain log line, which reads as "just a log" for real failures.
      if (isBrowser) console.error(...toConsoleArgs(msg), cleanMeta);
      else console.error(msg, cleanMeta);
    },
  };

  const activeLogger = customLogger || defaultConsoleLogger;
  const logFn = activeLogger.info ?? activeLogger.log;

  // Custom loggers get plain text (they format/ship structured logs themselves);
  // the default console logger gets colors when the sink can render them — a
  // real TTY on the server, or browser devtools (where the ANSI is converted
  // to %c CSS in `toConsoleArgs`; the TTY check alone would leave browser logs
  // colorless, since a browser has no process.stdout).
  const useColors = !customLogger && (isBrowser || supportsColor());

  // Correlates the three lines of one request when logs from parallel requests
  // interleave. Empty when the request has no id.
  const tag = (id?: string) =>
    id ? `${paint(`[${id}]`, 'gray', useColors)} ` : '';

  return {
    name: 'logger',

    onRequest(options) {
      if (!allow('info')) return;

      // Retried attempts are already announced by onRetry — skip the duplicate line.
      if ((options.retryAttempt ?? 0) > 0) return;

      const method = options.method || 'GET';
      const path = options.path || '/';
      const metadata = allow('debug')
        ? cleanMetadata({
            headers: options.headers,
            params: options.params,
            body: options.body,
          })
        : undefined;

      const arrow = paint('-->', 'gray', useColors);
      logFn(
        `${prefix}${tag(options.requestId)}${arrow} ${colorizeMethod(method, useColors)} ${path}`,
        metadata,
      );
    },

    onResponse(response, options) {
      if (!allow('info')) return;

      const method = options.method || 'GET';
      const path = options.path || '/';
      const metadata = allow('debug')
        ? cleanMetadata({
            headers: response.headers,
            data: response.data,
          })
        : undefined;

      const arrow = paint('<--', 'gray', useColors);
      const status = colorizeStatus(response.status, useColors);
      // Below the threshold reads as a Next Data Cache hit (in-process, no network) rather
      // than a real round-trip — tagged, not hidden, so the request line above it isn't left
      // dangling with no terminus.
      const cacheTag =
        response.duration <= cacheHitThresholdMs ? ' (cache?)' : '';
      const timing = paint(
        `in ${response.duration}ms${cacheTag}`,
        'gray',
        useColors,
      );
      logFn(
        `${prefix}${tag(options.requestId)}${arrow} ${colorizeMethod(method, useColors)} ${path} ${status} ${timing}`,
        metadata,
      );
    },

    onRetry(info) {
      if (!allow('info')) return;

      // A retry is expected and recoverable — log level, not warn/error, so the
      // browser console doesn't attach an alarm icon and a stack trace.
      const marker = paint('↺', 'yellow', useColors);
      const label = paint(
        `retry ${info.attempt}/${info.limit}`,
        'yellow',
        useColors,
      );
      const source = info.fromRetryAfter ? ' (Retry-After)' : '';
      const wait = paint(
        `in ${Math.round(info.wait)}ms${source}`,
        'gray',
        useColors,
      );
      logFn(
        `${prefix}${tag(info.requestId)}${marker} ${label} ${colorizeMethod(info.method || 'GET', useColors)} ${info.path || '/'} ${wait} — ${info.error.message}`,
      );
    },

    onFinalError(error, options) {
      const method = options.method || 'GET';
      const path = options.path || '/';

      // An aborted request is a caller-initiated cancellation, not a failure —
      // TanStack Query aborts the in-flight query when its component unmounts or
      // re-renders. (React 19 Strict Mode double-invokes effects in dev, so the
      // first request is routinely canceled and a second one succeeds.) Don't
      // red-flag it as an error, but still close the request's lifecycle with a
      // muted line at info level — otherwise its `-->` line has no matching
      // terminus and looks like it silently vanished.
      if (error.name === 'AbortError') {
        if (!allow('info')) return;
        const marker = paint('✕', 'gray', useColors);
        const label = paint('cancelled', 'gray', useColors);
        logFn(
          `${prefix}${tag(options.requestId)}${marker} ${colorizeMethod(method, useColors)} ${path} — ${label}`,
        );
        return;
      }

      if (!allow('error')) return;

      // `ApiError.data` is the server's actual response body (e.g. a Laravel
      // 422's `{message, errors: {field: [msg,...]}}`) — without it, a
      // validation failure only ever showed as "HTTP Error 422", no way to
      // tell which field or why without re-triggering the request under a
      // debugger. `status` rides along too, redundant with the header text
      // but useful for log filtering/aggregation.
      const metadata = cleanMetadata({
        path,
        method,
        params: options.params,
        ...(error instanceof ApiError
          ? { status: error.status, data: error.data }
          : {}),
      });

      // Header carries the message (where + why in one red line). The `error`
      // object is still passed for custom sinks (Sentry etc. want the stack),
      // but the default console sink deliberately doesn't print it — its stack
      // is always the same transport plumbing (`validation` -> `attempt` ->
      // `executeWithRetries`), never the real cause, so it's pure noise.
      const label = paint('Error', 'red', useColors);
      activeLogger.error(
        `${prefix}${tag(options.requestId)}${label} ${colorizeMethod(method, useColors)} ${path} — ${error.message}`,
        error,
        metadata,
      );
    },
  };
}
