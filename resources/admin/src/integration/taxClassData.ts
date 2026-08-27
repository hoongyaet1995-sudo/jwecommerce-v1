import type { LaravelAdminBootstrap } from '../types';

export interface StoreTaxClass {
  id: string;
  name: string;
  ratePercent: number;
  isActive: boolean;
  createdAt?: string;
  updatedAt?: string;
}

export interface TaxClassDocument {
  classes: StoreTaxClass[];
  activeTaxClassId: string | null;
}

type Payload<T> = { data: T; message?: string; errors?: Record<string, string[]> };

const baseUrl = (bootstrap: Pick<LaravelAdminBootstrap, 'taxClassesUrl'>): string => {
  if (!bootstrap.taxClassesUrl) throw new Error('Tax Classes API is unavailable.');
  return bootstrap.taxClassesUrl;
};

const headers = (bootstrap: Pick<LaravelAdminBootstrap, 'csrfToken'>): HeadersInit => ({
  Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': bootstrap.csrfToken,
});

const request = async <T>(bootstrap: Pick<LaravelAdminBootstrap, 'taxClassesUrl' | 'csrfToken'>, path = '', method = 'GET', body?: unknown): Promise<T> => {
  const response = await fetch(`${baseUrl(bootstrap)}${path}`, {
    method,
    credentials: 'same-origin',
    headers: method === 'GET' ? { Accept: 'application/json' } : headers(bootstrap),
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const payload = await response.json().catch(() => null) as Payload<T> | null;
  if (!response.ok || !payload?.data) {
    const message = payload?.errors ? Object.values(payload.errors).flat().join(' ') : payload?.message ?? 'Tax Classes request failed.';
    throw new Error(message);
  }
  return payload.data;
};

export const fetchTaxClasses = (bootstrap: Pick<LaravelAdminBootstrap, 'taxClassesUrl' | 'csrfToken'>): Promise<TaxClassDocument> => request(bootstrap);
export const createTaxClass = (bootstrap: Pick<LaravelAdminBootstrap, 'taxClassesUrl' | 'csrfToken'>, taxClass: Pick<StoreTaxClass, 'name' | 'ratePercent' | 'isActive'>): Promise<TaxClassDocument> => request(bootstrap, '', 'POST', taxClass);
export const updateTaxClass = (bootstrap: Pick<LaravelAdminBootstrap, 'taxClassesUrl' | 'csrfToken'>, taxClass: StoreTaxClass): Promise<TaxClassDocument> => request(bootstrap, `/${taxClass.id}`, 'PATCH', taxClass);
export const activateTaxClass = (bootstrap: Pick<LaravelAdminBootstrap, 'taxClassesUrl' | 'csrfToken'>, id: string): Promise<TaxClassDocument> => request(bootstrap, `/${id}/activate`, 'POST');
export const deactivateTaxClass = (bootstrap: Pick<LaravelAdminBootstrap, 'taxClassesUrl' | 'csrfToken'>, id: string): Promise<TaxClassDocument> => request(bootstrap, `/${id}/deactivate`, 'POST');
export const deleteTaxClass = (bootstrap: Pick<LaravelAdminBootstrap, 'taxClassesUrl' | 'csrfToken'>, id: string): Promise<TaxClassDocument> => request(bootstrap, `/${id}`, 'DELETE');
