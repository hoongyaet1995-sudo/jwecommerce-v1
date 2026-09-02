// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { LaravelAdminBootstrap } from '../types';
import { courierConfigsFromEasyParcel, disconnectEasyParcelStore, saveEasyParcelSenderProfile, startEasyParcelAuthorization } from './easyParcelProviderData';

const bootstrap = {
  user: { name: 'Admin', email: 'admin@example.test', avatar: null },
  csrfToken: 'csrf-token',
  logoutUrl: '/logout',
  easyParcelProviderUrl: '/admin/api/shipping/providers/easyparcel',
  easyParcelProviderAuthorizeUrl: '/admin/api/shipping/providers/easyparcel/authorize',
} satisfies LaravelAdminBootstrap;

describe('EasyParcel provider client', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('presents the provider operating environment on the EasyParcel courier card', () => {
    const [easyParcel] = courierConfigsFromEasyParcel({
      provider: 'easyparcel',
      environment: 'production',
      configured: true,
      enabled: true,
      connectionState: 'connected',
      lastVerifiedAt: '2026-08-31T05:51:58+00:00',
      lastError: null,
    });

    expect(easyParcel.environment).toBe('production');
  });

  it('disconnects the authorized store without sending platform credentials from the browser', async () => {
    const fetch = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ data: { provider: 'easyparcel', environment: 'sandbox', configured: true, enabled: false, connectionState: 'unverified', lastVerifiedAt: null, lastError: null } }),
    });
    vi.stubGlobal('window', { fetch });

    const provider = await disconnectEasyParcelStore(bootstrap);

    expect(fetch).toHaveBeenCalledWith('/admin/api/shipping/providers/easyparcel', expect.objectContaining({
      method: 'DELETE',
      credentials: 'same-origin',
    }));
    expect(provider).toMatchObject({ provider: 'easyparcel', configured: true, connectionState: 'unverified' });
    expect(provider).not.toHaveProperty('clientSecret');
  });

  it('sends only the selected production environment through the existing server-managed authorization form', () => {
    const submitted = vi.spyOn(HTMLFormElement.prototype, 'submit').mockImplementation(() => undefined);

    startEasyParcelAuthorization(bootstrap, 'production');

    const form = document.querySelector('form[action="/admin/api/shipping/providers/easyparcel/authorize"]') as HTMLFormElement;
    expect(form).not.toBeNull();
    expect(new FormData(form).get('environment')).toBe('production');
    expect(form.innerHTML).not.toContain('client_secret');
    expect(submitted).toHaveBeenCalledOnce();
    form.remove();
  });

  it('saves only manual sender data and the selected environment without browser OAuth credentials', async () => {
    const fetch = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ data: { provider: 'easyparcel', environment: 'production', configured: true, enabled: true, connectionState: 'connected', lastVerifiedAt: null, lastError: null, senderProfile: { name: 'JW Studio Dispatch' }, senderProfileComplete: true } }),
    });
    vi.stubGlobal('window', { fetch });

    await saveEasyParcelSenderProfile(bootstrap, 'production', {
      name: 'JW Studio Dispatch',
      address_1: '18 Test Industry Road',
    });

    const options = fetch.mock.calls[0][1] as RequestInit;
    expect(fetch).toHaveBeenCalledWith('/admin/api/shipping/providers/easyparcel', expect.objectContaining({ method: 'PUT' }));
    expect(JSON.parse(String(options.body))).toEqual({
      environment: 'production',
      senderProfile: { name: 'JW Studio Dispatch', address_1: '18 Test Industry Road' },
    });
    expect(String(options.body)).not.toContain('access_token');
    expect(String(options.body)).not.toContain('client_secret');
  });
});
