import { describe, expect, it } from 'vitest';

import { ParseError } from '@/lib/api/core/errors';
import { parseErrorData, parseResponseData } from '@/lib/api/utils/response';

describe('parseResponseData', () => {
  it('parses a well-formed JSON body', async () => {
    const res = new Response(JSON.stringify({ ok: true }), { status: 200 });
    await expect(parseResponseData(res, 'json')).resolves.toEqual({ ok: true });
  });

  it('throws ParseError on malformed JSON instead of silently returning null (B1)', async () => {
    const res = new Response('{not json', { status: 200 });
    await expect(parseResponseData(res, 'json')).rejects.toBeInstanceOf(
      ParseError,
    );
  });

  it('returns null for 204 without attempting to parse', async () => {
    const res = new Response(null, { status: 204 });
    await expect(parseResponseData(res, 'json')).resolves.toBeNull();
  });

  it('returns null for 205 without attempting to parse', async () => {
    const res = new Response(null, { status: 205 });
    await expect(parseResponseData(res, 'json')).resolves.toBeNull();
  });

  it('returns null when the response has no body (e.g. HEAD)', async () => {
    const res = new Response(null, { status: 200 });
    await expect(parseResponseData(res, 'json')).resolves.toBeNull();
  });

  it('does not throw for non-JSON response types on arbitrary bytes', async () => {
    const res = new Response('not json at all', { status: 200 });
    await expect(parseResponseData(res, 'text')).resolves.toBe(
      'not json at all',
    );
  });

  it('parses a blob response', async () => {
    const res = new Response('binary-ish', { status: 200 });
    const data = await parseResponseData(res, 'blob');
    expect(data).toBeInstanceOf(Blob);
  });

  it('parses an arraybuffer response', async () => {
    const res = new Response('abc', { status: 200 });
    const data = await parseResponseData(res, 'arraybuffer');
    expect(data).toBeInstanceOf(ArrayBuffer);
  });

  it('returns the raw ReadableStream for the stream type', async () => {
    const res = new Response('abc', { status: 200 });
    const data = await parseResponseData(res, 'stream');
    expect(data).toBe(res.body);
  });

  it('falls back to a generic reason when .json() rejects with a non-Error', async () => {
    // A real fetch Response only ever rejects .json() with a SyntaxError
    // (instanceof Error) — this exercises the defensive fallback for the
    // (in-practice-impossible) case of some environment rejecting with
    // something else, e.g. a plain string.
    const res = new Response('{not json', { status: 200 });
    res.json = () => Promise.reject('not an Error instance');

    await expect(parseResponseData(res, 'json')).rejects.toMatchObject({
      reason: 'invalid JSON',
    });
  });
});

describe('parseErrorData', () => {
  it('parses a JSON error body', async () => {
    const res = new Response(JSON.stringify({ error: 'bad' }), { status: 400 });
    await expect(parseErrorData(res)).resolves.toEqual({ error: 'bad' });
  });

  it('falls back to text when the error body is not JSON', async () => {
    const res = new Response('plain text error', { status: 500 });
    await expect(parseErrorData(res)).resolves.toBe('plain text error');
  });
});
