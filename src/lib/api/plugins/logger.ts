import type { ApiPlugin } from '@/lib/api';

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
  enabled?: boolean;
  verbose?: boolean;
  logger?: ApiLogger;
  prefix?: string;
}

const colors = {
  red: '\x1b[31m',
  green: '\x1b[32m',
  yellow: '\x1b[33m',
  cyan: '\x1b[36m',
  gray: '\x1b[90m',
  reset: '\x1b[0m',
};

function colorizeStatus(status: number): string {
  if (status >= 200 && status < 300)
    return `${colors.green}${status}${colors.reset}`;
  if (status >= 300 && status < 400)
    return `${colors.cyan}${status}${colors.reset}`;
  if (status >= 400 && status < 500)
    return `${colors.yellow}${status}${colors.reset}`;
  return `${colors.red}${status}${colors.reset}`;
}

function colorizeMethod(method: string): string {
  const m = method.toUpperCase();
  if (m === 'POST') return `${colors.green}${m}${colors.reset}`;
  if (m === 'GET') return `${colors.cyan}${m}${colors.reset}`;
  if (m === 'PUT' || m === 'PATCH')
    return `${colors.yellow}${m}${colors.reset}`;
  if (m === 'DELETE') return `${colors.red}${m}${colors.reset}`;
  return m;
}

function cleanMetadata(meta: Record<string, unknown>): Record<string, unknown> {
  const clean: Record<string, unknown> = {};
  for (const [key, value] of Object.entries(meta)) {
    if (value === undefined || value === null) continue;

    if (key === 'headers' && value instanceof Headers) {
      const headerEntries = Object.fromEntries(value.entries());
      if (Object.keys(headerEntries).length > 0) clean[key] = headerEntries;
      continue;
    }

    if (typeof value === 'object' && !Array.isArray(value)) {
      if (Object.keys(value).length > 0) clean[key] = value;
      continue;
    }

    clean[key] = value;
  }
  return clean;
}

export function logger({
  enabled = process.env.NODE_ENV !== 'production',
  verbose: isVerbose = false,
  logger: customLogger,
  prefix: rawPrefix = '',
}: LoggerOptions = {}): ApiPlugin {
  const prefix = rawPrefix ? `${rawPrefix} ` : '';

  const defaultConsoleLogger: ApiLogger = {
    log: (msg, meta) => print('log', msg, meta),
    info: (msg, meta) => print('log', msg, meta),
    warn: (msg, meta) => print('warn', msg, meta),
    error: (msg, err, meta) => {
      const cleanMeta = meta ? cleanMetadata(meta) : {};
      const combined = err ? { error: err.message, ...cleanMeta } : cleanMeta;

      if (Object.keys(combined).length > 0) {
        console.groupCollapsed(msg);
        if (err) console.error(err);
        console.dir(cleanMeta, { depth: null });
        console.groupEnd();
      } else {
        console.error(msg);
        if (err) console.error(err);
      }
    },
  };

  function print(
    level: 'log' | 'warn',
    msg: string,
    meta?: Record<string, unknown>,
  ) {
    const cleanMeta = meta ? cleanMetadata(meta) : {};
    if (Object.keys(cleanMeta).length > 0) {
      console.groupCollapsed(msg);
      console.dir(cleanMeta, { depth: null });
      console.groupEnd();
    } else {
      console[level](msg);
    }
  }

  const activeLogger = customLogger || defaultConsoleLogger;

  return {
    name: 'logger',

    onRequest(options) {
      if (!enabled) return;

      const method = options.method || 'GET';
      const path = options.path || '/';
      const metadata = isVerbose
        ? cleanMetadata({
            headers: options.headers,
            params: options.params,
            body: options.body,
          })
        : {};

      if (customLogger) {
        const logFn = activeLogger.info || activeLogger.log;
        logFn(`${prefix}--> ${method} ${path}`, metadata);
      } else {
        const message = `${prefix}${colors.gray}-->${colors.reset} ${colorizeMethod(method)} ${path}`;
        activeLogger.log(message, metadata);
      }
    },

    onResponse(response, options) {
      if (!enabled) return;

      const method = options.method || 'GET';
      const path = options.path || '/';
      const metadata = isVerbose
        ? cleanMetadata({
            headers: response.headers,
            data: response.data,
          })
        : {};

      if (customLogger) {
        const logFn = activeLogger.info || activeLogger.log;
        logFn(
          `${prefix}<-- ${method} ${path} ${response.status} (${response.duration}ms)`,
          metadata,
        );
      } else {
        const message = `${prefix}${colors.gray}<--${colors.reset} ${colorizeMethod(method)} ${path} ${colorizeStatus(response.status)} ${colors.gray}in ${response.duration}ms${colors.reset}`;
        activeLogger.log(message, metadata);
      }
    },

    onError(error, context) {
      if (!enabled) return;

      const method = context.options.method || 'GET';
      const path = context.options.path || '/';
      const metadata = cleanMetadata({
        path,
        method,
        params: context.options.params,
      });

      if (customLogger) {
        activeLogger.error(
          `${prefix}Error ${method} ${path} - ${error.message}`,
          error,
          metadata,
        );
      } else {
        const message = `${prefix}${colors.red}Error${colors.reset} ${colorizeMethod(method)} ${path} - ${error.message}`;
        activeLogger.error(message, error, metadata);
      }
    },
  };
}
