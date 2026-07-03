import { describe, expect, it } from 'vitest';

import { makeQueryClient } from '@/lib/api/query-client';

describe('makeQueryClient', () => {
  it('returns a fresh QueryClient instance on each call', () => {
    const a = makeQueryClient();
    const b = makeQueryClient();

    expect(a).not.toBe(b);
  });

  it('disables query retries — transport retries already happen in HttpClient', () => {
    const client = makeQueryClient();

    expect(client.getDefaultOptions().queries?.retry).toBe(false);
  });

  it('sets a non-zero staleTime so a hydrated query is not instantly refetched', () => {
    const client = makeQueryClient();

    expect(client.getDefaultOptions().queries?.staleTime).toBe(60_000);
  });
});
