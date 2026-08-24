import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it, vi } from 'vitest';
import type { HeaderMenuL1, StorefrontShellSettings } from '../../types';
import { StorefrontHeaderPreview } from './StorefrontHeaderPreview';

const settings: StorefrontShellSettings = {
  enabled: true,
  layout: 'atelier',
  theme: 'midnight-obsidian',
  customPalette: { background: null, text: null, accent: null },
  zones: {
    left: { enabled: false, text: 'HIDDEN LEFT COPY', badge: 'HIDDEN', linkUrl: null },
    center: { enabled: true, text: 'VISIBLE CENTER COPY', couponCode: 'LIVE', linkUrl: null },
    right: {
      enabled: false,
      helperText: 'HIDDEN RIGHT COPY',
      trackingEnabled: true,
      trackingLabel: 'HIDDEN TRACKING',
      trackingUrl: '/account',
      whatsappEnabled: true,
      whatsappLabel: 'HIDDEN WHATSAPP',
      whatsappUrl: 'https://wa.me/60123456789',
    },
  },
};

const menu: HeaderMenuL1 = {
  id: 'menu-1',
  title: 'Shop',
  url: '/shop',
  linkType: 'custom_url',
  displayStyle: 'megamenu',
  isActive: true,
  sortOrder: 1,
  columns: [],
};

describe('StorefrontHeaderPreview zone rendering', () => {
  it('omits disabled zones while preserving an enabled zone in the rendered announcement bar', () => {
    const html = renderToStaticMarkup(
      <StorefrontHeaderPreview
        settings={settings}
        utilityState={{
          showSearch: true,
          showThemeToggle: true,
          showCurrency: true,
          showUserAccount: true,
          showCartBadge: true,
          selectedCurrency: 'MYR (RM)',
          cartCount: 2,
        }}
        headerMenus={[menu]}
        onSelectLayout={vi.fn()}
        onUtilityChange={vi.fn()}
        onAnnouncementEnabledChange={vi.fn()}
        onEditAnnouncement={vi.fn()}
      />,
    );

    expect(html).toContain('VISIBLE CENTER COPY');
    expect(html).not.toContain('HIDDEN LEFT COPY');
    expect(html).not.toContain('HIDDEN RIGHT COPY');
    expect(html).not.toContain('HIDDEN WHATSAPP');
  });
});
