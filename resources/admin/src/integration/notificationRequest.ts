import type { LaravelAdminBootstrap } from '../types';
import { NotificationApiError, type NotificationValidationErrors } from '../types/notifications';

type NotificationResponse<T> = { data?: T; errors?: NotificationValidationErrors; };

const unavailable = 'Notification service is unavailable. Please try again later.';

const userError = (status: number, payload: NotificationResponse<unknown> | null, fallback: string): NotificationApiError => {
  if (status === 422) {
    const errors = payload?.errors ?? {};
    const message = Object.values(errors).flat().join(' ') || 'Notification request could not be completed.';
    return new NotificationApiError(message, status, errors);
  }

  if (status === 403) return new NotificationApiError('You do not have permission to manage notifications.', status);
  if (status === 429) return new NotificationApiError('Too many notification requests. Please try again shortly.', status);
  if (status >= 500) return new NotificationApiError(unavailable, status);

  return new NotificationApiError(fallback, status);
};

export const notificationHeaders = (bootstrap: LaravelAdminBootstrap): HeadersInit => ({
  Accept: 'application/json',
  'Content-Type': 'application/json',
  'X-CSRF-TOKEN': bootstrap.csrfToken,
});

export const notificationRequest = async <T>(
  url: string,
  init: RequestInit,
  fallback: string,
): Promise<T> => {
  let response: Response;

  try {
    response = await window.fetch(url, init);
  } catch {
    throw new NotificationApiError(unavailable, 0);
  }

  const payload = await response.json().catch(() => null) as NotificationResponse<T> | null;
  if (!response.ok) throw userError(response.status, payload, fallback);
  if (payload?.data === undefined) throw new NotificationApiError(fallback, response.status);

  return payload.data;
};
