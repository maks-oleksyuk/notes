import { describe, expect, it } from 'vitest';
import { z } from 'zod';

import { ValidationError } from '@/lib/api/core/errors';
import { validation } from '@/lib/api/plugins/validation';

import type { ApiResponse } from '@/lib/api/core/types';

const PostSchema = z.object({ id: z.number(), title: z.string() });

function response(data: unknown): ApiResponse {
  return {
    data,
    status: 200,
    statusText: 'OK',
    headers: new Headers(),
    url: 'https://api.test/posts/1',
    duration: 5,
  };
}

describe('validation plugin', () => {
  it('is a no-op when no schema is given', () => {
    const plugin = validation();
    const res = response({ anything: true });

    expect(() => plugin.onResponse?.(res, {})).not.toThrow();
    expect(res.data).toEqual({ anything: true });
  });

  it('replaces response.data with the Zod-parsed value on success', () => {
    const plugin = validation();
    const res = response({ id: 1, title: 'hello' });

    plugin.onResponse?.(res, { schema: PostSchema });

    expect(res.data).toEqual({ id: 1, title: 'hello' });
  });

  it('throws ValidationError with the schema issues on a mismatch', () => {
    const plugin = validation();
    const res = response({ id: 'not-a-number' });

    let caught: unknown;
    try {
      plugin.onResponse?.(res, { schema: PostSchema });
    } catch (err) {
      caught = err;
    }

    expect(caught).toBeInstanceOf(ValidationError);
    const err = caught as ValidationError;
    expect(err.details.url).toBe('https://api.test/posts/1');
    expect(err.details.errors.length).toBeGreaterThan(0);
  });
});
