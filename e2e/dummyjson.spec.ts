import { expect, test as it } from '@playwright/test';

// No mocks: goes through the app's /api/dummyjson route handler to the live dummyjson.com.
it('browses products, signs in, refreshes a corrupted token and signs out', async ({
  page,
}) => {
  await page.goto('/en/demo/dummyjson');

  await expect(page.locator('table tbody tr').first()).toBeVisible();
  await page.getByRole('button', { name: 'Next →' }).click();
  await expect(page.getByRole('button', { name: '← Prev' })).toBeEnabled();

  await page.getByPlaceholder('username').fill('emilys');
  await page.getByPlaceholder('password').fill('emilyspass');
  await page.getByRole('button', { name: 'Sign in' }).click();
  await expect(page.getByText(/^Signed in as/u)).toBeVisible();

  await page.getByRole('button', { name: 'GET /auth/me' }).click();
  await expect(
    page.getByText(/GET \/auth\/me succeeded as emilys/u),
  ).toBeVisible();

  await page
    .getByRole('button', { name: 'Corrupt token & refetch (live refresh)' })
    .click();
  await expect(
    page.getByText(/Backend returned a live 401.*refreshed.*replayed/u),
  ).toBeVisible();

  await page.getByRole('button', { name: 'Logout' }).click();
  await expect(page.getByRole('button', { name: 'Sign in' })).toBeVisible();
});
