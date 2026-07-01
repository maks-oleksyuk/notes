// Short id to correlate a request's log lines (onRequest/onResponse/onError)
// and, later, to forward as an X-Request-Id header to the backend.
export function generateRequestId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID().slice(0, 8);
  }
  return Math.random().toString(16).slice(2, 10);
}
