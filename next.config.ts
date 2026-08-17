import { withSentryConfig } from '@sentry/nextjs';

import type { NextConfig } from 'next';

const getBrowserLogging: () => boolean | 'warn' | 'error' = ():
  | boolean
  | 'warn'
  | 'error' => {
  const env = process.env.NEXT_LOGGING_BROWSER;
  if (env === 'true') return true;
  if (env === 'false') return false;
  if (env === 'warn' || env === 'error') return env;
  return 'error';
};

const nextConfig: NextConfig = {
  output: 'standalone',
  reactCompiler: true,
  allowedDevOrigins: ['*.ddev.site'],
  logging: {
    browserToTerminal: getBrowserLogging(),
    fetches: {
      fullUrl: true,
      hmrRefreshes: false,
    },
  },
  experimental: {
    useTypeScriptCli: true,
    optimizePackageImports: ['@mantine/core', '@mantine/hooks'],
  },
};

export default withSentryConfig(nextConfig, {
  org: process.env.SENTRY_ORG,
  project: process.env.SENTRY_PROJECT,
  silent: true,
});
