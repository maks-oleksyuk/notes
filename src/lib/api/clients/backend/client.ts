import { HttpClient } from '@/lib/api';

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
  },
);
