import { ApiClient } from '@/lib/api';

// ── Configuration ───────────────────────────────────────────────

const isDebug = process.env.NEXT_PUBLIC_DEBUG_MODE === 'true';

const client = new ApiClient({
  baseUrl: process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:3001/api',
  timeout: 30_000,
  cache: { revalidate: 60 },
  logger: { level: isDebug ? 'debug' : 'warn' },
  auth: process.env.NEXT_PUBLIC_API_TOKEN
    ? { type: 'bearer', token: process.env.NEXT_PUBLIC_API_TOKEN }
    : undefined,
});

// ── CRUD Methods ────────────────────────────────────────────────

export async function get<T = unknown>(
  path: string,
  params?: Record<string, string | number | boolean>,
) {
  return client.get<T>(path, { params });
}

export async function post<T = unknown>(path: string, body?: unknown) {
  return client.post<T>(path, body);
}

export async function put<T = unknown>(path: string, body?: unknown) {
  return client.put<T>(path, body);
}

export async function patch<T = unknown>(path: string, body?: unknown) {
  return client.patch<T>(path, body);
}

export async function del<T = unknown>(path: string) {
  return client.delete<T>(path);
}

// Export client for direct usage
export { client as backendClient };
