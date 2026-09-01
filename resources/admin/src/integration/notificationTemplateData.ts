import type {
  LaravelAdminBootstrap,
  NotificationDispatch,
  NotificationEventKey,
  NotificationTemplate,
  NotificationTemplateUpdate,
} from '../types';
import { notificationHeaders, notificationRequest } from './notificationRequest';

const templatesUrl = (bootstrap: LaravelAdminBootstrap): string => {
  if (!bootstrap.notificationTemplatesUrl) throw new Error('Notification template API is unavailable.');
  return bootstrap.notificationTemplatesUrl;
};

export const fetchNotificationTemplates = (bootstrap: LaravelAdminBootstrap): Promise<NotificationTemplate[]> =>
  notificationRequest(templatesUrl(bootstrap), {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  }, 'Notification templates could not be loaded.');

export const saveNotificationTemplate = (
  bootstrap: LaravelAdminBootstrap,
  eventKey: NotificationEventKey,
  changes: NotificationTemplateUpdate,
): Promise<NotificationTemplate> => notificationRequest(`${templatesUrl(bootstrap)}/${encodeURIComponent(eventKey)}`, {
  method: 'PUT',
  credentials: 'same-origin',
  headers: notificationHeaders(bootstrap),
  body: JSON.stringify(changes),
}, 'Notification template could not be saved.');

export const resetNotificationTemplates = (bootstrap: LaravelAdminBootstrap): Promise<NotificationTemplate[]> =>
  notificationRequest(`${templatesUrl(bootstrap)}/reset`, {
    method: 'POST',
    credentials: 'same-origin',
    headers: notificationHeaders(bootstrap),
    body: JSON.stringify({}),
  }, 'Notification templates could not be reset.');

export const sendNotificationTest = (
  bootstrap: LaravelAdminBootstrap,
  eventKey: NotificationEventKey,
  recipientEmail: string,
): Promise<NotificationDispatch> => notificationRequest(`${templatesUrl(bootstrap)}/${encodeURIComponent(eventKey)}/test`, {
  method: 'POST',
  credentials: 'same-origin',
  headers: notificationHeaders(bootstrap),
  body: JSON.stringify({ recipientEmail }),
}, 'Notification test could not be queued.');
