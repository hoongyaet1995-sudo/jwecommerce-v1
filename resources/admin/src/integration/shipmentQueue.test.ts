import {fetchShipments} from './shipmentData';
import type {LaravelAdminBootstrap} from '../types';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { ShipmentParcel } from '../types/shipping';
import { preparationParcels, deliveryParcels, visibleShipmentParcels } from './shipmentQueue';

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

describe('separate preparation and delivery history',()=>{
 it('only admits exact processing order rows and retains terminal history in deliveries',()=>{
  const rows=[parcel({id:'ready',source:'pending',orderStatus:'processing'}),parcel({id:'hold',source:'pending',orderStatus:'on_hold'}),parcel({id:'unpaid',source:'pending',orderStatus:'pending_payment'}),parcel({id:'dispatched',source:'manual',orderStatus:'shipped'}),parcel({id:'cancelled',source:'manual',status:'cancelled'})];
  expect(preparationParcels(rows).map(p=>p.id)).toEqual(['ready']);
  expect(deliveryParcels(rows).map(p=>p.id)).toEqual(['dispatched','cancelled']);
 });
});

describe('server lifecycle field handoff',()=>{
 afterEach(()=>vi.unstubAllGlobals());
 it('preserves order and payment status through the list adapter before queue filtering',async()=>{
  vi.stubGlobal('window',{fetch:vi.fn().mockResolvedValue({ok:true,json:async()=>({data:[parcel({source:'pending',orderStatus:'processing',paymentStatus:'paid',statusName:'Preparing'})]})})});
  const rows=await fetchShipments({shipmentsUrl:'/admin/api/shipments'} as LaravelAdminBootstrap);
  expect(rows[0]).toMatchObject({orderStatus:'processing',paymentStatus:'paid',statusName:'Preparing'});
  expect(preparationParcels(rows)).toHaveLength(1);
 });
});
