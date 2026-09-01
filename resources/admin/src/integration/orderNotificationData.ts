import type {
  LaravelAdminBootstrap,
  NotificationDispatch,
  OrderNotificationAction,
  OrderNotificationPreview,
} from '../types';
import { notificationHeaders, notificationRequest } from './notificationRequest';

const ordersUrl = (bootstrap: LaravelAdminBootstrap): string => {
  if (!bootstrap.orderNotificationsUrl) throw new Error('Order notification API is unavailable.');
  return bootstrap.orderNotificationsUrl;
};

const orderUrl = (bootstrap: LaravelAdminBootstrap, orderId: string): string =>
  `${ordersUrl(bootstrap)}/${encodeURIComponent(orderId)}`;

const dispatchUrl = (dispatchId: string): string =>
  `/admin/api/notification-dispatches/${encodeURIComponent(dispatchId)}`;

export const fetchOrderNotificationDispatches = (
  bootstrap: LaravelAdminBootstrap,
  orderId: string,
): Promise<NotificationDispatch[]> => notificationRequest(`${orderUrl(bootstrap, orderId)}/notification-dispatches`, {
  method: 'GET',
  credentials: 'same-origin',
  headers: { Accept: 'application/json' },
}, 'Order notification history could not be loaded.');

export const previewOrderNotification = (
  bootstrap: LaravelAdminBootstrap,
  orderId: string,
  action: OrderNotificationAction,
): Promise<OrderNotificationPreview> => notificationRequest(`${orderUrl(bootstrap, orderId)}/notifications/preview`, {
  method: 'POST',
  credentials: 'same-origin',
  headers: notificationHeaders(bootstrap),
  body: JSON.stringify(action),
}, 'Order notification preview could not be loaded.');

export const retryNotificationDispatch = (
  bootstrap: LaravelAdminBootstrap,
  dispatchId: string,
): Promise<NotificationDispatch> => notificationRequest(`${dispatchUrl(dispatchId)}/retry`, {
  method: 'POST',
  credentials: 'same-origin',
  headers: notificationHeaders(bootstrap),
  body: JSON.stringify({}),
}, 'Notification retry could not be queued.');

export const sendQueuedNotificationDispatch = (
  bootstrap: LaravelAdminBootstrap,
  dispatchId: string,
): Promise<NotificationDispatch> => notificationRequest(`${dispatchUrl(dispatchId)}/send-now`, {
  method: 'POST',
  credentials: 'same-origin',
  headers: notificationHeaders(bootstrap),
  body: JSON.stringify({}),
}, 'Queued Email could not be sent.');

export const resendNotificationDispatch = (
  bootstrap: LaravelAdminBootstrap,
  dispatchId: string,
): Promise<NotificationDispatch> => notificationRequest(`${dispatchUrl(dispatchId)}/resend`, {
  method: 'POST',
  credentials: 'same-origin',
  headers: notificationHeaders(bootstrap),
  body: JSON.stringify({}),
}, 'Notification resend could not be queued.');

export const sendCurrentOrderNotification = (
  bootstrap: LaravelAdminBootstrap,
  orderId: string,
  action: OrderNotificationAction,
): Promise<NotificationDispatch> => notificationRequest(`${orderUrl(bootstrap, orderId)}/notifications/send-current`, {
  method: 'POST',
  credentials: 'same-origin',
  headers: notificationHeaders(bootstrap),
  body: JSON.stringify(action),
}, 'Current notification template could not be queued.');
