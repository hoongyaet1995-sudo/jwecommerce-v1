import type { FulfillmentStatus, LaravelAdminBootstrap, Order, OrderNotificationSummary, PaymentProofStatus, PaymentStatus } from '../types';

type OrderCollectionResponse = { orders: Order[] };
type OrderResponse = { data: Order };
type ErrorResponse = { message?: string; errors?: Record<string, string[]> };

const errorMessage = (payload: ErrorResponse | null, fallback: string): string =>
  payload?.errors ? Object.values(payload.errors).flat().join(' ') : payload?.message ?? fallback;

const eventKeys = ['order_created', 'payment_verified', 'payment_proof_rejected', 'ready_to_ship', 'shipped', 'delivered', 'cancelled', 'on_hold'];
const dispatchStates = ['queued', 'sending', 'sent', 'failed'];
const dispatchSources = ['automatic', 'admin_current_template', 'manual_resend', 'test'];

const isNotificationSummary = (value: unknown): value is OrderNotificationSummary | null => {
  if (value === null) return true;
  if (!value || typeof value !== 'object') return false;

  const summary = value as Record<string, unknown>;
  return typeof summary.publicId === 'string'
    && typeof summary.eventKey === 'string' && eventKeys.includes(summary.eventKey)
    && summary.channel === 'email'
    && typeof summary.state === 'string' && dispatchStates.includes(summary.state)
    && typeof summary.source === 'string' && dispatchSources.includes(summary.source)
    && (summary.queuedAt === null || typeof summary.queuedAt === 'string');
};

const hasNotificationContract = (value: unknown): value is Order =>
  Boolean(
    value
    && typeof value === 'object'
    && 'notificationContextFingerprint' in value
    && typeof value.notificationContextFingerprint === 'string'
    && 'notificationSummary' in value
    && isNotificationSummary(value.notificationSummary),
  );

export const fetchOrders = async (bootstrap: LaravelAdminBootstrap): Promise<Order[]> => {
  if (!bootstrap.ordersUrl) throw new Error('Order API is unavailable.');
  const response = await window.fetch(bootstrap.ordersUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
  if (!response.ok) throw new Error('Order data request failed.');
  const payload = await response.json() as OrderCollectionResponse;
  if (!Array.isArray(payload.orders) || !payload.orders.every(hasNotificationContract)) throw new Error('Invalid order data response.');
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
  if (!hasNotificationContract(payload.data)) throw new Error('Invalid order data response.');
  return payload.data;
};

export const updateOrderStatusData = (bootstrap: LaravelAdminBootstrap, orderId: string, paymentStatus?: PaymentStatus, fulfillmentStatus?: FulfillmentStatus, note?: string, notifyCustomer?: boolean): Promise<Order> =>
  mutateOrder(bootstrap, `${encodeURIComponent(orderId)}/status`, 'PATCH', { paymentStatus, fulfillmentStatus, note, notifyCustomer });

export const updateOrderTrackingData = (bootstrap: LaravelAdminBootstrap, orderId: string, carrier: string, trackingNumber: string): Promise<Order> =>
  mutateOrder(bootstrap, `${encodeURIComponent(orderId)}/tracking`, 'PATCH', { carrier, trackingNumber });

export const processOrderRefundData = (bootstrap: LaravelAdminBootstrap, orderId: string, amount: number, reason: string): Promise<Order> =>
  mutateOrder(bootstrap, `${encodeURIComponent(orderId)}/refund`, 'POST', { amount, reason });

export const reviewPaymentProofData = (
  bootstrap: LaravelAdminBootstrap,
  orderId: string,
  paymentId: string,
  decision: Extract<PaymentProofStatus, 'verified' | 'rejected'>,
  reason?: string,
): Promise<Order> => mutateOrder(
  bootstrap,
  `${encodeURIComponent(orderId)}/payments/${encodeURIComponent(paymentId)}/proof/review`,
  'POST',
  { decision, reason },
);
