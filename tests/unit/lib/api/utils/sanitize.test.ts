import { describe, expect, it } from 'vitest';

import { cleanMetadata } from '@/lib/api/utils/sanitize';

describe('cleanMetadata', () => {
  it('redacts sensitive top-level keys', () => {
    expect(cleanMetadata({ authorization: 'Bearer x' })).toEqual({
      authorization: '***REDACTED***',
    });
  });

  it('redacts sensitive keys nested in objects', () => {
    expect(
      cleanMetadata({ headers: { Authorization: 'Bearer x', 'X-A': '1' } }),
    ).toEqual({
      headers: { Authorization: '***REDACTED***', 'X-A': '1' },
    });
  });

  it('redacts sensitive keys inside a Headers instance', () => {
    const headers = new Headers({ Authorization: 'Bearer x', 'X-A': '1' });
    expect(cleanMetadata({ headers })).toEqual({
      headers: { authorization: '***REDACTED***', 'x-a': '1' },
    });
  });

  it('drops undefined/null entries entirely', () => {
    expect(cleanMetadata({ a: undefined, b: null, c: 1 })).toEqual({ c: 1 });
  });

  it('drops empty-object entries after sanitizing', () => {
    expect(cleanMetadata({ params: {} })).toEqual({});
  });

  it('leaves non-sensitive data untouched', () => {
    expect(cleanMetadata({ method: 'GET', path: '/posts' })).toEqual({
      method: 'GET',
      path: '/posts',
    });
  });

  it('redacts sensitive keys inside array items', () => {
    expect(cleanMetadata({ items: [{ token: 'secret' }, { id: 1 }] })).toEqual({
      items: [{ token: '***REDACTED***' }, { id: 1 }],
    });
  });

  describe('robustness (B3)', () => {
    it('breaks circular references with [Circular] instead of blowing the stack', () => {
      const meta: Record<string, unknown> = { a: 1 };
      meta.self = meta;

      expect(cleanMetadata({ body: meta })).toEqual({
        body: { a: 1, self: '[Circular]' },
      });
    });

    it('does not flag the same object referenced from two sibling keys (shared ref ≠ cycle)', () => {
      const shared = { id: 1 };

      expect(cleanMetadata({ body: { a: shared, b: shared } })).toEqual({
        body: { a: { id: 1 }, b: { id: 1 } },
      });
    });

    it('serializes Date to ISO instead of dropping it as an empty object', () => {
      const created = new Date('2026-07-04T00:00:00.000Z');

      expect(cleanMetadata({ body: { created } })).toEqual({
        body: { created: '2026-07-04T00:00:00.000Z' },
      });
    });

    it('stringifies non-plain class instances instead of flattening them to {}', () => {
      const url = new URL('https://example.com/path');

      expect(cleanMetadata({ body: { url } })).toEqual({
        body: { url: 'https://example.com/path' },
      });
    });
  });

  describe('substring matching (CP-8)', () => {
    it('redacts keys that only contain a sensitive substring, not just exact matches', () => {
      expect(
        cleanMetadata({
          AuthToken: 'x',
          sessionId: 'y',
          refreshToken: 'z',
          'X-Api-Key': 'w',
        }),
      ).toEqual({
        AuthToken: '***REDACTED***',
        sessionId: '***REDACTED***',
        refreshToken: '***REDACTED***',
        'X-Api-Key': '***REDACTED***',
      });
    });

    it('normalizes separators before matching, so `api-key`/`apiKey`/`API_KEY` all hit', () => {
      expect(
        cleanMetadata({ 'api-key': 'a', apiKey: 'b', API_KEY: 'c', id: 1 }),
      ).toEqual({
        'api-key': '***REDACTED***',
        apiKey: '***REDACTED***',
        API_KEY: '***REDACTED***',
        id: 1,
      });
    });
  });
});
