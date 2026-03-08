import type { ApiResponse } from '@/lib/api';
import { ApiClient } from '@/lib/api';

// ── Configuration ───────────────────────────────────────────────
// Configure via env or pass options

export interface StorageConfig {
  /** API endpoint for presigned URLs or proxy uploads */
  endpoint: string;
  /** Auth token if needed */
  token?: string;
  /** Timeout for uploads (default: 60s) */
  uploadTimeout?: number;
}

const isDebug = process.env.NEXT_PUBLIC_DEBUG_MODE === 'true';

// Default client from env
const defaultConfig: StorageConfig = {
  endpoint: process.env.NEXT_PUBLIC_STORAGE_API_URL ?? '',
  token: process.env.NEXT_PUBLIC_STORAGE_TOKEN,
  uploadTimeout: 60_000,
};

const client = new ApiClient({
  baseUrl: defaultConfig.endpoint,
  timeout: defaultConfig.uploadTimeout,
  cache: { revalidate: false },
  logger: { level: isDebug ? 'debug' : 'warn' },
  auth: defaultConfig.token
    ? { type: 'bearer', token: defaultConfig.token }
    : undefined,
});

// ── Presigned URLs (recommended for S3/R2) ───────────────────────

export async function getUploadUrl(
  filename: string,
  contentType: string,
): Promise<ApiResponse<{ uploadUrl: string; key: string; fileUrl: string }>> {
  return client.post('/uploads/presigned', { filename, contentType });
}

export async function getDownloadUrl(
  key: string,
): Promise<ApiResponse<{ url: string }>> {
  return client.post('/downloads/presigned', { key });
}

// ── Direct Upload to Presigned URL ───────────────────────────────

export async function uploadToUrl(
  url: string,
  file: File,
): Promise<{ key: string; url: string }> {
  const response = await fetch(url, {
    method: 'PUT',
    body: file,
  });

  if (!response.ok) {
    throw new Error(`Upload failed: ${response.statusText}`);
  }

  const urlObj = new URL(url);
  return { key: urlObj.pathname.slice(1), url: response.url };
}

// ── Proxy Upload (via backend) ───────────────────────────────────

export async function uploadViaProxy(
  file: File,
  options?: { folder?: string },
): Promise<ApiResponse<{ id: string; url: string }>> {
  const formData = new FormData();
  formData.append('file', file);
  if (options?.folder) formData.append('folder', options.folder);

  return client.post('/storage/upload', formData);
}

// ── File Management ───────────────────────────────────────────────

export async function deleteFile(key: string): Promise<ApiResponse<null>> {
  return client.delete(`/storage/files/${encodeURIComponent(key)}`);
}

export async function listFiles(options?: {
  folder?: string;
  limit?: number;
}): Promise<ApiResponse<{ files: Array<{ key: string; url: string }> }>> {
  return client.get('/storage/files', {
    params: options as Record<string, string | number | boolean>,
  });
}

// ── Create Custom Client ─────────────────────────────────────────

export function createStorageClient(config: StorageConfig) {
  return new ApiClient({
    baseUrl: config.endpoint,
    timeout: config.uploadTimeout ?? 60_000,
    cache: { revalidate: false },
    logger: { level: isDebug ? 'debug' : 'warn' },
    auth: config.token ? { type: 'bearer', token: config.token } : undefined,
  });
}

// Export default client
export { client as storageClient };
