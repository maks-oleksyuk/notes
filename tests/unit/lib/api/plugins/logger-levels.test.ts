import { describe, expect, it } from 'vitest';

import {
  createLevelFilter,
  resolveLevel,
} from '@/lib/api/plugins/logger/levels';

describe('resolveLevel', () => {
  it('accepts a known level string', () => {
    expect(resolveLevel('debug', 'error')).toBe('debug');
  });

  it('falls back for an unknown/typo value', () => {
    expect(resolveLevel('verbose', 'error')).toBe('error');
  });

  it('falls back for a non-string value (e.g. undefined env var)', () => {
    expect(resolveLevel(undefined, 'warn')).toBe('warn');
  });

  it('falls back for inherited object keys (C1 — e.g. API_LOG_LEVEL=toString)', () => {
    expect(resolveLevel('toString', 'info')).toBe('info');
    expect(resolveLevel('hasOwnProperty', 'info')).toBe('info');
  });
});

describe('createLevelFilter', () => {
  it('allows messages at or below the threshold severity', () => {
    const allow = createLevelFilter('warn');
    expect(allow('error')).toBe(true);
    expect(allow('warn')).toBe(true);
    expect(allow('info')).toBe(false);
    expect(allow('debug')).toBe(false);
  });

  it('silent threshold blocks everything', () => {
    const allow = createLevelFilter('silent');
    expect(allow('error')).toBe(false);
  });

  it('debug threshold allows everything', () => {
    const allow = createLevelFilter('debug');
    expect(allow('debug')).toBe(true);
  });
});
