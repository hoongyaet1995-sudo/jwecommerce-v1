import type { Category, Collection, LaravelAdminBootstrap, Product } from '../types';

export interface CatalogData {
  categories: Category[];
  collections: Collection[];
  products: Product[];
}

type CatalogEntity = Category | Collection | Product;

const apiRequest = async <T>(bootstrap: LaravelAdminBootstrap, url: string, method: string, body?: unknown): Promise<T | null> => {
  const response = await window.fetch(url, {
    method,
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      ...(body === undefined ? {} : { 'Content-Type': 'application/json' }),
      ...(method === 'GET' ? {} : { 'X-CSRF-TOKEN': bootstrap.csrfToken }),
    },
    ...(body === undefined ? {} : { body: JSON.stringify(body) }),
  });

  if (response.status === 204) return null;
  const payload = await response.json().catch(() => null) as { data?: T; message?: string; errors?: Record<string, string[]> } | null;
  if (!response.ok) {
    const message = payload?.errors
      ? Object.values(payload.errors).flat().join(' ')
      : payload?.message ?? 'Catalog request failed.';
    throw new Error(message);
  }
  return (payload?.data ?? payload) as T;
};

export const fetchCatalog = async (bootstrap: LaravelAdminBootstrap): Promise<CatalogData> => {
  if (!bootstrap.catalogUrl) throw new Error('Catalog API is unavailable.');
  const data = await apiRequest<CatalogData>(bootstrap, bootstrap.catalogUrl, 'GET');
  if (!data || !Array.isArray(data.categories) || !Array.isArray(data.collections) || !Array.isArray(data.products)) {
    throw new Error('Catalog API returned an invalid document.');
  }
  return data;
};

const entityUrl = (bootstrap: LaravelAdminBootstrap, entity: 'categories' | 'collections' | 'products', id?: string): string =>
  `${bootstrap.catalogUrl}/${entity}${id ? `/${encodeURIComponent(id)}` : ''}`;

const isPersisted = (id: string): boolean => /^\d+$/.test(id);

export const saveCatalogEntity = async <T extends CatalogEntity>(
  bootstrap: LaravelAdminBootstrap,
  entity: 'categories' | 'collections' | 'products',
  document: T,
): Promise<T> => {
  const persisted = isPersisted(document.id);
  const result = await apiRequest<T>(bootstrap, entityUrl(bootstrap, entity, persisted ? document.id : undefined), persisted ? 'PATCH' : 'POST', document);
  if (!result) throw new Error('Catalog save response was empty.');
  return result;
};

export const deleteCatalogEntity = async (
  bootstrap: LaravelAdminBootstrap,
  entity: 'categories' | 'collections' | 'products',
  id: string,
): Promise<void> => {
  await apiRequest(bootstrap, entityUrl(bootstrap, entity, id), 'DELETE');
};
