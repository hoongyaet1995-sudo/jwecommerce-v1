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

const expectProfileMenu = async (page: import('@playwright/test').Page) => {
  await page.getByRole('button', { name: /Julian Admin/i }).click();

  await expect(page.getByText('Julian Admin')).toBeVisible();
  await expect(page.getByText('julian@example.test')).toBeVisible();
  await expect(page.getByText('SWITCH TEST ROLE')).toBeVisible();
  await expect(page.getByText('Reset Seed Demo Data')).toBeVisible();
  await expect(page.getByText('Log Out')).toBeVisible();
  await expect(page.getByText('2FA Verified')).toBeVisible();
};

test('Profile menu retains the imported controls while showing Laravel identity at desktop', async ({ page }) => {
  await page.setViewportSize({ width: 1920, height: 900 });
  await page.goto('/');
  await page.waitForTimeout(2500);
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-6/shell-desktop-1920x900-candidate.png' });
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-5/profile-desktop-closed.png', fullPage: true });
  await expectProfileMenu(page);
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-5/profile-desktop-open.png', fullPage: true });
});

test('Profile menu remains responsive at mobile', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/');
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-5/profile-mobile-closed.png', fullPage: true });
  await expectProfileMenu(page);
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-5/profile-mobile-open.png', fullPage: true });
});

test('Profile logout submits the Laravel POST endpoint and CSRF token', async ({ page }) => {
  await page.goto('/');
  await expectProfileMenu(page);

  const logoutRequest = page.waitForRequest((request) => request.url().endsWith('/logout') && request.method() === 'POST');
  await page.getByText('Log Out').click();

  await expect((await logoutRequest).postData()).toBe('_token=csrf-token');
});

test('desktop sidebar collapse persists and can be expanded again', async ({ page }) => {
  await page.setViewportSize({ width: 1920, height: 900 });
  await page.goto('/');

  const sidebar = page.locator('#admin-sidebar');
  await expect(sidebar).toHaveClass(/lg:translate-x-0/);
  await page.getByRole('button', { name: 'Toggle Desktop Sidebar' }).click();
  await expect(sidebar).toHaveClass(/-translate-x-full/);
  await expect.poll(() => page.evaluate(() => localStorage.getItem('aurora_sidebar_collapsed'))).toBe('true');

  await page.reload();
  await expect(sidebar).toHaveClass(/-translate-x-full/);
  await page.getByRole('button', { name: 'Toggle Desktop Sidebar' }).click();
  await expect(sidebar).toHaveClass(/lg:translate-x-0/);
});

test('mobile sidebar opens as a drawer and its backdrop closes it', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/');

  const trigger = page.getByRole('button', { name: 'Toggle Navigation' });
  const sidebar = page.locator('#admin-sidebar');
  await trigger.click();
  await expect(sidebar).toHaveClass(/translate-x-0/);
  await expect(page.locator('div.fixed.inset-0.z-30')).toBeVisible();
  await page.waitForTimeout(300);
  await page.screenshot({ path: '../../output/admin-react-rebuild/task-6/shell-mobile-drawer-390x844-candidate.png' });

  await page.locator('div.fixed.inset-0.z-30').click({ position: { x: 300, y: 80 } });
  await expect(sidebar).toHaveClass(/-translate-x-full/);
});

test('theme selection persists across a reload', async ({ page }) => {
  await page.setViewportSize({ width: 1920, height: 900 });
  await page.goto('/');

  await page.getByRole('button', { name: 'Light Mode' }).click();
  await expect.poll(() => page.evaluate(() => document.documentElement.dataset.theme)).toBe('light');
  await expect.poll(() => page.evaluate(() => localStorage.getItem('aurora_admin_theme'))).toBe('light');

  await page.reload();
  await expect.poll(() => page.evaluate(() => document.documentElement.dataset.theme)).toBe('light');
});

test('command shortcut filters results and Escape restores focus to its trigger', async ({ page }) => {
  await page.setViewportSize({ width: 1920, height: 900 });
  await page.goto('/');

  const trigger = page.locator('#btn-command-palette-trigger');
  await trigger.focus();
  await page.keyboard.press('Control+k');
  const palette = page.locator('#command-palette-modal');
  const search = palette.getByPlaceholder(/Type a command/i);
  await expect(palette).toBeVisible();
  await expect(search).toBeFocused();
  await search.fill('payment');
  await expect(palette.getByText('Payment & Gateways')).toBeVisible();

  await page.keyboard.press('Escape');
  await expect(palette).toBeHidden();
  await expect(trigger).toBeFocused();
});

test('notifications close with Escape and restore focus to the bell', async ({ page }) => {
  await page.setViewportSize({ width: 1920, height: 900 });
  await page.goto('/');

  const bell = page.getByRole('button', { name: 'Notifications' });
  await bell.click();
  await expect(page.locator('#notification-drawer')).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(page.locator('#notification-drawer')).toBeHidden();
  await expect(bell).toBeFocused();
});

test('Profile supports close/open, test role selection, seed reset toast, and Escape focus restoration', async ({ page }) => {
  await page.setViewportSize({ width: 1920, height: 900 });
  await page.goto('/');

  const trigger = page.getByRole('button', { name: /Julian Admin/i });
  await trigger.click();
  await expect(page.locator('#profile-dropdown-menu')).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(page.locator('#profile-dropdown-menu')).toBeHidden();
  await expect(trigger).toBeFocused();

  await trigger.click();
  await page.getByRole('button', { name: 'Catalog Mgr' }).click();
  await expect(page.getByText('catalog_manager', { exact: true })).toBeVisible();

  await trigger.click();
  await page.getByText('Reset Seed Demo Data', { exact: true }).click();
  await expect(page.getByText('Database Reset', { exact: true })).toBeVisible();
  await expect(page.getByText('Restored default demo seed data.', { exact: true })).toBeVisible();
});

test('mobile drawer closes on Escape and restores focus to its hamburger trigger', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/');

  const trigger = page.getByRole('button', { name: 'Toggle Navigation' });
  await trigger.click();
  await expect(page.locator('#admin-sidebar')).toHaveClass(/translate-x-0/);
  await page.keyboard.press('Escape');
  await expect(page.locator('#admin-sidebar')).toHaveClass(/-translate-x-full/);
  await expect(trigger).toBeFocused();
});
