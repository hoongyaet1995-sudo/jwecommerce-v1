import React, { useState, useEffect } from 'react';
import { MarketplaceInventoryItem, StaffUser } from '../../types';
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
  ClipboardCheck
} from 'lucide-react';

interface InventoryAdjustmentModalProps {
  item: MarketplaceInventoryItem | null;
  isOpen: boolean;
  onClose: () => void;
  staffUsers: StaffUser[];
  currentUserName?: string;
  onApplyAdjustment: (idOrSku: string, delta: number, reason: string, staffName: string) => void;
  onRecordAuditCount: (idOrSku: string, physicalCount: number, reason: string, staffName: string) => void;
}

export const InventoryAdjustmentModal: React.FC<InventoryAdjustmentModalProps> = ({
  item,
  isOpen,
  onClose,
  staffUsers = [],
  currentUserName,
  onApplyAdjustment,
  onRecordAuditCount
}) => {
  if (!isOpen || !item) return null;

  const isUnknownPhysical = item.physicalStockStatus === 'unknown' || item.totalPhysicalStock === null;

  // Tabs: 'delta' (adjustment) or 'audit' (physical count cycle)
  const [mode, setMode] = useState<'delta' | 'audit'>(isUnknownPhysical ? 'audit' : 'delta');
  const [step, setStep] = useState<'input' | 'review'>('input');

  // Input states
  const [delta, setDelta] = useState<number>(10);
  const [auditCount, setAuditCount] = useState<number>(item.totalPhysicalStock ?? 50);
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

  // Reset step on open
  useEffect(() => {
    setStep('input');
    setConfirmedAcknowledged(false);
    if (item.physicalStockStatus === 'unknown' || item.totalPhysicalStock === null) {
      setMode('audit');
      setAuditCount(50);
      setReason('Initial Physical Warehouse Audit & Ledger Verification');
    } else {
      setMode('delta');
      setDelta(10);
      setAuditCount(item.totalPhysicalStock);
      setReason('Supplier Stock Inbound (PO Receipt)');
    }
  }, [item]);

  const selectedStaff = staffUsers.find(s => s.id === selectedStaffId) || staffUsers[0];
  const staffDisplayName = selectedStaff ? `${selectedStaff.fullName} (${selectedStaff.position || 'Staff'})` : (currentUserName || 'Authorized Staff');

  // Calculations
  const currentPhysical = item.totalPhysicalStock ?? 0;
  const resultingPhysical = mode === 'delta'
    ? Math.max(0, currentPhysical + delta)
    : Math.max(0, auditCount);

  const resultingAtp = Math.max(0, resultingPhysical - item.reservedStock - item.safetyStock);

  const handleSubmitReview = (e: React.FormEvent) => {
    e.preventDefault();
    setStep('review');
  };

  const handleFinalConfirm = () => {
    const fullReason = customNotes.trim() ? `${reason} — ${customNotes.trim()}` : reason;
    if (mode === 'delta') {
      onApplyAdjustment(item.masterSku, delta, fullReason, staffDisplayName);
    } else {
      onRecordAuditCount(item.masterSku, auditCount, fullReason, staffDisplayName);
    }
    onClose();
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-xs">
      <div className="relative w-full max-w-lg bg-white dark:bg-neutral-900 rounded-2xl shadow-2xl border border-neutral-200 dark:border-neutral-800 overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        
        {/* Header */}
        <div className="p-5 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950/60 flex items-center justify-between">
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
              <p className="text-xs text-neutral-500 dark:text-neutral-400 font-mono mt-0.5">
                {item.masterSku} • {item.productName}
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 cursor-pointer"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Action Mode Toggle */}
        <div className="px-6 pt-4 pb-2 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-950/30 flex items-center gap-2">
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
            Adjust Delta (Inbound / Write-off)
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
            Physical Count Cycle Audit
          </button>
        </div>

        {/* Notice for unverified stock */}
        {isUnknownPhysical && (
          <div className="mx-6 mt-4 p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-700 dark:text-amber-300 text-xs flex items-start gap-2.5">
            <AlertTriangle className="w-4 h-4 shrink-0 mt-0.5 text-amber-500" />
            <div className="space-y-0.5">
              <p className="font-bold">Physical stock unverified in warehouse</p>
              <p className="text-[11px] opacity-90">
                Marketplace catalog snapshots do not initialize warehouse physical inventory. Please verify actual physical count on shelf before stock broadcast.
              </p>
            </div>
          </div>
        )}

        {/* Content - Step 1: Input */}
        {step === 'input' && (
          <form onSubmit={handleSubmitReview} className="p-6 space-y-4 text-xs">
            
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

            {/* Quantity Input */}
            {mode === 'delta' ? (
              <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-950/60 border border-neutral-200 dark:border-neutral-800 space-y-2">
                <div className="flex items-center justify-between">
                  <span className="font-bold text-neutral-700 dark:text-neutral-300">
                    Stock Change (Delta Units)
                  </span>
                  <span className="text-[11px] text-neutral-400 font-mono">
                    Current: {item.totalPhysicalStock ?? 'Unverified'}
                  </span>
                </div>
                <div className="flex items-center gap-2">
                  <button
                    type="button"
                    onClick={() => setDelta(prev => prev - 1)}
                    className="p-2.5 rounded-lg bg-white dark:bg-neutral-800 hover:bg-neutral-200 dark:hover:bg-neutral-700 border border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300 cursor-pointer"
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
                    className="p-2.5 rounded-lg bg-white dark:bg-neutral-800 hover:bg-neutral-200 dark:hover:bg-neutral-700 border border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300 cursor-pointer"
                  >
                    <Plus className="w-4 h-4" />
                  </button>
                </div>
                <div className="flex items-center justify-between text-[11px] pt-1 text-neutral-500 dark:text-neutral-400">
                  <span>New Physical Stock: <strong className="text-neutral-900 dark:text-white font-bold">{resultingPhysical} units</strong></span>
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
                    Bin: {item.warehouseLocation}
                  </span>
                </div>
                <input
                  type="number"
                  min="0"
                  value={auditCount}
                  onChange={e => setAuditCount(Math.max(0, parseInt(e.target.value) || 0))}
                  placeholder="Enter exact count counted on shelf..."
                  required
                  className="w-full text-center font-black text-xl px-3 py-2.5 rounded-xl bg-white dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white"
                />
                <div className="flex items-center justify-between text-[11px] pt-1 text-neutral-500 dark:text-neutral-400">
                  <span>Reserved: <strong className="text-amber-600 dark:text-amber-400">{item.reservedStock}</strong></span>
                  <span>Safety Buffer: <strong className="text-neutral-700 dark:text-neutral-300">{item.safetyStock}</strong></span>
                  <span>Verified ATP: <strong className="text-emerald-600 dark:text-emerald-400 font-bold">{resultingAtp} units</strong></span>
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

            <div className="pt-2 flex items-center justify-between border-t border-neutral-200 dark:border-neutral-800">
              <button
                type="button"
                onClick={onClose}
                className="px-4 py-2.5 text-xs font-semibold text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-xl cursor-pointer"
              >
                Cancel
              </button>
              <button
                type="submit"
                className="px-5 py-2.5 bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 text-xs font-bold rounded-xl shadow-xs hover:bg-neutral-800 dark:hover:bg-neutral-100 flex items-center gap-1.5 cursor-pointer"
              >
                <span>Proceed to Review</span>
                <ArrowRight className="w-3.5 h-3.5" />
              </button>
            </div>
          </form>
        )}

        {/* Content - Step 2: Explicit Review */}
        {step === 'review' && (
          <div className="p-6 space-y-4 text-xs">
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
                <div>
                  <span className="text-neutral-500 block">Physical Stock Before:</span>
                  <span className="font-mono text-neutral-700 dark:text-neutral-300">
                    {item.totalPhysicalStock !== null ? `${item.totalPhysicalStock} units` : 'Unverified / Unknown'}
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

            <div className="pt-2 flex items-center justify-between border-t border-neutral-200 dark:border-neutral-800">
              <button
                type="button"
                onClick={() => setStep('input')}
                className="px-4 py-2.5 text-xs font-semibold text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-xl cursor-pointer"
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
