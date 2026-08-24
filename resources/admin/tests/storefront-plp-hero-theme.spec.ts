import { expect, test } from '@playwright/test';

test('PLP dark hero uses the same solid surface as the storefront body', async ({ page }) => {
  await page.goto('/shop');

  const themeToggle = page.getByRole('button', { name: 'Toggle Dark or Light Mode' });
  const root = page.locator('html');

  if (await root.getAttribute('data-atelier-theme') !== 'dark') {
    await themeToggle.click();
  }

  const hero = page.locator('section').filter({ hasText: 'CATALOG & PRODUCT LISTING' }).first();

  await expect(root).toHaveAttribute('data-atelier-theme', 'dark');
  await expect(hero).toHaveCSS('background-image', 'none');
  await expect(hero).toHaveCSS('background-color', 'oklch(0.141 0.005 285.823)');
});
