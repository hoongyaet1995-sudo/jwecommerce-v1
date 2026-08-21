import type { FooterMenuL1, HeaderMenuL1, LaravelAdminBootstrap } from '../types';

export interface MenuData {
  headerMenus: HeaderMenuL1[];
  footerMenus: FooterMenuL1[];
}

const decode = async <T>(response: Response): Promise<T> => {
  const payload = await response.json().catch(() => null) as { data?: T; message?: string; errors?: Record<string, string[]> } | null;
  if (!response.ok) {
    const message = payload?.errors ? Object.values(payload.errors).flat().join(' ') : payload?.message ?? 'Menu request failed.';
    throw new Error(message);
  }
  return (payload?.data ?? payload) as T;
};

export const fetchMenus = async (bootstrap: LaravelAdminBootstrap): Promise<MenuData> => {
  if (!bootstrap.menusUrl) throw new Error('Menu API is unavailable.');
  const response = await window.fetch(bootstrap.menusUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
  return decode<MenuData>(response);
};

export const saveMenuDocument = async <T extends HeaderMenuL1[] | FooterMenuL1[]>(
  bootstrap: LaravelAdminBootstrap,
  key: 'header' | 'footer',
  items: T,
): Promise<T> => {
  if (!bootstrap.menusUrl) throw new Error('Menu API is unavailable.');
  const response = await window.fetch(`${bootstrap.menusUrl}/${key}`, {
    method: 'PUT', credentials: 'same-origin',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': bootstrap.csrfToken },
    body: JSON.stringify({ items }),
  });
  return decode<T>(response);
};
