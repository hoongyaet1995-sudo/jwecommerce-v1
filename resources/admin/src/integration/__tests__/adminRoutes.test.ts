import { describe, expect, it, vi } from 'vitest';
import type { NavSection } from '../../types';
import {
  ADMIN_PATH_BY_SECTION,
  initialSectionFromLocation,
  navigateToSection,
  pathForSection,
  sectionFromPath,
  subscribeToSectionChanges,
} from '../adminRoutes';

const ALL_ADMIN_SECTIONS = [
  'dashboard',
  'support-overview',
  'support-tiktok-prototype',
  'support-tiktok',
  'support-whatsapp',
  'support-instagram',
  'support-facebook',
  'integration',
  'integration-ai-connect',
  'support',
  'shipping-malaysia',
  'marketplace',
  'marketplace-auth',
  'marketplace-mapping',
  'marketplace-oms',
  'marketplace-inventory',
  'marketplace-sync',
  'storefront',
  'storefront-header-announcement',
  'menu-header',
  'menu-footer',
  'pages-builder',
  'website-design',
  'catalog',
  'catalog-categories',
  'catalog-collections',
  'catalog-products',
  'catalog-reviews',
  'commerce',
  'enquiries',
  'customers',
  'orders',
  'settings',
  'settings-users',
  'settings-search',
  'settings-shop',
  'settings-payment',
  'settings-points',
  'settings-invoice',
  'settings-email',
  'settings-social',
  'settings-general',
  'settings-courier',
  'settings-agent-group',
  'settings-orders-status',
  'settings-shipment-status',
  'settings-countries',
  'settings-zones',
  'settings-currencies',
  'settings-order-total',
  'settings-tax-classes',
  'settings-tax-zones',
  'reports',
  'reports-sales',
  'reports-orders',
  'reports-products',
  'reports-customers',
  'reports-shipping',
  'reports-tax',
  'reports-marketing',
  'ai-auto-reply',
  'ai-improvement',
  'ai-personality',
  'ai-knowledge',
  'ai-guardrails',
  'promotions',
  'settings-ai-agent',
  'architecture-spec',
] as const satisfies readonly NavSection[];

describe('Admin URL adapter', () => {
  it('covers every NavSection exactly once in both directions', () => {
    expect(Object.keys(ADMIN_PATH_BY_SECTION).sort()).toEqual([...ALL_ADMIN_SECTIONS].sort());
    expect(new Set(Object.values(ADMIN_PATH_BY_SECTION)).size).toBe(ALL_ADMIN_SECTIONS.length);

    for (const section of ALL_ADMIN_SECTIONS) {
      expect(sectionFromPath(pathForSection(section))).toBe(section);
    }
  });

  it('maps representative routes and rejects unknown Admin paths', () => {
    expect(pathForSection('dashboard')).toBe('/admin');
    expect(pathForSection('marketplace-oms')).toBe('/admin/marketplace/oms');
    expect(pathForSection('settings-tax-zones')).toBe('/admin/settings/tax-zones');
    expect(pathForSection('catalog-reviews')).toBe('/admin/catalog/reviews');
    expect(sectionFromPath('/admin/pages')).toBe('pages-builder');
    expect(sectionFromPath('/admin/catalog/reviews')).toBe('catalog-reviews');
    expect(sectionFromPath('/admin/not-a-page')).toBeNull();
  });

  it('normalizes trailing slashes, query strings, and hashes', () => {
    expect(sectionFromPath('/admin/')).toBe('dashboard');
    expect(sectionFromPath('/admin/pages/?page=1#section')).toBe('pages-builder');
  });

  it('pushes the canonical path without reloading', () => {
    const pushState = vi.fn();
    navigateToSection('reports-tax', { history: { pushState } });
    expect(pushState).toHaveBeenCalledOnce();
    expect(pushState).toHaveBeenCalledWith(null, '', '/admin/reports/tax');
  });

  it('initializes from the current pathname and falls back only outside the route host', () => {
    expect(initialSectionFromLocation({ location: { pathname: '/admin/catalog/products' } }))
      .toBe('catalog-products');
    expect(initialSectionFromLocation({ location: { pathname: '/not-admin' } })).toBe('dashboard');
  });

  it('subscribes one popstate listener and removes that same listener', () => {
    const addEventListener = vi.fn();
    const removeEventListener = vi.fn();
    const onSection = vi.fn();
    const navigationWindow = {
      location: { pathname: '/admin/orders' },
      addEventListener,
      removeEventListener,
    };

    const unsubscribe = subscribeToSectionChanges(onSection, navigationWindow);
    expect(addEventListener).toHaveBeenCalledOnce();
    expect(addEventListener).toHaveBeenCalledWith('popstate', expect.any(Function));

    const listener = addEventListener.mock.calls[0][1] as () => void;
    listener();
    expect(onSection).toHaveBeenCalledWith('orders');

    unsubscribe();
    expect(removeEventListener).toHaveBeenCalledWith('popstate', listener);
  });
});
