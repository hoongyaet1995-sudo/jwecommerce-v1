import React, { useState, useMemo, useEffect } from 'react';
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
  ChevronRight,
  RotateCcw,
  Lock,
  Unlock,
  RefreshCw,
  ExternalLink,
  Sliders,
  Check,
  Split,
  AlertCircle
} from 'lucide-react';
import { MarketplaceInventoryItem } from '../../../types';
import { InventoryStoreChannel, StoreStockAccountBalance } from './inventoryTypes';
import { MarketplaceLogo } from '../MarketplaceLogo';

import { InitialStockCountModalProps } from './SharedInitialStockCountModal';

interface IndependentStockAllocationModalProps extends Omit<InitialStockCountModalProps, 'mode' | 'onSaveCount'> {
  mode: 'independent';
  onSaveCount: (payload: {
    sku: string;
    allocations?: Record<string, number>;
    physicalCount: number;
    reason: string;
    notes?: string;
    operator: string;
  }) => Promise<any>;
  onNavigateToTransfers?: () => void;
  onNavigateToSkuMapping?: () => void;
  onRetrySync?: (taskId: string) => Promise<unknown>;
}

/* ZIP interface retained only as a source reference for the adapter boundary.
interface InitialStockCountModalProps {
  isOpen: boolean;
  onClose: () => void;
  item: MarketplaceInventoryItem | null;
  mode: 'shared' | 'independent';
  stores: InventoryStoreChannel[];
  storeBalances?: Record<string, Record<string, StoreStockAccountBalance>>;
  onSaveCount: (payload: {
    sku: string;
    targetStoreId?: string;
    allocations?: Record<string, number>;
    physicalCount: number;
    reason: string;
    notes?: string;
    operator: string;
  }) => void;
  uncountedQueue?: MarketplaceInventoryItem[];
  onSelectNextItem?: (nextItem: MarketplaceInventoryItem) => void;
  onNavigateToTransfers?: () => void;
  onNavigateToSkuMapping?: () => void;
} */

export const IndependentStockAllocationModal: React.FC<IndependentStockAllocationModalProps> = ({
  isOpen,
  onClose,
  item,
  mode,
  stores,
  storeBalances = {},
  onSaveCount,
  uncountedQueue = [],
  onSelectNextItem,
  onNavigateToTransfers,
  onNavigateToSkuMapping,
  onRetrySync,
  onOpenSyncDetails
}) => {
  // Modal Step: 'input' (Count & Allocate) | 'review' (Review) | 'success' (Success)
  const [step, setStep] = useState<'input' | 'review' | 'success'>('input');

  // Physical Count Source (Can be locked if pre-verified from shared pool or previous approved count)
  const [isCountLocked, setIsCountLocked] = useState<boolean>(false);
  const [physicalCountInput, setPhysicalCountInput] = useState<string>('');

  // Per-Store Allocation Inputs: storeId -> string representation for user typing
  const [allocations, setAllocations] = useState<Record<string, string>>({});

  // Audit Fields
  const [operator, setOperator] = useState<string>('Alexander (Admin)');
  const [reason, setReason] = useState<string>('Initial Store Physical Count');
  const [notes, setNotes] = useState<string>('');

  const [isSaving, setIsSaving] = useState(false);
  const [saveError, setSaveError] = useState('');
  const [saveReceipt, setSaveReceipt] = useState<any>(null);
  const [retryingTaskId, setRetryingTaskId] = useState<string | null>(null);
  const syncStages: any[] = saveReceipt?.external_stages || [];

  // Filter channels into active inventory accounts and coming soon accounts
  const activeStores = useMemo(() => {
    return stores.filter(s => !s.isComingSoon && s.platform !== 'tiktok' && s.platform !== 'shopify');
  }, [stores]);

  const comingSoonStores = useMemo(() => {
    return stores.filter(s => s.isComingSoon || s.platform === 'tiktok' || s.platform === 'shopify');
  }, [stores]);

  // Read existing balances for this SKU if any
  const skuStoreBalances = useMemo(() => {
    if (!item?.masterSku) return {};
    return storeBalances[item.masterSku] || {};
  }, [item?.masterSku, storeBalances]);

  // Calculate sum of reserved stock across all stores
  const totalReservedAcrossStores = useMemo(() => {
    return activeStores.reduce((sum, st) => {
      const b = skuStoreBalances[st.id];
      const r = b?.reservedStock ?? 0;
      return sum + r;
    }, 0);
  }, [activeStores, skuStoreBalances]);

  const mappedStoreIds = useMemo(() => new Set<string>((item as any)?.mappedStoreIds || []), [item]);
  const isStoreMapped = (store: InventoryStoreChannel) => store.platform === 'webstore' || mappedStoreIds.has(store.id);

  // Initialize or Reset form when modal opens or item changes
  useEffect(() => {
    if (isOpen && item) {
      setStep('input');
      setOperator('Alexander (Admin)');
      setReason('Initial Store Physical Count');
      setNotes('');
      setSaveError('');
      setSaveReceipt(null);

      // If item already has an officially verified count, show as locked source total
      if (item.totalPhysicalStock !== null && item.totalPhysicalStock !== undefined && item.totalPhysicalStock > 0) {
        setPhysicalCountInput(String(item.totalPhysicalStock));
        setIsCountLocked(true);
      } else {
        // Field MUST be blank for a new first count!
        setPhysicalCountInput('');
        setIsCountLocked(false);
      }

      // Initialize allocations as blank strings for each active store
      const initialMap: Record<string, string> = {};
      activeStores.forEach(st => {
        initialMap[st.id] = '';
      });
      setAllocations(initialMap);
    }
  }, [isOpen, item?.id]);

  if (!isOpen || !item) return null;

  // Numerical verified total calculation
  const verifiedCountNum = physicalCountInput.trim() === '' ? NaN : Number(physicalCountInput);
  const isVerifiedCountValid = !isNaN(verifiedCountNum) && verifiedCountNum >= 0 && Number.isInteger(verifiedCountNum);

  // Parse allocations and calculate total allocated
  const parsedAllocations: Record<string, number> = {};
  let totalAllocatedNum = 0;
  let hasInvalidAllocationNumber = false;
  let hasNegativeAllocation = false;

  activeStores.forEach(st => {
    const rawVal = allocations[st.id] ?? '';
    if (rawVal.trim() === '') {
      parsedAllocations[st.id] = 0;
    } else {
      const num = Number(rawVal);
      if (isNaN(num) || !Number.isInteger(num)) {
        hasInvalidAllocationNumber = true;
      } else if (num < 0) {
        hasNegativeAllocation = true;
      } else {
        parsedAllocations[st.id] = num;
        totalAllocatedNum += num;
      }
    }
  });

  // Calculate live conservation metrics
  const verifiedTotal = isVerifiedCountValid ? verifiedCountNum : 0;
  const remainingCount = verifiedTotal - totalAllocatedNum;
  const netDifference = totalAllocatedNum - verifiedTotal;
  const isBalanced = isVerifiedCountValid && remainingCount === 0 && !hasInvalidAllocationNumber && !hasNegativeAllocation;

  // Check reserved stock violations per store
  const storeReservationConflicts: Record<string, { allocated: number; reserved: number }> = {};
  let hasReservationConflict = false;

  activeStores.forEach(st => {
    const storeAlloc = parsedAllocations[st.id] ?? 0;
    const storeReserved = skuStoreBalances[st.id]?.reservedStock ?? 0;

    // If staff allocated fewer units than existing reserved orders for this store
    if (storeAlloc < storeReserved && allocations[st.id]?.trim() !== '') {
      storeReservationConflicts[st.id] = { allocated: storeAlloc, reserved: storeReserved };
      hasReservationConflict = true;
    }
  });

  // Check connection blocker
  const hasConnectionBlocker = activeStores.some(st => {
    return st.connectionState !== 'connected' && (allocations[st.id]?.trim() !== '' && parsedAllocations[st.id] > 0);
  });

  // Validation flag for Proceed to Review
  const canProceedToReview =
    isVerifiedCountValid &&
    isBalanced &&
    !hasInvalidAllocationNumber &&
    !hasNegativeAllocation &&
    !hasReservationConflict &&
    !hasConnectionBlocker;

  // Quick Action Helpers
  const handleAssignRemaining = (targetStoreId: string = 'website') => {
    if (!isVerifiedCountValid || verifiedCountNum <= 0) return;
    const currentForTarget = Number(allocations[targetStoreId] || 0);
    const newTargetVal = Math.max(0, currentForTarget + remainingCount);

    setAllocations(prev => ({
      ...prev,
      [targetStoreId]: String(newTargetVal)
    }));
  };

  const handleDistributeEvenly = () => {
    if (!isVerifiedCountValid || verifiedCountNum <= 0) return;
    const eligibleStores = activeStores.filter(s => isStoreMapped(s) && s.connectionState === 'connected');
    if (eligibleStores.length === 0) return;

    const baseShare = Math.floor(verifiedCountNum / eligibleStores.length);
    let remainder = verifiedCountNum % eligibleStores.length;

    const newMap: Record<string, string> = {};
    activeStores.forEach(s => {
      newMap[s.id] = '0';
    });

    eligibleStores.forEach((st, idx) => {
      // Place remainder deterministically starting from the first store (JW Storefront)
      const allocatedAmount = idx < remainder ? baseShare + 1 : baseShare;
      newMap[st.id] = String(allocatedAmount);
    });

    setAllocations(newMap);
  };

  const handleSetZero = () => {
    const zeroMap: Record<string, string> = {};
    activeStores.forEach(s => {
      zeroMap[s.id] = '0';
    });
    setAllocations(zeroMap);
  };

  // Step 1 -> Step 2
  const handleProceedToReview = () => {
    if (!canProceedToReview) return;
    setStep('review');
  };

  // Step 2 -> Step 3
  const handleConfirmSave = async () => {
    if (!canProceedToReview) return;
    setIsSaving(true);
    setSaveError('');
    try {
      const eligibleAllocations = Object.fromEntries(
        activeStores.filter(isStoreMapped).map(store => [store.id, parsedAllocations[store.id] ?? 0])
      );
      const receipt = await onSaveCount({sku: item.masterSku, allocations: eligibleAllocations,
        physicalCount: verifiedCountNum, reason, notes: notes.trim() || undefined, operator});
      setSaveReceipt(receipt);
      setStep('success');
    } catch (error) {
      setSaveError(error instanceof Error ? error.message : 'Stock allocation could not be saved.');
    } finally {
      setIsSaving(false);
    }
  };

  const handleRetrySync = async (taskId: string) => {
    if (!onRetrySync) return;
    setRetryingTaskId(taskId);
    setSaveError('');
    try {
      await onRetrySync(taskId);
      setSaveReceipt((current: any) => ({
        ...current,
        external_stages: (current?.external_stages || []).map((stage: any) =>
          String(stage.id) === String(taskId) ? { ...stage, status: 'queued' } : stage
        )
      }));
    } catch (error) {
      setSaveError(error instanceof Error ? error.message : 'Platform sync could not be retried.');
    } finally {
      setRetryingTaskId(null);
    }
  };

  // Queue progression
  const remainingUncounted = uncountedQueue.filter(q => q.id !== item.id);
  const handleNextSku = () => {
    if (remainingUncounted.length > 0 && onSelectNextItem) {
      onSelectNextItem(remainingUncounted[0]);
      setStep('input');
    } else {
      onClose();
    }
  };

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto bg-neutral-950/75 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 animate-in fade-in duration-150">
      <div
        id="initial-stock-count-modal"
        className="bg-white dark:bg-neutral-900 rounded-2xl shadow-2xl border border-neutral-200 dark:border-neutral-800 w-full max-w-3xl overflow-hidden flex flex-col max-h-[92vh]"
      >
        {/* ================= MODAL HEADER ================= */}
        <div className="px-6 py-4 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-900/50">
          <div className="flex items-start justify-between gap-4">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-xl bg-neutral-100 dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200 flex items-center justify-center font-bold shrink-0">
                <ClipboardCheck className="w-5 h-5 text-neutral-900 dark:text-white" />
              </div>
              <div>
                <div className="flex items-center gap-2 flex-wrap">
                  <h3 className="text-base font-bold text-neutral-900 dark:text-white">
                    {step === 'success'
                      ? 'Stock Allocation Completed'
                      : step === 'review'
                      ? 'Review Stock Allocation'
                      : 'Allocate Stock'}
                  </h3>
                  <span className="text-[11px] font-semibold px-2 py-0.5 rounded-md bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 border border-neutral-200 dark:border-neutral-700">
                    Independent Stock
                  </span>
                  <span className="text-[11px] text-neutral-400">
                    Step {step === 'input' ? '1 of 3: Count & Allocate' : step === 'review' ? '2 of 3: Review' : '3 of 3: Success'}
                  </span>
                </div>
                <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                  Count this SKU once, then distribute the verified quantity across eligible store accounts.
                </p>
              </div>
            </div>

            <button
              type="button"
              onClick={onClose}
              className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors cursor-pointer"
              aria-label="Close modal"
            >
              <X className="w-5 h-5" />
            </button>
          </div>

        </div>

        {/* ================= MODAL BODY ================= */}
        <div className="p-6 overflow-y-auto space-y-5 flex-1">
          {/* STEP 1: COUNT & ALLOCATE */}
          {step === 'input' && (
            <div className="space-y-5">
              {/* Product Info Card */}
              <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-800 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div className="flex items-center gap-3.5 min-w-0">
                  <div className="w-14 h-14 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 overflow-hidden flex items-center justify-center shrink-0">
                    {item.imageUrl ? (
                      <img
                        src={item.imageUrl}
                        alt={item.productName}
                        className="w-full h-full object-cover"
                        referrerPolicy="no-referrer"
                      />
                    ) : (
                      <Boxes className="w-6 h-6 text-neutral-400" />
                    )}
                  </div>
                  <div className="min-w-0">
                    <div className="flex items-center gap-2 flex-wrap">
                      <span className="font-mono text-xs font-bold text-neutral-900 dark:text-white">
                        {item.masterSku}
                      </span>
                      {item.barcode && (
                        <span className="font-mono text-[11px] text-neutral-500 dark:text-neutral-400">
                          UPC: {item.barcode}
                        </span>
                      )}
                    </div>
                    <h4 className="text-sm font-semibold text-neutral-900 dark:text-neutral-100 truncate max-w-md">
                      {item.productName}
                    </h4>
                    <p className="text-xs text-neutral-500 dark:text-neutral-400">
                      Variant: <span className="font-medium text-neutral-700 dark:text-neutral-300">{item.variantName || 'Standard'}</span>
                    </p>
                  </div>
                </div>

                {/* Status Badges */}
                <div className="flex flex-col sm:items-end gap-1.5 shrink-0">
                  {item.totalPhysicalStock !== null && item.totalPhysicalStock > 0 ? (
                    <>
                      <div className="flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-neutral-200/70 dark:bg-neutral-700/60 text-neutral-800 dark:text-neutral-200 text-xs font-semibold">
                        <Check className="w-3.5 h-3.5 text-neutral-700 dark:text-neutral-300" />
                        <span>Total verified physical: {item.totalPhysicalStock} units</span>
                      </div>
                      <span className="text-[11px] px-2 py-0.5 rounded bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 font-medium">
                        Store allocation incomplete
                      </span>
                    </>
                  ) : (
                    <span className="text-xs px-2.5 py-1 rounded-md bg-neutral-200/70 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 font-medium">
                      Not Counted (未盘点)
                    </span>
                  )}
                </div>
              </div>

              {/* Reference Only Section: Marketplace Snapshots */}
              <div className="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-800 space-y-2 text-xs">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-1.5 text-neutral-500 dark:text-neutral-400 font-medium">
                    <Info className="w-4 h-4" />
                    <span>External Platform Reference Quantities (For Reference Only)</span>
                  </div>
                  <span className="text-[11px] text-neutral-400">Never auto-filled into JW ledger</span>
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
                  {(item.storeAssociations || []).map(association => (
                    <div key={association.connectionId} className="p-2.5 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
                      <div className="flex items-center gap-2 min-w-0">
                        <MarketplaceLogo platform={association.platform} size="xs" />
                        <span className="text-neutral-700 dark:text-neutral-300 font-medium truncate">{association.storeNickname || association.storeName}</span>
                      </div>
                      <span className="font-mono font-bold text-neutral-800 dark:text-neutral-200">{association.channelStockSnapshot === undefined ? 'N/A' : `${association.channelStockSnapshot} units`}</span>
                    </div>
                  ))}
                  {(item.storeAssociations || []).length === 0 && <span className="text-neutral-400">No marketplace snapshot available.</span>}
                </div>
                <div className="flex items-center gap-2 text-[11px] text-amber-700 dark:text-amber-300/90 pt-1">
                  <AlertTriangle className="w-3.5 h-3.5 shrink-0 text-amber-500" />
                  <span>
                    Notice: Platform reported quantities are not verified physical stock. The input field below is intentionally blank and must reflect the physical items counted by staff.
                  </span>
                </div>
              </div>

              {/* Section 1: Physical Count (Count Once) */}
              <div className="p-4 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 space-y-3">
                <div className="flex items-center justify-between">
                  <div>
                    <label
                      htmlFor="verified-physical-count-input"
                      className="block text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider"
                    >
                      Verified Total Physical Quantity <span className="text-rose-500">*</span>
                    </label>
                    <p className="text-xs text-neutral-500 dark:text-neutral-400">
                      Actual physical stock counted in batch for master SKU. Non-negative whole numbers only.
                    </p>
                  </div>
                  {isCountLocked ? (
                    <div className="flex items-center gap-2">
                      <span className="text-[11px] font-semibold px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 flex items-center gap-1">
                        <Lock className="w-3 h-3" />
                        <span>Source: Approved Physical Count</span>
                      </span>
                      <button
                        type="button"
                        onClick={() => setIsCountLocked(false)}
                        className="text-[11px] text-neutral-400 hover:text-neutral-200 underline cursor-pointer"
                      >
                        Recount / Unlock
                      </button>
                    </div>
                  ) : null}
                </div>

                <div className="flex items-center gap-3">
                  <div className="relative flex-1">
                    <input
                      id="verified-physical-count-input"
                      type="number"
                      min="0"
                      step="1"
                      disabled={isCountLocked}
                      placeholder="Enter verified physical units (e.g. 22)"
                      value={physicalCountInput}
                      onChange={e => setPhysicalCountInput(e.target.value)}
                      className="w-full px-3.5 py-2.5 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white font-mono text-base font-bold focus:outline-hidden focus:ring-2 focus:ring-neutral-900 dark:focus:ring-white disabled:opacity-75 disabled:cursor-not-allowed"
                    />
                    <span className="absolute right-3.5 top-1/2 -translate-y-1/2 text-xs font-semibold text-neutral-400 pointer-events-none">
                      Units
                    </span>
                  </div>
                </div>

                {/* Validation Note for Reserved Stock */}
                <div className="flex items-center justify-between text-xs text-neutral-500 dark:text-neutral-400 pt-1">
                  <span>
                    Existing reserved customer orders: <strong className="text-neutral-800 dark:text-neutral-200">{totalReservedAcrossStores} units</strong>
                  </span>
                  {isVerifiedCountValid && verifiedCountNum < totalReservedAcrossStores && (
                    <span className="text-rose-500 font-semibold flex items-center gap-1">
                      <AlertCircle className="w-3.5 h-3.5" />
                      Cannot be lower than reserved ({totalReservedAcrossStores})
                    </span>
                  )}
                </div>
              </div>

              {/* ================= LIVE CONSERVATION SUMMARY (Sticky & Color-Coded) ================= */}
              <div
                id="live-conservation-summary"
                className={`p-4 rounded-xl border transition-all ${
                  !isVerifiedCountValid || verifiedTotal === 0
                    ? 'bg-neutral-50 dark:bg-neutral-800/60 border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300'
                    : remainingCount > 0
                    ? 'bg-amber-500/10 border-amber-500/30 text-amber-700 dark:text-amber-300'
                    : remainingCount < 0
                    ? 'bg-rose-500/10 border-rose-500/30 text-rose-700 dark:text-rose-300'
                    : 'bg-emerald-500/10 border-emerald-500/30 text-emerald-700 dark:text-emerald-300'
                }`}
              >
                <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                  <div>
                    <div className="flex items-center gap-2">
                      <span className="text-xs font-bold uppercase tracking-wider">
                        Live Conservation Summary
                      </span>
                      <span
                        className={`text-[11px] font-bold px-2 py-0.5 rounded-full ${
                          !isVerifiedCountValid || verifiedTotal === 0
                            ? 'bg-neutral-200 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-400'
                            : remainingCount > 0
                            ? 'bg-amber-500/20 text-amber-700 dark:text-amber-300'
                            : remainingCount < 0
                            ? 'bg-rose-500/20 text-rose-700 dark:text-rose-300'
                            : 'bg-emerald-500/20 text-emerald-700 dark:text-emerald-300'
                        }`}
                      >
                        {!isVerifiedCountValid || verifiedTotal === 0
                          ? 'Awaiting Verified Count'
                          : remainingCount > 0
                          ? `${remainingCount} Units Unallocated`
                          : remainingCount < 0
                          ? `Over-allocated by ${Math.abs(remainingCount)}`
                          : 'Status: Balanced'}
                      </span>
                    </div>
                    <p className="text-xs opacity-90 mt-0.5">
                      Sum allocated across all active stores must exactly equal verified physical quantity.
                    </p>
                  </div>

                  {/* Metrics Row */}
                  <div className="flex items-center gap-4 text-xs font-mono">
                    <div className="text-center">
                      <span className="block text-[10px] text-neutral-400 uppercase">Verified Total</span>
                      <span className="font-bold text-sm text-neutral-900 dark:text-white">
                        {isVerifiedCountValid ? verifiedTotal : '—'}
                      </span>
                    </div>
                    <span className="text-neutral-400 font-sans font-bold">=</span>
                    <div className="text-center">
                      <span className="block text-[10px] text-neutral-400 uppercase">Allocated</span>
                      <span className="font-bold text-sm text-neutral-900 dark:text-white">{totalAllocatedNum}</span>
                    </div>
                    <span className="text-neutral-400 font-sans font-bold">+</span>
                    <div className="text-center">
                      <span className="block text-[10px] text-neutral-400 uppercase">Remaining</span>
                      <span
                        className={`font-bold text-sm ${
                          remainingCount === 0 && verifiedTotal > 0
                            ? 'text-emerald-600 dark:text-emerald-400'
                            : remainingCount > 0
                            ? 'text-amber-600 dark:text-amber-400'
                            : 'text-rose-600 dark:text-rose-400'
                        }`}
                      >
                        {remainingCount}
                      </span>
                    </div>
                  </div>
                </div>

                {/* Validation message banner if any blocker exists */}
                {activeStores.some(st => !isStoreMapped(st)) && (
                  <div className="mt-2 pt-2 border-t border-amber-500/20 text-xs text-amber-700 dark:text-amber-300 flex items-center gap-1.5">
                    <AlertTriangle className="w-3.5 h-3.5 shrink-0" />
                    <span>Unmapped store accounts are excluded from this allocation. Map the SKU later, then use Stock Transfers to assign stock.</span>
                  </div>
                )}
                {hasReservationConflict && (
                  <div className="mt-2 pt-2 border-t border-rose-500/20 text-xs text-rose-700 dark:text-rose-300 flex items-center gap-1.5">
                    <AlertCircle className="w-3.5 h-3.5 shrink-0" />
                    <span>One or more stores have an allocation lower than existing customer order reservations.</span>
                  </div>
                )}
              </div>

              {/* Section 2: Store Allocation Accounts */}
              <div className="space-y-3">
                <div className="flex items-center justify-between">
                  <div>
                    <h4 className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider">
                      Allocate Stock
                    </h4>
                    <p className="text-xs text-neutral-500 dark:text-neutral-400">
                      Specify stock ledger balance for each connected store inventory account.
                    </p>
                  </div>

                  {/* Compact Quick Actions */}
                  <div className="flex items-center gap-1.5">
                    <button
                      type="button"
                      onClick={() => handleAssignRemaining('website')}
                      disabled={!isVerifiedCountValid || remainingCount <= 0}
                      className="px-2.5 py-1 rounded-lg text-xs font-semibold bg-neutral-100 dark:bg-neutral-800 hover:bg-neutral-200 dark:hover:bg-neutral-700 text-neutral-800 dark:text-neutral-200 border border-neutral-200 dark:border-neutral-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
                      title="Assign unallocated remainder to JW Storefront"
                    >
                      Assign Remaining
                    </button>
                    <button
                      type="button"
                      onClick={handleDistributeEvenly}
                      disabled={!isVerifiedCountValid || verifiedTotal <= 0}
                      className="px-2.5 py-1 rounded-lg text-xs font-semibold bg-neutral-100 dark:bg-neutral-800 hover:bg-neutral-200 dark:hover:bg-neutral-700 text-neutral-800 dark:text-neutral-200 border border-neutral-200 dark:border-neutral-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
                    >
                      Distribute Evenly
                    </button>
                    <button
                      type="button"
                      onClick={handleSetZero}
                      className="px-2.5 py-1 rounded-lg text-xs font-medium text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 cursor-pointer"
                    >
                      Set 0
                    </button>
                  </div>
                </div>

                {/* Store Rows List */}
                <div className="space-y-2">
                  {activeStores.map(st => {
                    const isUnmapped = !isStoreMapped(st);
                    const connectionStatus = st.connectionState;
                    const isConnectionExpired = connectionStatus === 'expired';
                    const isInputDisabled = isUnmapped || isConnectionExpired;

                    const balanceRecord = skuStoreBalances[st.id];
                    const reserved = balanceRecord?.reservedStock ?? 0;
                    const currentPhysical = balanceRecord?.physicalStock ?? null;
                    const currentAvail = balanceRecord?.availableStock ?? null;

                    const enteredVal = allocations[st.id] ?? '';
                    const parsedNum = parsedAllocations[st.id] ?? 0;
                    const resultingAvail = enteredVal.trim() === '' ? '—' : Math.max(0, parsedNum - reserved);
                    const isConflict = storeReservationConflicts[st.id] !== undefined;

                    return (
                      <div
                        key={st.id}
                        className={`p-3.5 rounded-xl border transition-all ${
                          isConflict
                            ? 'bg-rose-500/5 border-rose-500/40 dark:border-rose-500/40'
                            : isUnmapped
                            ? 'bg-neutral-50 dark:bg-neutral-800/40 border-neutral-200 dark:border-neutral-800 opacity-90'
                            : 'bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-800'
                        }`}
                      >
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                          {/* Store Identity */}
                          <div className="flex items-center gap-3 min-w-0">
                            <div className="w-8 h-8 rounded-lg bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center shrink-0 border border-neutral-200 dark:border-neutral-700">
                              <MarketplaceLogo platform={st.platform} size="xs" />
                            </div>
                            <div className="min-w-0">
                              <div className="flex items-center gap-2">
                                <span className="font-semibold text-xs text-neutral-900 dark:text-white truncate">
                                  {st.nickname || st.name}
                                </span>
                                <span className="text-[10px] px-1.5 py-0.5 rounded bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400">
                                  {st.platformDisplayName}
                                </span>
                                {isUnmapped ? (
                                  <span className="text-[10px] font-semibold px-2 py-0.5 rounded bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                    SKU Not Mapped
                                  </span>
                                ) : isConnectionExpired ? (
                                  <span className="text-[10px] font-semibold px-2 py-0.5 rounded bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
                                    Auth Expired
                                  </span>
                                ) : (
                                  <span className="text-[10px] font-medium text-emerald-600 dark:text-emerald-400">
                                    Mapped
                                  </span>
                                )}
                              </div>
                              <p className="text-[11px] text-neutral-500 truncate max-w-sm">
                                {st.name}
                              </p>
                            </div>
                          </div>

                          {/* Stocks & Input Control */}
                          <div className="flex items-center justify-between sm:justify-end gap-4 shrink-0">
                            {/* Previous & Reserved Indicators */}
                            <div className="text-right text-xs font-mono space-y-0.5 hidden sm:block">
                              <div className="text-neutral-400 text-[10px]">
                                Prev Phys:{' '}
                                <span className="text-neutral-700 dark:text-neutral-300">
                                  {currentPhysical !== null ? `${currentPhysical}u` : '—'}
                                </span>
                              </div>
                              <div className="text-neutral-400 text-[10px]">
                                Reserved:{' '}
                                <span className="font-bold text-neutral-800 dark:text-neutral-200">
                                  {reserved}u
                                </span>
                              </div>
                            </div>

                            {/* Resulting Available Preview */}
                            <div className="text-right font-mono min-w-[70px]">
                              <span className="block text-[10px] text-neutral-400 uppercase">Available (ATP)</span>
                              <span
                                className={`text-xs font-bold ${
                                  resultingAvail === '—'
                                    ? 'text-neutral-400'
                                    : 'text-emerald-600 dark:text-emerald-400'
                                }`}
                              >
                                {resultingAvail} {resultingAvail !== '—' ? 'u' : ''}
                              </span>
                            </div>

                            {/* Allocation Input */}
                            <div className="w-28 relative">
                              <input
                                type="number"
                                min="0"
                                step="1"
                                disabled={isInputDisabled}
                                placeholder={isInputDisabled ? '—' : '0'}
                                value={enteredVal}
                                onChange={e => {
                                  const val = e.target.value;
                                  setAllocations(prev => ({
                                    ...prev,
                                    [st.id]: val
                                  }));
                                }}
                                className={`w-full px-2.5 py-1.5 rounded-lg border font-mono text-sm font-bold text-right transition-colors focus:outline-hidden ${
                                  isConflict
                                    ? 'border-rose-500 bg-rose-500/10 text-rose-600 dark:text-rose-400 focus:ring-2 focus:ring-rose-500'
                                    : isInputDisabled
                                    ? 'border-neutral-200 dark:border-neutral-700 bg-neutral-100 dark:bg-neutral-800/60 text-neutral-400 cursor-not-allowed'
                                    : 'border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white focus:ring-2 focus:ring-neutral-900 dark:focus:ring-white'
                                }`}
                              />
                            </div>
                          </div>
                        </div>

                        {/* Store Helper State if unmapped or conflict */}
                        {isUnmapped && (
                          <div className="mt-2.5 pt-2 border-t border-neutral-200 dark:border-neutral-700/60 flex items-center justify-between gap-2 text-[11px] text-amber-700 dark:text-amber-300/90">
                            <div className="flex items-center gap-1.5">
                              <AlertTriangle className="w-3.5 h-3.5 shrink-0 text-amber-500" />
                              <span>Map this store listing to the JW Master SKU before allocating stock.</span>
                            </div>
                            <button
                              type="button"
                              onClick={() => {
                                if (onNavigateToSkuMapping) {
                                  onNavigateToSkuMapping();
                                } else {
                                  // Quick simulation toggle
                                  // Mapping remains unchanged until the mapping workflow persists it.
                                }
                              }}
                              className="px-2 py-0.5 rounded border border-amber-500/30 hover:bg-amber-500/20 text-amber-600 dark:text-amber-300 font-semibold cursor-pointer shrink-0"
                            >
                              Go to SKU Mapping
                            </button>
                          </div>
                        )}

                        {isConnectionExpired && (
                          <div className="mt-2.5 pt-2 border-t border-neutral-200 dark:border-neutral-700/60 flex items-center justify-between gap-2 text-[11px] text-rose-600 dark:text-rose-400">
                            <div className="flex items-center gap-1.5">
                              <ShieldAlert className="w-3.5 h-3.5 shrink-0" />
                              <span>OAuth authorization expired. Reconnect store before stock can be broadcast.</span>
                            </div>
                            <span className="text-[10px] text-neutral-400">Settings &gt; Integrations</span>
                          </div>
                        )}

                        {isConflict && (
                          <div className="mt-2.5 pt-2 border-t border-rose-500/20 flex items-center gap-1.5 text-[11px] text-rose-600 dark:text-rose-400 font-medium">
                            <AlertCircle className="w-3.5 h-3.5 shrink-0" />
                            <span>
                              Allocation ({enteredVal}) is less than reserved stock ({reserved}). Cannot reduce stock below active customer reservations.
                            </span>
                          </div>
                        )}
                      </div>
                    );
                  })}
                </div>

                {/* Coming Soon Stores (Read-Only) */}
                {comingSoonStores.length > 0 && (
                  <div className="pt-2 border-t border-neutral-200/60 dark:border-neutral-800/60 space-y-2">
                    <span className="text-[11px] text-neutral-400 uppercase tracking-wider font-semibold">
                      Supported Soon (Non-participating)
                    </span>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 opacity-60">
                      {comingSoonStores.map(cs => (
                        <div
                          key={cs.id}
                          className="p-2.5 rounded-lg bg-neutral-100 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-800 flex items-center justify-between text-xs"
                        >
                          <div className="flex items-center gap-2">
                            <MarketplaceLogo platform={cs.platform} size="xs" />
                            <span className="text-neutral-700 dark:text-neutral-300 font-medium">{cs.name}</span>
                          </div>
                          <span className="text-[10px] px-1.5 py-0.5 rounded bg-neutral-200 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-400 font-semibold">
                            Coming Soon
                          </span>
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </div>

              {/* Operator, Reason & Notes */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-neutral-200 dark:border-neutral-800 text-xs">
                <div>
                  <label
                    htmlFor="operator-name-input"
                    className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1"
                  >
                    Operator / Staff Name <span className="text-rose-500">*</span>
                  </label>
                  <input
                    id="operator-name-input"
                    type="text"
                    value={operator}
                    onChange={e => setOperator(e.target.value)}
                    className="w-full px-3 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white"
                  />
                </div>
                <div>
                  <label
                    htmlFor="audit-reason-select"
                    className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1"
                  >
                    Audit Reason <span className="text-rose-500">*</span>
                  </label>
                  <select
                    id="audit-reason-select"
                    value={reason}
                    onChange={e => setReason(e.target.value)}
                    className="w-full px-3 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white"
                  >
                    <option value="Initial Store Physical Count">Initial Store Physical Count</option>
                    <option value="Periodic Cycle Count">Periodic Cycle Count</option>
                    <option value="Channel Re-balancing">Channel Re-balancing</option>
                    <option value="Post-intake Verification">Post-intake Verification</option>
                  </select>
                </div>
                <div className="sm:col-span-2">
                  <label
                    htmlFor="internal-notes-input"
                    className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1"
                  >
                    Internal Notes (Optional)
                  </label>
                  <input
                    id="internal-notes-input"
                    type="text"
                    placeholder="e.g. Verified physical batch in shelf A3; allocated across webstore and active Shopee stores"
                    value={notes}
                    onChange={e => setNotes(e.target.value)}
                    className="w-full px-3 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white"
                  />
                </div>
              </div>

              {/* Action Buttons */}
              <div className="pt-3 flex items-center justify-between border-t border-neutral-200 dark:border-neutral-800">
                <button
                  type="button"
                  onClick={onClose}
                  className="px-4 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 text-xs font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="button"
                  disabled={!canProceedToReview}
                  onClick={handleProceedToReview}
                  className="px-6 py-2.5 rounded-xl bg-neutral-900 hover:bg-neutral-800 dark:bg-white dark:hover:bg-neutral-100 text-white dark:text-neutral-950 text-xs font-bold transition-all shadow-xs flex items-center gap-2 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                >
                  <span>Proceed to Review</span>
                  <ChevronRight className="w-4 h-4" />
                </button>
              </div>
            </div>
          )}

          {/* ================= STEP 2: REVIEW ================= */}
          {step === 'review' && (
            <div className="space-y-5">
              {/* Product and Verified Total Card */}
              <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
                <div>
                  <span className="font-mono text-xs font-bold text-neutral-900 dark:text-white block">
                    {item.masterSku}
                  </span>
                  <h4 className="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                    {item.productName} ({item.variantName || 'Standard'})
                  </h4>
                </div>
                <div className="text-right">
                  <span className="text-neutral-400 block text-[11px] uppercase">Verified Total Physical</span>
                  <span className="font-mono font-bold text-lg text-neutral-900 dark:text-white">
                    {verifiedCountNum} units
                  </span>
                </div>
              </div>

              {/* Store Breakdown Table */}
              <div className="border border-neutral-200 dark:border-neutral-800 rounded-xl overflow-hidden">
                <table className="w-full text-left text-xs">
                  <thead className="bg-neutral-100 dark:bg-neutral-800/80 text-neutral-600 dark:text-neutral-400 font-semibold border-b border-neutral-200 dark:border-neutral-800">
                    <tr>
                      <th className="py-2.5 px-3">Store Account</th>
                      <th className="py-2.5 px-3 text-right">Prev Phys</th>
                      <th className="py-2.5 px-3 text-right">New Allocation</th>
                      <th className="py-2.5 px-3 text-right">Reserved</th>
                      <th className="py-2.5 px-3 text-right">Resulting ATP</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800">
                    {activeStores.map(st => {
                      const balanceRecord = skuStoreBalances[st.id];
                      const prevPhys = balanceRecord?.physicalStock ?? '—';
                      const reserved = balanceRecord?.reservedStock ?? 0;
                      const newAlloc = parsedAllocations[st.id] ?? 0;
                      const atp = Math.max(0, newAlloc - reserved);

                      return (
                        <tr key={st.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/40">
                          <td className="py-2.5 px-3">
                            <div className="flex items-center gap-2">
                              <MarketplaceLogo platform={st.platform} size="xs" />
                              <span className="font-medium text-neutral-900 dark:text-white">
                                {st.nickname || st.name}
                              </span>
                            </div>
                          </td>
                          <td className="py-2.5 px-3 text-right font-mono text-neutral-500">
                            {prevPhys !== '—' ? `${prevPhys}u` : '—'}
                          </td>
                          <td className="py-2.5 px-3 text-right font-mono font-bold text-neutral-900 dark:text-white">
                            {newAlloc} units
                          </td>
                          <td className="py-2.5 px-3 text-right font-mono text-neutral-500">
                            {reserved} units
                          </td>
                          <td className="py-2.5 px-3 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">
                            {atp} units
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                  <tfoot className="bg-neutral-50 dark:bg-neutral-800/50 font-bold border-t border-neutral-200 dark:border-neutral-800">
                    <tr>
                      <td className="py-2.5 px-3 text-neutral-700 dark:text-neutral-300">Total Across Accounts</td>
                      <td className="py-2.5 px-3 text-right font-mono text-neutral-500">
                        {item.totalPhysicalStock !== null ? `${item.totalPhysicalStock}u` : '—'}
                      </td>
                      <td className="py-2.5 px-3 text-right font-mono text-neutral-900 dark:text-white">
                        {totalAllocatedNum} units
                      </td>
                      <td className="py-2.5 px-3 text-right font-mono text-neutral-500">
                        {totalReservedAcrossStores} units
                      </td>
                      <td className="py-2.5 px-3 text-right font-mono text-emerald-600 dark:text-emerald-400">
                        {Math.max(0, totalAllocatedNum - totalReservedAcrossStores)} units
                      </td>
                    </tr>
                  </tfoot>
                </table>
              </div>

              {/* High-Visibility Confirmation Banner */}
              <div className="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-between text-xs text-emerald-700 dark:text-emerald-300">
                <div className="flex items-center gap-2 font-bold">
                  <CheckCircle2 className="w-4 h-4 text-emerald-500 shrink-0" />
                  <span>Total physical stock is conserved.</span>
                </div>
                <span className="font-mono font-semibold">
                  Verified ({verifiedCountNum}) = Sum Allocated ({totalAllocatedNum})
                </span>
              </div>

              {/* Atomic Ledger Transaction Notice */}
              <div className="p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-800 text-xs space-y-1 text-neutral-600 dark:text-neutral-400">
                <div className="flex items-center gap-1.5 font-semibold text-neutral-800 dark:text-neutral-200">
                  <ShieldAlert className="w-3.5 h-3.5 text-neutral-500" />
                  <span>Atomic Ledger Commit</span>
                </div>
                <p>
                  Confirming writes all store balances as one atomic operation. Physical units cannot be duplicated across multiple stores.
                </p>
                <div className="pt-2 grid grid-cols-2 gap-2 text-[11px] border-t border-neutral-200/60 dark:border-neutral-700/60">
                  <div>
                    <span className="text-neutral-400">Operator: </span>
                    <span className="font-medium text-neutral-700 dark:text-neutral-300">{operator}</span>
                  </div>
                  <div>
                    <span className="text-neutral-400">Reason: </span>
                    <span className="font-medium text-neutral-700 dark:text-neutral-300">{reason}</span>
                  </div>
                  {notes && (
                    <div className="col-span-2">
                      <span className="text-neutral-400">Notes: </span>
                      <span className="italic text-neutral-700 dark:text-neutral-300">{notes}</span>
                    </div>
                  )}
                </div>
              </div>

              {/* Review Buttons */}
              {saveError && <div role="alert" className="rounded-xl border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-xs font-semibold text-rose-600 dark:text-rose-300">{saveError}</div>}
              <div className="pt-3 flex items-center justify-between border-t border-neutral-200 dark:border-neutral-800">
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
                  className="px-6 py-2.5 rounded-xl bg-neutral-900 hover:bg-neutral-800 dark:bg-white dark:hover:bg-neutral-100 text-white dark:text-neutral-950 text-xs font-bold transition-all shadow-xs flex items-center gap-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <CheckCircle2 className="w-4 h-4" />
                  <span>{isSaving ? 'Allocating…' : 'Confirm & Allocate Stock'}</span>
                </button>
              </div>
            </div>
          )}

          {/* ================= STEP 3: SUCCESS ================= */}
          {step === 'success' && (
            <div className="py-4 space-y-6">
              <div className="text-center space-y-2">
                <div className="w-12 h-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-500 mx-auto flex items-center justify-center">
                  <CheckCircle2 className="w-7 h-7" />
                </div>
                <h4 className="text-base font-bold text-neutral-900 dark:text-white">
                  Stock Allocation Completed
                </h4>
                <p className="text-xs text-neutral-500 dark:text-neutral-400 max-w-md mx-auto">
                  Master SKU <strong>{item.masterSku}</strong> verified at <strong>{verifiedCountNum} units</strong>. Store stock accounts initialized across active sales channels.
                </p>
              </div>

              {/* Separation of Concerns: 1. JW Central Ledger vs 2. Marketplace Sync Broadcast */}
              <div className="grid grid-cols-1 gap-3">
                {/* 1. JW Central Ledger State */}
                <div className="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-start gap-3 text-xs">
                  <CheckCircle2 className="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" />
                  <div className="space-y-0.5">
                    <span className="font-bold text-emerald-700 dark:text-emerald-300 block">
                      1. JW Central Inventory Ledger: Updated & Conserved
                    </span>
                    <p className="text-emerald-800/80 dark:text-emerald-200/80">
                      Atomic ledger transaction succeeded. {verifiedCountNum} physical units partitioned across independent store balances with complete audit trail.
                    </p>
                  </div>
                </div>

                {/* 2. Platform Broadcast Status */}
                <div className="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-800 space-y-2.5 text-xs">
                  <div className="flex items-center justify-between">
                    <span className="font-bold text-neutral-900 dark:text-white">
                      2. Marketplace ATP Broadcast Status
                    </span>
                  </div>

                  <div className="space-y-1.5">
                    {activeStores.map(st => {
                      const stage = syncStages.find(entry => String(entry.store_id ?? 'website') === st.id);
                      const syncStatus = stage?.status || (st.platform === 'webstore' ? 'confirmed' : 'queued');
                      const allocQty = parsedAllocations[st.id] ?? 0;
                      const isFailed = ['failed', 'auth_expired', 'uncertain'].includes(syncStatus);
                      const isMapped = isStoreMapped(st);

                      return (
                        <div
                          key={st.id}
                          role={onOpenSyncDetails ? 'button' : undefined}
                          tabIndex={onOpenSyncDetails ? 0 : undefined}
                          onClick={onOpenSyncDetails ? () => onOpenSyncDetails(st) : undefined}
                          onKeyDown={onOpenSyncDetails ? event => {if(event.key==='Enter'||event.key===' ') {event.preventDefault();onOpenSyncDetails(st);}} : undefined}
                          className="p-2.5 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 flex items-center justify-between text-xs"
                        >
                          <div className="flex items-center gap-2">
                            <MarketplaceLogo platform={st.platform} size="xs" />
                            <span className="font-medium text-neutral-800 dark:text-neutral-200">
                              {st.nickname || st.name}
                            </span>
                          </div>

                          <div className="flex items-center gap-3">
                            <span className="font-mono text-neutral-500">{allocQty} units</span>
                            {!isMapped ? (
                              <span className="font-semibold text-amber-600 dark:text-amber-400">SKU Not Mapped · Excluded</span>
                            ) : isFailed ? (
                              <div className="flex items-center gap-2">
                                <span className="font-semibold text-rose-500 flex items-center gap-1">
                                  <AlertTriangle className="w-3.5 h-3.5" />
                                  {syncStatus}
                                </span>
                                {stage?.id && onRetrySync && (
                                  <button
                                    type="button"
                                    onClick={event => {event.stopPropagation();if(onOpenSyncDetails) onOpenSyncDetails(st);else void handleRetrySync(String(stage.id));}}
                                    disabled={retryingTaskId === String(stage.id)}
                                    className="px-2 py-1 rounded-lg border border-neutral-200 dark:border-neutral-700 text-[10px] font-semibold text-neutral-700 dark:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                                  >
                                    {retryingTaskId === String(stage.id) ? 'Retrying…' : 'Retry Platform Sync Only'}
                                  </button>
                                )}
                              </div>
                            ) : (
                              <span className="font-semibold text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                                <Check className="w-3.5 h-3.5" />
                                {syncStatus === 'confirmed' ? 'Pushed & Confirmed' : 'Queued for Sync'}
                              </span>
                            )}
                          </div>
                        </div>
                      );
                    })}
                  </div>

                  {syncStages.some(stage => ['failed', 'auth_expired', 'uncertain'].includes(stage.status)) && (
                    <div className="p-2.5 rounded-lg bg-amber-500/10 border border-amber-500/20 text-[11px] text-amber-700 dark:text-amber-300">
                      <strong>Important:</strong> A platform broadcast failure does not imply that the JW ledger allocation failed. Physical units remain securely allocated in the central ledger. You can retry pushing stock to failed channels at any time.
                    </div>
                  )}
                  {saveError && <div role="alert" className="rounded-lg border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-[11px] font-semibold text-rose-600 dark:text-rose-300">{saveError}</div>}
                </div>
              </div>

              {/* Action Buttons */}
              <div className="flex flex-wrap items-center justify-center gap-3 pt-2">
                {remainingUncounted.length > 0 && (
                  <button
                    type="button"
                    onClick={handleNextSku}
                    className="px-5 py-2.5 rounded-xl bg-neutral-900 hover:bg-neutral-800 dark:bg-white dark:hover:bg-neutral-100 text-white dark:text-neutral-950 text-xs font-bold transition-all shadow-xs flex items-center gap-2 cursor-pointer"
                  >
                    <span>Continue Next SKU ({remainingUncounted.length} left)</span>
                    <ChevronRight className="w-4 h-4" />
                  </button>
                )}

                <button
                  type="button"
                  onClick={onClose}
                  className="px-4 py-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-800 text-neutral-700 dark:text-neutral-300 text-xs font-semibold transition-colors cursor-pointer"
                >
                  Return to Inventory
                </button>

                {onNavigateToTransfers && (
                  <button
                    type="button"
                    onClick={onNavigateToTransfers}
                    className="px-4 py-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-800 text-neutral-700 dark:text-neutral-300 text-xs font-semibold transition-colors cursor-pointer"
                  >
                    View Stock Transfers
                  </button>
                )}
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};
