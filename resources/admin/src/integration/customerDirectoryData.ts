import type { Customer, LaravelAdminBootstrap } from '../types';

export interface CustomerDirectoryCustomer {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  avatar: string | null;
  totalOrders: number;
  totalSpent: number;
  currency: string | null;
  registeredAt: string;
  lastOrderAt: string | null;
  status: 'active' | 'vip' | 'blocked' | null;
  tags: string[];
  notes: string | null;
  addresses: Array<{
    id: number;
    isDefault: boolean;
    label: string | null;
    type?: 'shipping' | 'billing';
    firstName: string;
    lastName: string;
    phone: string | null;
    address1: string;
    address2: string | null;
    city: string;
    province: string | null;
    country: string;
    postalCode: string;
  }>;
}

export interface CustomerDirectoryData {
  customers: CustomerDirectoryCustomer[];
  pagination: {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
  };
}

type CustomerDirectoryRequest = (input: RequestInfo | URL, init?: RequestInit) => Promise<Pick<Response, 'ok' | 'json'>>;

const isRecord = (value: unknown): value is Record<string, unknown> => typeof value === 'object' && value !== null;

const isAddress = (value: unknown): boolean => isRecord(value)
  && typeof value.id === 'number'
  && typeof value.isDefault === 'boolean'
  && (typeof value.label === 'string' || value.label === null)
  && (value.type === undefined || value.type === 'shipping' || value.type === 'billing')
  && typeof value.firstName === 'string'
  && typeof value.lastName === 'string'
  && (typeof value.phone === 'string' || value.phone === null)
  && typeof value.address1 === 'string'
  && (typeof value.address2 === 'string' || value.address2 === null)
  && typeof value.city === 'string'
  && (typeof value.province === 'string' || value.province === null)
  && typeof value.country === 'string'
  && typeof value.postalCode === 'string';

const isCustomer = (value: unknown): value is CustomerDirectoryCustomer => {
  if (!isRecord(value) || !Array.isArray(value.addresses) || !Array.isArray(value.tags)) return false;

  return typeof value.id === 'number'
    && typeof value.name === 'string'
    && typeof value.email === 'string'
    && (typeof value.phone === 'string' || value.phone === null)
    && (typeof value.avatar === 'string' || value.avatar === null)
    && typeof value.totalOrders === 'number'
    && typeof value.totalSpent === 'number'
    && (typeof value.currency === 'string' || value.currency === null)
    && typeof value.registeredAt === 'string'
    && (typeof value.lastOrderAt === 'string' || value.lastOrderAt === null)
    && (value.status === null || ['active', 'vip', 'blocked'].includes(String(value.status)))
    && value.tags.every(tag => typeof tag === 'string')
    && (typeof value.notes === 'string' || value.notes === null)
    && value.addresses.every(isAddress);
};

const isCustomerDirectoryData = (value: unknown): value is CustomerDirectoryData => {
  if (!isRecord(value) || !Array.isArray(value.customers) || !isRecord(value.pagination)) return false;

  return value.customers.every(isCustomer)
    && typeof value.pagination.currentPage === 'number'
    && typeof value.pagination.lastPage === 'number'
    && typeof value.pagination.perPage === 'number'
    && typeof value.pagination.total === 'number';
};

export const fetchCustomerDirectoryData = async (
  bootstrap: LaravelAdminBootstrap,
  request: CustomerDirectoryRequest = window.fetch.bind(window),
): Promise<CustomerDirectoryData | null> => {
  if (!bootstrap.customersUrl) return null;

  const response = await request(bootstrap.customersUrl, {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  });

  if (!response.ok) throw new Error('Customer directory data request failed');

  const data: unknown = await response.json();

  if (!isCustomerDirectoryData(data)) throw new Error('Invalid customer directory data response');

  return data;
};

export const customerDirectoryToCustomers = (data: CustomerDirectoryData): Customer[] => data.customers.map(customer => ({
  id: String(customer.id), name: customer.name, email: customer.email, phone: customer.phone ?? '', avatar: customer.avatar ?? undefined,
  totalOrders: customer.totalOrders, totalSpent: customer.totalSpent / 100, currency: customer.currency ?? 'USD',
  registeredAt: customer.registeredAt, lastOrderAt: customer.lastOrderAt ?? undefined, status: customer.status ?? 'active',
  tags: customer.tags, notes: customer.notes ?? undefined,
  addresses: customer.addresses.map(address => ({ id: String(address.id), isDefault: address.isDefault, type: address.type ?? 'shipping',
    firstName: address.firstName, lastName: address.lastName, phone: address.phone ?? '', address1: address.address1,
    address2: address.address2 ?? undefined, city: address.city, province: address.province ?? '', country: address.country, postalCode: address.postalCode })),
}));

const customerWrite = async (bootstrap: LaravelAdminBootstrap, customer: Customer): Promise<Customer> => {
  if (!bootstrap.customersUrl) throw new Error('Customer API is unavailable.');
  const persisted = /^\d+$/.test(customer.id);
  const response = await window.fetch(`${bootstrap.customersUrl}${persisted ? `/${customer.id}` : ''}`, {
    method: persisted ? 'PATCH' : 'POST', credentials: 'same-origin',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': bootstrap.csrfToken },
    body: JSON.stringify(customer),
  });
  const payload = await response.json().catch(() => null) as { data?: CustomerDirectoryCustomer; message?: string; errors?: Record<string,string[]> } | null;
  if (!response.ok || !payload?.data) throw new Error(payload?.errors ? Object.values(payload.errors).flat().join(' ') : payload?.message ?? 'Customer save failed.');
  return customerDirectoryToCustomers({ customers: [payload.data], pagination: { currentPage: 1, lastPage: 1, perPage: 1, total: 1 } })[0];
};

export const saveCustomerData = customerWrite;

export const deleteCustomerData = async (bootstrap: LaravelAdminBootstrap, id: string): Promise<void> => {
  if (!bootstrap.customersUrl) throw new Error('Customer API is unavailable.');
  const response = await window.fetch(`${bootstrap.customersUrl}/${encodeURIComponent(id)}`, {
    method: 'DELETE', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': bootstrap.csrfToken },
  });
  if (!response.ok && response.status !== 204) throw new Error('Customer delete failed.');
};
