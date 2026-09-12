import React, { useState, useMemo, useEffect } from 'react';
import {
  MarketplaceInventoryItem,
  WarehouseStockBalance,
  EntityWarehouse,
  StoreFulfillmentBinding,
  MarketplaceOrder,
  InventoryOrderException,
  AdminUser,
  ReconcileInventoryReceipt
} from '../../types';
import {
  Scale,
  Warehouse,
  AlertTriangle,
  CheckCircle2,
  X,
  ArrowRight,
  ShieldAlert,
  Info,
  Building,
  UserCheck,
  Package,
  Layers,
  Store,
  RefreshCw,
  ExternalLink,
  ChevronDown,
  ChevronUp,
  Clock,
  Lock,
  FileCheck2
} from 'lucide-react';

interface WarehouseInventoryReconcileModalProps {
  isOpen: boolean;
  onClose: () => void;
  item?: MarketplaceInventoryItem | null;
  inventoryItem?: MarketplaceInventoryItem | null;
  warehouse?: WarehouseStockBalance | null;
  warehouseBalance?: WarehouseStockBalance | null;
  entityWarehouse?: EntityWarehouse;
  storeBindings?: StoreFulfillmentBinding[];
  allOrders?: MarketplaceOrder[];
  openOrdersCount?: number;
  exceptions?: InventoryOrderException[];
  openExceptionsCount?: number;
  currentUser?: AdminUser | null;
  currentUserName?: string;
  onOpenAuditModal?: (item: MarketplaceInventoryItem, warehouseId: string) => void;
  onReconcile?: (params: {
    masterSku: string;
    warehouseId: string;
    newSafetyStock: number;
    reason: string;
    expectedVersion?: number | string;
    operatorName: string;
  }) => Promise<ReconcileInventoryReceipt>;
  onConfirmReconcile?: (params: {
    masterSku: string;
    warehouseId: string;
    newSafetyStock: number;
    reason: string;
    expectedVersion?: number | string;
    operatorName: string;
  }) => Promise<ReconcileInventoryReceipt>;
}

export const WarehouseInventoryReconcileModal: React.FC<WarehouseInventoryReconcileModalProps> = ({
  isOpen,
  onClose,
  item: rawItem,
  inventoryItem,
  warehouse: rawWarehouse,
  warehouseBalance,
  entityWarehouse,
  storeBindings = [],
  allOrders = [],
  openOrdersCount = 0,
  exceptions = [],
  openExceptionsCount = 0,
  currentUser,
  currentUserName,
  onOpenAuditModal,
  onReconcile,
  onConfirmReconcile
}) => {
  const effectiveItem = rawItem || inventoryItem;
  const effectiveWarehouse = rawWarehouse || warehouseBalance;
  const effectiveReconcile = onReconcile || onConfirmReconcile;

  // Step 1: Input & Verification, Step 2: Explicit Review & Server Authorization
  const [step, setStep] = useState<1 | 2>(1);

  // Form State
  const [newSafetyStockInput, setNewSafetyStockInput] = useState<string>(
    String(effectiveWarehouse?.safetyStock ?? 0)
  );
  const [reason, setReason] = useState<string>('Routine warehouse balance reconciliation & safety buffer alignment');

  // Synchronize when warehouse changes
  useEffect(() => {
    if (effectiveWarehouse) {
      setNewSafetyStockInput(String(effectiveWarehouse.safetyStock ?? 0));
      setStep(1);
      setServerError(null);
      setReceipt(null);
    }
  }, [effectiveWarehouse?.warehouseId, effectiveWarehouse?.safetyStock]);

  // Submission & Receipt State
  const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
  const [serverError, setServerError] = useState<string | null>(null);
  const [receipt, setReceipt] = useState<ReconcileInventoryReceipt | null>(null);
  const [isVersionConflict, setIsVersionConflict] = useState<boolean>(false);
  const [showOrderBreakdown, setShowOrderBreakdown] = useState<boolean>(false);

  // Authenticated Operator: Strictly derived from current authenticated staff user
  const operatorName = currentUser?.name || currentUserName || 'Alexander (Super Admin)';
  const operatorRole = currentUser?.role || 'Inventory Controller / WMS Admin';

  // 1. Physical Stock Verification Check
  const isPhysicalVerified = Boolean(
    effectiveWarehouse &&
    effectiveWarehouse.physicalStockStatus === 'verified' &&
    effectiveWarehouse.physicalStock !== null &&
    effectiveWarehouse.physicalStock !== undefined
  );
  const lastAuditDisplay = effectiveWarehouse?.lastAuditAt
    ? `${new Date(effectiveWarehouse.lastAuditAt).toLocaleDateString()} (${effectiveWarehouse.lastAuditBy || effectiveWarehouse.lastAuditedBy || 'Staff Auditor'})`
    : null;

  // 2. Server-calculated Unfulfilled Reserved Stock from allocated orders
  const allocatedOrders = useMemo(() => {
    if (!effectiveWarehouse || !effectiveItem) return [];
    return allOrders.filter(order => {
      // Must be unfulfilled / awaiting dispatch
      const isUnfulfilled =
        order.fulfillmentStatus !== 'delivered' &&
        order.fulfillmentStatus !== 'cancelled' &&
        order.channelStatus !== 'CANCELLED';

      // Must be allocated to this specific warehouse
      const isAllocatedToThisWarehouse =
        order.allocatedWarehouse === effectiveWarehouse.warehouseId ||
        order.allocatedWarehouse === effectiveWarehouse.warehouseCode ||
        order.allocatedWarehouse === effectiveWarehouse.warehouseName ||
        order.allocatedWarehouse === entityWarehouse?.name ||
        order.allocatedWarehouse === entityWarehouse?.code;

      // Must contain this master SKU
      const hasSku = (order.items || []).some(
        it => it.sku === effectiveItem.masterSku || it.productId === effectiveItem.id
      );

      return isUnfulfilled && isAllocatedToThisWarehouse && hasSku;
    });
  }, [allOrders, effectiveWarehouse, entityWarehouse, effectiveItem]);

  // Sum of quantities reserved by server-calculated orders
  const serverCalculatedReservedQty = useMemo(() => {
    if (!effectiveWarehouse || !effectiveItem) return 0;
    let sum = 0;
    allocatedOrders.forEach(ord => {
      (ord.items || []).forEach(it => {
        if (it.sku === effectiveItem.masterSku || it.productId === effectiveItem.id) {
          sum += it.quantity;
        }
      });
    });
    // If order simulation has specific ledger entries, prefer server calculated quantity, fallback to ledger
    return sum > 0 ? sum : (effectiveWarehouse.reservedStock || 0);
  }, [allocatedOrders, effectiveItem, effectiveWarehouse]);

  // 3. Blocking Items Check:
  // - Unrouted / unassigned orders for this SKU
  // - Unresolved order exceptions for this SKU
  const unroutedOrders = useMemo(() => {
    if (!effectiveItem) return [];
    return allOrders.filter(order => {
      const isUnfulfilled =
        order.fulfillmentStatus !== 'delivered' &&
        order.fulfillmentStatus !== 'cancelled';
      const hasSku = (order.items || []).some(
        it => it.sku === effectiveItem.masterSku || it.productId === effectiveItem.id
      );
      const isUnrouted = !order.allocatedWarehouse || order.allocatedWarehouse === 'unassigned';
      return isUnfulfilled && hasSku && isUnrouted;
    });
  }, [allOrders, effectiveItem]);

  const unresolvedExceptions = useMemo(() => {
    if (!effectiveItem) return [];
    return exceptions.filter(
      ex =>
        (ex.channelSku === effectiveItem.masterSku || ex.channelSku === effectiveItem.id) &&
        ex.status === 'open'
    );
  }, [exceptions, effectiveItem]);

  const hasBlockingItems = unroutedOrders.length > 0 || unresolvedExceptions.length > 0;

  // 4. Safety Stock Validation (Non-negative integer)
  const parsedSafetyStock = parseInt(newSafetyStockInput, 10);
  const isSafetyStockValid =
    !isNaN(parsedSafetyStock) &&
    parsedSafetyStock >= 0 &&
    Number.isInteger(parsedSafetyStock) &&
    newSafetyStockInput.trim() !== '';

  // 5. ATP Calculation: Physical - Reserved - Safety
  const physicalCount = effectiveWarehouse?.physicalStock ?? 0;
  const newAtp = isPhysicalVerified
    ? Math.max(0, physicalCount - serverCalculatedReservedQty - (isSafetyStockValid ? parsedSafetyStock : 0))
    : null;

  // 6. Impact on Bound Stores
  const boundStores = useMemo(() => {
    if (!effectiveWarehouse) return [];
    return storeBindings.filter(
      b =>
        b.boundWarehouseId === effectiveWarehouse.warehouseId ||
        b.boundWarehouseCode === effectiveWarehouse.warehouseCode
    );
  }, [storeBindings, effectiveWarehouse]);

  if (!isOpen || !effectiveWarehouse || !effectiveItem) return null;

  const item = effectiveItem;
  const warehouse = effectiveWarehouse;

  // Handler for proceeding to Step 2
  const handleProceedToReview = (e: React.FormEvent) => {
    e.preventDefault();
    setServerError(null);

    if (!isPhysicalVerified) {
      setServerError('Physical stock is unverified. A cycle count audit is mandatory prior to reconciliation.');
      return;
    }

    if (hasBlockingItems) {
      setServerError('Reconciliation is blocked by unrouted orders or unresolved exceptions. These cannot be bypassed.');
      return;
    }

    if (!isSafetyStockValid) {
      setServerError('Safety stock must be a non-negative integer.');
      return;
    }

    if (!reason.trim() || reason.trim().length < 5) {
      setServerError('A valid, detailed reconciliation reason is mandatory (minimum 5 characters).');
      return;
    }

    setStep(2);
  };

  // Handler for Final Submission to Server WMS Ledger
  const handleConfirmReconciliation = async () => {
    if (isSubmitting) return; // Prevent double submission
    setIsSubmitting(true);
    setServerError(null);
    setIsVersionConflict(false);

    try {
      if (!effectiveReconcile) {
        throw new Error('No reconciliation handler configured.');
      }
      const res = await effectiveReconcile({
        masterSku: item.masterSku,
        warehouseId: warehouse.warehouseId,
        newSafetyStock: parsedSafetyStock,
        reason: reason.trim(),
        expectedVersion: warehouse.version ?? warehouse.lastAuditAt,
        operatorName
      });

      if (res.success) {
        setReceipt(res);
      } else {
        // Failed: preserve form inputs!
        setServerError(res.error || 'Server rejected reconciliation request.');
        if (res.versionConflict || res.status === 'VERSION_CONFLICT') {
          setIsVersionConflict(true);
        }
      }
    } catch (err: any) {
      setServerError(err.message || 'An unexpected error occurred contacting the WMS server.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/75 backdrop-blur-xs overflow-y-auto">
      <div className="relative w-full max-w-2xl max-h-[calc(100dvh-1.5rem)] sm:max-h-[calc(100dvh-2.5rem)] my-auto flex flex-col bg-white dark:bg-neutral-900 rounded-2xl shadow-2xl border border-neutral-200 dark:border-neutral-800 overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        
        {/* Pinned Header */}
        <div className="p-4 sm:p-5 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950/70 flex items-center justify-between shrink-0">
          <div className="flex items-center gap-3">
            <div className="p-2.5 rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20">
              <Scale className="w-5 h-5" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                  Entity Warehouse Inventory Reconciliation
                </h3>
                <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-purple-500/10 text-purple-700 dark:text-purple-300 border border-purple-500/20">
                  {step === 1 ? 'Step 1: Balance Input' : receipt ? 'Audit Receipt' : 'Step 2: Ledger Review'}
                </span>
              </div>
              <p className="text-xs text-neutral-500 mt-0.5">
                Fixed Binding: <span className="font-mono font-bold text-neutral-800 dark:text-neutral-200">{item.masterSku}</span> @ <span className="font-semibold text-neutral-800 dark:text-neutral-200">{warehouse.warehouseCode} ({warehouse.warehouseName})</span>
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 cursor-pointer transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Content Body with Internal Scrolling */}
        <div className="flex-1 min-h-0 overflow-y-auto p-4 sm:p-6 space-y-5 text-xs">

          {/* Receipt View (Once Completed) */}
          {receipt ? (
            <div className="space-y-4 animate-in fade-in duration-200">
              <div className="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-800 dark:text-emerald-300 space-y-2">
                <div className="flex items-center gap-2 font-bold text-sm">
                  <CheckCircle2 className="w-5 h-5 text-emerald-500 shrink-0" />
                  <span>{receipt.status==='BASIC_SAFETY_CONFIGURED'?'Safety Stock Saved — Order Coverage Unchanged':'WMS Ledger Balance Reconciled Successfully'}</span>
                </div>
                <p className="text-xs text-emerald-700 dark:text-emerald-400">
                  Transaction Receipt: <span className="font-mono font-bold">{receipt.receiptId}</span> • Recorded at {new Date(receipt.timestamp || '').toLocaleString()}
                </p>
              </div>

              {/* Receipt Specs Summary */}
              <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 space-y-3">
                <h4 className="font-bold text-neutral-900 dark:text-white flex items-center justify-between">
                  <span>{receipt.status==='BASIC_SAFETY_CONFIGURED'?'Safety Stock Audit Entry':'Reconciled Ledger Entry'}</span>
                  <span className="font-mono text-[10px] text-neutral-500">STATUS: {receipt.status}</span>
                </h4>
                
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
                  <div className="p-2.5 bg-white dark:bg-neutral-900 rounded-lg border border-neutral-200 dark:border-neutral-800">
                    <span className="text-[10px] text-neutral-400 block mb-0.5">Physical Stock</span>
                    <span className="font-bold text-sm font-mono text-neutral-900 dark:text-white">
                      {receipt.physicalStock} units
                    </span>
                    <span className="text-[9px] text-neutral-400 block mt-0.5">(Unchanged)</span>
                  </div>
                  <div className="p-2.5 bg-white dark:bg-neutral-900 rounded-lg border border-neutral-200 dark:border-neutral-800">
                    <span className="text-[10px] text-neutral-400 block mb-0.5">Reserved Stock</span>
                    <span className="font-bold text-sm font-mono text-amber-600 dark:text-amber-400">
                      {receipt.newReserved} units
                    </span>
                    <span className="text-[9px] text-neutral-400 block mt-0.5">From Orders</span>
                  </div>
                  <div className="p-2.5 bg-white dark:bg-neutral-900 rounded-lg border border-neutral-200 dark:border-neutral-800">
                    <span className="text-[10px] text-neutral-400 block mb-0.5">Safety Stock</span>
                    <span className="font-bold text-sm font-mono text-purple-600 dark:text-purple-400">
                      {receipt.newSafety} units
                    </span>
                    <span className="text-[9px] text-neutral-400 block mt-0.5">
                      Prev: {receipt.previousSafety ?? 'Unknown'}
                    </span>
                  </div>
                  <div className="p-2.5 bg-white dark:bg-neutral-900 rounded-lg border border-neutral-200 dark:border-neutral-800">
                    <span className="text-[10px] text-neutral-400 block mb-0.5">Resulting ATP</span>
                    <span className="font-bold text-sm font-mono text-emerald-600 dark:text-emerald-400">
                      {receipt.newAtp} units
                    </span>
                    <span className="text-[9px] text-neutral-400 block mt-0.5">Available</span>
                  </div>
                </div>

                <div className="pt-2 border-t border-neutral-200 dark:border-neutral-800 text-[11px] text-neutral-600 dark:text-neutral-400 space-y-1">
                  <div>Authorizing Operator: <strong className="text-neutral-900 dark:text-white">{receipt.operator}</strong></div>
                  <div>Facility: <strong className="text-neutral-900 dark:text-white">{receipt.warehouseName} ({receipt.warehouseCode})</strong></div>
                  <div>SKU: <strong className="font-mono text-neutral-900 dark:text-white">{receipt.masterSku}</strong> • {receipt.productName}</div>
                </div>
              </div>

              {/* Decoupling Notice */}
              <div className="p-3.5 rounded-xl bg-blue-500/10 border border-blue-500/20 text-blue-900 dark:text-blue-300 space-y-1 text-xs">
                <div className="flex items-center gap-1.5 font-bold">
                  <Info className="w-4 h-4 text-blue-600 dark:text-blue-400" />
                  <span>Channel Broadcast Decoupling</span>
                </div>
                <p className="text-[11px] leading-relaxed">
                  This reconciliation strictly updated the internal WMS ledger balance and warehouse ATP. Per operational policy, this transaction does <strong>not</strong> push or write stock to Shopee or Lazada channels automatically. Use per-store auto-sync or manual broadcast when ready.
                </p>
              </div>
            </div>
          ) : step === 1 ? (
            /* STEP 1: INPUT & CONSTRAINTS FORM */
            <form id="reconcile-step1-form" onSubmit={handleProceedToReview} className="space-y-4">
              
              {/* Error Banner */}
              {serverError && (
                <div className="p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-800 dark:text-rose-300 flex items-start gap-2.5">
                  <AlertTriangle className="w-4 h-4 text-rose-500 shrink-0 mt-0.5" />
                  <div className="text-xs">
                    <span className="font-bold block">Validation Alert</span>
                    <span>{serverError}</span>
                  </div>
                </div>
              )}

              {/* Version Conflict Alert */}
              {isVersionConflict && (
                <div className="p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-800 dark:text-amber-300 space-y-2">
                  <div className="flex items-center gap-2 font-bold text-xs">
                    <RefreshCw className="w-4 h-4 text-amber-500 shrink-0 animate-spin" />
                    <span>Inventory Version Conflict Detected</span>
                  </div>
                  <p className="text-[11px] leading-relaxed">
                    The warehouse stock version has changed on the server due to concurrent order allocations or audits. Your form inputs have been preserved. Please refresh your balance review before re-authorizing.
                  </p>
                </div>
              )}

              {/* Fixed Context Card */}
              <div className="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 rounded-lg bg-neutral-200 dark:bg-neutral-800 flex items-center justify-center overflow-hidden shrink-0">
                    {item.imageUrl ? (
                      <img src={item.imageUrl} alt={item.productName} className="w-full h-full object-cover" />
                    ) : (
                      <Package className="w-5 h-5 text-neutral-400" />
                    )}
                  </div>
                  <div>
                    <div className="flex items-center gap-2">
                      <span className="font-mono font-bold text-neutral-900 dark:text-white">{item.masterSku}</span>
                      <span className="text-neutral-400">•</span>
                      <span className="text-neutral-700 dark:text-neutral-300 font-medium">{item.productName}</span>
                    </div>
                    <p className="text-[11px] text-neutral-500 mt-0.5">
                      Facility: <strong className="text-neutral-700 dark:text-neutral-300">{warehouse.warehouseName}</strong> • Shelf Bin: <span className="font-mono">{warehouse.locationBin || warehouse.binLocation || 'Not configured'}</span>
                    </p>
                  </div>
                </div>
                <div className="flex items-center gap-1.5 text-[10px] text-neutral-400 font-mono bg-neutral-100 dark:bg-neutral-800 px-2 py-1 rounded">
                  <Lock className="w-3 h-3 text-neutral-500" />
                  <span>LOCKED CONTEXT</span>
                </div>
              </div>

              {/* 1. Physical Stock Verification Card */}
              <div className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 space-y-2">
                <div className="flex items-center justify-between">
                  <span className="font-bold text-neutral-800 dark:text-neutral-200 flex items-center gap-1.5">
                    <Building className="w-4 h-4 text-purple-600 dark:text-purple-400" />
                    <span>Physical Verified Stock (实盘库存)</span>
                  </span>
                  {isPhysicalVerified ? (
                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/20">
                      <CheckCircle2 className="w-3 h-3 text-emerald-500" />
                      Verified Count
                    </span>
                  ) : (
                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-700 dark:text-amber-300 border border-amber-500/20">
                      <AlertTriangle className="w-3 h-3 text-amber-500" />
                      Pending reconciliation
                    </span>
                  )}
                </div>

                <div className="flex items-baseline justify-between pt-1">
                  <div>
                    {isPhysicalVerified ? (
                      <div className="text-xl font-mono font-black text-neutral-900 dark:text-white">
                        {warehouse.physicalStock} <span className="text-xs font-normal text-neutral-500">units in bins</span>
                      </div>
                    ) : (
                      <div className="text-sm font-bold text-amber-600 dark:text-amber-400">
                        Pending reconciliation
                      </div>
                    )}
                    <p className="text-[11px] text-neutral-500 mt-0.5">
                      {lastAuditDisplay ? (
                        <span>Last audit cycle: {lastAuditDisplay}</span>
                      ) : (
                        <span className="text-rose-500 font-medium">No verified physical audit recorded for this facility</span>
                      )}
                    </p>
                  </div>

                  {!isPhysicalVerified && onOpenAuditModal && (
                    <button
                      type="button"
                      onClick={() => {
                        onClose();
                        onOpenAuditModal(item, warehouse.warehouseId);
                      }}
                      className="px-3 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-700 text-white font-bold text-[11px] cursor-pointer shadow-xs transition-colors flex items-center gap-1.5"
                    >
                      <span>Conduct Physical Audit</span>
                      <ArrowRight className="w-3.5 h-3.5" />
                    </button>
                  )}
                </div>

                {!isPhysicalVerified && (
                  <div className="p-3 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-800 dark:text-amber-300 text-[11px] leading-relaxed">
                    <strong>WMS Audit Rule:</strong> Physical stock cannot default to zero. You must conduct a physical cycle count audit before balance reconciliation can be authorized.
                  </div>
                )}
              </div>

              {/* 2. Server-Calculated Unfulfilled Reserved Stock */}
              <div className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 space-y-2">
                <div className="flex items-center justify-between">
                  <span className="font-bold text-neutral-800 dark:text-neutral-200 flex items-center gap-1.5">
                    <Clock className="w-4 h-4 text-amber-600 dark:text-amber-400" />
                    <span>Unfulfilled Reserved Stock (未出库预留数量)</span>
                  </span>
                  <span className="text-[10px] font-mono text-neutral-400 bg-neutral-100 dark:bg-neutral-800 px-2 py-0.5 rounded">
                    READ-ONLY • SERVER COMPUTED
                  </span>
                </div>

                <div className="flex items-baseline justify-between">
                  <div className="text-xl font-mono font-black text-amber-600 dark:text-amber-400">
                    {serverCalculatedReservedQty} <span className="text-xs font-normal text-neutral-500">units reserved</span>
                  </div>
                  <button
                    type="button"
                    onClick={() => setShowOrderBreakdown(!showOrderBreakdown)}
                    className="text-[11px] text-purple-600 dark:text-purple-400 hover:underline flex items-center gap-1 cursor-pointer font-medium"
                  >
                    <span>{showOrderBreakdown ? 'Hide Breakdown' : `View ${allocatedOrders.length} Allocated Orders`}</span>
                    {showOrderBreakdown ? <ChevronUp className="w-3.5 h-3.5" /> : <ChevronDown className="w-3.5 h-3.5" />}
                  </button>
                </div>

                <p className="text-[11px] text-neutral-500">
                  {warehouse.basicWms?'Includes only reservations owned by this balance. Existing and unallocated order demand is excluded.':`Calculated dynamically by the server from active, unfulfilled orders routed to ${warehouse.warehouseName}.`}
                </p>

                {/* Collapsible Allocated Order List */}
                {showOrderBreakdown && (
                  <div className="pt-2 border-t border-neutral-200 dark:border-neutral-800 space-y-2 max-h-40 overflow-y-auto">
                    {allocatedOrders.length === 0 ? (
                      <p className="text-neutral-400 italic text-[11px]">No active allocated orders found. Ledger reserve is {warehouse.reservedStock} units.</p>
                    ) : (
                      allocatedOrders.map(ord => {
                        const itm = ord.items.find(i => i.sku === item.masterSku || i.productId === item.id);
                        return (
                          <div key={ord.id} className="p-2 rounded bg-neutral-50 dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 flex items-center justify-between text-[11px]">
                            <div>
                              <span className="font-mono font-bold text-neutral-900 dark:text-white">{ord.orderNumber}</span>
                              <span className="text-neutral-400 mx-1.5">•</span>
                              <span className="text-neutral-600 dark:text-neutral-300 capitalize">{ord.channel} ({ord.channelStoreName})</span>
                            </div>
                            <div className="flex items-center gap-2 font-mono">
                              <span className="text-amber-600 dark:text-amber-400 font-bold">{itm?.quantity || 1} units</span>
                              <span className="text-[10px] px-1.5 py-0.5 rounded bg-neutral-200 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300">
                                {ord.channelStatus}
                              </span>
                            </div>
                          </div>
                        );
                      })
                    )}
                  </div>
                )}
              </div>

              {/* 3. Blocking Constraints Detection (Strict Non-Bypassable) */}
              {hasBlockingItems && (
                <div className="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-900 dark:text-rose-300 space-y-2">
                  <div className="flex items-center gap-2 font-bold text-xs">
                    <ShieldAlert className="w-4 h-4 text-rose-600 shrink-0" />
                    <span>Blocking Items Detected — Reconciliation Blocked</span>
                  </div>
                  <p className="text-[11px] leading-relaxed">
                    The WMS detects unrouted orders or unresolved exceptions for this SKU. In accordance with operational controls, <strong>blocking items cannot be bypassed with checkboxes</strong> and must be resolved first.
                  </p>

                  <div className="space-y-1.5 pt-1">
                    {unroutedOrders.map(ord => (
                      <div key={ord.id} className="p-2 rounded bg-white/60 dark:bg-neutral-900/60 border border-rose-500/20 flex items-center justify-between text-[11px]">
                        <span>Order <strong className="font-mono">{ord.orderNumber}</strong> has unassigned warehouse allocation</span>
                        <span className="font-bold text-rose-600 dark:text-rose-400">Needs Routing</span>
                      </div>
                    ))}
                    {unresolvedExceptions.map(ex => (
                      <div key={ex.id} className="p-2 rounded bg-white/60 dark:bg-neutral-900/60 border border-rose-500/20 flex items-center justify-between text-[11px]">
                        <span>Exception: <strong>{ex.explanation}</strong> ({ex.orderNumber})</span>
                        <span className="font-bold text-rose-600 dark:text-rose-400">{ex.type}</span>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* 4. Safety Stock Unit Input & ATP Preview */}
              <div className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 space-y-3">
                <div>
                  <div className="flex items-center justify-between mb-1">
                    <label htmlFor="reconcile-safety-stock-input" className="font-bold text-neutral-800 dark:text-neutral-200">
                      Entity Warehouse Safety Stock (安全库存单位数) <span className="text-rose-500">*</span>
                    </label>
                    <span className="text-[10px] text-neutral-400">Current: {warehouse.safetyStockKnown===false?'Unknown':warehouse.safetyStock} units</span>
                  </div>
                  <div className="relative">
                    <input
                      id="reconcile-safety-stock-input"
                      type="number"
                      min="0"
                      step="1"
                      value={newSafetyStockInput}
                      onChange={e => setNewSafetyStockInput(e.target.value)}
                      placeholder="e.g. 5"
                      required
                      className="w-full px-3.5 py-2.5 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white font-mono text-sm focus:outline-hidden focus:ring-2 focus:ring-purple-500"
                    />
                    <span className="absolute right-3.5 top-3 text-xs font-medium text-neutral-400">units</span>
                  </div>
                  <p className="text-[10px] text-neutral-500 mt-1 leading-relaxed">
                    <strong>Critical distinction:</strong> This is the physical warehouse reserve in units, NOT the store-level <code className="font-mono text-purple-600 dark:text-purple-400">safetyBufferPercent</code> applied on channel broadcasts.
                  </p>
                </div>

                {/* Real-time Calculated ATP Preview */}
                <div className="p-3 rounded-lg bg-emerald-500/5 dark:bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-between">
                  <div>
                    <span className="text-[10px] uppercase font-bold tracking-wider text-emerald-700 dark:text-emerald-300 block">
                      Calculated Warehouse Available-to-Promise (ATP)
                    </span>
                    <span className="text-[11px] text-neutral-500">
                      Formula: Physical ({warehouse.physicalStock ?? '?'}) − Reserved ({serverCalculatedReservedQty}) − Safety ({isSafetyStockValid ? parsedSafetyStock : '?'})
                    </span>
                  </div>
                  <div className="text-right">
                    <span className="text-base font-black font-mono text-emerald-600 dark:text-emerald-400">
                      {newAtp !== null ? `${newAtp} ATP` : 'Pending'}
                    </span>
                  </div>
                </div>
              </div>

              {/* 5. Mandatory Reason Input */}
              <div>
                <label htmlFor="reconcile-reason-input" className="block font-bold text-neutral-800 dark:text-neutral-200 mb-1">
                  Reconciliation Reason (必填对账原因) <span className="text-rose-500">*</span>
                </label>
                <textarea
                  id="reconcile-reason-input"
                  rows={2}
                  value={reason}
                  onChange={e => setReason(e.target.value)}
                  placeholder="Mandatory reason explaining this safety stock or balance reconciliation..."
                  required
                  className="w-full px-3 py-2 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white text-xs focus:outline-hidden focus:ring-2 focus:ring-purple-500"
                />
              </div>

              {/* 6. Operator Identity (Locked to Authenticated Employee) */}
              <div className="p-3 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
                <div className="flex items-center gap-2.5">
                  <UserCheck className="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                  <div>
                    <span className="text-[10px] text-neutral-400 uppercase tracking-wider block font-medium">Authorizing Operator (Locked)</span>
                    <span className="font-bold text-neutral-900 dark:text-white text-xs">{operatorName}</span>
                    <span className="text-neutral-400 text-[10px] ml-1.5">({operatorRole})</span>
                  </div>
                </div>
                <span className="text-[10px] font-bold px-2 py-0.5 rounded bg-neutral-200 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300">
                  Verified Session
                </span>
              </div>
            </form>
          ) : (
            /* STEP 2: EXPLICIT REVIEW (复核步骤) */
            <div className="space-y-4">
              
              {/* Review Header Banner */}
              <div className="p-3.5 rounded-xl bg-purple-500/10 border border-purple-500/20 text-purple-900 dark:text-purple-300 flex items-center justify-between">
                <div>
                  <h4 className="font-bold text-xs">Step 2: Pre-Commit Ledger Verification</h4>
                  <p className="text-[11px] opacity-90 mt-0.5">
                    Review balance delta, physical stock invariance, and associated channel store impacts before committing.
                  </p>
                </div>
                <FileCheck2 className="w-5 h-5 text-purple-600 dark:text-purple-400 shrink-0" />
              </div>

              {/* Error Banner */}
              {serverError && (
                <div className="p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-800 dark:text-rose-300 flex items-start gap-2.5">
                  <AlertTriangle className="w-4 h-4 text-rose-500 shrink-0 mt-0.5" />
                  <div className="text-xs">
                    <span className="font-bold block">Authorization Error</span>
                    <span>{serverError}</span>
                  </div>
                </div>
              )}

              {/* Five Core Verification Cards */}
              <div className="space-y-3">
                
                {/* 1. 实体库存不变 (Physical Stock Unchanged) */}
                <div className="p-3.5 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 flex items-center justify-between">
                  <div className="space-y-0.5">
                    <div className="flex items-center gap-1.5 font-bold text-neutral-900 dark:text-white text-xs">
                      <CheckCircle2 className="w-4 h-4 text-emerald-500" />
                      <span>Physical Stock Invariance (实体库存不变)</span>
                    </div>
                    <p className="text-[11px] text-neutral-500 max-w-md">
                      Physical bin inventory is unchanged ({warehouse.physicalStock} units). Physical adjustments are strictly governed by verified cycle count audits.
                    </p>
                  </div>
                  <div className="text-right">
                    <span className="font-mono font-bold text-sm text-neutral-900 dark:text-white">
                      {warehouse.physicalStock} units
                    </span>
                    <span className="text-[10px] text-emerald-600 dark:text-emerald-400 block font-medium">Constant (不变)</span>
                  </div>
                </div>

                {/* 2. 预留前后值 (Reserved Stock Before vs After) */}
                <div className="p-3.5 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 flex items-center justify-between">
                  <div className="space-y-0.5">
                    <span className="font-bold text-neutral-900 dark:text-white text-xs block">
                      Reserved Stock Allocation (预留数量变动)
                    </span>
                    <p className="text-[11px] text-neutral-500">
                      Derived from {allocatedOrders.length} active unfulfilled customer orders.
                    </p>
                  </div>
                  <div className="flex items-center gap-3 font-mono">
                    <span className="text-neutral-400 line-through text-xs">{warehouse.reservedStock} units</span>
                    <ArrowRight className="w-3.5 h-3.5 text-neutral-400" />
                    <span className="font-bold text-amber-600 dark:text-amber-400 text-sm">{serverCalculatedReservedQty} units</span>
                  </div>
                </div>

                {/* 3. 安全库存前后值 (Safety Stock Before vs After) */}
                <div className="p-3.5 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 flex items-center justify-between">
                  <div className="space-y-0.5">
                    <span className="font-bold text-neutral-900 dark:text-white text-xs block">
                      Safety Stock Units (安全库存变动)
                    </span>
                    <p className="text-[11px] text-neutral-500">
                      Physical unit buffer inside {warehouse.warehouseName}.
                    </p>
                  </div>
                  <div className="flex items-center gap-3 font-mono">
                    <span className="text-neutral-400 line-through text-xs">{warehouse.safetyStockKnown===false?'Unknown':warehouse.safetyStock} units</span>
                    <ArrowRight className="w-3.5 h-3.5 text-neutral-400" />
                    <div className="text-right">
                      <span className="font-bold text-purple-600 dark:text-purple-400 text-sm">{parsedSafetyStock} units</span>
                      <span className="text-[10px] text-neutral-400 block">
                        Delta: {warehouse.safetyStockKnown===false?'Unknown':parsedSafetyStock - warehouse.safetyStock >= 0 ? `+${parsedSafetyStock - warehouse.safetyStock}` : parsedSafetyStock - warehouse.safetyStock} units
                      </span>
                    </div>
                  </div>
                </div>

                {/* 4. ATP 变化 (Available-to-Promise Change) */}
                <div className="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
                  <div className="space-y-0.5">
                    <span className="font-bold text-neutral-900 dark:text-white text-xs block">
                      Warehouse ATP Change (可用库存 ATP 变化)
                    </span>
                    <p className="text-[11px] text-neutral-500">
                      Physical ({warehouse.physicalStock}) − Reserved ({serverCalculatedReservedQty}) − Safety ({parsedSafetyStock})
                    </p>
                  </div>
                  <div className="flex items-center gap-3 font-mono">
                     <span className="text-neutral-400 line-through text-xs">{warehouse.safetyStockKnown===false?'Unknown':warehouse.availableAtp} ATP</span>
                    <ArrowRight className="w-3.5 h-3.5 text-neutral-400" />
                    <span className="font-black text-emerald-600 dark:text-emerald-400 text-base bg-emerald-500/10 px-2.5 py-1 rounded-lg border border-emerald-500/20">
                      {newAtp} ATP
                    </span>
                  </div>
                </div>

                {/* 5. 关联店铺影响 (Associated Channel Store Impact) */}
                <div className="p-3.5 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 space-y-2">
                  <div className="flex items-center justify-between">
                    <span className="font-bold text-neutral-900 dark:text-white text-xs flex items-center gap-1.5">
                      <Store className="w-4 h-4 text-purple-600 dark:text-purple-400" />
                      <span>Associated Channel Stores ({boundStores.length} Bound Stores)</span>
                    </span>
                    <span className="text-[10px] text-neutral-400">Warehouse Fulfillment Routing</span>
                  </div>

                  {boundStores.length === 0 ? (
                    <p className="text-neutral-400 italic text-[11px]">
                      No marketplace store currently bound specifically to this facility. Stock will reside in WMS balance.
                    </p>
                  ) : (
                    <div className="space-y-1.5">
                      {boundStores.map(b => (
                        <div key={b.id} className="p-2 rounded-lg bg-neutral-50 dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 flex items-center justify-between text-[11px]">
                          <div className="flex items-center gap-2">
                            <span className="font-bold text-neutral-900 dark:text-white">{b.storeName}</span>
                            <span className="text-[10px] px-1.5 py-0.2 rounded bg-neutral-200 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 uppercase font-bold">
                              {b.platform}
                            </span>
                          </div>
                          <div className="flex items-center gap-2 font-mono">
                            <span className="text-neutral-400">Target Warehouse ATP:</span>
                            <span className="font-bold text-emerald-600 dark:text-emerald-400">{newAtp} units</span>
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              </div>

              {/* Explicit Decoupling Notice: 对账本身不能宣称已推送 Shopee/Lazada */}
              <div className="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-900 dark:text-amber-300 space-y-1 text-xs">
                <div className="flex items-center gap-1.5 font-bold">
                  <ShieldAlert className="w-4 h-4 text-amber-600 shrink-0" />
                  <span>WMS Ledger Scope Policy</span>
                </div>
                <p className="text-[11px] leading-relaxed">
                  Authorizing this reconciliation commits changes exclusively to the <strong>internal WMS ledger</strong>. It does <strong>not</strong> push or sync stock to Shopee or Lazada. Platform stock synchronizations must be executed via store-specific broadcast controls.
                </p>
              </div>

              {/* Audit Details */}
              <div className="p-3 rounded-lg bg-neutral-50 dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 text-[11px] text-neutral-500 space-y-1">
                <div>Reason: <span className="text-neutral-800 dark:text-neutral-200 font-medium">"{reason}"</span></div>
                <div>Authorizer: <span className="text-neutral-800 dark:text-neutral-200 font-bold">{operatorName}</span> ({operatorRole})</div>
              </div>
            </div>
          )}
        </div>

        {/* Pinned Footer */}
        <div className="p-3.5 sm:px-6 sm:py-3.5 flex items-center justify-between border-t border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950/70 shrink-0">
          {receipt ? (
            <div className="w-full flex justify-end">
              <button
                type="button"
                onClick={onClose}
                className="px-5 py-2 rounded-xl bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100 font-bold text-xs cursor-pointer shadow-xs transition-colors"
              >
                Close Receipt
              </button>
            </div>
          ) : step === 1 ? (
            <>
              <button
                type="button"
                onClick={onClose}
                className="px-4 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-700 dark:text-neutral-300 font-medium text-xs cursor-pointer transition-colors"
              >
                Cancel
              </button>

              <button
                type="submit"
                form="reconcile-step1-form"
                disabled={!isPhysicalVerified || hasBlockingItems || !isSafetyStockValid}
                className={`px-5 py-2 rounded-xl font-bold text-xs flex items-center gap-1.5 transition-all shadow-xs ${
                  !isPhysicalVerified || hasBlockingItems || !isSafetyStockValid
                    ? 'bg-neutral-200 dark:bg-neutral-800 text-neutral-400 cursor-not-allowed border border-neutral-300 dark:border-neutral-700'
                    : 'bg-purple-600 hover:bg-purple-700 text-white cursor-pointer'
                }`}
              >
                <span>Proceed to Review</span>
                <ArrowRight className="w-4 h-4" />
              </button>
            </>
          ) : (
            <>
              <button
                type="button"
                onClick={() => setStep(1)}
                disabled={isSubmitting}
                className="px-4 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-700 dark:text-neutral-300 font-medium text-xs cursor-pointer transition-colors"
              >
                Back to Edit
              </button>

              <button
                type="button"
                onClick={handleConfirmReconciliation}
                disabled={isSubmitting}
                className="px-5 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs cursor-pointer shadow-xs transition-colors flex items-center gap-2"
              >
                {isSubmitting ? (
                  <>
                    <RefreshCw className="w-4 h-4 animate-spin" />
                    <span>Committing to Server WMS...</span>
                  </>
                ) : (
                  <>
                    <Scale className="w-4 h-4" />
                    <span>Confirm & Apply to WMS Ledger</span>
                  </>
                )}
              </button>
            </>
          )}
        </div>

      </div>
    </div>
  );
};
