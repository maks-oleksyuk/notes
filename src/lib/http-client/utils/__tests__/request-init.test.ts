import { describe, expect, it } from 'vitest';

import { toRequestInit } from '@/lib/http-client/utils';

describe('toRequestInit', () => {
  it('picks only fetch-known fields, dropping client-specific config', () => {
    const init = toRequestInit(
      {
        retry: { limit: 3 },
        timeout: 100,
        cache: 'no-store',
        next: { revalidate: 60 },
      },
      { method: 'GET', headers: new Headers(), body: null },
    );

    expect(init).not.toHaveProperty('retry');
    expect(init).not.toHaveProperty('timeout');
    expect(init.cache).toBe('no-store');
    expect(init.next).toEqual({ revalidate: 60 });
  });

  it('sets duplex: "half" for a streaming body (undici/Node requires it)', () => {
    const stream = new ReadableStream();

    const init = toRequestInit(
      {},
      { method: 'POST', headers: new Headers(), body: stream },
    );

    expect(init.duplex).toBe('half');
  });

  it('does not set duplex for non-stream bodies', () => {
    const init = toRequestInit(
      {},
      { method: 'POST', headers: new Headers(), body: '{}' },
    );

    expect(init).not.toHaveProperty('duplex');
  });
});
