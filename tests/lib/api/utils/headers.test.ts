import { describe, expect, it } from 'vitest';

import { buildHeaders, headersToRecord } from '@/lib/api/utils/headers';

describe('headersToRecord', () => {
  it('returns {} for undefined', () => {
    expect(headersToRecord(undefined)).toEqual({});
  });

  it('normalizes a Headers instance', () => {
    const h = new Headers({ 'X-A': '1' });
    expect(headersToRecord(h)).toEqual({ 'x-a': '1' });
  });

  it('normalizes a tuple array', () => {
    expect(headersToRecord([['X-A', '1']])).toEqual({ 'X-A': '1' });
  });

  it('passes a plain record through', () => {
    expect(headersToRecord({ 'X-A': '1' })).toEqual({ 'X-A': '1' });
  });
});

describe('buildHeaders', () => {
  it('auto-sets Content-Type: application/json for an object body', () => {
    const headers = buildHeaders(undefined, { a: 1 });
    expect(headers.get('Content-Type')).toBe('application/json');
  });

  it('does not override an explicit Content-Type', () => {
    const headers = buildHeaders({ 'Content-Type': 'text/plain' }, { a: 1 });
    expect(headers.get('Content-Type')).toBe('text/plain');
  });

  it('does not set Content-Type for FormData', () => {
    const headers = buildHeaders(undefined, new FormData());
    expect(headers.has('Content-Type')).toBe(false);
  });

  it('does not set Content-Type for Blob', () => {
    const headers = buildHeaders(undefined, new Blob(['x']));
    expect(headers.has('Content-Type')).toBe(false);
  });

  it('adds X-Request-Id when given and not already present', () => {
    const headers = buildHeaders(undefined, undefined, 'req-1');
    expect(headers.get('X-Request-Id')).toBe('req-1');
  });

  it('does not override an explicit X-Request-Id', () => {
    const headers = buildHeaders(
      { 'X-Request-Id': 'explicit' },
      undefined,
      'req-1',
    );
    expect(headers.get('X-Request-Id')).toBe('explicit');
  });
});
