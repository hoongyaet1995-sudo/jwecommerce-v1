import type { FulfillmentStatus, LaravelAdminBootstrap, Order, PaymentStatus } from '../types';

type OrderCollectionResponse = { orders: Order[] };
type OrderResponse = { data: Order };
type ErrorResponse = { message?: string; errors?: Record<string, string[]> };

const errorMessage = (payload: ErrorResponse | null, fallback: string): string =>
  payload?.errors ? Object.values(payload.errors).flat().join(' ') : payload?.message ?? fallback;

export const fetchOrders = async (bootstrap: LaravelAdminBootstrap): Promise<Order[]> => {
  if (!bootstrap.ordersUrl) throw new Error('Order API is unavailable.');
  const response = await window.fetch(bootstrap.ordersUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
  if (!response.ok) throw new Error('Order data request failed.');
  const payload = await response.json() as OrderCollectionResponse;
  if (!Array.isArray(payload.orders)) throw new Error('Invalid order data response.');
  return payload.orders;
};

const mutateOrder = async (bootstrap: LaravelAdminBootstrap, path: string, method: string, body: unknown): Promise<Order> => {
  if (!bootstrap.ordersUrl) throw new Error('Order API is unavailable.');
  const response = await window.fetch(`${bootstrap.ordersUrl}/${path}`, {
    method, credentials: 'same-origin',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': bootstrap.csrfToken },
    body: JSON.stringify(body),
  });
  const payload = await response.json().catch(() => null) as (OrderResponse & ErrorResponse) | null;
  if (!response.ok || !payload?.data) throw new Error(errorMessage(payload, 'Order update failed.'));
  return payload.data;
};

export const updateOrderStatusData = (bootstrap: LaravelAdminBootstrap, orderId: string, paymentStatus?: PaymentStatus, fulfillmentStatus?: FulfillmentStatus, note?: string): Promise<Order> =>
  mutateOrder(bootstrap, `${encodeURIComponent(orderId)}/status`, 'PATCH', { paymentStatus, fulfillmentStatus, note });

export const updateOrderTrackingData = (bootstrap: LaravelAdminBootstrap, orderId: string, carrier: string, trackingNumber: string): Promise<Order> =>
  mutateOrder(bootstrap, `${encodeURIComponent(orderId)}/tracking`, 'PATCH', { carrier, trackingNumber });

export const processOrderRefundData = (bootstrap: LaravelAdminBootstrap, orderId: string, amount: number, reason: string): Promise<Order> =>
  mutateOrder(bootstrap, `${encodeURIComponent(orderId)}/refund`, 'POST', { amount, reason });
