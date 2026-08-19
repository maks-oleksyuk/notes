import { afterEach, describe, expect, it, vi } from 'vitest';

import { getDummyJsonBaseUrl } from '../base-url';

describe('getDummyJsonBaseUrl', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('resolves to the real backend on the server (no window)', () => {
    expect(getDummyJsonBaseUrl()).toBe('https://dummyjson.com');
  });

  it('resolves to the same-origin proxy in the browser (window defined)', () => {
    vi.stubGlobal('window', {});
    expect(getDummyJsonBaseUrl()).toBe('/api/dummyjson');
  });
});
