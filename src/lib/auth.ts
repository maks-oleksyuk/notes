import { memoryAdapter } from '@better-auth/memory-adapter';
import { betterAuth } from 'better-auth';
import { nextCookies } from 'better-auth/next-js';

import { serverEnv } from '@/lib/env/server';

export const auth = betterAuth({
  secret: serverEnv.BETTER_AUTH_SECRET,
  baseURL: serverEnv.BETTER_AUTH_URL,
  database: memoryAdapter({}),
  emailAndPassword: {
    enabled: true,
  },
  plugins: [nextCookies()],
});
