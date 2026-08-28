import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';
import { CourierConfigModal } from './CourierConfigModal';
import { CourierConfigTab } from './CourierConfigTab';
import type { CourierConfig } from '../../types/shipping';

const source = readFileSync(resolve(process.cwd(), 'src/components/shipping/ShippingDashboard.tsx'), 'utf8');

describe('ShippingDashboard provider action binding', () => {
  it('binds the existing EasyParcel provider and fulfillment actions from AdminContext before passing them to source UI controls', () => {
    expect(source).toContain('saveCourierConfig,');
    expect(source).toContain('generateShipmentAWB,');
    expect(source).toContain('refreshEasyParcelProvider,');
  });

  it('renders an unavailable EasyParcel wallet as inert rather than a fabricated balance', () => {
    const courier = {
      id: 'cfg_easyparcel',
      courierCode: 'easyparcel',
      name: 'EasyParcel Multi-Carrier',
      displayName: 'EasyParcel Multi-Carrier',
      logo: '',
      category: 'aggregator_api',
      isEnabled: false,
      environment: 'sandbox',
      apiKey: '',
      apiSecret: '',
      senderHubName: '',
      senderHubAddress: '',
      senderHubPostcode: '',
      senderHubState: '',
      senderContactPhone: '',
      supportsCOD: false,
      codFeePercent: 0,
      supportsEastMalaysia: false,
      supportsAirFreightHazmat: false,
      supportedServices: [],
      status: 'pending_keys',
      webhookEndpointUrl: '',
      autoRetryQueue: false,
      maxRetryAttempts: 0,
    } satisfies CourierConfig;

    const html = renderToStaticMarkup(React.createElement(CourierConfigModal, {
      isOpen: true,
      config: courier,
      onClose: () => undefined,
      onSave: () => undefined,
      onPing: async () => 0,
      onTopUpWallet: () => undefined,
    }));

    expect(html).toContain('Unavailable');
    expect(html).not.toContain('RM 0.00');
    expect(html).toMatch(/<button[^>]*disabled=""[^>]*title="Wallet top-up is not available through this integration"/);
  });

  it('renders the unavailable EasyParcel card top-up control as disabled', () => {
    const courier = {
      id: 'cfg_easyparcel',
      courierCode: 'easyparcel',
      name: 'EasyParcel Multi-Carrier',
      displayName: 'EasyParcel Multi-Carrier',
      logo: '',
      category: 'aggregator_api',
      isEnabled: false,
      environment: 'sandbox',
      apiKey: '',
      apiSecret: '',
      senderHubName: '',
      senderHubAddress: '',
      senderHubPostcode: '',
      senderHubState: '',
      senderContactPhone: '',
      supportsCOD: false,
      codFeePercent: 0,
      supportsEastMalaysia: false,
      supportsAirFreightHazmat: false,
      supportedServices: [],
      status: 'pending_keys',
      webhookEndpointUrl: '',
      autoRetryQueue: false,
      maxRetryAttempts: 0,
    } satisfies CourierConfig;

    const html = renderToStaticMarkup(React.createElement(CourierConfigTab, {
      couriers: [courier],
      onToggleCourier: () => undefined,
      onOpenConfigModal: () => undefined,
      onPingCourier: async () => 0,
      onTopUpEasyParcel: () => undefined,
    }));

    expect(html).toContain('Unavailable');
    expect(html).toMatch(/<button[^>]*disabled=""[^>]*title="Wallet top-up is not available through this integration"/);
  });
});
