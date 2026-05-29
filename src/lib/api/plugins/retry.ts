import type { ApiPlugin } from '@/lib/api';
import { ApiError, NetworkError, TimeoutError } from '@/lib/api';

export interface RetryOptions {
  limit?: number;
  delay?: number;
  statusCodes?: number[];
}

export function retry(options: RetryOptions = {}): ApiPlugin {
  const limit = options.limit ?? 3;
  const delay = options.delay ?? 1000;
  const statusCodes = options.statusCodes ?? [408, 429, 500, 502, 503, 504];

  return {
    name: 'retry',
    onError(error, context) {
      const attempt = (context.options as any)._retryAttempt ?? 0;

      let shouldRetry = attempt < limit;

      if (error instanceof ApiError) {
        shouldRetry = shouldRetry && statusCodes.includes(error.status);
      } else {
        shouldRetry =
          shouldRetry &&
          (error instanceof NetworkError || error instanceof TimeoutError);
      }

      if (shouldRetry) {
        (context.options as any)._retryAttempt = attempt + 1;

        return new Promise((resolve) => {
          setTimeout(async () => {
            console.log(
              `  ↺ Attempt ${attempt + 1}/${limit} for ${context.options.path}`,
            );
            resolve(await context.retry());
          }, delay);
        });
      }
    },
  };
}
