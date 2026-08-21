import { expect, test } from '@playwright/test';

const laravelBootstrap = {
  user: { name: 'Julian Admin', email: 'julian@example.test', avatar: null },
  csrfToken: 'csrf-token',
  logoutUrl: '/logout',
  customersUrl: '/admin/api/customers',
};

const customerDirectoryResponse = {
  customers: [{
    id: 42,
    name: 'Metrics Buyer',
    email: 'metrics@example.test',
    phone: '+60 12 345 6789',
    avatar: null,
    totalOrders: 2,
    totalSpent: 2100,
    currency: 'MYR',
    registeredAt: '2026-08-19T00:00:00+00:00',
    lastOrderAt: '2026-08-19T00:01:00+00:00',
    status: null,
    tags: [],
    notes: null,
    addresses: [{ id: 1, isDefault: true, label: 'Home', firstName: 'Metrics', lastName: 'Buyer', phone: '+60 12 345 6789', address1: '1 Jalan Example', address2: null, city: 'Kuala Lumpur', province: null, country: 'Malaysia', postalCode: '50000' }],
  }],
  pagination: { currentPage: 1, lastPage: 1, perPage: 30, total: 1 },
};

test('Customer Directory keeps the ZIP table structure while rendering only the protected contract values', async ({ page }) => {
  await page.addInitScript(({ bootstrap, response }) => {
    window.__JW_ADMIN__ = bootstrap;
    window.fetch = async (input) => {
      if (String(input) === bootstrap.customersUrl) {
        return new Response(JSON.stringify(response), { status: 200, headers: { 'Content-Type': 'application/json' } });
      }

      throw new Error(`Unexpected request: ${String(input)}`);
    };
  }, { bootstrap: laravelBootstrap, response: customerDirectoryResponse });

  await page.goto('/admin/customers');

  await expect(page.getByText('Customer Directory & Accounts')).toBeVisible();
  await expect(page.getByRole('cell', { name: 'Metrics Buyer metrics@example.test' })).toBeVisible();
  await expect(page.getByRole('cell', { name: '2 orders' })).toBeVisible();
  await expect(page.getByRole('cell', { name: '$21.00' })).toBeVisible();
  await expect(page.getByText('Elena Rostova')).toHaveCount(0);
  await expect(page.locator('tbody tr').filter({ hasText: 'Metrics Buyer' })).not.toContainText('VIP Member');
});
