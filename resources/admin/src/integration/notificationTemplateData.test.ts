import { afterEach, describe, expect, it, vi } from 'vitest';
import type { LaravelAdminBootstrap } from '../types';
import {
  fetchNotificationTemplates,
  resetNotificationTemplates,
  saveNotificationTemplate,
  sendNotificationTest,
} from './notificationTemplateData';

const bootstrap = {
  user: { name: 'Notifications Admin', email: 'notifications@example.test', avatar: null },
  csrfToken: 'csrf-token',
  logoutUrl: '/logout',
  notificationTemplatesUrl: '/admin/api/notification-templates',
} satisfies LaravelAdminBootstrap;

const template = {
  eventKey: 'order_created',
  statusName: 'Pending Payment',
  color: 'amber',
  automaticEmail: true,
  variables: ['{customer_name}', '{tracking_url}'],
  emailEnabled: true,
  emailSubject: 'Order #{order_number}',
  emailPreheader: 'Thank you',
  emailHeading: 'Order received',
  emailBody: 'Hello {customer_name}',
  emailCtaText: 'Track order',
  emailCtaUrl: '{tracking_url}',
  whatsappEnabled: false,
  whatsappMessage: 'Order #{order_number}',
  whatsappCtaButtons: [],
  version: 3,
  updatedById: 8,
  updatedAt: '2026-08-31T00:00:00.000000Z',
};

const dispatch = {
  id: 'dispatch-test',
  eventKey: 'order_created',
  channel: 'email',
  state: 'queued',
  source: 'test',
  subject: 'Order #JW-17',
  templateVersion: 3,
  attemptCount: 0,
  queuedAt: '2026-08-31T00:00:00.000000Z',
  sentAt: null,
  failedAt: null,
  errorCode: null,
  resendOfId: null,
  createdAt: '2026-08-31T00:00:00.000000Z',
};

const jsonResponse = (payload: unknown, status = 200) => new Response(JSON.stringify(payload), {
  status,
  headers: { 'Content-Type': 'application/json' },
});

afterEach(() => vi.unstubAllGlobals());

describe('Laravel notification template adapter', () => {
  it('uses the protected same-origin template collection and CSRF writes', async () => {
    const fetch = vi.fn()
      .mockResolvedValueOnce(jsonResponse({ data: [template] }))
      .mockResolvedValueOnce(jsonResponse({ data: template }))
      .mockResolvedValueOnce(jsonResponse({ data: [template] }))
      .mockResolvedValueOnce(jsonResponse({ data: dispatch }, 201));
    vi.stubGlobal('window', { fetch });

    await expect(fetchNotificationTemplates(bootstrap)).resolves.toEqual([template]);
    await expect(saveNotificationTemplate(bootstrap, 'order_created', { emailSubject: 'Updated', version: 3 })).resolves.toEqual(template);
    await expect(resetNotificationTemplates(bootstrap)).resolves.toEqual([template]);
    await expect(sendNotificationTest(bootstrap, 'order_created', 'test@example.test')).resolves.toEqual(dispatch);

    expect(fetch).toHaveBeenNthCalledWith(1, '/admin/api/notification-templates', {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    });
    expect(fetch).toHaveBeenNthCalledWith(2, '/admin/api/notification-templates/order_created', expect.objectContaining({
      method: 'PUT',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': 'csrf-token' },
      body: JSON.stringify({ emailSubject: 'Updated', version: 3 }),
    }));
    expect(fetch).toHaveBeenNthCalledWith(3, '/admin/api/notification-templates/reset', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': 'csrf-token' },
      body: JSON.stringify({}),
    });
    expect(fetch).toHaveBeenNthCalledWith(4, '/admin/api/notification-templates/order_created/test', expect.objectContaining({
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': 'csrf-token' },
      body: JSON.stringify({ recipientEmail: 'test@example.test' }),
    }));
  });

  it('keeps 422 field messages but never surfaces private backend text for authorization, throttling, or server failures', async () => {
    const fetch = vi.fn()
      .mockResolvedValueOnce(jsonResponse({ message: 'private policy detail' }, 403))
      .mockResolvedValueOnce(jsonResponse({ message: 'invalid', errors: { emailSubject: ['A subject is required.'] } }, 422))
      .mockResolvedValueOnce(jsonResponse({ message: 'private throttle rule' }, 429))
      .mockResolvedValueOnce(jsonResponse({ message: 'SMTP password=secret' }, 500));
    vi.stubGlobal('window', { fetch });

    await expect(fetchNotificationTemplates(bootstrap)).rejects.toThrow('You do not have permission to manage notifications.');
    await expect(saveNotificationTemplate(bootstrap, 'order_created', { emailSubject: '' })).rejects.toMatchObject({
      message: 'A subject is required.',
      errors: { emailSubject: ['A subject is required.'] },
    });
    await expect(resetNotificationTemplates(bootstrap)).rejects.toThrow('Too many notification requests. Please try again shortly.');
    await expect(sendNotificationTest(bootstrap, 'order_created', 'test@example.test')).rejects.toThrow('Notification service is unavailable. Please try again later.');
  });
});
