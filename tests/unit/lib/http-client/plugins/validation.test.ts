import { describe, expect, it } from 'vitest';
import { z } from 'zod';

import { ValidationError } from '@/lib/http-client/core/errors';
import {
  summarizeIssues,
  validation,
} from '@/lib/http-client/plugins/validation';

import type { ApiResponse } from '@/lib/http-client/core/types';

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
    // The message names the failing field, so a log line is actionable, not just
    // "Response validation failed".
    expect(err.message).toContain('id');
    expect(err.message).toContain('Response validation failed');
  });

  it('flattens union issues so the message names real fields, not "invalid union"', () => {
    // Same shape as the admin locations schema: bare array OR { data: [...] }.
    const unionSchema = z.union([
      z.array(z.object({ id: z.number() })),
      z.object({ data: z.array(z.object({ id: z.number() })) }),
    ]);
    const plugin = validation();
    // A wrong shape (object without `data`, not an array) fails both arms.
    const res = response({ nope: true });

    let caught: unknown;
    try {
      plugin.onResponse?.(res, { schema: unionSchema });
    } catch (err) {
      caught = err;
    }

    const err = caught as ValidationError;
    expect(err).toBeInstanceOf(ValidationError);
    // Not a bare top-level "invalid union" — the nested arm issues are surfaced.
    expect(err.message.toLowerCase()).not.toContain('invalid union');
    expect(err.message).toContain(':');
  });

  it('summarizeIssues dedupes identical path+reason lines', () => {
    const issue = {
      code: 'custom',
      path: ['id'],
      message: 'Invalid input: expected number, received string',
    } as never;

    const summary = summarizeIssues([issue, issue]);

    expect(summary).toContain('1 issue:');
    expect(summary.match(/id: expected/g)).toHaveLength(1);
  });

  it('summarizeIssues truncates past MAX_ISSUES and reports the remainder', () => {
    const issues = Array.from({ length: 7 }, (_, i) => ({
      code: 'custom',
      path: [`field${i}`],
      message: 'Invalid input: bad value',
    })) as never;

    const summary = summarizeIssues(issues);

    expect(summary).toContain('7 issues:');
    expect(summary).toContain('…and 2 more');
    expect(summary.match(/field\d: bad value/g)).toHaveLength(5);
  });
});
