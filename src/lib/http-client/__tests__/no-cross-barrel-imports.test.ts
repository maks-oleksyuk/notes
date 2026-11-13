import { readdir, readFile } from 'node:fs/promises';
import path from 'node:path';

import { describe, expect, it } from 'vitest';

// Regression guard for the exact bug class this repo already got bitten by
// once at the api-client layer ("HttpClient is not a constructor", thrown at
// module-load time depending on evaluation order) and that resurfaced inside
// the library itself in utils/response.ts: `core/index.ts` re-exports `client.ts`, which
// imports the `utils` barrel — so any file under `utils/` that imports back
// through the bare `@/lib/http-client/core` barrel closes a
// core -> utils -> core loop, evaluated mid-load. `tsc` never sees this;
// it only throws at runtime, and only depending on import order. Deep
// imports (`core/errors`, `core/types`, ...) from within `utils/` sidestep
// the cycle entirely — this test fails loudly if that discipline slips.
const utilsDir = path.resolve(import.meta.dirname, '..', 'utils');

describe('http-client utils/ does not import the core/ barrel', () => {
  it('imports core/* deep, never the bare @/lib/http-client/core barrel', async () => {
    const files = (await readdir(utilsDir, { recursive: true })).filter(
      (file) =>
        file.endsWith('.ts') && !file.split(path.sep).includes('__tests__'),
    );
    const sources = await Promise.all(
      files.map(async (file) => ({
        file,
        source: await readFile(path.join(utilsDir, file), 'utf8'),
      })),
    );
    const offenders = sources
      .filter(({ source }) => source.includes(`from '@/lib/http-client/core'`))
      .map(({ file }) => file);

    expect(offenders).toEqual([]);
  });
});
