import { isRawBody } from './body';

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

export function buildHeaders(
  headersInit?: HeadersInit,
  body?: unknown,
  requestId?: string,
): Headers {
  const headers = new Headers(headersInit);

  // Auto-set Content-Type for objects, but never for raw bodies (FormData,
  // URLSearchParams, Blob, binary, stream) — fetch supplies the right one
  // itself where it can (e.g., form-urlencoded for URLSearchParams), and a
  // wrong `application/json` here would make the server misparse the body.
  if (body && !headers.has('Content-Type') && !isRawBody(body)) {
    headers.set('Content-Type', 'application/json');
  }

  // Forward the correlation id so the backend can tie its logs to ours.
  if (requestId && !headers.has('X-Request-Id')) {
    headers.set('X-Request-Id', requestId);
  }

  return headers;
}
