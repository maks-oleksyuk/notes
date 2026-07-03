import { describe, expect, it } from 'vitest';
import {
  ApiError,
  NetworkError,
  ParseError,
  TimeoutError,
  ValidationError,
} from '@/lib/api/core/errors';

describe('ApiError', () => {
  it('carries the response info and serializes it via toJSON', () => {
    const headers = new Headers({ 'x-a': '1' });
    const err = new ApiError('HTTP 500', {
      status: 500,
      statusText: 'Internal Server Error',
      url: 'https://api.test/x',
      method: 'GET',
      data: { message: 'boom' },
      headers,
    });

    expect(err).toBeInstanceOf(Error);
    expect(err.name).toBe('ApiError');
    expect(err.toJSON()).toEqual({
      name: 'ApiError',
      message: 'HTTP 500',
      status: 500,
      statusText: 'Internal Server Error',
      url: 'https://api.test/x',
      method: 'GET',
      data: { message: 'boom' },
    });
  });
});

describe('NetworkError', () => {
  it('carries the url', () => {
    const err = new NetworkError('DNS lookup failed', 'https://api.test/x');
    expect(err.name).toBe('NetworkError');
    expect(err.url).toBe('https://api.test/x');
  });
});

describe('TimeoutError', () => {
  it('builds a message from the url and timeout', () => {
    const err = new TimeoutError('https://api.test/x', 2000);
    expect(err.name).toBe('TimeoutError');
    expect(err.message).toBe(
      'Request timeout after 2000ms: https://api.test/x',
    );
  });
});

describe('ValidationError', () => {
  it('carries the schema failure details', () => {
    const err = new ValidationError('bad shape', {
      url: 'https://api.test/x',
      errors: [{ path: ['id'], message: 'expected number' }],
      data: { id: 'not-a-number' },
    });
    expect(err.name).toBe('ValidationError');
    expect(err.details.errors).toHaveLength(1);
  });
});

describe('ParseError', () => {
  it('builds a message from the url and reason', () => {
    const err = new ParseError('https://api.test/x', 'unexpected token');
    expect(err.name).toBe('ParseError');
    expect(err.message).toBe(
      'Failed to parse response body as JSON: https://api.test/x (unexpected token)',
    );
  });
});
