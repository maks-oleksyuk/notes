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
