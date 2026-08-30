// @vitest-environment jsdom
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import React, { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { renderToStaticMarkup } from 'react-dom/server';
import { afterEach, describe, expect, it, vi } from 'vitest';
const mockedAdminContext = vi.hoisted(() => ({ value: {} as Record<string, unknown> }));
vi.mock('../../context/AdminContext', () => ({ useAdmin: () => mockedAdminContext.value }));
import { CourierConfigModal } from './CourierConfigModal';
import { EasyParcelOAuthModal } from './EasyParcelOAuthModal';
import { EasyParcelBookingModal } from './EasyParcelBookingModal';
import { CourierConfigTab } from './CourierConfigTab';
import { ShippingDashboard } from './ShippingDashboard';
import { ShippingFulfillmentTab } from './ShippingFulfillmentTab';
import { ShippingOverviewTab } from './ShippingOverviewTab';
import { TrackingAndWebhooksTab } from './TrackingAndWebhooksTab';
import type { CourierConfig, EasyParcelWebhookReceiptRow } from '../../types/shipping';
import { INITIAL_SHIPMENT_PARCELS } from '../../data/shippingData';

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

const metricCardText = (html: string, label: string): string => {
  const host = document.createElement('div');
  host.innerHTML = html;
  const labelNode = Array.from(host.querySelectorAll('span'))
    .find(node => node.textContent === label);

  return labelNode?.closest('.cursor-pointer')?.textContent ?? '';
};

afterEach(() => {
  mountedRoots.splice(0).forEach(({ root, host }) => {
    act(() => root.unmount());
    host.remove();
  });
});

describe('ShippingDashboard provider action binding', () => {
  it('shows the stable EasyParcel callback URL using the existing courier callback presentation', () => {
    const html = renderToStaticMarkup(React.createElement(EasyParcelOAuthModal, {
      isOpen: true,
      provider: null,
      webhookCallbackUrl: 'https://www.ramarama.com/webhooks/easyparcel',
      onClose: () => undefined,
      onAuthorize: () => undefined,
      onDisconnect: async () => undefined,
    }));

    expect(html).toContain('Courier Webhook Callback URL');
    expect(html).toContain('https://www.ramarama.com/webhooks/easyparcel');
    expect(html).toContain('Copy');
  });

  it('copies the stable EasyParcel callback URL from the existing copy control', () => {
    const writeText = vi.fn();
    Object.defineProperty(navigator, 'clipboard', {
      configurable: true,
      value: { writeText },
    });
    const host = document.createElement('div');
    document.body.append(host);
    const root = createRoot(host);
    mountedRoots.push({ root, host });

    act(() => root.render(React.createElement(EasyParcelOAuthModal, {
      isOpen: true,
      provider: null,
      webhookCallbackUrl: 'https://www.ramarama.com/webhooks/easyparcel',
      onClose: () => undefined,
      onAuthorize: () => undefined,
      onDisconnect: async () => undefined,
    })));
    const copyButton = Array.from(host.querySelectorAll('button'))
      .find(button => button.textContent?.trim() === 'Copy');

    act(() => copyButton?.click());

    expect(writeText).toHaveBeenCalledWith('https://www.ramarama.com/webhooks/easyparcel');
  });

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

  it('opens recovery guidance for an aged pending AWB and starts a fresh quote only after confirmation', async () => {
    const parcel = {
      ...INITIAL_SHIPMENT_PARCELS[0],
      id: 'aged-pending-awb',
      orderId: 'replacement-order-77',
      orderNumber: 'JW-AGED-PENDING-AWB',
      source: 'easyparcel' as const,
      courier: 'easyparcel' as const,
      trackingNumber: '',
      status: 'pending' as const,
    };
    const onOpenEasyParcelBooking = vi.fn();
    const onConfirmEasyParcelProviderFailure = vi.fn().mockResolvedValue({
      recoveryId: 'recovery-91',
      shipmentId: parcel.id,
      replacementOrderId: parcel.orderId,
      shipmentStatus: 'booking_failed',
      recoveryState: 'awaiting_replacement',
      cancellationState: 'error',
      cancellationMessage: 'EasyParcel cancellation unconfirmed.',
      cancellationConfirmed: false,
    });
    const host = document.createElement('div');
    document.body.append(host);
    const root = createRoot(host);
    mountedRoots.push({ root, host });

    await act(async () => root.render(React.createElement(ShippingFulfillmentTab, {
      parcels: [parcel],
      onGenerateAWB: vi.fn(),
      onBatchGenerateAWB: vi.fn(),
      onOpenAWBModal: vi.fn(),
      onOpenTrackingModal: vi.fn(),
      onOpenPickupModal: vi.fn(),
      onUpdateStatus: vi.fn(),
      onOpenEasyParcelBooking,
      onRefreshEasyParcelStatus: vi.fn().mockResolvedValue({
        shipmentId: parcel.id,
        status: 'pending',
        changed: false,
        providerStatusCode: 7,
        providerStatus: 'Schedule In Arrangement',
        checkedAt: '2026-08-30T10:00:00+08:00',
        awbAvailable: false,
        recoveryDisposition: 'needs_review',
      }),
      onRefreshAllEasyParcelStatuses: vi.fn().mockResolvedValue([]),
      onConfirmEasyParcelProviderFailure,
    })));

    const refresh = host.querySelector('button[aria-label="Get Latest Status"]') as HTMLButtonElement;
    await act(async () => refresh.click());

    expect(host.textContent).toContain('EasyParcel reports Schedule In Arrangement (code 7); AWB is still unavailable.');
    expect(host.textContent).toContain('Confirm Provider Failure & Rebook');
    expect(onOpenEasyParcelBooking).not.toHaveBeenCalled();

    const reason = host.querySelector('textarea') as HTMLTextAreaElement;
    await act(async () => {
      const setValue = Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype, 'value')?.set;
      setValue?.call(reason, 'EasyParcel portal explicitly reports Failed.');
      reason.dispatchEvent(new Event('input', { bubbles: true }));
    });
    const confirm = Array.from(host.querySelectorAll('button'))
      .find(button => button.textContent?.includes('Confirm Provider Failure & Rebook')) as HTMLButtonElement;
    await act(async () => confirm.click());

    expect(onConfirmEasyParcelProviderFailure).toHaveBeenCalledWith(
      parcel.id,
      'EasyParcel portal explicitly reports Failed.',
    );
    expect(host.textContent).toContain('EasyParcel cancellation unconfirmed.');
    expect(host.textContent).toContain('Cancellation was not confirmed by EasyParcel.');
    expect(onOpenEasyParcelBooking).toHaveBeenCalledTimes(1);
    expect(onOpenEasyParcelBooking).toHaveBeenCalledWith(expect.objectContaining({
      orderId: 'replacement-order-77',
    }));
  });

  it('keeps an unconfirmed provider cancellation warning visible in the existing booking modal', () => {
    const parcel = {
      ...INITIAL_SHIPMENT_PARCELS[0],
      id: 'replacement-order-warning',
      source: 'pending' as const,
      status: 'pending' as const,
      trackingNumber: '',
      easyParcelRecoveryWarning: 'EasyParcel cancellation unconfirmed.',
    };

    const html = renderToStaticMarkup(React.createElement(EasyParcelBookingModal, {
      isOpen: true,
      parcel,
      onClose: vi.fn(),
      onQuote: vi.fn(),
      onBook: vi.fn(),
      onBooked: vi.fn(),
    }));

    expect(html).toContain('Cancellation was not confirmed by EasyParcel.');
    expect(html).toContain('EasyParcel cancellation unconfirmed.');
  });

  it('opens provider-terminal recovery guidance from the global status refresh', async () => {
    const parcel = {
      ...INITIAL_SHIPMENT_PARCELS[0],
      id: 'provider-terminal-shipment',
      orderId: 'provider-terminal-order',
      source: 'easyparcel' as const,
      courier: 'easyparcel' as const,
      trackingNumber: '',
      status: 'pending' as const,
    };
    const onOpenEasyParcelBooking = vi.fn();
    const host = document.createElement('div');
    document.body.append(host);
    const root = createRoot(host);
    mountedRoots.push({ root, host });

    await act(async () => root.render(React.createElement(ShippingFulfillmentTab, {
      parcels: [parcel],
      onGenerateAWB: vi.fn(),
      onBatchGenerateAWB: vi.fn(),
      onOpenAWBModal: vi.fn(),
      onOpenTrackingModal: vi.fn(),
      onOpenPickupModal: vi.fn(),
      onUpdateStatus: vi.fn(),
      onOpenEasyParcelBooking,
      onRefreshEasyParcelStatus: vi.fn(),
      onRefreshAllEasyParcelStatuses: vi.fn().mockResolvedValue([{
        shipmentId: parcel.id,
        status: 'cancelled',
        changed: true,
        providerStatusCode: 0,
        providerStatus: 'Cancelled',
        checkedAt: '2026-08-30T11:00:00+08:00',
        awbAvailable: false,
        recoveryDisposition: 'provider_terminal',
      }]),
      onConfirmEasyParcelProviderFailure: vi.fn(),
    })));

    const globalRefresh = Array.from(host.querySelectorAll('button'))
      .find(button => button.textContent?.trim() === 'Get Latest Status') as HTMLButtonElement;
    await act(async () => globalRefresh.click());

    expect(host.textContent).toContain('EasyParcel reports Cancelled (code 0); AWB is still unavailable.');
    const rebook = Array.from(host.querySelectorAll('button'))
      .find(button => button.textContent?.includes('Start Fresh Quote & Rebook')) as HTMLButtonElement;
    await act(async () => rebook.click());
    expect(onOpenEasyParcelBooking).toHaveBeenCalledWith(expect.objectContaining({
      orderId: 'provider-terminal-order',
      source: 'pending',
    }));
  });

  it('counts canonical shipped parcels in the ShippingDashboard in-transit badge', () => {
    mockedAdminContext.value = {
      courierConfigs: [],
      smartRoutingRules: [],
      shipmentParcels: [{
        ...INITIAL_SHIPMENT_PARCELS[0],
        id: 'canonical-shipped-dashboard',
        status: 'shipped' as const,
      }],
      easyParcelWebhookReceipts: [],
      easyParcelWebhookAuditStatus: 'ready',
      easyParcelWebhookAuditError: null,
      refreshEasyParcelWebhookReceipts: async () => undefined,
      pickupRequests: [],
      billingReconciliations: [],
      shippingRates: [],
      addToast: vi.fn(),
    };

    const html = renderToStaticMarkup(React.createElement(ShippingDashboard));

    expect(html).toContain('1 In-Transit');
  });

  it('counts canonical shipped parcels in the ShippingOverview in-transit metric', () => {
    const html = renderToStaticMarkup(React.createElement(ShippingOverviewTab, {
      parcels: [{
        ...INITIAL_SHIPMENT_PARCELS[0],
        id: 'canonical-shipped-overview',
        status: 'shipped' as const,
      }],
      couriers: [],
      pickupRequests: [],
      onNavigateTab: () => undefined,
      onOpenPickupModal: () => undefined,
    }));

    expect(metricCardText(html, 'In-Transit Shipments')).toContain('1');
  });

  it('counts returned parcels in the ShippingOverview exceptions metric', () => {
    const html = renderToStaticMarkup(React.createElement(ShippingOverviewTab, {
      parcels: [{
        ...INITIAL_SHIPMENT_PARCELS[0],
        id: 'canonical-returned-overview',
        status: 'returned' as const,
      }],
      couriers: [],
      pickupRequests: [],
      onNavigateTab: () => undefined,
      onOpenPickupModal: () => undefined,
    }));

    expect(metricCardText(html, 'Exceptions & NITC')).toContain('1');
  });

  it('treats booking failures, cancellations, and holds as shipping exceptions', () => {
    const exceptionParcels = (['booking_failed', 'cancelled', 'on_hold'] as const).map((status, index) => ({
      ...INITIAL_SHIPMENT_PARCELS[0],
      id: `easyparcel-exception-${index}`,
      status,
    }));
    const overview = renderToStaticMarkup(React.createElement(ShippingOverviewTab, {
      parcels: exceptionParcels,
      couriers: [],
      pickupRequests: [],
      onNavigateTab: () => undefined,
      onOpenPickupModal: () => undefined,
    }));
    const fulfillment = renderToStaticMarkup(React.createElement(ShippingFulfillmentTab, {
      parcels: exceptionParcels,
      onGenerateAWB: vi.fn(),
      onBatchGenerateAWB: vi.fn(),
      onOpenAWBModal: vi.fn(),
      onOpenTrackingModal: vi.fn(),
      onOpenPickupModal: vi.fn(),
      onUpdateStatus: vi.fn(),
      onOpenEasyParcelBooking: vi.fn(),
      onRefreshEasyParcelStatus: vi.fn(),
      onRefreshAllEasyParcelStatuses: vi.fn(),
      onConfirmEasyParcelProviderFailure: vi.fn(),
    }));

    expect(metricCardText(overview, 'Exceptions & NITC')).toContain('3');
    expect(fulfillment).toMatch(/Exceptions \(NITC\)<\/span><span[^>]*>3<\/span>/);
    expect(fulfillment).toContain('Booking Failed');
    expect(fulfillment).toContain('Cancelled');
    expect(fulfillment).toContain('On Hold');
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

  it('offers only manual shipments as manual lifecycle event targets', () => {
    const manual = {
      ...INITIAL_SHIPMENT_PARCELS[0],
      id: 'manual-target',
      orderNumber: 'JW-MANUAL-TARGET',
      source: 'manual' as const,
      trackingEvents: [],
    };
    const provider = {
      ...INITIAL_SHIPMENT_PARCELS[1],
      id: 'easyparcel-provider-truth',
      orderNumber: 'JW-EASYPARCEL-PROVIDER-TRUTH',
      source: 'easyparcel' as const,
      trackingEvents: [],
    };

    const html = renderToStaticMarkup(React.createElement(TrackingAndWebhooksTab, trackingProps({
      parcels: [provider, manual],
    })));

    expect(html).toContain('JW-MANUAL-TARGET');
    expect(html).not.toContain('JW-EASYPARCEL-PROVIDER-TRUTH');
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

  it('marks retained receipt rows as cached throughout a failed audit view', () => {
    const failedWithRows = renderToStaticMarkup(React.createElement(TrackingAndWebhooksTab, trackingProps({
      webhookReceipts: [receipt({ id: 'cached-receipt' })],
      webhookAuditStatus: 'error',
      webhookAuditError: 'EasyParcel webhook audit could not be loaded.',
    })));

    expect(failedWithRows).toContain('Showing last successful provider receipts below');
    expect(failedWithRows).toContain('Cached provider receipt');
    expect(failedWithRows).toContain('cached provider data');
    expect(failedWithRows).toContain('Webhook audit unavailable · cached receipts shown');
    expect(failedWithRows).not.toContain('Latest: processed');
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

  it('invokes one bounded refresh on tab entry and another from the existing audit refresh action', async () => {
    const refreshAudit = vi.fn().mockResolvedValue(undefined);
    mockedAdminContext.value = {
      courierConfigs: [],
      smartRoutingRules: [],
      shipmentParcels: [],
      easyParcelWebhookReceipts: [],
      easyParcelWebhookAuditStatus: 'ready',
      easyParcelWebhookAuditError: null,
      refreshEasyParcelWebhookReceipts: refreshAudit,
      pickupRequests: [],
      billingReconciliations: [],
      shippingRates: [],
      addToast: vi.fn(),
    };
    const host = document.createElement('div');
    document.body.append(host);
    const root = createRoot(host);
    mountedRoots.push({ root, host });

    await act(async () => {
      root.render(React.createElement(ShippingDashboard));
    });
    const trackingTab = Array.from(host.querySelectorAll('button'))
      .find(button => button.textContent?.includes('Webhooks & Telemetry')) as HTMLButtonElement;
    await act(async () => { trackingTab.click(); });

    expect(refreshAudit).toHaveBeenCalledTimes(1);

    const refreshButton = Array.from(host.querySelectorAll('button'))
      .find(button => button.textContent?.includes('Refresh audit')) as HTMLButtonElement;
    await act(async () => { refreshButton.click(); });

    expect(refreshAudit).toHaveBeenCalledTimes(2);
  });
});
