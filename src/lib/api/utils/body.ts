/**
 * Normalizes and prepares the request body for fetch.
 */
export function buildBody(body: unknown): BodyInit | null {
  if (!body) return null;

  if (body instanceof FormData || body instanceof Blob) {
    return body;
  }

  if (typeof body === 'object') {
    return JSON.stringify(body);
  }

  return String(body);
}
