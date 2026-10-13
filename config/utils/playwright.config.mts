import path from 'node:path';

import { defineConfig } from '@playwright/test';

const rootDir = path.resolve(import.meta.dirname, '../..');
const isCI = !!process.env.CI;
// E2E_BASE_URL points the suite at an already deployed environment (staging, preview, prod).
// Without it, a local production build is built and served on its own port (not the dev server's 3000).
const externalURL = process.env.E2E_BASE_URL;
const port = 3100;
const baseURL = externalURL ?? `http://localhost:${port}`;

export default defineConfig({
  testDir: path.resolve(rootDir, 'e2e'),
  outputDir: path.resolve(rootDir, 'var/report/playwright/artifacts'),
  fullyParallel: true,
  forbidOnly: isCI,
  retries: isCI ? 1 : 0,
  reporter: isCI
    ? [
        ['github'],
        [
          'html',
          {
            open: 'never',
            outputFolder: path.resolve(rootDir, 'var/report/playwright/html'),
          },
        ],
      ]
    : [['list']],
  use: {
    baseURL,
    trace: 'retain-on-failure',
  },
  projects: [{ name: 'chromium' }],
  webServer: externalURL
    ? undefined
    : {
        command: 'task prod',
        cwd: rootDir,
        port,
        // Locally, reuse a server you started with `PORT=3100 task prod`; CI always builds fresh.
        reuseExistingServer: !isCI,
        timeout: 300_000,
        env: {
          PORT: String(port),
          BETTER_AUTH_SECRET: 'e2e-only-long-string-secret-value',
          BETTER_AUTH_URL: baseURL,
          NEXT_PUBLIC_BETTER_AUTH_URL: baseURL,
          NEXT_PUBLIC_DUMMYJSON_API_LOG_LEVEL: 'silent',
        },
      },
});
