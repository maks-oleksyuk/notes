import { describe, expect, it } from 'vitest';

import { buildBody } from '@/lib/api/utils/body';

describe('buildBody', () => {
  it('returns null for null/undefined only (B4 — not falsy)', () => {
    expect(buildBody(null)).toBeNull();
    expect(buildBody(undefined)).toBeNull();
  });

  it('preserves falsy-but-legitimate bodies', () => {
    expect(buildBody(0)).toBe('0');
    expect(buildBody('')).toBe('');
    expect(buildBody(false)).toBe('false');
  });

  it('serializes plain objects as JSON', () => {
    expect(buildBody({ a: 1 })).toBe('{"a":1}');
  });

  it('passes a pre-serialized string through unchanged', () => {
    expect(buildBody('{"already":"json"}')).toBe('{"already":"json"}');
  });

  it('passes FormData through unchanged', () => {
    const fd = new FormData();
    expect(buildBody(fd)).toBe(fd);
  });

  it('passes Blob through unchanged', () => {
    const blob = new Blob(['x']);
    expect(buildBody(blob)).toBe(blob);
  });

  it('passes URLSearchParams through unchanged (B4)', () => {
    const usp = new URLSearchParams({ a: '1' });
    expect(buildBody(usp)).toBe(usp);
  });
});
