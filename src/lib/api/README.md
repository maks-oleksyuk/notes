# API Client

Розширюваний HTTP-клієнт на `fetch` з плагінами, ретраями в ядрі, логуванням і
типізованими помилками.

```ts
import { HttpClient } from '@/lib/api';
import { logger } from '@/lib/api/plugins';

const api = new HttpClient('https://example.com', {
  retry: { limit: 3 },
  plugins: [logger({ level: 'info' })],
});

const { data } = await api.get<Post[]>('/posts', { params: { page: 1 } });
```

---

## Життєвий цикл запиту

```mermaid
flowchart TD
  Start(["request(path, options)"]) --> Merge["mergeOptions()<br/>• requestId<br/>• retryPolicy = resolveRetry(retry)"]
  Merge --> Loop{{"цикл спроб<br/>attempt = 0, 1, 2…"}}
  Loop --> Attempt

  subgraph Attempt["attempt() — одна спроба"]
    direction TB
    OnReq["onRequest (плагіни + simple)"] --> Build["buildUrl · buildHeaders (+X-Request-Id) · buildBody"]
    Build --> Fetch["fetch()"]
    Fetch --> Ok{"response.ok?"}
    Ok -- "ні" --> ThrowApi["throw ApiError"]
    Ok -- "так" --> Parse["parseResponseData"]
    Parse --> OnRes["onResponse (плагіни + simple)"]
    OnRes --> Return(["return ApiResponse"])
  end

  Attempt -- "успіх" --> Return
  Attempt -- "throw" --> Norm["normalizeError()<br/>ApiError / NetworkError / TimeoutError"]

  Norm --> P1

  subgraph Phases["обробка помилки"]
    direction TB
    P1["Фаза 1 — RECOVERY<br/>плагіни onError (auth…)<br/>перший результат виграє"] --> P1q{"хтось<br/>відновив?"}
    P1q -- "так" --> Recovered(["return результат"])
    P1q -- "ні" --> P2{"Фаза 2 — RETRY<br/>nextRetry(err, attempt)"}
    P2 -- "decision" --> EmitRetry["emit onRetry<br/>sleep(wait)"]
    P2 -- "null (годі)" --> P3["Фаза 3 — FINAL<br/>emit onFinalError<br/>simple onResponseError"]
    P3 --> Throw(["throw finalError"])
  end

  EmitRetry -. "continue (наступна спроба)" .-> Loop
```

---

## Коли що спрацьовує

| Хук | Тип | Коли | Може змінити результат? |
|-----|-----|------|--------------------------|
| `onRequest` | плагін | перед кожною спробою (до `fetch`) | так (мутує `options`) |
| `onResponse` | плагін | після успішної відповіді (2xx) | так (напр. валідація) |
| `onError` | плагін (**recovery**) | після помилки, до ретраю | **так** — повернене значення = відновлення, обриває ланцюг |
| `onRetry` | плагін (**observer**) | перед паузою наступної спроби | ні (лише сповіщення) |
| `onFinalError` | плагін (**observer**) | коли ретраї вичерпані / помилка нефатальна для retry | ні |

**Дві ролі плагінів:**
- **Recovery** (`onError`) — лагодить причину й повертає відповідь. Порядок важливий (перший виграє).
- **Observation** (`onRetry` / `onFinalError`) — лише дивиться. **Порядок не важливий.**

---

## Ретраї (у ядрі, не плагін)

```mermaid
sequenceDiagram
  participant C as HttpClient.request
  participant F as fetch/server
  participant P as onRetry-спостерігачі

  C->>F: attempt 0 --> GET /x
  F-->>C: 503
  Note over C: nextRetry() → backoff+jitter або Retry-After
  C->>P: onRetry {attempt:1, wait, error}
  Note over C: sleep(wait)
  C->>F: attempt 1 --> GET /x
  F-->>C: 503
  C->>P: onRetry {attempt:2, wait}
  Note over C: sleep(wait)
  C->>F: attempt 2 --> GET /x
  F-->>C: 200 ✅
  C-->>C: return ApiResponse
```

Політика — `core/retry-policy.ts`:
- **Backoff + jitter:** `min(maxDelay, delay·2^attempt)`, половина фіксована + половина рандом.
- **Retry-After (429/503):** якщо ≤ `maxRetryAfter` (5 хв) — чекаємо стільки; якщо більше — **give-up** (не марнуємо спробу).
- **Ретрайабельні:** статуси `408/429/500/502/503/504`, `NetworkError`, `TimeoutError`.
- `requestId` **однаковий** на всіх спробах → кореляція логів.

---

## Структура

```
core/
  client.ts         HttpClient: цикл спроб + 3 фази обробки помилки
  retry-policy.ts   resolveRetry, nextRetry (backoff, Retry-After)
  errors.ts         ApiError · NetworkError · TimeoutError
  types.ts          ApiRequestOptions · ApiPlugin · RetryOptions · RetryInfo
utils/
  url · headers (+X-Request-Id) · body · response · sanitize · request-id
plugins/
  logger/           observer: onRequest/onResponse/onRetry/onFinalError
  timeout · validation
clients/
  blog · backend    готові інстанси
```

---

## Плагіни

| Плагін | Хуки | Призначення |
|--------|------|-------------|
| `logger` | onRequest, onResponse, onRetry, onFinalError | кольорові логи (ANSI в TTY), тег `[requestId]`, рівні `silent…debug`, маскування PII |
| `timeout` | onRequest, onResponse, onError | скасування по таймауту (`AbortController`) |
| `validation` | onResponse | Zod-валідація відповіді |

Порядок плагінів впливає лише на `onError` (recovery) — observer-хуки від порядку не залежать.
