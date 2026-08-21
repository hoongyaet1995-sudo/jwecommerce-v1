import type { LaravelAdminBootstrap } from '../types';

export interface DashboardData {
  metrics: {
    sales_today: number;
    orders: number;
    customers: number;
    low_stock: number;
    pending_orders: number;
  };
  recentOrders: Array<{
    id: number;
    number: string;
    email: string;
    customerName: string;
    status: string;
    total: number;
    itemCount: number;
    createdAt: string;
  }>;
  lowStockVariants: Array<{
    id: number;
    sku: string;
    title: string;
    stock: number;
    image: string;
    product: { id: number; title: string; handle: string } | null;
  }>;
  revenueTrend: Array<{ label: string; total: number }>;
  categoryCounts: Array<{ id: number; name: string; productsCount: number }>;
}

type DashboardRequest = (input: RequestInfo | URL, init?: RequestInit) => Promise<Pick<Response, 'ok' | 'json'>>;

const isRecord = (value: unknown): value is Record<string, unknown> => typeof value === 'object' && value !== null;

const isDashboardData = (value: unknown): value is DashboardData => {
  if (!isRecord(value) || !isRecord(value.metrics)) return false;

  return typeof value.metrics.sales_today === 'number'
    && typeof value.metrics.orders === 'number'
    && typeof value.metrics.customers === 'number'
    && typeof value.metrics.low_stock === 'number'
    && typeof value.metrics.pending_orders === 'number'
    && Array.isArray(value.recentOrders)
    && Array.isArray(value.lowStockVariants)
    && Array.isArray(value.revenueTrend)
    && Array.isArray(value.categoryCounts);
};

export const fetchDashboardData = async (
  bootstrap: LaravelAdminBootstrap,
  request: DashboardRequest = window.fetch.bind(window),
): Promise<DashboardData | null> => {
  if (!bootstrap.dashboardUrl) return null;

  const response = await request(bootstrap.dashboardUrl, {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  });

  if (!response.ok) throw new Error('Dashboard data request failed');

  const data: unknown = await response.json();

  if (!isDashboardData(data)) throw new Error('Invalid dashboard data response');

  return data;
};
