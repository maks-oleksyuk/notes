import type { ApiPlugin } from '@/lib/api';
import { cleanMetadata } from '@/lib/api/utils/sanitize';
import { colorizeMethod, colorizeStatus, paint, supportsColor } from './colors';
import { createLevelFilter, resolveLevel, type LogLevel } from './levels';

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

  function print(
    level: 'log' | 'warn',
    msg: string,
    meta?: Record<string, unknown>,
  ) {
    const cleanMeta = meta ? cleanMetadata(meta) : undefined;
    if (!cleanMeta || Object.keys(cleanMeta).length === 0) {
      console[level](msg);
      return;
    }
    if (isBrowser) {
      console.groupCollapsed(msg);
      console.dir(cleanMeta, { depth: null });
      console.groupEnd();
    } else {
      console[level](msg, cleanMeta);
    }
  }

  const defaultConsoleLogger: ApiLogger = {
    log: (msg, meta) => print('log', msg, meta),
    info: (msg, meta) => print('log', msg, meta),
    warn: (msg, meta) => print('warn', msg, meta),
    error: (msg, err, meta) => {
      const cleanMeta = meta ? cleanMetadata(meta) : undefined;
      const hasMeta = cleanMeta && Object.keys(cleanMeta).length > 0;

      if (!err && !hasMeta) {
        console.error(msg);
        return;
      }
      if (isBrowser) {
        console.groupCollapsed(msg);
        if (err) console.error(err);
        if (hasMeta) console.dir(cleanMeta, { depth: null });
        console.groupEnd();
      } else {
        console.error(msg, ...(err ? [err] : []), ...(hasMeta ? [cleanMeta] : []));
      }
    },
  };

  const activeLogger = customLogger || defaultConsoleLogger;
  const logFn = activeLogger.info || activeLogger.log;

  // Custom loggers get plain text (they format/ship structured logs themselves);
  // the default console logger gets ANSI only when the sink is a real terminal.
  const useColors = !customLogger && supportsColor();

  // Correlates the three lines of one request when logs from parallel requests
  // interleave. Empty when the request has no id.
  const tag = (id?: string) => id ? `${paint(`[${id}]`, 'gray', useColors)} ` : '';

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
      const label = paint(`retry ${info.attempt}/${info.limit}`, 'yellow', useColors);
      const source = info.fromRetryAfter ? ' (Retry-After)' : '';
      const wait = paint(`in ${Math.round(info.wait)}ms${source}`, 'gray', useColors);
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
