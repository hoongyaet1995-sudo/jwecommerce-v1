// @vitest-environment jsdom
import React, { act, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { Order } from '../types';

const orderApi = vi.hoisted(() => ({
  fetchOrders: vi.fn(),
  updateOrderStatusData: vi.fn(),
  updateOrderTrackingData: vi.fn(),
  processOrderRefundData: vi.fn(),
  reviewPaymentProofData: vi.fn(),
}));

vi.mock('../integration/orderData', () => orderApi);

import { AdminProvider, useAdmin } from './AdminContext';

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

const address = {
  id: 'address-1',
  isDefault: true,
  type: 'shipping' as const,
  firstName: 'Amina',
  lastName: 'Buyer',
  address1: '1 Test Street',
  city: 'Kuala Lumpur',
  province: 'Kuala Lumpur',
  country: 'Malaysia',
  postalCode: '50000',
  phone: '+60123456789',
};

const order = (overrides: Partial<Order> = {}): Order => ({
  id: '17',
  orderNumber: 'JW-10017',
  createdAt: '2026-09-01T08:00:00.000Z',
  customerId: 'customer-17',
  customerName: 'Amina Buyer',
  customerEmail: 'initial@example.test',
  paymentGateway: 'Bank In / DuitNow',
  paymentGatewayStatus: 'pending',
  fulfillmentStatus: 'processing',
  currency: 'MYR',
  items: [],
  subtotal: 120,
  taxAmount: 0,
  shippingAmount: 8,
  discountAmount: 0,
  totalAmount: 128,
  shippingAddress: address,
  billingAddress: { ...address, id: 'billing-1', type: 'billing' },
  notificationContextFingerprint: 'context-initial',
  notificationSummary: null,
  timeline: [],
  ...overrides,
});

const deferred = <T,>() => {
  let resolve!: (value: T) => void;
  let reject!: (reason: unknown) => void;
  const promise = new Promise<T>((next, fail) => { resolve = next; reject = fail; });
  return { promise, resolve, reject };
};

let currentAdmin: ReturnType<typeof useAdmin> | null = null;
const roots: Array<{ root: ReturnType<typeof createRoot>; host: HTMLDivElement }> = [];

const Probe = () => {
  const admin = useAdmin();
  const [isOrderOpen, setIsOrderOpen] = useState(true);
  currentAdmin = admin;

  return (
    <div>
      <button type="button" onClick={() => setIsOrderOpen(false)}>Close order</button>
      <button type="button" onClick={() => setIsOrderOpen(true)}>Reopen order</button>
      {isOrderOpen && <span data-testid="selected-order-email">{admin.orders[0]?.customerEmail ?? 'none'}</span>}
    </div>
  );
};

const mountProvider = async () => {
  const host = document.createElement('div');
  document.body.append(host);
  const root = createRoot(host);
  roots.push({ root, host });
  await act(async () => root.render(<AdminProvider><Probe /></AdminProvider>));
  await vi.waitFor(() => expect(currentAdmin?.orders[0]?.customerEmail).toBe('initial@example.test'));
  return host;
};

const button = (host: HTMLElement, text: string): HTMLButtonElement => {
  const match = Array.from(host.querySelectorAll('button')).find(item => item.textContent === text);
  if (!match) throw new Error(`Button not found: ${text}`);
  return match;
};

beforeEach(() => {
  currentAdmin = null;
  window.__JW_ADMIN__ = {
    user: { name: 'Admin', email: 'admin@example.test', avatar: null },
    csrfToken: 'csrf-token',
    logoutUrl: '/logout',
    ordersUrl: '/admin/api/orders',
  };
  orderApi.fetchOrders.mockReset().mockResolvedValue([order()]);
  orderApi.updateOrderStatusData.mockReset();
  orderApi.updateOrderTrackingData.mockReset();
  orderApi.processOrderRefundData.mockReset();
  orderApi.reviewPaymentProofData.mockReset();
  vi.stubGlobal('fetch', vi.fn(async () => new Response(JSON.stringify({ message: 'Unavailable in unit test.' }), { status: 503 })));
});

afterEach(() => {
  roots.splice(0).forEach(({ root, host }) => {
    act(() => root.unmount());
    host.remove();
  });
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
  delete window.__JW_ADMIN__;
});

describe('AdminProvider whole-order mutation ownership', () => {
  it('does not let a delayed status response revert a newer proof review or a reopened order', async () => {
    const oldStatus = deferred<Order>();
    const newerProof = deferred<Order>();
    orderApi.updateOrderStatusData.mockReturnValue(oldStatus.promise);
    orderApi.reviewPaymentProofData.mockReturnValue(newerProof.promise);
    const host = await mountProvider();

    let statusResult!: Promise<Order | null>;
    let proofResult!: Promise<Order | null>;
    act(() => {
      statusResult = currentAdmin!.updateOrderStatus('17', 'paid', 'processing', 'Old status');
      proofResult = currentAdmin!.reviewPaymentProof('17', 'payment-17', 'verified');
    });
    const newer = order({ customerEmail: 'newer-proof@example.test', notificationContextFingerprint: 'context-newer-proof' });
    await act(async () => {
      newerProof.resolve(newer);
      await expect(proofResult).resolves.toEqual(newer);
    });
    expect(currentAdmin!.orders[0].customerEmail).toBe('newer-proof@example.test');

    const stale = order({ customerEmail: 'stale-status@example.test', notificationContextFingerprint: 'context-stale-status' });
    await act(async () => {
      oldStatus.resolve(stale);
      await expect(statusResult).resolves.toBeNull();
    });
    expect(currentAdmin!.orders[0].customerEmail).toBe('newer-proof@example.test');

    await act(async () => button(host, 'Close order').click());
    await act(async () => button(host, 'Reopen order').click());
    expect(host.querySelector('[data-testid="selected-order-email"]')?.textContent).toBe('newer-proof@example.test');
  });

  it('does not let a delayed proof response revert a newer tracking mutation', async () => {
    const oldProof = deferred<Order>();
    const newerTracking = deferred<Order>();
    orderApi.reviewPaymentProofData.mockReturnValue(oldProof.promise);
    orderApi.updateOrderTrackingData.mockReturnValue(newerTracking.promise);
    await mountProvider();

    let proofResult!: Promise<Order | null>;
    let trackingResult!: Promise<Order | null>;
    act(() => {
      proofResult = currentAdmin!.reviewPaymentProof('17', 'payment-17', 'verified');
      trackingResult = currentAdmin!.updateOrderTracking('17', 'New Carrier', 'NEW-TRACKING');
    });
    const newer = order({ customerEmail: 'newer-tracking@example.test', trackingNumber: 'NEW-TRACKING', notificationContextFingerprint: 'context-newer-tracking' });
    await act(async () => {
      newerTracking.resolve(newer);
      await expect(trackingResult).resolves.toEqual(newer);
    });
    const stale = order({ customerEmail: 'stale-proof@example.test', notificationContextFingerprint: 'context-stale-proof' });
    await act(async () => {
      oldProof.resolve(stale);
      await expect(proofResult).resolves.toBeNull();
    });

    expect(currentAdmin!.orders[0].customerEmail).toBe('newer-tracking@example.test');
    expect(currentAdmin!.orders[0].trackingNumber).toBe('NEW-TRACKING');
  });

  it('includes refund in the same per-order mutation ownership boundary', async () => {
    const oldTracking = deferred<Order>();
    const newerRefund = deferred<Order>();
    orderApi.updateOrderTrackingData.mockReturnValue(oldTracking.promise);
    orderApi.processOrderRefundData.mockReturnValue(newerRefund.promise);
    await mountProvider();

    let trackingResult!: Promise<Order | null>;
    let refundResult!: Promise<Order | null>;
    act(() => {
      trackingResult = currentAdmin!.updateOrderTracking('17', 'Old Carrier', 'OLD-TRACKING');
      refundResult = currentAdmin!.processOrderRefund('17', 10, 'Newer refund');
    });
    const newer = order({ customerEmail: 'newer-refund@example.test', notificationContextFingerprint: 'context-newer-refund' });
    await act(async () => {
      newerRefund.resolve(newer);
      await expect(refundResult).resolves.toEqual(newer);
    });
    const stale = order({ customerEmail: 'stale-tracking@example.test', trackingNumber: 'OLD-TRACKING', notificationContextFingerprint: 'context-stale-tracking' });
    await act(async () => {
      oldTracking.resolve(stale);
      await expect(trackingResult).resolves.toBeNull();
    });

    expect(currentAdmin!.orders[0].customerEmail).toBe('newer-refund@example.test');
    expect(currentAdmin!.orders[0].notificationContextFingerprint).toBe('context-newer-refund');
  });

  it('still reports and normalizes the current mutation error', async () => {
    orderApi.updateOrderStatusData.mockRejectedValue(new Error('Current status failed.'));
    await mountProvider();

    let result!: Order | null;
    await act(async () => {
      result = await currentAdmin!.updateOrderStatus('17', 'paid', 'processing', 'Current status');
    });

    expect(result).toBeNull();
    expect(currentAdmin!.toasts).toEqual(expect.arrayContaining([
      expect.objectContaining({ type: 'error', title: 'Order Update Failed', message: 'Current status failed.' }),
    ]));
  });
});
