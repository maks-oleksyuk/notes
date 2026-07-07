import { describe, expect, it } from 'vitest';

import { buildUrl } from '@/lib/http-client/utils/url';

describe('buildUrl', () => {
  it('joins a relative base and path', () => {
    expect(buildUrl('https://api.test', '/posts')).toBe(
      'https://api.test/posts',
    );
  });

  it('strips duplicate slashes between base and path', () => {
    expect(buildUrl('https://api.test/', '/posts')).toBe(
      'https://api.test/posts',
    );
  });

  it('returns just the path when base is empty', () => {
    expect(buildUrl('', '/posts')).toBe('/posts');
  });

  it('treats a path that merely starts with the letters "http" as relative, not absolute (B5)', () => {
    expect(buildUrl('https://api.test', 'httpfoo/x')).toBe(
      'https://api.test/httpfoo/x',
    );
  });

  it('treats a real absolute URL path as absolute, ignoring baseUrl', () => {
    expect(buildUrl('https://api.test', 'https://other.test/y')).toBe(
      'https://other.test/y',
    );
  });

  it('serializes scalar query params', () => {
    expect(buildUrl('', '/posts', { page: 1, q: 'a b' })).toBe(
      '/posts?page=1&q=a+b',
    );
  });

  it('skips undefined and null params', () => {
    expect(buildUrl('', '/posts', { a: 1, b: undefined, c: null })).toBe(
      '/posts?a=1',
    );
  });

  it('repeats the key for array params (B5)', () => {
    expect(buildUrl('', '/posts', { tag: ['a', 'b'] })).toBe(
      '/posts?tag=a&tag=b',
    );
  });

  it('treats an empty path as the base URL alone', () => {
    expect(buildUrl('https://api.test', '')).toBe('https://api.test/');
  });

  it('tolerates an undefined path', () => {
    expect(buildUrl('https://api.test', undefined)).toBe('https://api.test/');
  });
});
