import { afterEach, describe, expect, it, vi } from 'vitest';
import type { LaravelAdminBootstrap } from '../types';
import { saveEasyParcelCredentials } from './easyParcelProviderData';

const bootstrap = {
  user: { name: 'Admin', email: 'admin@example.test', avatar: null },
  csrfToken: 'csrf-token',
  logoutUrl: '/logout',
  easyParcelProviderUrl: '/admin/api/shipping/providers/easyparcel',
} satisfies LaravelAdminBootstrap;

describe('EasyParcel provider client', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('saves sandbox credentials through Laravel and never expects credentials in the response', async () => {
    const fetch = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ data: { provider: 'easyparcel', environment: 'sandbox', configured: true, enabled: false, connectionState: 'unverified', lastVerifiedAt: null, lastError: null } }),
    });
    vi.stubGlobal('window', { fetch });

    const provider = await saveEasyParcelCredentials(bootstrap, {
      environment: 'sandbox',
      clientId: 'client-id',
      clientSecret: 'client-secret',
      enabled: false,
    });

    expect(fetch).toHaveBeenCalledWith('/admin/api/shipping/providers/easyparcel', expect.objectContaining({
      method: 'PUT',
      credentials: 'same-origin',
      body: JSON.stringify({ environment: 'sandbox', clientId: 'client-id', clientSecret: 'client-secret', enabled: false }),
    }));
    expect(provider).toMatchObject({ provider: 'easyparcel', configured: true, connectionState: 'unverified' });
    expect(provider).not.toHaveProperty('clientSecret');
  });
});
