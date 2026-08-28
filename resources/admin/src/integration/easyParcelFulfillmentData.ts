import type { LaravelAdminBootstrap } from '../types';

export interface EasyParcelPackageSpec {
  weightKg: number;
  lengthCm: number;
  widthCm: number;
  heightCm: number;
}

export interface EasyParcelQuoteService {
  serviceCode: string;
  serviceName: string;
  courierCode: string;
  courierName: string;
  deliveryDuration: string | null;
  isPickup: boolean;
  isDropoff: boolean;
  supportsCod: boolean;
  currency: string;
  amount: number;
}

export interface EasyParcelQuote {
  id: string;
  orderId: string;
  expiresAt: string | null;
  package: EasyParcelPackageSpec;
  services: EasyParcelQuoteService[];
}

export interface EasyParcelBooking {
  id: string;
  orderId: string;
  quoteId: string;
  fulfillmentStatus: 'pending' | 'ready_to_ship';
  trackingNumber: string | null;
  hasLabel: boolean;
}

type ApiResponse<T> = { data: T; message?: string; errors?: Record<string, string[]> };

const orderUrl = (bootstrap: LaravelAdminBootstrap, orderId: string, suffix: string): string => {
  if (!bootstrap.easyParcelOrdersUrl) throw new Error('EasyParcel fulfillment API is unavailable.');
  return `${bootstrap.easyParcelOrdersUrl}/${encodeURIComponent(orderId)}/easyparcel/${suffix}`;
};

const post = async <T>(bootstrap: LaravelAdminBootstrap, url: string, body: unknown, fallback: string): Promise<T> => {
  const response = await window.fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': bootstrap.csrfToken },
    body: JSON.stringify(body),
  });
  const payload = await response.json().catch(() => null) as ApiResponse<T> | null;
  if (!response.ok || !payload?.data) {
    const message = payload?.errors ? Object.values(payload.errors).flat().join(' ') : payload?.message ?? fallback;
    throw new Error(message);
  }
  return payload.data;
};

export const requestEasyParcelQuote = (bootstrap: LaravelAdminBootstrap, orderId: string, packageSpec: EasyParcelPackageSpec): Promise<EasyParcelQuote> =>
  post(bootstrap, orderUrl(bootstrap, orderId, 'quotes'), { package: packageSpec }, 'EasyParcel quotation could not be requested.');

export const confirmEasyParcelBooking = (bootstrap: LaravelAdminBootstrap, orderId: string, quoteId: string, serviceCode: string): Promise<EasyParcelBooking> =>
  post(bootstrap, orderUrl(bootstrap, orderId, 'bookings'), { quoteId, serviceCode }, 'EasyParcel booking could not be confirmed.');
