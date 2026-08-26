import type { LaravelAdminBootstrap } from '../types';
import type { GeoTaxZone, GlobalTaxEngineSettings, TaxClass, TaxRate } from '../types/oscommerceSettings';

export interface TaxSettingsDocument {
  configuration: {
    isEnabled: boolean;
    isPublished: boolean;
    pricesIncludeTax: boolean;
    addressBasis: 'shipping_address' | 'billing_address' | 'store_origin';
    merchantComplianceMetadata: Record<string, unknown>;
  };
  zones: GeoTaxZone[];
  classes: TaxClass[];
  rules: TaxRate[];
}

type Payload<T> = { data: T; message?: string; errors?: Record<string, string[]> };

const url = (bootstrap: LaravelAdminBootstrap): string => {
  if (!bootstrap.taxSettingsUrl) throw new Error('Tax settings API is unavailable.');
  return bootstrap.taxSettingsUrl;
};

const headers = (bootstrap: LaravelAdminBootstrap): HeadersInit => ({
  Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': bootstrap.csrfToken,
});

const message = (payload: Payload<unknown> | null, fallback: string): string => payload?.errors
  ? Object.values(payload.errors).flat().join(' ')
  : payload?.message ?? fallback;

const request = async <T>(bootstrap: LaravelAdminBootstrap, path = '', method = 'GET', body?: unknown): Promise<T> => {
  const response = await window.fetch(`${url(bootstrap)}${path}`, {
    method, credentials: 'same-origin', headers: method === 'GET' ? { Accept: 'application/json' } : headers(bootstrap),
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const payload = await response.json().catch(() => null) as Payload<T> | null;
  if (!response.ok || !payload?.data) throw new Error(message(payload, 'Tax settings request failed.'));
  return payload.data;
};

const destroy = async (bootstrap: LaravelAdminBootstrap, path: string): Promise<void> => {
  const response = await window.fetch(`${url(bootstrap)}${path}`, { method: 'DELETE', credentials: 'same-origin', headers: headers(bootstrap) });
  if (response.ok) return;
  const payload = await response.json().catch(() => null) as Payload<unknown> | null;
  throw new Error(message(payload, 'Tax settings delete failed.'));
};

export const fetchTaxSettings = (bootstrap: LaravelAdminBootstrap): Promise<TaxSettingsDocument> => request<TaxSettingsDocument>(bootstrap);
export const saveTaxConfiguration = (bootstrap: LaravelAdminBootstrap, configuration: TaxSettingsDocument['configuration']): Promise<TaxSettingsDocument> => request<TaxSettingsDocument>(bootstrap, '/configuration', 'PUT', configuration);
export const saveTaxZone = (bootstrap: LaravelAdminBootstrap, zone: GeoTaxZone): Promise<GeoTaxZone> => request<GeoTaxZone>(bootstrap, `/zones${/^\d+$/.test(zone.id) ? `/${zone.id}` : ''}`, /^\d+$/.test(zone.id) ? 'PATCH' : 'POST', zone);
export const deleteTaxZone = (bootstrap: LaravelAdminBootstrap, id: string): Promise<void> => destroy(bootstrap, `/zones/${id}`);
export const saveTaxClass = (bootstrap: LaravelAdminBootstrap, taxClass: TaxClass): Promise<TaxClass> => request<TaxClass>(bootstrap, `/classes${/^\d+$/.test(taxClass.id) ? `/${taxClass.id}` : ''}`, /^\d+$/.test(taxClass.id) ? 'PATCH' : 'POST', taxClass);
export const deleteTaxClass = (bootstrap: LaravelAdminBootstrap, id: string): Promise<void> => destroy(bootstrap, `/classes/${id}`);
export const saveTaxRule = (bootstrap: LaravelAdminBootstrap, rule: TaxRate): Promise<TaxRate> => request<TaxRate>(bootstrap, `/rules${/^\d+$/.test(rule.id) ? `/${rule.id}` : ''}`, /^\d+$/.test(rule.id) ? 'PATCH' : 'POST', rule);
export const deleteTaxRule = (bootstrap: LaravelAdminBootstrap, id: string): Promise<void> => destroy(bootstrap, `/rules/${id}`);

export const globalTaxEngineFromConfiguration = (configuration: TaxSettingsDocument['configuration'], current: GlobalTaxEngineSettings): GlobalTaxEngineSettings => ({
  ...current,
  pricesIncludeTax: configuration.pricesIncludeTax,
  calculateTaxBasedOn: configuration.addressBasis,
});
