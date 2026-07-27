import { NextResponse } from 'next/server';

import { dummyJsonServerApi } from '@/lib/api/dummyjson/server-client';
import { ApiError, TimeoutError } from '@/lib/http-client/core';

import type { NextRequest } from 'next/server';
import type { HttpMethod } from '@/lib/http-client/core';

/**
 * Thin proxy: the browser's `dummyJsonApi` (baseUrl `/api/dummyjson`, see
 * `base-url.ts`) never calls dummyjson.com directly — every request lands
 * here first and gets forwarded through `dummyJsonServerApi`.
 *
 * No cookie/session layer — the token lives in browser JS, and whatever
 * `Authorization` header the browser's own `auth` plugin attached is
 * forwarded through as-is, not replaced.
 */
export const dynamic = 'force-dynamic';

const BODY_METHODS = new Set(['POST', 'PUT', 'PATCH']);

// Statuses the HTTP spec forbids a body on — `new Response(body, ...)` throws
// for them, and `NextResponse.json` always produces a body (even `null`
// serializes to the string "null").
const NULL_BODY_STATUSES = new Set([204, 205, 304]);

/** Repeated query keys forward as arrays — a plain `[key, value]` loop would
 * keep only the last value. */
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

async function handle(
  req: NextRequest,
  { params }: { params: Promise<{ path: string[] }> },
) {
  const { path } = await params;
  const targetPath = `/${path.join('/')}`;

  let body: unknown;
  if (BODY_METHODS.has(req.method)) {
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

  // The one header this proxy forwards — the browser's own `auth` plugin set
  // it, this route only relays it to the real backend.
  const authorization = req.headers.get('authorization');
  const headers: Record<string, string> = authorization
    ? { Authorization: authorization }
    : {};

  try {
    const response = await dummyJsonServerApi.request(targetPath, {
      method: req.method as HttpMethod,
      params: collectSearchParams(req),
      headers,
      body,
      // Tie the upstream call to the browser's own lifetime: tab closed /
      // TanStack aborting an unmounted query cancels it too.
      signal: req.signal,
    });
    if (NULL_BODY_STATUSES.has(response.status) || response.data === null) {
      return new NextResponse(null, { status: response.status });
    }
    return NextResponse.json(response.data, { status: response.status });
  } catch (err) {
    if (err instanceof ApiError) {
      const errorBody =
        err.data !== null && typeof err.data === 'object'
          ? err.data
          : { message: err.message };
      return NextResponse.json(errorBody, { status: err.status });
    }
    // The browser aborted (see `signal` above) — nobody is listening for this
    // response. 499 is nginx's "client closed request" convention.
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

export {
  handle as DELETE,
  handle as GET,
  handle as PATCH,
  handle as POST,
  handle as PUT,
};
