// @vitest-environment jsdom
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import React, { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { renderToStaticMarkup } from 'react-dom/server';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { CourierConfigModal } from './CourierConfigModal';
import { CourierConfigTab } from './CourierConfigTab';
import { TrackingAndWebhooksTab } from './TrackingAndWebhooksTab';
import type { CourierConfig, EasyParcelWebhookReceiptRow } from '../../types/shipping';

const source = readFileSync(resolve(process.cwd(), 'src/components/shipping/ShippingDashboard.tsx'), 'utf8');
globalThis.IS_REACT_ACT_ENVIRONMENT = true;

const receipt = (overrides: Partial<EasyParcelWebhookReceiptRow> = {}): EasyParcelWebhookReceiptRow => ({
  id: 'receipt-41',
  topic: 'shipment.tracking.update',
  state: 'processed',
  shipmentId: 'shipment-9',
  orderNumber: 'JW-1009',
  trackingNumber: 'AWB-1009',
  receivedAt: '2026-08-29T10:00:00+08:00',
  processedAt: '2026-08-29T10:00:01+08:00',
  attemptCount: 1,
  errorCode: null,
  ...overrides,
});

const trackingProps = (overrides: Record<string, unknown> = {}) => ({
  parcels: [],
  webhookReceipts: [],
  webhookAuditStatus: 'ready',
  webhookAuditError: null,
  webhookEndpointConfigured: true,
  providerConnected: true,
  onRefreshWebhookAudit: async () => undefined,
  onSimulateWebhook: () => undefined,
  onOpenTrackingModal: () => undefined,
  ...overrides,
});

const mountedRoots: Array<{ root: Root; host: HTMLDivElement }> = [];

afterEach(() => {
  mountedRoots.splice(0).forEach(({ root, host }) => {
    act(() => root.unmount());
    host.remove();
  });
});

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

  it('binds sanitized provider receipts into the existing manual audit feed language', () => {
    const html = renderToStaticMarkup(React.createElement(TrackingAndWebhooksTab, trackingProps({
      webhookReceipts: [receipt()],
    })));

    expect(html).toContain('Manual Event Entry');
    expect(html).toContain('Provider receipt');
    expect(html).toContain('shipment.tracking.update');
    expect(html).toContain('AWB-1009');
    expect(html).toContain('Provider authorized');
    expect(html).toContain('Webhook endpoint configured');
    expect(html).not.toContain('provider webhooks remain unavailable');
    expect(html).not.toContain('Manual audit mode');
    expect(html).not.toContain('Not connected');
    expect(html).not.toContain('queue connected');
  });

  it('renders loading, failure, and genuinely empty webhook audit states distinctly', () => {
    const loading = renderToStaticMarkup(React.createElement(TrackingAndWebhooksTab, trackingProps({
      webhookAuditStatus: 'loading',
    })));
    expect(loading).toContain('Loading EasyParcel webhook audit');
    expect(loading).not.toContain('No provider receipts received');

    const failed = renderToStaticMarkup(React.createElement(TrackingAndWebhooksTab, trackingProps({
      webhookAuditStatus: 'error',
      webhookAuditError: 'EasyParcel webhook audit could not be loaded.',
    })));
    expect(failed).toContain('Webhook audit unavailable');
    expect(failed).toContain('EasyParcel webhook audit could not be loaded.');
    expect(failed).not.toContain('No provider receipts received');

    const empty = renderToStaticMarkup(React.createElement(TrackingAndWebhooksTab, trackingProps()));
    expect(empty).toContain('No provider receipts received yet');
    expect(empty).not.toContain('Loading EasyParcel webhook audit');
    expect(empty).not.toContain('Webhook audit unavailable');
  });

  it('does not claim queue connectivity when OAuth is connected but the endpoint is not configured', () => {
    const html = renderToStaticMarkup(React.createElement(TrackingAndWebhooksTab, trackingProps({
      webhookEndpointConfigured: false,
      providerConnected: true,
    })));

    expect(html).toContain('Provider authorized');
    expect(html).toContain('Webhook endpoint not configured');
    expect(html).not.toContain('queue connected');
  });

  it('renders a nullable processed timestamp without inventing a reconciliation time', () => {
    const html = renderToStaticMarkup(React.createElement(TrackingAndWebhooksTab, trackingProps({
      webhookReceipts: [receipt({ state: 'queued', processedAt: null })],
    })));

    expect(html).toContain('Awaiting reconciliation');
    expect(html).not.toContain('null');
    expect(html).not.toContain('Invalid Date');
  });

  it('refreshes the existing audit card and renders newly returned receipt data', async () => {
    const host = document.createElement('div');
    document.body.append(host);
    const root = createRoot(host);
    mountedRoots.push({ root, host });
    const onRefresh = vi.fn(async () => {
      await act(async () => {
        root.render(React.createElement(TrackingAndWebhooksTab, trackingProps({
          webhookReceipts: [receipt({ id: 'receipt-new', trackingNumber: 'AWB-NEW-01' })],
          onRefreshWebhookAudit: onRefresh,
        })));
      });
    });

    await act(async () => {
      root.render(React.createElement(TrackingAndWebhooksTab, trackingProps({
        onRefreshWebhookAudit: onRefresh,
      })));
    });
    const refreshButton = Array.from(host.querySelectorAll('button'))
      .find(button => button.textContent?.includes('Refresh audit')) as HTMLButtonElement;

    await act(async () => { refreshButton.click(); });

    expect(onRefresh).toHaveBeenCalledTimes(1);
    expect(host.textContent).toContain('AWB-NEW-01');
    expect(host.textContent).not.toContain('No provider receipts received yet');
  });
});
