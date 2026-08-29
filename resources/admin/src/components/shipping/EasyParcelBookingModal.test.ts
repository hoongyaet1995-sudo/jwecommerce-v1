// @vitest-environment jsdom
import React, { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { EasyParcelQuote } from '../../integration/easyParcelFulfillmentData';
import type { ShipmentParcel } from '../../types/shipping';
import { EasyParcelBookingModal } from './EasyParcelBookingModal';

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

const deferred = <T,>() => {
  let resolve!: (value: T) => void;
  const promise = new Promise<T>((settle) => { resolve = settle; });
  return { promise, resolve };
};

const setInputValue = (input: HTMLInputElement, value: string) => {
  const setValue = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')?.set;
  setValue?.call(input, value);
  input.dispatchEvent(new Event('input', { bubbles: true }));
};

const parcel = (id: string): ShipmentParcel => ({
  id,
  orderId: `order-${id}`,
  orderNumber: `#${id}`,
  customerName: 'Test Customer',
  destinationPostcode: '50000',
  items: [{ sku: 'SKU-1', name: 'Test item', qty: 1, unitPrice: 20, weightKg: 1.25 }],
  totalWeightKg: 1.25,
  volumetricWeightKg: 0.5,
  dimensionsCm: { length: 28, width: 18, height: 8 },
  courier: 'easyparcel',
  serviceType: 'standard',
  trackingNumber: '',
  routingSortCode: '',
  status: 'pending',
  paymentMode: 'prepaid',
  isEastMalaysiaAirRestricted: false,
  postcodeValidationStatus: 'valid',
  createdAt: '2026-08-29T00:00:00Z',
  systemQuotedCharge: null,
  isWeightDiscrepancy: false,
  trackingEvents: [],
});

const staleQuote: EasyParcelQuote = {
  id: 'stale-quote',
  orderId: 'order-parcel-a',
  expiresAt: null,
  package: { weightKg: 1.25, lengthCm: 28, widthCm: 18, heightCm: 8 },
  services: [{ serviceCode: 'STALE', serviceName: 'Stale Courier', courierCode: 'stale', courierName: 'Stale Courier', deliveryDuration: '1 day', isPickup: true, isDropoff: false, supportsCod: false, currency: 'MYR', amount: 10 }],
};

const mountedRoots: Array<{ root: Root; host: HTMLDivElement }> = [];

afterEach(() => {
  mountedRoots.splice(0).forEach(({ root, host }) => {
    act(() => root.unmount());
    host.remove();
  });
  vi.useRealTimers();
});

describe('EasyParcelBookingModal quote lifecycle', () => {
  it('clears loading and ignores a pending quote after package edits or parcel switches', async () => {
    const pendingQuote = deferred<EasyParcelQuote>();
    const quote = () => pendingQuote.promise;
    const host = document.createElement('div');
    document.body.append(host);
    const root = createRoot(host);
    mountedRoots.push({ root, host });
    const render = (activeParcel: ShipmentParcel) => root.render(React.createElement(EasyParcelBookingModal, {
      isOpen: true,
      parcel: activeParcel,
      onClose: () => undefined,
      onQuote: quote,
      onBook: async () => ({ id: 'booking', orderId: activeParcel.orderId, quoteId: 'quote', fulfillmentStatus: 'ready_to_ship', trackingNumber: null, hasLabel: true }),
      onBooked: async () => undefined,
    }));

    await act(async () => { render(parcel('parcel-a')); });
    const quoteButton = Array.from(host.querySelectorAll('button')).find((button) => button.textContent?.includes('Re-fetch Quotes')) as HTMLButtonElement;
    await act(async () => { quoteButton.click(); });
    expect(host.textContent).toContain('Fetching EasyParcel rates…');

    const weight = host.querySelector('#easyparcel-package-weight') as HTMLInputElement;
    await act(async () => {
      setInputValue(weight, '2');
    });
    expect(host.textContent).not.toContain('Fetching EasyParcel rates…');
    expect(Array.from(host.querySelectorAll('button')).find((button) => button.textContent?.includes('Re-fetch Quotes'))).toBeTruthy();

    await act(async () => { render(parcel('parcel-b')); });
    expect(host.textContent).not.toContain('Fetching EasyParcel rates…');

    await act(async () => { pendingQuote.resolve(staleQuote); });
    expect(host.textContent).not.toContain('Stale Courier');
    expect(host.textContent).not.toContain('Fetching EasyParcel rates…');
    const refreshedQuoteButton = Array.from(host.querySelectorAll('button')).find((button) => button.textContent?.includes('Re-fetch Quotes')) as HTMLButtonElement;
    expect(refreshedQuoteButton.disabled).toBe(false);
    const bookingButton = Array.from(host.querySelectorAll('button')).find((button) => button.textContent?.includes('Confirm Booking')) as HTMLButtonElement;
    expect(bookingButton.disabled).toBe(true);
  });

  it('recomputes quote expiry while the modal is idle', async () => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-08-29T00:00:00Z'));
    const host = document.createElement('div');
    document.body.append(host);
    const root = createRoot(host);
    mountedRoots.push({ root, host });
    const expiringQuote: EasyParcelQuote = {
      ...staleQuote,
      id: 'expiring-quote',
      expiresAt: '2026-08-29T00:00:01Z',
      services: [{ ...staleQuote.services[0], serviceCode: 'EXPIRING', serviceName: 'Expiring Courier' }],
    };

    await act(async () => {
      root.render(React.createElement(EasyParcelBookingModal, {
        isOpen: true,
        parcel: parcel('parcel-expiry'),
        onClose: () => undefined,
        onQuote: async () => expiringQuote,
        onBook: async () => ({ id: 'booking', orderId: 'order-parcel-expiry', quoteId: 'expiring-quote', fulfillmentStatus: 'ready_to_ship', trackingNumber: null, hasLabel: true }),
        onBooked: async () => undefined,
      }));
    });

    const quoteButton = Array.from(host.querySelectorAll('button')).find((button) => button.textContent?.includes('Re-fetch Quotes')) as HTMLButtonElement;
    await act(async () => {
      quoteButton.click();
      await Promise.resolve();
    });
    expect(host.textContent).toContain('Expiring Courier');
    expect(host.textContent).not.toContain('Quote expired. Refresh rates to continue.');

    await act(async () => {
      vi.advanceTimersByTime(1_100);
    });

    expect(host.textContent).toContain('Quote expired. Refresh rates to continue.');
  });
});
