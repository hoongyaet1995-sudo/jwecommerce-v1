import { afterEach, describe, expect, it, vi } from 'vitest';
import type { LaravelAdminBootstrap, Order } from '../types';
import { amendOrderDeliveryDetails, fetchOrders, reviewPaymentProofData, updateOrderStatusData } from './orderData';

const bootstrap: LaravelAdminBootstrap = {
  user: { name: 'Payments Admin', email: 'payments@example.test', avatar: null },
  csrfToken: 'csrf-token',
  logoutUrl: '/logout',
  ordersUrl: '/admin/api/orders',
};

const order = {
  id: '17',
  paymentGatewayStatus: 'paid',
  paymentProof: { status: 'verified' },
  notificationContextFingerprint: 'notification-context-v1',
  notificationSummary: null,
} as Order;
const queuedNotificationSummary = {
  publicId: 'dispatch-queued',
  eventKey: 'payment_verified',
  channel: 'email',
  state: 'queued',
  source: 'automatic',
  queuedAt: '2026-09-01T00:00:00+00:00',
};

afterEach(() => vi.unstubAllGlobals());

describe('Laravel payment-proof order adapter', () => {
  it('patches audited delivery details without treating the response as a complete order', async () => {
    const result = { shippingAddress: { firstName: 'Tee', lastName: 'Jing', phone: '0143323862', address1: 'New address', postalCode: '40150', city: 'Shah Alam', state: 'Selangor', country: 'Malaysia' }, addressBookSyncResult: 'not_requested', requiresReplacement: false };
    const request = vi.fn().mockResolvedValue(new Response(JSON.stringify({ data: result }), { status: 200, headers: { 'Content-Type': 'application/json' } }));
    vi.stubGlobal('window', { fetch: request });

    await expect(amendOrderDeliveryDetails(bootstrap, '17', {
      firstName: 'Tee', lastName: 'Jing', phone: '0143323862', address1: 'New address', address2: null,
      postalCode: '40150', city: 'Shah Alam', state: 'Selangor', country: 'Malaysia', reasonCode: 'customer_request',
      internalNote: 'Customer verified address.', syncAddressBook: false,
    })).resolves.toEqual(result);
    expect(request).toHaveBeenCalledWith('/admin/api/orders/17/delivery-details', expect.objectContaining({ method: 'PATCH', headers: expect.objectContaining({ 'X-CSRF-TOKEN': 'csrf-token' }) }));
  });
  it('rejects an order collection entry without the mandatory notification context fingerprint', async () => {
    const { notificationContextFingerprint: _notificationContextFingerprint, ...orderWithoutContextFingerprint } = order;
    const request = vi.fn().mockResolvedValue(new Response(JSON.stringify({ orders: [orderWithoutContextFingerprint] }), {
      status: 200,
      headers: { 'Content-Type': 'application/json' },
    }));
    vi.stubGlobal('window', { fetch: request });

    await expect(fetchOrders(bootstrap)).rejects.toThrow('Invalid order data response.');
  });

  it('sends the staff notification choice with the order mutation', async () => {
    const request = vi.fn().mockResolvedValue(new Response(JSON.stringify({ data: { ...order, notificationSummary: queuedNotificationSummary } }), {
      status: 200,
      headers: { 'Content-Type': 'application/json' },
    }));
    vi.stubGlobal('window', { fetch: request });

    await expect(updateOrderStatusData(bootstrap, '17', 'paid', 'processing', 'Packed', false)).resolves.toMatchObject({
      id: '17',
      notificationSummary: queuedNotificationSummary,
    });

    expect(request).toHaveBeenCalledWith('/admin/api/orders/17/status', expect.objectContaining({
      body: JSON.stringify({ paymentStatus: 'paid', fulfillmentStatus: 'processing', note: 'Packed', notifyCustomer: false }),
    }));
  });

  it('returns a null summary for an intentionally suppressed notification and omits an undefined notification choice', async () => {
    const request = vi.fn().mockResolvedValue(new Response(JSON.stringify({ data: { ...order, notificationSummary: null } }), {
      status: 200,
      headers: { 'Content-Type': 'application/json' },
    }));
    vi.stubGlobal('window', { fetch: request });

    await expect(updateOrderStatusData(bootstrap, '17', 'paid', 'processing', 'Packed')).resolves.toMatchObject({
      id: '17',
      notificationSummary: null,
    });

    expect(request).toHaveBeenCalledWith('/admin/api/orders/17/status', expect.objectContaining({
      body: JSON.stringify({ paymentStatus: 'paid', fulfillmentStatus: 'processing', note: 'Packed' }),
    }));
  });

  it('rejects an order mutation response that omits the mandatory notification summary', async () => {
    const { notificationSummary: _notificationSummary, ...orderWithoutNotificationSummary } = order;
    const request = vi.fn().mockResolvedValue(new Response(JSON.stringify({ data: orderWithoutNotificationSummary }), {
      status: 200,
      headers: { 'Content-Type': 'application/json' },
    }));
    vi.stubGlobal('window', { fetch: request });

    await expect(updateOrderStatusData(bootstrap, '17', 'paid', 'processing')).rejects.toThrow('Invalid order data response.');
  });

  it('rejects an order mutation response that omits the mandatory notification context fingerprint', async () => {
    const { notificationContextFingerprint: _notificationContextFingerprint, ...orderWithoutContextFingerprint } = order;
    const request = vi.fn().mockResolvedValue(new Response(JSON.stringify({ data: orderWithoutContextFingerprint }), {
      status: 200,
      headers: { 'Content-Type': 'application/json' },
    }));
    vi.stubGlobal('window', { fetch: request });

    await expect(updateOrderStatusData(bootstrap, '17', 'paid', 'processing')).rejects.toThrow('Invalid order data response.');
  });

  it('posts a CSRF-protected verification decision to the order-scoped proof endpoint', async () => {
    const request = vi.fn().mockResolvedValue(new Response(JSON.stringify({ data: order }), {
      status: 200,
      headers: { 'Content-Type': 'application/json' },
    }));
    vi.stubGlobal('window', { fetch: request });

    await expect(reviewPaymentProofData(bootstrap, '17', '29', 'verified', 'Matched against merchant statement.')).resolves.toEqual(order);

    expect(request).toHaveBeenCalledWith('/admin/api/orders/17/payments/29/proof/review', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': 'csrf-token',
      },
      body: JSON.stringify({ decision: 'verified', reason: 'Matched against merchant statement.' }),
    });
  });
});
