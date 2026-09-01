import { afterEach, describe, expect, it, vi } from 'vitest';
import type { LaravelAdminBootstrap } from '../types';
import {
  fetchOrderNotificationDispatches,
  previewOrderNotification,
  resendNotificationDispatch,
  retryNotificationDispatch,
  sendCurrentOrderNotification,
} from './orderNotificationData';

const bootstrap = {
  user: { name: 'Notifications Admin', email: 'notifications@example.test', avatar: null },
  csrfToken: 'csrf-token',
  logoutUrl: '/logout',
  orderNotificationsUrl: '/admin/api/orders',
} satisfies LaravelAdminBootstrap;

const dispatch = {
  id: 'dispatch-1', eventKey: 'payment_verified', channel: 'email', state: 'failed', source: 'automatic',
  subject: 'Payment verified', templateVersion: 2, attemptCount: 4, queuedAt: '2026-08-31T00:00:00Z',
  sentAt: null, failedAt: '2026-08-31T00:01:00Z', errorCode: 'transport', resendOfId: null, createdAt: '2026-08-31T00:00:00Z',
};

const sentDispatch = {
  id: 'dispatch-sent',
  eventKey: 'payment_verified',
  channel: 'email',
  state: 'sent',
  source: 'automatic',
  subject: 'Payment verified',
  templateVersion: 2,
  attemptCount: 1,
  queuedAt: '2026-08-31T00:00:00Z',
  sentAt: '2026-08-31T00:01:00Z',
  failedAt: null,
  errorCode: null,
  resendOfId: null,
  createdAt: '2026-08-31T00:00:00Z',
};

const resendDispatch = {
  id: 'dispatch-resend',
  eventKey: 'payment_verified',
  channel: 'email',
  state: 'queued',
  source: 'manual_resend',
  subject: 'Payment verified',
  templateVersion: 2,
  attemptCount: 0,
  queuedAt: '2026-09-01T00:05:00.000000Z',
  sentAt: null,
  failedAt: null,
  errorCode: null,
  resendOfId: 'dispatch-sent',
  createdAt: '2026-09-01T00:05:00.000000Z',
};

const preview = {
  eventKey: 'payment_verified', subject: 'Payment verified', preheader: 'Thank you', heading: 'Payment received',
  body: 'Hello Buyer', ctaText: 'Track order', ctaUrl: 'https://ecom.jwaistudio.online/order-tracking',
  variables: { customer_name: 'Buyer', tracking_url: 'https://ecom.jwaistudio.online/order-tracking' },
};

const jsonResponse = (payload: unknown, status = 200) => new Response(JSON.stringify(payload), {
  status,
  headers: { 'Content-Type': 'application/json' },
});

afterEach(() => vi.unstubAllGlobals());

describe('Laravel order notification adapter', () => {
  it('uses order-scoped preview/audit endpoints and separate retry, resend, and current-template operations', async () => {
    const fetch = vi.fn()
      .mockResolvedValueOnce(jsonResponse({ data: [dispatch] }))
      .mockResolvedValueOnce(jsonResponse({ data: preview }))
      .mockResolvedValueOnce(jsonResponse({ data: { ...dispatch, state: 'queued', failedAt: null, errorCode: null } }))
      .mockResolvedValueOnce(jsonResponse({ data: resendDispatch }, 201))
      .mockResolvedValueOnce(jsonResponse({ data: { ...dispatch, id: 'dispatch-3', state: 'queued', source: 'admin_current_template' } }, 201));
    vi.stubGlobal('window', { fetch });

    await expect(fetchOrderNotificationDispatches(bootstrap, '17')).resolves.toEqual([dispatch]);
    await expect(previewOrderNotification(bootstrap, '17', { eventKey: 'payment_verified', staffNote: 'Packed safely.' })).resolves.toEqual(preview);
    await expect(retryNotificationDispatch(bootstrap, 'dispatch-1')).resolves.toEqual(expect.objectContaining({
      id: 'dispatch-1', state: 'queued', failedAt: null, errorCode: null,
    }));
    await expect(resendNotificationDispatch(bootstrap, 'dispatch-sent')).resolves.toEqual(expect.objectContaining({
      id: 'dispatch-resend', state: 'queued', source: 'manual_resend', resendOfId: 'dispatch-sent', attemptCount: 0,
      queuedAt: '2026-09-01T00:05:00.000000Z', createdAt: '2026-09-01T00:05:00.000000Z', failedAt: null, errorCode: null,
    }));
    await expect(sendCurrentOrderNotification(bootstrap, '17', { eventKey: 'payment_verified', staffNote: 'Packed safely.' })).resolves.toEqual(expect.objectContaining({ id: 'dispatch-3', state: 'queued' }));

    expect(fetch).toHaveBeenNthCalledWith(1, '/admin/api/orders/17/notification-dispatches', expect.objectContaining({ method: 'GET', credentials: 'same-origin' }));
    expect(fetch).toHaveBeenNthCalledWith(2, '/admin/api/orders/17/notifications/preview', expect.objectContaining({
      method: 'POST', body: JSON.stringify({ eventKey: 'payment_verified', staffNote: 'Packed safely.' }),
      credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': 'csrf-token' },
    }));
    expect(fetch).toHaveBeenNthCalledWith(3, '/admin/api/notification-dispatches/dispatch-1/retry', expect.objectContaining({
      method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': 'csrf-token' },
    }));
    expect(fetch).toHaveBeenNthCalledWith(4, '/admin/api/notification-dispatches/dispatch-sent/resend', expect.objectContaining({
      method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': 'csrf-token' },
    }));
    expect(fetch).toHaveBeenNthCalledWith(5, '/admin/api/orders/17/notifications/send-current', expect.objectContaining({
      method: 'POST', body: JSON.stringify({ eventKey: 'payment_verified', staffNote: 'Packed safely.' }),
      credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': 'csrf-token' },
    }));
  });

  it('returns validation fields but hides unsafe backend failures', async () => {
    const fetch = vi.fn()
      .mockResolvedValueOnce(jsonResponse({ message: 'private authorization reason' }, 403))
      .mockResolvedValueOnce(jsonResponse({ errors: { eventKey: ['The selected event key is invalid.'] } }, 422))
      .mockResolvedValueOnce(jsonResponse({ message: 'private rate rule' }, 429))
      .mockResolvedValueOnce(jsonResponse({ message: 'provider secret leaked' }, 503));
    vi.stubGlobal('window', { fetch });

    await expect(fetchOrderNotificationDispatches(bootstrap, '17')).rejects.toThrow('You do not have permission to manage notifications.');
    await expect(previewOrderNotification(bootstrap, '17', { eventKey: 'payment_verified' })).rejects.toMatchObject({
      message: 'The selected event key is invalid.',
      errors: { eventKey: ['The selected event key is invalid.'] },
    });
    await expect(retryNotificationDispatch(bootstrap, 'dispatch-1')).rejects.toThrow('Too many notification requests. Please try again shortly.');
    await expect(resendNotificationDispatch(bootstrap, 'dispatch-1')).rejects.toThrow('Notification service is unavailable. Please try again later.');
  });
});
