import { expect, test } from '@playwright/test';

test('guest storefront header presents the source-faithful theme, member, and cart controls', async ({ page }) => {
  await page.goto('/shop');

  const themeToggle = page.getByRole('button', { name: 'Toggle Dark or Light Mode' });
  await expect(themeToggle.locator('svg[data-lucide="sun"]')).toBeVisible();
  await themeToggle.click();
  await expect(page.locator('html')).toHaveAttribute('data-atelier-theme', 'light');
  await expect(themeToggle.locator('svg[data-lucide="moon"]')).toBeVisible();

  const memberToggle = page.getByRole('button', { name: 'User Account Menu' });
  await expect(page.getByLabel('Atelier Member Studio')).toBeHidden();
  await memberToggle.click();
  await expect(page.getByLabel('Atelier Member Studio')).toBeVisible();
  await expect(page.getByRole('link', { name: /Sign In/ })).toHaveAttribute('href', /\/account\/login$/);
  await expect(page.getByRole('link', { name: /Register/ })).toHaveAttribute('href', /\/account\/register$/);
  await expect(page.getByRole('link', { name: /Forgot Password/ })).toHaveAttribute('href', /\/account\/forgot-password$/);

  const cartTrigger = page.getByRole('button', { name: 'Open Shopping Cart' });
  await expect(cartTrigger).not.toContainText('Cart');
  await expect(cartTrigger.locator('svg[data-lucide="shopping-bag"]')).toBeVisible();
  await cartTrigger.click();
  await expect(page.locator('#cart-drawer[aria-hidden="false"]')).toBeVisible();
});
