import type { LoggerConfig, LogLevel } from './types';

const LOG_LEVELS: Record<LogLevel, number> = {
  debug: 0,
  info: 1,
  warn: 2,
  error: 3,
  silent: 4,
};

export class ApiLogger {
  private readonly level: number;
  private readonly logBodies: boolean;

  constructor(config: LoggerConfig) {
    this.level = LOG_LEVELS[config.level];
    this.logBodies = config.logBodies ?? false;
  }

  debug(message: string, data?: Record<string, unknown>) {
    if (this.level > LOG_LEVELS.debug) return;
    console.debug(`[API:DEBUG] ${message}`, data ?? '');
  }

  info(message: string, data?: Record<string, unknown>) {
    if (this.level > LOG_LEVELS.info) return;
    console.info(`[API:INFO] ${message}`, data ?? '');
  }

  warn(message: string, data?: Record<string, unknown>) {
    if (this.level > LOG_LEVELS.warn) return;
    console.warn(`[API:WARN] ${message}`, data ?? '');
  }

  error(message: string, data?: Record<string, unknown>) {
    if (this.level > LOG_LEVELS.error) return;
    console.error(`[API:ERROR] ${message}`, data ?? '');
  }

  logRequest(method: string, url: string, body?: unknown) {
    const data: Record<string, unknown> = { method, url };
    if (this.logBodies && body) data.body = body;
    this.debug('Request', data);
  }

  logResponse(
    method: string,
    url: string,
    status: number,
    duration: number,
    body?: unknown,
  ) {
    const data: Record<string, unknown> = { method, url, status, duration };
    if (this.logBodies && body) data.body = body;
    this.debug('Response', data);
  }

  logError(
    method: string,
    url: string,
    error: unknown,
    duration: number,
    attempt?: number,
  ) {
    const data: Record<string, unknown> = { method, url, duration };
    if (attempt !== undefined) data.attempt = attempt;
    data.error = error instanceof Error ? error.message : String(error);
    this.error('Request failed', data);
  }
}
