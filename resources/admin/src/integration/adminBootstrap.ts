import type { LaravelAdminBootstrap } from '../types';

export const readAdminBootstrap = (
  bootstrap: LaravelAdminBootstrap | undefined = typeof window === 'undefined'
    ? undefined
    : window.__JW_ADMIN__,
): LaravelAdminBootstrap => {
  if (
    !bootstrap
    || !bootstrap.user
    || typeof bootstrap.user.name !== 'string'
    || typeof bootstrap.user.email !== 'string'
    || (bootstrap.user.avatar !== null && typeof bootstrap.user.avatar !== 'string')
    || typeof bootstrap.csrfToken !== 'string'
    || typeof bootstrap.logoutUrl !== 'string'
    || (bootstrap.notificationTemplatesUrl !== undefined && typeof bootstrap.notificationTemplatesUrl !== 'string')
    || (bootstrap.orderNotificationsUrl !== undefined && typeof bootstrap.orderNotificationsUrl !== 'string')
    || (bootstrap.notificationPermissions !== undefined && !bootstrap.notificationPermissions.every((permission) => typeof permission === 'string'))
    || (bootstrap.user.notificationPermissions !== undefined && !bootstrap.user.notificationPermissions.every((permission) => typeof permission === 'string'))
  ) {
    throw new Error('Missing Laravel Admin bootstrap');
  }

  return bootstrap;
};
