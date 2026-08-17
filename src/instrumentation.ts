import { captureRequestError, init } from '@sentry/nextjs';

import { clientEnv } from '@/lib/env/client';
import { sharedEnv } from '@/lib/env/shared';

export function register() {
  const dsn = clientEnv.SENTRY_DSN;
  const { NEXT_RUNTIME } = sharedEnv();
  if (NEXT_RUNTIME === 'nodejs' || NEXT_RUNTIME === 'edge') {
    init({ dsn, tracesSampleRate: 0 });
  }
}

export const onRequestError = captureRequestError;
