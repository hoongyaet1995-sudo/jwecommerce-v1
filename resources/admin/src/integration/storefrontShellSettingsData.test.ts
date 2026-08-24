import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it, vi } from 'vitest';
import type { StorefrontShellSettings } from '../types';

const bootstrap = {
  user: { name: 'Julian Admin', email: 'julian@example.test', avatar: null },
  csrfToken: 'csrf-token',
  logoutUrl: '/logout',
  storefrontShellSettingsUrl: '/admin/api/storefront-shell-settings',
};

const payload: StorefrontShellSettings = {
  enabled: false,
  layout: 'atelier',
  theme: 'midnight-obsidian',
  customPalette: {
    background: null,
    text: null,
    accent: null,
  },
  zones: {
    left: {
      enabled: false,
      text: '',
      badge: '',
      linkUrl: null,
    },
    center: {
      enabled: false,
      text: '',
      couponCode: '',
      linkUrl: null,
    },
    right: {
      enabled: false,
      helperText: '',
      trackingEnabled: false,
      trackingLabel: '',
      trackingUrl: null,
      whatsappEnabled: false,
      whatsappLabel: '',
      whatsappUrl: null,
    },
  },
};

describe('Laravel storefront shell settings adapter', () => {
  it('loads the saved live shell payload from the protected Laravel endpoint', async () => {
    const module = await import('./storefrontShellSettingsData').catch(() => null);
    const request = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => payload,
    });

    expect(module).not.toBeNull();
    if (!module) {
      return;
    }

    await expect(module.loadStorefrontShellSettings(bootstrap, request)).resolves.toMatchObject({
      layout: 'atelier',
    });
    expect(request).toHaveBeenCalledWith('/admin/api/storefront-shell-settings', {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    });
  });

  it('fails explicitly when the Laravel bootstrap omits the protected API URL', async () => {
    const module = await import('./storefrontShellSettingsData');
    const request = vi.fn();

    await expect(module.loadStorefrontShellSettings({
      user: bootstrap.user,
      csrfToken: bootstrap.csrfToken,
      logoutUrl: bootstrap.logoutUrl,
    }, request)).rejects.toThrow('Storefront shell settings API is unavailable.');
    expect(request).not.toHaveBeenCalled();
  });

  it('sends disabled zone flags to Laravel without reconstructing them from their copy', async () => {
    const module = await import('./storefrontShellSettingsData');
    const settings = {
      ...payload,
      enabled: true,
      zones: {
        left: { ...payload.zones.left, enabled: false, text: 'Retained hidden left copy' },
        center: { ...payload.zones.center, enabled: false, text: 'Retained hidden center copy' },
        right: {
          ...payload.zones.right,
          enabled: false,
          helperText: 'Retained hidden utility copy',
          trackingEnabled: false,
          whatsappEnabled: false,
        },
      },
    };
    const request = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => settings,
    });

    await module.saveStorefrontShellSettingsData(bootstrap, settings, request);

    const init = request.mock.calls[0]?.[1] as RequestInit;
    const sent = JSON.parse(String(init.body));
    expect(sent.zones.left.enabled).toBe(false);
    expect(sent.zones.center.enabled).toBe(false);
    expect(sent.zones.right.enabled).toBe(false);
  });

  it('wires the new Header & Announcement admin route into the app shell', () => {
    const appSource = readFileSync(resolve(process.cwd(), 'src/App.tsx'), 'utf8');
    const sidebarSource = readFileSync(resolve(process.cwd(), 'src/components/layout/AdminSidebar.tsx'), 'utf8');

    expect(appSource).toContain("case 'storefront-header-announcement':");
    expect(sidebarSource).toContain('Header & Announcement');
  });

  it('loads the source-faithful editor and permits live persistence only for Atelier', async () => {
    const editor = await import('../components/cms/StorefrontHeaderAnnouncementEditor').catch(() => null);

    expect(editor).not.toBeNull();
    if (!editor) {
      return;
    }

    expect(editor.canSaveStorefrontShellLayout('atelier')).toBe(true);
    expect(editor.canSaveStorefrontShellLayout('centered-luxury')).toBe(false);
    expect(editor.canSaveStorefrontShellLayout('floating-pill')).toBe(false);
    expect(editor.canSaveStorefrontShellLayout('mega-catalog')).toBe(false);
    expect(editor.canSaveStorefrontShellLayout('editorial-drawer')).toBe(false);
  });
});
