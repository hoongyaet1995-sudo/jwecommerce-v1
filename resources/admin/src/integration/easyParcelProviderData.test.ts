// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { LaravelAdminBootstrap } from '../types';
import { disconnectEasyParcelStore, startEasyParcelAuthorization } from './easyParcelProviderData';

const bootstrap = {
  user: { name: 'Admin', email: 'admin@example.test', avatar: null },
  csrfToken: 'csrf-token',
  logoutUrl: '/logout',
  easyParcelProviderUrl: '/admin/api/shipping/providers/easyparcel',
  easyParcelProviderAuthorizeUrl: '/admin/api/shipping/providers/easyparcel/authorize',
} satisfies LaravelAdminBootstrap;

describe('EasyParcel provider client', () => {
  afterEach(() => vi.unstubAllGlobals());

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
});
