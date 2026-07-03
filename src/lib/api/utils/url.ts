// `startsWith('http')` would also match `httpfoo://...` or a path that just happens
// to start with the letters "http" — an actual scheme needs `://` after it.
const ABSOLUTE_URL_RE = /^https?:\/\//i;

/**
 * Constructs a fully qualified URL, combining baseUrl, path, and query parameters.
 */
export function buildUrl(
  baseUrl: string,
  // Optional because `ApiRequestOptions.path` is (callers always end up setting
  // it via `mergeOptions()`, but the type itself doesn't guarantee that) — handled
  // defensively below via `path?.replace(...)`.
  path: string | undefined,
  params?: Record<
    string,
    | string
    | number
    | boolean
    | undefined
    | null
    | Array<string | number | boolean>
  >,
): string {
  const cleanBase = baseUrl.replace(/\/+$/, '');
  const cleanPath = path?.replace(/^\/+/, '') || '';
  const pathIsAbsolute = ABSOLUTE_URL_RE.test(cleanPath);
  const isAbsolute = pathIsAbsolute || ABSOLUTE_URL_RE.test(cleanBase);

  const urlStr = pathIsAbsolute
    ? cleanPath
    : cleanBase
      ? `${cleanBase}/${cleanPath}`
      : cleanPath;

  const urlObj = new URL(urlStr, 'http://localhost');

  if (params) {
    for (const [key, value] of Object.entries(params)) {
      if (value === undefined || value === null) continue;
      if (Array.isArray(value)) {
        for (const item of value) urlObj.searchParams.append(key, String(item));
      } else {
        urlObj.searchParams.set(key, String(value));
      }
    }
  }

  // If it was a relative path, return the path part, otherwise full URL
  return isAbsolute ? urlObj.toString() : `${urlObj.pathname}${urlObj.search}`;
}
