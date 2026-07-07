import path from 'node:path';

import { defineConfig } from 'vitest/config';

// Mirrors tsconfig.json's "@/*" -> "./src/*" path alias, since vitest resolves
// modules through Vite, not tsc — the tsconfig mapping alone doesn't apply here.
const rootDir = path.resolve(__dirname, '../..');
const isCI = !!process.env.CI;

export default defineConfig({
  root: rootDir,
  resolve: {
    alias: {
      '@': path.resolve(rootDir, './src'),
    },
  },
  test: {
    environment: 'node',
    // Tests live in a mirrored `tests/<type>/` tree, not next to the source files —
    // keeps `src/` free of *.test.ts clutter. `unit/` today; `feature/` (e2e/
    // integration) lands as a sibling once that tooling is picked.
    include: ['tests/unit/**/*.test.ts'],
    // Hides passing tests' console output (e.g. the logger plugin's request/
    // response lines) — only surfaces it for failing tests, where it's
    // actually needed to debug. Applies locally too, not just CI.
    silent: 'passed-only',
    // 'dot' keeps CI logs short (one char per file instead of a line per file);
    // 'default' locally shows the per-file breakdown as tests run.
    // JUnit XML is what Codecov's test-results upload (report_type: test_results
    // in CI) reads.
    reporters: isCI ? ['dot', 'github-actions', 'junit'] : ['default', 'junit'],
    outputFile: {
      junit: path.resolve(rootDir, 'var/report/junit.xml'),
    },
    coverage: {
      provider: 'v8',
      // 'lcov' is what Codecov's coverage upload reads; 'text'/'html' stay for
      // local runs (`task test:coverage` prints text, opens coverage/index.html).
      reporter: ['text', 'html', 'lcov'],
      reportsDirectory: path.resolve(rootDir, 'var/report/coverage'),
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
        // Entity barrels nested one level deeper, e.g. clients/blog/posts/index.ts.
        'src/lib/api/clients/*/*/index.ts',
        // Pure type/interface declarations — no runtime statements to cover.
        'src/lib/api/core/types.ts',
        'src/lib/api/plugins/auth/types.ts',
        'src/lib/api/clients/*/*/types.ts',
        // Real-backend integration client — no unit tests against it (would
        // require the live Evexia backend), so it's excluded from coverage
        // entirely rather than dragging the ratio down.
        'src/lib/api/clients/evexia/**',
      ],
    },
  },
});
