import { captureRouterTransitionStart, init } from '@sentry/nextjs';

import { clientEnv } from '@/lib/env/client';

init({
  dsn: clientEnv.SENTRY_DSN,
  tracesSampleRate: 0,
  denyUrls: [
    /extensions\//iu,
    /^moz-extension:\/\//iu,
    /^chrome-extension:\/\//iu,
    /^safari-extension:\/\//iu,
  ],
});

export const onRouterTransitionStart = captureRouterTransitionStart;
