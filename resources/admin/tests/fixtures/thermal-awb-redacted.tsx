import React from 'react';
import { createRoot } from 'react-dom/client';
import { ThermalAWBModal } from '../../src/components/shipping/ThermalAWBModal';
import type { ShipmentParcel } from '../../src/types/shipping';

declare global {
  interface Window {
    __THERMAL_AWB_EFFECTS__: {
      onPrint: number;
      windowPrint: number;
    };
  }
}

const redactedParcel = {
  id: '1',
  orderId: '11',
  orderNumber: 'JW-10011',
  customerName: 'Customer',
  items: [{ sku: 'SKU-1', name: 'Item', qty: 1, unitPrice: 25, weightKg: 0.5 }],
  totalWeightKg: 0.5,
  volumetricWeightKg: null,
  dimensionsCm: null,
  courier: 'easyparcel',
  serviceType: 'EP-STD',
  trackingNumber: 'EP-TRACKING-1',
  routingSortCode: '',
  status: 'ready_to_ship',
  paymentMode: 'prepaid',
  isEastMalaysiaAirRestricted: false,
  postcodeValidationStatus: 'unknown',
  createdAt: '2026-08-28T00:00:00+08:00',
  systemQuotedCharge: 8,
  isWeightDiscrepancy: false,
  trackingEvents: [],
} satisfies ShipmentParcel;

window.__THERMAL_AWB_EFFECTS__ = { onPrint: 0, windowPrint: 0 };
window.print = () => {
  window.__THERMAL_AWB_EFFECTS__.windowPrint += 1;
};

createRoot(document.getElementById('root')!).render(
  <ThermalAWBModal
    parcel={redactedParcel}
    onClose={() => undefined}
    onPrint={() => {
      window.__THERMAL_AWB_EFFECTS__.onPrint += 1;
    }}
  />,
);
