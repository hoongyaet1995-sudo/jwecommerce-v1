import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { ParcelTrackingModal } from '../components/shipping/ParcelTrackingModal';
import { ShippingFulfillmentTab } from '../components/shipping/ShippingFulfillmentTab';
import { INITIAL_SHIPMENT_PARCELS } from '../data/shippingData';
import type { LaravelAdminBootstrap } from '../types';
import type { ShipmentParcel } from '../types/shipping';
import { fetchShipments } from './shipmentData';

const bootstrap = {
  user: { name: 'Admin', email: 'admin@example.test', avatar: null },
  csrfToken: 'csrf-token',
  logoutUrl: '/logout',
  shipmentsUrl: '/admin/api/shipping/shipments',
} satisfies LaravelAdminBootstrap;

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
  trackingNumber: '',
  routingSortCode: '',
  status: 'pending',
  paymentMode: 'prepaid',
  isEastMalaysiaAirRestricted: false,
  postcodeValidationStatus: 'unknown',
  createdAt: '2026-08-28T00:00:00+08:00',
  systemQuotedCharge: null,
  isWeightDiscrepancy: false,
  trackingEvents: [],
} satisfies ShipmentParcel;

describe('redacted shipment-list client contract', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('projects fetched rows onto the safe DTO even if an upstream response includes recipient PII', async () => {
    const responseRow = {
      ...redactedParcel,
      customerPhone: '+60 12-345 6789',
      customerEmail: 'buyer@example.test',
      recipientAddress: {
        street: '12 Private Street',
        city: 'Kuala Lumpur',
        state: 'Kuala Lumpur',
        postcode: '50000',
        country: 'Malaysia',
        region: 'peninsular',
      },
    };
    vi.stubGlobal('window', {
      fetch: vi.fn().mockResolvedValue({
        ok: true,
        json: async () => ({ data: [responseRow] }),
      }),
    });

    const [parcel] = await fetchShipments(bootstrap);

    expect(parcel).toMatchObject({ id: '1', orderNumber: 'JW-10011', customerName: 'Customer' });
    expect(parcel).not.toHaveProperty('customerPhone');
    expect(parcel).not.toHaveProperty('customerEmail');
    expect(parcel).not.toHaveProperty('recipientAddress');
  });

  it('renders a non-empty fulfillment row using neutral recipient placeholders', () => {
    const html = renderToStaticMarkup(
      <ShippingFulfillmentTab
        parcels={[redactedParcel]}
        onGenerateAWB={vi.fn()}
        onBatchGenerateAWB={vi.fn()}
        onOpenAWBModal={vi.fn()}
        onOpenTrackingModal={vi.fn()}
        onOpenPickupModal={vi.fn()}
        onUpdateStatus={vi.fn()}
      />,
    );

    expect(html).toContain('JW-10011');
    expect(html).toContain('Recipient details restricted');
    expect(html).not.toContain('buyer@example.test');
    expect(html).not.toContain('12 Private Street');
  });

  it('renders tracking for a redacted row without exposing a contact action', () => {
    const html = renderToStaticMarkup(
      <ParcelTrackingModal parcel={redactedParcel} onClose={vi.fn()} />,
    );

    expect(html).toContain('JW-10011');
    expect(html).toContain('Recipient contact restricted');
    expect(html).not.toContain('Contact Recipient on WhatsApp');
  });

  it('keeps bundled shipment-list examples within the redacted DTO boundary', () => {
    for (const parcel of INITIAL_SHIPMENT_PARCELS) {
      expect(parcel).not.toHaveProperty('customerPhone');
      expect(parcel).not.toHaveProperty('customerEmail');
      expect(parcel).not.toHaveProperty('recipientAddress');
    }
  });
});
