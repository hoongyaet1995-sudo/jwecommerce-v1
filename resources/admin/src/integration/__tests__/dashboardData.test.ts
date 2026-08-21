import { describe, expect, it, vi } from 'vitest';
import { fetchDashboardData } from '../dashboardData';

const bootstrap = {
  user: { name: 'Julian Admin', email: 'julian@example.test', avatar: null },
  csrfToken: 'csrf-token',
  logoutUrl: '/logout',
  dashboardUrl: '/admin/api/dashboard',
};

const response = {
  metrics: { sales_today: 2500, orders: 1, customers: 1, low_stock: 1, pending_orders: 3 },
  recentOrders: [{ id: 1, number: 'JW-DASH-1', email: 'buyer@example.test', customerName: 'Buyer', status: 'paid', total: 2500, itemCount: 1, createdAt: '2026-08-19T00:00:00+00:00' }],
  lowStockVariants: [{ id: 1, sku: 'LOW-001', title: 'Standard', stock: 2, image: 'images/catalog/aether-1.jpg', product: { id: 1, title: 'Low Stock Tool', handle: 'low-stock-tool' } }],
  revenueTrend: [{ label: 'Wed', total: 2500 }],
  categoryCounts: [{ id: 1, name: 'Studio Audio', productsCount: 1 }],
};

describe('Laravel dashboard data adapter', () => {
  it('requests the strict Laravel host dashboard endpoint and preserves explicit server values', async () => {
    const request = vi.fn().mockResolvedValue({ ok: true, json: async () => response });

    await expect(fetchDashboardData(bootstrap, request)).resolves.toEqual(response);
    expect(request).toHaveBeenCalledWith('/admin/api/dashboard', {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    });
  });

  it('keeps the ZIP mock data when an intentional controlled design fixture omits only the dashboard endpoint', async () => {
    const request = vi.fn();
    const viteBootstrap = { ...bootstrap, dashboardUrl: undefined };

    await expect(fetchDashboardData(viteBootstrap, request)).resolves.toBeNull();
    expect(request).not.toHaveBeenCalled();
  });

  it('rejects an invalid server response instead of merging unvalidated data into the UI', async () => {
    const request = vi.fn().mockResolvedValue({ ok: true, json: async () => ({ metrics: {} }) });

    await expect(fetchDashboardData(bootstrap, request)).rejects.toThrow('Invalid dashboard data response');
  });

  it('rejects a payload that does not provide the operational pending-order total', async () => {
    const { metrics, ...incompleteResponse } = response;
    const request = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ ...incompleteResponse, metrics: { ...metrics, pending_orders: undefined } }),
    });

    await expect(fetchDashboardData(bootstrap, request)).rejects.toThrow('Invalid dashboard data response');
  });
});
