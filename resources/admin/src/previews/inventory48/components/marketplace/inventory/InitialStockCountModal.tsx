import React, { useState, useMemo, useEffect, useRef } from 'react';
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
  AlertCircle,
  Link2,
  Clock,
  Warehouse,
  ChevronDown,
  ChevronUp
} from 'lucide-react';
import { MarketplaceInventoryItem } from '../../../types';
import { InventoryStoreChannel, StoreStockAccountBalance } from './inventoryTypes';
import { MarketplaceLogo } from '../MarketplaceLogo';
import {
  StoreWithWarehouses,
  StorePlatformWarehouse,
  SkuWarehouseStockBalance
} from './warehouseInventoryTypes';
import {loadFacilityView} from './loadFacilityView';
import {storeInventoryApi} from '../../../../../integration/storeInventory';

const EMPTY_STORE_BALANCES: Record<string, Record<string, StoreStockAccountBalance>> = {};

interface InitialStockCountModalProps {
  isOpen: boolean;
  onClose: () => void;
  item: MarketplaceInventoryItem | null;
  mode: 'shared' | 'independent';
  stores: InventoryStoreChannel[];
  storeBalances?: Record<string, Record<string, StoreStockAccountBalance>>;
  initialTargetStoreId?: string;
  initialTargetWarehouseId?: string;
  onSaveCount: (payload: {
    sku: string;
    targetStoreId?: string;
    targetWarehouseId?: string;
    allocations?: Record<string, number>;
    physicalCount: number;
    reason: string;
    notes?: string;
    operator: string;
  }) => Promise<void>;
  uncountedQueue?: MarketplaceInventoryItem[];
  onSelectNextItem?: (nextItem: MarketplaceInventoryItem) => void;
  onNavigateToTransfers?: () => void;
  onNavigateToSkuMapping?: () => void;
  onNavigateToConnectStore?: () => void;
  onOpenStockSyncModal?: (item: MarketplaceInventoryItem, store: InventoryStoreChannel) => void;
}

export const InitialStockCountModal: React.FC<InitialStockCountModalProps> = ({
  isOpen,
  onClose,
  item,
  mode,
  stores,
  storeBalances = EMPTY_STORE_BALANCES,
  initialTargetStoreId,
  initialTargetWarehouseId,
  onSaveCount,
  uncountedQueue = [],
  onSelectNextItem,
  onNavigateToTransfers,
  onNavigateToSkuMapping,
  onNavigateToConnectStore,
  onOpenStockSyncModal
}) => {
  // Modal Step: 'input' (Count & Allocate) | 'review' (Review) | 'success' (Success)
  const [step, setStep] = useState<'input' | 'review' | 'success'>('input');
  const [confirmedCount,setConfirmedCount]=useState<number|null>(null);

  // Physical Count Source (Can be locked if pre-verified from shared pool or previous approved count)
  const [isCountLocked, setIsCountLocked] = useState<boolean>(false);
  const [physicalCountInput, setPhysicalCountInput] = useState<string>('');

  // Per-Store Allocation Inputs: storeId -> string representation for user typing
  const [allocations, setAllocations] = useState<Record<string, string>>({});

  // Per-Warehouse Allocation Inputs for multi-warehouse stores: warehouseId -> string representation
  const [warehouseAllocations, setWarehouseAllocations] = useState<Record<string, string>>({});

  // Expanded stores for warehouse drilldown
  const [expandedStores, setExpandedStores] = useState<Record<string, boolean>>({});

  // Store warehouse configurations
  const [storeConfigs, setStoreConfigs] = useState<StoreWithWarehouses[]>([]);
  const [warehouseStockData,setWarehouseStockData] = useState<Record<string, Record<string, SkuWarehouseStockBalance>>>({});
  const [providerReferences,setProviderReferences] = useState<Record<string,Record<string,number>>>({});
  const [providerReferenceFailures,setProviderReferenceFailures] = useState<Record<string,Record<string,string>>>({});
  const [providerReferenceLoading,setProviderReferenceLoading] = useState(false);
  const [selectedCountTarget,setSelectedCountTarget] = useState<{storeId:string;warehouseId:string}|null>(null);

  // Simulated mapping overrides for demo/edge-case handling
  const [unmappedStores, setUnmappedStores] = useState<Record<string, boolean>>({});

  // Simulated connection state overrides
  const [connectionOverrides, setConnectionOverrides] = useState<Record<string, 'connected' | 'expired'>>({});

  // Audit Fields
  const [operator, setOperator] = useState<string>('Current admin');
  const [reason, setReason] = useState<string>('Initial Store Physical Count');
  const [notes, setNotes] = useState<string>('');

  // Step 3 Platform Sync Simulation States: storeId -> 'confirmed' | 'syncing' | 'failed'
  const [platformSyncStatuses, setPlatformSyncStatuses] = useState<Record<string, 'confirmed' | 'syncing' | 'failed'>>({});
  const [isRetryingSync, setIsRetryingSync] = useState<boolean>(false);
  const [saveError,setSaveError] = useState<string>('');
  const initializedSession = useRef<string | null>(null);

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

  const mappedMarketplaceStores = useMemo(() => {
    const mappedIds = new Set((((item as any)?.mappedStoreIds || []) as string[]).map(String));
    return activeStores.filter(store => store.platform !== 'webstore' && mappedIds.has(String(store.id)));
  }, [activeStores, item]);

  // Calculate sum of reserved stock across all stores
  const totalReservedAcrossStores = useMemo(() => {
    return activeStores.reduce((sum, st) => {
      const b = skuStoreBalances[st.id];
      const r = b?.reservedStock ?? 0;
      return sum + r;
    }, 0);
  }, [activeStores, skuStoreBalances]);

  useEffect(() => {
    if (!isOpen) {
      initializedSession.current = null;
      setStep('input');
      setConfirmedCount(null);
      setSelectedCountTarget(initialTargetStoreId && initialTargetWarehouseId
        ? {storeId:initialTargetStoreId,warehouseId:initialTargetWarehouseId} : null);
    }
  }, [isOpen]);

  // Initialize or Reset form when modal opens or item changes
  useEffect(() => {
    if (isOpen && item && step !== 'success') {
      const session = `${item.id}:${initialTargetStoreId || ''}:${initialTargetWarehouseId || ''}`;
      if (initializedSession.current === session) return;
      initializedSession.current = session;
      setStep('input');
      setConfirmedCount(null);
      setOperator('Current admin');
      setReason(initialTargetWarehouseId ? 'Target Facility First Count' : 'Initial Store Physical Count');
      setNotes('');
      setUnmappedStores({});
      setConnectionOverrides({});

      // Initialize warehouse allocations
      const initialWhMap: Record<string, string> = {};
      storeConfigs.forEach(sc => {
        sc.warehouses.forEach(wh => {
          const bal = warehouseStockData[item.masterSku]?.[wh.id];
          if (bal && bal.physicalStock > 0) {
            initialWhMap[wh.id] = String(bal.physicalStock);
          } else {
            initialWhMap[wh.id] = '';
          }
        });
      });
      setWarehouseAllocations(initialWhMap);

      // If item already has an officially verified count, show as locked source total
      if (!initialTargetWarehouseId && item.totalPhysicalStock !== null && item.totalPhysicalStock !== undefined && item.totalPhysicalStock > 0) {
        setPhysicalCountInput(String(item.totalPhysicalStock));
        setIsCountLocked(true);
      } else {
        // Field MUST be blank for a new first count!
        setPhysicalCountInput('');
        setIsCountLocked(false);
      }

      // Initialize store-level allocations
      const initialMap: Record<string, string> = {};
      activeStores.forEach(st => {
        const sc = storeConfigs.find(c => c.storeId === st.id);
        if (sc && sc.warehouses.length > 0) {
          // If store has warehouses, sum the warehouse allocations
          const whSum = sc.warehouses.reduce((sum, wh) => {
            const num = Number(initialWhMap[wh.id] || 0);
            return sum + (isNaN(num) ? 0 : num);
          }, 0);
          initialMap[st.id] = whSum > 0 ? String(whSum) : '';
        } else {
          // No warehouse store (e.g. webstore)
          const b = skuStoreBalances[st.id];
          initialMap[st.id] = b && b.physicalStock > 0 ? String(b.physicalStock) : '';
        }
      });
      setAllocations(initialMap);
    }
  }, [isOpen, item?.id, activeStores, storeConfigs, warehouseStockData, initialTargetStoreId, initialTargetWarehouseId]);

  // Facility data can arrive after the form opens. Add its initial balances without
  // replacing quantities the operator has already entered in this session.
  useEffect(() => {
    if (!isOpen || !item) return;
    setWarehouseAllocations(previous => {
      let changed = false;
      const next = { ...previous };
      for (const store of storeConfigs) for (const warehouse of store.warehouses) {
        if (warehouse.id in next) continue;
        const balance = warehouseStockData[item.masterSku]?.[warehouse.id];
        next[warehouse.id] = balance && balance.physicalStock > 0 ? String(balance.physicalStock) : '';
        changed = true;
      }
      return changed ? next : previous;
    });
  }, [isOpen, item?.id, storeConfigs, warehouseStockData]);

  useEffect(()=>{
    if(!isOpen || !item) return;
    let active=true;
    loadFacilityView(item,stores,storeBalances).then(view=>{
      if(!active)return;
      setStoreConfigs(view.storeConfigs);setWarehouseStockData(view.warehouseBalances);
      setExpandedStores(Object.fromEntries(view.storeConfigs.map(store=>[store.storeId,true])));
    });
    return()=>{active=false};
  // Capture the facility view once per opened SKU. Parent inventory refreshes
  // must not restart this request or replace an in-progress count draft.
  },[isOpen,item?.id,initialTargetStoreId,initialTargetWarehouseId]);

  useEffect(()=>{
    if(!isOpen || !item || !storeConfigs.length) return;
    let active=true;
    const mapped=new Set((((item as any).mappedStoreIds || []) as string[]).map(String));
    const facilities=storeConfigs.flatMap(store=>
      mapped.has(String(store.storeId)) && store.warehouseSyncStatus==='synced'
        ? store.warehouses.filter(wh=>wh.inventoryWarehouseId && /^\d+$/.test(wh.id))
            .map(wh=>({storeId:String(store.storeId),warehouseId:wh.id})) : []);
    setProviderReferences({});
    setProviderReferenceFailures({});
    setProviderReferenceLoading(facilities.length>0);
    Promise.allSettled(facilities.map(async facility=>({
      ...facility,
      result:await storeInventoryApi.facilitySyncCheck(facility.storeId,Number(facility.warehouseId),(item as any).variantId)
    }))).then(results=>{
      if(!active)return;
      const next:Record<string,Record<string,number>>={};
      const failures:Record<string,Record<string,string>>={};
      results.forEach((result,index)=>{
        if(result.status!=='fulfilled'){
          const {storeId,warehouseId}=facilities[index];
          failures[storeId] ??= {};
          failures[storeId][warehouseId]=result.reason instanceof Error ? result.reason.message : 'Provider read unavailable';
          return;
        }
        const {storeId,warehouseId,result:detail}=result.value;
        const quantity=detail.marketplace_reported_stock;
        if(Number.isInteger(quantity) && quantity>=0){
          next[storeId] ??= {};
          next[storeId][warehouseId]=quantity;
        }
      });
      setProviderReferences(next);
      setProviderReferenceFailures(failures);
      setProviderReferenceLoading(false);
    });
    return()=>{active=false};
  },[isOpen,item?.id,storeConfigs]);

  if (!isOpen || !item) return null;

  const effectiveTargetStoreId=selectedCountTarget?.storeId || initialTargetStoreId;
  const effectiveTargetWarehouseId=selectedCountTarget?.warehouseId || initialTargetWarehouseId;

  // Numerical verified total calculation
  const verifiedCountNum = physicalCountInput.trim() === '' ? NaN : Number(physicalCountInput);
  const isVerifiedCountValid = !isNaN(verifiedCountNum) && verifiedCountNum >= 0 && Number.isInteger(verifiedCountNum);

  // Parse allocations and calculate total allocated across all warehouses and no-warehouse stores
  const parsedAllocations: Record<string, number> = {};
  const parsedWarehouseAllocations: Record<string, number> = {};
  let totalAllocatedNum = 0;
  let hasInvalidAllocationNumber = false;
  let hasNegativeAllocation = false;

  // Track warehouse-level reservation conflicts
  const warehouseReservationConflicts: Record<string, { allocated: number; reserved: number; whName: string; storeName: string }> = {};

  activeStores.forEach(st => {
    const sc = storeConfigs.find(c => c.storeId === st.id);
    const hasWarehouses = sc && sc.warehouses.length > 0;

    if (hasWarehouses) {
      let storeWhSum = 0;
      sc.warehouses.forEach(wh => {
        const rawWhVal = warehouseAllocations[wh.id] ?? '';
        const whBalance = warehouseStockData[item.masterSku]?.[wh.id];
        const whReserved = whBalance?.reservedStock ?? 0;

        if (rawWhVal.trim() === '') {
          parsedWarehouseAllocations[wh.id] = 0;
        } else {
          const num = Number(rawWhVal);
          if (isNaN(num) || !Number.isInteger(num)) {
            hasInvalidAllocationNumber = true;
          } else if (num < 0) {
            hasNegativeAllocation = true;
          } else {
            parsedWarehouseAllocations[wh.id] = num;
            storeWhSum += num;

            // Check reservation conflict for this warehouse
            if (num < whReserved) {
              warehouseReservationConflicts[wh.id] = {
                allocated: num,
                reserved: whReserved,
                whName: wh.warehouseName,
                storeName: st.nickname || st.name
              };
            }
          }
        }
      });
      parsedAllocations[st.id] = storeWhSum;
      totalAllocatedNum += storeWhSum;
    } else {
      // Store without registered platform warehouses (e.g. webstore)
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
    }
  });

  // Calculate live conservation metrics
  const verifiedTotal = isVerifiedCountValid ? verifiedCountNum : 0;
  const remainingCount = verifiedTotal - totalAllocatedNum;
  const netDifference = totalAllocatedNum - verifiedTotal;
  const isBalanced = isVerifiedCountValid && verifiedTotal > 0 && remainingCount === 0 && !hasInvalidAllocationNumber && !hasNegativeAllocation;

  // Check reserved stock violations per store (for non-warehouse stores)
  const storeReservationConflicts: Record<string, { allocated: number; reserved: number }> = {};
  let hasReservationConflict = Object.keys(warehouseReservationConflicts).length > 0;

  activeStores.forEach(st => {
    const sc = storeConfigs.find(c => c.storeId === st.id);
    if (!sc || sc.warehouses.length === 0) {
      const storeAlloc = parsedAllocations[st.id] ?? 0;
      const storeReserved = skuStoreBalances[st.id]?.reservedStock ?? 0;

      // If staff allocated fewer units than existing reserved orders for this store
      if (storeAlloc < storeReserved && allocations[st.id]?.trim() !== '') {
        storeReservationConflicts[st.id] = { allocated: storeAlloc, reserved: storeReserved };
        hasReservationConflict = true;
      }
    }
  });

  // Check verification pending stores blocker
  const verificationPendingStores = activeStores.filter(st => {
    const sc = storeConfigs.find(c => c.storeId === st.id);
    return sc?.syncStatus === 'verification_pending';
  });
  const hasVerificationPendingAttempt = verificationPendingStores.some(st => {
    const sc = storeConfigs.find(c => c.storeId === st.id);
    if (sc && sc.warehouses.length > 0) {
      return sc.warehouses.some(wh => (warehouseAllocations[wh.id]?.trim() || '') !== '');
    }
    return (allocations[st.id]?.trim() || '') !== '';
  });

  // Check unmapped active stores blocker
  const hasUnmappedBlocker = activeStores.some(st => {
    const mapped = storeConfigs.find(config => config.storeId === st.id)?.isSkuMapped;
    return !mapped && (allocations[st.id]?.trim() || '') !== '';
  });

  // Check connection blocker
  const hasConnectionBlocker = activeStores.some(st => {
    const conn = connectionOverrides[st.id] || st.connectionState;
    return conn === 'expired' && (allocations[st.id]?.trim() !== '' && (parsedAllocations[st.id] ?? 0) > 0);
  });

  // Validation flag for Proceed to Review
  const targetStore=storeConfigs.find(store=>store.storeId===effectiveTargetStoreId);
  const targetWarehouse=targetStore?.warehouses.find(warehouse=>warehouse.id===effectiveTargetWarehouseId);
  const targetBalance=effectiveTargetWarehouseId&&item
    ? warehouseStockData[item.masterSku]?.[effectiveTargetWarehouseId] : undefined;
  const targetedCountReady=!!effectiveTargetWarehouseId && !!targetStore
    && targetStore.warehouseSyncStatus==='synced' && targetStore.isSkuMapped
    && (effectiveTargetWarehouseId.startsWith('direct:') || (targetWarehouse?.status==='active' && !!targetWarehouse.inventoryWarehouseId))
    && targetBalance?.isCounted===false
    && !providerReferenceLoading
    && !providerReferenceFailures[String(effectiveTargetStoreId)]?.[String(effectiveTargetWarehouseId)];
  const countedFacilityTotal=storeConfigs.reduce((sum,store)=>sum+store.warehouses.reduce((warehouseSum,warehouse)=>{
    const balance=warehouseStockData[item.masterSku]?.[warehouse.id];
    return warehouseSum+(balance?.isCounted ? balance.physicalStock || 0 : 0);
  },0),0);
  const draftFacilityTotal=countedFacilityTotal+(isVerifiedCountValid ? verifiedCountNum : 0);
  const canProceedToReview = initialTargetWarehouseId
    ? targetedCountReady && isVerifiedCountValid && verifiedCountNum >= 0
    :
    isVerifiedCountValid &&
    verifiedCountNum > 0 &&
    isBalanced &&
    !hasInvalidAllocationNumber &&
    !hasNegativeAllocation &&
    !hasReservationConflict &&
    !hasUnmappedBlocker &&
    !hasConnectionBlocker &&
    !hasVerificationPendingAttempt;

  // Toggle store accordion
  const handleToggleStoreExpand = (storeId: string) => {
    setExpandedStores(prev => ({
      ...prev,
      [storeId]: !prev[storeId]
    }));
  };

  // Warehouse input change handler
  const handleWarehouseAllocationChange = (storeId: string, warehouseId: string, val: string) => {
    if(initialTargetWarehouseId){
      setSelectedCountTarget({storeId,warehouseId});
      setPhysicalCountInput(val);
    }
    setWarehouseAllocations(prev => {
      const updated = { ...prev, [warehouseId]: val };
      // Also update store total in allocations
      const sc = storeConfigs.find(c => c.storeId === storeId);
      if (sc) {
        const storeSum = sc.warehouses.reduce((acc, wh) => {
          const wVal = wh.id === warehouseId ? val : updated[wh.id] || '';
          const num = Number(wVal);
          return acc + (isNaN(num) ? 0 : num);
        }, 0);
        setAllocations(aPrev => ({ ...aPrev, [storeId]: storeSum > 0 ? String(storeSum) : '' }));
      }
      return updated;
    });
  };

  // Quick Action Helpers
  const handleAssignRemaining = (targetStoreId: string = 'webstore') => {
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
    const eligibleStores = activeStores.filter(s =>
      storeConfigs.find(config => config.storeId === s.id)?.isSkuMapped
      && s.connectionState !== 'expired');
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
    setSaveError('');
    try {
    await onSaveCount({
      sku: item.masterSku,
      targetStoreId: effectiveTargetStoreId,
      targetWarehouseId: effectiveTargetWarehouseId,
      allocations: initialTargetWarehouseId ? undefined : parsedAllocations,
      physicalCount: verifiedCountNum,
      reason,
      notes: notes.trim() || undefined,
      operator
    });

    // Ledger confirmation does not prove that a provider accepted or read back stock.
    setConfirmedCount(verifiedCountNum);
    setPlatformSyncStatuses({});
    setStep('success');
    } catch(error:any) {
      setSaveError(error?.message || 'Physical count could not be saved.');
    }
  };

  // Retry Platform Sync simulation in Step 3
  const handleRetryPlatformSyncOnly = async () => {
    setSaveError('Use the stock sync status panel to verify the provider response.');
  };

  // Queue progression
  const remainingUncounted = uncountedQueue.filter(q => q.id !== item.id);
  const successStores = initialTargetStoreId
    ? activeStores.filter(store => store.id === initialTargetStoreId) : activeStores;
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
                    {initialTargetWarehouseId
                      ? step === 'success' ? 'First Count Completed' : step === 'review' ? 'Review First Count' : 'First Count'
                      : step === 'success'
                      ? 'Stock Allocation Completed'
                      : step === 'review'
                      ? 'Review Stock Allocation'
                      : 'Allocate Stock'}
                  </h3>
                  <span className="text-[11px] font-semibold px-2 py-0.5 rounded-md bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 border border-neutral-200 dark:border-neutral-700">
                    {mode === 'shared' ? 'Shared Stock' : 'Independent Stock'}
                  </span>
                  <span className="text-[11px] text-neutral-400">
                    Step {step === 'input' ? (initialTargetWarehouseId ? '1 of 3: Count' : '1 of 3: Count & Allocate') : step === 'review' ? '2 of 3: Review' : '3 of 3: Success'}
                  </span>
                </div>
                <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                  {initialTargetWarehouseId ? 'Record a verified physical count for the selected facility only.' : 'Count this SKU once, then distribute the verified quantity across eligible store accounts.'}
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
                      Not Counted
                    </span>
                  )}
                </div>
              </div>

              {initialTargetWarehouseId && targetStore && (
                <div className="p-3 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800 text-xs text-indigo-900 dark:text-indigo-200 flex items-start gap-2.5">
                  <Warehouse className="w-4 h-4 text-indigo-600 dark:text-indigo-400 shrink-0 mt-0.5" />
                  <div>
                    <div className="font-bold">Specific Facility First Count Target</div>
                    <p className="text-neutral-600 dark:text-neutral-300 text-[11px] mt-0.5">
                      {targetStore.storeNickname || targetStore.storeName} · {targetWarehouse?.warehouseName || targetBalance?.warehouseName || 'Direct inventory'}
                      {targetWarehouse ? ` (${targetWarehouse.codeType}: ${targetWarehouse.codeValue})` : ''}. Other facilities remain unchanged.
                    </p>
                  </div>
                </div>
              )}

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
                  {mappedMarketplaceStores.map(store => {
                    const association = item.storeAssociations?.find(row => String(row.connectionId) === String(store.id));
                    return <div key={store.id} className="p-2.5 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 flex items-center justify-between gap-2">
                      <div className="flex items-center gap-2 min-w-0">
                        <MarketplaceLogo platform={store.platform} size="xs" />
                        <span className="text-neutral-700 dark:text-neutral-300 font-medium truncate">{store.nickname || store.name}</span>
                      </div>
                      <span className="font-mono font-bold text-neutral-800 dark:text-neutral-200 shrink-0">{
                        (()=>{
                          const config=storeConfigs.find(row=>row.storeId===store.id);
                          const facilities=config?.warehouses.filter(wh=>/^\d+$/.test(wh.id)) || [];
                          const observed=providerReferences[String(store.id)] || {};
                          if(facilities.length){
                            if(providerReferenceLoading)return 'Checking warehouses…';
                            const observedCount=facilities.filter(wh=>observed[wh.id]!==undefined).length;
                            return observedCount===facilities.length
                              ? `${facilities.reduce((sum,wh)=>sum+observed[wh.id],0)} units (${observedCount} WH)`
                              : `Read unavailable (${observedCount}/${facilities.length} WH checked)`;
                          }
                          return association?.channelStockSnapshot == null ? 'Unknown' : `${association.channelStockSnapshot} units`;
                        })()
                      }</span>
                    </div>;
                  })}
                  {mappedMarketplaceStores.length === 0 && <div className="p-2.5 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 text-neutral-500 dark:text-neutral-400">No mapped marketplace stores for this SKU.</div>}
                </div>
                {Object.keys(providerReferenceFailures).length>0 && (
                  <div className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-amber-500/30 bg-amber-500/10 p-2.5 text-[11px] text-amber-700 dark:text-amber-300">
                    <span>Platform stock read failed. The Lazada test access may have expired; stock operations for affected warehouses are paused.</span>
                    {onNavigateToConnectStore && <button type="button" onClick={onNavigateToConnectStore} className="font-bold underline">Renew Test Access in Connect Store</button>}
                  </div>
                )}
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
                      {initialTargetWarehouseId ? 'Verified Facility Physical Quantity' : 'Verified Total Physical Quantity'} <span className="text-rose-500">*</span>
                    </label>
                    <p className="text-xs text-neutral-500 dark:text-neutral-400">
                      {initialTargetWarehouseId
                        ? 'Count only the selected facility. Other facilities remain unchanged. Non-negative whole numbers only.'
                        : 'Actual physical stock counted in batch for master SKU. Non-negative whole numbers only.'}
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
                      onChange={e => {
                        setPhysicalCountInput(e.target.value);
                        if(initialTargetWarehouseId && effectiveTargetStoreId && effectiveTargetWarehouseId){
                          if(effectiveTargetWarehouseId.startsWith('direct:'))setAllocations(prev=>({...prev,[effectiveTargetStoreId]:e.target.value}));
                          else setWarehouseAllocations(prev=>({...prev,[effectiveTargetWarehouseId]:e.target.value}));
                        }
                      }}
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
                        {initialTargetWarehouseId
                          ? isVerifiedCountValid ? 'Selected warehouse draft' : 'Awaiting selected warehouse count'
                          : !isVerifiedCountValid || verifiedTotal === 0
                          ? 'Awaiting Verified Count'
                          : remainingCount > 0
                          ? `${remainingCount} Units Unallocated`
                          : remainingCount < 0
                          ? `Over-allocated by ${Math.abs(remainingCount)}`
                          : 'Status: Balanced'}
                      </span>
                    </div>
                    <p className="text-xs opacity-90 mt-0.5">
                      {initialTargetWarehouseId
                        ? 'Each warehouse has its own physical ledger. The selected warehouse is counted independently; all others remain unchanged.'
                        : 'Sum allocated across all active stores must exactly equal verified physical quantity.'}
                    </p>
                  </div>

                  {/* Metrics Row */}
                  <div className="flex items-center gap-4 text-xs font-mono">
                    <div className="text-center">
                      <span className="block text-[10px] text-neutral-400 uppercase">{initialTargetWarehouseId ? 'Counted Now' : 'Verified Total'}</span>
                      <span className="font-bold text-sm text-neutral-900 dark:text-white">
                        {isVerifiedCountValid ? verifiedTotal : '—'}
                      </span>
                    </div>
                    <span className="text-neutral-400 font-sans font-bold">{initialTargetWarehouseId ? '+' : '='}</span>
                    <div className="text-center">
                      <span className="block text-[10px] text-neutral-400 uppercase">{initialTargetWarehouseId ? 'Other Counted WHs' : 'Allocated'}</span>
                      <span className="font-bold text-sm text-neutral-900 dark:text-white">{initialTargetWarehouseId ? countedFacilityTotal : totalAllocatedNum}</span>
                    </div>
                    <span className="text-neutral-400 font-sans font-bold">{initialTargetWarehouseId ? '=' : '+'}</span>
                    <div className="text-center">
                      <span className="block text-[10px] text-neutral-400 uppercase">{initialTargetWarehouseId ? 'JW Draft Total' : 'Remaining'}</span>
                      <span
                        className={`font-bold text-sm ${
                          remainingCount === 0 && verifiedTotal > 0
                            ? 'text-emerald-600 dark:text-emerald-400'
                            : remainingCount > 0
                            ? 'text-amber-600 dark:text-amber-400'
                            : 'text-rose-600 dark:text-rose-400'
                        }`}
                      >
                        {initialTargetWarehouseId ? draftFacilityTotal : remainingCount}
                      </span>
                    </div>
                  </div>
                </div>

                {/* Validation message banner if any blocker exists */}
                {hasUnmappedBlocker && (
                  <div className="mt-2 pt-2 border-t border-amber-500/20 text-xs text-amber-700 dark:text-amber-300 flex items-center gap-1.5">
                    <AlertTriangle className="w-3.5 h-3.5 shrink-0" />
                    <span>Active store account has an unmapped SKU. Map store listing before completing allocation.</span>
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
                      {initialTargetWarehouseId ? 'Select one uncounted warehouse and enter its independent physical count.' : 'Specify stock ledger balance for each connected store inventory account.'}
                    </p>
                  </div>

                  {/* Compact Quick Actions */}
                  <div className={`${initialTargetWarehouseId ? 'hidden' : 'flex'} items-center gap-1.5`}>
                    <button
                      type="button"
                      onClick={() => handleAssignRemaining('webstore')}
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

                {/* Store Rows List with Multi-Warehouse & No-Warehouse Support */}
                <div className="space-y-3">
                  {/* Contextual Banner if opened for specific facility First Count */}
                  {initialTargetWarehouseId && (
                    <div className="p-3 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800 text-xs text-indigo-900 dark:text-indigo-200 flex items-start gap-2.5">
                      <Warehouse className="w-4 h-4 text-indigo-600 dark:text-indigo-400 shrink-0 mt-0.5" />
                      <div>
                        <div className="font-bold">Specific Facility First Count Target</div>
                        <p className="text-neutral-600 dark:text-neutral-300 text-[11px] mt-0.5">
                          Recording initial physical inventory for target platform warehouse. Other facilities maintain their current balances without silent drift.
                        </p>
                      </div>
                    </div>
                  )}

                  {activeStores.filter(st=>!initialTargetWarehouseId || st.platform==='webstore'
                    || mappedMarketplaceStores.some(mapped=>mapped.id===st.id)).map(st => {
                    const sc = storeConfigs.find(c => c.storeId === st.id);
                    const isVerificationPending = sc?.syncStatus === 'verification_pending';
                    const hasWarehouses = sc && sc.warehouses.length > 0;
                    const isExpanded = !!expandedStores[st.id];

                    const isUnmapped = sc?.isSkuMapped === false;
                    const connectionStatus = connectionOverrides[st.id] || st.connectionState;
                    const isConnectionExpired = connectionStatus === 'expired';
                    const isInputDisabled = isUnmapped || isConnectionExpired || isVerificationPending;

                    const balanceRecord = skuStoreBalances[st.id];
                    const storePhysical = parsedAllocations[st.id] ?? 0;
                    const storeReserved = hasWarehouses
                      ? sc.warehouses.reduce((acc, wh) => {
                          const wBal = warehouseStockData[item.masterSku]?.[wh.id];
                          return acc + (wBal?.reservedStock ?? 0);
                        }, 0)
                      : (balanceRecord?.reservedStock ?? 0);
                    const storeAvail = Math.max(0, storePhysical - storeReserved);

                    const isStoreConflict = !hasWarehouses && storeReservationConflicts[st.id] !== undefined;
                    const storeHasAnyWhConflict = hasWarehouses && sc.warehouses.some(wh => warehouseReservationConflicts[wh.id]);

                    return (
                      <div
                        key={st.id}
                        className={`rounded-xl border transition-all overflow-hidden ${
                          isStoreConflict || storeHasAnyWhConflict
                            ? 'bg-rose-500/5 border-rose-500/40 dark:border-rose-500/40'
                            : isVerificationPending
                            ? 'bg-amber-500/5 border-amber-500/30 dark:border-amber-500/30'
                            : isUnmapped
                            ? 'bg-neutral-50 dark:bg-neutral-800/40 border-neutral-200 dark:border-neutral-800 opacity-90'
                            : 'bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-800'
                        }`}
                      >
                        {/* Store Header Row */}
                        <div className="p-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                          {/* Store Identity */}
                          <div className="flex items-center gap-3 min-w-0">
                            <div className="w-8 h-8 rounded-lg bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center shrink-0 border border-neutral-200 dark:border-neutral-700">
                              <MarketplaceLogo platform={st.platform} size="xs" />
                            </div>
                            <div className="min-w-0">
                              <div className="flex items-center gap-2 flex-wrap">
                                <span className="font-semibold text-xs text-neutral-900 dark:text-white truncate">
                                  {st.nickname || st.name}
                                </span>
                                <span className="text-[10px] px-1.5 py-0.5 rounded bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400">
                                  {st.platformDisplayName}
                                </span>
                                
                                {isVerificationPending ? (
                                  <span className="text-[10px] font-bold px-2 py-0.5 rounded bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 flex items-center gap-1">
                                    <AlertTriangle className="w-2.5 h-2.5" />
                                    Warehouse verification pending
                                  </span>
                                ) : hasWarehouses ? (
                                  <span className="text-[10px] font-semibold px-2 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 flex items-center gap-1">
                                    <Warehouse className="w-2.5 h-2.5" />
                                    {sc.warehouses.length} {sc.warehouses.length === 1 ? 'Warehouse' : 'Warehouses'}
                                  </span>
                                ) : (
                                  <span className="text-[10px] font-semibold px-2 py-0.5 rounded bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400">
                                    Direct Channel (No WH)
                                  </span>
                                )}

                                {isUnmapped && (
                                  <span className="text-[10px] font-semibold px-2 py-0.5 rounded bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                    SKU Not Mapped
                                  </span>
                                )}
                              </div>
                              <p className="text-[11px] text-neutral-500 truncate max-w-sm">
                                {st.name}
                              </p>
                            </div>
                          </div>

                          {/* Stocks & Actions */}
                          <div className="flex items-center justify-between sm:justify-end gap-3 shrink-0">
                            {/* Rollup Summary */}
                            <div className="text-right text-xs font-mono space-y-0.5">
                              <div className="text-[10px] text-neutral-400">
                                Total Phys: <span className="font-bold text-neutral-800 dark:text-neutral-200">{storePhysical}u</span>
                              </div>
                              <div className="text-[10px] text-neutral-400">
                                Res: <span className="text-neutral-600 dark:text-neutral-300">{storeReserved}u</span> | ATP: <span className="font-bold text-emerald-600 dark:text-emerald-400">{storeAvail}u</span>
                              </div>
                            </div>

                            {/* If store has warehouses, show Expand/Collapse Button */}
                            {hasWarehouses ? (
                              <button
                                type="button"
                                onClick={() => handleToggleStoreExpand(st.id)}
                                className="px-3 py-1.5 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-200 text-xs font-semibold hover:bg-neutral-100 dark:hover:bg-neutral-750 flex items-center gap-1.5 transition cursor-pointer"
                              >
                                <span>{isExpanded ? 'Hide WHs' : `Expand (${sc.warehouses.length})`}</span>
                                {isExpanded ? <ChevronUp className="w-3.5 h-3.5" /> : <ChevronDown className="w-3.5 h-3.5" />}
                              </button>
                            ) : (
                              /* No warehouse: Direct Input */
                              <div className="w-28 relative">
                                <input
                                  type="number"
                                  min="0"
                                  step="1"
                                  disabled={isInputDisabled || (!!initialTargetWarehouseId && !!warehouseStockData[item.masterSku]?.[`direct:${st.id}`]?.isCounted)}
                                  placeholder={isInputDisabled ? '—' : '0'}
                                  value={allocations[st.id] ?? ''}
                                  onChange={e => {
                                    const val = e.target.value;
                                    if(initialTargetWarehouseId){setSelectedCountTarget({storeId:st.id,warehouseId:`direct:${st.id}`});setPhysicalCountInput(val)}
                                    setAllocations(prev => ({
                                      ...prev,
                                      [st.id]: val
                                    }));
                                  }}
                                  className={`w-full px-2.5 py-1.5 rounded-lg border font-mono text-sm font-bold text-right transition-colors focus:outline-hidden ${
                                    isStoreConflict
                                      ? 'border-rose-500 bg-rose-500/10 text-rose-600 dark:text-rose-400 focus:ring-2 focus:ring-rose-500'
                                      : isInputDisabled
                                      ? 'border-neutral-200 dark:border-neutral-700 bg-neutral-100 dark:bg-neutral-800/60 text-neutral-400 cursor-not-allowed'
                                      : 'border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white focus:ring-2 focus:ring-neutral-900 dark:focus:ring-white'
                                  }`}
                                />
                              </div>
                            )}
                          </div>
                        </div>

                        {/* Verification Pending Warning Alert */}
                        {isVerificationPending && (
                          <div className="px-3.5 py-2.5 bg-amber-500/10 border-t border-amber-500/20 flex items-center gap-2 text-xs text-amber-800 dark:text-amber-200">
                            <AlertTriangle className="w-4 h-4 shrink-0 text-amber-600 dark:text-amber-400" />
                            <span>
                                    <strong>Warehouse verification pending: </strong>The platform API timed out or access is insufficient. Allocation and counting are paused; the store will not fall back to no-warehouse mode.
                            </span>
                          </div>
                        )}

                        {/* Expanded Warehouse Breakdown Section */}
                        {hasWarehouses && isExpanded && (
                          <div className="px-3.5 pb-3.5 pt-1 border-t border-neutral-200 dark:border-neutral-800/80 bg-neutral-50/50 dark:bg-neutral-950/30">
                            <div className="mb-2 flex items-center justify-between text-[11px] text-neutral-500">
                              <span>{initialTargetWarehouseId ? 'Enter a first count for one uncounted platform warehouse:' : 'Enter Physical Allocation per registered platform warehouse:'}</span>
                              <span className="font-mono text-[10px]">
                                Sum: <strong className="text-neutral-800 dark:text-neutral-200">{storePhysical}u</strong>
                              </span>
                            </div>

                            <div className="space-y-2">
                              {sc.warehouses.map(wh => {
                                const whBal = warehouseStockData[item.masterSku]?.[wh.id];
                                const whPrevPhys = whBal?.physicalStock ?? '—';
                                const whReserved = whBal?.reservedStock ?? 0;
                                const whEntered = warehouseAllocations[wh.id] ?? '';
                                const whParsed = parsedWarehouseAllocations[wh.id] ?? 0;
                                const whAtp = whEntered.trim() === '' ? '—' : Math.max(0, whParsed - whReserved);
                                const whConflict = warehouseReservationConflicts[wh.id];

                                return (
                                  <div
                                    key={wh.id}
                                    className={`p-2.5 rounded-lg border transition ${
                                      whConflict
                                        ? 'bg-rose-500/10 border-rose-500/40'
                                        : 'bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-800'
                                    }`}
                                  >
                                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                      {/* Warehouse Identity */}
                                      <div className="min-w-0">
                                        <div className="flex items-center gap-2">
                                          <Warehouse className="w-3.5 h-3.5 text-neutral-400 shrink-0" />
                                          <span className="font-semibold text-xs text-neutral-900 dark:text-white truncate">
                                            {wh.warehouseName}
                                          </span>
                                          {wh.isDefault && (
                                            <span className="text-[9px] px-1 py-0.5 rounded bg-neutral-100 dark:bg-neutral-800 text-neutral-500">
                                              Default
                                            </span>
                                          )}
                                        </div>
                                        <div className="flex items-center gap-2 mt-0.5 text-[10px] text-neutral-500 font-mono">
                                          <span>{wh.codeType}: <strong className="text-neutral-700 dark:text-neutral-300">{wh.codeValue}</strong></span>
                                          {wh.city && <span>• {wh.city}, {wh.state}</span>}
                                        </div>
                                      </div>

                                      {/* Warehouse Numbers & Input */}
                                      <div className="flex items-center justify-between sm:justify-end gap-3 shrink-0">
                                        <div className="text-right text-xs font-mono hidden sm:block">
                                          {initialTargetWarehouseId && (
                                            <div className="text-neutral-400 text-[10px]">
                                              Platform: <span className="text-neutral-600 dark:text-neutral-300">{
                                                providerReferenceLoading ? 'Checking…' : providerReferenceFailures[String(st.id)]?.[wh.id]
                                                  ? 'Read unavailable' : providerReferences[String(st.id)]?.[wh.id] === undefined
                                                    ? 'Unknown' : `${providerReferences[String(st.id)][wh.id]}u`
                                              }</span>
                                            </div>
                                          )}
                                          <div className="text-neutral-400 text-[10px]">
                                            Prev: <span className="text-neutral-600 dark:text-neutral-300">{whPrevPhys !== '—' ? `${whPrevPhys}u` : '—'}</span>
                                          </div>
                                          <div className="text-neutral-400 text-[10px]">
                                            Res: <span className="text-neutral-700 dark:text-neutral-200 font-bold">{whReserved}u</span>
                                          </div>
                                        </div>

                                        <div className="text-right font-mono min-w-[56px]">
                                          <span className="block text-[9px] text-neutral-400 uppercase">ATP</span>
                                          <span className={`text-xs font-bold ${whAtp === '—' ? 'text-neutral-400' : 'text-emerald-600 dark:text-emerald-400'}`}>
                                            {whAtp !== '—' ? `${whAtp}u` : '—'}
                                          </span>
                                        </div>

                                        <div className="w-24">
                                          <input
                                            type="number"
                                            min="0"
                                            step="1"
                                            disabled={isInputDisabled || (!!initialTargetWarehouseId && (whBal?.isCounted || wh.status!=='active' || !wh.inventoryWarehouseId))}
                                            placeholder={isInputDisabled ? '—' : '0'}
                                            value={whEntered}
                                            onChange={e => handleWarehouseAllocationChange(st.id, wh.id, e.target.value)}
                                            aria-label={`${st.nickname || st.name} ${wh.warehouseName} first count`}
                                            className={`w-full px-2 py-1 rounded-md border font-mono text-xs font-bold text-right transition-colors focus:outline-hidden ${
                                              whConflict
                                                ? 'border-rose-500 bg-rose-500/10 text-rose-600 dark:text-rose-400 focus:ring-1 focus:ring-rose-500'
                                                : isInputDisabled
                                                ? 'border-neutral-200 dark:border-neutral-700 bg-neutral-100 dark:bg-neutral-800/60 text-neutral-400 cursor-not-allowed'
                                                : 'border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white focus:ring-1 focus:ring-indigo-500'
                                            }`}
                                          />
                                        </div>
                                      </div>
                                    </div>

                                    {/* Warehouse reservation conflict alert */}
                                    {whConflict && (
                                      <div className="mt-1.5 pt-1.5 border-t border-rose-500/20 text-[11px] text-rose-600 dark:text-rose-400 flex items-center gap-1 font-medium">
                                        <AlertCircle className="w-3 h-3 shrink-0" />
                                        <span>
                                          Cannot reduce stock below reservations: reserved orders ({whConflict.reserved}u) &gt; allocation ({whConflict.allocated}u)
                                        </span>
                                      </div>
                                    )}
                                  </div>
                                );
                              })}
                            </div>
                          </div>
                        )}

                        {/* Unmapped state helper */}
                        {isUnmapped && (
                          <div className="p-2.5 border-t border-neutral-200 dark:border-neutral-700/60 flex items-center justify-between gap-2 text-[11px] text-amber-700 dark:text-amber-300/90">
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
                                  setUnmappedStores(prev => ({ ...prev, [st.id]: false }));
                                }
                              }}
                              className="px-2 py-0.5 rounded border border-amber-500/30 hover:bg-amber-500/20 text-amber-600 dark:text-amber-300 font-semibold cursor-pointer shrink-0"
                            >
                              Go to SKU Mapping
                            </button>
                          </div>
                        )}

                        {/* Store Reservation Conflict Alert (No-warehouse stores) */}
                        {isStoreConflict && (
                          <div className="p-2.5 border-t border-rose-500/20 flex items-center gap-1.5 text-[11px] text-rose-600 dark:text-rose-400 font-medium">
                            <AlertCircle className="w-3.5 h-3.5 shrink-0" />
                            <span>
                              Cannot reduce stock below reservations: reserved orders ({storeReserved}u) &gt; allocation ({parsedAllocations[st.id]}u)
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
              {initialTargetWarehouseId && (
                <div className="p-3 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800 text-xs text-indigo-900 dark:text-indigo-200">
                  First count applies only to {targetWarehouse?.name || targetStore?.storeName || 'the selected account'}.
                  Other facilities keep their existing balances.
                </div>
              )}
              <div className={`${initialTargetWarehouseId ? 'hidden' : ''} border border-neutral-200 dark:border-neutral-800 rounded-xl overflow-hidden`}>
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
                    {successStores.map(st => {
                      const sc = storeConfigs.find(c => c.storeId === st.id);
                      const hasWarehouses = sc && sc.warehouses.length > 0;
                      const balanceRecord = skuStoreBalances[st.id];
                      const prevPhys = balanceRecord?.physicalStock ?? '—';
                      const reserved = hasWarehouses
                        ? sc.warehouses.reduce((acc, wh) => {
                            const wBal = warehouseStockData[item.masterSku]?.[wh.id];
                            return acc + (wBal?.reservedStock ?? 0);
                          }, 0)
                        : (balanceRecord?.reservedStock ?? 0);
                      const newAlloc = parsedAllocations[st.id] ?? 0;
                      const atp = Math.max(0, newAlloc - reserved);

                      return (
                        <React.Fragment key={st.id}>
                          <tr className="hover:bg-neutral-50 dark:hover:bg-neutral-800/40 bg-neutral-50/30 dark:bg-neutral-850/30 font-medium">
                            <td className="py-2.5 px-3">
                              <div className="flex items-center gap-2">
                                <MarketplaceLogo platform={st.platform} size="xs" />
                                <span className="font-semibold text-neutral-900 dark:text-white">
                                  {st.nickname || st.name}
                                </span>
                                {hasWarehouses && (
                                  <span className="text-[10px] px-1.5 py-0.5 rounded bg-neutral-200 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-400">
                                    {sc.warehouses.length} WHs
                                  </span>
                                )}
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

                          {/* Render warehouse breakdown if store has warehouses */}
                          {hasWarehouses && sc.warehouses.map(wh => {
                            const whBal = warehouseStockData[item.masterSku]?.[wh.id];
                            const whPrev = whBal?.physicalStock ?? '—';
                            const whRes = whBal?.reservedStock ?? 0;
                            const whAlloc = parsedWarehouseAllocations[wh.id] ?? 0;
                            const whAtp = Math.max(0, whAlloc - whRes);

                            return (
                              <tr key={wh.id} className="text-xs text-neutral-600 dark:text-neutral-400 bg-neutral-50/10 dark:bg-neutral-900/30">
                                <td className="py-1.5 pl-8 pr-3 font-mono text-[11px]">
                                  <div className="flex items-center gap-1.5">
                                    <Warehouse className="w-3 h-3 text-neutral-400 shrink-0" />
                                    <span>{wh.warehouseName}</span>
                                    <span className="text-[10px] text-neutral-400">({wh.codeValue})</span>
                                  </div>
                                </td>
                                <td className="py-1.5 px-3 text-right font-mono text-[11px] text-neutral-400">
                                  {whPrev !== '—' ? `${whPrev}u` : '—'}
                                </td>
                                <td className="py-1.5 px-3 text-right font-mono text-[11px] font-semibold text-neutral-800 dark:text-neutral-200">
                                  {whAlloc}u
                                </td>
                                <td className="py-1.5 px-3 text-right font-mono text-[11px] text-neutral-400">
                                  {whRes}u
                                </td>
                                <td className="py-1.5 px-3 text-right font-mono text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                                  {whAtp}u
                                </td>
                              </tr>
                            );
                          })}
                        </React.Fragment>
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
              <div className={`${initialTargetWarehouseId ? 'hidden' : ''} p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-between text-xs text-emerald-700 dark:text-emerald-300`}>
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
                  {initialTargetWarehouseId ? 'Confirming records the selected facility count only. Other facilities remain unchanged.' : 'Confirming writes all store balances as one atomic operation. Physical units cannot be duplicated across multiple stores.'}
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
              <div className="pt-3 flex items-center justify-between border-t border-neutral-200 dark:border-neutral-800">
                <button
                  type="button"
                  onClick={() => setStep('input')}
                  className="px-4 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 text-xs font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 cursor-pointer"
                >
                  Back to Edit
                </button>
              {saveError && <p role="alert" className="text-xs text-rose-500">{saveError}</p>}
              <button
                type="button"
                onClick={handleConfirmSave}
                  className="px-6 py-2.5 rounded-xl bg-neutral-900 hover:bg-neutral-800 dark:bg-white dark:hover:bg-neutral-100 text-white dark:text-neutral-950 text-xs font-bold transition-all shadow-xs flex items-center gap-2 cursor-pointer"
                >
                  <CheckCircle2 className="w-4 h-4" />
                  <span>{initialTargetWarehouseId ? 'Confirm First Count' : 'Confirm & Allocate Stock'}</span>
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
                  {initialTargetWarehouseId ? 'First Count Completed' : 'Stock Allocation Completed'}
                </h4>
                <p className="text-xs text-neutral-500 dark:text-neutral-400 max-w-md mx-auto">
                  Master SKU <strong>{item.masterSku}</strong> was recorded at <strong>{confirmedCount ?? verifiedCountNum} units</strong> in the JW ledger.
                </p>
              </div>

              {/* Separation of Concerns: 1. JW Central Ledger vs 2. Marketplace Sync Broadcast */}
              <div className="grid grid-cols-1 gap-3">
                {/* 1. JW Central Ledger State */}
                <div className="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-start gap-3 text-xs">
                  <CheckCircle2 className="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" />
                  <div className="space-y-0.5">
                    <span className="font-bold text-emerald-700 dark:text-emerald-300 block">
                      {initialTargetWarehouseId ? '1. Selected Facility Ledger: Updated' : '1. JW Central Inventory Ledger: Updated & Conserved'}
                    </span>
                    <p className="text-emerald-800/80 dark:text-emerald-200/80">
                      JW recorded {confirmedCount ?? verifiedCountNum} physical units for the selected count. Platform stock confirmation is tracked separately.
                    </p>
                  </div>
                </div>

                {/* 2. Platform Broadcast Status */}
                <div className="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-800 space-y-2.5 text-xs">
                  <div className="flex items-center justify-between">
                    <span className="font-bold text-neutral-900 dark:text-white">
                      2. Marketplace ATP Broadcast Status
                    </span>
                    {Object.values(platformSyncStatuses).some(st => st === 'failed') && (
                      <button
                        type="button"
                        disabled={isRetryingSync}
                        onClick={handleRetryPlatformSyncOnly}
                        className="px-2.5 py-1 rounded-md bg-neutral-900 hover:bg-neutral-800 dark:bg-white dark:hover:bg-neutral-100 text-white dark:text-neutral-950 font-bold text-[11px] flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
                      >
                        {isRetryingSync ? (
                          <RefreshCw className="w-3 h-3 animate-spin" />
                        ) : (
                          <RotateCcw className="w-3 h-3" />
                        )}
                        <span>Retry Platform Sync Only</span>
                      </button>
                    )}
                  </div>

                  <div className="space-y-1.5">
                    {successStores.map(st => {
                      const isWebstore = st.platform === 'webstore' || st.id === 'webstore';
                          const isUnmapped = !storeConfigs.find(config=>config.storeId===st.id)?.isSkuMapped;
                          const syncStatus = platformSyncStatuses[st.id] || (isWebstore ? 'saved' : 'not_sent');
                          const allocQty = initialTargetStoreId ? (confirmedCount ?? verifiedCountNum) : (parsedAllocations[st.id] ?? 0);

                      return (
                        <div
                          key={st.id}
                          onClick={() => onOpenStockSyncModal?.(item, st)}
                          title="Click to view Stock Sync Details"
                          className="p-2.5 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 flex items-center justify-between text-xs hover:border-neutral-400 dark:hover:border-neutral-600 transition cursor-pointer group"
                        >
                          <div className="flex items-center gap-2">
                            <MarketplaceLogo platform={st.platform} size="xs" />
                            <span className="font-medium text-neutral-800 dark:text-neutral-200 group-hover:text-neutral-900 dark:group-hover:text-white">
                              {st.nickname || st.name}
                            </span>
                          </div>

                          <div className="flex items-center gap-3">
                            <span className="font-mono text-neutral-500">{allocQty} units</span>
                            {isWebstore ? (
                              <span className="font-semibold text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                                <Check className="w-3.5 h-3.5" />
                                Saved
                              </span>
                            ) : isUnmapped ? (
                              <span className="font-semibold text-amber-600 dark:text-amber-400 flex items-center gap-1">
                                <Link2 className="w-3.5 h-3.5" />
                                SKU Not Mapped · Excluded
                              </span>
                            ) : syncStatus === 'confirmed' ? (
                              <span className="font-semibold text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                                <Check className="w-3.5 h-3.5" />
                                Confirmed
                              </span>
                            ) : syncStatus === 'syncing' ? (
                              <span className="font-semibold text-amber-600 dark:text-amber-400 flex items-center gap-1">
                                <Clock className="w-3.5 h-3.5 animate-spin" />
                                Provider confirmation pending
                              </span>
                            ) : syncStatus === 'failed' ? (
                              <span className="font-semibold text-rose-500 flex items-center gap-1">
                                <AlertTriangle className="w-3.5 h-3.5" />
                                Sync Failed
                              </span>
                            ) : (
                              <span className="font-semibold text-amber-600 dark:text-amber-400 flex items-center gap-1">
                                <Clock className="w-3.5 h-3.5" />
                                Provider status not checked
                              </span>
                            )}
                          </div>
                        </div>
                      );
                    })}
                  </div>

                  {Object.values(platformSyncStatuses).some(st => st === 'failed') && (
                    <div className="p-2.5 rounded-lg bg-amber-500/10 border border-amber-500/20 text-[11px] text-amber-700 dark:text-amber-300">
                      <strong>Important:</strong> A platform broadcast failure does not imply that the JW ledger allocation failed. Physical units remain securely allocated in the central ledger. You can retry pushing stock to failed channels at any time.
                    </div>
                  )}
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
