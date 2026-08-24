import { expect, test } from '@playwright/test';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const directory = fileURLToPath(new URL('.', import.meta.url));
const screenshot = resolve(directory, '../../../test-results/storefront-quick-add/quick-add-modal.png');
const mobileScreenshot = resolve(directory, '../../../test-results/storefront-quick-add/quick-add-modal-mobile.png');

test('a multi-SKU PLP product opens the source-faithful selector modal without developer mode controls', async ({ page }) => {
  await page.goto('/shop');

  const triggers = page.locator('[data-quick-add-trigger]');
  const trigger = triggers.first();
  const card = trigger.locator('xpath=ancestor::article');
  const secondPreview = card.locator('[data-product-card-variant-preview] [data-card-variant-id]:not([disabled])').nth(1);
  await expect(secondPreview).toBeVisible();
  const expectedVariantId = await secondPreview.getAttribute('data-card-variant-id');
  await secondPreview.click();
  await trigger.click();

  const modal = page.locator('[data-quick-add-modal]:not(.hidden)').first();
  await expect(modal).toBeVisible();
  await expect(modal.locator('[data-quick-add-variant-input]')).toHaveValue(expectedVariantId!);
  await expect(modal.locator('[data-quick-add-selector]')).toBeVisible();
  await expect(modal.getByText('Variant Matrix UI Mode (4 Display Styles)')).toHaveCount(0);
  await expect(modal.getByText('View Full Product Specifications & Lab Tests')).toBeVisible();
  await page.screenshot({ path: screenshot, fullPage: true });

  await page.keyboard.press('Escape');
  await expect(modal).toBeHidden();
});

test('a cart validation response stays in the modal and uses the source warning treatment', async ({ page }) => {
  await page.route('**/cart/items', async route => {
    await route.fulfill({ status: 422, contentType: 'application/json', body: JSON.stringify({ errors: { quantity: ['Requested quantity exceeds available stock.'] } }) });
  });
  await page.goto('/shop');
  await page.locator('[data-quick-add-trigger]').first().click();

  const modal = page.locator('[data-quick-add-modal]:not(.hidden)').first();
  await modal.locator('[data-quick-add-form]').evaluate(form => form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
  await expect(modal.getByRole('alert')).toHaveText('Requested quantity exceeds available stock.');
  await expect(modal).toBeVisible();
});

test('a successful quick add shows the source success state before opening the cart flow', async ({ page }) => {
  await page.route('**/cart/items', async route => {
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true }) });
  });
  await page.goto('/shop');
  await page.locator('[data-quick-add-trigger]').first().click();

  const modal = page.locator('[data-quick-add-modal]:not(.hidden)').first();
  await modal.locator('[data-quick-add-form]').evaluate(form => form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
  await expect(modal.getByText('Added to Cart Successfully!')).toBeVisible();
});

test('the source-faithful quick add modal remains horizontally contained on mobile', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/shop');
  await page.locator('[data-quick-add-trigger]').first().click();

  const modal = page.locator('[data-quick-add-modal]:not(.hidden)').first();
  await expect(modal).toBeVisible();
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)).toBeLessThanOrEqual(1);
  await page.screenshot({ path: mobileScreenshot, fullPage: true });
});
