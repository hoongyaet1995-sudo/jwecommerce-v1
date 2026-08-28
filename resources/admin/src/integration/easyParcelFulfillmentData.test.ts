import { afterEach, describe, expect, it, vi } from 'vitest';
import type { LaravelAdminBootstrap } from '../types';
import { confirmEasyParcelBooking, requestEasyParcelQuote } from './easyParcelFulfillmentData';

const bootstrap = {
  user: { name: 'Admin', email: 'admin@example.test', avatar: null },
  csrfToken: 'csrf-token',
  logoutUrl: '/logout',
  easyParcelOrdersUrl: '/admin/api/shipping/orders',
} satisfies LaravelAdminBootstrap;

describe('EasyParcel fulfillment client', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('requests a persisted quote with the staff-confirmed package specification', async () => {
    const fetch = vi.fn().mockResolvedValue({ ok: true, json: async () => ({ data: { id: '77', services: [] } }) });
    vi.stubGlobal('window', { fetch });

    await requestEasyParcelQuote(bootstrap, '42', { weightKg: 1.25, lengthCm: 20, widthCm: 10, heightCm: 8 });

    expect(fetch).toHaveBeenCalledWith('/admin/api/shipping/orders/42/easyparcel/quotes', expect.objectContaining({
      method: 'POST',
      body: JSON.stringify({ package: { weightKg: 1.25, lengthCm: 20, widthCm: 10, heightCm: 8 } }),
    }));
  });

  it('confirms only a returned service against a persisted quote', async () => {
    const fetch = vi.fn().mockResolvedValue({ ok: true, json: async () => ({ data: { id: '91', fulfillmentStatus: 'ready_to_ship' } }) });
    vi.stubGlobal('window', { fetch });

    await confirmEasyParcelBooking(bootstrap, '42', '77', 'EP-STD');

    expect(fetch).toHaveBeenCalledWith('/admin/api/shipping/orders/42/easyparcel/bookings', expect.objectContaining({
      method: 'POST',
      body: JSON.stringify({ quoteId: '77', serviceCode: 'EP-STD' }),
    }));
  });
});
