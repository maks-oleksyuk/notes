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
    // Migrating off the mirrored `tests/<type>/` tree towards colocated
    // `__tests__/` folders next to source (React/Next.js convention). `lib/api`
    // hasn't moved yet, so both patterns are needed until that migrates too.
    include: ['tests/unit/**/*.test.ts', 'src/**/__tests__/**/*.test.ts'],
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
      include: ['src/lib/http-client/**/*.ts', 'src/lib/api/**/*.ts'],
      // Only the pure re-export barrels — NOT `plugins/auth/index.ts` or
      // `plugins/logger/index.ts`, which are real implementations that happen
      // to be named index.ts, not barrels.
      exclude: [
        'src/lib/http-client/core/index.ts',
        'src/lib/http-client/utils/index.ts',
        'src/lib/http-client/plugins/index.ts',
        'src/lib/api/index.ts',
        'src/lib/api/*/index.ts',
        // Entity barrels nested one level deeper, e.g. clients/blog/posts/index.ts.
        'src/lib/api/*/*/index.ts',
        // Pure type/interface declarations — no runtime statements to cover.
        'src/lib/http-client/core/types.ts',
        'src/lib/http-client/plugins/auth/types.ts',
        'src/lib/api/*/*/types.ts',
        // Real-backend integration client — no unit tests against it (would
        // require the live Evexia backend), so it's excluded from coverage
        // entirely rather than dragging the ratio down.
        'src/lib/api/evexia/**',
      ],
    },
  },
});
