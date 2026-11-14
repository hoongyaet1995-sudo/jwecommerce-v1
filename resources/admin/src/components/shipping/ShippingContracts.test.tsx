// @vitest-environment jsdom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it, vi } from 'vitest';
import { PickupScheduleModal } from './PickupScheduleModal';
import { ShippingOverviewTab } from './ShippingOverviewTab';
import { CorrectFulfilmentModal } from './CorrectFulfilmentModal';
import { INITIAL_SHIPMENT_PARCELS } from '../../data/shippingData';
import type { PickupRequest, ShipmentParcel } from '../../types/shipping';
globalThis.IS_REACT_ACT_ENVIRONMENT = true;
describe('canonical shipping presentation contracts', () => {
  it('submits canonical pickup values without fabricating a provider reference', () => {
    const host = document.createElement('div'); const root = createRoot(host); const onSubmit = vi.fn();
    try {
      act(() => root.render(<PickupScheduleModal isOpen onClose={() => undefined} onSubmit={onSubmit} />));
      act(() => host.querySelector('form')!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
      expect(onSubmit).toHaveBeenCalledWith(expect.objectContaining({ vehicleType: 'car', totalWeightKg: 18.5, pickupAddress: 'Subang Central Warehouse (Bay 3)', contactPerson: 'Kamal bin Ahmad', contactPhone: '+60 12-889 1234' }));
      expect(onSubmit.mock.calls[0][0]).not.toHaveProperty('pickupRefNo');
    } finally { act(() => root.unmount()); }
  });
  it('renders pickup weight and address from the canonical read DTO', () => {
    const request: PickupRequest = { id: 'pickup-1', pickupRefNo: 'REF-1', courier: 'jnt', pickupDate: '2026-11-16', timeSlot: '14:00 - 18:00', totalParcels: 12, totalWeightKg: 18.5, vehicleType: 'car', pickupAddress: 'Canonical warehouse', contactPerson: 'Kamal', contactPhone: '0123456789', status: 'confirmed', createdAt: '2026-11-15' };
    const html = renderToStaticMarkup(<ShippingOverviewTab parcels={[]} couriers={[]} pickupRequests={[request]} onNavigateTab={() => undefined} onOpenPickupModal={() => undefined} />);
    expect(html).toContain('18.5'); expect(html).toContain('Canonical warehouse');
  });
  it('shows replacement state from the persisted correction snapshot', () => {
    const parcel: ShipmentParcel = { ...INITIAL_SHIPMENT_PARCELS[0], activeCorrection: { id: 'correction-1', sourceShipmentId: 'shipment-1', replacementShipmentId: null, reasonCode: 'other', externalActionState: 'not_required', status: 'awaiting_replacement', readyToResume: true } };
    const html = renderToStaticMarkup(<CorrectFulfilmentModal isOpen parcel={parcel} onClose={() => undefined} onConfirmVoidAndReplace={() => undefined} onConfirmCorrectDetailsOnly={() => undefined} />);
    expect(html).toContain('Awaiting replacement');
  });
});
