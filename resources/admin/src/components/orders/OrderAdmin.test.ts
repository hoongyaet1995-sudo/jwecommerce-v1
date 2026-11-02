import { flushSync } from 'react-dom';
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

import { notificationEventKeyForStatus, OrderAdmin, shouldShowReturnsWorkbench } from './OrderAdmin';

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

const source = readFileSync(resolve(process.cwd(), 'src/components/orders/OrderAdmin.tsx'), 'utf8');

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
  notificationContextFingerprint: 'order-context-v1',
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
  if(label==='Details') return detailButton(host,0);
  const match = Array.from(host.querySelectorAll('button')).find(button => button.textContent?.trim() === label);
  if (!match) throw new Error(`Button not found: ${label}`);
  return match as HTMLButtonElement;
};

const detailButton = (host: HTMLElement, index: number): HTMLButtonElement => {
  const trigger = host.querySelectorAll<HTMLButtonElement>('.jw-record-actions > button')[index];
  if(!trigger) throw new Error('Order actions not found');
  return {click:()=>{flushSync(()=>trigger.click());(document.querySelector('[role="menuitem"]') as HTMLButtonElement).click();}} as HTMLButtonElement;
};

const closeDetailButton = (host: HTMLElement): HTMLButtonElement => {
  const match = Array.from(host.querySelectorAll('button')).find(b=>b.textContent?.trim()==='Back to orders');
  if (!match) throw new Error('Order Detail close button not found');
  return match;
};

const deferred = <T,>() => {
  let resolve!: (value: T) => void;
  const promise = new Promise<T>(next => { resolve = next; });
  return { promise, resolve };
};

const fillManualDispatch = async (host: HTMLElement, carrierValue: string, trackingValue: string) => {
  const carrier = host.querySelector('input[placeholder^="e.g. DHL Express"]') as HTMLInputElement;
  const tracking = host.querySelector('input[placeholder^="e.g. TRK-"]') as HTMLInputElement;
  await act(async () => {
    Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')?.set?.call(carrier, carrierValue);
    carrier.dispatchEvent(new Event('input', { bubbles: true }));
    Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')?.set?.call(tracking, trackingValue);
    tracking.dispatchEvent(new Event('input', { bubbles: true }));
  });
};

beforeEach(() => {
  window.history.replaceState(null,'','/admin/orders');
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
      ordersUrl: '/admin/api/orders',
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


describe('Order detail workflow integration',()=>{
 async function open(){const {host}=await mountOrderAdmin();await act(async()=>exactButton(host,'Details').click());return host}
 async function status(host:HTMLElement){await act(async()=>exactButton(host,'Update status').click());await act(async()=>exactButton(host,'Update order status').click())}
 async function submit(host:HTMLElement){await act(async()=>document.querySelector('.jw-order-dialog form')!.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true})))}
 it('shows real order panels, invoice and breadcrumbs without notification tools',async()=>{
 const host=await open();expect(host.textContent).toContain('Order JW-10017');expect(host.textContent).toContain('After-sales');expect(host.querySelector('[aria-label="Breadcrumb"]')).not.toBeNull();expect(host.querySelector('a[href$="/17/invoice"]')).not.toBeNull();expect(host.textContent).not.toContain('Preview Message Template');expect(host.querySelector('input[type="checkbox"]')).toBeNull();
 });
 it('does not mutate an order when entering or reviewing status changes',async()=>{const host=await open();await status(host);await submit(host);expect(document.body.textContent).toContain('Review status change');expect(mockedAdminContext.value.updateOrderStatus).not.toHaveBeenCalled();});
 it('confirms a canonical status with automatic notification once',async()=>{const host=await open();await status(host);await submit(host);await submit(host);expect(mockedAdminContext.value.updateOrderStatus).toHaveBeenCalledTimes(1);expect((mockedAdminContext.value.updateOrderStatus as ReturnType<typeof vi.fn>).mock.calls[0][4]).toBe(true);expect(document.querySelector('[role="dialog"]')).toBeNull();});
 it.each(['constructor','toString','__proto__','unpaid_review','cancel_requested','processing_manual'])('does not notify for unowned status %s',async(code)=>{(mockedAdminContext.value.orderStatuses as any[])[0].code=code;const host=await open();await status(host);await submit(host);await submit(host);expect((mockedAdminContext.value.updateOrderStatus as ReturnType<typeof vi.fn>).mock.calls[0][4]).toBe(false);});
 it('retains the status dialog and reports a rejected save',async()=>{mockedAdminContext.value.updateOrderStatus=vi.fn().mockRejectedValue(new Error('Payment must be verified.'));const host=await open();await status(host);await submit(host);await submit(host);expect(document.querySelector('[role="alert"]')?.textContent).toContain('Payment must be verified');expect(document.querySelector('[role="dialog"]')).not.toBeNull();});
 it('opens an after-sales request without sending it',async()=>{const host=await open();await act(async()=>exactButton(host,'Returns & refunds').click());expect(document.querySelector('[aria-label="Return or refund request"]')).not.toBeNull();expect(document.body.textContent).toContain('does not issue a refund');expect(mockedAdminContext.value.auditFetch).not.toHaveBeenCalled();});
 it('offers the processing transition for a verified paid order before shipment eligibility',async()=>{mockedAdminContext.value.orders=[baseOrder({fulfillmentStatus:'unfulfilled'})];const host=await open();expect(host.textContent).toContain('Shipment');await act(async()=>exactButton(host,'Start fulfillment').click());expect(document.querySelector('select')?.value).toBe('status_processing');expect(mockedAdminContext.value.updateOrderStatus).not.toHaveBeenCalled();});
 it('restores a linked order after reload without a mutation',async()=>{window.history.replaceState(null,'','/admin/orders?order=17');const {host}=await mountOrderAdmin();expect(host.textContent).toContain('Order JW-10017');expect(mockedAdminContext.value.updateOrderStatus).not.toHaveBeenCalled();await act(async()=>exactButton(host,'Orders').click());expect(new URLSearchParams(window.location.search).has('order')).toBe(false);});
 it('places quotation beside invoice with a distinct button style',async()=>{mockedAdminContext.value.orders=[baseOrder({quotation:{url:'/quotations/test',number:'QT-1'} as any})];const host=await open();const quote=host.querySelector('.jw-order-top-actions a.jw-order-quotation');expect(quote?.textContent).toContain('View quotation');expect(quote?.getAttribute('href')).toBe('/quotations/test');expect(host.textContent).not.toContain('View accepted quotation');});
 it('maps only exact owned notification status codes',()=>{expect(notificationEventKeyForStatus('processing')).toBe('payment_verified');expect(notificationEventKeyForStatus('processing_manual')).toBeNull();expect(notificationEventKeyForStatus('__proto__')).toBeNull();expect(shouldShowReturnsWorkbench(false,1)).toBe(true);});
});
