import type { LaravelAdminBootstrap } from '../types';

export interface CatalogListingSettings {
  eyebrow: string;
  title: string;
  description: string;
}

type Request = (input: RequestInfo | URL, init?: RequestInit) => Promise<Pick<Response, 'ok' | 'json'>>;

const isSettings = (value: unknown): value is CatalogListingSettings => {
  if (typeof value !== 'object' || value === null) return false;
  const settings = value as Record<string, unknown>;
  return typeof settings.eyebrow === 'string'
    && typeof settings.title === 'string'
    && typeof settings.description === 'string';
};

const unavailable = () => new Error('Catalog listing settings API is unavailable.');

export const loadCatalogListingSettings = async (
  bootstrap: LaravelAdminBootstrap = window.__JW_ADMIN__ ?? { user: { name: '', email: '', avatar: null }, csrfToken: '', logoutUrl: '' },
  request: Request = window.fetch.bind(window),
): Promise<CatalogListingSettings> => {
  if (!bootstrap.catalogListingSettingsUrl) throw unavailable();
  const response = await request(bootstrap.catalogListingSettingsUrl, {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  });
  const data: unknown = await response.json();
  if (!response.ok || !isSettings(data)) throw new Error('Catalog listing settings request failed.');
  return data;
};

export const saveCatalogListingSettings = async (
  bootstrap: LaravelAdminBootstrap,
  settings: CatalogListingSettings,
  request: Request = window.fetch.bind(window),
): Promise<CatalogListingSettings> => {
  if (!bootstrap.catalogListingSettingsUrl) throw unavailable();
  const response = await request(bootstrap.catalogListingSettingsUrl, {
    method: 'PUT',
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': bootstrap.csrfToken,
    },
    body: JSON.stringify(settings),
  });
  const data: unknown = await response.json().catch(() => null);
  if (!response.ok || !isSettings(data)) {
    throw new Error(
      typeof data === 'object' && data !== null && typeof (data as Record<string, unknown>).message === 'string'
        ? (data as Record<string, string>).message
        : 'Catalog listing settings save failed.',
    );
  }
  return data;
};
