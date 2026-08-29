import { afterEach, describe, expect, it, vi } from 'vitest';
import type { LaravelAdminBootstrap } from '../types';
import type { EasyParcelWebhookAuditSnapshot, EasyParcelWebhookReceiptRow } from '../types/shipping';
import { createEasyParcelWebhookAuditRefresher, fetchEasyParcelWebhookReceipts } from './easyParcelWebhookData';

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

  it('prevents an older success or failure from overwriting the latest successful refresh', async () => {
    const pending = [deferred<EasyParcelWebhookReceiptRow[]>(), deferred<EasyParcelWebhookReceiptRow[]>()];
    let call = 0;
    let snapshot: EasyParcelWebhookAuditSnapshot = {
      receipts: [], status: 'ready', error: null,
    };
    const refresh = createEasyParcelWebhookAuditRefresher(
      () => pending[call++].promise,
      update => { snapshot = typeof update === 'function' ? update(snapshot) : update; },
    );

    const olderSuccess = refresh();
    const latestSuccess = refresh();
    pending[1].resolve([receiptRow('latest-success')]);
    await latestSuccess;
    pending[0].resolve([receiptRow('older-success')]);
    await olderSuccess;

    expect(snapshot.status).toBe('ready');
    expect(snapshot.receipts.map(row => row.id)).toEqual(['latest-success']);

    const failures = [deferred<EasyParcelWebhookReceiptRow[]>(), deferred<EasyParcelWebhookReceiptRow[]>()];
    call = 0;
    const refreshWithOlderFailure = createEasyParcelWebhookAuditRefresher(
      () => failures[call++].promise,
      update => { snapshot = typeof update === 'function' ? update(snapshot) : update; },
    );
    const olderFailure = refreshWithOlderFailure();
    const newestSuccess = refreshWithOlderFailure();
    failures[1].resolve([receiptRow('newest-success')]);
    await newestSuccess;
    failures[0].reject(new Error('older request failed'));
    await expect(olderFailure).resolves.toBeUndefined();

    expect(snapshot.status).toBe('ready');
    expect(snapshot.error).toBeNull();
    expect(snapshot.receipts.map(row => row.id)).toEqual(['newest-success']);
  });

  it('retains the last successful rows and records failure only for the latest request', async () => {
    const request = deferred<EasyParcelWebhookReceiptRow[]>();
    let snapshot: EasyParcelWebhookAuditSnapshot = {
      receipts: [receiptRow('cached-row')], status: 'ready', error: null,
    };
    const refresh = createEasyParcelWebhookAuditRefresher(
      () => request.promise,
      update => { snapshot = typeof update === 'function' ? update(snapshot) : update; },
    );

    const pendingRefresh = refresh();
    expect(snapshot).toMatchObject({ status: 'loading', error: null });
    expect(snapshot.receipts.map(row => row.id)).toEqual(['cached-row']);

    request.reject(new Error('EasyParcel webhook audit could not be loaded.'));
    await expect(pendingRefresh).rejects.toThrow('EasyParcel webhook audit could not be loaded.');

    expect(snapshot.status).toBe('error');
    expect(snapshot.error).toBe('EasyParcel webhook audit could not be loaded.');
    expect(snapshot.receipts.map(row => row.id)).toEqual(['cached-row']);
  });
});

const deferred = <T,>() => {
  let resolve!: (value: T) => void;
  let reject!: (reason: Error) => void;
  const promise = new Promise<T>((settle, fail) => { resolve = settle; reject = fail; });
  return { promise, resolve, reject };
};

const receiptRow = (id: string): EasyParcelWebhookReceiptRow => ({
  id,
  topic: 'shipment.tracking.update',
  state: 'processed',
  shipmentId: '9',
  orderNumber: 'JW-1009',
  trackingNumber: `AWB-${id}`,
  receivedAt: '2026-08-29T10:00:00+08:00',
  processedAt: '2026-08-29T10:01:00+08:00',
  attemptCount: 1,
  errorCode: null,
});
