import type { NextConfig } from 'next';

const getBrowserLogging: () => boolean|'warn'|'error' = (): boolean|'warn'|'error' => {
  const env = process.env.NEXT_LOGGING_BROWSER;
  if (env === 'true') return true;
  if (env === 'false') return false;
  if (env === 'warn' || env === 'error') return env;
  return 'error';
};

const nextConfig: NextConfig = {
  reactCompiler: true,
  allowedDevOrigins: ['*.ddev.site'],
  logging: {
    browserToTerminal: getBrowserLogging(),
    fetches: {
      fullUrl: true,
      hmrRefreshes: false,
    },
  },
};

export default nextConfig;
