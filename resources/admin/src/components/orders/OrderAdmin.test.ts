// @vitest-environment jsdom
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { Order } from '../../types';

const mockedAdminContext = vi.hoisted(() => ({ value: {} as Record<string, unknown> }));

vi.mock('../../context/AdminContext', () => ({
  useAdmin: () => mockedAdminContext.value,
}));

import { notificationEventKeyForStatus, OrderAdmin } from './OrderAdmin';

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

const source = readFileSync(resolve(process.cwd(), 'src/components/orders/OrderAdmin.tsx'), 'utf8');

describe('OrderAdmin manual fulfillment source fidelity', () => {
  it('retains the approved manual dispatch controls and awaits the persisted tracking adapter', () => {
    expect(source).toContain('Carrier / hand-off partner');
    expect(source).toContain('Tracking reference');
    expect(source).toContain('Record dispatch');
    expect(source).toContain('Records a manual shipment and append-only dispatch event. No courier label is purchased.');
    expect(source).toContain('const saved = await updateOrderTracking(selectedOrder.id, carrier, tracking);');
    expect(source).toContain('if (saved) {');
  });

  it('binds the Bank In-only proof controls to persisted Laravel data rather than browser mock state', () => {
    expect(source).toContain('selectedOrder.paymentProof?.required');
    expect(source).toContain('reviewPaymentProof');
    expect(source).toContain('Payment Summary');
    expect(source).toContain('Shipping Source');
    expect(source).not.toContain('URL.createObjectURL');
    expect(source).not.toContain('localStorage.setItem');
    expect(source).toContain('Payment verification required:');
    expect(source).toContain('This manual Bank In / DuitNow order cannot proceed to parcel fulfillment or courier dispatch until payment proof is verified by authorized staff.');
  });

  it('retains the selected notification frame while removing simulated delivery ownership', () => {
    expect(source).toContain('Update Order Status & Dispatch Customer Notification');
    expect(source).toContain('p-4 rounded-2xl bg-indigo-50/60 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-900/60 space-y-3');
    expect(source).not.toContain('email_sent');
    expect(source).not.toContain('whatsapp_sent');
    expect(source).not.toContain('Dispatched instant customer notification.');
  });
});

describe('OrderAdmin canonical notification event ownership', () => {
  it('maps only exact owned status codes and rejects substring lookalikes', () => {
    expect(notificationEventKeyForStatus('pending')).toBe('order_created');
    expect(notificationEventKeyForStatus('processing')).toBe('payment_verified');
    expect(notificationEventKeyForStatus('ready_to_ship')).toBe('ready_to_ship');
    expect(notificationEventKeyForStatus('cancelled')).toBe('cancelled');
    expect(notificationEventKeyForStatus('unpaid_review')).toBeNull();
    expect(notificationEventKeyForStatus('cancel_requested')).toBeNull();
    expect(notificationEventKeyForStatus('processing_manual')).toBeNull();
  });
});

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

const baseOrder = (overrides: Partial<Order> = {}): Order => ({
  id: '17',
  orderNumber: 'JW-10017',
  createdAt: '2026-08-31T08:00:00.000Z',
  customerId: 'customer-17',
  customerName: 'Amina Buyer',
  customerEmail: 'amina@example.test',
  paymentGateway: 'stripe',
  paymentGatewayStatus: 'paid',
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
  notificationSummary: null,
  customStatusId: 'status_processing',
  timeline: [{
    id: 'event-1',
    timestamp: '2026-08-31T08:00:00.000Z',
    type: 'paid',
    title: 'Payment verified',
    description: 'Payment was verified.',
  }],
  ...overrides,
});

const roots: Array<{ root: ReturnType<typeof createRoot>; host: HTMLDivElement }> = [];

const mountOrderAdmin = async () => {
  const host = document.createElement('div');
  document.body.append(host);
  const root = createRoot(host);
  roots.push({ root, host });
  await act(async () => root.render(React.createElement(OrderAdmin)));
  return { host, root };
};

const exactButton = (host: HTMLElement, label: string): HTMLButtonElement => {
  const match = Array.from(host.querySelectorAll('button')).find(button => button.textContent?.trim() === label);
  if (!match) throw new Error(`Button not found: ${label}`);
  return match as HTMLButtonElement;
};

beforeEach(() => {
  const updateOrderStatus = vi.fn(async (
    _orderId: string,
    _paymentStatus: string,
    _fulfillmentStatus: string,
    _note: string,
    notifyCustomer?: boolean,
  ) => ({
    ...baseOrder(),
    notificationSummary: notifyCustomer ? {
      publicId: 'dispatch-new',
      eventKey: 'payment_verified' as const,
      channel: 'email' as const,
      state: 'queued' as const,
      source: 'automatic' as const,
      queuedAt: '2026-08-31T08:02:00.000Z',
    } : null,
  }));
  const auditFetch = vi.fn(async (input: RequestInfo | URL, init: RequestInit = {}) => {
    const url = String(input);
    if (url.endsWith('/notification-dispatches')) return new Response(JSON.stringify({ data: [] }));
    if (url.endsWith('/notifications/preview')) {
      const body = JSON.parse(String(init.body ?? '{}')) as { eventKey: string; staffNote?: string };
      return new Response(JSON.stringify({ data: {
        eventKey: body.eventKey,
        statusName: body.eventKey === 'payment_verified' ? 'Payment Verified' : 'Status Update',
        emailEnabled: body.eventKey !== 'on_hold',
        subject: `${body.eventKey} preview`,
        preheader: '',
        heading: 'Order update',
        body: body.staffNote || 'No note',
        ctaText: 'Track order',
        ctaUrl: 'https://ecom.jwaistudio.online/order-tracking',
        variables: {},
      } }));
    }
    return new Response(JSON.stringify({ message: 'Not found' }), { status: 404 });
  });
  vi.stubGlobal('fetch', auditFetch);
  mockedAdminContext.value = {
    laravelBootstrap: {
      user: { name: 'Admin', email: 'admin@example.test', avatar: null },
      csrfToken: 'csrf-token',
      logoutUrl: '/logout',
      orderNotificationsUrl: '/admin/api/orders',
    },
    notificationPermissions: [
      'notifications.deliveries.view',
      'notifications.deliveries.retry',
      'notifications.deliveries.resend',
      'notifications.current.send',
    ],
    orders: [baseOrder()],
    updateOrderStatus,
    updateOrderTracking: vi.fn(),
    reviewPaymentProof: vi.fn(),
    orderStatuses: [{
      id: 'status_processing',
      code: 'processing',
      name: 'Processing & Verified',
      description: 'Processing',
      color: 'indigo',
      isDefault: false,
      isDefaultPaid: true,
      isDefaultCancelled: false,
      notifyCustomer: true,
      publicVisibility: true,
      inventoryAction: 'deduct',
      emailTemplate: 'Payment verified',
      sortOrder: 20,
    }],
    setCurrentSection: vi.fn(),
    auditFetch,
  };
});

afterEach(() => {
  roots.splice(0).forEach(({ root, host }) => {
    act(() => root.unmount());
    host.remove();
  });
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
});

describe('OrderAdmin notification mutation binding', () => {
  it('passes an unchecked Email choice as false and refreshes the selected order plus audit', async () => {
    const { host } = await mountOrderAdmin();
    await act(async () => exactButton(host, 'Details').click());
    await vi.waitFor(() => expect(host.textContent).toContain('Dispatch Email Update to amina@example.test'));
    const checkbox = host.querySelector('input[type="checkbox"]') as HTMLInputElement;

    await act(async () => checkbox.click());
    expect(checkbox.checked).toBe(false);
    await act(async () => exactButton(host, 'Update & Notify').click());

    const context = mockedAdminContext.value as {
      updateOrderStatus: ReturnType<typeof vi.fn>;
      auditFetch: ReturnType<typeof vi.fn>;
    };
    await vi.waitFor(() => expect(context.updateOrderStatus).toHaveBeenCalled());
    expect(context.updateOrderStatus.mock.calls[0][4]).toBe(false);
    await vi.waitFor(() => expect(context.auditFetch.mock.calls.filter(([url]) => String(url).endsWith('/notification-dispatches'))).toHaveLength(2));
    expect(host.textContent).not.toContain('Status update Email intent: Queued');
  });

  it('passes the checked Email choice as true and renders only the queued backend intent', async () => {
    const { host } = await mountOrderAdmin();
    await act(async () => exactButton(host, 'Details').click());
    await act(async () => exactButton(host, 'Update & Notify').click());

    const updateOrderStatus = (mockedAdminContext.value as { updateOrderStatus: ReturnType<typeof vi.fn> }).updateOrderStatus;
    await vi.waitFor(() => expect(updateOrderStatus).toHaveBeenCalled());
    expect(updateOrderStatus.mock.calls[0][4]).toBe(true);
    await vi.waitFor(() => expect(host.textContent).toContain('Status update Email intent: Queued'));
    expect(host.textContent).not.toContain('Status update Email intent: Sent');
  });

  it('keeps the selected Bank In payment-verification notice bound to authoritative proof truth', async () => {
    mockedAdminContext.value = {
      ...mockedAdminContext.value,
      orders: [baseOrder({
        paymentGateway: 'Bank In / DuitNow',
        paymentGatewayStatus: 'pending',
        paymentProof: { required: true, status: 'uploaded', canReview: true },
      })],
    };
    const { host } = await mountOrderAdmin();
    await act(async () => exactButton(host, 'Details').click());

    expect(host.textContent).toContain('Payment verification required:');
    expect(host.textContent).toContain('This manual Bank In / DuitNow order cannot proceed to parcel fulfillment or courier dispatch until payment proof is verified by authorized staff.');
  });

  it('invalidates a delayed status mutation when same-order note and status drafts change through ABA', async () => {
    let resolveMutation!: (order: Order) => void;
    const updateOrderStatus = vi.fn(() => new Promise<Order>(resolve => { resolveMutation = resolve; }));
    mockedAdminContext.value = {
      ...mockedAdminContext.value,
      updateOrderStatus,
      orderStatuses: [
        ...(mockedAdminContext.value.orderStatuses as Array<Record<string, unknown>>),
        {
          id: 'status_delivered', code: 'delivered', name: 'Delivered & Completed', description: 'Delivered', color: 'emerald',
          isDefault: false, isDefaultPaid: false, isDefaultCancelled: false, notifyCustomer: true,
          publicVisibility: true, inventoryAction: 'none', emailTemplate: 'Delivered', sortOrder: 50,
        },
      ],
    };
    const { host } = await mountOrderAdmin();
    await act(async () => exactButton(host, 'Details').click());
    const statusSelect = Array.from(host.querySelectorAll('select')).find(select =>
      Array.from(select.options).some(option => option.value === 'status_processing'),
    ) as HTMLSelectElement;
    const note = host.querySelector('input[placeholder^="e.g. Package packed"]') as HTMLInputElement;

    act(() => exactButton(host, 'Update & Notify').click());
    await vi.waitFor(() => expect(updateOrderStatus).toHaveBeenCalledTimes(1));
    await act(async () => {
      Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')?.set?.call(note, 'Newer same-order note');
      note.dispatchEvent(new Event('input', { bubbles: true }));
      statusSelect.value = 'status_delivered';
      statusSelect.dispatchEvent(new Event('change', { bubbles: true }));
      statusSelect.value = 'status_processing';
      statusSelect.dispatchEvent(new Event('change', { bubbles: true }));
    });

    await act(async () => resolveMutation({
      ...baseOrder(),
      notificationSummary: {
        publicId: 'stale-dispatch', eventKey: 'payment_verified', channel: 'email', state: 'queued', source: 'automatic', queuedAt: '2026-08-31T08:02:00.000Z',
      },
    }));

    expect(statusSelect.value).toBe('status_processing');
    expect(note.value).toBe('Newer same-order note');
    expect(host.textContent).not.toContain('Status update Email intent: Queued');
  });
});
