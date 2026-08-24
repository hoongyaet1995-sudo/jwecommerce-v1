import { expect, test, type Page } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const testDirectory = fileURLToPath(new URL('.', import.meta.url));
const screenshotRoot = resolve(testDirectory, '../../../test-results/task-17/source');

const openSourceGallery = async (page: Page) => {
  await page.goto('/');
  await page.getByText('Products & Catalog', { exact: true }).click();
  await page.locator('#nav-btn-catalog button').click();
  await page.getByText('Product Matrix', { exact: true }).click();
  await page.getByTitle('Edit Product & Matrix').first().click();
  await page.getByRole('button', { name: /3\. Media Gallery/ }).click();
};

test('ZIP 7 ProductMediaManager exposes its selected desktop and mobile interaction states', async ({ page }) => {
  mkdirSync(screenshotRoot, { recursive: true });
  await openSourceGallery(page);

  await expect(page.getByText('Product Images & Media Assets', { exact: true })).toBeVisible();
  await expect(page.getByText(/Drag and drop product images here, or/)).toBeVisible();
  await expect(page.getByText('Gallery Sequence', { exact: false })).toBeVisible();
  await expect(page.getByText('PRIMARY', { exact: true })).toBeVisible();
  await page.screenshot({ path: resolve(screenshotRoot, 'product-media-desktop.png'), fullPage: true });

  await page.getByRole('button', { name: 'Batch URLs', exact: true }).click();
  await expect(page.getByText('Batch Import Image URLs', { exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'Cancel', exact: true }).click();

  await page.getByText('Quick Presets Gallery', { exact: false }).scrollIntoViewIfNeeded();
  await expect(page.getByText('Atelier ANC Studio (Yellow Hero)', { exact: true })).toBeVisible();
  await page.getByAltText('Atelier ANC Studio (Yellow Hero)', { exact: true }).click();

  await page.getByTitle('Zoom / Preview in Lightbox').first().click();
  await expect(page.getByAltText('Enlarged preview')).toBeVisible();
  await page.locator('div.fixed.inset-0.z-60').getByRole('button').first().click();

  await page.getByTitle('Edit Image Alt Text (SEO)').first().click();
  await expect(page.getByText('Edit Image Alt Text (SEO & Accessibility)', { exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'Save Alt Text' }).click();

  await page.setViewportSize({ width: 390, height: 844 });
  await page.screenshot({ path: resolve(screenshotRoot, 'product-media-mobile.png'), fullPage: true });
  const overflow = await page.locator('html').evaluate(element => ({
    clientWidth: element.clientWidth,
    scrollWidth: element.scrollWidth,
  }));
  expect(overflow.scrollWidth).toBeLessThanOrEqual(overflow.clientWidth + 1);
});
