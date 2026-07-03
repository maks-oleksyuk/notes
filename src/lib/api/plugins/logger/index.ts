import { cleanMetadata } from '@/lib/api/utils/sanitize';

import {
  ansiToConsoleFormat,
  colorizeMethod,
  colorizeStatus,
  paint,
  supportsColor,
} from './colors';
import { createLevelFilter, resolveLevel } from './levels';

import type { ApiPlugin } from '../../core/types';
import type { LogLevel } from './levels';

export type { LogLevel } from './levels';

export interface ApiLogger {
  log: (message: string, metadata?: Record<string, unknown>) => void;
  info?: (message: string, metadata?: Record<string, unknown>) => void;
  warn?: (message: string, metadata?: Record<string, unknown>) => void;
  error: (
    message: string,
    error?: Error,
    metadata?: Record<string, unknown>,
  ) => void;
}

export interface LoggerOptions {
  level?: LogLevel;
  logger?: ApiLogger;
  prefix?: string;
}

export function logger({
  level: rawLevel,
  logger: customLogger,
  prefix: rawPrefix = '',
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

  const defaultConsoleLogger: ApiLogger = {
    // `logFn` below always resolves to `.info` (defined right after this object,
    // and always present here) — `.log` exists only to satisfy `ApiLogger`'s
    // required field, never actually called by this plugin.
    /* v8 ignore next */
    log: (msg, meta) => print('log', msg, meta),
    info: (msg, meta) => print('log', msg, meta),
    // Not called by this plugin's own hooks (only `onFinalError` -> `.error`
    // exists below) — implemented anyway so the default logger fully satisfies
    // `ApiLogger` for anyone who grabs it indirectly.
    /* v8 ignore next */
    warn: (msg, meta) => print('warn', msg, meta),
    // `err`/`meta` optionality below exists for `ApiLogger`'s general contract —
    // this plugin's sole caller (`onFinalError`) always supplies both, so those
    // branches are unreachable through this module alone. Real for anyone using
    // this default logger directly with `.error(msg)` only.
    error: (msg, err, meta) => {
      /* v8 ignore next */
      const cleanMeta = meta ? cleanMetadata(meta) : undefined;
      /* v8 ignore next */
      const hasMeta = cleanMeta && Object.keys(cleanMeta).length > 0;

      if (isBrowser) {
        console.groupCollapsed(...toConsoleArgs(msg));
        /* v8 ignore next */
        if (err) console.error(err);
        /* v8 ignore next */
        if (hasMeta) console.dir(cleanMeta, { depth: null });
        console.groupEnd();
      } else {
        console.error(
          msg,
          /* v8 ignore next */
          ...(err ? [err] : []),
          /* v8 ignore next */
          ...(hasMeta ? [cleanMeta] : []),
        );
      }
    },
  };

  const activeLogger = customLogger || defaultConsoleLogger;
  const logFn = activeLogger.info || activeLogger.log;

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
      const timing = paint(`in ${response.duration}ms`, 'gray', useColors);
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
      if (!allow('error')) return;

      const method = options.method || 'GET';
      const path = options.path || '/';
      const metadata = cleanMetadata({
        path,
        method,
        params: options.params,
      });

      const label = paint('Error', 'red', useColors);
      activeLogger.error(
        `${prefix}${tag(options.requestId)}${label} ${colorizeMethod(method, useColors)} ${path} - ${error.message}`,
        error,
        metadata,
      );
    },
  };
}
