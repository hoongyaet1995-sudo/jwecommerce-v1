import { visibleShipmentParcels } from '../../integration/shipmentQueue';
import { SharedFulfillmentFlow } from '../shipping/SharedFulfillmentFlow';
import type { ShipmentParcel } from '../../types/shipping';
import { CommerceHeading, CommerceAction } from '../commerce/CommerceUI';
import { paymentStatusLabel } from '../../integration/paymentStatusLabel';
import { useAdminCurrency } from '../../context/useAdminCurrency';
import React, { useEffect, useRef, useState } from 'react';
import { useAdmin } from '../../context/AdminContext';
import { Order, FulfillmentStatus, NotificationEventKey, OrderNotificationSummary, PaymentStatus } from '../../types';
import { OrderNotificationPanel } from './OrderNotificationPanel';
import { OrderPaymentProofPreviewModal } from './OrderPaymentProofPreviewModal';
import { AmendDeliveryDetailsModal } from './AmendDeliveryDetailsModal';
import {OrderDialogOverlay} from './OrderDialogOverlay';
import {OrderDetailView} from './OrderDetailView';
import { OrderReturnRequestCard } from './OrderReturnRequestCard';
import { createReturnRequestData, mutateReturnRequestData } from '../../integration/orderData';
import { fetchCommerceSettings } from '../../integration/commerceSettingsData';
import {
  ShoppingBag,
  Search,
  Filter,
  CheckCircle2,
  Clock,
  Truck,
  RotateCcw,
  AlertCircle,
  Eye,
  Edit2,
  X,
  CreditCard,
  MapPin,
  User,
  DollarSign,
  PackageCheck,
  Send,
  History,
  ShieldCheck,
  Layers,
  Download,
  FileText,
  AlertTriangle,
  RefreshCw
} from 'lucide-react';

const NOTIFICATION_EVENT_BY_STATUS_CODE = new Map<string, NotificationEventKey>([
  ['pending', 'order_created'],
  ['processing', 'payment_verified'],
  ['ready_to_ship', 'ready_to_ship'],
  ['shipped', 'shipped'],
  ['delivered', 'delivered'],
  ['cancelled', 'cancelled'],
  ['on_hold', 'on_hold'],
]);

const FULFILLMENT_BY_STATUS_CODE = new Map<string, FulfillmentStatus>([
  ['processing', 'processing'],
  ['ready_to_ship', 'processing'],
  ['shipped', 'shipped'],
  ['delivered', 'delivered'],
  ['cancelled', 'cancelled'],
]);

export const notificationEventKeyForStatus = (code: string): NotificationEventKey | null =>
  NOTIFICATION_EVENT_BY_STATUS_CODE.get(code) ?? null;

export const shouldShowReturnsWorkbench = (enabled: boolean, historicalCount: number): boolean => enabled || historicalCount > 0;

export const OrderAdmin: React.FC = () => {
  const { money, currencyCode } = useAdminCurrency();
  const { laravelBootstrap, orders, shipmentParcels = [], updateOrderStatus, updateOrderTracking, reviewPaymentProof, amendOrderDeliveryDetails, reloadOrders, reloadShipmentParcels, orderStatuses, setCurrentSection, setSelectedCustomerId, notificationPermissions } = useAdmin();
  const [isReloading, setIsReloading] = useState(false);
  const reloadStream = async () => {
    if (isReloading) return;
    setIsReloading(true);
    try {
      await reloadOrders();
      await reloadShipmentParcels?.();
      setEligibilityError('');
    } finally {
      setIsReloading(false);
    }
  };

  const [sharedParcel,setSharedParcel]=useState<ShipmentParcel|null>(null);
  const [eligibilityLoading,setEligibilityLoading]=useState(false);
  const [eligibilityError,setEligibilityError]=useState('');
  const eligibilityVersion=orders.map(o=>`${o.id}:${o.fulfillmentStatus}:${o.paymentGatewayStatus}`).join('|');
  const eligibilityLoader=useRef(reloadShipmentParcels);eligibilityLoader.current=reloadShipmentParcels;
  useEffect(()=>{let active=true;const reload=eligibilityLoader.current;if(!reload)return;setEligibilityLoading(true);setEligibilityError('');void reload().catch(()=>{if(active)setEligibilityError('Shipment availability could not be refreshed. Click Refresh to check again.')}).finally(()=>{if(active)setEligibilityLoading(false)});return()=>{active=false}},[eligibilityVersion]);

  const [filtersOpen,setFiltersOpen]=useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedStatus, setSelectedStatus] = useState<string>('all');
  const [returnsEnabled, setReturnsEnabled] = useState(true);
  const [rmaBusy,setRmaBusy]=useState(false);
  const [selectedOrder, setSelectedOrder] = useState<Order | null>(null);
  const [isDetailOpen, setIsDetailOpen] = useState(false);
  const [returnModal,setReturnModal]=useState(false);
  const [statusModal,setStatusModal]=useState(false);
  const [statusReview,setStatusReview]=useState(false);
  const [detailError,setDetailError]=useState('');
  const [isProofPreviewOpen, setIsProofPreviewOpen] = useState(false);
  const [isRejectBoxOpen, setIsRejectBoxOpen] = useState(false);
  const [isAmendDeliveryOpen, setIsAmendDeliveryOpen] = useState(false);
  const [rejectionReasonInput, setRejectionReasonInput] = useState('');
  const returnHistoryCount = orders.filter(order => order.returnRequest).length;
  const showReturnsWorkbench = shouldShowReturnsWorkbench(returnsEnabled, returnHistoryCount);

  useEffect(() => {
    let active = true;
    void fetchCommerceSettings(laravelBootstrap).then(document => {
      if (active) setReturnsEnabled(document.returnPolicy?.enabled ?? true);
    }).catch(() => undefined);
    return () => { active = false; };
  }, [laravelBootstrap]);

  useEffect(() => {
    if (!showReturnsWorkbench && (selectedStatus === 'returns' || selectedStatus.startsWith('rma:'))) setSelectedStatus('all');
  }, [selectedStatus, showReturnsWorkbench]);

  // Status Change Workflow State
  const [chosenStatusId, setChosenStatusId] = useState<string>('');
  const [notifyCustomerCheck, setNotifyCustomerCheck] = useState<boolean>(true);
  const [customerCommentInput, setCustomerCommentInput] = useState<string>('');
  const [statusMutationDispatch, setStatusMutationDispatch] = useState<OrderNotificationSummary | null>(null);
  const [notificationAuditRefreshKey, setNotificationAuditRefreshKey] = useState(0);
  const [isUpdatingStatus, setIsUpdatingStatus] = useState(false);
  const selectedOrderRef = useRef<Order | null>(null);
  const selectedOrderMutationRequestRef = useRef(0);
  const canViewNotificationAudit = notificationPermissions.includes('notifications.deliveries.view');

  // Manual Fulfillment State
  const [manualCarrier, setManualCarrier] = useState('');
  const [manualTracking, setManualTracking] = useState('');
  const [carrierError, setCarrierError] = useState('');
  const [trackingError, setTrackingError] = useState('');
  const [isDispatched, setIsDispatched] = useState(false);

  // Tracking Number Input state
  const [trackingNumberInput, setTrackingNumberInput] = useState('');

  const filteredOrders = orders.filter(o => {
    const matchesSearch =
      o.orderNumber.toLowerCase().includes(searchTerm.toLowerCase()) ||
      o.customerName.toLowerCase().includes(searchTerm.toLowerCase()) ||
      o.customerEmail.toLowerCase().includes(searchTerm.toLowerCase());
    const matchesRma = o.returnRequest?.number.toLowerCase().includes(searchTerm.toLowerCase()) || o.returnRequest?.item.sku.toLowerCase().includes(searchTerm.toLowerCase());

    const matchesStatus =
      selectedStatus === 'all' || selectedStatus === 'returns' && Boolean(o.returnRequest) || selectedStatus.startsWith('rma:') && o.returnRequest?.status === selectedStatus.slice(4) ||
      o.fulfillmentStatus === selectedStatus ||
      o.customStatusId === selectedStatus;

    return (matchesSearch || Boolean(matchesRma)) && matchesStatus;
  });

  const [orderRouteId,setOrderRouteId]=useState(()=>new URLSearchParams(window.location.search).get('order'));
  useEffect(()=>{const restore=()=>setOrderRouteId(new URLSearchParams(window.location.search).get('order'));window.addEventListener('popstate',restore);return()=>window.removeEventListener('popstate',restore)},[]);
  const writeOrderRoute=(id:string|null)=>{const url=new URL(window.location.href);if(id)url.searchParams.set('order',id);else url.searchParams.delete('order');window.history.replaceState(window.history.state,'',url);setOrderRouteId(id)};
  const handleOpenDetail = (order: Order) => {
    writeOrderRoute(order.id);
    selectedOrderMutationRequestRef.current += 1;
    selectedOrderRef.current = order;
    setSelectedOrder(order);
    setTrackingNumberInput(order.trackingNumber || '');
    setManualCarrier(order.trackingCarrier || '');
    setManualTracking(order.trackingNumber || '');
    setCarrierError('');
    setTrackingError('');
    setIsDispatched(Boolean(order.trackingCarrier && order.trackingNumber));

    // Set initial chosen status
    const currentStatusObj = orderStatuses.find(
      s => s.id === order.customStatusId || s.code === order.fulfillmentStatus
    ) || orderStatuses[0];

    setChosenStatusId(currentStatusObj?.id || '');
    setNotifyCustomerCheck(Boolean(currentStatusObj?.notifyCustomer));
    setCustomerCommentInput('');
    setStatusMutationDispatch(null);
    setIsUpdatingStatus(false);
    setIsProofPreviewOpen(false);
    setIsRejectBoxOpen(false);
    setRejectionReasonInput('');
    setIsDetailOpen(true);
  };

  useEffect(()=>{if(orderRouteId){const found=orders.find(o=>o.id===orderRouteId);if(found&&selectedOrderRef.current?.id!==found.id)handleOpenDetail(found)}else if(selectedOrderRef.current){selectedOrderRef.current=null;setSelectedOrder(null);setIsDetailOpen(false)}},[orderRouteId,orders]);

  const handleCloseDetail = () => {
    writeOrderRoute(null);
    selectedOrderMutationRequestRef.current += 1;
    selectedOrderRef.current = null;
    setIsUpdatingStatus(false);
    setIsDetailOpen(false);
    setIsAmendDeliveryOpen(false);
  };

  const deliveryReasonCode = (reason: string) => {
    if (reason.startsWith('Customer requested')) return 'customer_request' as const;
    if (reason.startsWith('Mobile contact') || reason.startsWith('Recipient name')) return 'invalid_receiver_details' as const;
    if (reason.includes('Typo') || reason.includes('Postcode') || reason.includes('Building')) return 'address_typo' as const;
    return 'other' as const;
  };

  const handlePaymentProofReview = async (decision: 'verified' | 'rejected') => {
    if (!selectedOrder?.paymentProof?.paymentId) return;
    const reason = rejectionReasonInput.trim();
    if (decision === 'rejected' && !reason) return;
    const orderId = selectedOrder.id;
    const requestId = ++selectedOrderMutationRequestRef.current;
    setIsUpdatingStatus(false);
    const saved = await reviewPaymentProof(orderId, selectedOrder.paymentProof.paymentId, decision, reason || undefined);
    if (requestId !== selectedOrderMutationRequestRef.current || selectedOrderRef.current?.id !== orderId) return;
    if (saved) {
      selectedOrderRef.current = saved;
      setSelectedOrder(saved);
      if (canViewNotificationAudit) setNotificationAuditRefreshKey(value => value + 1);
      setIsRejectBoxOpen(false);
      setRejectionReasonInput('');
    }
  };

  const handleProofDownload = () => {
    const url = selectedOrder?.paymentProof?.downloadUrl;
    if (url) window.open(url, '_blank', 'noopener');
  };

  const handleStatusSelectChange = (statusId: string) => {
    selectedOrderMutationRequestRef.current += 1;
    setIsUpdatingStatus(false);
    setChosenStatusId(statusId);
    setStatusMutationDispatch(null);
    const targetStatus = orderStatuses.find(s => s.id === statusId);
    if (targetStatus) {
      setNotifyCustomerCheck(Boolean(targetStatus.notifyCustomer && notificationEventKeyForStatus(targetStatus.code)));
    }
  };

  const handleCustomerCommentChange = (value: string) => {
    selectedOrderMutationRequestRef.current += 1;
    setIsUpdatingStatus(false);
    setStatusMutationDispatch(null);
    setCustomerCommentInput(value);
  };

  const handleNotifyCustomerChange = (checked: boolean) => {
    selectedOrderMutationRequestRef.current += 1;
    setIsUpdatingStatus(false);
    setStatusMutationDispatch(null);
    setNotifyCustomerCheck(checked);
  };

  const handleRecordDispatch = async () => {
    let hasError = false;
    if (!manualCarrier.trim()) {
      setCarrierError('Carrier / hand-off partner is required');
      hasError = true;
    } else {
      setCarrierError('');
    }

    if (!manualTracking.trim()) {
      setTrackingError('Tracking reference is required');
      hasError = true;
    } else {
      setTrackingError('');
    }

    if (hasError || !selectedOrder) return;

    const carrier = manualCarrier.trim();
    const tracking = manualTracking.trim();
    const orderId = selectedOrder.id;
    const requestId = ++selectedOrderMutationRequestRef.current;
    setIsUpdatingStatus(false);
    const saved = await updateOrderTracking(selectedOrder.id, carrier, tracking);
    if (requestId !== selectedOrderMutationRequestRef.current || selectedOrderRef.current?.id !== orderId) return;
    if (saved) {
      selectedOrderRef.current = saved;
      setSelectedOrder(saved);
      setManualCarrier(saved.trackingCarrier || carrier);
      setManualTracking(saved.trackingNumber || tracking);
      setIsDispatched(true);
      if (canViewNotificationAudit) setNotificationAuditRefreshKey(value => value + 1);
    }
  };

  const handleApplyOrderStatusUpdate = async () => {
    if (!selectedOrder) return;
    const targetStatus = orderStatuses.find(s => s.id === chosenStatusId);
    if (!targetStatus) return;
    const orderId = selectedOrder.id;
    const requestId = ++selectedOrderMutationRequestRef.current;
    setIsUpdatingStatus(true);
    setStatusMutationDispatch(null);

    // Map to standard fulfillment status if applicable
    const mappedFulfillment = FULFILLMENT_BY_STATUS_CODE.get(targetStatus.code) ?? selectedOrder.fulfillmentStatus;

    // Map payment status if default paid
    let mappedPayment = selectedOrder.paymentGatewayStatus;
    // Payment is verified by the payment workflow, not by selecting an order status.
    const saved = await updateOrderStatus(
      orderId,
      mappedPayment,
      mappedFulfillment,
      customerCommentInput || `Order transitioned to ${targetStatus.name}. Inventory action: ${targetStatus.inventoryAction}.`,
      notificationEventKeyForStatus(targetStatus.code) !== null,
      targetStatus.code,
    );
    if (requestId !== selectedOrderMutationRequestRef.current || selectedOrderRef.current?.id !== orderId) return;

    if (!saved) throw new Error('The status could not be changed. Check the order payment and current stage.');
    if (saved) {
      selectedOrderRef.current = saved;
      setSelectedOrder(saved);
      setStatusMutationDispatch(saved.notificationSummary);
      setNotificationAuditRefreshKey(value => value + 1);
    }
    setIsUpdatingStatus(false);
  };

  const selectedNotificationEventKey = notificationEventKeyForStatus(
    orderStatuses.find(status => status.id === chosenStatusId)?.code || '',
  );

  const renderStatusBadge = (status: FulfillmentStatus) => {
    switch (status) {
      case 'delivered':
        return <span className="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800"><CheckCircle2 className="w-2.5 h-2.5" /> Delivered</span>;
      case 'shipped':
        return <span className="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300"><Truck className="w-2.5 h-2.5" /> Shipped</span>;
      case 'processing':
        return <span className="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300"><Clock className="w-2.5 h-2.5" /> Processing</span>;
      case 'cancelled':
        return <span className="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-neutral-200 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400">Cancelled</span>;
      default:
        return <span className="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-300">Unfulfilled</span>;
    }
  };

  const renderPaymentBadge = (status: PaymentStatus) => {
    switch (status) {
      case 'paid':
        return <span className="text-[10px] font-bold text-emerald-600 dark:text-emerald-400">Paid</span>;
      case 'refunded':
        return <span className="text-[10px] font-bold text-rose-600 dark:text-rose-400">Refunded</span>;
      default:
        return <span className="text-[10px] font-bold text-amber-600 dark:text-amber-400">{paymentStatusLabel(status)}</span>;
    }
  };

  if(sharedParcel)return <SharedFulfillmentFlow parcel={sharedParcel} onClose={()=>setSharedParcel(null)}/>;
  return (
    <div className="jw-commerce space-y-6 pb-12">
      {!isDetailOpen&&<><CommerceHeading title="Orders" description="Review payments, fulfill orders and follow delivery." section="Orders" onOverview={()=>setCurrentSection('commerce')} actions={<button className="jw-btn" onClick={()=>void reloadStream()} disabled={isReloading}>{isReloading?'Loading…':'Refresh'}</button>}/>
      {/* Filter Bar */}
      <div className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs flex flex-col md:flex-row items-center justify-between gap-3">
        <div className="relative w-full md:w-80">
          <Search className="w-4 h-4 text-neutral-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            placeholder="Search order #, customer name, email..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="w-full pl-9 pr-4 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
          />
        </div>

        <div className="flex items-center gap-2">
          <button className="jw-btn" aria-expanded={filtersOpen} onClick={()=>setFiltersOpen(!filtersOpen)}><Filter size={16}/> Filters</button>
          <div hidden={!filtersOpen} className="flex items-center gap-2">
          {showReturnsWorkbench&&<button type="button" onClick={()=>setSelectedStatus(selectedStatus==='returns'?'all':'returns')} className="px-3 py-2 text-xs font-bold rounded-xl border border-neutral-200 dark:border-neutral-700">Returns ({returnHistoryCount})</button>}
          <select
            value={selectedStatus}
            onChange={(e) => setSelectedStatus(e.target.value)}
            className="px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
          >
            <option value="all">All Fulfillment Statuses</option>
            {showReturnsWorkbench&&<><option value="returns">Returns: All</option><option value="rma:requested">RMA: New Request</option><option value="rma:approved">RMA: Awaiting Return</option><option value="rma:received">RMA: Item Received</option><option value="rma:resolved_refund">RMA: Resolved</option><option value="rma:resolved_replacement">RMA: Resolved Replacement</option><option value="rma:rejected">RMA: Rejected</option></>}
            <option value="unfulfilled">Unfulfilled</option>
            <option value="processing">Processing</option>
            <option value="shipped">Shipped</option>
            <option value="delivered">Delivered</option>
            <option value="cancelled">Cancelled</option>
          </select></div>
        </div>
      </div>

      {/* Orders Table */}
      <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-3xl overflow-hidden shadow-sm">
        <div className="overflow-x-auto">
          <table className="jw-orders-table w-full text-left text-xs border-collapse">
            <thead>
              <tr className="border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50/70 dark:bg-neutral-800/60 text-[10px] uppercase font-bold text-neutral-500 tracking-wider">
                <th className="py-3.5 px-4">Order #</th>
                <th className="py-3.5 px-4">Customer</th>
                <th className="py-3.5 px-4">Items / SKUs</th>
                <th className="py-3.5 px-4">Total Amount</th>
                <th className="py-3.5 px-4">Payment</th>
                <th className="py-3.5 px-4">Fulfillment Status</th>
                <th className="py-3.5 px-4">Date</th>
                <th className="py-3.5 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800 text-neutral-800 dark:text-neutral-200">
              {filteredOrders.map((ord) => (
                <tr key={ord.id} className="hover:bg-neutral-50/60 dark:hover:bg-neutral-800/50 transition-colors">
                  <td className="py-3.5 px-4 font-mono font-bold text-neutral-900 dark:text-white">
                    {ord.orderNumber}{ord.returnRequest&&<div className="mt-1 inline-flex px-2 py-0.5 rounded-full text-[9px] bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-300">RMA: {ord.returnRequest.status.replaceAll('_',' ')}</div>}
                  </td>

                  <td className="py-3.5 px-4">
                    <div className="font-semibold text-neutral-900 dark:text-white text-xs">
                      {ord.customerName}
                    </div>
                    <div className="text-[11px] text-neutral-400 font-mono">
                      {ord.customerEmail}
                    </div>
                  </td>

                  <td className="py-3.5 px-4">
                    <div className="flex items-center gap-1.5">
                      <span className="font-semibold">{ord.items.length} items</span>
                      <span className="text-neutral-400 text-[10px]">
                        ({ord.items.reduce((s, i) => s + i.quantity, 0)} units)
                      </span>
                    </div>
                  </td>

                  <td className="py-3.5 px-4 font-mono font-bold text-neutral-900 dark:text-white text-xs">
                    {money(ord.totalAmount, ord.currency, ord.currencyExchangeRate)}
                  </td>

                  <td className="py-3.5 px-4">
                    {renderPaymentBadge(ord.paymentGatewayStatus)}
                  </td>

                  <td className="py-3.5 px-4">
                    {ord.orderStatusName ? <span className="jw-order-badge">{ord.orderStatusName}</span> : renderStatusBadge(ord.fulfillmentStatus)}
                  </td>

                  <td className="py-3.5 px-4 font-mono text-[11px] text-neutral-400">
                    {ord.createdAt.split('T')[0]}
                  </td>

                  <td className="py-3.5 px-4 text-right">
                    <CommerceAction label={ord.orderNumber} onClick={()=>handleOpenDetail(ord)} onPrepare={()=>{const parcel=visibleShipmentParcels(shipmentParcels).find(p=>p.orderId===ord.id&&p.source==='pending');if(parcel)setSharedParcel(parcel)}} prepareDisabledReason={eligibilityLoading?'Checking shipment availability…':eligibilityError|| (visibleShipmentParcels(shipmentParcels).some(p=>p.orderId===ord.id&&p.source==='pending')?undefined:'Payment or shipment preparation must be completed before preparing this order.')}/>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      </>}
      {isDetailOpen&&selectedOrder&&<OrderDetailView order={selectedOrder} invoiceUrl={`${laravelBootstrap.ordersUrl}/${encodeURIComponent(selectedOrder.id)}/invoice`} onHome={()=>setCurrentSection('commerce')} onClose={handleCloseDetail} onCustomer={()=>{setSelectedCustomerId(selectedOrder.customerId);setCurrentSection('customers')}} onEditAddress={()=>setIsAmendDeliveryOpen(true)} onStartFulfillment={()=>{const processing=orderStatuses.find(s=>s.code==='processing');if(processing)handleStatusSelectChange(processing.id);setDetailError('');setStatusReview(false);setStatusModal(true)}} onStatus={()=>{setDetailError('');setStatusReview(false);setStatusModal(true)}} canPrepare={visibleShipmentParcels(shipmentParcels).some(p=>p.orderId===selectedOrder.id&&p.source==='pending')} onPrepare={()=>{const p=visibleShipmentParcels(shipmentParcels).find(p=>p.orderId===selectedOrder.id&&p.source==='pending');if(p)setSharedParcel(p)}} onViewShipments={()=>setCurrentSection('shipping-malaysia')} payment={<>                <div className="space-y-1.5 text-[11px] font-mono text-neutral-600 dark:text-neutral-300 pb-3 border-b border-neutral-200 dark:border-neutral-700/80">
                  <div className="flex items-center justify-between gap-3"><span className="text-neutral-500 dark:text-neutral-400">Payment Method:</span><span className="font-semibold text-neutral-800 dark:text-neutral-200 text-right">{selectedOrder.paymentGateway}</span></div>
                  <div className="flex items-center justify-between gap-3"><span className="text-neutral-500 dark:text-neutral-400">Provider:</span><span className="text-neutral-700 dark:text-neutral-300 text-right">{selectedOrder.paymentProvider || 'Not recorded'}</span></div>
                  <div className="flex items-center justify-between gap-3"><span className="text-neutral-500 dark:text-neutral-400">Reference:</span><span className="font-mono font-medium text-neutral-800 dark:text-neutral-200 text-right break-all">{selectedOrder.paymentReference || 'Not recorded'}</span></div>
                  <div className="flex items-center justify-between gap-3"><span className="text-neutral-500 dark:text-neutral-400">Payment Status:</span><span>{renderPaymentBadge(selectedOrder.paymentGatewayStatus)}</span></div>
                  {selectedOrder.paymentProof?.required && <div className="flex items-center justify-between gap-3"><span className="text-neutral-500 dark:text-neutral-400">Payment Proof:</span><span className={`text-[10px] font-bold ${selectedOrder.paymentProof.status === 'verified' ? 'text-emerald-600 dark:text-emerald-400' : selectedOrder.paymentProof.status === 'rejected' ? 'text-rose-600 dark:text-rose-400' : 'text-amber-600 dark:text-amber-400'}`}>{selectedOrder.paymentProof.status.replace('_', ' ')}</span></div>}
                </div>

                {selectedOrder.paymentProof?.required && (
                  <div className="p-3 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200/80 dark:border-neutral-800 space-y-2.5">
                    {selectedOrder.paymentProof.status === 'uploaded' && (
                      <div className="space-y-2.5">
                        <div className="flex items-center justify-between gap-3"><span className="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-300 border border-amber-300 dark:border-amber-800"><FileText className="w-2.5 h-2.5" /> Receipt uploaded — awaiting staff review</span><span className="text-[10px] font-mono text-neutral-400 truncate">{selectedOrder.paymentProof.originalFileName || 'Protected receipt'}</span></div>
                        <div className="flex items-center gap-2 flex-wrap">
                          <button type="button" disabled={!selectedOrder.paymentProof.previewUrl} onClick={() => setIsProofPreviewOpen(true)} className="px-2.5 py-1.5 rounded-lg bg-neutral-100 dark:bg-neutral-800 hover:bg-neutral-200 dark:hover:bg-neutral-700 disabled:opacity-50 text-neutral-800 dark:text-neutral-200 font-semibold text-[11px] flex items-center gap-1 transition cursor-pointer"><Eye className="w-3.5 h-3.5" /><span>View proof</span></button>
                          <button type="button" disabled={!selectedOrder.paymentProof.downloadUrl} onClick={handleProofDownload} className="px-2.5 py-1.5 rounded-lg bg-neutral-100 dark:bg-neutral-800 hover:bg-neutral-200 dark:hover:bg-neutral-700 disabled:opacity-50 text-neutral-800 dark:text-neutral-200 font-semibold text-[11px] flex items-center gap-1 transition cursor-pointer"><Download className="w-3.5 h-3.5" /><span>Download</span></button>
                          {selectedOrder.paymentProof.canReview && <><button type="button" onClick={() => handlePaymentProofReview('verified')} className="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[11px] flex items-center gap-1 shadow-xs transition cursor-pointer ml-auto"><ShieldCheck className="w-3.5 h-3.5" /><span>Verify payment</span></button><button type="button" onClick={() => setIsRejectBoxOpen(value => !value)} className="px-2.5 py-1.5 rounded-lg bg-rose-50 dark:bg-rose-950/60 hover:bg-rose-100 dark:hover:bg-rose-900/60 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800/80 font-bold text-[11px] flex items-center gap-1 transition cursor-pointer"><AlertCircle className="w-3.5 h-3.5" /><span>Reject proof</span></button></>}
                        </div>
                        {isRejectBoxOpen && <div className="p-2.5 rounded-xl bg-rose-50/60 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900/60 space-y-2 animate-in fade-in"><label className="text-[10px] font-bold text-rose-800 dark:text-rose-300 uppercase tracking-wider">Rejection Reason / Verification Note</label><input type="text" placeholder="Type reason for customer notice..." value={rejectionReasonInput} onChange={event => setRejectionReasonInput(event.target.value)} className="w-full px-2.5 py-1.5 text-xs rounded-lg border bg-white dark:bg-neutral-900 border-rose-300 dark:border-rose-800 text-neutral-900 dark:text-white" /><div className="flex justify-end gap-2"><button type="button" onClick={() => setIsRejectBoxOpen(false)} className="px-2.5 py-1 text-[11px] rounded-lg text-neutral-600 hover:text-neutral-800 font-medium">Cancel</button><button type="button" disabled={!rejectionReasonInput.trim()} onClick={() => handlePaymentProofReview('rejected')} className="px-3 py-1 text-[11px] rounded-lg bg-rose-600 hover:bg-rose-700 disabled:opacity-50 text-white font-bold shadow-xs cursor-pointer">Confirm Rejection</button></div></div>}
                      </div>
                    )}
                    {selectedOrder.paymentProof.status === 'verified' && <div className="flex items-center justify-between gap-3"><div><span className="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800"><ShieldCheck className="w-2.5 h-2.5" /> Payment verified</span><p className="text-[10px] text-emerald-600 dark:text-emerald-400 font-medium mt-1">Ready for fulfillment</p></div><button type="button" onClick={() => setIsProofPreviewOpen(true)} className="px-2.5 py-1 rounded-lg bg-neutral-100 dark:bg-neutral-800 hover:bg-neutral-200 dark:hover:bg-neutral-700 text-neutral-800 dark:text-neutral-200 font-semibold text-[11px] flex items-center gap-1 transition cursor-pointer"><Eye className="w-3.5 h-3.5" />View proof</button></div>}
                    {selectedOrder.paymentProof.status === 'rejected' && <div className="space-y-2"><span className="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-rose-100 dark:bg-rose-950 text-rose-700 dark:text-rose-300 border border-rose-300 dark:border-rose-800"><AlertCircle className="w-2.5 h-2.5" /> Proof rejected</span><p className="text-[11px] text-rose-700 dark:text-rose-300">{selectedOrder.paymentProof.audit?.find(event => event.eventType === 'rejected')?.reason || 'Awaiting customer re-upload.'}</p><button type="button" onClick={() => setIsProofPreviewOpen(true)} className="px-2.5 py-1 rounded-lg bg-neutral-100 dark:bg-neutral-800 hover:bg-neutral-200 dark:hover:bg-neutral-700 text-neutral-800 dark:text-neutral-200 font-semibold text-[11px] flex items-center gap-1 transition cursor-pointer"><Eye className="w-3.5 h-3.5" />View proof</button></div>}
                    {['missing', 'none'].includes(selectedOrder.paymentProof.status) && <p className="text-[11px] text-neutral-500 dark:text-neutral-400">No receipt uploaded by customer yet.</p>}
                  </div>
                )}

</>} afterSales={<section className="jw-order-panel"><header><h2><RotateCcw size={18} aria-hidden="true"/>After-sales</h2></header><div className="jw-order-body"><OrderReturnRequestCard order={selectedOrder} busy={rmaBusy} onAction={async(action,body)=>{if(!selectedOrder.returnRequest)return;setRmaBusy(true);try{const saved=await mutateReturnRequestData(laravelBootstrap,selectedOrder.returnRequest.id,action,body);selectedOrderRef.current=saved;setSelectedOrder(saved);await reloadOrders()}finally{setRmaBusy(false)}}}/>{!selectedOrder.returnRequest&&<><p>No request</p><button className="jw-btn" onClick={()=>{setDetailError('');setReturnModal(true)}}>Returns & refunds</button></>}</div></section>}/>}
      {returnModal&&selectedOrder&&<OrderDialogOverlay busy={rmaBusy} onClose={()=>setReturnModal(false)}><section className="jw-order-dialog" role="dialog" aria-modal="true" aria-label="Return or refund request"><header><h2>Return or refund request</h2><button disabled={rmaBusy} aria-label="Close" onClick={()=>setReturnModal(false)}><X size={18}/></button></header><form onSubmit={async e=>{e.preventDefault();const values=new FormData(e.currentTarget);setRmaBusy(true);setDetailError('');try{const saved=await createReturnRequestData(laravelBootstrap,selectedOrder.id,{order_item_id:Number(values.get('item')),quantity:Number(values.get('quantity')),resolution:values.get('resolution'),reason:values.get('reason'),details:values.get('details')});selectedOrderRef.current=saved;setSelectedOrder(saved);setReturnModal(false);await reloadOrders()}catch(error){setDetailError(error instanceof Error?error.message:'The request could not be saved.')}finally{setRmaBusy(false)}}}>{detailError&&<p role="alert">{detailError}</p>}<label>Resolution<select name="resolution"><option value="refund">Return & refund</option><option value="replacement">Replacement</option></select></label><label>Items<select name="item">{selectedOrder.items.map(i=><option key={i.id} value={i.id}>{i.title} · Qty {i.quantity}</option>)}</select></label><label>Quantity<input name="quantity" type="number" min="1" defaultValue="1" required/></label><label>Reason<select name="reason"><option value="defective">Defective</option><option value="damaged_in_transit">Damaged in transit</option><option value="incorrect_item">Incorrect item</option><option value="missing_parts">Missing parts</option><option value="quality">Quality</option><option value="other">Other</option></select></label><label>Details<textarea name="details" required maxLength={2000}/></label><p>Creating a request does not issue a refund, restock items or cancel delivery.</p><footer><button type="button" className="jw-btn" disabled={rmaBusy} onClick={()=>setReturnModal(false)}>Cancel</button><button className="jw-btn jw-btn-primary" disabled={rmaBusy}>{rmaBusy?'Saving…':'Create request'}</button></footer></form></section></OrderDialogOverlay>}
      {statusModal&&selectedOrder&&<OrderDialogOverlay busy={isUpdatingStatus} onClose={()=>setStatusModal(false)}><section className="jw-order-dialog" role="dialog" aria-modal="true" aria-label="Update order status"><header><h2>{statusReview?'Review status change':'Update order status'}</h2><button aria-label="Close" disabled={isUpdatingStatus} onClick={()=>setStatusModal(false)}><X size={18}/></button></header><p>Order {selectedOrder.orderNumber}</p>{detailError&&<p role="alert">{detailError}</p>}<form onSubmit={async e=>{e.preventDefault();if(!statusReview){setStatusReview(true);return}try{await handleApplyOrderStatusUpdate();setStatusModal(false)}catch(error){setIsUpdatingStatus(false);setDetailError(error instanceof Error?error.message:'Order could not be updated. Your details are retained.')}}}><label>New status<select disabled={statusReview||isUpdatingStatus} value={chosenStatusId} onChange={e=>handleStatusSelectChange(e.target.value)}>{orderStatuses.filter(status => !['completed'].includes(status.code)).map(st=><option key={st.id} value={st.id}>{st.name}</option>)}</select></label><label>Reason (optional)<textarea maxLength={2000} disabled={statusReview||isUpdatingStatus} value={customerCommentInput} onChange={e=>handleCustomerCommentChange(e.target.value)}/></label><p className="jw-order-muted">Applicable customer notifications are sent automatically.</p><footer><button type="button" className="jw-btn" disabled={isUpdatingStatus} onClick={()=>statusReview?setStatusReview(false):setStatusModal(false)}>{statusReview?'Back':'Cancel'}</button><button className="jw-btn jw-btn-primary" disabled={isUpdatingStatus}>{isUpdatingStatus?'Saving…':statusReview?'Confirm change':'Review change'}</button></footer></form></section></OrderDialogOverlay>}
      {sharedParcel&&<SharedFulfillmentFlow parcel={sharedParcel} onClose={()=>setSharedParcel(null)}/>}
      {selectedOrder && (
        <AmendDeliveryDetailsModal
          isOpen={isAmendDeliveryOpen}
          order={selectedOrder}
          hasActiveShipment={shipmentParcels.some(parcel => parcel.orderId === selectedOrder.id && parcel.source !== 'pending' && parcel.status !== 'delivered' && parcel.status !== 'cancelled')}
          onClose={() => setIsAmendDeliveryOpen(false)}
          onSave={async (orderId, updatedAddress, metadata) => {
            const saved = await amendOrderDeliveryDetails(orderId, {
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
            selectedOrderRef.current = saved;
            setSelectedOrder(saved);
          }}
        />
      )}
      {selectedOrder && (
        <OrderPaymentProofPreviewModal
          isOpen={isProofPreviewOpen}
          orderNumber={selectedOrder.orderNumber}
          paymentMethod={selectedOrder.paymentGateway}
          paymentProvider={selectedOrder.paymentProvider}
          paymentReference={selectedOrder.paymentReference}
          proof={selectedOrder.paymentProof}
          onDownload={handleProofDownload}
          onClose={() => setIsProofPreviewOpen(false)}
        />
      )}
    </div>
  );
};
