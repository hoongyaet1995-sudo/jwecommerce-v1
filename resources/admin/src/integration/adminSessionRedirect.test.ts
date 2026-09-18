import { describe, expect, it, vi } from 'vitest';
import { installAdminSessionRedirect } from './adminSessionRedirect';

describe('global Admin session redirect', () => {
  it('redirects an unauthenticated Admin API request to login with the current Admin URL', async () => {
    const fetcher = vi.fn().mockResolvedValue(new Response(null, { status: 401 }));
    const location = { pathname: '/admin/marketplace/auth', search: '?tab=stores', hash: '#connected', replace: vi.fn() };
    const request = installAdminSessionRedirect(fetcher, location);

    await request('/admin/api/marketplace/connections', { credentials: 'same-origin' });

    expect(location.replace).toHaveBeenCalledWith('/admin/login?intended=%2Fadmin%2Fmarketplace%2Fauth%3Ftab%3Dstores%23connected');
  });

  it('redirects only once when concurrent Admin requests receive an expired-session response', async () => {
    const fetcher = vi.fn().mockResolvedValue(new Response(null, { status: 419 }));
    const location = { pathname: '/admin/catalog', search: '', hash: '', replace: vi.fn() };
    const request = installAdminSessionRedirect(fetcher, location);

    await Promise.all([request('/admin/api/catalog'), request('/admin/api/dashboard')]);

    expect(location.replace).toHaveBeenCalledOnce();
  });

  it('does not redirect for ordinary API failures or non-Admin requests', async () => {
    const fetcher = vi.fn()
      .mockResolvedValueOnce(new Response(null, { status: 403 }))
      .mockResolvedValueOnce(new Response(null, { status: 401 }));
    const location = { pathname: '/admin/catalog', search: '', hash: '', replace: vi.fn() };
    const request = installAdminSessionRedirect(fetcher, location);

    await request('/admin/api/catalog');
    await request('/customer/api/orders');

    expect(location.replace).not.toHaveBeenCalled();
  });
});
