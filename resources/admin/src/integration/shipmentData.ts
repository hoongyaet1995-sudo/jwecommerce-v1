import type { LaravelAdminBootstrap } from '../types';
import type { ShipmentParcel } from '../types/shipping';

type ShipmentResponse = { data: ShipmentParcel[] };
type ErrorResponse = { message?: string; errors?: Record<string, string[]> };
export type EasyParcelShipmentStatusRefresh = {
  shipmentId: string;
  status: ShipmentParcel['status'];
  changed: boolean;
  providerStatusCode: number;
  providerStatus: string;
  checkedAt: string;
  awbAvailable: boolean;
  recoveryDisposition: 'none' | 'needs_review' | 'provider_terminal';
};
export type EasyParcelRecoveryResult = {
  recoveryId: string;
  shipmentId: string;
  replacementOrderId: string;
  shipmentStatus: ShipmentParcel['status'];
  recoveryState: string;
  cancellationState: 'success' | 'error' | 'claimed';
  cancellationMessage: string | null;
  cancellationConfirmed: boolean;
};

const errorMessage = (payload: ErrorResponse | null, fallback: string): string =>
  payload?.errors ? Object.values(payload.errors).flat().join(' ') : payload?.message ?? fallback;

const shipmentListParcel = (row: ShipmentParcel): ShipmentParcel => ({
  id: row.id,
  source: row.source,
  orderId: row.orderId,
  orderNumber: row.orderNumber,
  customerName: row.customerName,
  destinationPostcode: row.destinationPostcode,
  items: row.items,
  totalWeightKg: row.totalWeightKg,
  volumetricWeightKg: row.volumetricWeightKg,
  dimensionsCm: row.dimensionsCm,
  courier: row.courier,
  serviceType: row.serviceType,
  trackingNumber: row.trackingNumber,
  routingSortCode: row.routingSortCode,
  status: row.status,
  paymentMode: row.paymentMode,
  codAmount: row.codAmount,
  isEastMalaysiaAirRestricted: row.isEastMalaysiaAirRestricted,
  postcodeValidationStatus: row.postcodeValidationStatus,
  createdAt: row.createdAt,
  awbGeneratedAt: row.awbGeneratedAt,
  dispatchedAt: row.dispatchedAt,
  estimatedDeliveryDate: row.estimatedDeliveryDate,
  deliveredAt: row.deliveredAt,
  actualCourierCharge: row.actualCourierCharge,
  systemQuotedCharge: row.systemQuotedCharge,
  isWeightDiscrepancy: row.isWeightDiscrepancy,
  exceptionReason: row.exceptionReason,
  exceptionAttempts: row.exceptionAttempts,
  pickupRequestId: row.pickupRequestId,
  trackingEvents: row.trackingEvents,
});

export const fetchShipments = async (bootstrap: LaravelAdminBootstrap): Promise<ShipmentParcel[]> => {
  if (!bootstrap.shipmentsUrl) throw new Error('Shipment API is unavailable.');
  const response = await window.fetch(bootstrap.shipmentsUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
  if (!response.ok) throw new Error('Shipment data request failed.');
  const payload = await response.json() as ShipmentResponse;
  if (!Array.isArray(payload.data)) throw new Error('Invalid shipment data response.');
  return payload.data.map(shipmentListParcel);
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

export const refreshEasyParcelShipmentStatus = async (
  bootstrap: LaravelAdminBootstrap,
  shipmentId: string,
): Promise<EasyParcelShipmentStatusRefresh> => {
  if (!bootstrap.shipmentsUrl) throw new Error('Shipment API is unavailable.');
  const response = await window.fetch(
    `${bootstrap.shipmentsUrl}/${encodeURIComponent(shipmentId)}/easyparcel/status`,
    {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-CSRF-TOKEN': bootstrap.csrfToken },
    },
  );
  const payload = await response.json().catch(() => null) as { data?: EasyParcelShipmentStatusRefresh } & ErrorResponse | null;
  if (!response.ok || !payload?.data) {
    throw new Error(errorMessage(payload, 'EasyParcel status could not be refreshed.'));
  }

  return payload.data;
};

export const easyParcelShipmentStatusDiagnostic = (
  result: EasyParcelShipmentStatusRefresh,
): string => `EasyParcel reports ${result.providerStatus} (code ${result.providerStatusCode}); AWB ${result.awbAvailable ? 'is available.' : 'is still unavailable.'}`;

export const easyParcelRecoveryCanRebook = (result: EasyParcelRecoveryResult): boolean =>
  result.recoveryState === 'awaiting_replacement'
  && (result.shipmentStatus === 'booking_failed' || result.shipmentStatus === 'cancelled');

export const confirmEasyParcelProviderFailure = async (
  bootstrap: LaravelAdminBootstrap,
  shipmentId: string,
  reason: string,
): Promise<EasyParcelRecoveryResult> => {
  if (!bootstrap.shipmentsUrl) throw new Error('Shipment API is unavailable.');
  const response = await window.fetch(
    `${bootstrap.shipmentsUrl}/${encodeURIComponent(shipmentId)}/easyparcel/recovery`,
    {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': bootstrap.csrfToken,
      },
      body: JSON.stringify({ reason }),
    },
  );
  const payload = await response.json().catch(() => null) as { data?: EasyParcelRecoveryResult } & ErrorResponse | null;
  if (!response.ok || !payload?.data) {
    throw new Error(errorMessage(payload, 'EasyParcel failed-booking recovery could not be confirmed.'));
  }

  return payload.data;
};
