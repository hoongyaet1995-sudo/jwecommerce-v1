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
import type { CourierConfig, EasyParcelWebhookReceiptRow, ShipmentActionCapabilities, ShipmentParcel } from '../../types/shipping';
import { INITIAL_SHIPMENT_PARCELS } from '../../data/shippingData';

const source = readFileSync(resolve(process.cwd(), 'src/components/shipping/ShippingDashboard.tsx'), 'utf8');
const fulfillmentSource = readFileSync(resolve(process.cwd(), 'src/components/shipping/ShippingFulfillmentTab.tsx'), 'utf8');
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

const actionCapabilities = (enabled: Partial<ShipmentActionCapabilities> = {}): ShipmentActionCapabilities => ({
  fulfil: false, resume: false, printLabel: false, viewTimeline: false, correct: false,
  amendAddress: false, updateStatus: false, refreshProvider: false, copyTracking: false,
  ...enabled,
});

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
  it('renders More Actions through a portal so the table scroller cannot clip it', () => {
    expect(fulfillmentSource).toContain("from 'react-dom'");
    expect(fulfillmentSource).toContain('createPortal(');
  });

  it('preserves the selected Google AI Studio Actions visual primitives', () => {
    expect(fulfillmentSource).toContain('bg-indigo-600 hover:bg-indigo-700');
    expect(fulfillmentSource).toContain('<MoreHorizontal className="w-4 h-4" />');
    expect(fulfillmentSource).toContain('flex items-center gap-2.5 px-3 py-2');
  });

  it('keeps the approved logistics reload and single-line shipment action affordances', () => {
    expect(source).toContain('Reload Logistics');
  });
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

  it('selects the existing production account card only when the server reports its authorization gate is ready', () => {
    const onAuthorize = vi.fn();
    const host = document.createElement('div');
    document.body.append(host);
    const root = createRoot(host);
    mountedRoots.push({ root, host });

    act(() => root.render(React.createElement(EasyParcelOAuthModal, {
      isOpen: true,
      provider: {
        provider: 'easyparcel',
        environment: 'sandbox',
        configured: true,
        enabled: false,
        connectionState: 'unconfigured',
        lastVerifiedAt: null,
        lastError: null,
        environments: {
          production: { authorizationEnabled: true, configured: true, enabled: false, connectionState: 'unconfigured' },
        },
      },
      webhookCallbackUrl: 'https://www.ramarama.com/webhooks/easyparcel',
      onClose: () => undefined,
      onAuthorize,
      onDisconnect: async () => undefined,
    })));

    const liveAccount = Array.from(host.querySelectorAll('button'))
      .find(button => button.textContent?.includes('Live Account')) as HTMLButtonElement;
    expect(liveAccount.disabled).toBe(false);
    act(() => liveAccount.click());
    const continueToProvider = Array.from(host.querySelectorAll('button'))
      .find(button => button.textContent?.includes('Continue to EasyParcel')) as HTMLButtonElement;
    act(() => continueToProvider.click());

    expect(onAuthorize).toHaveBeenCalledWith('production');
  });

  it('lets a connected merchant edit and save the sender profile independently from OAuth', async () => {
    const onSaveSenderProfile = vi.fn().mockResolvedValue(undefined);
    const host = document.createElement('div');
    document.body.append(host);
    const root = createRoot(host);
    mountedRoots.push({ root, host });

    act(() => root.render(React.createElement(EasyParcelOAuthModal, {
      isOpen: true,
      provider: {
        provider: 'easyparcel',
        environment: 'sandbox',
        configured: true,
        enabled: true,
        connectionState: 'connected',
        lastVerifiedAt: '2026-08-31T00:00:00+00:00',
        lastError: null,
        senderProfileComplete: false,
        senderProfile: {
          name: 'JW Studio Dispatch',
          phone_number_country_code: 'MY',
          phone_number: '0142244200',
          postcode: '47600',
          city: 'Subang Jaya',
          subdivision_code: 'MY-10',
          country_code: 'MY',
        },
      },
      webhookCallbackUrl: 'https://www.ramarama.com/webhooks/easyparcel',
      onClose: () => undefined,
      onAuthorize: () => undefined,
      onDisconnect: async () => undefined,
      onSaveSenderProfile,
    })));

    expect(host.textContent).toContain('Sender Profile');
    expect(host.textContent).toContain('Required before quotes and bookings');
    const address = host.querySelector('input[name="address_1"]') as HTMLInputElement;
    expect(address).not.toBeNull();
    act(() => {
      Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')?.set?.call(address, '18 Test Industry Road');
      address.dispatchEvent(new Event('input', { bubbles: true }));
    });
    const save = Array.from(host.querySelectorAll('button'))
      .find(button => button.textContent?.includes('Save Sender Profile')) as HTMLButtonElement;

    await act(async () => save.click());

    expect(onSaveSenderProfile).toHaveBeenCalledWith('sandbox', expect.objectContaining({
      name: 'JW Studio Dispatch',
      address_1: '18 Test Industry Road',
    }));
    expect(host.querySelector('input[name="address_1"]')).toBeNull();
  });

  it('keeps a ready sender profile collapsed until the merchant chooses to edit it', () => {
    const host = document.createElement('div');
    document.body.append(host);
    const root = createRoot(host);
    mountedRoots.push({ root, host });

    act(() => root.render(React.createElement(EasyParcelOAuthModal, {
      isOpen: true,
      provider: {
        provider: 'easyparcel',
        environment: 'production',
        configured: true,
        enabled: true,
        connectionState: 'connected',
        lastVerifiedAt: '2026-08-31T00:00:00+00:00',
        lastError: null,
        senderProfileComplete: true,
        senderProfile: {
          name: 'JW Studio Dispatch',
          city: 'Subang Jaya',
          subdivision_code: 'MY-10',
        },
      },
      webhookCallbackUrl: 'https://www.ramarama.com/webhooks/easyparcel',
      onClose: () => undefined,
      onAuthorize: () => undefined,
      onDisconnect: async () => undefined,
    })));

    expect(host.textContent).toContain('JW Studio Dispatch');
    expect(host.textContent).toContain('Subang Jaya, MY-10');
    expect(host.querySelector('input[name="address_1"]')).toBeNull();

    const edit = Array.from(host.querySelectorAll('button'))
      .find(button => button.textContent?.includes('Edit Sender Profile')) as HTMLButtonElement;
    act(() => edit.click());

    expect(host.querySelector('input[name="address_1"]')).not.toBeNull();
  });

  it('binds the existing EasyParcel provider and fulfillment actions from AdminContext before passing them to source UI controls', () => {
    expect(source).toContain('saveCourierConfig,');
    expect(source).toContain('generateShipmentAWB,');
    expect(source).toContain('refreshEasyParcelProvider,');
  });

  it('does not offer EasyParcel wallet top-up from the generic courier modal', () => {
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
    }));

    expect(html).not.toContain('EasyParcel Prepaid Shipping Wallet Balance');
    expect(html).not.toContain('Top-Up');
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
      lifecycleStage: 'pending_fulfilment' as const,
      labelState: 'pending' as const,
      recoveryState: 'none' as const,
      primaryAction: null,
      capabilities: actionCapabilities({ refreshProvider: true }),
    };
    const onOpenEasyParcelBooking = vi.fn();
    const recovery = {
      recoveryId: 'recovery-91',
      state: 'awaiting_replacement',
      cancellationState: 'error' as const,
      cancellationMessage: 'EasyParcel cancellation unconfirmed.',
      cancellationConfirmed: false,
      replacementOrderId: parcel.orderId,
      readyToRebook: true,
    };
    const replacement = {
      ...parcel,
      id: `pending-order-${parcel.orderId}`,
      source: 'pending' as const,
      easyParcelRecovery: recovery,
      lifecycleStage: 'action_required' as const,
      labelState: 'not_required' as const,
      recoveryState: 'rebooking_ready' as const,
      primaryAction: 'resume' as const,
      capabilities: actionCapabilities({ resume: true, amendAddress: true }),
    };
    const onConfirmEasyParcelProviderFailure = vi.fn().mockResolvedValue({
      result: {
        recoveryId: recovery.recoveryId,
        shipmentId: parcel.id,
        replacementOrderId: parcel.orderId,
        shipmentStatus: 'booking_failed',
        recoveryState: 'awaiting_replacement',
        cancellationState: 'error',
        cancellationMessage: recovery.cancellationMessage,
        cancellationConfirmed: false,
      },
      shipments: [{ ...parcel, status: 'booking_failed', easyParcelRecovery: recovery }, replacement],
      replacementParcel: replacement,
      reloadWarning: null,
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
        result: {
          shipmentId: parcel.id,
          status: 'pending',
          changed: false,
          providerStatusCode: 7,
          providerStatus: 'Schedule In Arrangement',
          checkedAt: '2026-08-30T10:00:00+08:00',
          awbAvailable: false,
          recoveryDisposition: 'needs_review',
          recovery: null,
        },
        shipments: [parcel],
        replacementParcel: null,
        reloadWarning: null,
      }),
      onRefreshAllEasyParcelStatuses: vi.fn().mockResolvedValue([]),
      onConfirmEasyParcelProviderFailure,
    })));

    const more = host.querySelector('button[aria-label="More shipment operations"]') as HTMLButtonElement;
    await act(async () => more.click());
    const refresh = Array.from(document.body.querySelectorAll('button')).find(button => button.textContent?.trim() === 'Refresh EasyParcel Status') as HTMLButtonElement;
    await act(async () => refresh.click());

    expect(host.textContent).toContain('EasyParcel reports Schedule In Arrangement (code 7); AWB is still unavailable. Checked 2026-08-30T10:00:00+08:00.');
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
    expect(onOpenEasyParcelBooking).toHaveBeenCalledTimes(1);
    expect(onOpenEasyParcelBooking).toHaveBeenCalledWith(expect.objectContaining({
      orderId: 'replacement-order-77',
      easyParcelRecovery: recovery,
    }));
  });

  it('keeps an unconfirmed provider cancellation warning visible in the existing booking modal', () => {
    const parcel = {
      ...INITIAL_SHIPMENT_PARCELS[0],
      id: 'replacement-order-warning',
      source: 'pending' as const,
      status: 'pending' as const,
      trackingNumber: '',
      easyParcelRecovery: {
        recoveryId: 'recovery-warning',
        state: 'awaiting_replacement',
        cancellationState: 'error' as const,
        cancellationMessage: 'EasyParcel cancellation unconfirmed.',
        cancellationConfirmed: false,
        replacementOrderId: 'replacement-order-warning',
        readyToRebook: true,
      },
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

  it('opens audited provider-terminal recovery guidance from the global status refresh', async () => {
    const parcel = {
      ...INITIAL_SHIPMENT_PARCELS[0],
      id: 'provider-terminal-shipment',
      orderId: 'provider-terminal-order',
      source: 'easyparcel' as const,
      courier: 'easyparcel' as const,
      trackingNumber: '',
      status: 'pending' as const,
      lifecycleStage: 'pending_fulfilment' as const,
      labelState: 'pending' as const,
      recoveryState: 'none' as const,
      primaryAction: null,
      capabilities: actionCapabilities({ refreshProvider: true }),
    };
    const recovery = {
      recoveryId: 'global-recovery-94',
      state: 'awaiting_replacement',
      cancellationState: 'success' as const,
      cancellationMessage: 'EasyParcel cancellation confirmed by provider status code 0.',
      cancellationConfirmed: true,
      replacementOrderId: parcel.orderId,
      readyToRebook: true,
    };
    const replacement = {
      ...parcel,
      id: `pending-order-${parcel.orderId}`,
      source: 'pending' as const,
      status: 'pending' as const,
      easyParcelRecovery: recovery,
      lifecycleStage: 'action_required' as const,
      labelState: 'not_required' as const,
      recoveryState: 'rebooking_ready' as const,
      primaryAction: 'resume' as const,
      capabilities: actionCapabilities({ resume: true, amendAddress: true }),
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
        result: {
          shipmentId: parcel.id,
          status: 'cancelled',
          changed: true,
          providerStatusCode: 0,
          providerStatus: 'Cancelled',
          checkedAt: '2026-08-30T11:00:00+08:00',
          awbAvailable: false,
          recoveryDisposition: 'provider_terminal',
          recovery,
        },
        shipments: [{ ...parcel, status: 'cancelled', easyParcelRecovery: recovery }, replacement],
        replacementParcel: replacement,
        reloadWarning: null,
      }]),
      onConfirmEasyParcelProviderFailure: vi.fn(),
    })));

    const globalRefresh = Array.from(host.querySelectorAll('button'))
      .find(button => button.textContent?.trim() === 'Get Latest Status') as HTMLButtonElement;
    await act(async () => globalRefresh.click());

    expect(host.textContent).toContain('EasyParcel reports Cancelled (code 0); AWB is still unavailable. Checked 2026-08-30T11:00:00+08:00.');
    const rebook = Array.from(host.querySelectorAll('button'))
      .find(button => button.textContent?.includes('Continue to Fresh Quote & Rebook')) as HTMLButtonElement;
    await act(async () => rebook.click());
    expect(onOpenEasyParcelBooking).toHaveBeenCalledWith(expect.objectContaining({
      orderId: 'provider-terminal-order',
      source: 'pending',
      easyParcelRecovery: recovery,
    }));
  });

  it('renders provider code 8 as on hold without failure confirmation or rebook', async () => {
    const pending = {
      ...INITIAL_SHIPMENT_PARCELS[0],
      id: 'provider-on-hold-shipment',
      source: 'easyparcel' as const,
      courier: 'easyparcel' as const,
      trackingNumber: '',
      status: 'pending' as const,
      lifecycleStage: 'pending_fulfilment' as const,
      labelState: 'pending' as const,
      recoveryState: 'none' as const,
      primaryAction: null,
      capabilities: actionCapabilities({ refreshProvider: true }),
    };
    const onHold = { ...pending, status: 'on_hold' as const, lifecycleStage: 'action_required' as const, recoveryState: 'review_required' as const, primaryAction: 'reviewRecovery' as const };
    const host = document.createElement('div');
    document.body.append(host);
    const root = createRoot(host);
    mountedRoots.push({ root, host });

    const Harness = () => {
      const [rows, setRows] = React.useState([pending]);
      return React.createElement(ShippingFulfillmentTab, {
        parcels: rows,
        onGenerateAWB: vi.fn(),
        onBatchGenerateAWB: vi.fn(),
        onOpenAWBModal: vi.fn(),
        onOpenTrackingModal: vi.fn(),
        onOpenPickupModal: vi.fn(),
        onUpdateStatus: vi.fn(),
        onOpenEasyParcelBooking: vi.fn(),
        onRefreshEasyParcelStatus: async () => {
          setRows([onHold]);
          return {
            result: {
              shipmentId: pending.id,
              status: 'on_hold' as const,
              changed: true,
              providerStatusCode: 8,
              providerStatus: 'On Hold',
              checkedAt: '2026-08-30T12:05:00+08:00',
              awbAvailable: false,
              recoveryDisposition: 'none' as const,
              recovery: null,
            },
            shipments: [onHold],
            replacementParcel: null,
            reloadWarning: null,
          };
        },
        onRefreshAllEasyParcelStatuses: vi.fn().mockResolvedValue([]),
        onConfirmEasyParcelProviderFailure: vi.fn(),
      });
    };
    await act(async () => root.render(React.createElement(Harness)));

    const more = host.querySelector('button[aria-label="More shipment operations"]') as HTMLButtonElement;
    await act(async () => more.click());
    const refresh = Array.from(document.body.querySelectorAll('button')).find(button => button.textContent?.trim() === 'Refresh EasyParcel Status') as HTMLButtonElement;
    await act(async () => refresh.click());

    expect(host.textContent).toContain('On Hold');
    expect(host.textContent).not.toContain('Confirm Provider Failure & Rebook');
    expect(host.textContent).not.toContain('Start Fresh Quote & Rebook');
    expect(host.querySelector('#easyparcel-recovery-modal-overlay')).toBeNull();
  });

  it('opens audited code 0 guidance and hands off the refreshed backend parcel after unmounting recovery', async () => {
    const stale = {
      ...INITIAL_SHIPMENT_PARCELS[0],
      id: 'provider-cancelled-shipment',
      orderId: 'provider-cancelled-order',
      source: 'easyparcel' as const,
      courier: 'easyparcel' as const,
      trackingNumber: '',
      status: 'pending' as const,
      totalWeightKg: 99,
      dimensionsCm: { length: 99, width: 99, height: 99 },
      lifecycleStage: 'pending_fulfilment' as const,
      labelState: 'pending' as const,
      recoveryState: 'none' as const,
      primaryAction: null,
      capabilities: actionCapabilities({ refreshProvider: true }),
    };
    const recovery = {
      recoveryId: 'provider-recovery-93',
      state: 'awaiting_replacement',
      cancellationState: 'success' as const,
      cancellationMessage: 'EasyParcel cancellation confirmed by provider status code 0.',
      cancellationConfirmed: true,
      replacementOrderId: stale.orderId,
      readyToRebook: true,
    };
    const replacement = {
      ...stale,
      id: `pending-order-${stale.orderId}`,
      source: 'pending' as const,
      status: 'pending' as const,
      totalWeightKg: 1.5,
      dimensionsCm: { length: 31, width: 21, height: 11 },
      easyParcelRecovery: recovery,
      lifecycleStage: 'action_required' as const,
      labelState: 'not_required' as const,
      recoveryState: 'rebooking_ready' as const,
      primaryAction: 'resume' as const,
      capabilities: actionCapabilities({ resume: true, amendAddress: true }),
    };
    const onOpenEasyParcelBooking = vi.fn();
    const host = document.createElement('div');
    document.body.append(host);
    const root = createRoot(host);
    mountedRoots.push({ root, host });

    await act(async () => root.render(React.createElement(ShippingFulfillmentTab, {
      parcels: [stale],
      onGenerateAWB: vi.fn(),
      onBatchGenerateAWB: vi.fn(),
      onOpenAWBModal: vi.fn(),
      onOpenTrackingModal: vi.fn(),
      onOpenPickupModal: vi.fn(),
      onUpdateStatus: vi.fn(),
      onOpenEasyParcelBooking,
      onRefreshEasyParcelStatus: vi.fn().mockResolvedValue({
        result: {
          shipmentId: stale.id,
          status: 'cancelled',
          changed: true,
          providerStatusCode: 0,
          providerStatus: 'Cancelled',
          checkedAt: '2026-08-30T12:00:00+08:00',
          awbAvailable: false,
          recoveryDisposition: 'provider_terminal',
          recovery,
        },
        shipments: [{ ...stale, status: 'cancelled', easyParcelRecovery: recovery }, replacement],
        replacementParcel: replacement,
        reloadWarning: null,
      }),
      onRefreshAllEasyParcelStatuses: vi.fn().mockResolvedValue([]),
      onConfirmEasyParcelProviderFailure: vi.fn(),
    })));

    const more = host.querySelector('button[aria-label="More shipment operations"]') as HTMLButtonElement;
    await act(async () => more.click());
    const refresh = Array.from(document.body.querySelectorAll('button')).find(button => button.textContent?.trim() === 'Refresh EasyParcel Status') as HTMLButtonElement;
    await act(async () => refresh.click());
    const overlay = host.querySelector('#easyparcel-recovery-modal-overlay') as HTMLDivElement;
    expect(overlay).not.toBeNull();
    expect(overlay.className).toContain('bg-black/70');
    expect(overlay.className).toContain('backdrop-blur-sm');
    expect(host.textContent).toContain('Checked 2026-08-30T12:00:00+08:00.');

    const rebook = Array.from(host.querySelectorAll('button'))
      .find(button => button.textContent?.includes('Continue to Fresh Quote & Rebook')) as HTMLButtonElement;
    await act(async () => rebook.click());

    expect(host.querySelector('#easyparcel-recovery-modal-overlay')).toBeNull();
    expect(onOpenEasyParcelBooking).toHaveBeenCalledWith(expect.objectContaining({
      id: `pending-order-${stale.orderId}`,
      totalWeightKg: 1.5,
      dimensionsCm: { length: 31, width: 21, height: 11 },
      easyParcelRecovery: recovery,
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

  it('labels the global fulfilment shortcut as the open work queue instead of an AWB queue', () => {
    mockedAdminContext.value = {
      courierConfigs: [],
      smartRoutingRules: [],
      shipmentParcels: [INITIAL_SHIPMENT_PARCELS[0]],
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

    expect(html).toContain('View Open Fulfilment (0)');
    expect(html).not.toContain('View Pending AWBs');
    expect(html).not.toContain('Batch Print AWB');
  });

  it('routes pickup scheduling to the EasyParcel Dashboard without claiming the provider is disconnected', async () => {
    const addToast = vi.fn();
    mockedAdminContext.value = {
      courierConfigs: [],
      smartRoutingRules: [],
      shipmentParcels: [],
      easyParcelWebhookReceipts: [],
      easyParcelWebhookAuditStatus: 'ready',
      easyParcelWebhookAuditError: null,
      refreshEasyParcelWebhookReceipts: async () => undefined,
      pickupRequests: [],
      billingReconciliations: [],
      shippingRates: [],
      addToast,
    };
    const host = document.createElement('div');
    document.body.append(host);
    const root = createRoot(host);
    mountedRoots.push({ root, host });

    await act(async () => {
      root.render(React.createElement(ShippingDashboard));
    });
    expect(host.textContent).not.toContain('Schedule Driver Pickup');
    const pickup = Array.from(host.querySelectorAll('button'))
      .find(button => button.textContent?.includes('Manage Pickup in EasyParcel')) as HTMLButtonElement;
    await act(async () => pickup.click());

    expect(addToast).toHaveBeenCalledWith({
      type: 'info',
      title: 'Pickup scheduling is dashboard-managed',
      message: 'JW Studio does not create or cancel EasyParcel pickup requests. Continue in the official EasyParcel Dashboard.',
    });
  });

  it('offers printing only when an EasyParcel shipment has an official provider label', () => {
    const withoutLabel = renderToStaticMarkup(React.createElement(ShippingFulfillmentTab, {
      parcels: [{
        ...INITIAL_SHIPMENT_PARCELS[0],
        id: 'easyparcel-without-label',
        source: 'easyparcel' as const,
        trackingNumber: 'EPSAMPLE-WITHOUT-LABEL',
        hasLabel: false,
        status: 'pending' as const,
        lifecycleStage: 'pending_fulfilment' as const,
        labelState: 'pending' as const,
        recoveryState: 'none' as const,
        primaryAction: null,
        capabilities: actionCapabilities({ viewTimeline: true, refreshProvider: true, copyTracking: true }),
      }],
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
    const withLabel = renderToStaticMarkup(React.createElement(ShippingFulfillmentTab, {
      parcels: [{
        ...INITIAL_SHIPMENT_PARCELS[0],
        id: 'easyparcel-with-label',
        source: 'easyparcel' as const,
        trackingNumber: 'EPSAMPLE-WITH-LABEL',
        hasLabel: true,
        status: 'ready_to_ship' as const,
        lifecycleStage: 'ready_to_ship' as const,
        labelState: 'available' as const,
        recoveryState: 'none' as const,
        primaryAction: 'printLabel' as const,
        capabilities: actionCapabilities({ printLabel: true, viewTimeline: true, correct: true, amendAddress: true, refreshProvider: true, copyTracking: true }),
      }],
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

    expect(withoutLabel).toContain('AWB Pending');
    expect(withoutLabel).not.toContain('title="Print 4x6 Thermal AWB / Dispatch Consignment Note"');
    expect(withLabel).toContain('title="Print 4x6 Thermal AWB / Dispatch Consignment Note"');
  });

  it.each(['cancelled', 'booking_failed'] as const)(
    'opens a fresh rebook draft from a recoverable %s exception without exposing its old label action',
    async (status) => {
    const recovery = {
      recoveryId: 'cancelled-recovery-1',
      state: 'awaiting_replacement',
      cancellationState: 'success' as const,
      cancellationMessage: 'EasyParcel cancellation confirmed.',
      cancellationConfirmed: true,
      replacementOrderId: 'cancelled-order-1',
      readyToRebook: true,
    };
    const rebookParcel = {
      ...INITIAL_SHIPMENT_PARCELS[0],
      id: 'pending-order-cancelled-order-1',
      orderId: 'cancelled-order-1',
      source: 'pending' as const,
      trackingNumber: '',
      hasLabel: false,
      status: 'pending' as const,
      easyParcelRecovery: recovery,
      lifecycleStage: 'action_required' as const,
      labelState: 'not_required' as const,
      recoveryState: 'rebooking_ready' as const,
      primaryAction: 'resume' as const,
      capabilities: actionCapabilities({ resume: true, amendAddress: true }),
    };
    const cancelled = {
      ...INITIAL_SHIPMENT_PARCELS[0],
      id: `${status}-easyparcel-label`,
      orderId: 'cancelled-order-1',
      source: 'easyparcel' as const,
      trackingNumber: 'AWB-CANCELLED-LABEL',
      hasLabel: true,
      status,
      easyParcelRecovery: recovery,
      lifecycleStage: 'action_required' as const,
      labelState: 'available' as const,
      recoveryState: 'rebooking_ready' as const,
      primaryAction: 'resume' as const,
      capabilities: actionCapabilities({ resume: true, viewTimeline: true, copyTracking: true, printLabel: true }),
    };
    const onOpenEasyParcelBooking = vi.fn();
    const host = document.createElement('div');
    document.body.append(host);
    const root = createRoot(host);
    mountedRoots.push({ root, host });

    await act(async () => root.render(React.createElement(ShippingFulfillmentTab, {
      parcels: [cancelled, rebookParcel],
      onGenerateAWB: vi.fn(),
      onBatchGenerateAWB: vi.fn(),
      onOpenAWBModal: vi.fn(),
      onOpenTrackingModal: vi.fn(),
      onOpenPickupModal: vi.fn(),
      onUpdateStatus: vi.fn(),
      onOpenEasyParcelBooking,
      onRefreshEasyParcelStatus: vi.fn(),
      onRefreshAllEasyParcelStatuses: vi.fn(),
      onConfirmEasyParcelProviderFailure: vi.fn(),
    })));

    expect(host.textContent).toContain('All Shipments1');
    expect(host.textContent).toContain('Pending Fulfilment0');
    expect(host.textContent).toContain('Action Required1');
    expect(host.textContent).not.toContain('Cancelled AWB');
    expect(host.innerHTML).not.toContain('title="Open Official EasyParcel AWB"');
    const rebook = Array.from(host.querySelectorAll('button'))
      .find(button => button.textContent?.trim() === 'Resume') as HTMLButtonElement;
    await act(async () => rebook.click());
    expect(onOpenEasyParcelBooking).toHaveBeenCalledWith(rebookParcel);
    },
  );

  it('exposes approved correction, resume, and address actions without offering correction for delivered shipments', async () => {
    const base = INITIAL_SHIPMENT_PARCELS[0];
    const activeRows: ShipmentParcel[] = [
      { ...base, id: 'ready-attempt', orderId: 'order-ready', orderNumber: 'JW-READY', source: 'easyparcel', fulfillmentMethod: 'easyparcel', managementMode: 'provider_managed', courier: 'easyparcel', trackingNumber: 'EP-READY', hasLabel: true, status: 'ready_to_ship', lifecycleStage: 'ready_to_ship', labelState: 'available', recoveryState: 'none', primaryAction: 'printLabel', capabilities: actionCapabilities({ printLabel: true, viewTimeline: true, correct: true, amendAddress: true, refreshProvider: true, copyTracking: true }) },
      { ...base, id: 'manual-active', orderId: 'order-manual', orderNumber: 'JW-MANUAL', source: 'manual', fulfillmentMethod: 'manual_delivery', managementMode: 'team_managed', courier: 'manual', trackingNumber: 'MANUAL-1', status: 'in_transit', lifecycleStage: 'in_transit', labelState: 'not_required', recoveryState: 'none', primaryAction: 'updateStatus', capabilities: actionCapabilities({ viewTimeline: true, updateStatus: true, copyTracking: true }) },
      { ...base, id: 'delivered-attempt', orderId: 'order-delivered', orderNumber: 'JW-DELIVERED', source: 'manual', fulfillmentMethod: 'manual_delivery', managementMode: 'team_managed', courier: 'manual', trackingNumber: 'DONE-1', status: 'delivered', lifecycleStage: 'delivered', labelState: 'not_required', recoveryState: 'none', primaryAction: null, capabilities: actionCapabilities({ viewTimeline: true, copyTracking: true }) },
      { ...base, id: 'pending-order-new', orderId: 'order-new', orderNumber: 'JW-NEW', source: 'pending', fulfillmentMethod: null, managementMode: null, courier: 'unassigned', trackingNumber: '', status: 'pending', lifecycleStage: 'pending_fulfilment', labelState: 'not_required', recoveryState: 'none', primaryAction: 'fulfil', capabilities: actionCapabilities({ fulfil: true, amendAddress: true }) },
      { ...base, id: 'pending-order-resume', orderId: 'order-resume', orderNumber: 'JW-RESUME', source: 'pending', fulfillmentMethod: null, managementMode: null, courier: 'unassigned', trackingNumber: '', status: 'pending', lifecycleStage: 'action_required', labelState: 'not_required', recoveryState: 'replacement_required', primaryAction: 'resume', capabilities: actionCapabilities({ resume: true, amendAddress: true }), activeCorrection: { id: 'correction-1', sourceShipmentId: 'old-attempt', replacementShipmentId: null, reasonCode: 'wrong_fulfilment_method', externalActionState: 'not_required', status: 'awaiting_replacement', readyToResume: true } },
    ];
    const onOpenCorrection = vi.fn();
    const onOpenFulfilment = vi.fn();
    const onOpenAmendDeliveryDetails = vi.fn();
    const host = document.createElement('div');
    document.body.append(host);
    const root = createRoot(host);
    mountedRoots.push({ root, host });

    await act(async () => root.render(React.createElement(ShippingFulfillmentTab, {
      parcels: activeRows,
      onGenerateAWB: vi.fn(), onBatchGenerateAWB: vi.fn(), onOpenAWBModal: vi.fn(), onOpenTrackingModal: vi.fn(),
      onOpenPickupModal: vi.fn(), onUpdateStatus: vi.fn(), onOpenFulfilment, onOpenEasyParcelBooking: vi.fn(),
      onRefreshEasyParcelStatus: vi.fn(), onRefreshAllEasyParcelStatuses: vi.fn(), onConfirmEasyParcelProviderFailure: vi.fn(),
      onOpenCorrection, onOpenAmendDeliveryDetails,
    } as any)));

    const rowFor = (orderNumber: string) => Array.from(host.querySelectorAll('tr')).find(row => row.textContent?.includes(orderNumber)) as HTMLTableRowElement;
    const menuFor = (orderNumber: string) => rowFor(orderNumber).querySelector('button[aria-label="More shipment operations"]') as HTMLButtonElement;

    await act(async () => menuFor('JW-READY').click());
    const correctionButtons = Array.from(document.body.querySelectorAll('button')).filter(button => button.textContent?.trim() === 'Correct Fulfilment');
    expect(correctionButtons).toHaveLength(1);
    await act(async () => (correctionButtons[0] as HTMLButtonElement).click());
    expect(onOpenCorrection).toHaveBeenCalledWith(expect.objectContaining({ id: 'ready-attempt' }));

    const resume = Array.from(host.querySelectorAll('button')).find(button => button.textContent?.trim() === 'Resume') as HTMLButtonElement;
    expect(resume).toBeTruthy();
    await act(async () => resume.click());
    expect(onOpenFulfilment).toHaveBeenCalledWith(expect.objectContaining({ id: 'pending-order-resume' }));

    await act(async () => menuFor('JW-READY').click());
    const addressActions = Array.from(document.body.querySelectorAll('button')).filter(button => button.textContent?.trim() === 'Amend Delivery Details');
    expect(addressActions).toHaveLength(1);
    await act(async () => (addressActions[0] as HTMLButtonElement).click());
    expect(onOpenAmendDeliveryDetails).toHaveBeenCalledWith(expect.objectContaining({ id: 'ready-attempt' }));

    await act(async () => menuFor('JW-MANUAL').click());
    expect(document.body.textContent).not.toContain('Correct Fulfilment');
    expect(document.body.textContent).not.toContain('Amend Delivery Details');

    await act(async () => menuFor('JW-NEW').click());
    expect(document.body.textContent).not.toContain('Correct Fulfilment');
    expect(document.body.textContent).toContain('Amend Delivery Details');
  });

  it('separates replacement work from initial pending fulfilment in the status tabs', () => {
    const base = INITIAL_SHIPMENT_PARCELS[0];
    const replacement = {
      ...base,
      id: 'pending-order-replacement', orderId: 'replacement-order', orderNumber: 'JW-REPLACEMENT',
      source: 'pending' as const, fulfillmentMethod: null, managementMode: null,
      courier: 'unassigned' as const, trackingNumber: '', hasLabel: false, status: 'pending' as const,
      lifecycleStage: 'action_required' as const, labelState: 'not_required' as const,
      recoveryState: 'replacement_required' as const, primaryAction: 'resume' as const,
      capabilities: {
        fulfil: false, resume: true, printLabel: false, viewTimeline: false,
        correct: false, amendAddress: true, updateStatus: false,
        refreshProvider: false, copyTracking: false,
      },
      activeCorrection: { id: 'correction-1', sourceShipmentId: 'old-attempt', replacementShipmentId: null, reasonCode: 'wrong_fulfilment_method', externalActionState: 'not_required' as const, status: 'awaiting_replacement' as const, readyToResume: true },
    };
    const html = renderToStaticMarkup(React.createElement(ShippingFulfillmentTab, {
      parcels: [replacement],
      onGenerateAWB: vi.fn(), onBatchGenerateAWB: vi.fn(), onOpenAWBModal: vi.fn(), onOpenTrackingModal: vi.fn(),
      onOpenPickupModal: vi.fn(), onUpdateStatus: vi.fn(), onOpenFulfilment: vi.fn(), onOpenEasyParcelBooking: vi.fn(),
      onRefreshEasyParcelStatus: vi.fn(), onRefreshAllEasyParcelStatuses: vi.fn(), onConfirmEasyParcelProviderFailure: vi.fn(),
    }));

    expect(html).toMatch(/<span>Pending Fulfilment<\/span><span[^>]*>0<\/span>/);
    expect(html).toMatch(/<span>Action Required<\/span><span[^>]*>1<\/span>/);
    expect(html).toContain('Resume');
  });

  it('does not render AWB Pending or operational actions for a delivered manual shipment', () => {
    const base = INITIAL_SHIPMENT_PARCELS[0];
    const delivered = {
      ...base,
      id: 'manual-delivered-no-awb', source: 'manual' as const, fulfillmentMethod: 'manual_delivery' as const,
      managementMode: 'team_managed' as const, courier: 'manual' as const, status: 'delivered' as const,
      trackingNumber: 'JW-MANUAL-14-1', hasLabel: false,
      lifecycleStage: 'delivered' as const, labelState: 'not_required' as const,
      recoveryState: 'none' as const, primaryAction: null,
      capabilities: {
        fulfil: false, resume: false, printLabel: false, viewTimeline: true,
        correct: false, amendAddress: false, updateStatus: false,
        refreshProvider: false, copyTracking: true,
      },
    };
    const html = renderToStaticMarkup(React.createElement(ShippingFulfillmentTab, {
      parcels: [delivered],
      onGenerateAWB: vi.fn(), onBatchGenerateAWB: vi.fn(), onOpenAWBModal: vi.fn(), onOpenTrackingModal: vi.fn(),
      onOpenPickupModal: vi.fn(), onUpdateStatus: vi.fn(), onOpenFulfilment: vi.fn(), onOpenEasyParcelBooking: vi.fn(),
      onRefreshEasyParcelStatus: vi.fn(), onRefreshAllEasyParcelStatuses: vi.fn(), onConfirmEasyParcelProviderFailure: vi.fn(),
      onOpenCorrection: vi.fn(), onOpenAmendDeliveryDetails: vi.fn(),
    }));

    expect(html).not.toContain('AWB Pending');
    expect(html).not.toContain('Print AWB');
    expect(html).not.toContain('Correct Fulfilment');
    expect(html).not.toContain('Amend Delivery Details');
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

  it('shows the connected production provider truth in ShippingOverview without wallet claims', () => {
    const html = renderToStaticMarkup(React.createElement(ShippingOverviewTab, {
      parcels: [],
      couriers: [{
        id: 'cfg_easyparcel',
        courierCode: 'easyparcel',
        name: 'EasyParcel Multi-Carrier',
        displayName: 'EasyParcel Multi-Carrier',
        logo: '',
        category: 'aggregator_api',
        isEnabled: true,
        environment: 'production',
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
        status: 'connected',
        webhookEndpointUrl: '',
        autoRetryQueue: false,
        maxRetryAttempts: 0,
      } satisfies CourierConfig],
      pickupRequests: [],
      onNavigateTab: () => undefined,
      onOpenPickupModal: () => undefined,
    }));

    expect(html).toContain('Connected');
    expect(html).toContain('Live Production');
    expect(html).toContain('Managed in EasyParcel Dashboard');
    expect(html).not.toContain('Not connected');
    expect(html).not.toContain('Provider integration required');
    expect(html).not.toContain('wallet balance');
    expect(html).not.toContain('Provider Required');
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

  it('treats booking failures, cancellations, holds, and unknown provider updates as shipping exceptions', () => {
    const exceptionParcels = (['booking_failed', 'cancelled', 'on_hold', 'provider_update'] as const).map((status, index) => ({
      ...INITIAL_SHIPMENT_PARCELS[0],
      id: `easyparcel-exception-${index}`,
      source: 'easyparcel' as const,
      fulfillmentMethod: 'easyparcel' as const,
      managementMode: 'provider_managed' as const,
      status,
      lifecycleStage: 'action_required' as const,
      labelState: status === 'booking_failed' ? 'failed' as const : 'pending' as const,
      recoveryState: 'review_required' as const,
      primaryAction: 'reviewRecovery' as const,
      capabilities: actionCapabilities({ viewTimeline: true, refreshProvider: status === 'on_hold' || status === 'provider_update', copyTracking: true }),
    })) as ShipmentParcel[];
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

    expect(metricCardText(overview, 'Exceptions & NITC')).toContain('4');
    expect(fulfillment).toMatch(/Action Required<\/span><span[^>]*>4<\/span>/);
    expect(fulfillment).toContain('Booking Failed');
    expect(fulfillment).toContain('Cancelled');
    expect(fulfillment).toContain('On Hold');
    expect(fulfillment).toContain('Provider Review');
  });

  it('does not render EasyParcel wallet or top-up controls on the courier card', () => {
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
    }));

    expect(html).not.toContain('Prepaid Wallet');
    expect(html).not.toContain('+ RM100');
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
    expect(html).toContain('EasyParcel provider receipt');
    expect(html).not.toContain('EasyParcel sandbox receipt');
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
