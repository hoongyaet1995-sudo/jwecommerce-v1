// @vitest-environment jsdom
import React, { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type {
  LaravelAdminBootstrap,
  NotificationDispatch,
  NotificationEventKey,
  NotificationPermission,
  NotificationTemplate,
} from '../../types';

const mockedAdminContext = vi.hoisted(() => ({ value: {} as Record<string, unknown> }));

vi.mock('../../context/AdminContext', () => ({
  useAdmin: () => mockedAdminContext.value,
}));

import { NotificationTemplateSettingAdmin } from './NotificationTemplateSettingAdmin';
import { SETTING_TABS, ShopSettingsHub } from './ShopSettingsHub';

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

const bootstrap = {
  user: {
    name: 'Notifications Admin',
    email: 'notifications@example.test',
    avatar: null,
    notificationPermissions: [
      'notifications.templates.view',
      'notifications.templates.manage',
      'notifications.test.send',
    ],
  },
  notificationPermissions: [
    'notifications.templates.view',
    'notifications.templates.manage',
    'notifications.test.send',
  ],
  csrfToken: 'csrf-token',
  logoutUrl: '/logout',
  notificationTemplatesUrl: '/admin/api/notification-templates',
} satisfies LaravelAdminBootstrap;

const permissions = bootstrap.notificationPermissions satisfies NotificationPermission[];

const template = (
  eventKey: NotificationEventKey,
  statusName: string,
  overrides: Partial<NotificationTemplate> = {},
): NotificationTemplate => ({
  eventKey,
  statusName,
  color: 'indigo',
  automaticEmail: eventKey !== 'on_hold',
  variables: [
    '{customer_name}',
    '{order_number}',
    '{order_url}',
    '{tracking_url}',
    '{status_name}',
    '{staff_note}',
    '{shop_name}',
    '{shop_email}',
  ],
  emailEnabled: eventKey !== 'on_hold',
  emailSubject: `[JW Studio] ${statusName} for #{order_number}`,
  emailPreheader: `${statusName} update`,
  emailHeading: statusName,
  emailBody: 'Dear {customer_name},\n\nOrder #{order_number} is now {status_name}.',
  emailCtaText: 'Track order',
  emailCtaUrl: '{tracking_url}',
  whatsappEnabled: true,
  whatsappMessage: 'Hi {customer_name}, order #{order_number} is now {status_name}.',
  whatsappCtaButtons: [{ id: 'track', text: 'Track order', type: 'url', value: '{tracking_url}' }],
  version: 4,
  updatedById: 8,
  updatedAt: '2026-08-31T00:00:00.000000Z',
  ...overrides,
});

const initialTemplates = (): NotificationTemplate[] => [
  template('order_created', 'Pending Payment', { color: 'amber' }),
  template('payment_verified', 'Processing & Verified'),
  template('payment_proof_rejected', 'Payment Proof Rejected', {
    color: 'rose',
    variables: [
      '{customer_name}',
      '{order_number}',
      '{order_url}',
      '{tracking_url}',
      '{payment_proof_reason}',
      '{shop_name}',
      '{shop_email}',
    ],
  }),
  template('ready_to_ship', 'Packed & AWB Generated', { color: 'cyan' }),
  template('shipped', 'Dispatched (In Transit)', { color: 'blue' }),
  template('delivered', 'Delivered & Completed', { color: 'emerald' }),
  template('cancelled', 'Cancelled & Restocked', { color: 'rose' }),
  template('on_hold', 'On Hold', {
    automaticEmail: false,
    emailEnabled: false,
    color: 'purple',
  }),
];

const jsonResponse = (payload: unknown, status = 200) => new Response(JSON.stringify(payload), {
  status,
  headers: { 'Content-Type': 'application/json' },
});

class NotificationTemplateApi {
  templates = initialTemplates();
  testDispatches = 0;

  readonly fetch = vi.fn(async (input: RequestInfo | URL, init: RequestInit = {}) => {
    const url = String(input);
    const method = init.method ?? 'GET';

    if (url === '/admin/api/notification-templates' && method === 'GET') {
      return jsonResponse({ data: this.templates.map(item => ({ ...item })) });
    }

    if (url === '/admin/api/notification-templates/reset' && method === 'POST') {
      this.templates = initialTemplates().map(item => ({
        ...item,
        emailSubject: item.eventKey === 'order_created'
          ? '[JW Studio] Reset order #{order_number}'
          : item.emailSubject,
        version: item.version + 1,
      }));
      return jsonResponse({ data: this.templates.map(item => ({ ...item })) });
    }

    const match = url.match(/^\/admin\/api\/notification-templates\/([^/]+)(\/test)?$/);
    if (!match) return jsonResponse({ message: 'Not found' }, 404);

    const eventKey = decodeURIComponent(match[1]) as NotificationEventKey;
    const current = this.templates.find(item => item.eventKey === eventKey);
    if (!current) return jsonResponse({ message: 'Not found' }, 404);

    if (match[2] === '/test' && method === 'POST') {
      this.testDispatches += 1;
      const dispatch: NotificationDispatch = {
        id: `test-${this.testDispatches}`,
        eventKey,
        channel: 'email',
        state: 'queued',
        source: 'test',
        subject: current.emailSubject,
        templateVersion: current.version,
        attemptCount: 0,
        queuedAt: '2026-08-31T00:01:00.000000Z',
        sentAt: null,
        failedAt: null,
        errorCode: null,
        resendOfId: null,
        createdAt: '2026-08-31T00:01:00.000000Z',
      };
      return jsonResponse({ data: dispatch }, 201);
    }

    if (method === 'PUT') {
      const body = JSON.parse(String(init.body ?? '{}')) as Partial<NotificationTemplate>;
      if (body.emailSubject === '') {
        return jsonResponse({
          message: 'Invalid template',
          errors: { emailSubject: ['A subject is required.'] },
        }, 422);
      }

      if (body.version !== current.version) {
        return jsonResponse({ message: 'Stale template version.' }, 409);
      }

      Object.assign(current, body, { version: current.version + 1 });
      return jsonResponse({ data: { ...current } });
    }

    return jsonResponse({ message: 'Not found' }, 404);
  });
}

const mountedRoots: Array<{ root: Root; host: HTMLDivElement }> = [];

const mount = async (node: React.ReactNode = <NotificationTemplateSettingAdmin />) => {
  const host = document.createElement('div');
  document.body.append(host);
  const root = createRoot(host);
  mountedRoots.push({ root, host });

  await act(async () => {
    root.render(node);
  });

  await vi.waitFor(() => expect(host.textContent).toContain('Pending Payment'));
  return { host, root };
};

const button = (host: HTMLElement, label: string): HTMLButtonElement => {
  const candidates = Array.from(host.querySelectorAll('button'));
  const match = candidates.find(candidate => candidate.textContent?.trim() === label)
    ?? candidates.find(candidate => candidate.textContent?.includes(label));
  if (!match) throw new Error(`Button not found: ${label}`);
  return match as HTMLButtonElement;
};

const inputByPlaceholder = (host: HTMLElement, placeholder: string): HTMLInputElement => {
  const match = host.querySelector(`input[placeholder="${placeholder}"]`);
  if (!match) throw new Error(`Input not found: ${placeholder}`);
  return match as HTMLInputElement;
};

const change = async (element: HTMLInputElement | HTMLTextAreaElement, value: string) => {
  await act(async () => {
    const setter = Object.getOwnPropertyDescriptor(
      element instanceof HTMLTextAreaElement ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype,
      'value',
    )?.set;
    setter?.call(element, value);
    element.dispatchEvent(new Event('input', { bubbles: true }));
    element.dispatchEvent(new Event('change', { bubbles: true }));
  });
};

beforeEach(() => {
  const api = new NotificationTemplateApi();
  vi.stubGlobal('fetch', api.fetch);
  mockedAdminContext.value = {
    laravelBootstrap: bootstrap,
    notificationPermissions: permissions,
    currentSection: 'settings-email',
    setCurrentSection: vi.fn(),
    shopSettings: {
      shopName: 'JW Studio',
      shopLogo: '',
      email: 'support@jwstudio.my',
      phone: '+60 3-8888 1234',
      whatsapp: '+60 12-345 6789',
    },
    saveShopSettings: vi.fn(),
    addToast: vi.fn(),
  };
  (mockedAdminContext.value as { api?: NotificationTemplateApi }).api = api;
});

afterEach(() => {
  mountedRoots.splice(0).forEach(({ root, host }) => {
    act(() => root.unmount());
    host.remove();
  });
  localStorage.clear();
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
});

describe('NotificationTemplateSettingAdmin real-data binding', () => {
  it('loads the complete API matrix including payment rejection and dormant On Hold', async () => {
    const { host } = await mount();

    expect(host.textContent).toContain('Payment Proof Rejected');
    expect(host.textContent).toContain('On Hold');
    expect(host.textContent).toContain('DORMANT');
    expect(host.textContent).toContain('8 lifecycle statuses configured');
  });

  it('saves the current API template with its optimistic version and reloads persisted server truth after remount', async () => {
    const { host, root } = await mount();
    const api = (mockedAdminContext.value as { api: NotificationTemplateApi }).api;
    localStorage.setItem('aurora_admin_notification_templates', JSON.stringify([{ emailSubject: 'Browser-owned stale value' }]));
    const subject = inputByPlaceholder(host, 'e.g. [{shop_name}] Order #{order_number} Update: {status_name}');

    await change(subject, 'Persisted API subject #{order_number}');
    await act(async () => button(host, 'Save Status Template').click());
    await vi.waitFor(() => expect(host.textContent).toContain('Saved'));

    expect(api.templates[0].emailSubject).toBe('Persisted API subject #{order_number}');
    expect(api.templates[0].version).toBe(5);

    await act(async () => root.unmount());
    mountedRoots.splice(0, 1);
    const remounted = await mount();
    const remountedSubject = inputByPlaceholder(remounted.host, 'e.g. [{shop_name}] Order #{order_number} Update: {status_name}');
    expect(remountedSubject.value).toBe('Persisted API subject #{order_number}');
    expect(remountedSubject.value).not.toContain('Browser-owned stale value');
  });

  it('resets templates only after explicit confirmation', async () => {
    const { host } = await mount();
    const api = (mockedAdminContext.value as { api: NotificationTemplateApi }).api;
    const confirm = vi.spyOn(window, 'confirm').mockReturnValueOnce(false).mockReturnValueOnce(true);

    await act(async () => button(host, 'Reset to Defaults').click());
    expect(api.templates[0].emailSubject).not.toContain('Reset order');

    await act(async () => button(host, 'Reset to Defaults').click());
    await vi.waitFor(() => expect(inputByPlaceholder(
      host,
      'e.g. [{shop_name}] Order #{order_number} Update: {status_name}',
    ).value).toBe('[JW Studio] Reset order #{order_number}'));

    expect(confirm).toHaveBeenCalledTimes(2);
  });

  it('shows API validation messages without replacing the editor', async () => {
    const { host } = await mount();
    const subject = inputByPlaceholder(host, 'e.g. [{shop_name}] Order #{order_number} Update: {status_name}');

    await change(subject, '');
    await act(async () => button(host, 'Save Status Template').click());

    await vi.waitFor(() => expect(host.textContent).toContain('A subject is required.'));
    expect(host.textContent).toContain('Email Subject Line');
  });

  it('inserts only API-allowlisted variables at the active cursor', async () => {
    const { host } = await mount();
    const subject = inputByPlaceholder(host, 'e.g. [{shop_name}] Order #{order_number} Update: {status_name}');
    const original = subject.value;

    await act(async () => {
      subject.focus();
      subject.setSelectionRange(4, 4);
    });
    await act(async () => button(host, '{customer_name}').click());
    await vi.waitFor(() => expect(subject.value).toBe(`${original.slice(0, 4)}{customer_name}${original.slice(4)}`));
    await act(async () => new Promise(resolve => setTimeout(resolve, 60)));

    await act(async () => button(host, 'Payment Proof Rejected').click());
    await vi.waitFor(() => expect(host.textContent).toContain('Proof Rejection Reason'));
    expect(Array.from(host.querySelectorAll('button')).some(candidate => candidate.textContent?.includes('{payment_proof_upload_url}'))).toBe(false);
    expect(Array.from(host.querySelectorAll('button')).some(candidate => candidate.textContent?.includes('{payment_proof_reason}'))).toBe(true);
  });

  it('preserves the desktop and mobile email preview toggle', async () => {
    const { host } = await mount();
    const desktop = host.querySelector('button[title="Desktop Preview"]') as HTMLButtonElement;
    const mobile = host.querySelector('button[title="Mobile Preview"]') as HTMLButtonElement;

    expect(desktop.className).toContain('bg-white');
    await act(async () => mobile.click());
    expect(mobile.className).toContain('bg-white');
    expect(desktop.className).not.toContain('bg-white');

    await act(async () => desktop.click());
    expect(desktop.className).toContain('bg-white');
  });

  it('queues an Email test and reports Queued rather than delivered', async () => {
    const { host } = await mount();
    const api = (mockedAdminContext.value as { api: NotificationTemplateApi }).api;

    await act(async () => button(host, 'Send Test Notification').click());
    const recipient = inputByPlaceholder(host, 'admin@jwstudio.my');
    await change(recipient, 'qa@example.test');
    await act(async () => button(host, 'Send Test').click());

    await vi.waitFor(() => expect(host.textContent).toContain('Queued'));
    expect(host.textContent).not.toContain('delivered');
    expect(api.testDispatches).toBe(1);
  });

  it('never dispatches or simulates WhatsApp delivery', async () => {
    const { host } = await mount();
    const api = (mockedAdminContext.value as { api: NotificationTemplateApi }).api;

    await act(async () => button(host, 'WhatsApp Message').click());
    await act(async () => button(host, 'Send Test Notification').click());
    await act(async () => button(host, 'Send Test').click());

    expect(host.textContent).toContain('Not connected');
    expect(host.textContent).not.toContain('successfully sent');
    expect(api.testDispatches).toBe(0);
  });

  it('honors bootstrap permissions and preserves the operator-disabled On Hold email state', async () => {
    mockedAdminContext.value = {
      ...mockedAdminContext.value,
      notificationPermissions: ['notifications.templates.view'],
    };
    const { host } = await mount();

    expect(button(host, 'Save Status Template').disabled).toBe(true);
    expect(button(host, 'Reset to Defaults').disabled).toBe(true);
    expect(button(host, 'Send Test Notification').disabled).toBe(true);

    await act(async () => button(host, 'On Hold').click());
    const emailToggle = host.querySelector('input[type="checkbox"]') as HTMLInputElement;
    expect(emailToggle.checked).toBe(false);
    expect(emailToggle.disabled).toBe(true);
  });

  it('mounts inside the existing Settings shell under the approved tab label', async () => {
    expect(SETTING_TABS.find(tab => tab.id === 'settings-email')?.label).toBe('Auto Email & WhatsApp Template');
    const { host } = await mount(<ShopSettingsHub />);
    expect(host.textContent).toContain('Auto Email & WhatsApp Template');
    expect(host.textContent).toContain('Auto Email & WhatsApp Templates');
  });
});
