import { afterEach, describe, expect, it, vi } from 'vitest';
import type { LaravelAdminBootstrap } from '../types';
import { disconnectEasyParcelStore } from './easyParcelProviderData';

const bootstrap = {
  user: { name: 'Admin', email: 'admin@example.test', avatar: null },
  csrfToken: 'csrf-token',
  logoutUrl: '/logout',
  easyParcelProviderUrl: '/admin/api/shipping/providers/easyparcel',
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
});
