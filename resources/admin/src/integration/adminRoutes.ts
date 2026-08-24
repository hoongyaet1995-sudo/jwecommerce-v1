import type { NavSection } from '../types';

export const ADMIN_PATH_BY_SECTION = {
  dashboard: '/admin',
  commerce: '/admin/commerce',
  orders: '/admin/orders',
  customers: '/admin/customers',
  'shipping-malaysia': '/admin/shipping',
  catalog: '/admin/catalog',
  'catalog-products': '/admin/catalog/products',
  'catalog-categories': '/admin/catalog/categories',
  'catalog-collections': '/admin/catalog/collections',
  'catalog-reviews': '/admin/catalog/reviews',
  promotions: '/admin/promotions',
  marketplace: '/admin/marketplace',
  'marketplace-auth': '/admin/marketplace/auth',
  'marketplace-oms': '/admin/marketplace/oms',
  'marketplace-inventory': '/admin/marketplace/inventory',
  'marketplace-sync': '/admin/marketplace/sync',
  storefront: '/admin/storefront',
  'pages-builder': '/admin/pages',
  'menu-header': '/admin/menus/header',
  'menu-footer': '/admin/menus/footer',
  support: '/admin/support',
  'ai-auto-reply': '/admin/ai-auto-reply',
  reports: '/admin/reports',
  'reports-sales': '/admin/reports/sales',
  'reports-orders': '/admin/reports/orders',
  'reports-products': '/admin/reports/products',
  'reports-customers': '/admin/reports/customers',
  'reports-shipping': '/admin/reports/shipping',
  'reports-tax': '/admin/reports/tax',
  settings: '/admin/settings',
  'settings-users': '/admin/settings/users',
  'settings-shop': '/admin/settings/shop',
  'settings-payment': '/admin/settings/payment',
  'settings-points': '/admin/settings/points',
  'settings-invoice': '/admin/settings/invoice',
  'settings-email': '/admin/settings/email',
  'settings-social': '/admin/settings/social',
  'settings-general': '/admin/settings/general',
  'settings-courier': '/admin/settings/couriers',
  'settings-agent-group': '/admin/settings/agent-groups',
  'settings-orders-status': '/admin/settings/order-status',
  'settings-countries': '/admin/settings/countries',
  'settings-zones': '/admin/settings/zones',
  'settings-currencies': '/admin/settings/currencies',
  'settings-order-total': '/admin/settings/order-total',
  'settings-tax-zones': '/admin/settings/tax-zones',
  'settings-ai-agent': '/admin/settings/ai-agent',
  'architecture-spec': '/admin/developer-specs',
} satisfies Record<NavSection, string>;

type NavigationHistory = {
  pushState: (data: unknown, unused: string, url?: string | URL | null) => void;
};

type NavigationWindow = {
  history: NavigationHistory;
};

type PopstateWindow = {
  location: { pathname: string };
  addEventListener: (type: 'popstate', listener: () => void) => void;
  removeEventListener: (type: 'popstate', listener: () => void) => void;
};

type LocationWindow = {
  location: { pathname: string };
};

const SECTION_BY_ADMIN_PATH = new Map<string, NavSection>(
  Object.entries(ADMIN_PATH_BY_SECTION).map(([section, path]) => [path, section as NavSection]),
);

function normalizedPath(pathname: string): string {
  const parsedPath = new URL(pathname, 'http://jw-admin.local').pathname;
  return parsedPath === '/admin/' ? '/admin' : parsedPath.replace(/\/$/, '');
}

export function sectionFromPath(pathname: string): NavSection | null {
  return SECTION_BY_ADMIN_PATH.get(normalizedPath(pathname)) ?? null;
}

export function pathForSection(section: NavSection): string {
  return ADMIN_PATH_BY_SECTION[section];
}

export function initialSectionFromLocation(
  navigationWindow: LocationWindow = window,
): NavSection {
  return sectionFromPath(navigationWindow.location.pathname) ?? 'dashboard';
}

export function navigateToSection(
  section: NavSection,
  navigationWindow: NavigationWindow = window,
): void {
  navigationWindow.history.pushState(null, '', pathForSection(section));
}

export function subscribeToSectionChanges(
  onSection: (section: NavSection) => void,
  navigationWindow: PopstateWindow = window,
): () => void {
  const onPopstate = () => {
    const section = sectionFromPath(navigationWindow.location.pathname);
    if (section) onSection(section);
  };

  navigationWindow.addEventListener('popstate', onPopstate);
  return () => navigationWindow.removeEventListener('popstate', onPopstate);
}
