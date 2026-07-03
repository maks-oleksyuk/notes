import { afterEach, describe, expect, it, vi } from 'vitest';

import { generateRequestId } from '@/lib/api/utils/request-id';

describe('generateRequestId', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('returns an 8-char id from crypto.randomUUID when available', () => {
    const id = generateRequestId();
    expect(id).toHaveLength(8);
  });

  it('falls back to Math.random when crypto.randomUUID is unavailable', () => {
    vi.stubGlobal('crypto', {});
    const id = generateRequestId();
    expect(id.length).toBeGreaterThan(0);
  });
});
