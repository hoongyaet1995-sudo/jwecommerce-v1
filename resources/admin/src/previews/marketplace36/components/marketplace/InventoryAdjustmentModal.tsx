import React, { useState, useEffect } from 'react';
import { MarketplaceInventoryItem, StaffUser, EntityWarehouse } from '../../types';
import {
  Warehouse,
  Minus,
  Plus,
  AlertTriangle,
  CheckCircle2,
  ShieldCheck,
  ArrowRight,
  UserCheck,
  FileText,
  X,
  ClipboardCheck,
  MapPin
} from 'lucide-react';

interface InventoryAdjustmentModalProps {
  item: MarketplaceInventoryItem | null;
  isOpen: boolean;
  onClose: () => void;
  staffUsers: StaffUser[];
  currentUserName?: string;
  targetWarehouseId?: string;
  warehouses?: EntityWarehouse[];
  onApplyAdjustment: (idOrSku: string, delta: number, reason: string, staffName: string, warehouseId?: string) => void;
  onRecordAuditCount: (idOrSku: string, physicalCount: number, reason: string, staffName: string, warehouseId?: string) => void;
}

export const InventoryAdjustmentModal: React.FC<InventoryAdjustmentModalProps> = ({
  item,
  isOpen,
  onClose,
  staffUsers = [],
  currentUserName,
  targetWarehouseId,
  warehouses = [],
  onApplyAdjustment,
  onRecordAuditCount
}) => {
  const isUnknownPhysical = Boolean(item && (item.physicalStockStatus === 'unknown' || item.totalPhysicalStock === null));

  // Tabs: 'delta' (adjustment) or 'audit' (physical count cycle)
  const [mode, setMode] = useState<'delta' | 'audit'>(isUnknownPhysical ? 'audit' : 'delta');
  const [step, setStep] = useState<'input' | 'review'>('input');

  // Multi-warehouse selection
  const hasWarehouseBalances = Boolean(item?.warehouseBalances && item.warehouseBalances.length > 0);
  const [selectedWarehouseId, setSelectedWarehouseId] = useState<string>(() => {
    if (targetWarehouseId) return targetWarehouseId;
    if (hasWarehouseBalances && item?.warehouseBalances && item.warehouseBalances[0]) {
      return item.warehouseBalances[0].warehouseId;
    }
    return '';
  });

  const currentWarehouseBalance = hasWarehouseBalances && selectedWarehouseId
    ? item?.warehouseBalances?.find(b => b.warehouseId === selectedWarehouseId)
    : null;

  const currentPhysical = currentWarehouseBalance
    ? currentWarehouseBalance.physicalStock
    : (item?.totalPhysicalStock ?? 0);

  const currentReserved = currentWarehouseBalance
    ? currentWarehouseBalance.reserved
    : (item?.reservedStock ?? 0);

  const currentSafety = currentWarehouseBalance
    ? currentWarehouseBalance.safetyStock
    : (item?.safetyStock ?? 0);

  const currentBin = currentWarehouseBalance
    ? currentWarehouseBalance.locationBin
    : (item?.warehouseLocation ?? '');

  // Input states
  const [delta, setDelta] = useState<number>(10);
  const [auditCount, setAuditCount] = useState<number>(currentPhysical || 50);
  const [selectedStaffId, setSelectedStaffId] = useState<string>('');
  const [reason, setReason] = useState<string>(
    isUnknownPhysical
      ? 'Initial Physical Warehouse Audit & Ledger Verification'
      : 'Supplier Stock Inbound (PO Receipt)'
  );
  const [customNotes, setCustomNotes] = useState<string>('');
  const [confirmedAcknowledged, setConfirmedAcknowledged] = useState<boolean>(false);

  // Initialize staff
  useEffect(() => {
    if (staffUsers.length > 0) {
      const matchingStaff = staffUsers.find(
        s => s.fullName.toLowerCase().includes(currentUserName?.toLowerCase() || '') ||
             s.username.toLowerCase().includes(currentUserName?.toLowerCase() || '')
      );
      setSelectedStaffId(matchingStaff ? matchingStaff.id : staffUsers[0].id);
    }
  }, [staffUsers, currentUserName]);

  // Reset step on open or warehouse switch
  useEffect(() => {
    if (!item) return;
    setStep('input');
    setConfirmedAcknowledged(false);
    if (targetWarehouseId) {
      setSelectedWarehouseId(targetWarehouseId);
    } else if (item.warehouseBalances && item.warehouseBalances[0]) {
      setSelectedWarehouseId(item.warehouseBalances[0].warehouseId);
    }

    if (item.physicalStockStatus === 'unknown' || item.totalPhysicalStock === null) {
      setMode('audit');
      setAuditCount(50);
      setReason('Initial Physical Warehouse Audit & Ledger Verification');
    } else {
      setMode('delta');
      setDelta(10);
      setAuditCount(currentPhysical);
      setReason('Supplier Stock Inbound (PO Receipt)');
    }
  }, [item, targetWarehouseId]);

  const selectedStaff = staffUsers.find(s => s.id === selectedStaffId) || staffUsers[0];
  const staffDisplayName = selectedStaff ? `${selectedStaff.fullName} (${selectedStaff.position || 'Staff'})` : (currentUserName || 'Authorized Staff');

  // Defensive validation: NO SILENT Math.max(0, ...)
  const resultingPhysical = mode === 'delta'
    ? currentPhysical + delta
    : auditCount;

  const isNegativeStockError = resultingPhysical < 0;
  const resultingAtp = Math.max(0, resultingPhysical - currentReserved - currentSafety);

  const handleSubmitReview = (e: React.FormEvent) => {
    e.preventDefault();
    if (isNegativeStockError) return;
    setStep('review');
  };

  const handleFinalConfirm = () => {
    if (!item || isNegativeStockError) return;
    const fullReason = customNotes.trim() ? `${reason} — ${customNotes.trim()}` : reason;
    const whId = selectedWarehouseId || undefined;
    if (mode === 'delta') {
      onApplyAdjustment(item.masterSku, delta, fullReason, staffDisplayName, whId);
    } else {
      onRecordAuditCount(item.masterSku, auditCount, fullReason, staffDisplayName, whId);
    }
    onClose();
  };

  if (!isOpen || !item) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/70 backdrop-blur-xs overflow-y-auto">
      <div className="relative w-full max-w-lg max-h-[calc(100dvh-1.5rem)] sm:max-h-[calc(100dvh-2.5rem)] my-auto flex flex-col bg-white dark:bg-neutral-900 rounded-2xl shadow-2xl border border-neutral-200 dark:border-neutral-800 overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        
        {/* Header - Pinned */}
        <div className="p-4 sm:p-5 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950/60 flex items-center justify-between shrink-0">
          <div className="flex items-center gap-3">
            <div className="p-2.5 rounded-xl bg-neutral-100 dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200 border border-neutral-200 dark:border-neutral-700">
              <Warehouse className="w-5 h-5" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                  Warehouse Stock Operation
                </h3>
                {isUnknownPhysical && (
                  <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                    Awaiting Physical Audit
                  </span>
                )}
              </div>
              <p className="text-xs text-neutral-500 dark:text-neutral-400 font-mono mt-0.5 truncate max-w-[220px] sm:max-w-xs">
                {item.masterSku} • {item.productName}
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

        {/* Action Mode Toggle - Pinned */}
        <div className="px-4 sm:px-6 pt-3.5 pb-2.5 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-950/30 flex items-center gap-2 shrink-0">
          <button
            type="button"
            onClick={() => { setMode('delta'); setStep('input'); }}
            className={`flex-1 py-2 px-3 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center justify-center gap-1.5 ${
              mode === 'delta'
                ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 shadow-xs'
                : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'
            }`}
          >
            <Plus className="w-3.5 h-3.5" />
            <span>Adjust Delta (Inbound / Write-off)</span>
          </button>
          <button
            type="button"
            onClick={() => { setMode('audit'); setStep('input'); }}
            className={`flex-1 py-2 px-3 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center justify-center gap-1.5 ${
              mode === 'audit'
                ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 shadow-xs'
                : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'
            }`}
          >
            <ClipboardCheck className="w-3.5 h-3.5" />
            <span>Physical Count Cycle Audit</span>
          </button>
        </div>

        {/* Content - Step 1: Input */}
        {step === 'input' && (
          <form onSubmit={handleSubmitReview} className="flex flex-col flex-1 min-h-0 overflow-hidden">
            <div className="p-4 sm:p-6 space-y-4 text-xs overflow-y-auto flex-1 min-h-0">
              {/* Notice for unverified stock */}
              {isUnknownPhysical && (
                <div className="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-700 dark:text-amber-300 text-xs flex items-start gap-2.5">
                  <AlertTriangle className="w-4 h-4 shrink-0 mt-0.5 text-amber-500" />
                  <div className="space-y-0.5">
                    <p className="font-bold">Physical stock unverified in warehouse</p>
                    <p className="text-[11px] opacity-90">
                      Marketplace catalog snapshots do not initialize warehouse physical inventory. Please verify actual physical count on shelf before stock broadcast.
                    </p>
                  </div>
                </div>
              )}
            
              {/* Operator Selection (Mandatory Authenticated Identity) */}
              <div>
                <label className="block font-bold text-neutral-700 dark:text-neutral-300 mb-1.5 flex items-center justify-between">
                  <span className="flex items-center gap-1.5">
                    <UserCheck className="w-3.5 h-3.5 text-neutral-500" />
                    Authenticated Employee Identity <span className="text-rose-500">*</span>
                  </span>
                  <span className="text-[10px] text-neutral-400 font-normal">Audit-logged</span>
                </label>
                <select
                  value={selectedStaffId}
                  onChange={e => setSelectedStaffId(e.target.value)}
                  required
                  className="w-full px-3 py-2.5 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white font-medium focus:ring-2 focus:ring-neutral-400 dark:focus:ring-neutral-600 focus:outline-hidden"
                >
                  {staffUsers.map(staff => (
                    <option key={staff.id} value={staff.id}>
                      {staff.fullName} — {staff.position || 'Staff'} ({staff.email})
                    </option>
                  ))}
                </select>
              </div>

              {/* Warehouse Target Selection (Multi-Warehouse Mode) */}
              {hasWarehouseBalances && (
                <div>
                  <label className="block font-bold text-neutral-700 dark:text-neutral-300 mb-1.5 flex items-center justify-between">
                    <span className="flex items-center gap-1.5">
                      <Warehouse className="w-3.5 h-3.5 text-neutral-500" />
                      Target Entity Warehouse <span className="text-rose-500">*</span>
                    </span>
                    <span className="text-[10px] text-neutral-400 font-mono">
                      Bin: {currentBin}
                    </span>
                  </label>
                  <select
                    value={selectedWarehouseId}
                    onChange={e => {
                      setSelectedWarehouseId(e.target.value);
                      const wb = item.warehouseBalances?.find(b => b.warehouseId === e.target.value);
                      if (wb) {
                        setAuditCount(wb.physicalStock);
                      }
                    }}
                    className="w-full px-3 py-2.5 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white font-medium focus:ring-2 focus:ring-neutral-400 dark:focus:ring-neutral-600 focus:outline-hidden"
                  >
                    {(item.warehouseBalances || []).map(wb => (
                      <option key={wb.warehouseId} value={wb.warehouseId}>
                        {wb.warehouseName} ({wb.warehouseCode}) — Shelf {wb.locationBin} (Phys: {wb.physicalStock}, ATP: {wb.availableAtp})
                      </option>
                    ))}
                  </select>
                </div>
              )}

              {/* Quantity Input */}
              {mode === 'delta' ? (
                <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-950/60 border border-neutral-200 dark:border-neutral-800 space-y-2">
                  <div className="flex items-center justify-between">
                    <span className="font-bold text-neutral-700 dark:text-neutral-300">
                      Stock Change (Delta Units)
                    </span>
                    <span className="text-[11px] text-neutral-400 font-mono">
                      Current: {currentPhysical} units {currentWarehouseBalance ? `(${currentWarehouseBalance.warehouseCode})` : ''}
                    </span>
                  </div>
                  <div className="flex items-center gap-2">
                    <button
                      type="button"
                      onClick={() => setDelta(prev => prev - 1)}
                      className="p-2.5 rounded-lg bg-white dark:bg-neutral-800 hover:bg-neutral-200 dark:hover:bg-neutral-700 border border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300 cursor-pointer transition-colors"
                    >
                      <Minus className="w-4 h-4" />
                    </button>
                    <input
                      type="number"
                      value={delta}
                      onChange={e => setDelta(parseInt(e.target.value) || 0)}
                      className="w-full text-center font-black text-lg px-3 py-2 rounded-xl bg-white dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white"
                    />
                    <button
                      type="button"
                      onClick={() => setDelta(prev => prev + 1)}
                      className="p-2.5 rounded-lg bg-white dark:bg-neutral-800 hover:bg-neutral-200 dark:hover:bg-neutral-700 border border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300 cursor-pointer transition-colors"
                    >
                      <Plus className="w-4 h-4" />
                    </button>
                  </div>
                  <div className="flex items-center justify-between text-[11px] pt-1 text-neutral-500 dark:text-neutral-400">
                    <span>New Physical Stock: <strong className={`font-bold ${isNegativeStockError ? 'text-rose-600 dark:text-rose-400' : 'text-neutral-900 dark:text-white'}`}>{resultingPhysical} units</strong></span>
                    <span>Projected ATP: <strong className="text-emerald-600 dark:text-emerald-400 font-bold">{resultingAtp} units</strong></span>
                  </div>
                </div>
              ) : (
                <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-950/60 border border-neutral-200 dark:border-neutral-800 space-y-2">
                  <div className="flex items-center justify-between">
                    <span className="font-bold text-neutral-700 dark:text-neutral-300">
                      Verified Physical Shelf Count
                    </span>
                    <span className="text-[11px] text-neutral-400 font-mono">
                      Bin: {currentBin}
                    </span>
                  </div>
                  <input
                    type="number"
                    min="0"
                    value={auditCount}
                    onChange={e => setAuditCount(parseInt(e.target.value) || 0)}
                    placeholder="Enter exact count counted on shelf..."
                    required
                    className="w-full text-center font-black text-xl px-3 py-2.5 rounded-xl bg-white dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white"
                  />
                  <div className="flex items-center justify-between text-[11px] pt-1 text-neutral-500 dark:text-neutral-400">
                    <span>Reserved: <strong className="text-amber-600 dark:text-amber-400">{currentReserved}</strong></span>
                    <span>Safety Buffer: <strong className="text-neutral-700 dark:text-neutral-300">{currentSafety}</strong></span>
                    <span>Verified ATP: <strong className="text-emerald-600 dark:text-emerald-400 font-bold">{resultingAtp} units</strong></span>
                  </div>
                </div>
              )}

              {/* Defensive Negative Stock Alert */}
              {isNegativeStockError && (
                <div className="p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-700 dark:text-rose-300 text-xs flex items-start gap-2.5">
                  <AlertTriangle className="w-4 h-4 shrink-0 mt-0.5 text-rose-500" />
                  <div className="space-y-0.5">
                    <p className="font-bold">Defensive Validation: Negative Stock Disallowed</p>
                    <p className="text-[11px] opacity-90">
                      Resulting physical balance would be {resultingPhysical} units. Negative inventory is strictly prohibited. Please enter a valid adjustment quantity.
                    </p>
                  </div>
                </div>
              )}

              {/* Reason Selection */}
              <div>
                <label className="block font-bold text-neutral-700 dark:text-neutral-300 mb-1.5">
                  Audit Reason <span className="text-rose-500">*</span>
                </label>
                <select
                  value={reason}
                  onChange={e => setReason(e.target.value)}
                  className="w-full px-3 py-2.5 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white font-medium"
                >
                  {mode === 'delta' ? (
                    <>
                      <option value="Supplier Stock Inbound (PO Receipt)">Supplier Stock Inbound (PO Receipt)</option>
                      <option value="Damaged / Defective Stock Write-off">Damaged / Defective Stock Write-off</option>
                      <option value="Customer Return Restock (Inspected)">Customer Return Restock (Inspected)</option>
                      <option value="Internal Sampling / Demo Transfer">Internal Sampling / Demo Transfer</option>
                      <option value="Packaging Shrinkage Reconciliation">Packaging Shrinkage Reconciliation</option>
                    </>
                  ) : (
                    <>
                      <option value="Initial Physical Warehouse Audit & Ledger Verification">Initial Physical Warehouse Audit & Ledger Verification</option>
                      <option value="Monthly Routine Cycle Count">Monthly Routine Cycle Count</option>
                      <option value="Discrepancy Investigation Reconciliation">Discrepancy Investigation Reconciliation</option>
                      <option value="Annual Stock Take Audit">Annual Stock Take Audit</option>
                    </>
                  )}
                </select>
              </div>

              {/* Custom Notes / PO Reference */}
              <div>
                <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                  Reference / Notes (PO number, batch ref, shelf bin tag)
                </label>
                <input
                  type="text"
                  value={customNotes}
                  onChange={e => setCustomNotes(e.target.value)}
                  placeholder="e.g. PO-2026-8812 / Rack 3 Physical Inspection"
                  className="w-full px-3 py-2 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white"
                />
              </div>
            </div>

            {/* Pinned Action Footer */}
            <div className="p-3.5 sm:px-6 sm:py-3.5 flex items-center justify-between border-t border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950/70 shrink-0">
              <button
                type="button"
                onClick={onClose}
                className="px-4 py-2.5 text-xs font-semibold text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-xl cursor-pointer transition-colors"
              >
                Cancel
              </button>
              <button
                type="submit"
                disabled={isNegativeStockError}
                className="px-5 py-2.5 bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 text-xs font-bold rounded-xl shadow-xs hover:bg-neutral-800 dark:hover:bg-neutral-100 disabled:opacity-40 disabled:cursor-not-allowed flex items-center gap-1.5 cursor-pointer transition-colors"
              >
                <span>Proceed to Review</span>
                <ArrowRight className="w-3.5 h-3.5" />
              </button>
            </div>
          </form>
        )}

        {/* Content - Step 2: Explicit Review */}
        {step === 'review' && (
          <div className="flex flex-col flex-1 min-h-0 overflow-hidden">
            <div className="p-4 sm:p-6 space-y-4 text-xs overflow-y-auto flex-1 min-h-0">
              <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 space-y-3">
                <div className="flex items-center gap-2 text-neutral-900 dark:text-white font-bold border-b border-neutral-200 dark:border-neutral-800 pb-2">
                  <ShieldCheck className="w-4 h-4 text-emerald-500" />
                  <span>Explicit Review & Authorization Summary</span>
                </div>

                <div className="grid grid-cols-2 gap-3 text-[11px]">
                  <div>
                    <span className="text-neutral-500 block">Operator Identity:</span>
                    <span className="font-bold text-neutral-900 dark:text-white">{staffDisplayName}</span>
                  </div>
                  <div>
                    <span className="text-neutral-500 block">Operation Mode:</span>
                    <span className="font-bold text-neutral-900 dark:text-white">
                      {mode === 'delta' ? 'Stock Delta Adjustment' : 'Physical Cycle Count Audit'}
                    </span>
                  </div>
                  {currentWarehouseBalance && (
                    <div className="col-span-2 p-2 rounded-lg bg-neutral-100 dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800">
                      <span className="text-neutral-500 block text-[10px]">Target Physical Warehouse:</span>
                      <span className="font-bold text-neutral-900 dark:text-white text-xs">
                        {currentWarehouseBalance.warehouseName} ({currentWarehouseBalance.warehouseCode}) • Shelf Bin: {currentWarehouseBalance.locationBin}
                      </span>
                    </div>
                  )}
                  <div>
                    <span className="text-neutral-500 block">Physical Stock Before:</span>
                    <span className="font-mono text-neutral-700 dark:text-neutral-300">
                      {currentPhysical} units
                    </span>
                  </div>
                  <div>
                    <span className="text-neutral-500 block">Resulting Physical Stock:</span>
                    <span className="font-mono font-bold text-neutral-900 dark:text-white">
                      {resultingPhysical} units
                    </span>
                  </div>
                  <div>
                    <span className="text-neutral-500 block">Reserved / Safety Buffer:</span>
                    <span className="font-mono text-neutral-700 dark:text-neutral-300">
                      {item.reservedStock} res / {item.safetyStock} safety
                    </span>
                  </div>
                  <div>
                    <span className="text-neutral-500 block">Resulting ATP:</span>
                    <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400 text-sm">
                      {resultingAtp} units
                    </span>
                  </div>
                </div>

                <div className="pt-2 border-t border-neutral-200 dark:border-neutral-800 text-[11px]">
                  <span className="text-neutral-500 block">Reason:</span>
                  <span className="font-medium text-neutral-800 dark:text-neutral-200">
                    {reason} {customNotes && `(${customNotes})`}
                  </span>
                </div>

                {/* Connected Store Impact Preview */}
                <div className="pt-2 border-t border-neutral-200 dark:border-neutral-800">
                  <span className="text-[10px] uppercase tracking-wider font-bold text-neutral-400 block mb-1.5">
                    Connected Store Synchronization Impact
                  </span>
                  <div className="space-y-1">
                    {(item.storeAssociations || []).length === 0 ? (
                      <p className="text-[11px] text-neutral-400 italic">No stores currently associated with this SKU.</p>
                    ) : (
                      (item.storeAssociations || []).map(a => (
                        <div key={a.connectionId} className="flex items-center justify-between text-[11px] px-2 py-1 rounded bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800">
                          <span className="font-semibold text-neutral-800 dark:text-neutral-200">{a.storeName} ({a.platform.toUpperCase()})</span>
                          <span className="text-neutral-500">
                            {a.syncState === 'enabled' ? (
                              <span className="text-emerald-600 dark:text-emerald-400 font-medium">Will update to {resultingAtp} units</span>
                            ) : (
                              <span className="text-neutral-400">Sync inactive ({a.syncState})</span>
                            )}
                          </span>
                        </div>
                      ))
                    )}
                  </div>
                </div>
              </div>

              {/* Explicit Acknowledgment Checkbox */}
              <label className="flex items-start gap-2.5 p-3 rounded-xl bg-neutral-100/70 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-800 cursor-pointer">
                <input
                  type="checkbox"
                  checked={confirmedAcknowledged}
                  onChange={e => setConfirmedAcknowledged(e.target.checked)}
                  className="mt-0.5 rounded text-neutral-900 focus:ring-neutral-500"
                />
                <span className="text-[11px] text-neutral-700 dark:text-neutral-300 leading-snug">
                  I, <strong>{staffDisplayName}</strong>, authorize this stock change and verify that the recorded count accurately reflects physical goods at the warehouse location.
                </span>
              </label>
            </div>

            {/* Pinned Action Footer */}
            <div className="p-3.5 sm:px-6 sm:py-3.5 flex items-center justify-between border-t border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950/70 shrink-0">
              <button
                type="button"
                onClick={() => setStep('input')}
                className="px-4 py-2 text-xs font-semibold text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-xl cursor-pointer transition-colors"
              >
                Back to Edit
              </button>
              <button
                type="button"
                disabled={!confirmedAcknowledged}
                onClick={handleFinalConfirm}
                className="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed text-white text-xs font-bold rounded-xl shadow-xs flex items-center gap-1.5 cursor-pointer transition-colors"
              >
                <CheckCircle2 className="w-3.5 h-3.5" />
                <span>Confirm & Apply to WMS Ledger</span>
              </button>
            </div>
          </div>
        )}

      </div>
    </div>
  );
};
