import {SettingsReadError} from './settingsReadError';
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
  returnPolicy: ReturnPolicySettings;
}

export interface ReturnPolicySettings {
  enabled: boolean;
  returnWindowDays: number;
  policySummary: string;
}

type ResponsePayload = { data: CommerceSettingsDocument; message?: string; errors?: Record<string, string[]> };

export const fetchCommerceSettings = async (bootstrap: LaravelAdminBootstrap): Promise<CommerceSettingsDocument> => {
  if (!bootstrap.commerceSettingsUrl) throw new SettingsReadError('Commerce settings',undefined,'response');
  let response:Response;
  try {response=await window.fetch(bootstrap.commerceSettingsUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });}
  catch {throw new SettingsReadError('Commerce settings',undefined,'network');}
  if (!response.ok) throw new SettingsReadError('Commerce settings',response.status);
  try {const payload=await response.json() as ResponsePayload;if(!payload?.data||!Array.isArray(payload.data.shippingRates)||!Array.isArray(payload.data.currencies))throw new Error();return payload.data;}
  catch {throw new SettingsReadError('Commerce settings',response.status,'response');}
};

export const saveCommerceSettings = async (bootstrap: LaravelAdminBootstrap, document: Omit<CommerceSettingsDocument, 'returnPolicy'> & { returnPolicy?: ReturnPolicySettings }): Promise<CommerceSettingsDocument> => {
  if (!bootstrap.commerceSettingsUrl) throw new Error('Commerce settings API is unavailable.');
  const response = await window.fetch(bootstrap.commerceSettingsUrl, { method: 'PUT', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': bootstrap.csrfToken }, body: JSON.stringify(document) });
  const payload = await response.json().catch(() => null) as ResponsePayload | null;
  if (!response.ok || !payload?.data) throw new Error(payload?.errors ? Object.values(payload.errors).flat().join(' ') : payload?.message ?? 'Commerce settings save failed.');
  return payload.data;
};

const saveOfflinePaymentSetting = async (
  bootstrap: LaravelAdminBootstrap,
  suffix: 'bank-transfer' | 'cod' | 'shipping',
  document: Partial<Pick<CommerceSettingsDocument, 'bankTransfer' | 'codConfig' | 'shippingRates'>>,
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

export const saveShippingConfiguration = async (
  bootstrap: LaravelAdminBootstrap,
  shippingRates: GeneralShippingRate[],
): Promise<CommerceSettingsDocument> => saveOfflinePaymentSetting(bootstrap, 'shipping', { shippingRates });

export const saveReturnPolicyConfiguration = async (
  bootstrap: LaravelAdminBootstrap,
  returnPolicy: ReturnPolicySettings,
): Promise<CommerceSettingsDocument> => {
  if (!bootstrap.commerceSettingsUrl) throw new Error('Commerce settings API is unavailable.');
  const response = await window.fetch(`${bootstrap.commerceSettingsUrl}/return-policy`, {
    method: 'PUT', credentials: 'same-origin',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': bootstrap.csrfToken },
    body: JSON.stringify({ returnPolicy }),
  });
  const payload = await response.json().catch(() => null) as ResponsePayload | null;
  if (!response.ok || !payload?.data) throw new Error(payload?.errors ? Object.values(payload.errors).flat().join(' ') : payload?.message ?? 'Return policy save failed.');
  return payload.data;
};
