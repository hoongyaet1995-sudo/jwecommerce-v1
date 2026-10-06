import { describe, expect, it, vi } from 'vitest';
import { fetchCustomerDirectoryData } from '../customerDirectoryData';

const bootstrap = {
  user: { name: 'Julian Admin', email: 'julian@example.test', avatar: null },
  csrfToken: 'csrf-token',
  logoutUrl: '/logout',
  customersUrl: '/admin/api/customers',
};

const response = {
  customers: [{
    id: 1,
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

describe('Laravel customer-directory data adapter', () => {
  it('accepts provisional contacts with null email and platform history', async () => {
    const data = { ...response, customers: [{ ...response.customers[0], email: null, platforms: ['instagram', 'whatsapp'] }] };
    const request = vi.fn().mockResolvedValue({ ok: true, json: async () => data });
    await expect(fetchCustomerDirectoryData(bootstrap, request)).resolves.toEqual(data);
  });
  it('requests the strict Laravel host endpoint and preserves null database-only fields', async () => {
    const request = vi.fn().mockResolvedValue({ ok: true, json: async () => response });

    await expect(fetchCustomerDirectoryData(bootstrap, request)).resolves.toEqual(response);
    expect(request).toHaveBeenCalledWith('/admin/api/customers', {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    });
  });

  it('keeps the ZIP fixture customers in isolated Vite when no Laravel endpoint is supplied', async () => {
    const request = vi.fn();

    await expect(fetchCustomerDirectoryData({ ...bootstrap, customersUrl: undefined }, request)).resolves.toBeNull();
    expect(request).not.toHaveBeenCalled();
  });

  it('rejects a customer status outside the persisted status contract', async () => {
    const request = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ ...response, customers: [{ ...response.customers[0], status: 'super_vip' }] }),
    });

    await expect(fetchCustomerDirectoryData(bootstrap, request)).rejects.toThrow('Invalid customer directory data response');
  });
});
