# HTTP Client

Fetch wrapper with retries, timeout, and plugins (`auth`, `logger`,
`validation`, `sentry`) — read `core/` and `plugins/` for the code. This is just the how-to for wiring up a new API.

## Adding a new API client

Template to copy — `src/lib/api/dummyjson/`:

```
src/lib/api/<name>/
  base-url.ts      server: real backend; browser: your own proxy
  server-client.ts HttpClient that actually talks to the backend (server-only)
  client.ts         HttpClient the browser uses (through the proxy)
src/app/api/<name>/[...path]/route.ts   Route Handler proxy
```

### 1. `base-url.ts`

```ts
export function getMyApiBaseUrl(): string {
    return typeof window === 'undefined'
        ? 'https://real-backend.example.com' // server — direct
        : '/api/my-api'; // browser — through your own proxy (no CORS, no leaked token)
}
```

### 2. `server-client.ts` — the only thing that actually hits the backend

```ts
import {HttpClient} from '@/lib/http-client/core';

import {getMyApiBaseUrl} from './base-url';

export const myApiServer = new HttpClient(getMyApiBaseUrl(), {
    timeout: 10_000,
    retry: {limit: 3},
});
```

### 3. `route.ts` — the proxy

```ts
import {myApiServer} from '@/lib/api/my-api/server-client';
import {toNextJsProxyHandler} from '@/lib/http-client/next-proxy';

export const dynamic = 'force-dynamic';

export const {GET, POST, PATCH, PUT, DELETE} =
    toNextJsProxyHandler(myApiServer);
```

### 4. `client.ts` — the browser-side client

```ts
import {HttpClient} from '@/lib/http-client/core';

import {getMyApiBaseUrl} from './base-url';

export const myApi = new HttpClient(getMyApiBaseUrl(), {
    timeout: 10_000,
    retry: {limit: 3},
});
```

Done — `myApi.get('/whatever')` in the browser hits `/api/my-api/whatever`, and the Route Handler proxies it to the real
backend.

## Plugins (optional)

```ts
import {HttpClient} from '@/lib/http-client/core';
import {auth, logger, sentry, validation} from '@/lib/http-client/plugins';

const api = new HttpClient(baseUrl, {
    plugins: [logger({level: 'info'}), validation(), sentry()],
});
```

- `auth(tokenProvider)` — Bearer token + auto-refresh on 401. Example provider:
  `src/lib/api/dummyjson/auth/token-provider.ts`.
- `logger` — logs requests to the console.
- `validation()` + `schema: ZodType` in the request options — runtime response validation.
- `sentry()` — reports errors to Sentry.

Import `core`/`plugins` directly (no shared barrel) to avoid circular imports.
