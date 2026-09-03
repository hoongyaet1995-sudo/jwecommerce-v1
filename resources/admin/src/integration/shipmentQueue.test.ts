import { describe, expect, it } from 'vitest';
import type { ShipmentParcel } from '../types/shipping';
import { visibleShipmentParcels } from './shipmentQueue';

const parcel = (overrides: Partial<ShipmentParcel>): ShipmentParcel => ({
  id: 'shipment-1', orderId: 'order-1', orderNumber: 'JW-1', customerName: 'Customer', items: [],
  totalWeightKg: 1, volumetricWeightKg: 0, dimensionsCm: { length: 1, width: 1, height: 1 },
  courier: 'unassigned', serviceType: '', trackingNumber: '', hasLabel: false, routingSortCode: '',
  status: 'pending', lifecycleStage: 'pending_fulfilment', labelState: 'not_required', recoveryState: 'none',
  primaryAction: 'fulfil', capabilities: { fulfil: true, resume: false, printLabel: false, viewTimeline: false, correct: false, amendAddress: true, updateStatus: false, refreshProvider: false, copyTracking: false },
  paymentMode: 'prepaid', isEastMalaysiaAirRestricted: false, postcodeValidationStatus: 'valid', createdAt: '', awbGeneratedAt: null,
  dispatchedAt: null, estimatedDeliveryDate: null, systemQuotedCharge: 0, actualCourierCharge: 0, isWeightDiscrepancy: false,
  trackingEvents: [],
  ...overrides,
});

describe('visibleShipmentParcels', () => {
  it('excludes the superseded attempt when its resumable replacement is in the open work queue', () => {
    const oldAttempt = parcel({ id: 'old-attempt', source: 'manual', lifecycleStage: 'action_required', primaryAction: 'reviewRecovery' });
    const replacement = parcel({
      id: 'pending-order-order-1', source: 'pending', lifecycleStage: 'action_required', recoveryState: 'replacement_required', primaryAction: 'resume',
      capabilities: { ...parcel({}).capabilities, fulfil: false, resume: true },
      activeCorrection: { id: 'correction-1', sourceShipmentId: 'old-attempt', replacementShipmentId: null, reasonCode: 'wrong_fulfilment_method', externalActionState: 'not_required', status: 'awaiting_replacement', readyToResume: true },
    });
    const independent = parcel({ id: 'pending-order-2', orderId: 'order-2', lifecycleStage: 'pending_fulfilment' });

    expect(visibleShipmentParcels([oldAttempt, replacement, independent]).map(row => row.id)).toEqual([
      'pending-order-order-1',
      'pending-order-2',
    ]);
  });
});
