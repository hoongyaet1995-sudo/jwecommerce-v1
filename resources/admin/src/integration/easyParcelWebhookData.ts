import type { LaravelAdminBootstrap } from '../types';
import type { EasyParcelWebhookReceiptRow } from '../types/shipping';

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
