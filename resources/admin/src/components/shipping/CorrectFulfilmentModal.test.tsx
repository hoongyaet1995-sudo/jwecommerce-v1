import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it, vi } from 'vitest';
import type { ShipmentParcel } from '../../types/shipping';
import { CorrectFulfilmentModal } from './CorrectFulfilmentModal';

const parcel = {
  id:'7',source:'manual',fulfillmentMethod:'external_courier',managementMode:'team_managed',externalCourierName:'J&T Express',
  orderId:'4',orderNumber:'JW-1004',customerName:'Buyer',items:[],totalWeightKg:1,volumetricWeightKg:null,dimensionsCm:null,
  courier:'jnt',serviceType:'Standard',trackingNumber:'JNT-7',hasLabel:false,routingSortCode:'',status:'in_transit',paymentMode:'prepaid',
  lifecycleStage:'in_transit',labelState:'not_required',recoveryState:'none',primaryAction:'updateStatus',
  capabilities:{fulfil:false,resume:false,printLabel:false,viewTimeline:true,correct:false,amendAddress:false,updateStatus:true,refreshProvider:false,copyTracking:true},
  isEastMalaysiaAirRestricted:false,postcodeValidationStatus:'valid',createdAt:'2026-09-02T00:00:00Z',systemQuotedCharge:null,isWeightDiscrepancy:false,trackingEvents:[],
} satisfies ShipmentParcel;

describe('CorrectFulfilmentModal source-fidelity boundary', () => {
  it('preserves the approved dual-mode correction UI without prototype toggles', () => {
    const html=renderToStaticMarkup(<CorrectFulfilmentModal isOpen parcel={parcel} onClose={vi.fn()} onConfirmVoidAndReplace={vi.fn()} onConfirmCorrectDetailsOnly={vi.fn()} />);
    expect(html).toContain('Correct Fulfilment');
    expect(html).toContain('Void Attempt &amp; Replace Method');
    expect(html).toContain('Correct Details Only');
    expect(html).not.toContain('Simulate Permission Denied');
    expect(html).not.toContain('Toggle Unconfirmed Provider State');
  });
});
