import { HttpClient } from '../../core';

/**
 * Pre-configured instance of HttpClient for our Backend API.
 */
export const backendApi = new HttpClient(
  process.env.NEXT_PUBLIC_API_URL || 'http://localhost:3001/api',
  {
    // Default headers for all backend requests
    headers: {
      'Accept-Language': 'uk',
    },
    // Example of a global request hook (Interceptor)
    onRequest: (options) => {
      console.log(`🚀 [Backend API] ${options.method} ${options.path}`);
    },
  },
);
