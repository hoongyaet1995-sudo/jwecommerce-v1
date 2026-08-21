import { expect, test } from '@playwright/test';

const laravelBootstrap = {
  user: { name: 'Julian Admin', email: 'julian@example.test', avatar: null },
  csrfToken: 'csrf-token',
  logoutUrl: '/logout',
  dashboardUrl: '/admin/api/dashboard',
};

const dashboardResponse = {
  metrics: { sales_today: 2500, orders: 1, customers: 1, low_stock: 1, pending_orders: 6 },
  recentOrders: [{ id: 1, number: 'JW-DASH-1', email: 'buyer@example.test', customerName: 'Buyer', status: 'pending_payment', total: 2500, itemCount: 1, createdAt: '2026-08-19T00:00:00+00:00' }],
  lowStockVariants: [{ id: 1, sku: 'LOW-001', title: 'Standard', stock: 2, image: 'images/catalog/aether-1.jpg', product: { id: 1, title: 'Low Stock Tool', handle: 'low-stock-tool' } }],
  revenueTrend: [{ label: 'Wed', total: 2500 }],
  categoryCounts: [{ id: 1, name: 'Studio Audio', productsCount: 1 }],
};

test('DashboardOverview renders authenticated Laravel contract values inside the imported UI', async ({ page }) => {
  await page.addInitScript((bootstrap) => {
    window.__JW_ADMIN__ = bootstrap;
  }, laravelBootstrap);
  await page.route('**/admin/api/dashboard', async (route) => {
    await route.fulfill({ json: dashboardResponse });
  });

  await page.goto('/admin');

  await expect(page.getByText('$25.00', { exact: true }).first()).toBeVisible();
  await expect(page.getByText('Low Stock Tool', { exact: true })).toBeVisible();
  await expect(page.getByText('JW-DASH-1', { exact: true })).toBeVisible();
  await expect(page.getByText('pending_payment', { exact: true })).toBeVisible();
  await expect(page.getByText('6 orders needing action', { exact: true })).toBeVisible();
  await expect(page.getByText('Studio Audio', { exact: true })).toBeVisible();
});

test('a controlled Vite design fixture still requires a valid Laravel-shaped identity bootstrap and retains ZIP data without a dashboard endpoint', async ({ page }) => {
  const fixtureBootstrap: typeof laravelBootstrap & { dashboardUrl?: string } = { ...laravelBootstrap };
  delete fixtureBootstrap.dashboardUrl;
  let dashboardRequested = false;

  await page.addInitScript((bootstrap) => {
    window.__JW_ADMIN__ = bootstrap;
  }, fixtureBootstrap);
  await page.route('**/admin/api/dashboard', async (route) => {
    dashboardRequested = true;
    await route.abort();
  });

  await page.goto('/admin');

  await expect(page.getByText('View All (1280)', { exact: true })).toBeVisible();
  expect(dashboardRequested).toBe(false);
});
