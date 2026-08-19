import { z } from 'zod';

/**
 * `NEXT_PUBLIC_*` vars — safe to import from client components.
 */

const schema = z.object({
  BETTER_AUTH_URL: z.string().optional(),
  SENTRY_DSN: z.string().optional(),
});

export const clientEnv = schema.parse({
  BETTER_AUTH_URL: process.env.NEXT_PUBLIC_BETTER_AUTH_URL,
  SENTRY_DSN: process.env.NEXT_PUBLIC_SENTRY_DSN,
});
