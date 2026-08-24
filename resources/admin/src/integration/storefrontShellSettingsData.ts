import type { LaravelAdminBootstrap, StorefrontShellSettings } from '../types';

type ShellSettingsRequest = (
  input: RequestInfo | URL,
  init?: RequestInit,
) => Promise<Pick<Response, 'ok' | 'json'>>;

const isRecord = (value: unknown): value is Record<string, unknown> =>
  typeof value === 'object' && value !== null;

const isShellSettings = (value: unknown): value is StorefrontShellSettings => {
  if (!isRecord(value) || !isRecord(value.customPalette) || !isRecord(value.zones)) {
    return false;
  }

  const left = value.zones.left;
  const center = value.zones.center;
  const right = value.zones.right;

  return isRecord(left)
    && isRecord(center)
    && isRecord(right)
    && typeof value.enabled === 'boolean'
    && typeof value.layout === 'string'
    && typeof value.theme === 'string'
    && ('background' in value.customPalette)
    && ('text' in value.customPalette)
    && ('accent' in value.customPalette)
    && typeof left.enabled === 'boolean'
    && typeof left.text === 'string'
    && typeof left.badge === 'string'
    && (left.linkUrl === null || typeof left.linkUrl === 'string')
    && typeof center.enabled === 'boolean'
    && typeof center.text === 'string'
    && typeof center.couponCode === 'string'
    && (center.linkUrl === null || typeof center.linkUrl === 'string')
    && typeof right.enabled === 'boolean'
    && typeof right.helperText === 'string'
    && typeof right.trackingEnabled === 'boolean'
    && typeof right.trackingLabel === 'string'
    && (right.trackingUrl === null || typeof right.trackingUrl === 'string')
    && typeof right.whatsappEnabled === 'boolean'
    && typeof right.whatsappLabel === 'string'
    && (right.whatsappUrl === null || typeof right.whatsappUrl === 'string');
};

export const loadStorefrontShellSettings = async (
  bootstrap: LaravelAdminBootstrap = window.__JW_ADMIN__ ?? DEFAULT_BOOTSTRAP_FALLBACK,
  request: ShellSettingsRequest = window.fetch.bind(window),
): Promise<StorefrontShellSettings> => {
  if (!bootstrap.storefrontShellSettingsUrl) {
    throw new Error('Storefront shell settings API is unavailable.');
  }

  const response = await request(bootstrap.storefrontShellSettingsUrl, {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  });

  if (!response.ok) {
    throw new Error('Storefront shell settings request failed.');
  }

  const data: unknown = await response.json();

  if (!isShellSettings(data)) {
    throw new Error('Invalid storefront shell settings response');
  }

  return data;
};

export const saveStorefrontShellSettingsData = async (
  bootstrap: LaravelAdminBootstrap,
  settings: StorefrontShellSettings,
  request: ShellSettingsRequest = window.fetch.bind(window),
): Promise<StorefrontShellSettings> => {
  if (!bootstrap.storefrontShellSettingsUrl) {
    throw new Error('Storefront shell settings API is unavailable.');
  }

  const response = await request(bootstrap.storefrontShellSettingsUrl, {
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

  if (!response.ok || !isShellSettings(data)) {
    const message = isRecord(data) && typeof data.message === 'string'
      ? data.message
      : 'Storefront shell settings save failed.';
    throw new Error(message);
  }

  return data;
};

const DEFAULT_BOOTSTRAP_FALLBACK: LaravelAdminBootstrap = {
  user: {
    name: '',
    email: '',
    avatar: null,
  },
  csrfToken: '',
  logoutUrl: '',
};
