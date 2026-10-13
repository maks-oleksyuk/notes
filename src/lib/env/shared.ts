import { z } from 'zod';

/**
 * Env vars safe to read in both server and browser bundles — non-secret, all optional/defaulted.
 */

const schema = z.object({
  NODE_ENV: z
    .enum(['development', 'test', 'production'])
    .default('development'),
  NEXT_PHASE: z.string().optional(),
  NEXT_RUNTIME: z.preprocess(
    (v) => v || undefined,
    z.enum(['nodejs', 'edge']).optional(),
  ),
  DUMMYJSON_API_LOG_LEVEL: z.string().optional(),
});

export function sharedEnv(): z.infer<typeof schema> {
  return schema.parse(
    typeof process === 'undefined'
      ? {}
      : {
          NODE_ENV: process.env.NODE_ENV,
          NEXT_PHASE: process.env.NEXT_PHASE,
          NEXT_RUNTIME: process.env.NEXT_RUNTIME,
          DUMMYJSON_API_LOG_LEVEL:
            process.env.NEXT_PUBLIC_DUMMYJSON_API_LOG_LEVEL,
        },
  );
}
