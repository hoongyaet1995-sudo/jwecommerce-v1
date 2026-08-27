import type { LaravelAdminBootstrap } from '../types';
import type { ShipmentParcel } from '../types/shipping';

type ShipmentResponse = { data: ShipmentParcel[] };
type ErrorResponse = { message?: string; errors?: Record<string, string[]> };

const errorMessage = (payload: ErrorResponse | null, fallback: string): string =>
  payload?.errors ? Object.values(payload.errors).flat().join(' ') : payload?.message ?? fallback;

export const fetchShipments = async (bootstrap: LaravelAdminBootstrap): Promise<ShipmentParcel[]> => {
  if (!bootstrap.shipmentsUrl) throw new Error('Shipment API is unavailable.');
  const response = await window.fetch(bootstrap.shipmentsUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
  if (!response.ok) throw new Error('Shipment data request failed.');
  const payload = await response.json() as ShipmentResponse;
  if (!Array.isArray(payload.data)) throw new Error('Invalid shipment data response.');
  return payload.data;
};

export const appendShipmentEvent = async (
  bootstrap: LaravelAdminBootstrap,
  shipmentId: string,
  status: 'in_transit' | 'out_for_delivery' | 'delivered' | 'failed_delivery' | 'returned',
  description: string,
  location?: string,
): Promise<void> => {
  if (!bootstrap.shipmentsUrl) throw new Error('Shipment API is unavailable.');
  const response = await window.fetch(`${bootstrap.shipmentsUrl}/${encodeURIComponent(shipmentId)}/events`, {
    method: 'POST', credentials: 'same-origin',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': bootstrap.csrfToken },
    body: JSON.stringify({ status, description, location }),
  });
  const payload = await response.json().catch(() => null) as ErrorResponse | null;
  if (!response.ok) throw new Error(errorMessage(payload, 'Shipment event could not be recorded.'));
};
