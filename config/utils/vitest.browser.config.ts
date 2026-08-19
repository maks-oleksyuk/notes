import path from 'node:path';

import react from '@vitejs/plugin-react';
import { playwright } from '@vitest/browser-playwright';
import { defineConfig } from 'vitest/config';

const rootDir = path.resolve(import.meta.dirname, '../..');
const isCI = !!process.env.CI;

export default defineConfig({
  root: rootDir,
  plugins: [react()],
  resolve: {
    alias: {
      '@': path.resolve(rootDir, './src'),
    },
  },
  server: {
    // Stands in for the Next.js Route Handler at
    // src/app/api/dummyjson/[...path]/route.ts, which doesn't exist on vitest's own Vite dev server.
    proxy: {
      '/api/dummyjson': {
        target: 'https://dummyjson.com',
        changeOrigin: true,
        rewrite: (requestPath) => requestPath.replace(/^\/api\/dummyjson/u, ''),
      },
    },
    allowedHosts: isCI ? true : ['next-js.ddev.site'],
  },
  test: {
    include: ['src/**/__tests__/**/*.browser.test.tsx'],
    silent: 'passed-only',
    setupFiles: [path.resolve(rootDir, 'config/utils/vitest.browser.setup.ts')],
    attachmentsDir: path.resolve(rootDir, 'var/report/vitest-attachments'),
    outputFile: {
      junit: path.resolve(rootDir, 'var/report/junit.xml'),
    },
    coverage: {
      provider: 'v8',
      reporter: ['text', 'html', 'lcov'],
      reportsDirectory: path.resolve(rootDir, 'var/report/coverage'),
      include: ['src/app/demo/dummyjson/page.tsx'],
      thresholds: { 100: true },
    },
    browser: {
      enabled: true,
      provider: playwright(),
      headless: true,
      ui: true,
      trace: 'off',
      instances: [{ browser: 'chromium' }],
    },
    api: {
      host: '0.0.0.0',
      port: 51204,
      allowWrite: true,
      allowExec: true,
    },
    reporters: isCI ? ['dot', 'github-actions', 'junit'] : ['default', 'junit'],
  },
});
