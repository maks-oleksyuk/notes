import { HttpResponse, http } from 'msw';
import { setupWorker } from 'msw/browser';
import { afterAll, afterEach, beforeAll, expect, it } from 'vitest';
import { render } from 'vitest-browser-react';

import { Providers } from '@/app/providers';

import DummyJsonDemoPage from '../page';

// Intercepts specific dummyjson calls; unhandled ones bypass to the real backend.
const worker = setupWorker();

beforeAll(() => worker.start({ onUnhandledRequest: 'bypass' }));
afterEach(() => worker.resetHandlers());
afterAll(() => worker.stop());

it('loads the public product list without auth', async () => {
  const screen = await render(
    <Providers>
      <DummyJsonDemoPage />
    </Providers>,
  );

  await expect
    .element(
      screen.getByRole('heading', { name: 'dummyjson.com — http-client demo' }),
    )
    .toBeVisible();

  await expect
    .poll(() => screen.container.querySelectorAll('table tbody tr').length)
    .toBeGreaterThan(0);

  await screen.getByRole('combobox', { name: 'Sort by' }).click();
  await screen.getByRole('option', { name: 'Sort: Price' }).click();

  await screen.getByRole('button', { name: 'Next →' }).click();
  await expect
    .element(screen.getByRole('button', { name: '← Prev' }))
    .toBeEnabled();
  await screen.getByRole('button', { name: '← Prev' }).click();

  await screen.getByRole('button', { name: 'Reload' }).click();
});

it('logs in, calls /auth/me, forces a token refresh, then logs out', async () => {
  const screen = await render(
    <Providers>
      <DummyJsonDemoPage />
    </Providers>,
  );

  // Same-value fill() is a no-op — clear first to actually fire onChange.
  await screen.getByPlaceholder('username').fill('');
  await screen.getByPlaceholder('username').fill('emilys');
  await screen.getByPlaceholder('password').fill('');
  await screen.getByPlaceholder('password').fill('emilyspass');
  await screen.getByRole('button', { name: 'Sign in' }).click();

  await expect.element(screen.getByText('Signed in as')).toBeVisible();
  await expect
    .element(screen.getByText('emilys', { exact: true }))
    .toBeVisible();

  await screen.getByRole('button', { name: 'GET /auth/me' }).click();
  await expect
    .element(screen.getByText(/GET \/auth\/me succeeded as emilys/u))
    .toBeVisible();

  await screen
    .getByRole('button', { name: 'Corrupt token & refetch (live refresh)' })
    .click();
  await expect
    .element(
      screen.getByText(/Backend returned a live 401.*refreshed.*replayed/u),
    )
    .toBeVisible();

  await screen.getByRole('button', { name: 'Logout' }).click();
  await expect
    .element(screen.getByRole('button', { name: 'Sign in' }))
    .toBeVisible();
});

it('shows an error when /auth/me fails, including on a corrupt-token retry', async () => {
  const screen = await render(
    <Providers>
      <DummyJsonDemoPage />
    </Providers>,
  );

  await screen.getByRole('button', { name: 'Sign in' }).click();
  await expect.element(screen.getByText('Signed in as')).toBeVisible();

  // 400, not 5xx — the retry policy would retry those, slowing the test down.
  worker.use(
    http.get(
      '*/auth/me',
      () =>
        new HttpResponse(null, { status: 400, statusText: 'Mocked Failure' }),
    ),
  );

  await screen.getByRole('button', { name: 'GET /auth/me' }).click();
  await expect
    .poll(() => screen.container.textContent)
    .toContain('Mocked Failure');

  await screen
    .getByRole('button', { name: 'Corrupt token & refetch (live refresh)' })
    .click();
  await expect
    .poll(() => screen.container.textContent)
    .toContain('Mocked Failure');
});

it('does not attempt a token refresh when the login response has no refresh token', async () => {
  worker.use(
    http.post('*/auth/login', () =>
      HttpResponse.json({
        id: 1,
        username: 'emilys',
        email: 'emily.johnson@x.dummyjson.com',
        firstName: 'Emily',
        lastName: 'Johnson',
        gender: 'female',
        image: 'https://dummyjson.com/icon/emilys/128',
        accessToken: 'mock-access',
        // refreshToken intentionally omitted.
      }),
    ),
  );

  const screen = await render(
    <Providers>
      <DummyJsonDemoPage />
    </Providers>,
  );

  await screen.getByRole('button', { name: 'Sign in' }).click();
  await expect.element(screen.getByText('Signed in as')).toBeVisible();

  // Early-return guard (`if (!refreshToken) return`) — nothing should happen.
  await screen
    .getByRole('button', { name: 'Corrupt token & refetch (live refresh)' })
    .click();
  await expect
    .poll(() => screen.container.textContent)
    .not.toContain('Corrupted the access token');
});
