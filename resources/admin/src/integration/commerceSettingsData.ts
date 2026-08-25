import type { LaravelAdminBootstrap } from '../types';
import type { GeneralShippingRate } from '../types/shipping';
import type { CashOnDeliveryConfig, GlobalPaymentSettings, ManualBankTransferConfig, PaymentGatewayConfig } from '../types/payment';
import type { CurrencySetting } from '../types';

export interface CommerceSettingsDocument {
  shippingRates: GeneralShippingRate[];
  paymentGateways: PaymentGatewayConfig[];
  globalPayment: GlobalPaymentSettings | Record<string, never>;
  bankTransfer: ManualBankTransferConfig | Record<string, never>;
  codConfig: CashOnDeliveryConfig | Record<string, never>;
  currencies: CurrencySetting[];
}

type ResponsePayload = { data: CommerceSettingsDocument; message?: string; errors?: Record<string, string[]> };

export const fetchCommerceSettings = async (bootstrap: LaravelAdminBootstrap): Promise<CommerceSettingsDocument> => {
  if (!bootstrap.commerceSettingsUrl) throw new Error('Commerce settings API is unavailable.');
  const response = await window.fetch(bootstrap.commerceSettingsUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
  if (!response.ok) throw new Error('Commerce settings request failed.');
  return (await response.json() as ResponsePayload).data;
};

export const saveCommerceSettings = async (bootstrap: LaravelAdminBootstrap, document: CommerceSettingsDocument): Promise<CommerceSettingsDocument> => {
  if (!bootstrap.commerceSettingsUrl) throw new Error('Commerce settings API is unavailable.');
  const response = await window.fetch(bootstrap.commerceSettingsUrl, { method: 'PUT', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': bootstrap.csrfToken }, body: JSON.stringify(document) });
  const payload = await response.json().catch(() => null) as ResponsePayload | null;
  if (!response.ok || !payload?.data) throw new Error(payload?.errors ? Object.values(payload.errors).flat().join(' ') : payload?.message ?? 'Commerce settings save failed.');
  return payload.data;
};

const saveOfflinePaymentSetting = async (
  bootstrap: LaravelAdminBootstrap,
  suffix: 'bank-transfer' | 'cod',
  document: Partial<Pick<CommerceSettingsDocument, 'bankTransfer' | 'codConfig'>>,
): Promise<CommerceSettingsDocument> => {
  if (!bootstrap.commerceSettingsUrl) throw new Error('Commerce settings API is unavailable.');
  const response = await window.fetch(`${bootstrap.commerceSettingsUrl}/${suffix}`, {
    method: 'PUT',
    credentials: 'same-origin',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': bootstrap.csrfToken },
    body: JSON.stringify(document),
  });
  const payload = await response.json().catch(() => null) as ResponsePayload | null;
  if (!response.ok || !payload?.data) throw new Error(payload?.errors ? Object.values(payload.errors).flat().join(' ') : payload?.message ?? 'Commerce settings save failed.');
  return payload.data;
};

export const saveBankTransferConfiguration = async (
  bootstrap: LaravelAdminBootstrap,
  bankTransfer: ManualBankTransferConfig,
): Promise<CommerceSettingsDocument> => saveOfflinePaymentSetting(bootstrap, 'bank-transfer', { bankTransfer });

export const saveCodConfiguration = async (
  bootstrap: LaravelAdminBootstrap,
  codConfig: CashOnDeliveryConfig,
): Promise<CommerceSettingsDocument> => saveOfflinePaymentSetting(bootstrap, 'cod', { codConfig });
