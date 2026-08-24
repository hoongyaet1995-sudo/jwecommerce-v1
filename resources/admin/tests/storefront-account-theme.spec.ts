import { expect, test } from '@playwright/test';

for (const path of ['/account/login', '/account/register', '/account/forgot-password']) {
  test(`account theme control applies dark mode on ${path}`, async ({ page }) => {
    await page.goto(path);

    const themeToggle = page.getByRole('button', { name: 'Toggle Dark or Light Mode' });
    const root = page.locator('html');

    if (await root.getAttribute('data-atelier-theme') !== 'light') {
      await themeToggle.click();
    }

    await expect(root).toHaveAttribute('data-atelier-theme', 'light');
    await themeToggle.click();
    await expect(root).toHaveAttribute('data-atelier-theme', 'dark');
    await expect(page.locator('div.min-h-screen')).toHaveClass(/dark:bg-zinc-950/);
  });
}
