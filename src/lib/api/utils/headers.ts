/**
 * Normalizes headers of type HeadersInit to a plain Record object.
 */
export function headersToRecord(headers?: HeadersInit): Record<string, string> {
  if (!headers) return {};
  if (headers instanceof Headers) {
    return Object.fromEntries(headers.entries());
  }
  if (Array.isArray(headers)) {
    return Object.fromEntries(headers);
  }
  return headers as Record<string, string>;
}

/**
 * Constructs a native Headers object, auto-setting Content-Type to JSON if appropriate.
 */
export function buildHeaders(
  headersInit?: HeadersInit,
  body?: unknown,
): Headers {
  const headers = new Headers(headersInit);

  // Auto-set Content-Type for objects, but not for files/blobs
  if (body && !headers.has('Content-Type')) {
    if (!(body instanceof FormData || body instanceof Blob)) {
      headers.set('Content-Type', 'application/json');
    }
  }

  return headers;
}
