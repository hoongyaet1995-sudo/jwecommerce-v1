// @vitest-environment jsdom
import React, { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type {
  LaravelAdminBootstrap,
  NotificationDispatch,
  NotificationEventKey,
  NotificationPermission,
  Order,
  OrderNotificationSummary,
} from '../../types';

const mockedAdminContext = vi.hoisted(() => ({ value: {} as Record<string, unknown> }));

vi.mock('../../context/AdminContext', () => ({
  useAdmin: () => mockedAdminContext.value,
}));

import { OrderNotificationPanel } from './OrderNotificationPanel';

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

const allPermissions: NotificationPermission[] = [
  'notifications.deliveries.view',
  'notifications.deliveries.retry',
  'notifications.deliveries.resend',
  'notifications.current.send',
];

const bootstrap = {
  user: {
    name: 'Order Admin',
    email: 'orders@example.test',
    avatar: null,
    notificationPermissions: allPermissions,
  },
  notificationPermissions: allPermissions,
  csrfToken: 'csrf-token',
  logoutUrl: '/logout',
  orderNotificationsUrl: '/admin/api/orders',
} satisfies LaravelAdminBootstrap;

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
  timeline: [],
  ...overrides,
});

const dispatch = (
  id: string,
  state: NotificationDispatch['state'],
  overrides: Partial<NotificationDispatch> = {},
): NotificationDispatch => ({
  id,
  eventKey: 'shipped',
  channel: 'email',
  state,
  source: 'automatic',
  subject: `Shipment update ${id}`,
  templateVersion: 4,
  attemptCount: state === 'queued' ? 0 : 1,
  queuedAt: '2026-08-31T08:01:00.000Z',
  sentAt: state === 'sent' ? '2026-08-31T08:02:00.000Z' : null,
  failedAt: state === 'failed' ? '2026-08-31T08:02:00.000Z' : null,
  errorCode: state === 'failed' ? 'transport' : null,
  resendOfId: null,
  createdAt: '2026-08-31T08:01:00.000Z',
  ...overrides,
});

const jsonResponse = (payload: unknown, status = 200) => new Response(JSON.stringify(payload), {
  status,
  headers: { 'Content-Type': 'application/json' },
});

class OrderNotificationApi {
  dispatches: NotificationDispatch[] = [dispatch('queued-1', 'queued')];
  auditReads = 0;
  actionRequests: Array<{ url: string; body: Record<string, unknown> }> = [];
  previewRequests: Array<{ eventKey: NotificationEventKey; staffNote?: string | null }> = [];
  auditGate: Promise<void> | null = null;
  actionGate: Promise<void> | null = null;
  previewGates = new Map<NotificationEventKey, Promise<void>>();
  actionFailure: { status: number; payload: unknown } | null = null;

  readonly fetch = vi.fn(async (input: RequestInfo | URL, init: RequestInit = {}) => {
    const url = String(input);
    const method = init.method ?? 'GET';

    if (url === '/admin/api/orders/17/notification-dispatches' && method === 'GET') {
      this.auditReads += 1;
      if (this.auditGate) {
        const gate = this.auditGate;
        this.auditGate = null;
        await gate;
      }
      return jsonResponse({ data: this.dispatches.map(item => ({ ...item })) });
    }

    if (url === '/admin/api/orders/17/notifications/preview' && method === 'POST') {
      const body = JSON.parse(String(init.body ?? '{}')) as { eventKey: NotificationEventKey; staffNote?: string | null };
      this.previewRequests.push(body);
      const gate = this.previewGates.get(body.eventKey);
      if (gate) {
        this.previewGates.delete(body.eventKey);
        await gate;
      }
      return jsonResponse({
        data: {
          eventKey: body.eventKey,
          subject: `${body.eventKey} for JW-10017`,
          preheader: 'Order update',
          heading: `${body.eventKey} heading`,
          body: `Order JW-10017 note: ${body.staffNote || '(no custom note)'}`,
          ctaText: 'Track order',
          ctaUrl: 'https://ecom.jwaistudio.online/order-tracking',
          variables: {
            order_number: 'JW-10017',
            staff_note: body.staffNote || '(no custom note)',
          },
        },
      });
    }

    const actionMatch = url.match(/^\/admin\/api\/notification-dispatches\/([^/]+)\/(retry|resend)$/);
    const sendCurrent = url === '/admin/api/orders/17/notifications/send-current';
    if ((actionMatch || sendCurrent) && method === 'POST') {
      const body = JSON.parse(String(init.body ?? '{}')) as Record<string, unknown>;
      this.actionRequests.push({ url, body });
      if (this.actionGate) {
        const gate = this.actionGate;
        this.actionGate = null;
        await gate;
      }
      if (this.actionFailure) return jsonResponse(this.actionFailure.payload, this.actionFailure.status);

      if (actionMatch?.[2] === 'retry') {
        this.dispatches = this.dispatches.map(item => item.id === actionMatch[1]
          ? { ...item, state: 'queued', failedAt: null, errorCode: null }
          : item);
        return jsonResponse({ data: this.dispatches.find(item => item.id === actionMatch[1]) });
      }

      const next = dispatch(`manual-${this.dispatches.length + 1}`, 'queued', {
        eventKey: sendCurrent ? body.eventKey as NotificationEventKey : 'shipped',
        source: sendCurrent ? 'admin_current_template' : 'manual_resend',
        resendOfId: actionMatch?.[1] ?? null,
        subject: sendCurrent ? `${body.eventKey} current subject` : 'Original frozen subject',
      });
      this.dispatches = [next, ...this.dispatches];
      return jsonResponse({ data: next }, 201);
    }

    return jsonResponse({ message: 'Not found' }, 404);
  });
}

const mountedRoots: Array<{ root: Root; host: HTMLDivElement }> = [];

const renderPanel = async (
  props: Partial<React.ComponentProps<typeof OrderNotificationPanel>> = {},
) => {
  const host = document.createElement('div');
  document.body.append(host);
  const root = createRoot(host);
  mountedRoots.push({ root, host });
  const render = async (next: Partial<React.ComponentProps<typeof OrderNotificationPanel>> = {}) => {
    await act(async () => {
      root.render(
        <OrderNotificationPanel
          order={order()}
          eventKey="shipped"
          notifyCustomer
          staffNote="Packed with care"
          onNotifyCustomerChange={vi.fn()}
          {...props}
          {...next}
        />,
      );
    });
  };
  await render();
  return { host, root, render };
};

const button = (host: HTMLElement, label: string): HTMLButtonElement => {
  const match = Array.from(host.querySelectorAll('button'))
    .find(candidate => candidate.textContent?.trim() === label)
    ?? Array.from(host.querySelectorAll('button'))
      .find(candidate => candidate.textContent?.includes(label));
  if (!match) throw new Error(`Button not found: ${label}`);
  return match as HTMLButtonElement;
};

beforeEach(() => {
  const api = new OrderNotificationApi();
  vi.stubGlobal('fetch', api.fetch);
  mockedAdminContext.value = {
    laravelBootstrap: bootstrap,
    notificationPermissions: allPermissions,
    setCurrentSection: vi.fn(),
    api,
  };
});

afterEach(() => {
  mountedRoots.splice(0).forEach(({ root, host }) => {
    act(() => root.unmount());
    host.remove();
  });
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
});

describe('OrderNotificationPanel real delivery binding', () => {
  it('shows server-owned queued state instead of fabricated sent timeline text', async () => {
    const { host } = await renderPanel();

    await vi.waitFor(() => expect(host.textContent).toContain('Queued'));
    expect(host.textContent).toContain('Shipment update queued-1');
    expect(host.textContent).toContain('Email');
    expect(host.textContent).toContain('Automatic');
    expect(host.textContent).not.toContain('instant customer notification');
    const auditHeading = Array.from(host.querySelectorAll('h4'))
      .find(candidate => candidate.textContent?.includes('Email Delivery Audit'));
    expect(auditHeading?.parentElement?.parentElement?.textContent).not.toContain('amina@example.test');
  });

  it('keeps Retry, Resend, and Send Current Template semantically separate', async () => {
    const api = (mockedAdminContext.value as { api: OrderNotificationApi }).api;
    api.dispatches = [dispatch('failed-1', 'failed'), dispatch('sent-1', 'sent')];
    const confirm = vi.spyOn(window, 'confirm').mockReturnValue(true);
    const { host } = await renderPanel();

    await vi.waitFor(() => expect(button(host, 'Retry')).toBeTruthy());
    const labels = Array.from(host.querySelectorAll('button')).map(candidate => candidate.textContent?.trim());
    expect(labels.filter(label => label === 'Retry')).toHaveLength(1);
    expect(labels.filter(label => label === 'Resend')).toHaveLength(1);
    expect(labels).toContain('Send Current Template');

    await act(async () => button(host, 'Retry').click());
    await vi.waitFor(() => expect(api.actionRequests.some(item => item.url.endsWith('/failed-1/retry'))).toBe(true));

    await act(async () => button(host, 'Resend').click());
    await vi.waitFor(() => expect(api.actionRequests.some(item => item.url.endsWith('/sent-1/resend'))).toBe(true));
    expect(confirm).toHaveBeenCalledWith('Resend this Email using its original frozen content?');

    await act(async () => button(host, 'Send Current Template').click());
    await vi.waitFor(() => expect(api.actionRequests.some(item => item.url.endsWith('/notifications/send-current'))).toBe(true));
    expect(confirm).toHaveBeenCalledWith('Send the current Email template and preview to this customer?');
    expect(api.auditReads).toBeGreaterThanOrEqual(4);
  });

  it('uses Email-only copy and previews the current order, event, and staff note while WhatsApp stays disconnected', async () => {
    const api = (mockedAdminContext.value as { api: OrderNotificationApi }).api;
    const { host } = await renderPanel();

    expect(host.textContent).toContain('Dispatch Email Update to amina@example.test');
    expect(host.textContent).not.toContain('Dispatch Email & WhatsApp Update');

    await act(async () => button(host, 'Preview Message Template').click());
    await vi.waitFor(() => expect(host.textContent).toContain('Order JW-10017 note: Packed with care'));
    expect(host.textContent).toContain('shipped for JW-10017');
    expect(host.textContent).toContain('Not connected');
    expect(host.textContent).not.toContain('successfully sent');
    expect(api.previewRequests.at(-1)).toEqual({ eventKey: 'shipped', staffNote: 'Packed with care' });
  });

  it('disables protected actions when their exact permissions are absent', async () => {
    mockedAdminContext.value = {
      ...mockedAdminContext.value,
      notificationPermissions: ['notifications.deliveries.view'],
    };
    const api = (mockedAdminContext.value as { api: OrderNotificationApi }).api;
    api.dispatches = [dispatch('failed-1', 'failed'), dispatch('sent-1', 'sent')];
    const { host } = await renderPanel();

    await vi.waitFor(() => expect(button(host, 'Retry')).toBeTruthy());
    expect(button(host, 'Retry').disabled).toBe(true);
    expect(button(host, 'Resend').disabled).toBe(true);
    expect(button(host, 'Send Current Template').disabled).toBe(true);
  });

  it('shows loading, empty, validation, and safe failed-action states without inventing delivery success', async () => {
    const api = (mockedAdminContext.value as { api: OrderNotificationApi }).api;
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    let releaseAudit!: () => void;
    api.auditGate = new Promise<void>(resolve => { releaseAudit = resolve; });
    api.dispatches = [];
    const { host } = await renderPanel();

    expect(host.textContent).toContain('Loading Email delivery audit...');
    await act(async () => releaseAudit());
    await vi.waitFor(() => expect(host.textContent).toContain('No Email delivery audit is available for this order.'));

    api.dispatches = [dispatch('sent-1', 'sent')];
    api.actionFailure = {
      status: 422,
      payload: { errors: { eventKey: ['The selected notification event is unavailable.'] } },
    };
    await act(async () => button(host, 'Send Current Template').click());
    await vi.waitFor(() => expect(host.textContent).toContain('The selected notification event is unavailable.'));
    expect(host.textContent).not.toContain('Sent successfully');
  });

  it('ignores an old preview response after the selected event changes', async () => {
    const api = (mockedAdminContext.value as { api: OrderNotificationApi }).api;
    let releaseOldPreview!: () => void;
    api.previewGates.set('payment_verified', new Promise<void>(resolve => { releaseOldPreview = resolve; }));
    const { host, render } = await renderPanel({ eventKey: 'payment_verified', staffNote: 'Old note' });

    await vi.waitFor(() => expect(api.previewRequests.some(item => item.eventKey === 'payment_verified')).toBe(true));
    await render({ eventKey: 'shipped', staffNote: 'New note' });
    await vi.waitFor(() => expect(api.previewRequests.some(item => item.eventKey === 'shipped')).toBe(true));
    await act(async () => button(host, 'Preview Message Template').click());
    await vi.waitFor(() => expect(host.textContent).toContain('Order JW-10017 note: New note'));

    await act(async () => releaseOldPreview());
    expect(host.textContent).toContain('Order JW-10017 note: New note');
    expect(host.textContent).not.toContain('Order JW-10017 note: Old note');
  });

  it('ignores an old action response after an event ABA change', async () => {
    const api = (mockedAdminContext.value as { api: OrderNotificationApi }).api;
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    let releaseOldAction!: () => void;
    api.actionGate = new Promise<void>(resolve => { releaseOldAction = resolve; });
    const { host, render } = await renderPanel({ eventKey: 'payment_verified', staffNote: 'Older A note' });

    await vi.waitFor(() => expect(button(host, 'Send Current Template').disabled).toBe(false));
    act(() => button(host, 'Send Current Template').click());
    await vi.waitFor(() => expect(api.actionRequests).toHaveLength(1));
    await render({ eventKey: 'shipped', staffNote: 'B note' });
    await render({ eventKey: 'payment_verified', staffNote: 'Newer A note' });

    await act(async () => releaseOldAction());
    await act(async () => Promise.resolve());
    expect(host.textContent).not.toContain('Email action state: Queued');
    expect(api.auditReads).toBe(1);
  });

  it('renders a mutation state only from the backend notification summary', async () => {
    const summary: OrderNotificationSummary = {
      publicId: 'summary-queued',
      eventKey: 'shipped',
      channel: 'email',
      state: 'queued',
      source: 'automatic',
      queuedAt: '2026-08-31T08:03:00.000Z',
    };
    const withoutSummary = await renderPanel({ mutationDispatch: null });
    await vi.waitFor(() => expect(withoutSummary.host.textContent).toContain('Shipment update queued-1'));
    expect(withoutSummary.host.textContent).not.toContain('Status update Email intent: Queued');

    const withSummary = await renderPanel({ mutationDispatch: summary });
    await vi.waitFor(() => expect(withSummary.host.textContent).toContain('Status update Email intent: Queued'));
    expect(withSummary.host.textContent).not.toContain('Status update Email intent: Sent');
  });

  it('refetches the delivery audit when refreshed order truth exposes a new notification summary', async () => {
    const api = (mockedAdminContext.value as { api: OrderNotificationApi }).api;
    const { render } = await renderPanel();
    await vi.waitFor(() => expect(api.auditReads).toBe(1));
    const summary: OrderNotificationSummary = {
      publicId: 'summary-from-order-refresh',
      eventKey: 'shipped',
      channel: 'email',
      state: 'queued',
      source: 'automatic',
      queuedAt: '2026-08-31T08:04:00.000Z',
    };

    await render({ order: order({ notificationSummary: summary }) });
    await vi.waitFor(() => expect(api.auditReads).toBe(2));
  });
});
