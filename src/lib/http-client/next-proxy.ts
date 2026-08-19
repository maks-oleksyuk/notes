import { NextResponse } from 'next/server';

import { ApiError, TimeoutError } from './core';

import type { NextRequest } from 'next/server';
import type { HttpClient, HttpMethod } from './core';

const BODY_METHODS = new Set(['POST', 'PUT', 'PATCH']);

// Statuses the HTTP spec forbids a body on — `new Response(body, ...)` throws
// for them, and `NextResponse.json` always produces a body (even `null`
// serializes to the string "null").
const NULL_BODY_STATUSES = new Set([204, 205, 304]);

/** Repeated query keys (`?tag=a&tag=b`) forward as arrays — a plain
 * `[key, value]` loop would keep only the last value. */
function collectSearchParams(
  req: NextRequest,
): Record<string, string | string[]> {
  const out: Record<string, string | string[]> = {};
  for (const key of new Set(req.nextUrl.searchParams.keys())) {
    const values = req.nextUrl.searchParams.getAll(key);
    out[key] = values.length > 1 ? values : values[0];
  }
  return out;
}

function defaultHeaders(req: NextRequest): Record<string, string> {
  const authorization = req.headers.get('authorization');
  return authorization ? { Authorization: authorization } : {};
}

export interface NextProxyOptions {
  /** Headers to forward upstream. Defaults to relaying the browser's own
   * `Authorization` header as-is. */
  headers?: (req: NextRequest) => Record<string, string>;
  /** Accept `multipart/form-data` bodies and an `x-proxy-response: raw`
   * request header that returns the upstream body as a Blob instead of JSON
   * (file uploads/downloads). Default false. */
  supportsFiles?: boolean;
}

/**
 * Turns an `HttpClient` into a Next.js catch-all Route Handler that proxies
 * every method to it — the `toNextJsHandler(auth)` pattern from better-auth,
 * for our own server-side API clients.
 */
export function toNextJsProxyHandler(
  api: HttpClient,
  {
    headers: buildHeaders = defaultHeaders,
    supportsFiles = false,
  }: NextProxyOptions = {},
) {
  async function handle(
    req: NextRequest,
    { params }: { params: Promise<{ path: string[] }> },
  ) {
    const { path } = await params;
    const targetPath = `/${path.join('/')}`;

    let body: unknown;
    if (BODY_METHODS.has(req.method)) {
      const contentType = req.headers.get('content-type') ?? '';
      if (supportsFiles && contentType.includes('multipart/form-data')) {
        body = await req.formData();
      } else {
        const text = await req.text();
        if (text) {
          try {
            body = JSON.parse(text);
          } catch {
            return NextResponse.json(
              { message: 'Invalid JSON body' },
              { status: 400 },
            );
          }
        }
      }
    }

    const wantsRaw =
      supportsFiles && req.headers.get('x-proxy-response') === 'raw';

    try {
      const response = await api.request(targetPath, {
        method: req.method as HttpMethod,
        params: collectSearchParams(req),
        headers: buildHeaders(req),
        body,
        responseType: wantsRaw ? 'blob' : 'json',
        // Tie the upstream call to the browser's own lifetime: tab closed /
        // TanStack aborting an unmounted query cancels it too.
        signal: req.signal,
      });
      if (NULL_BODY_STATUSES.has(response.status) || response.data === null) {
        return new NextResponse(null, { status: response.status });
      }
      if (wantsRaw) {
        const headers = new Headers();
        const contentType = response.headers.get('content-type');
        const contentDisposition = response.headers.get('content-disposition');
        if (contentType) headers.set('Content-Type', contentType);
        if (contentDisposition) {
          headers.set('Content-Disposition', contentDisposition);
        }
        return new NextResponse(response.data as Blob, {
          status: response.status,
          headers,
        });
      }
      return NextResponse.json(response.data, { status: response.status });
    } catch (err) {
      if (err instanceof ApiError) {
        const errorBody =
          err.data !== null && typeof err.data === 'object'
            ? err.data
            : { message: err.message };
        const headers = new Headers();
        // `Retry-After` is the one upstream header the browser side acts on
        // (its retry policy honors it on 429/503) — everything else stays
        // behind the proxy on purpose.
        const retryAfter = err.headers?.get('retry-after');
        if (retryAfter) headers.set('Retry-After', retryAfter);
        return NextResponse.json(errorBody, { status: err.status, headers });
      }
      // The browser aborted (see `signal` above) — nobody is listening for
      // this response. 499 is nginx's "client closed request" convention.
      if (err instanceof Error && err.name === 'AbortError') {
        return new NextResponse(null, { status: 499 });
      }
      if (err instanceof TimeoutError) {
        return NextResponse.json(
          { message: 'Upstream timeout' },
          { status: 504 },
        );
      }
      return NextResponse.json({ message: 'Proxy error' }, { status: 502 });
    }
  }

  return {
    GET: handle,
    POST: handle,
    PATCH: handle,
    PUT: handle,
    DELETE: handle,
  };
}
