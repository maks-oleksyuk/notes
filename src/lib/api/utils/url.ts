/**
 * Constructs a fully qualified URL, combining baseUrl, path, and query parameters.
 */
export function buildUrl(
  baseUrl: string,
  path: string,
  params?: Record<string, string | number | boolean | undefined | null>,
): string {
  const cleanBase = baseUrl.replace(/\/+$/, '');
  const cleanPath = path?.replace(/^\/+/, '') || '';

  let urlStr = cleanPath.startsWith('http')
    ? cleanPath
    : cleanBase
      ? `${cleanBase}/${cleanPath}`
      : cleanPath;

  // Fix potential double slashes if base was empty
  if (!cleanBase && !cleanPath.startsWith('http')) {
    urlStr = cleanPath;
  }

  const urlObj = new URL(urlStr, 'http://localhost');

  // Add query parameters
  if (params) {
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        urlObj.searchParams.set(key, String(value));
      }
    });
  }

  // If it was a relative path, return the path part, otherwise full URL
  return cleanPath.startsWith('http') || cleanBase.startsWith('http')
    ? urlObj.toString()
    : `${urlObj.pathname}${urlObj.search}`;
}
