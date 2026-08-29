import { afterEach, describe, expect, it, vi } from 'vitest';
import type { LaravelAdminBootstrap } from '../types';
import { fetchEasyParcelWebhookReceipts } from './easyParcelWebhookData';

const bootstrap = {
  user: { name: 'Admin', email: 'admin@example.test', avatar: null },
  csrfToken: 'csrf-token',
  logoutUrl: '/logout',
  easyParcelWebhookReceiptsUrl: '/admin/api/shipping/webhook-receipts',
} satisfies LaravelAdminBootstrap;

describe('EasyParcel webhook receipt client', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('loads the safe receipt projection with same-origin credentials', async () => {
    const rows = [{
      id: '41',
      topic: 'shipment.tracking.update',
      state: 'processed' as const,
      shipmentId: '9',
      orderNumber: 'JW-1009',
      trackingNumber: 'AWB-1009',
      receivedAt: '2026-08-29T10:00:00+08:00',
      processedAt: '2026-08-29T10:00:01+08:00',
      attemptCount: 1,
      errorCode: null,
    }];
    const fetch = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ data: rows }),
    });
    vi.stubGlobal('window', { fetch });

    await expect(fetchEasyParcelWebhookReceipts(bootstrap)).resolves.toEqual(rows);
    expect(fetch).toHaveBeenCalledWith('/admin/api/shipping/webhook-receipts', {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    });
  });

  it('uses the audit-specific error when the protected read fails', async () => {
    const fetch = vi.fn().mockResolvedValue({ ok: false });
    vi.stubGlobal('window', { fetch });

    await expect(fetchEasyParcelWebhookReceipts(bootstrap)).rejects.toThrow(
      'EasyParcel webhook audit could not be loaded.',
    );
  });

  it('performs a fresh request and preserves nullable processedAt on every refresh', async () => {
    const first = [{
      id: 'receipt-1', topic: 'shipment.tracking.update', state: 'queued' as const,
      shipmentId: '9', orderNumber: 'JW-1009', trackingNumber: 'AWB-1009',
      receivedAt: '2026-08-29T10:00:00+08:00', processedAt: null,
      attemptCount: 1, errorCode: null,
    }];
    const second = [{ ...first[0], id: 'receipt-2', state: 'processed' as const, processedAt: '2026-08-29T10:01:00+08:00' }];
    const fetch = vi.fn()
      .mockResolvedValueOnce({ ok: true, json: async () => ({ data: first }) })
      .mockResolvedValueOnce({ ok: true, json: async () => ({ data: second }) });
    vi.stubGlobal('window', { fetch });

    await expect(fetchEasyParcelWebhookReceipts(bootstrap)).resolves.toEqual(first);
    await expect(fetchEasyParcelWebhookReceipts(bootstrap)).resolves.toEqual(second);
    expect(fetch).toHaveBeenCalledTimes(2);
  });
});
