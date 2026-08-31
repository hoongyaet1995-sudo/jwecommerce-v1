// @vitest-environment jsdom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { renderToStaticMarkup } from 'react-dom/server';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { ThermalAWBModal } from '../components/shipping/ThermalAWBModal';
import { INITIAL_SHIPMENT_PARCELS } from '../data/shippingData';
import type { ShipmentParcel } from '../types/shipping';

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

const roots: Array<{ root: ReturnType<typeof createRoot>; host: HTMLDivElement }> = [];
const officialParcel = (overrides: Partial<ShipmentParcel> = {}): ShipmentParcel => ({
  ...INITIAL_SHIPMENT_PARCELS[0],
  id: '42',
  source: 'easyparcel',
  trackingNumber: 'EPSAMPLE-42',
  hasLabel: true,
  ...overrides,
} as ShipmentParcel);

afterEach(() => {
  roots.splice(0).forEach(({ root, host }) => {
    act(() => root.unmount());
    host.remove();
  });
  vi.restoreAllMocks();
});

describe('ThermalAWBModal official EasyParcel label', () => {
  it('embeds the protected official PDF and never redraws a simulated courier label', () => {
    const html = renderToStaticMarkup(React.createElement(ThermalAWBModal, {
      parcel: officialParcel(),
      onClose: () => undefined,
    }));

    expect(html).toContain('title="Official EasyParcel AWB PDF"');
    expect(html).toContain('/admin/api/shipping/shipments/42/label');
    expect(html).toContain('/admin/api/shipping/shipments/42/label/download');
    expect(html).toContain('Download AWB PDF');
    expect(html).not.toContain('Aurora Commerce Hub HQ');
    expect(html).not.toContain('SCAN-AWB');
    expect(html).not.toContain('Simulated crisp Barcode');
  });

  it('opens the same-origin official PDF when staff prints the AWB', () => {
    const open = vi.spyOn(window, 'open').mockReturnValue(null);
    const host = document.createElement('div');
    document.body.append(host);
    const root = createRoot(host);
    roots.push({ root, host });
    act(() => root.render(React.createElement(ThermalAWBModal, {
      parcel: officialParcel(),
      onClose: () => undefined,
    })));

    const print = Array.from(host.querySelectorAll('button'))
      .find(button => button.textContent?.includes('Print Thermal AWB')) as HTMLButtonElement;
    expect(print.disabled).toBe(false);
    act(() => print.click());

    expect(open).toHaveBeenCalledWith(
      '/admin/api/shipping/shipments/42/label',
      '_blank',
      'noopener,noreferrer',
    );
  });

  it('shows truthful unavailable states for missing and manual provider labels', () => {
    const pending = renderToStaticMarkup(React.createElement(ThermalAWBModal, {
      parcel: officialParcel({ hasLabel: false }),
      onClose: () => undefined,
    }));
    const manual = renderToStaticMarkup(React.createElement(ThermalAWBModal, {
      parcel: officialParcel({ source: 'manual', hasLabel: false }),
      onClose: () => undefined,
    }));

    expect(pending).toContain('Official EasyParcel AWB is still pending');
    expect(pending).toContain('disabled=""');
    expect(manual).toContain('Manual dispatch does not have a provider AWB');
  });
});
