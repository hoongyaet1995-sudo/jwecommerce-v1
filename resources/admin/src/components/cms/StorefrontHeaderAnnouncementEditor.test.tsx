import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { StorefrontShellSettings } from '../../types';

const admin = vi.hoisted(() => ({
  save: vi.fn(),
  settings: null as StorefrontShellSettings | null,
}));

vi.mock('../../context/AdminContext', () => ({
  useAdmin: () => ({
    storefrontShellSettings: admin.settings,
    saveStorefrontShellSettings: admin.save,
    setCurrentSection: vi.fn(),
    headerMenus: [],
  }),
}));

const shell = (layout: StorefrontShellSettings['layout'] = 'atelier'): StorefrontShellSettings => ({
  enabled: true,
  layout,
  theme: 'midnight-obsidian',
  customPalette: { background: null, text: null, accent: null },
  zones: {
    left: { enabled: false, text: 'Retained hidden left', badge: 'Hidden', linkUrl: '/about' },
    center: { enabled: true, text: 'Visible center', couponCode: 'LIVE', linkUrl: '/offers' },
    right: {
      enabled: false,
      helperText: 'Retained hidden right',
      trackingEnabled: false,
      trackingLabel: 'Track order',
      trackingUrl: '/account',
      whatsappEnabled: false,
      whatsappLabel: 'WhatsApp us',
      whatsappUrl: 'https://wa.me/60123456789',
    },
  },
});

describe('StorefrontHeaderAnnouncementEditor persistence boundary', () => {
  beforeEach(() => {
    admin.save.mockReset();
    admin.settings = shell();
  });

  it('round-trips each persisted zone enabled flag without deriving it from text', async () => {
    const editor = await import('./StorefrontHeaderAnnouncementEditor');
    const persisted = shell();
    const draft = editor.announcementDraftFromSettings(persisted);
    const rebuilt = editor.storefrontShellSettingsFromDraft(persisted, draft, 'atelier');

    expect(rebuilt.zones.left.enabled).toBe(false);
    expect(rebuilt.zones.center.enabled).toBe(true);
    expect(rebuilt.zones.right.enabled).toBe(false);
  });

  it('renders Save Live Changes disabled and does not invoke persistence for a preview-only layout', async () => {
    const editor = await import('./StorefrontHeaderAnnouncementEditor');
    const previewOnly = shell('floating-pill');
    admin.settings = previewOnly;

    const html = renderToStaticMarkup(<editor.StorefrontHeaderAnnouncementEditor />);
    expect(html).toMatch(/<button[^>]*disabled=""[^>]*title="Only Modern Atelier/);

    await editor.persistStorefrontShellDraft(previewOnly, admin.save);
    expect(admin.save).not.toHaveBeenCalled();
  });

  it('renders the ZIP-owned inline desktop/mobile simulator and retains its mobile frame contract', async () => {
    const editor = await import('./StorefrontHeaderAnnouncementEditor');
    const source = readFileSync(resolve(process.cwd(), 'src/components/cms/StorefrontHeaderAnnouncementEditor.tsx'), 'utf8');
    const PreviewableEditor = editor.StorefrontHeaderAnnouncementEditor as React.ComponentType<{
      initialTab: 'preview';
      initialPreviewViewport: 'mobile';
    }>;

    const html = renderToStaticMarkup(
      <PreviewableEditor initialTab="preview" initialPreviewViewport="mobile" />,
    );

    expect(source).toContain("setPreviewViewport('desktop')");
    expect(source).toContain("setPreviewViewport('mobile')");
    expect(source).toContain("previewViewport === 'mobile' ? 'max-w-md' : 'w-full'");
    expect(html).toContain('Desktop View');
    expect(html).toContain('Mobile View');
    expect(html).toContain('max-w-md');
    expect(html).toContain('Live Storefront Customer Simulation Viewport');
  });
});
