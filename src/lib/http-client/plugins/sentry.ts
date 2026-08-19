import { captureException } from '@sentry/react';

import { ApiError } from '@/lib/http-client/core';

import type { ApiPlugin } from '@/lib/http-client/core';

export function sentry(): ApiPlugin {
  return {
    name: 'sentry',
    onFinalError(error, options) {
      if (error.name === 'AbortError') return;
      if (error instanceof ApiError && error.status < 500) return;

      captureException(error, {
        contexts: {
          http: {
            method: options.method || 'GET',
            path: options.path,
            ...(error instanceof ApiError ? { status: error.status } : {}),
          },
        },
      });
    },
  };
}
