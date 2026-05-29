import type { ApiPlugin } from '@/lib/api';

export function timeout(defaultTimeout = 5000): ApiPlugin {
  return {
    name: 'timeout',
    onRequest(options) {
      const timeoutVal = (options as any).timeout ?? defaultTimeout;
      if (timeoutVal === 0 || timeoutVal === undefined) return;

      const controller = new AbortController();

      if (options.signal) {
        options.signal = AbortSignal.any([options.signal, controller.signal]);
      } else {
        options.signal = controller.signal;
      }

      const timer = setTimeout(() => {
        controller.abort();
      }, timeoutVal);

      (options as any)._timeoutTimer = timer;
    },
    onResponse(_response, options) {
      const timer = (options as any)._timeoutTimer;
      if (timer) clearTimeout(timer);
    },
    onError(_error, context) {
      const timer = (context.options as any)._timeoutTimer;
      if (timer) clearTimeout(timer);
    },
  };
}
