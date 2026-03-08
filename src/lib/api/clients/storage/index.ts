// Storage client (S3, R2, etc.)
// Configure via: NEXT_PUBLIC_STORAGE_API_URL, NEXT_PUBLIC_STORAGE_TOKEN

export type { StorageConfig } from './client';
export {
  createStorageClient,
  deleteFile as deleteStorageFile,
  getDownloadUrl,
  getUploadUrl,
  listFiles,
  storageClient,
  uploadToUrl,
  uploadViaProxy,
} from './client';
