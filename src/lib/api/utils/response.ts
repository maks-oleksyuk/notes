import type { ResponseType } from '../core/types';

/**
 * Parses the response data according to the expected response type.
 */
export async function parseResponseData<T>(
  response: Response,
  type: ResponseType,
): Promise<T> {
  if (response.status === 204) return null as unknown as T;

  try {
    switch (type) {
      case 'json':
        return (await response.json()) as T;
      case 'text':
        return (await response.text()) as unknown as T;
      case 'blob':
        return (await response.blob()) as unknown as T;
      case 'arraybuffer':
        return (await response.arrayBuffer()) as unknown as T;
      case 'stream':
        return response.body as unknown as T;
      default:
        return (await response.json()) as T;
    }
  } catch {
    return null as unknown as T;
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
