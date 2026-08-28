import type { LaravelAdminBootstrap } from '../types';
import type { CourierConfig } from '../types/shipping';

export interface EasyParcelProviderStatus {
  provider: 'easyparcel';
  environment: 'sandbox';
  configured: boolean;
  enabled: boolean;
  connectionState: 'unconfigured' | 'unverified' | 'connected' | 'failed';
  lastVerifiedAt: string | null;
  lastError: string | null;
}

type ProviderResponse = { data: EasyParcelProviderStatus };
type ErrorResponse = { message?: string; errors?: Record<string, string[]> };

const errorMessage = (payload: ErrorResponse | null, fallback: string): string =>
  payload?.errors ? Object.values(payload.errors).flat().join(' ') : payload?.message ?? fallback;

const jsonHeaders = (bootstrap: LaravelAdminBootstrap): HeadersInit => ({
  Accept: 'application/json',
  'Content-Type': 'application/json',
  'X-CSRF-TOKEN': bootstrap.csrfToken,
});

const responseData = async (response: Response, fallback: string): Promise<EasyParcelProviderStatus> => {
  const payload = await response.json().catch(() => null) as ProviderResponse | ErrorResponse | null;
  if (!response.ok || !payload || !('data' in payload)) {
    throw new Error(errorMessage(payload as ErrorResponse | null, fallback));
  }
  return payload.data;
};

const providerUrl = (bootstrap: LaravelAdminBootstrap): string => {
  if (!bootstrap.easyParcelProviderUrl) throw new Error('EasyParcel provider API is unavailable.');
  return bootstrap.easyParcelProviderUrl;
};

export const fetchEasyParcelProvider = async (bootstrap: LaravelAdminBootstrap): Promise<EasyParcelProviderStatus> =>
  responseData(await window.fetch(providerUrl(bootstrap), { credentials: 'same-origin', headers: { Accept: 'application/json' } }), 'EasyParcel provider could not be loaded.');

export const saveEasyParcelCredentials = async (
  bootstrap: LaravelAdminBootstrap,
  input: { environment: 'sandbox'; clientId: string; clientSecret: string; enabled: boolean },
): Promise<EasyParcelProviderStatus> => responseData(await window.fetch(providerUrl(bootstrap), {
  method: 'PUT',
  credentials: 'same-origin',
  headers: jsonHeaders(bootstrap),
  body: JSON.stringify(input),
}), 'EasyParcel provider could not be saved.');

export const startEasyParcelAuthorization = (bootstrap: LaravelAdminBootstrap): void => {
  if (!bootstrap.easyParcelProviderAuthorizeUrl) throw new Error('EasyParcel authorization is unavailable.');
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = bootstrap.easyParcelProviderAuthorizeUrl;
  form.style.display = 'none';
  const token = document.createElement('input');
  token.type = 'hidden';
  token.name = '_token';
  token.value = bootstrap.csrfToken;
  form.append(token);
  document.body.append(form);
  form.submit();
};

const presentationCourier = (
  id: string,
  courierCode: CourierConfig['courierCode'],
  displayName: string,
): CourierConfig => ({
  id,
  courierCode,
  name: displayName,
  displayName,
  logo: '',
  category: courierCode === 'easyparcel' ? 'aggregator_api' : 'direct_courier',
  isEnabled: false,
  environment: 'sandbox',
  apiKey: '',
  apiSecret: '',
  senderHubName: '',
  senderHubAddress: '',
  senderHubPostcode: '',
  senderHubState: '',
  senderContactPhone: '',
  supportsCOD: false,
  codFeePercent: 0,
  supportsEastMalaysia: false,
  supportsAirFreightHazmat: false,
  supportedServices: [],
  status: 'pending_keys',
  webhookEndpointUrl: '',
  autoRetryQueue: false,
  maxRetryAttempts: 0,
});

export const courierConfigsFromEasyParcel = (status: EasyParcelProviderStatus): CourierConfig[] => [
  {
    ...presentationCourier('cfg_easyparcel', 'easyparcel', 'EasyParcel Multi-Carrier'),
    isEnabled: status.enabled,
    status: status.connectionState === 'connected' ? 'connected' : status.connectionState === 'failed' ? 'error' : 'pending_keys',
  },
  presentationCourier('cfg_jnt', 'jnt', 'J&T Express MY'),
  presentationCourier('cfg_ninjavan', 'ninjavan', 'Ninja Van MY'),
  presentationCourier('cfg_posmalaysia', 'posmalaysia', 'Pos Laju (National Post)'),
  presentationCourier('cfg_flashexpress', 'flashexpress', 'Flash Express MY'),
  presentationCourier('cfg_dhl', 'dhlecommerce', 'DHL eCommerce MY'),
];
