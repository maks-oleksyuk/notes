import { describe, expect, it } from 'vitest';

// Same circular-import class of bug as backend.test.ts, on the other client that
// had it (`clients/blog/client.ts` also imported `HttpClient` from the root
// `@/lib/api` barrel).
describe('blogApi', () => {
  it('loads without throwing and exposes the HttpClient method surface', async () => {
    const { blogApi } = await import('@/lib/api/clients/blog/client');
    expect(typeof blogApi.get).toBe('function');
  });
});
