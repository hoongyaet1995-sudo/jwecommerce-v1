import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { ParcelTrackingModal } from '../components/shipping/ParcelTrackingModal';
import { ShippingFulfillmentTab } from '../components/shipping/ShippingFulfillmentTab';
import { INITIAL_SHIPMENT_PARCELS } from '../data/shippingData';
import type { LaravelAdminBootstrap } from '../types';
import type { ShipmentParcel } from '../types/shipping';
import {
  confirmEasyParcelProviderFailure,
  confirmEasyParcelProviderFailureWithReload,
  easyParcelRecoveryCanRebook,
  easyParcelShipmentStatusDiagnostic,
  fetchShipments,
  refreshEasyParcelShipmentStatus,
  refreshEasyParcelShipmentStatusWithReload,
} from './shipmentData';

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

  it('requests an authenticated EasyParcel shipment-details refresh for one shipment', async () => {
    const fetch = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ data: {
        shipmentId: '1',
        status: 'delivered',
        changed: true,
        providerStatusCode: 5,
        providerStatus: 'Delivered',
        checkedAt: '2026-08-30T10:00:00+08:00',
        awbAvailable: true,
        recoveryDisposition: 'none',
      } }),
    });
    vi.stubGlobal('window', { fetch });

    await expect(refreshEasyParcelShipmentStatus(bootstrap, '1')).resolves.toEqual({
      shipmentId: '1',
      status: 'delivered',
      changed: true,
      providerStatusCode: 5,
      providerStatus: 'Delivered',
      checkedAt: '2026-08-30T10:00:00+08:00',
      awbAvailable: true,
      recoveryDisposition: 'none',
    });
    expect(fetch).toHaveBeenCalledWith('/admin/api/shipping/shipments/1/easyparcel/status', expect.objectContaining({
      method: 'POST',
      credentials: 'same-origin',
    }));
  });

  it('posts an employee reason to the protected failed-booking recovery endpoint', async () => {
    const fetch = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ data: {
        recoveryId: '91',
        shipmentId: '1',
        replacementOrderId: '11',
        shipmentStatus: 'booking_failed',
        cancellationState: 'error',
        cancellationMessage: 'EasyParcel cancellation unconfirmed.',
        cancellationConfirmed: false,
      } }),
    });
    vi.stubGlobal('window', { fetch });

    await expect(confirmEasyParcelProviderFailure(
      bootstrap,
      '1',
      'EasyParcel portal explicitly reports Failed.',
    )).resolves.toMatchObject({
      recoveryId: '91',
      replacementOrderId: '11',
      cancellationConfirmed: false,
    });
    expect(fetch).toHaveBeenCalledWith(
      '/admin/api/shipping/shipments/1/easyparcel/recovery',
      expect.objectContaining({
        method: 'POST',
        credentials: 'same-origin',
        body: JSON.stringify({ reason: 'EasyParcel portal explicitly reports Failed.' }),
      }),
    );
  });

  it('does not release a replacement quote while cancellation is still claimed', () => {
    expect(easyParcelRecoveryCanRebook({
      recoveryId: '91',
      shipmentId: '1',
      replacementOrderId: '11',
      shipmentStatus: 'pending',
      recoveryState: 'cancelling',
      cancellationState: 'claimed',
      cancellationMessage: null,
      cancellationConfirmed: false,
    })).toBe(false);

    expect(easyParcelRecoveryCanRebook({
      recoveryId: '91',
      shipmentId: '1',
      replacementOrderId: '11',
      shipmentStatus: 'booking_failed',
      recoveryState: 'awaiting_replacement',
      cancellationState: 'error',
      cancellationMessage: 'EasyParcel cancellation unconfirmed.',
      cancellationConfirmed: false,
    })).toBe(true);
  });

  it('preserves provider-terminal recovery readiness and fresh backend package data through reload', async () => {
    const recovery = {
      recoveryId: '91',
      state: 'awaiting_replacement',
      cancellationState: 'success',
      cancellationMessage: 'EasyParcel cancellation confirmed by provider status code 0.',
      cancellationConfirmed: true,
      replacementOrderId: '11',
      readyToRebook: true,
    };
    const replacement = {
      ...redactedParcel,
      id: 'pending-order-11',
      source: 'pending' as const,
      totalWeightKg: 1.5,
      dimensionsCm: { length: 31, width: 21, height: 11 },
      easyParcelRecovery: recovery,
    };
    const fetch = vi.fn()
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ data: {
          shipmentId: '1',
          status: 'cancelled',
          changed: true,
          providerStatusCode: 0,
          providerStatus: 'Cancelled',
          checkedAt: '2026-08-30T12:00:00+08:00',
          awbAvailable: false,
          recoveryDisposition: 'provider_terminal',
          recovery,
        } }),
      })
      .mockResolvedValueOnce({ ok: true, json: async () => ({ data: [replacement] }) });
    vi.stubGlobal('window', { fetch });

    const outcome = await refreshEasyParcelShipmentStatusWithReload(bootstrap, '1');

    expect(outcome.result.status).toBe('cancelled');
    expect(outcome.result.recovery).toEqual(recovery);
    expect(outcome.replacementParcel).toMatchObject({
      id: 'pending-order-11',
      totalWeightKg: 1.5,
      dimensionsCm: { length: 31, width: 21, height: 11 },
      easyParcelRecovery: recovery,
    });
    expect(outcome.reloadWarning).toBeNull();
  });

  it('preserves a successful refresh and confirmation when shipment reload fails', async () => {
    const refreshFetch = vi.fn()
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ data: {
          shipmentId: '1',
          status: 'on_hold',
          changed: true,
          providerStatusCode: 8,
          providerStatus: 'On Hold',
          checkedAt: '2026-08-30T12:05:00+08:00',
          awbAvailable: false,
          recoveryDisposition: 'none',
          recovery: null,
        } }),
      })
      .mockResolvedValueOnce({ ok: false, json: async () => ({ message: 'reload failed' }) });
    vi.stubGlobal('window', { fetch: refreshFetch });

    await expect(refreshEasyParcelShipmentStatusWithReload(bootstrap, '1')).resolves.toMatchObject({
      result: { status: 'on_hold', providerStatusCode: 8 },
      shipments: null,
      replacementParcel: null,
      reloadWarning: 'Shipment list could not be reloaded. Refresh the page before continuing.',
    });

    const confirmFetch = vi.fn()
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ data: {
          recoveryId: '92',
          shipmentId: '1',
          replacementOrderId: '11',
          shipmentStatus: 'booking_failed',
          recoveryState: 'awaiting_replacement',
          cancellationState: 'error',
          cancellationMessage: 'EasyParcel cancellation unconfirmed.',
          cancellationConfirmed: false,
        } }),
      })
      .mockResolvedValueOnce({ ok: false, json: async () => ({ message: 'reload failed' }) });
    vi.stubGlobal('window', { fetch: confirmFetch });

    await expect(confirmEasyParcelProviderFailureWithReload(
      bootstrap,
      '1',
      'Provider portal reports Failed.',
    )).resolves.toMatchObject({
      result: { recoveryId: '92', recoveryState: 'awaiting_replacement' },
      shipments: null,
      replacementParcel: null,
      reloadWarning: 'Shipment list could not be reloaded. Refresh the page before continuing.',
    });
  });

  it('includes the provider check timestamp in every exact status diagnostic', () => {
    expect(easyParcelShipmentStatusDiagnostic({
      shipmentId: '1',
      status: 'on_hold',
      changed: true,
      providerStatusCode: 8,
      providerStatus: 'On Hold',
      checkedAt: '2026-08-30T12:05:00+08:00',
      awbAvailable: false,
      recoveryDisposition: 'none',
      recovery: null,
    })).toBe('EasyParcel reports On Hold (code 8); AWB is still unavailable. Checked 2026-08-30T12:05:00+08:00.');
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
        onOpenEasyParcelBooking={vi.fn()}
        onRefreshEasyParcelStatus={vi.fn()}
        onRefreshAllEasyParcelStatuses={vi.fn()}
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

  it('renders unified and per-row Get Latest Status controls for an EasyParcel AWB', () => {
    const parcel = {
      ...redactedParcel,
      source: 'easyparcel',
      courier: 'easyparcel',
      trackingNumber: 'AWB-LATEST-STATUS-1001',
      status: 'ready_to_ship' as const,
    };
    const html = renderToStaticMarkup(
      <ShippingFulfillmentTab
        parcels={[parcel]}
        onGenerateAWB={vi.fn()}
        onBatchGenerateAWB={vi.fn()}
        onOpenAWBModal={vi.fn()}
        onOpenTrackingModal={vi.fn()}
        onOpenPickupModal={vi.fn()}
        onUpdateStatus={vi.fn()}
        onOpenEasyParcelBooking={vi.fn()}
        onRefreshEasyParcelStatus={vi.fn()}
        onRefreshAllEasyParcelStatuses={vi.fn()}
      />,
    );

    expect(html).toContain('Get Latest Status');
    expect(html).toContain('Refresh this EasyParcel shipment status');
  });

  it('uses status reconciliation instead of offering a duplicate booking while an EasyParcel AWB is pending', () => {
    const parcel = {
      ...redactedParcel,
      source: 'easyparcel',
      courier: 'easyparcel',
      trackingNumber: '',
      status: 'pending' as const,
    };
    const html = renderToStaticMarkup(
      <ShippingFulfillmentTab
        parcels={[parcel]}
        onGenerateAWB={vi.fn()}
        onBatchGenerateAWB={vi.fn()}
        onOpenAWBModal={vi.fn()}
        onOpenTrackingModal={vi.fn()}
        onOpenPickupModal={vi.fn()}
        onUpdateStatus={vi.fn()}
        onOpenEasyParcelBooking={vi.fn()}
        onRefreshEasyParcelStatus={vi.fn()}
        onRefreshAllEasyParcelStatuses={vi.fn()}
      />,
    );

    expect(html).toContain('Get Latest Status');
    expect(html).toContain('AWB Pending');
    expect(html).not.toContain('Gen AWB');
  });

  it('keeps bundled shipment-list examples within the redacted DTO boundary', () => {
    for (const parcel of INITIAL_SHIPMENT_PARCELS) {
      expect(parcel).not.toHaveProperty('customerPhone');
      expect(parcel).not.toHaveProperty('customerEmail');
      expect(parcel).not.toHaveProperty('recipientAddress');
    }
  });
});
