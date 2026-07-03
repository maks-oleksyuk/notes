/**
 * Bodies `fetch` sends as-is: no JSON serialization in `buildBody`, and no
 * auto `Content-Type: application/json` in `buildHeaders` — for these types
 * fetch either derives the correct Content-Type itself (FormData boundary,
 * URLSearchParams form-encoding, Blob type) or a content type is the caller's
 * business (binary/stream). One shared predicate, so the two functions can't
 * drift apart again (that drift was exactly the URLSearchParams-gets-JSON bug).
 */
export function isRawBody(body: unknown): body is BodyInit {
  return (
    body instanceof FormData ||
    body instanceof Blob ||
    body instanceof URLSearchParams ||
    body instanceof ArrayBuffer ||
    ArrayBuffer.isView(body) ||
    (typeof ReadableStream !== 'undefined' && body instanceof ReadableStream)
  );
}

/**
 * Normalizes and prepares the request body for fetch.
 */
export function buildBody(body: unknown): BodyInit | null {
  // `== null` (not falsy) — `0`, `''`, `false` are legitimate bodies (e.g. a raw
  // JSON-encoded primitive), not "no body". Only `null`/`undefined` mean "no body".
  if (body == null) return null;

  if (isRawBody(body)) return body;

  if (typeof body === 'object') {
    return JSON.stringify(body);
  }

  return String(body);
}
