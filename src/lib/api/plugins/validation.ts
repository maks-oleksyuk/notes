import { ValidationError } from '../core/errors';

import type { ApiPlugin } from '../core/types';

export function validation(): ApiPlugin {
  return {
    name: 'validation',
    onResponse(response, options) {
      const schema = options.schema;
      if (schema) {
        const parsed = schema.safeParse(response.data);
        if (!parsed.success) {
          throw new ValidationError('Response validation failed', {
            url: response.url,
            errors: parsed.error.issues,
            data: response.data,
          });
        }
        response.data = parsed.data;
      }
    },
  };
}
