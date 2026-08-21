import { expect, test } from '@playwright/test';

const laravelBootstrap = {
  user: { name: 'Julian Admin', email: 'julian@example.test', avatar: null },
  csrfToken: 'csrf-token',
  logoutUrl: '/logout',
};

test.beforeEach(async ({ page }) => {
  await page.addInitScript((bootstrap) => {
    window.__JW_ADMIN__ = bootstrap;
  }, laravelBootstrap);
});

test('AdminProvider keeps in-memory entity state through push, Back, and Forward', async ({ page }) => {
  await page.goto('/tests/fixtures/provider-route-state.html');

  await page.getByRole('button', { name: 'Seed memory sentinel' }).click();
  await expect(page.getByTestId('sentinel')).toHaveText('present');

  await page.getByRole('button', { name: 'Clear persisted products' }).click();
  await expect.poll(() => page.evaluate(() => localStorage.getItem('aurora_admin_products')))
    .toBeNull();

  await page.getByRole('button', { name: 'Navigate to Commerce' }).click();
  await expect(page).toHaveURL('/admin/commerce');
  await expect(page.getByTestId('sentinel')).toHaveText('present');

  await page.goBack();
  await expect(page).toHaveURL(/provider-route-state\.html$/);
  await expect(page.getByTestId('sentinel')).toHaveText('present');

  await page.goForward();
  await expect(page).toHaveURL('/admin/commerce');
  await expect(page.getByTestId('sentinel')).toHaveText('present');
});
