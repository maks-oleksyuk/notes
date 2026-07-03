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
});
