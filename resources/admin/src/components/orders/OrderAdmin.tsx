import React, { useRef, useState } from 'react';
import { useAdmin } from '../../context/AdminContext';
import { Order, FulfillmentStatus, NotificationEventKey, OrderNotificationSummary, PaymentStatus } from '../../types';
import { OrderNotificationPanel } from './OrderNotificationPanel';
import { OrderPaymentProofPreviewModal } from './OrderPaymentProofPreviewModal';
import { AmendDeliveryDetailsModal } from './AmendDeliveryDetailsModal';
import { OrderReturnRequestCard } from './OrderReturnRequestCard';
import { mutateReturnRequestData } from '../../integration/orderData';
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

export const OrderAdmin: React.FC = () => {
  const { laravelBootstrap, orders, shipmentParcels = [], updateOrderStatus, updateOrderTracking, reviewPaymentProof, amendOrderDeliveryDetails, reloadOrders, orderStatuses, setCurrentSection, notificationPermissions } = useAdmin();
  const [isReloading, setIsReloading] = useState(false);
  const reloadStream = async () => {
    if (isReloading) return;
    setIsReloading(true);
    try {
      await reloadOrders();
    } finally {
      setIsReloading(false);
    }
  };

  const [searchTerm, setSearchTerm] = useState('');
  const [selectedStatus, setSelectedStatus] = useState<string>('all');
  const [rmaBusy,setRmaBusy]=useState(false);
  const [selectedOrder, setSelectedOrder] = useState<Order | null>(null);
  const [isDetailOpen, setIsDetailOpen] = useState(false);
  const [isProofPreviewOpen, setIsProofPreviewOpen] = useState(false);
  const [isRejectBoxOpen, setIsRejectBoxOpen] = useState(false);
  const [isAmendDeliveryOpen, setIsAmendDeliveryOpen] = useState(false);
  const [rejectionReasonInput, setRejectionReasonInput] = useState('');

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

  const handleOpenDetail = (order: Order) => {
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

  const handleCloseDetail = () => {
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
    if (targetStatus.isDefaultPaid) mappedPayment = 'paid';
    const saved = await updateOrderStatus(
      orderId,
      mappedPayment,
      mappedFulfillment,
      customerCommentInput || `Order transitioned to ${targetStatus.name}. Inventory action: ${targetStatus.inventoryAction}.`,
      notifyCustomerCheck && notificationEventKeyForStatus(targetStatus.code) !== null,
    );
    if (requestId !== selectedOrderMutationRequestRef.current || selectedOrderRef.current?.id !== orderId) return;

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
        return <span className="text-[10px] font-bold text-amber-600 dark:text-amber-400">Pending</span>;
    }
  };

  return (
    <div className="space-y-6 pb-12">
      {/* Header Bar */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-3xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-sm">
        <div>
          <div className="flex items-center gap-2">
            <span className="px-2.5 py-0.5 text-[10px] font-bold uppercase rounded-full bg-blue-500/20 text-blue-700 dark:text-blue-300 border border-blue-500/30">
              Fulfillment Operations
            </span>
            <span className="text-xs text-neutral-500">Live Order Streams</span>
          </div>
          <h2 className="text-lg sm:text-xl font-bold text-neutral-900 dark:text-white tracking-tight mt-1">
            Order Fulfillment & Transactions
          </h2>
          <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
            Process checkout transactions, generate shipping waybills, manage refunds, and track customer lifecycles.
          </p>
        </div>

        <div className="flex items-center gap-2">
        <button type="button" onClick={() => void reloadStream()} disabled={isReloading} className="flex items-center gap-2 px-3.5 py-2.5 rounded-2xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-xs font-semibold disabled:opacity-60"><RefreshCw className={`w-3.5 h-3.5 ${isReloading ? 'animate-spin' : ''}`} />{isReloading ? 'Reloading...' : 'Reload Stream'}</button>
        <button
          onClick={() => setCurrentSection('shipping-malaysia')}
          className="flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-sm transition cursor-pointer self-start sm:self-auto"
        >
          <Truck className="w-4 h-4" />
          Malaysia Multi-Courier Hub →
        </button></div>
      </div>

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
          <button type="button" onClick={()=>setSelectedStatus(selectedStatus==='returns'?'all':'returns')} className="px-3 py-2 text-xs font-bold rounded-xl border border-neutral-200 dark:border-neutral-700">Returns ({orders.filter(o=>o.returnRequest).length})</button>
          <select
            value={selectedStatus}
            onChange={(e) => setSelectedStatus(e.target.value)}
            className="px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
          >
            <option value="all">All Fulfillment Statuses</option>
            <option value="returns">Returns: All</option><option value="rma:requested">RMA: New Request</option><option value="rma:approved">RMA: Awaiting Return</option><option value="rma:received">RMA: Item Received</option><option value="rma:resolved_refund">RMA: Resolved</option><option value="rma:resolved_replacement">RMA: Resolved Replacement</option><option value="rma:rejected">RMA: Rejected</option>
            <option value="unfulfilled">Unfulfilled</option>
            <option value="processing">Processing</option>
            <option value="shipped">Shipped</option>
            <option value="delivered">Delivered</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
      </div>

      {/* Orders Table */}
      <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-3xl overflow-hidden shadow-sm">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs border-collapse">
            <thead>
              <tr className="border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50/70 dark:bg-neutral-800/60 text-[10px] uppercase font-bold text-neutral-500 tracking-wider">
                <th className="py-3.5 px-4">Order #</th>
                <th className="py-3.5 px-4">Customer</th>
                <th className="py-3.5 px-4">Items / SKUs</th>
                <th className="py-3.5 px-4">Total Amount</th>
                <th className="py-3.5 px-4">Payment</th>
                <th className="py-3.5 px-4">Fulfillment Status</th>
                <th className="py-3.5 px-4">Date</th>
                <th className="py-3.5 px-4 text-right">Action</th>
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
                    ${ord.totalAmount.toFixed(2)}
                  </td>

                  <td className="py-3.5 px-4">
                    {renderPaymentBadge(ord.paymentGatewayStatus)}
                  </td>

                  <td className="py-3.5 px-4">
                    {renderStatusBadge(ord.fulfillmentStatus)}
                  </td>

                  <td className="py-3.5 px-4 font-mono text-[11px] text-neutral-400">
                    {ord.createdAt.split('T')[0]}
                  </td>

                  <td className="py-3.5 px-4 text-right">
                    <button
                      onClick={() => handleOpenDetail(ord)}
                      className="px-3 py-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-semibold text-xs hover:bg-indigo-100 flex items-center gap-1 ml-auto"
                    >
                      <Eye className="w-3.5 h-3.5" />
                      <span>Details</span>
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Order Detail Modal */}
      {isDetailOpen && selectedOrder && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 bg-black/60 backdrop-blur-xs animate-in fade-in">
          <div className="w-full max-w-3xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-3xl p-6 shadow-2xl max-h-[90vh] overflow-y-auto space-y-6">
            {/* Header */}
            <div className="flex items-center justify-between pb-4 border-b border-neutral-100 dark:border-neutral-800">
              <div>
                <div className="flex items-center gap-2">
                  <span className="font-mono font-black text-lg text-neutral-900 dark:text-white">
                    {selectedOrder.orderNumber}
                  </span>
                  {renderStatusBadge(selectedOrder.fulfillmentStatus)}
                </div>
                <p className="text-xs text-neutral-400 mt-0.5">Placed on {selectedOrder.createdAt}</p>
              </div>

              <button onClick={handleCloseDetail} className="text-neutral-400 hover:text-neutral-600">
                <X className="w-5 h-5" />
              </button>
            </div>

            <OrderReturnRequestCard order={selectedOrder} busy={rmaBusy} onAction={async(action,body)=>{if(!selectedOrder.returnRequest)return;setRmaBusy(true);try{const saved=await mutateReturnRequestData(laravelBootstrap,selectedOrder.returnRequest.id,action,body);selectedOrderRef.current=saved;setSelectedOrder(saved);await reloadOrders();}finally{setRmaBusy(false);}}}/>

            <OrderNotificationPanel
              order={selectedOrder}
              eventKey={selectedNotificationEventKey}
              staffNote={customerCommentInput}
              notifyCustomer={notifyCustomerCheck}
              mutationDispatch={statusMutationDispatch}
              auditRefreshKey={notificationAuditRefreshKey}
              onNotifyCustomerChange={handleNotifyCustomerChange}
              onConfigureStatuses={() => setCurrentSection('settings-orders-status')}
              onManageTemplates={() => {
                handleCloseDetail();
                setCurrentSection('settings-email');
              }}
              frameClassName="p-4 rounded-2xl bg-indigo-50/60 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-900/60 space-y-3"
              frameTitle="Update Order Status & Dispatch Customer Notification"
            >
              {/* Bank In / DuitNow Payment Gate Notice */}
              {selectedOrder.paymentProof?.required &&
                selectedOrder.paymentProof.status !== 'verified' &&
                selectedOrder.paymentGatewayStatus !== 'paid' && (
                  <div className="p-2.5 rounded-xl bg-amber-500/10 border border-amber-500/30 flex items-start gap-2 text-xs text-amber-800 dark:text-amber-300">
                    <AlertTriangle className="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
                    <div>
                      <strong className="font-semibold">Payment verification required:</strong>
                      <p className="text-[11px] text-amber-700/90 dark:text-amber-300/90 mt-0.5">
                        This manual Bank In / DuitNow order cannot proceed to parcel fulfillment or courier dispatch until payment proof is verified by authorized staff.
                      </p>
                    </div>
                  </div>
                )}

              <div className="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                <div className="sm:col-span-4 space-y-1">
                  <label className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300">
                    New Order Status
                  </label>
                  <select
                    value={chosenStatusId}
                    onChange={e => handleStatusSelectChange(e.target.value)}
                    className="w-full px-3 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-800 border-neutral-300 dark:border-neutral-700 font-bold text-neutral-900 dark:text-white"
                  >
                    {orderStatuses.map(st => (
                      <option key={st.id} value={st.id}>
                        {st.name} ({(st.inventoryAction || 'none').toUpperCase()})
                      </option>
                    ))}
                  </select>
                </div>

                <div className="sm:col-span-5 space-y-1">
                  <label className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300">
                    Customer Note / Timeline Comment
                  </label>
                  <input
                    type="text"
                    placeholder="e.g. Package packed with fragile bubble wrap. Ready for pickup."
                    value={customerCommentInput}
                    onChange={e => handleCustomerCommentChange(e.target.value)}
                    className="w-full px-3 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-800 border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white"
                  />
                </div>

                <div className="sm:col-span-3">
                  <button
                    type="button"
                    disabled={isUpdatingStatus}
                    onClick={handleApplyOrderStatusUpdate}
                    className="w-full py-2 px-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold text-xs flex items-center justify-center gap-1.5 shadow-sm transition cursor-pointer"
                  >
                    <Send className="w-3.5 h-3.5" />
                    <span>{isUpdatingStatus ? 'Updating...' : 'Update & Notify'}</span>
                  </button>
                </div>
              </div>

              {/* Compact Manual Fulfillment Subsection (Processing Orders Only) */}
              {(selectedOrder.fulfillmentStatus === 'processing' || isDispatched) && (
                <div className="pt-2.5 pb-1 border-t border-indigo-200/70 dark:border-indigo-900/60 space-y-2">
                  {!isDispatched && !selectedOrder.trackingNumber ? (
                    <>
                      <div className="grid grid-cols-1 sm:grid-cols-12 gap-3 items-start">
                        <div className="sm:col-span-4 space-y-1">
                          <label className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300">
                            Carrier / hand-off partner
                          </label>
                          <input
                            type="text"
                            placeholder="e.g. DHL Express, J&T Express, Ninja Van"
                            value={manualCarrier}
                            onChange={e => {
                              setManualCarrier(e.target.value);
                              if (carrierError && e.target.value.trim()) setCarrierError('');
                            }}
                            className={`w-full px-3 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white transition-colors ${
                              carrierError
                                ? 'border-rose-500 dark:border-rose-500 focus:ring-1 focus:ring-rose-500'
                                : 'border-neutral-300 dark:border-neutral-700'
                            }`}
                          />
                          {carrierError && (
                            <p className="text-[10px] text-rose-500 dark:text-rose-400 font-medium">
                              {carrierError}
                            </p>
                          )}
                        </div>

                        <div className="sm:col-span-5 space-y-1">
                          <label className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300">
                            Tracking reference
                          </label>
                          <input
                            type="text"
                            placeholder="e.g. TRK-892410294MY"
                            value={manualTracking}
                            onChange={e => {
                              setManualTracking(e.target.value);
                              if (trackingError && e.target.value.trim()) setTrackingError('');
                            }}
                            className={`w-full px-3 py-2 text-xs rounded-xl border bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white transition-colors ${
                              trackingError
                                ? 'border-rose-500 dark:border-rose-500 focus:ring-1 focus:ring-rose-500'
                                : 'border-neutral-300 dark:border-neutral-700'
                            }`}
                          />
                          {trackingError && (
                            <p className="text-[10px] text-rose-500 dark:text-rose-400 font-medium">
                              {trackingError}
                            </p>
                          )}
                        </div>

                        <div className="sm:col-span-3 sm:pt-[21px]">
                          <button
                            type="button"
                            onClick={handleRecordDispatch}
                            className="w-full py-2 px-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs flex items-center justify-center gap-1.5 shadow-sm transition cursor-pointer"
                          >
                            <Truck className="w-3.5 h-3.5" />
                            <span>Record dispatch</span>
                          </button>
                        </div>
                      </div>

                      <p className="text-[11px] text-neutral-500 dark:text-neutral-400">
                        Records a manual shipment and append-only dispatch event. No courier label is purchased.
                      </p>
                    </>
                  ) : (
                    <div className="p-2.5 px-3 rounded-xl bg-white/80 dark:bg-neutral-800/80 border border-emerald-200 dark:border-emerald-900/60 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                      <div className="flex items-center gap-2 flex-wrap">
                        <span className="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800">
                          <CheckCircle2 className="w-2.5 h-2.5" /> Dispatched
                        </span>
                        <span className="text-neutral-700 dark:text-neutral-300">
                          Carrier: <strong className="text-neutral-900 dark:text-white font-mono">{selectedOrder.trackingCarrier || manualCarrier}</strong>
                        </span>
                        <span className="text-neutral-300 dark:text-neutral-700 hidden sm:inline">•</span>
                        <span className="text-neutral-700 dark:text-neutral-300">
                          Tracking: <strong className="text-indigo-600 dark:text-indigo-400 font-mono">{selectedOrder.trackingNumber || manualTracking}</strong>
                        </span>
                      </div>
                      <span className="text-[10px] text-neutral-400 font-mono">
                        Manual Dispatch Recorded
                      </span>
                    </div>
                  )}
                </div>
              )}

            </OrderNotificationPanel>

            {/* Order Timeline History */}
            <div className="p-4 rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50/40 dark:bg-neutral-800/30 space-y-3">
              <div className="flex items-center gap-2">
                <History className="w-4 h-4 text-indigo-500" />
                <h4 className="text-xs font-bold uppercase tracking-wider text-neutral-800 dark:text-neutral-200">
                  Order Status Audit Trail
                </h4>
              </div>

              <div className="space-y-2.5 max-h-48 overflow-y-auto pr-1">
                {selectedOrder.timeline.map(ev => (
                  <div
                    key={ev.id}
                    className="p-2.5 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200/80 dark:border-neutral-800 text-xs flex items-start justify-between gap-3 shadow-2xs"
                  >
                    <div>
                      <div className="font-bold text-neutral-900 dark:text-white flex items-center gap-1.5">
                        <span>{ev.title}</span>
                      </div>
                      <p className="text-[11px] text-neutral-600 dark:text-neutral-400 mt-0.5">
                        {ev.description}
                      </p>
                    </div>
                    <span className="text-[10px] font-mono text-neutral-400 whitespace-nowrap">
                      {new Date(ev.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                    </span>
                  </div>
                ))}
              </div>
            </div>

            {/* Line Items List */}
            <div className="space-y-3">
              <h3 className="text-xs font-bold uppercase tracking-wider text-neutral-500">Order Items</h3>
              <div className="border border-neutral-200 dark:border-neutral-800 rounded-2xl overflow-hidden divide-y divide-neutral-100 dark:divide-neutral-800">
                {selectedOrder.items.map((item) => (
                  <div key={item.id} className="p-3.5 flex items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                      <img src={item.image} alt={item.title} className="w-10 h-10 rounded-xl object-cover" />
                      <div>
                        <div className="font-bold text-neutral-900 dark:text-white text-xs">{item.title}</div>
                        <div className="text-[11px] text-neutral-400 font-mono">
                          {item.variantTitle} • SKU: {item.sku}
                        </div>
                      </div>
                    </div>

                    <div className="text-right font-mono">
                      <div className="font-bold text-xs text-neutral-900 dark:text-white">
                        ${(item.price * item.quantity).toFixed(2)}
                      </div>
                      <div className="text-[10px] text-neutral-400">
                        ${item.price} × {item.quantity}
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>

            {/* Customer & Shipping Details */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {/* Shipping Address */}
              <div className="p-4 rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-800/40 space-y-2 text-xs">
                <div className="flex items-center justify-between gap-2">
                  <div className="flex items-center gap-1.5 font-bold text-neutral-900 dark:text-white">
                    <MapPin className="w-3.5 h-3.5 text-indigo-500" />
                    <span>Shipping Address</span>
                  </div>
                  <button
                    type="button"
                    onClick={() => setIsAmendDeliveryOpen(true)}
                    className="px-2 py-1 text-[10px] font-bold rounded-lg border border-indigo-300 dark:border-indigo-700 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 transition cursor-pointer flex items-center gap-1"
                  >
                    <Edit2 className="w-3 h-3" />
                    Amend Delivery Details
                  </button>
                </div>
                <div className="text-neutral-700 dark:text-neutral-300 leading-relaxed font-mono text-[11px]">
                  <div>{selectedOrder.shippingAddress.firstName} {selectedOrder.shippingAddress.lastName}</div>
                  <div>{selectedOrder.shippingAddress.address1}</div>
                  <div>{selectedOrder.shippingAddress.city}, {selectedOrder.shippingAddress.province} {selectedOrder.shippingAddress.postalCode}</div>
                  <div>{selectedOrder.shippingAddress.country}</div>
                  <div className="text-neutral-400 mt-1">Tel: {selectedOrder.shippingAddress.phone}</div>
                </div>
              </div>

              {/* Financial Calculation */}
              <div className="p-4 rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-800/40 space-y-2 text-xs">
                <div className="flex items-center gap-1.5 font-bold text-neutral-900 dark:text-white">
                  <CreditCard className="w-3.5 h-3.5 text-emerald-500" />
                  <span>Payment Summary</span>
                </div>
                <div className="space-y-1.5 text-[11px] font-mono text-neutral-600 dark:text-neutral-300 pb-3 border-b border-neutral-200 dark:border-neutral-700/80">
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

                <div className="p-3 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200/80 dark:border-neutral-800 space-y-1.5 text-[11px]">
                  <div className="flex items-center gap-1.5 font-bold text-neutral-900 dark:text-white"><Layers className="w-3.5 h-3.5 text-indigo-500" />Shipping Source</div>
                  <div className="flex justify-between gap-3"><span className="text-neutral-500">Checkout service:</span><span className="text-right font-medium text-neutral-700 dark:text-neutral-300">{selectedOrder.shippingSource?.checkoutService || 'Not recorded'}</span></div>
                  <div className="flex justify-between gap-3"><span className="text-neutral-500">Fulfillment module:</span><span className="text-right font-medium text-neutral-700 dark:text-neutral-300">{selectedOrder.shippingSource?.fulfillmentModule || 'Not initiated'}</span></div>
                  {(selectedOrder.shippingSource?.provider || selectedOrder.shippingSource?.service) && <div className="flex justify-between gap-3"><span className="text-neutral-500">Provider / service:</span><span className="text-right font-medium text-neutral-700 dark:text-neutral-300">{[selectedOrder.shippingSource?.provider, selectedOrder.shippingSource?.service].filter(Boolean).join(' • ')}</span></div>}
                </div>
                <div className="space-y-1 text-[11px] font-mono text-neutral-600 dark:text-neutral-300">
                  <div className="flex justify-between">
                    <span>Subtotal:</span>
                    <span>${selectedOrder.subtotal.toFixed(2)}</span>
                  </div>
                  <div className="flex justify-between">
                    <span>Tax:</span>
                    <span>${selectedOrder.taxAmount.toFixed(2)}</span>
                  </div>
                  <div className="flex justify-between">
                    <span>Shipping:</span>
                    <span>${selectedOrder.shippingAmount.toFixed(2)}</span>
                  </div>
                  <div className="flex justify-between font-bold text-xs text-neutral-900 dark:text-white pt-1 border-t border-neutral-200 dark:border-neutral-700">
                    <span>Grand Total:</span>
                    <span>${selectedOrder.totalAmount.toFixed(2)}</span>
                  </div>
                </div>
              </div>
            </div>

            <div className="flex justify-end pt-3 border-t border-neutral-100 dark:border-neutral-800">
              <button
                type="button"
                onClick={handleCloseDetail}
                className="px-5 py-2 text-xs font-bold rounded-xl bg-neutral-800 text-white hover:bg-neutral-700"
              >
                Close Order
              </button>
            </div>
          </div>
        </div>
      )}
      {selectedOrder && (
        <AmendDeliveryDetailsModal
          isOpen={isAmendDeliveryOpen}
          order={selectedOrder}
          hasActiveShipment={shipmentParcels.some(parcel => parcel.orderId === selectedOrder.id && parcel.status !== 'delivered' && parcel.status !== 'cancelled')}
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
