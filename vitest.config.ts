import path from 'node:path';

import { defineConfig } from 'vitest/config';

// Mirrors tsconfig.json's "@/*" -> "./src/*" path alias, since vitest resolves
// modules through Vite, not tsc — the tsconfig mapping alone doesn't apply here.
export default defineConfig({
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  test: {
    environment: 'node',
    // Tests live in a mirrored `tests/` tree, not next to the source files —
    // keeps `src/` free of *.test.ts clutter.
    include: ['tests/**/*.test.ts'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'html'],
      // Coverage only makes sense for code that has (or should have) tests —
      // scoped to the API client for now, not the whole `src/` tree (pages,
      // routes, etc. aren't under test yet).
      include: ['src/lib/api/**/*.ts'],
      // Only the pure re-export barrels — NOT `plugins/auth/index.ts` or
      // `plugins/logger/index.ts`, which are real implementations that happen
      // to be named index.ts, not barrels.
      exclude: [
        'src/lib/api/index.ts',
        'src/lib/api/core/index.ts',
        'src/lib/api/utils/index.ts',
        'src/lib/api/plugins/index.ts',
        'src/lib/api/clients/index.ts',
        'src/lib/api/clients/*/index.ts',
        // Pure type/interface declarations — no runtime statements to cover.
        'src/lib/api/core/types.ts',
        'src/lib/api/plugins/auth/types.ts',
      ],
    },
  },
});
