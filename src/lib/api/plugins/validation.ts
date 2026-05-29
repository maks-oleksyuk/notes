import type { ZodSchema } from 'zod';
import type { ApiPlugin } from '@/lib/api';

export class ValidationError extends Error {
  constructor(
    message: string,
    public details: { url: string; errors: any[]; data: any },
  ) {
    super(message);
    this.name = 'ValidationError';
    Object.setPrototypeOf(this, ValidationError.prototype);
  }
}

export function validation(): ApiPlugin {
  return {
    name: 'validation',
    onResponse(response, options) {
      const schema = (options as any).schema as ZodSchema | undefined;
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
