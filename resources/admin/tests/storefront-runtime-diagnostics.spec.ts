import { expect, test } from '@playwright/test';

test('diagnostic: storefront runtime loads icons and opens the cart drawer and quick add', async ({ page }) => {
  const errors: string[] = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('console', message => {
    if (message.type() === 'error') errors.push(message.text());
  });
  page.on('response', response => {
    if (response.status() >= 400) errors.push(`${response.status()} ${response.url()}`);
  });

  await page.goto('http://127.0.0.1:8500/shop');
  await page.waitForTimeout(500);
  expect(errors).toEqual([]);
  await expect(page.locator('svg[data-lucide]').first()).toBeVisible();

  await page.locator('[data-cart-drawer-open]').first().click();
  await expect(page.locator('#cart-drawer[aria-hidden="false"]')).toBeVisible();

  await page.locator('button[data-cart-drawer-close]').click();
  await page.locator('[data-quick-add-trigger]').first().click();
  await expect(page.locator('[data-quick-add-modal]:not(.hidden)').first()).toBeVisible();
  expect(errors).toEqual([]);
});
