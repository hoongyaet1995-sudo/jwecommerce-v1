import {preparationParcels, deliveryParcels} from '../../integration/shipmentQueue';
import {navigateShipping,useShippingView} from './shippingNavigation';
import { SharedFulfillmentFlow } from './SharedFulfillmentFlow';
import '../commerce/commerce.css';
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
import { visibleShipmentParcels } from '../../integration/shipmentQueue';

export type ShippingTabType = 
  | 'settings'
  | 'deliveries'
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
    reloadShipmentParcels,
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

  const activeTab = useShippingView();
  const setActiveTab = navigateShipping;
  useEffect(()=>()=>navigateShipping('overview'),[]);
  const [isReloadingShipping, setIsReloadingShipping] = useState(false);
  const reloadLogistics = async () => { if (isReloadingShipping) return; setIsReloadingShipping(true); try { await reloadShipmentParcels(); } finally { setIsReloadingShipping(false); } };

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
  const openFulfilmentCount = preparationParcels(shipmentParcels).length;
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

  if(fulfilmentParcel)return <SharedFulfillmentFlow parcel={fulfilmentParcel} onClose={()=>setFulfilmentParcel(null)}/>;
  return (
    <div className={`jw-commerce space-y-6 pb-12 ${activeTab==='overview'?'jw-shipping-hub':''}`}>
      <nav className="jw-commerce-crumb" aria-label="Breadcrumb"><button onClick={()=>setActiveTab('overview')}>Shipping</button>{activeTab!=='overview'&&<><span>/</span><span>{activeTab==='fulfillment'?'Prepare shipments':activeTab==='deliveries'?'Track deliveries':'Settings'}</span></>}</nav>
      <div hidden={activeTab==='overview'} className="jw-commerce-heading"><div><h1>{activeTab==='overview'?'Shipping':activeTab==='fulfillment'?'Prepare shipments':activeTab==='deliveries'?'Track deliveries':'Shipping settings'}</h1><p>{activeTab==='overview'?'Prepare shipments and follow deliveries.':activeTab==='fulfillment'?'Choose an order to continue its delivery arrangements.':activeTab==='deliveries'?'Follow progress and review delivery issues.':'Manage checkout rates, courier connections and sender details.'}</p></div><button className="jw-btn" onClick={()=>void reloadLogistics()} disabled={isReloadingShipping}>{isReloadingShipping?'Loading…':'Refresh'}</button></div>

      {activeTab==='settings'&&<div className="jw-commerce-modules"><button className="jw-commerce-card" onClick={()=>setActiveTab('rates')}><Scale/><h2>Checkout delivery rates</h2><p>Set delivery charges shown to your customers.</p><span>Manage rates →</span></button><button className="jw-commerce-card" onClick={()=>setActiveTab('couriers')}><Server/><h2>Courier & sender</h2><p>Manage your courier connection and collection details.</p><span>Manage connection →</span></button></div>}
      {activeTab==='settings'&&<details className="mt-6"><summary>Connection details</summary><div className="flex gap-3 mt-4"><button className="jw-btn" onClick={explainPickupUnavailable}>Manage Pickup in EasyParcel</button><button className="jw-btn" onClick={()=>{setActiveTab('tracking');void refreshEasyParcelWebhookReceipts().catch(()=>undefined)}}>Connection diagnostics</button></div></details>}
      {/* Render Selected Tab */}
      <div>
        {activeTab==='overview'&&<>
          <section className="jw-shipping-banner"><span className="jw-shipping-tag">Shipping & logistics</span><h1>Shipping & Delivery</h1><p>Prepare orders for dispatch, follow deliveries and manage your shipping settings.</p><div className="jw-shipping-stats">{[['To prepare',openFulfilmentCount],['In transit',inTransitCount],['Connected couriers',connectedCouriersCount]].map(([title,value])=><div key={title}><span>{title}</span><strong>{value}</strong></div>)}</div></section>
          <div className="flex items-center justify-between mt-7 mb-4"><h2 className="text-lg font-bold">Shipping modules</h2><span className="text-xs text-neutral-500">Select a section to manage</span></div>
          <div className="jw-shipping-cards">{([{id:'fulfillment',title:'Prepare shipments',description:'Choose a delivery method and get orders ready for dispatch.',icon:Package},{id:'deliveries',title:'Track deliveries',description:'Follow delivery progress and review shipments that need attention.',icon:Truck},{id:'settings',title:'Shipping settings',description:'Manage checkout delivery rates, courier connections and sender details.',icon:Server}] as const).map(({id,title,description,icon:Icon})=><button className="jw-shipping-module" key={id} onClick={()=>setActiveTab(id)}><span className="jw-shipping-icon"><Icon size={21}/></span><h3>{title}</h3><p>{description}</p><span className="jw-shipping-open">{id==='fulfillment'?'Prepare shipments':id==='deliveries'?'Track deliveries':'Manage settings'} →</span></button>)}</div>
        </>}
        {activeTab === 'rates' && (
          <ShippingRatesTab
            onOpenInsertModal={handleOpenNewRate}
            onOpenEditModal={handleOpenEditRate}
          />
        )}

        {(activeTab === 'fulfillment'||activeTab==='deliveries') && (
          <ShippingFulfillmentTab
            key={activeTab}
            mode={activeTab === 'fulfillment' ? 'prepare' : 'deliveries'}
            parcels={activeTab==='deliveries'?deliveryParcels(shipmentParcels):preparationParcels(shipmentParcels)}
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
        <SharedFulfillmentFlow parcel={fulfilmentParcel} onClose={()=>setFulfilmentParcel(null)}/>
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
