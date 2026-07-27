// Deep path, not the `@/lib/http-client/core` barrel: the barrel re-exports
// `client.ts`, which imports `../utils` — going through it from here would be
// a real utils -> core -> utils import cycle.
import { ParseError } from '@/lib/http-client/core/errors';

import type { ResponseType } from '@/lib/http-client/core/types';

/**
 * Parses the response data according to the expected response type.
 *
 * Only the JSON branch can meaningfully "fail to parse" — text/blob/arraybuffer
 * accept any bytes. A JSON parse failure throws `ParseError` instead of silently
 * returning `null` typed as `T` (the old behavior hid a bad response as a `null`
 * that type-checks fine and breaks something else downstream).
 */
export async function parseResponseData<T>(
  response: Response,
  type: ResponseType,
): Promise<T> {
  // 204/205 have no body by spec; a HEAD response's body is also always null —
  // `response.body === null` covers all three without needing the method passed in.
  if (
    response.status === 204 ||
    response.status === 205 ||
    response.body === null
  ) {
    return null as unknown as T;
  }

  switch (type) {
    case 'text':
      return (await response.text()) as unknown as T;
    case 'blob':
      return (await response.blob()) as unknown as T;
    case 'arraybuffer':
      return (await response.arrayBuffer()) as unknown as T;
    case 'stream':
      return response.body as unknown as T;
    default:
      try {
        return (await response.json()) as T;
      } catch (err) {
        // A mid-stream abort rejects response.json() with 'AbortError', not a
        // syntax error — rethrow as-is so callers see the cancel, not ParseError.
        if (err instanceof Error && err.name === 'AbortError') {
          throw err;
        }
        throw new ParseError(
          response.url,
          err instanceof Error ? err.message : 'invalid JSON',
          err,
        );
      }
  }
}

/**
 * Parses response error data (tries JSON, falls back to text).
 */
export async function parseErrorData(response: Response): Promise<unknown> {
  try {
    const text = await response.text();
    try {
      return JSON.parse(text);
    } catch {
      return text;
    }
  } catch {
    return null;
  }
}
