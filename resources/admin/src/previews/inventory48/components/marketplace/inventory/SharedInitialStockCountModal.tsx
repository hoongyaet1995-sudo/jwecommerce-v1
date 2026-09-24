import React, { useState, useMemo } from 'react';
import {
  X,
  ClipboardCheck,
  AlertTriangle,
  Info,
  CheckCircle2,
  Boxes,
  ArrowRight,
  ShieldAlert,
  Store,
  Layers,
  ChevronRight,
  RotateCcw
} from 'lucide-react';
import { MarketplaceInventoryItem } from '../../../types';
import { InventoryStoreChannel, StoreStockAccountBalance } from './inventoryTypes';
import { MarketplaceLogo } from '../MarketplaceLogo';

export interface InitialStockCountModalProps {
  isOpen: boolean;
  onClose: () => void;
  item: MarketplaceInventoryItem | null;
  mode: 'shared' | 'independent';
  stores: InventoryStoreChannel[];
  storeBalances?: Record<string, Record<string, StoreStockAccountBalance>>;
  onSaveCount: (payload: {
    sku: string;
    targetStoreId?: string; // for independent mode
    allocations?: Record<string, number>;
    physicalCount: number;
    reason: string;
    notes?: string;
    operator: string;
  }) => Promise<any>;
  uncountedQueue?: MarketplaceInventoryItem[];
  onSelectNextItem?: (nextItem: MarketplaceInventoryItem) => void;
  onContinueModeSwitchPreparation?: () => void;
  onNavigateToTransfers?: () => void;
  onNavigateToSkuMapping?: () => void;
  onRetrySync?: (taskId: string) => Promise<unknown>;
  onOpenSyncDetails?: (store: InventoryStoreChannel) => void;
}

export const SharedInitialStockCountModal: React.FC<InitialStockCountModalProps> = ({
  isOpen,
  onClose,
  item,
  mode,
  stores,
  storeBalances = {},
  onSaveCount,
  uncountedQueue = [],
  onSelectNextItem,
  onContinueModeSwitchPreparation
}) => {
  // Modal Step: 'input' | 'review' | 'success'
  const [step, setStep] = useState<'input' | 'review' | 'success'>('input');

  // Form State - Input field MUST BE BLANK by default! Never prefilled with channel number.
  const [physicalCountInput, setPhysicalCountInput] = useState<string>('');
  const [selectedStoreId, setSelectedStoreId] = useState<string>('webstore');
  const [reason, setReason] = useState<string>('Initial Physical Stock Count');
  const [notes, setNotes] = useState<string>('');
  const [operator, setOperator] = useState<string>('Alexander (Admin)');
  const [isSaving, setIsSaving] = useState(false);
  const [saveError, setSaveError] = useState('');

  // Reset form when item changes or modal opens
  React.useEffect(() => {
    if (isOpen) {
      setPhysicalCountInput('');
      setStep('input');
      setReason('Initial Physical Stock Count');
      setNotes('');
      setIsSaving(false);
      setSaveError('');
    }
  }, [isOpen, item?.id]);

  if (!isOpen || !item) return null;

  const numericCount = physicalCountInput.trim() === '' ? NaN : Number(physicalCountInput);
  const isValidNumber = !isNaN(numericCount) && numericCount >= 0 && Number.isInteger(numericCount);

  // Selected target store object (for independent mode)
  const safeStores = stores || [];
  const selectedStore = safeStores.find(s => s.id === selectedStoreId) || safeStores[0];

  // Existing reserved stock
  const selectedBalance = mode === 'independent' ? storeBalances[item.masterSku]?.[selectedStoreId] : undefined;
  const currentReserved = selectedBalance?.reservedStock ?? item.reservedStock ?? 0;

  // Conflict detection: if entered count < reserved
  const hasConflict = isValidNumber && numericCount < currentReserved;

  // Impact calculation
  const newPhysical = isValidNumber ? numericCount : 0;
  const newAvailable = Math.max(0, newPhysical - currentReserved);
  const previousPhysical = mode === 'independent' ? selectedBalance?.physicalStock : item.totalPhysicalStock;
  const isUncounted = previousPhysical === null || previousPhysical === undefined;
  const previousQuantityDisplay = isUncounted ? 'Not Counted (未盘点)' : `${previousPhysical} units`;
  const quantityDelta = isUncounted ? `+${newPhysical}` : (newPhysical - previousPhysical >= 0 ? `+${newPhysical - previousPhysical}` : `${newPhysical - previousPhysical}`);

  // Remaining uncounted queue
  const remainingUncounted = uncountedQueue.filter(q => q.id !== item.id);

  const handleProceedToReview = (e: React.FormEvent) => {
    e.preventDefault();
    if (!isValidNumber) return;
    if (hasConflict) {
      // Prohibit proceeding directly with unhandled conflict
      return;
    }
    setStep('review');
  };

  const handleConfirmSave = async () => {
    if (!isValidNumber) return;
    setIsSaving(true);
    setSaveError('');
    try {
      await onSaveCount({
        sku: item.masterSku,
        targetStoreId: mode === 'independent' ? selectedStoreId : undefined,
        physicalCount: numericCount,
        reason,
        notes: notes.trim() || undefined,
        operator
      });
      setStep('success');
    } catch (error) {
      setSaveError(error instanceof Error ? error.message : 'Physical count could not be saved. Review the input and try again.');
    } finally {
      setIsSaving(false);
    }
  };

  const handleNextSku = () => {
    if (remainingUncounted.length > 0 && onSelectNextItem) {
      onSelectNextItem(remainingUncounted[0]);
      setPhysicalCountInput('');
      setStep('input');
    } else {
      onClose();
    }
  };

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto bg-neutral-950/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 animate-in fade-in duration-150">
      <div
        id="initial-stock-count-modal"
        className="bg-white dark:bg-neutral-900 rounded-2xl shadow-2xl border border-neutral-200 dark:border-neutral-800 w-full max-w-2xl overflow-hidden flex flex-col max-h-[92vh]"
      >
        {/* Header */}
        <div className="px-6 py-4 border-b border-neutral-100 dark:border-neutral-800 flex items-center justify-between bg-neutral-50/50 dark:bg-neutral-900/50">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-neutral-100 dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200 flex items-center justify-center font-bold">
              <ClipboardCheck className="w-5 h-5" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h3 className="text-base font-bold text-neutral-900 dark:text-white">
                  {step === 'success'
                    ? 'Initial Count Recorded'
                    : step === 'review'
                    ? 'Review Physical Stock Count'
                    : 'Initial Physical Stock Count (首次实物盘点)'}
                </h3>
                <span className="text-[11px] font-bold px-2 py-0.5 rounded-md bg-neutral-100 dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200 border border-neutral-200 dark:border-neutral-700">
                  {mode === 'shared' ? 'Shared Stock Mode' : 'Independent Stock Mode'}
                </span>
              </div>
              <p className="text-xs text-neutral-500 dark:text-neutral-400">
                {mode === 'shared'
                  ? 'Verify actual physical units in central inventory. All connected stores share this single pool.'
                  : 'Assign verified physical units to a specific store inventory account.'}
              </p>
            </div>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="p-2 rounded-lg text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Modal Body */}
        <div className="flex-1 overflow-y-auto p-6 space-y-5">
          {/* Target SKU summary card */}
          <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200/80 dark:border-neutral-700/60 flex items-center gap-4">
            <div className="w-14 h-14 rounded-xl bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 overflow-hidden shrink-0 flex items-center justify-center">
              {item.imageUrl ? (
                <img src={item.imageUrl} alt={item.productName} className="w-full h-full object-cover" />
              ) : (
                <Boxes className="w-6 h-6 text-neutral-400" />
              )}
            </div>
            <div className="min-w-0 flex-1">
              <div className="flex items-center gap-2">
                <span className="font-mono text-xs font-bold text-neutral-900 dark:text-white">
                  {item.masterSku}
                </span>
                {isUncounted && step !== 'success' ? (
                  <span className="text-[10px] font-bold px-1.5 py-0.5 rounded bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-300/40">
                    Not Counted (未盘点)
                  </span>
                ) : (
                  <span className="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300">
                    Verified ({step === 'success' ? numericCount : item.totalPhysicalStock} units)
                  </span>
                )}
              </div>
              <h4 className="text-sm font-bold text-neutral-900 dark:text-white truncate mt-0.5">
                {item.productName}
              </h4>
              <div className="text-xs text-neutral-500 dark:text-neutral-400 flex items-center gap-2 mt-0.5">
                <span>{item.variantName}</span>
                {item.barcode && <span>• Barcode: {item.barcode}</span>}
              </div>
            </div>
          </div>

          {/* STEP 1: INPUT FORM */}
          {step === 'input' && (
            <form onSubmit={handleProceedToReview} className="space-y-5">
              {/* Platform Reference Stock Callout (Strictly separated from actual input) */}
              <div className="p-3.5 rounded-xl bg-neutral-100/70 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-700 text-xs space-y-2">
                <div className="flex items-center gap-2 font-semibold text-neutral-700 dark:text-neutral-300">
                  <Info className="w-4 h-4 text-neutral-500 shrink-0" />
                  <span>External Platform Reference Quantities (For Reference Only)</span>
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px] text-neutral-600 dark:text-neutral-400">
                  {Number.isFinite((item as any).catalogStockSnapshot) && (
                    <div className="flex items-center justify-between p-2 rounded-lg bg-white dark:bg-neutral-800/80 border border-neutral-200/60 dark:border-neutral-700/60">
                      <span>JW Catalog Reference</span>
                      <span className="font-mono font-bold text-neutral-800 dark:text-neutral-200">{(item as any).catalogStockSnapshot} units</span>
                    </div>
                  )}
                  {((item.storeAssociations || []).length > 0) ? (
                    (item.storeAssociations || []).map(assoc => (
                      <div key={assoc.connectionId} className="flex items-center justify-between p-2 rounded-lg bg-white dark:bg-neutral-800/80 border border-neutral-200/60 dark:border-neutral-700/60">
                        <div className="flex items-center gap-1.5 truncate">
                          <MarketplaceLogo platform={assoc.platform} size="xs" />
                          <span className="truncate">{assoc.storeNickname || assoc.storeName}</span>
                        </div>
                        <span className="font-mono font-bold text-neutral-800 dark:text-neutral-200">
                          {assoc.channelStockSnapshot !== undefined ? `${assoc.channelStockSnapshot} units` : 'N/A'}
                        </span>
                      </div>
                    ))
                  ) : (
                    <div className="col-span-2 text-neutral-400">No channel associations established yet.</div>
                  )}
                </div>
                <p className="text-[11px] text-amber-700 dark:text-amber-400 font-medium">
                  Notice: Platform reported quantities are not verified physical stock. The input field below is intentionally blank and must reflect the physical items counted by staff.
                </p>
              </div>

              {/* Independent Mode: Store Selector + Explicit Conservation Warning */}
              {mode === 'independent' && (
                <div className="space-y-2 p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700">
                  <label className="block text-xs font-bold text-neutral-900 dark:text-neutral-100">
                    Select Target Store Inventory Account *
                  </label>
                  <select
                    value={selectedStoreId}
                    onChange={e => setSelectedStoreId(e.target.value)}
                    className="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white focus:ring-2 focus:ring-neutral-400 font-medium cursor-pointer"
                  >
                    {safeStores.filter(s => !s.isComingSoon).map(s => (
                      <option key={s.id} value={s.id}>
                        {s.name} ({s.platformDisplayName}) - {s.nickname}
                      </option>
                    ))}
                  </select>

                  <div className="flex items-start gap-2 text-[11px] text-neutral-700 dark:text-neutral-300 pt-1 font-medium">
                    <ShieldAlert className="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
                    <span>
                      <strong>Warning:</strong> The same batch of physical goods cannot be counted repeatedly across multiple store accounts. In Independent mode, units allocated to this store belong exclusively to this store's ledger.
                    </span>
                  </div>
                </div>
              )}

              {/* Physical Count Input Field (MUST BE BLANK INITIALLY) */}
              <div className="space-y-1.5">
                <div className="flex items-center justify-between">
                  <label className="text-xs font-bold text-neutral-800 dark:text-neutral-200">
                    {mode === 'shared'
                      ? 'Total Shared Physical Stock (实物盘点数量) *'
                      : `Verified Physical Quantity for ${selectedStore.name} *`}
                  </label>
                  <span className="text-[11px] text-neutral-400">
                    Must be a whole non-negative number
                  </span>
                </div>
                <div className="relative">
                  <input
                    type="number"
                    min="0"
                    step="1"
                    placeholder="Enter verified physical count (e.g. 25)..."
                    value={physicalCountInput}
                    onChange={e => setPhysicalCountInput(e.target.value)}
                    className={`w-full px-3.5 py-2.5 text-sm font-mono font-bold rounded-xl border transition-all ${
                      physicalCountInput === ''
                        ? 'border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white'
                        : !isValidNumber
                        ? 'border-red-400 bg-red-50/30 text-red-700 focus:ring-red-500'
                        : hasConflict
                        ? 'border-amber-400 bg-amber-50/30 text-amber-800 focus:ring-amber-500'
                        : 'border-emerald-400 bg-emerald-50/20 text-emerald-900 dark:text-emerald-100 focus:ring-emerald-500'
                    }`}
                  />
                  <div className="absolute right-3 top-2.5 text-xs text-neutral-400 pointer-events-none">
                    Units
                  </div>
                </div>
              </div>

              {/* Conflict Callout: Physical Count < Reserved */}
              {hasConflict && (
                <div className="p-3.5 rounded-xl bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800/50 flex items-start gap-3">
                  <AlertTriangle className="w-5 h-5 text-red-600 dark:text-red-400 shrink-0 mt-0.5" />
                  <div className="text-xs text-red-900 dark:text-red-200 space-y-1">
                    <div className="font-bold text-red-950 dark:text-red-100">
                      Inventory Deficit Conflict Detected!
                    </div>
                    <p>
                      Your entered physical count (<strong>{numericCount} units</strong>) is less than the active reserved orders (<strong>{currentReserved} units</strong>).
                      Saving this count would cause negative sellable stock (-{currentReserved - numericCount} units).
                    </p>
                    <div className="pt-1 text-[11px] font-semibold text-red-800 dark:text-red-300">
                      Resolution direction: Check whether orders were already physically picked, or conduct a secondary recount of uncounted stockroom shelves.
                    </div>
                  </div>
                </div>
              )}

              {/* Impact Preview Card */}
              {isValidNumber && !hasConflict && (
                <div className="p-3.5 rounded-xl bg-emerald-50/60 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800/40 space-y-2">
                  <div className="text-xs font-bold text-emerald-900 dark:text-emerald-200 flex items-center gap-1.5">
                    <CheckCircle2 className="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                    <span>Calculated Inventory Impact</span>
                  </div>
                  <div className="grid grid-cols-3 gap-2 text-center text-xs">
                    <div className="p-2 rounded-lg bg-white/80 dark:bg-neutral-800/80 border border-neutral-200 dark:border-neutral-700">
                      <div className="text-[10px] text-neutral-500">Verified Physical</div>
                      <div className="font-mono font-bold text-neutral-900 dark:text-white text-sm mt-0.5">
                        {newPhysical}
                      </div>
                    </div>
                    <div className="p-2 rounded-lg bg-white/80 dark:bg-neutral-800/80 border border-neutral-200 dark:border-neutral-700">
                      <div className="text-[10px] text-neutral-500">Locked Reserved</div>
                      <div className="font-mono font-bold text-amber-700 dark:text-amber-400 text-sm mt-0.5">
                        {currentReserved}
                      </div>
                    </div>
                    <div className="p-2 rounded-lg bg-white/80 dark:bg-neutral-800/80 border border-neutral-200 dark:border-neutral-700">
                      <div className="text-[10px] text-neutral-500">New Available (ATP)</div>
                      <div className="font-mono font-bold text-emerald-700 dark:text-emerald-400 text-sm mt-0.5">
                        {newAvailable}
                      </div>
                    </div>
                  </div>
                </div>
              )}

              {/* Operator, Reason & Notes */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div className="space-y-1">
                  <label className="text-xs font-bold text-neutral-700 dark:text-neutral-300">
                    Operator / Staff Name *
                  </label>
                  <input
                    type="text"
                    value={operator}
                    onChange={e => setOperator(e.target.value)}
                    className="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white"
                  />
                </div>
                <div className="space-y-1">
                  <label className="text-xs font-bold text-neutral-700 dark:text-neutral-300">
                    Audit Reason *
                  </label>
                  <select
                    value={reason}
                    onChange={e => setReason(e.target.value)}
                    className="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white cursor-pointer"
                  >
                    <option value="Initial Physical Stock Count">Initial Physical Stock Count (首次实物盘点)</option>
                    <option value="Post-Catalog Import Inventory Setup">Post-Catalog Import Inventory Setup</option>
                    <option value="New Batch Inbound Physical Verification">New Batch Inbound Physical Verification</option>
                    <option value="Physical Cycle Count Audit">Physical Cycle Count Audit</option>
                    <option value="Store Stock Account Initialization">Store Stock Account Initialization</option>
                  </select>
                </div>
              </div>

              <div className="space-y-1">
                <label className="text-xs font-medium text-neutral-700 dark:text-neutral-300">
                  Internal Notes (Optional)
                </label>
                <textarea
                  rows={2}
                  value={notes}
                  onChange={e => setNotes(e.target.value)}
                  placeholder="e.g. Counted in Central Rack B4, verified carton seals..."
                  className="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white"
                />
              </div>

              {/* Footer Buttons */}
              <div className="pt-2 flex items-center justify-between border-t border-neutral-100 dark:border-neutral-800">
                <div className="text-xs text-neutral-400">
                  {remainingUncounted.length > 0 && (
                    <span>{remainingUncounted.length} other uncounted item(s) pending</span>
                  )}
                </div>
                <div className="flex items-center gap-2">
                  <button
                    type="button"
                    onClick={onClose}
                    className="px-4 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 text-xs font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 cursor-pointer"
                  >
                    Cancel
                  </button>
                  <button
                    type="submit"
                    disabled={!isValidNumber || hasConflict}
                    className="px-5 py-2 rounded-xl bg-neutral-900 hover:bg-neutral-800 dark:bg-white dark:hover:bg-neutral-100 text-white dark:text-neutral-900 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-bold transition-all shadow-xs flex items-center gap-1.5 cursor-pointer"
                  >
                    <span>Proceed to Review</span>
                    <ArrowRight className="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>
            </form>
          )}

          {/* STEP 2: REVIEW STEP */}
          {step === 'review' && (
            <div className="space-y-4">
              <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700">
                <h4 className="text-xs font-bold uppercase tracking-wider text-neutral-900 dark:text-white mb-3">
                  Pre-Submission Audit Confirmation
                </h4>

                <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                  <div>
                    <span className="text-neutral-400 block text-[11px]">Product & Master SKU</span>
                    <span className="font-semibold text-neutral-900 dark:text-white">{item.masterSku}</span>
                  </div>
                  <div>
                    <span className="text-neutral-400 block text-[11px]">Target Scope</span>
                    <span className="font-semibold text-neutral-900 dark:text-white">
                      {mode === 'shared' ? 'Global Shared Pool' : (selectedStore?.name || 'Selected Store')}
                    </span>
                  </div>
                  <div>
                    <span className="text-neutral-400 block text-[11px]">Previous State</span>
                    <span className="font-semibold text-neutral-700 dark:text-neutral-300">{previousQuantityDisplay}</span>
                  </div>
                  <div>
                    <span className="text-neutral-400 block text-[11px]">Confirmed Physical Count</span>
                    <span className="font-mono font-bold text-neutral-900 dark:text-white text-sm">
                      {numericCount} units
                    </span>
                  </div>
                  <div>
                    <span className="text-neutral-400 block text-[11px]">Net Change</span>
                    <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400">
                      {quantityDelta}
                    </span>
                  </div>
                  <div>
                    <span className="text-neutral-400 block text-[11px]">Resulting Available (ATP)</span>
                    <span className="font-mono font-bold text-emerald-700 dark:text-emerald-300 text-sm">
                      {newAvailable} units
                    </span>
                  </div>
                </div>

                <div className="border-t border-neutral-200/60 dark:border-neutral-700/60 mt-3 pt-3 grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                  <div>
                    <span className="text-neutral-400 block text-[11px]">Auditor / Operator</span>
                    <span className="font-medium text-neutral-800 dark:text-neutral-200">{operator}</span>
                  </div>
                  <div>
                    <span className="text-neutral-400 block text-[11px]">Reason</span>
                    <span className="font-medium text-neutral-800 dark:text-neutral-200">{reason}</span>
                  </div>
                  {notes && (
                    <div className="col-span-2">
                      <span className="text-neutral-400 block text-[11px]">Notes</span>
                      <span className="italic text-neutral-600 dark:text-neutral-300">{notes}</span>
                    </div>
                  )}
                  <div className="col-span-2 flex items-center gap-2 pt-1 text-[11px] text-neutral-700 dark:text-neutral-300 font-medium">
                    <CheckCircle2 className="w-3.5 h-3.5 text-emerald-500" />
                    <span>Platform sync will be triggered to update connected stores to {newAvailable} units.</span>
                  </div>
                </div>
              </div>

              {saveError && (
                <div role="alert" className="rounded-xl border border-red-200 bg-red-50 px-3.5 py-3 text-xs font-medium text-red-800 dark:border-red-800/60 dark:bg-red-950/30 dark:text-red-200">
                  {saveError}
                </div>
              )}

              {/* Review Step Buttons */}
              <div className="pt-2 flex items-center justify-between border-t border-neutral-100 dark:border-neutral-800">
                <button
                  type="button"
                  onClick={() => setStep('input')}
                  className="px-4 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 text-xs font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 cursor-pointer"
                >
                  Back to Edit
                </button>
                <button
                  type="button"
                  onClick={handleConfirmSave}
                  disabled={isSaving}
                  className="px-6 py-2.5 rounded-xl bg-neutral-900 hover:bg-neutral-800 dark:bg-white dark:hover:bg-neutral-100 text-white dark:text-neutral-900 text-xs font-bold transition-all shadow-xs flex items-center gap-2 cursor-pointer disabled:cursor-wait disabled:opacity-50"
                >
                  <CheckCircle2 className="w-4 h-4" />
                  <span>{isSaving ? 'Saving Physical Count…' : 'Confirm & Save Physical Count'}</span>
                </button>
              </div>
            </div>
          )}

          {/* STEP 3: SUCCESS STEP */}
          {step === 'success' && (
            <div className="py-6 text-center space-y-5">
              <div className="w-14 h-14 rounded-2xl bg-neutral-100 dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200 mx-auto flex items-center justify-center">
                <CheckCircle2 className="w-8 h-8 text-emerald-600 dark:text-emerald-400" />
              </div>

              <div className="space-y-1.5">
                <h4 className="text-base font-bold text-neutral-900 dark:text-white">
                  Physical Stock Count Successfully Saved
                </h4>
                <p className="text-xs text-neutral-500 dark:text-neutral-400 max-w-md mx-auto">
                  Master SKU <strong>{item.masterSku}</strong> is now officially verified at <strong>{numericCount} units</strong>. Channel sync broadcast has been queued.
                </p>
              </div>

              <div className="p-3 max-w-sm mx-auto rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700 text-xs text-left space-y-1">
                <div className="flex justify-between">
                  <span className="text-neutral-500">Scope:</span>
                  <span className="font-semibold text-neutral-800 dark:text-neutral-200">{mode === 'shared' ? 'Shared Pool' : selectedStore.name}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-neutral-500">Verified Physical:</span>
                  <span className="font-mono font-bold text-neutral-900 dark:text-white">{numericCount}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-neutral-500">Available to Sell:</span>
                  <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400">{newAvailable}</span>
                </div>
              </div>

              <div className="flex items-center justify-center gap-3 pt-2">
                {remainingUncounted.length > 0 ? (
                  <button
                    type="button"
                    onClick={handleNextSku}
                    className="px-5 py-2.5 rounded-xl bg-neutral-900 hover:bg-neutral-800 dark:bg-white dark:hover:bg-neutral-100 text-white dark:text-neutral-900 text-xs font-bold transition-all shadow-xs flex items-center gap-2 cursor-pointer"
                  >
                    <span>Continue to Next Uncounted SKU ({remainingUncounted.length} left)</span>
                    <ChevronRight className="w-4 h-4" />
                  </button>
                ) : null}

                <button
                  type="button"
                  onClick={onContinueModeSwitchPreparation || onClose}
                  className="px-4 py-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-800 text-neutral-700 dark:text-neutral-300 text-xs font-semibold transition-colors cursor-pointer"
                >
                  Continue Mode Switch Preparation
                </button>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};
