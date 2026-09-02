import React, { useEffect, useState } from 'react';
import { 
  Truck, 
  Package, 
  GitFork, 
  Server, 
  Radio, 
  DollarSign, 
  Printer, 
  Plus, 
  RefreshCw, 
  Zap,
  CheckCircle2,
  AlertTriangle,
  Clock,
  Send,
  Download,
  Scale
} from 'lucide-react';
import { useAdmin } from '../../context/AdminContext';
import { 
  ShipmentParcel, 
  CourierConfig, 
  SmartRoutingRule, 
  PickupRequest,
  GeneralShippingRate
} from '../../types/shipping';

// Tabs
import { ShippingOverviewTab } from './ShippingOverviewTab';
import { ShippingFulfillmentTab } from './ShippingFulfillmentTab';
import { ShippingRatesTab } from './ShippingRatesTab';
import { SmartRoutingTab } from './SmartRoutingTab';
import { CourierConfigTab } from './CourierConfigTab';
import { TrackingAndWebhooksTab } from './TrackingAndWebhooksTab';
import { BillingReconciliationTab } from './BillingReconciliationTab';

// Modals
import { ThermalAWBModal } from './ThermalAWBModal';
import { PickupScheduleModal } from './PickupScheduleModal';
import { ParcelTrackingModal } from './ParcelTrackingModal';
import { SmartRoutingRuleModal } from './SmartRoutingRuleModal';
import { CourierConfigModal } from './CourierConfigModal';
import { GeneralShippingRateModal } from './GeneralShippingRateModal';
import { EasyParcelOAuthModal } from './EasyParcelOAuthModal';
import { EasyParcelBookingModal } from './EasyParcelBookingModal';
import { ChooseFulfillmentMethodModal } from './ChooseFulfillmentMethodModal';
import { CorrectFulfilmentModal } from './CorrectFulfilmentModal';
import { AmendDeliveryDetailsModal } from '../orders/AmendDeliveryDetailsModal';

export type ShippingTabType = 
  | 'overview' 
  | 'rates'
  | 'fulfillment' 
  | 'routing' 
  | 'couriers' 
  | 'tracking' 
  | 'reconciliation';

export const ShippingDashboard: React.FC = () => {
  const {
    courierConfigs = [],
    easyParcelProvider,
    saveCourierConfig,
    refreshEasyParcelProvider,
    disconnectEasyParcel,
    saveEasyParcelSender,
    quoteEasyParcelShipment,
    bookEasyParcelShipment,
    toggleCourierStatus,
    pingCourierAPI,
    smartRoutingRules = [],
    saveSmartRoutingRule,
    deleteSmartRoutingRule,
    toggleSmartRoutingRule,
    reorderSmartRoutingRules,
    shipmentParcels = [],
    orders = [],
    refreshEasyParcelShipmentStatus,
    refreshAllEasyParcelShipmentStatuses,
    confirmEasyParcelProviderFailure,
    easyParcelWebhookReceipts = [],
    easyParcelWebhookAuditStatus,
    easyParcelWebhookAuditError,
    refreshEasyParcelWebhookReceipts,
    generateShipmentAWB,
    batchGenerateAWB,
    updateShipmentStatus,
    recordExternalCourierDispatch,
    recordManualDeliveryDispatch,
    startShipmentCorrection,
    amendShipmentOperationalDetails,
    amendOrderDeliveryDetails,
    requestRedelivery,
    simulateCourierWebhookEvent,
    pickupRequests = [],
    createPickupRequest,
    cancelPickupRequest,
    billingReconciliations = [],
    resolveBillingDispute,
    shippingRates = [],
    saveShippingRate,
    addToast,
  } = useAdmin();

  const [activeTab, setActiveTab] = useState<ShippingTabType>('overview');

  // Modals state
  const [activeAWBParcel, setActiveAWBParcel] = useState<ShipmentParcel | null>(null);
  const [activeTrackingParcel, setActiveTrackingParcel] = useState<ShipmentParcel | null>(null);
  const [isPickupModalOpen, setIsPickupModalOpen] = useState(false);
  const [isRuleModalOpen, setIsRuleModalOpen] = useState(false);
  const [editingRule, setEditingRule] = useState<SmartRoutingRule | null>(null);
  const [isCourierModalOpen, setIsCourierModalOpen] = useState(false);
  const [editingCourier, setEditingCourier] = useState<CourierConfig | null>(null);
  const [isRateModalOpen, setIsRateModalOpen] = useState(false);
  const [editingRate, setEditingRate] = useState<GeneralShippingRate | null>(null);
  const [isEasyParcelOAuthOpen, setIsEasyParcelOAuthOpen] = useState(false);
  const [easyParcelBookingParcel, setEasyParcelBookingParcel] = useState<ShipmentParcel | null>(null);
  const [fulfilmentParcel, setFulfilmentParcel] = useState<ShipmentParcel | null>(null);
  const [correctionParcel, setCorrectionParcel] = useState<ShipmentParcel | null>(null);
  const [pendingReplacementParcel, setPendingReplacementParcel] = useState<ShipmentParcel | null>(null);
  const [amendDeliveryOrderId, setAmendDeliveryOrderId] = useState<string | null>(null);
  const [easyParcelCallbackState, setEasyParcelCallbackState] = useState(window.__JW_ADMIN__?.easyParcelConnection?.state ?? null);

  useEffect(() => {
    if (!easyParcelCallbackState) return;
    const easyParcel = courierConfigs.find((courier) => courier.courierCode === 'easyparcel');
    if (easyParcel) {
      setEditingCourier(easyParcel);
      setIsEasyParcelOAuthOpen(true);
    }
  }, [courierConfigs, easyParcelCallbackState]);

  // Counts for badge notifications
  const pendingAwbCount = shipmentParcels.filter(p => (
    (p.source === 'pending' || p.source === 'easyparcel')
    && !p.hasLabel
    && p.status !== 'cancelled'
    && p.status !== 'booking_failed'
  )).length;
  const inTransitCount = shipmentParcels.filter(p => p.status === 'shipped' || p.status === 'in_transit' || p.status === 'picked_up' || p.status === 'out_for_delivery').length;
  const discrepancyCount = billingReconciliations.filter(b => b.reconciliationStatus === 'disputed').length;
  const connectedCouriersCount = courierConfigs.filter(c => c.isEnabled).length;
  const activeRatesCount = shippingRates.filter(r => r.isActive).length;
  const easyParcelConnected = courierConfigs.some(c => c.courierCode === 'easyparcel' && c.status === 'connected');

  const handleOpenEditRule = (rule: SmartRoutingRule) => {
    setEditingRule(rule);
    setIsRuleModalOpen(true);
  };

  const handleOpenNewRule = () => {
    setEditingRule(null);
    setIsRuleModalOpen(true);
  };

  const handleOpenEditRate = (rate: GeneralShippingRate) => {
    setEditingRate(rate);
    setIsRateModalOpen(true);
  };

  const handleOpenNewRate = () => {
    setEditingRate(null);
    setIsRateModalOpen(true);
  };

  const handleOpenCourierConfig = (courier: CourierConfig) => {
    if (courier.courierCode === 'easyparcel') {
      void refreshEasyParcelProvider().catch(() => undefined);
      setEditingCourier(courier);
      setIsEasyParcelOAuthOpen(true);
      return;
    }
    setEditingCourier(courier);
    setIsCourierModalOpen(true);
  };

  const handleSaveRule = (rule: SmartRoutingRule) => {
    saveSmartRoutingRule(rule);
    setIsRuleModalOpen(false);
    setEditingRule(null);
  };

  const handleSaveCourier = (config: CourierConfig) => {
    saveCourierConfig(config);
    setIsCourierModalOpen(false);
    setEditingCourier(null);
  };

  const explainPickupUnavailable = () => {
    addToast({ type: 'info', title: 'Pickup scheduling is dashboard-managed', message: 'JW Studio does not create or cancel EasyParcel pickup requests. Continue in the official EasyParcel Dashboard.' });
  };

  const amendDeliveryOrder = orders.find(order => order.id === amendDeliveryOrderId) ?? null;

  const openDeliveryAmendment = (parcel: ShipmentParcel) => {
    const order = orders.find(candidate => candidate.id === parcel.orderId || candidate.orderNumber === parcel.orderNumber);
    if (order) setAmendDeliveryOrderId(order.id);
    else addToast({ type: 'error', title: 'Order unavailable', message: 'The authorized order record could not be opened for delivery amendment.' });
  };

  const correctionReasonCode = (reason: string) => {
    if (['wrong_fulfillment_method', 'wrong_easyparcel_courier', 'wrong_external_courier'].includes(reason)) return 'wrong_fulfilment_method' as const;
    if (reason === 'incorrect_receiver_details') return 'incorrect_delivery_details' as const;
    if (reason === 'duplicate_accidental_dispatch') return 'duplicate_booking' as const;
    if (reason === 'customer_requested_change') return 'customer_request' as const;
    return 'other' as const;
  };

  const deliveryReasonCode = (reason: string) => {
    if (reason.startsWith('Customer requested')) return 'customer_request' as const;
    if (reason.startsWith('Mobile contact') || reason.startsWith('Recipient name')) return 'invalid_receiver_details' as const;
    if (reason.includes('Typo') || reason.includes('Postcode') || reason.includes('Building')) return 'address_typo' as const;
    return 'other' as const;
  };

  return (
    <div className="space-y-6 pb-12">
      {/* Top Header & Breadcrumb */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2 text-xs font-semibold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">
            <Truck className="w-3.5 h-3.5" />
            <span>Fulfillment & Logistics Engine</span>
            <span className="text-neutral-300 dark:text-neutral-700">•</span>
            <span className="text-neutral-500 dark:text-neutral-400">Malaysia (MY) Direct & Aggregators</span>
          </div>
          <h1 className="text-2xl font-black tracking-tight text-neutral-900 dark:text-neutral-100 mt-1">
            Malaysia Shipping & Multi-Carrier Logistics Center
          </h1>
          <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5 max-w-3xl">
            Dispatch processing orders manually with carrier and tracking details, then retain an append-only shipment event audit. Carrier APIs, pickup booking, webhooks, labels, and billing reconciliation activate only after their provider integration is configured.
          </p>
        </div>

        {/* Global Quick Action Buttons */}
        <div className="flex items-center gap-2 flex-wrap">
          <button
            onClick={explainPickupUnavailable}
            className="flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white transition-colors cursor-pointer shadow-sm"
          >
            <Truck className="w-3.5 h-3.5" />
            Manage Pickup in EasyParcel
          </button>
          <button
            onClick={() => setActiveTab('fulfillment')}
            className="flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 hover:bg-neutral-50 dark:hover:bg-neutral-700 text-neutral-700 dark:text-neutral-200 transition-colors cursor-pointer shadow-xs"
          >
            <Printer className="w-3.5 h-3.5" />
            View Pending AWBs ({pendingAwbCount})
          </button>
        </div>
      </div>

      {/* Navigation Sub-Tabs */}
      <div className="flex items-center gap-2 p-1.5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 overflow-x-auto shadow-xs">
        <button
          onClick={() => setActiveTab('overview')}
          className={`flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition cursor-pointer whitespace-nowrap ${
            activeTab === 'overview'
              ? 'bg-indigo-600 text-white shadow-xs'
              : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800/60'
          }`}
        >
          <Package className="w-3.5 h-3.5" />
          Overview Hub
        </button>

        <button
          onClick={() => setActiveTab('rates')}
          className={`flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition cursor-pointer whitespace-nowrap ${
            activeTab === 'rates'
              ? 'bg-indigo-600 text-white shadow-xs'
              : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800/60'
          }`}
        >
          <Scale className="w-3.5 h-3.5" />
          <span>Rate Matrices & Postage Modes</span>
          <span className={`px-1.5 py-0.2 rounded-full text-[10px] font-bold ${
            activeTab === 'rates' ? 'bg-white text-indigo-700' : 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/60 dark:text-indigo-300'
          }`}>
            {activeRatesCount} Rates
          </span>
        </button>

        <button
          onClick={() => setActiveTab('fulfillment')}
          className={`flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition cursor-pointer whitespace-nowrap ${
            activeTab === 'fulfillment'
              ? 'bg-indigo-600 text-white shadow-xs'
              : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800/60'
          }`}
        >
          <Printer className="w-3.5 h-3.5" />
          <span>Fulfillment & Waybills</span>
          {pendingAwbCount > 0 && (
            <span className={`px-1.5 py-0.2 rounded-full text-[10px] font-bold ${
              activeTab === 'fulfillment' ? 'bg-white text-indigo-700' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300'
            }`}>
              {pendingAwbCount}
            </span>
          )}
        </button>

        <button
          onClick={() => setActiveTab('routing')}
          className={`flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition cursor-pointer whitespace-nowrap ${
            activeTab === 'routing'
              ? 'bg-indigo-600 text-white shadow-xs'
              : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800/60'
          }`}
        >
          <GitFork className="w-3.5 h-3.5" />
          <span>Smart Routing Engine</span>
          <span className={`px-1.5 py-0.2 rounded-full text-[10px] font-bold ${
            activeTab === 'routing' ? 'bg-white text-indigo-700' : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400'
          }`}>
            {smartRoutingRules.length} Rules
          </span>
        </button>

        <button
          onClick={() => setActiveTab('couriers')}
          className={`flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition cursor-pointer whitespace-nowrap ${
            activeTab === 'couriers'
              ? 'bg-indigo-600 text-white shadow-xs'
              : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800/60'
          }`}
        >
          <Server className="w-3.5 h-3.5" />
          <span>Courier Integrations</span>
          <span className={`px-1.5 py-0.2 rounded-full text-[10px] font-bold ${
            activeTab === 'couriers' ? 'bg-white text-indigo-700' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
          }`}>
            {connectedCouriersCount} Active
          </span>
        </button>

        <button
          onClick={() => {
            setActiveTab('tracking');
            void refreshEasyParcelWebhookReceipts().catch(() => undefined);
          }}
          className={`flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition cursor-pointer whitespace-nowrap ${
            activeTab === 'tracking'
              ? 'bg-indigo-600 text-white shadow-xs'
              : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800/60'
          }`}
        >
          <Radio className="w-3.5 h-3.5" />
          <span>Webhooks & Telemetry</span>
          {inTransitCount > 0 && (
            <span className={`px-1.5 py-0.2 rounded-full text-[10px] font-bold ${
              activeTab === 'tracking' ? 'bg-white text-indigo-700' : 'bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-300'
            }`}>
              {inTransitCount} In-Transit
            </span>
          )}
        </button>

        <button
          onClick={() => setActiveTab('reconciliation')}
          className={`flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition cursor-pointer whitespace-nowrap ${
            activeTab === 'reconciliation'
              ? 'bg-indigo-600 text-white shadow-xs'
              : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800/60'
          }`}
        >
          <DollarSign className="w-3.5 h-3.5" />
          <span>Billing Reconciliation</span>
          {discrepancyCount > 0 && (
            <span className={`px-1.5 py-0.2 rounded-full text-[10px] font-bold ${
              activeTab === 'reconciliation' ? 'bg-white text-indigo-700' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300'
            }`}>
              {discrepancyCount} Flagged
            </span>
          )}
        </button>
      </div>

      {/* Render Selected Tab */}
      <div>
        {activeTab === 'overview' && (
          <ShippingOverviewTab
            parcels={shipmentParcels}
            couriers={courierConfigs}
            pickupRequests={pickupRequests}
            onNavigateTab={(tab) => {
              if (tab === 'reconciliation') setActiveTab('reconciliation');
              else if (tab === 'fulfillment') setActiveTab('fulfillment');
              else if (tab === 'routing') setActiveTab('routing');
              else if (tab === 'couriers') setActiveTab('couriers');
              else if (tab === 'tracking') {
                setActiveTab('tracking');
                void refreshEasyParcelWebhookReceipts().catch(() => undefined);
              }
            }}
            onOpenPickupModal={explainPickupUnavailable}
          />
        )}

        {activeTab === 'rates' && (
          <ShippingRatesTab
            onOpenInsertModal={handleOpenNewRate}
            onOpenEditModal={handleOpenEditRate}
          />
        )}

        {activeTab === 'fulfillment' && (
          <ShippingFulfillmentTab
            parcels={shipmentParcels}
            onGenerateAWB={generateShipmentAWB}
            onBatchGenerateAWB={batchGenerateAWB}
            onOpenAWBModal={(parcel) => setActiveAWBParcel(parcel)}
            onOpenTrackingModal={(parcel) => setActiveTrackingParcel(parcel)}
            onOpenPickupModal={explainPickupUnavailable}
            onUpdateStatus={updateShipmentStatus}
            onOpenFulfilment={(parcel) => setFulfilmentParcel(parcel)}
            onOpenEasyParcelBooking={(parcel) => setEasyParcelBookingParcel(parcel)}
            onRefreshEasyParcelStatus={refreshEasyParcelShipmentStatus}
            onRefreshAllEasyParcelStatuses={refreshAllEasyParcelShipmentStatuses}
            onConfirmEasyParcelProviderFailure={confirmEasyParcelProviderFailure}
            onOpenCorrection={(parcel) => setCorrectionParcel(parcel)}
            onOpenAmendDeliveryDetails={openDeliveryAmendment}
          />
        )}

        {activeTab === 'routing' && (
          <SmartRoutingTab
            rules={smartRoutingRules}
            couriers={courierConfigs}
            onSaveRule={saveSmartRoutingRule}
            onDeleteRule={deleteSmartRoutingRule}
            onReorderRules={reorderSmartRoutingRules}
            onOpenNewRuleModal={handleOpenNewRule}
            onOpenEditRuleModal={handleOpenEditRule}
          />
        )}

        {activeTab === 'couriers' && (
          <CourierConfigTab
            couriers={courierConfigs}
            onToggleCourier={(id) => toggleCourierStatus(id)}
            onOpenConfigModal={handleOpenCourierConfig}
            onPingCourier={pingCourierAPI}
          />
        )}

        {activeTab === 'tracking' && (
          <TrackingAndWebhooksTab
            parcels={shipmentParcels}
            webhookReceipts={easyParcelWebhookReceipts}
            webhookAuditStatus={easyParcelWebhookAuditStatus}
            webhookAuditError={easyParcelWebhookAuditError}
            webhookEndpointConfigured={Boolean(window.__JW_ADMIN__?.easyParcelWebhookConfigured)}
            providerConnected={easyParcelConnected}
            onRefreshWebhookAudit={refreshEasyParcelWebhookReceipts}
            onSimulateWebhook={simulateCourierWebhookEvent}
            onOpenTrackingModal={(parcel) => setActiveTrackingParcel(parcel)}
          />
        )}

        {activeTab === 'reconciliation' && (
          <BillingReconciliationTab
            records={billingReconciliations}
            onResolveDispute={resolveBillingDispute}
          />
        )}
      </div>

      {/* Global Modals */}
      {activeAWBParcel && (
        <ThermalAWBModal
          parcel={activeAWBParcel}
          onClose={() => setActiveAWBParcel(null)}
        />
      )}

      {activeTrackingParcel && (
        <ParcelTrackingModal
          parcel={activeTrackingParcel}
          onClose={() => setActiveTrackingParcel(null)}
          onRedeliver={(parcelId) => requestRedelivery(parcelId)}
          onSimulateEvent={(parcelId, evtType, loc, desc) => {
            simulateCourierWebhookEvent(parcelId, evtType, loc, desc);
          }}
          onOpenAWB={(parcel) => {
            setActiveTrackingParcel(null);
            setActiveAWBParcel(parcel);
          }}
        />
      )}

      {isPickupModalOpen && (
        <PickupScheduleModal
          isOpen={isPickupModalOpen}
          onClose={() => setIsPickupModalOpen(false)}
          onSubmit={(req) => {
            createPickupRequest(req);
            setIsPickupModalOpen(false);
          }}
          defaultCourier="jnt"
          selectedParcelCount={shipmentParcels.filter(p => p.status === 'ready_to_ship').length || 8}
          totalWeightKg={14.8}
        />
      )}

      {isRuleModalOpen && (
        <SmartRoutingRuleModal
          isOpen={isRuleModalOpen}
          rule={editingRule}
          onClose={() => {
            setIsRuleModalOpen(false);
            setEditingRule(null);
          }}
          onSave={handleSaveRule}
        />
      )}

      {isCourierModalOpen && editingCourier && (
        <CourierConfigModal
          isOpen={isCourierModalOpen}
          config={editingCourier}
          onClose={() => {
            setIsCourierModalOpen(false);
            setEditingCourier(null);
          }}
          onSave={handleSaveCourier}
          onPing={pingCourierAPI}
        />
      )}

      {isEasyParcelOAuthOpen && editingCourier && (
        <EasyParcelOAuthModal
          isOpen={isEasyParcelOAuthOpen}
          provider={easyParcelProvider ?? { provider: 'easyparcel', environment: 'sandbox', configured: false, enabled: editingCourier.isEnabled, connectionState: editingCourier.status === 'connected' ? 'connected' : editingCourier.status === 'error' ? 'failed' : 'unconfigured', lastVerifiedAt: null, lastError: null }}
          webhookCallbackUrl={window.__JW_ADMIN__?.easyParcelWebhookCallbackUrl ?? editingCourier.webhookEndpointUrl}
          callbackState={easyParcelCallbackState}
          onClose={() => { setEasyParcelCallbackState(null); setIsEasyParcelOAuthOpen(false); setEditingCourier(null); }}
          onAuthorize={(environment) => { void pingCourierAPI(editingCourier.id, environment); }}
          onDisconnect={async (environment) => { await disconnectEasyParcel(environment); setEditingCourier({ ...editingCourier, isEnabled: false, status: 'pending_keys' }); }}
          onSaveSenderProfile={saveEasyParcelSender}
        />
      )}

      {easyParcelBookingParcel && (
        <EasyParcelBookingModal
          isOpen={true}
          parcel={easyParcelBookingParcel}
          onClose={() => setEasyParcelBookingParcel(null)}
          onQuote={(parcel, packageSpec) => quoteEasyParcelShipment(parcel, packageSpec)}
          onBook={(quote, serviceCode) => bookEasyParcelShipment(easyParcelBookingParcel, quote, serviceCode)}
          onBooked={async () => { await refreshEasyParcelProvider(); }}
        />
      )}

      {fulfilmentParcel && (
        <ChooseFulfillmentMethodModal
          isOpen={true}
          parcel={fulfilmentParcel}
          easyParcelConnected={easyParcelConnected}
          onClose={() => setFulfilmentParcel(null)}
          onQuoteEasyParcel={(parcel, packageSpec) => quoteEasyParcelShipment(parcel, packageSpec)}
          onConfirmEasyParcelBooking={async (parcel, quote, serviceCode) => {
            const booking = await bookEasyParcelShipment(parcel, quote, serviceCode);
            await refreshEasyParcelProvider();
            return booking;
          }}
          onConfirmExternalCourier={recordExternalCourierDispatch}
          onConfirmManualDelivery={recordManualDeliveryDispatch}
        />
      )}

      <CorrectFulfilmentModal
        isOpen={Boolean(correctionParcel)}
        parcel={correctionParcel}
        isOrderCancelled={orders.find(order => order.id === correctionParcel?.orderId || order.orderNumber === correctionParcel?.orderNumber)?.fulfillmentStatus === 'cancelled'}
        onClose={() => {
          setCorrectionParcel(null);
          if (pendingReplacementParcel) {
            setFulfilmentParcel(pendingReplacementParcel);
            setPendingReplacementParcel(null);
          }
        }}
        onOpenAmendDeliveryDetails={() => {
          if (correctionParcel) {
            openDeliveryAmendment(correctionParcel);
            setCorrectionParcel(null);
          }
        }}
        onConfirmVoidAndReplace={async (parcelId, data) => {
          if (!correctionParcel) return;
          const providerManaged = correctionParcel.managementMode === 'provider_managed' || correctionParcel.courier === 'easyparcel';
          const outcome = await startShipmentCorrection(parcelId, {
            reasonCode: correctionReasonCode(data.reason),
            internalNote: data.auditNote,
            externalActionState: providerManaged ? 'staff_confirmed_external' : 'not_required',
            externalActionNote: providerManaged ? data.auditNote : undefined,
          });
          setPendingReplacementParcel(outcome.replacementParcel);
        }}
        onConfirmCorrectDetailsOnly={async (parcelId, data) => {
          const reasonCode = data.driverName !== (correctionParcel?.driverName ?? undefined) ? 'driver_correction'
            : data.handoffReference !== (correctionParcel?.handoffReference ?? undefined) ? 'handoff_correction'
              : data.externalCourierName !== (correctionParcel?.externalCourierName ?? undefined) ? 'courier_correction'
                : data.trackingNumber !== (correctionParcel?.trackingNumber || undefined) || data.trackingUrl !== (correctionParcel?.trackingUrl ?? undefined) ? 'tracking_typo'
                  : 'other';
          await amendShipmentOperationalDetails(parcelId, {
            trackingNumber: data.trackingNumber,
            trackingUrl: data.trackingUrl,
            driverName: data.driverName,
            handoffReference: data.handoffReference,
            courierName: data.externalCourierName,
            reasonCode,
            internalNote: data.auditNote,
          });
        }}
      />

      <AmendDeliveryDetailsModal
        isOpen={Boolean(amendDeliveryOrder)}
        order={amendDeliveryOrder}
        hasActiveShipment={shipmentParcels.some(parcel => parcel.orderId === amendDeliveryOrder?.id && parcel.status !== 'delivered' && parcel.status !== 'cancelled')}
        onClose={() => setAmendDeliveryOrderId(null)}
        onSave={async (orderId, updatedAddress, metadata) => {
          await amendOrderDeliveryDetails(orderId, {
            firstName: updatedAddress.firstName,
            lastName: updatedAddress.lastName,
            phone: updatedAddress.phone,
            address1: updatedAddress.address1,
            address2: updatedAddress.address2,
            postalCode: updatedAddress.postalCode,
            city: updatedAddress.city,
            state: updatedAddress.province,
            country: updatedAddress.country,
            reasonCode: deliveryReasonCode(metadata.reason),
            internalNote: metadata.internalNote,
            syncAddressBook: metadata.saveToAddressBook,
          });
        }}
      />

      {isRateModalOpen && (
        <GeneralShippingRateModal
          isOpen={isRateModalOpen}
          rate={editingRate}
          onClose={() => {
            setIsRateModalOpen(false);
            setEditingRate(null);
          }}
          onSave={(savedRate) => {
            saveShippingRate(savedRate);
            setIsRateModalOpen(false);
            setEditingRate(null);
          }}
        />
      )}
    </div>
  );
};
