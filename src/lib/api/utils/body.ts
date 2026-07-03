/**
 * Normalizes and prepares the request body for fetch.
 */
export function buildBody(body: unknown): BodyInit | null {
  // `== null` (not falsy) — `0`, `''`, `false` are legitimate bodies (e.g. a raw
  // JSON-encoded primitive), not "no body". Only `null`/`undefined` mean "no body".
  if (body == null) return null;

  if (
    body instanceof FormData ||
    body instanceof Blob ||
    body instanceof URLSearchParams
  ) {
    return body;
  }

  if (typeof body === 'object') {
    return JSON.stringify(body);
  }

  return String(body);
}
