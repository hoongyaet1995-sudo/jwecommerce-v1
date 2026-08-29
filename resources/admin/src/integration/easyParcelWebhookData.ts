import type { LaravelAdminBootstrap } from '../types';
import type { EasyParcelWebhookAuditSnapshot, EasyParcelWebhookReceiptRow } from '../types/shipping';

export type EasyParcelWebhookAuditStateUpdate =
  | EasyParcelWebhookAuditSnapshot
  | ((current: EasyParcelWebhookAuditSnapshot) => EasyParcelWebhookAuditSnapshot);

export const createEasyParcelWebhookAuditRefresher = (
  load: () => Promise<EasyParcelWebhookReceiptRow[]>,
  publish: (update: EasyParcelWebhookAuditStateUpdate) => void,
): (() => Promise<void>) => {
  let latestRequestId = 0;

  return async (): Promise<void> => {
    const requestId = ++latestRequestId;
    publish(current => ({ ...current, status: 'loading', error: null }));

    try {
      const receipts = await load();
      if (requestId !== latestRequestId) return;
      publish({ receipts, status: 'ready', error: null });
    } catch (error) {
      if (requestId !== latestRequestId) return;
      const failure = error instanceof Error
        ? error
        : new Error('EasyParcel webhook audit could not be loaded.');
      publish(current => ({ ...current, status: 'error', error: failure.message }));
      throw failure;
    }
  };
};

export const fetchEasyParcelWebhookReceipts = async (
  bootstrap: LaravelAdminBootstrap,
): Promise<EasyParcelWebhookReceiptRow[]> => {
  if (!bootstrap.easyParcelWebhookReceiptsUrl) {
    throw new Error('EasyParcel webhook audit could not be loaded.');
  }

  const response = await window.fetch(bootstrap.easyParcelWebhookReceiptsUrl, {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  });
  if (!response.ok) throw new Error('EasyParcel webhook audit could not be loaded.');
  const payload = await response.json() as { data: EasyParcelWebhookReceiptRow[] };
  return payload.data;
};
