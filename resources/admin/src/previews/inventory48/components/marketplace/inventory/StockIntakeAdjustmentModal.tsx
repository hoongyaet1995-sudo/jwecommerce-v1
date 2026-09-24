import React, { useState, useEffect, useMemo, useRef } from 'react';
import {
  X,
  PlusCircle,
  Sliders,
  AlertTriangle,
  Info,
  CheckCircle2,
  Boxes,
  ArrowRight,
  ShieldAlert,
  Warehouse,
  RefreshCw,
  Lock,
  ExternalLink,
  ChevronDown,
  Check
} from 'lucide-react';
import { MarketplaceInventoryItem } from '../../../types';
import { InventoryStoreChannel } from './inventoryTypes';
import {
  StoreWithWarehouses,
  StorePlatformWarehouse,
  SkuWarehouseStockBalance
} from './warehouseInventoryTypes';
import {loadFacilityView} from './loadFacilityView';
import {storeInventoryApi} from '../../../../../integration/storeInventory';
import { MarketplaceLogo } from '../MarketplaceLogo';

interface StockIntakeAdjustmentModalProps {
  isOpen: boolean;
  onClose: () => void;
  item: MarketplaceInventoryItem | null;
  mode: 'shared' | 'independent';
  stores: InventoryStoreChannel[];
  storeBalances?: Record<string,Record<string,any>>;
  initialType?: 'intake' | 'adjustment';
  initialStoreId?: string;
  initialWarehouseId?: string;
  onSave: (payload: {
    sku: string;
    type: 'intake' | 'adjustment';
    targetStoreId?: string;
    targetWarehouseId?: string;
    targetWarehouseCode?: string;
    deltaQuantity: number;
    newPhysicalStock: number;
    reason: string;
    notes?: string;
    operator: string;
  }) => Promise<void>;
}

export const StockIntakeAdjustmentModal: React.FC<StockIntakeAdjustmentModalProps> = ({
  isOpen,
  onClose,
  item,
  mode,
  stores,
  storeBalances = {},
  initialType = 'intake',
  initialStoreId,
  initialWarehouseId,
  onSave
}) => {
  const [actionType, setActionType] = useState<'intake' | 'adjustment'>(initialType);
  const [quantityInput, setQuantityInput] = useState<string>('');
  const [selectedStoreId, setSelectedStoreId] = useState<string>(initialStoreId || '');
  const [selectedWarehouseId, setSelectedWarehouseId] = useState<string>(initialWarehouseId || '');
  const [reason, setReason] = useState<string>('');
  const [notes, setNotes] = useState<string>('');
  const [operator, setOperator] = useState<string>('Current admin');

  // Store warehouse configurations
  const [storeConfigs, setStoreConfigs] = useState<StoreWithWarehouses[]>([]);
  const [warehouseStockData, setWarehouseStockData] = useState<Record<string, Record<string, SkuWarehouseStockBalance>>>({});
  const [refreshNonce,setRefreshNonce] = useState(0);
  const [isRetryingFetch, setIsRetryingFetch] = useState<boolean>(false);
  const [isStoreDropdownOpen, setIsStoreDropdownOpen] = useState<boolean>(false);
  const storeDropdownRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (storeDropdownRef.current && !storeDropdownRef.current.contains(e.target as Node)) {
        setIsStoreDropdownOpen(false);
      }
    };
    if (isStoreDropdownOpen) {
      document.addEventListener('mousedown', handleClickOutside);
    }
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [isStoreDropdownOpen]);

  useEffect(() => {
    if (isOpen) {
      setActionType(initialType);
      setQuantityInput('');
      setNotes('');
      setReason(initialType === 'intake' ? 'Supplier PO Delivery Verification' : 'Physical Cycle Count Reconciliation');
      if (initialStoreId) setSelectedStoreId(initialStoreId);
      if (initialWarehouseId) setSelectedWarehouseId(initialWarehouseId);
    }
  }, [isOpen, initialType, initialStoreId, initialWarehouseId, item?.id]);

  useEffect(()=>{
    if(!isOpen || !item) return;
    let active=true;
    loadFacilityView(item,stores,storeBalances).then(view=>{
      if(!active)return;
      setStoreConfigs(view.storeConfigs);setWarehouseStockData(view.warehouseBalances);
      if(!initialStoreId) setSelectedStoreId(view.storeConfigs[0]?.storeId||'');
    });
    return()=>{active=false};
  },[isOpen,item?.id,stores,storeBalances,refreshNonce]);

  const currentSkuBalances = item ? (warehouseStockData[item.masterSku] || {}) : {};

  // Current selected store configuration
  const currentStoreConfig = storeConfigs.find(s => s.storeId === selectedStoreId) || storeConfigs[0];
  const hasWarehouses = currentStoreConfig && currentStoreConfig.warehouseMode !== 'no_warehouse' && currentStoreConfig.warehouses.length > 0;
  const isVerificationPending = currentStoreConfig?.warehouseSyncStatus === 'verification_pending';
  const isUnmapped = !currentStoreConfig?.isSkuMapped;

  // Available warehouses under current store
  const storeWarehouses = currentStoreConfig?.warehouses || [];

  // Ensure selectedWarehouseId belongs to current store if it has warehouses
  const effectiveWarehouseId = useMemo(() => {
    if (!hasWarehouses) return `direct:${selectedStoreId}`;
    const found = storeWarehouses.find(w => w.id === selectedWarehouseId);
    return found ? found.id : storeWarehouses[0]?.id || '';
  }, [hasWarehouses, storeWarehouses, selectedWarehouseId]);

  const currentWarehouseObj = useMemo(() => {
    if (!hasWarehouses) {
      return {
        id: `direct:${selectedStoreId}`,
        name: 'Storefront Direct Inventory',
        codeType: 'internal_bin' as const,
        codeValue: 'DIRECT-WEB',
        balance: currentSkuBalances[`direct:${selectedStoreId}`]
      };
    }
    const wh = storeWarehouses.find(w => w.id === effectiveWarehouseId);
    if (!wh) return null;
    return {
      ...wh,
      balance: currentSkuBalances[wh.id]
    };
  }, [hasWarehouses, storeWarehouses, effectiveWarehouseId, currentSkuBalances]);

  // Target current stock values
  const currentPhysical = currentWarehouseObj?.balance?.physicalStock ?? 0;
  const currentReserved = currentWarehouseObj?.balance?.reservedStock ?? 0;
  const currentAvailable = currentWarehouseObj?.balance?.availableStock ?? Math.max(0, currentPhysical - currentReserved);

  const numericQty = quantityInput.trim() === '' ? NaN : Number(quantityInput);
  const isValidQty = !isNaN(numericQty) && Number.isInteger(numericQty) && (actionType === 'intake' ? numericQty > 0 : numericQty >= 0);

  // New physical stock calculation
  const newPhysical = actionType === 'intake'
    ? (isValidQty ? currentPhysical + numericQty : currentPhysical)
    : (isValidQty ? numericQty : currentPhysical);

  const delta = newPhysical - currentPhysical;
  const newAvailable = Math.max(0, newPhysical - currentReserved);
  const hasDeficit = newPhysical < currentReserved;

  // Blocked submission check
  const isSubmissionBlocked = !isValidQty || hasDeficit || isVerificationPending || isUnmapped
    || !currentWarehouseObj?.balance?.isCounted || (hasWarehouses && ((currentWarehouseObj as any)?.status !== 'active' || !(currentWarehouseObj as any)?.inventoryWarehouseId));

  const handleRetryWarehouseVerification = async () => {
    setIsRetryingFetch(true);
    try {
      await storeInventoryApi.refreshFacilities(selectedStoreId);
      setRefreshNonce(value=>value+1);
    } catch(error:any) {
      setSaveError(error?.message||'Warehouse refresh failed.');
    } finally {
      setIsRetryingFetch(false);
    }
  };

  const [saveError,setSaveError] = useState('');
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (isSubmissionBlocked || !item) return;
    setSaveError('');
    try {
    await onSave({
      sku: item.masterSku,
      type: actionType,
      targetStoreId: mode === 'independent' ? selectedStoreId : undefined,
      targetWarehouseId: hasWarehouses ? effectiveWarehouseId : undefined,
      targetWarehouseCode: currentWarehouseObj?.codeValue,
      deltaQuantity: delta,
      newPhysicalStock: newPhysical,
      reason,
      notes: notes.trim() || undefined,
      operator
    });
    onClose();
    } catch(error:any) {
      setSaveError(error?.message || 'Inventory could not be saved.');
    }
  };

  // Safe early return after all React hooks and event handlers are called
  if (!isOpen || !item || !currentStoreConfig) return null;

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto bg-neutral-950/75 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 animate-in fade-in duration-150">
      <div 
        id="stock-intake-adjustment-modal"
        className="bg-white dark:bg-neutral-900 rounded-2xl shadow-2xl border border-neutral-200 dark:border-neutral-800 w-full max-w-2xl overflow-hidden flex flex-col my-4 text-neutral-900 dark:text-neutral-100"
      >
        {/* Header */}
        <div className="px-6 py-4 border-b border-neutral-100 dark:border-neutral-800 flex items-center justify-between bg-neutral-50/50 dark:bg-neutral-900/50">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl flex items-center justify-center font-bold bg-neutral-100 dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200">
              {actionType === 'intake' ? <PlusCircle className="w-5 h-5 text-emerald-500" /> : <Sliders className="w-5 h-5 text-purple-400" />}
            </div>
            <div>
              <h3 className="text-base font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                <span>{actionType === 'intake' ? 'Actual Stock Intake' : 'Stock Audit Adjustment'}</span>
                <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-950/60 border border-purple-800/60 text-purple-300">
                  {hasWarehouses ? 'Multi-Warehouse Compatible' : 'Direct Channel'}
                </span>
              </h3>
              <p className="text-xs text-neutral-500 dark:text-neutral-400">
                {actionType === 'intake'
                  ? 'Record newly arrived physical merchandise from suppliers or manufacturing to a specific facility.'
                  : 'Adjust inventory ledger to reflect physical stock variances, damage, or recount audit.'}
              </p>
            </div>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="p-2 rounded-lg text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors cursor-pointer"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Mode Switch Pills */}
        <div className="px-6 pt-4">
          <div className="flex p-1 bg-neutral-100 dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 text-xs font-semibold">
            <button
              type="button"
              onClick={() => {
                setActionType('intake');
                setQuantityInput('');
                setReason('Supplier PO Delivery Verification');
              }}
              className={`flex-1 py-2 rounded-lg transition-all flex items-center justify-center gap-2 cursor-pointer ${
                actionType === 'intake'
                  ? 'bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white shadow-xs font-bold'
                  : 'text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200'
              }`}
            >
              <PlusCircle className="w-4 h-4 text-emerald-500" />
              <span>Actual Inbound Intake (+Qty)</span>
            </button>
            <button
              type="button"
              onClick={() => {
                setActionType('adjustment');
                setQuantityInput('');
                setReason('Physical Cycle Count Reconciliation');
              }}
              className={`flex-1 py-2 rounded-lg transition-all flex items-center justify-center gap-2 cursor-pointer ${
                actionType === 'adjustment'
                  ? 'bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white shadow-xs font-bold'
                  : 'text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200'
              }`}
            >
              <Sliders className="w-4 h-4 text-purple-400" />
              <span>Audit Count Adjustment</span>
            </button>
          </div>
        </div>

        {/* Form Body */}
        <form onSubmit={handleSubmit} className="p-6 space-y-4 max-h-[75vh] overflow-y-auto">
          {/* Target SKU summary */}
          <div className="p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700 flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-lg bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 overflow-hidden shrink-0 flex items-center justify-center">
                {item.imageUrl ? (
                  <img src={item.imageUrl} alt={item.productName} className="w-full h-full object-cover" />
                ) : (
                  <Boxes className="w-5 h-5 text-neutral-400" />
                )}
              </div>
              <div>
                <div className="font-mono text-xs font-bold text-neutral-900 dark:text-white">{item.masterSku}</div>
                <div className="text-xs font-semibold text-neutral-900 dark:text-white truncate max-w-sm">{item.productName}</div>
              </div>
            </div>
            <div className="text-right">
              <div className="text-[10px] text-neutral-400">SKU Global Physical</div>
              <div className="font-mono font-bold text-neutral-800 dark:text-neutral-200 text-xs">
                {item.totalPhysicalStock !== null ? `${item.totalPhysicalStock} units` : 'Not Counted'}
              </div>
            </div>
          </div>

          {/* Target Store Account Select (Screenshot 2/3 style - Enterprise Custom Dropdown) */}
          <div className="space-y-1.5 p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700">
            <div className="flex items-center justify-between">
              <label className="text-xs font-bold text-neutral-900 dark:text-neutral-100">
                Target Store Account *
              </label>
              {currentStoreConfig && (
                <span className="text-[10px] text-neutral-400">
                  {currentStoreConfig.warehouseMode === 'no_warehouse' 
                    ? 'No Warehouse (Direct Storefront)' 
                    : `${currentStoreConfig.warehouses.length} Registered Platform Facilities`}
                </span>
              )}
            </div>

            {/* Custom High-Contrast Dropdown */}
            <div className="relative" ref={storeDropdownRef}>
              <button
                type="button"
                onClick={() => setIsStoreDropdownOpen(!isStoreDropdownOpen)}
                className="w-full px-3 py-2.5 text-xs rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white flex items-center justify-between gap-2 shadow-xs hover:border-neutral-400 dark:hover:border-neutral-600 focus:outline-hidden focus:ring-2 focus:ring-purple-500 transition-colors cursor-pointer text-left"
              >
                <div className="flex items-center gap-2.5 min-w-0 flex-1">
                  <MarketplaceLogo platform={currentStoreConfig.platform as any} size="xs" />
                  <span className="font-bold text-neutral-900 dark:text-white truncate">
                    {currentStoreConfig.storeNickname || currentStoreConfig.storeName}
                  </span>
                  <span className="text-[10px] font-mono px-1.5 py-0.5 rounded bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-300 font-semibold border border-neutral-200 dark:border-neutral-700 uppercase shrink-0">
                    {currentStoreConfig.platform}
                  </span>
                  {currentStoreConfig.warehouseSyncStatus === 'verification_pending' && (
                    <span className="text-[10px] font-semibold px-2 py-0.5 rounded bg-amber-500/20 text-amber-300 border border-amber-500/40 shrink-0">
                      Verification pending
                    </span>
                  )}
                  {!currentStoreConfig.isSkuMapped && (
                    <span className="text-[10px] font-semibold px-2 py-0.5 rounded bg-orange-500/20 text-orange-300 border border-orange-500/40 shrink-0">
                      Not Mapped
                    </span>
                  )}
                </div>
                <ChevronDown className={`w-4 h-4 text-neutral-400 transition-transform shrink-0 ${isStoreDropdownOpen ? 'rotate-180 text-purple-400' : ''}`} />
              </button>

              {/* Dropdown Menu Popup (Guaranteed Dark High-Contrast) */}
              {isStoreDropdownOpen && (
                <div 
                  className="absolute left-0 right-0 mt-1.5 z-50 bg-neutral-900 border border-neutral-750 rounded-xl shadow-2xl overflow-hidden py-1 divide-y divide-neutral-800/80 animate-in fade-in zoom-in-95 duration-100 max-h-72 overflow-y-auto"
                  style={{ backgroundColor: '#171717', color: '#f5f5f5' }}
                >
                  {storeConfigs.map(s => {
                    const isSelected = s.storeId === selectedStoreId;
                    const isPending = s.warehouseSyncStatus === 'verification_pending';
                    const isUnmappedStore = !s.isSkuMapped;

                    return (
                      <button
                        key={s.storeId}
                        type="button"
                        onClick={() => {
                          setSelectedStoreId(s.storeId);
                          if (s.warehouses.length > 0) {
                            setSelectedWarehouseId(s.warehouses[0].id);
                          }
                          setIsStoreDropdownOpen(false);
                        }}
                        className={`w-full px-3.5 py-2.5 text-left text-xs flex items-center justify-between gap-3 transition-colors cursor-pointer ${
                          isSelected
                            ? 'bg-purple-950/40 text-white font-semibold'
                            : 'hover:bg-neutral-800/90 text-neutral-200'
                        }`}
                      >
                        <div className="flex items-center gap-2.5 min-w-0 flex-1">
                          <MarketplaceLogo platform={s.platform as any} size="xs" />
                          <div className="min-w-0">
                            <div className="flex items-center gap-2 flex-wrap">
                              <span className="font-bold text-white text-xs">
                                {s.storeNickname || s.storeName}
                              </span>
                              <span className="text-[10px] font-mono px-1.5 py-0.5 rounded bg-neutral-800 text-neutral-300 font-semibold border border-neutral-700 uppercase">
                                {s.platform}
                              </span>
                              {isPending && (
                                <span className="text-[10px] font-semibold px-1.5 py-0.2 rounded bg-amber-500/20 text-amber-300 border border-amber-500/40">
                                  Verification pending
                                </span>
                              )}
                              {isUnmappedStore && (
                                <span className="text-[10px] font-semibold px-1.5 py-0.2 rounded bg-orange-500/20 text-orange-300 border border-orange-500/40">
                                  SKU Not Mapped
                                </span>
                              )}
                            </div>
                            <div className="text-[11px] text-neutral-400 truncate mt-0.5">
                              {s.storeName} • {s.warehouseMode === 'no_warehouse' ? 'Direct Storefront' : `${s.warehouses.length} Platform Facilities`}
                            </div>
                          </div>
                        </div>

                        {isSelected && (
                          <div className="w-5 h-5 rounded-full bg-purple-500/20 flex items-center justify-center text-purple-400 shrink-0">
                            <Check className="w-3.5 h-3.5" />
                          </div>
                        )}
                      </button>
                    );
                  })}
                </div>
              )}
            </div>
          </div>

          {/* Verification Pending Alert & Action Pause */}
          {isVerificationPending && (
            <div className="p-3.5 rounded-xl bg-amber-950/40 border border-amber-800/60 text-amber-200 text-xs space-y-2">
              <div className="flex items-start justify-between gap-2">
                <div className="flex items-center gap-2 font-bold text-amber-300">
                  <AlertTriangle className="w-4 h-4 text-amber-400" />
                  <span>Warehouse verification pending — stock operations paused</span>
                </div>
                <button
                  type="button"
                  disabled
                  title="Available after a verified warehouse API connection is implemented"
                  className="px-2.5 py-1 rounded bg-amber-600 text-white font-bold text-xs flex items-center gap-1 opacity-50 cursor-not-allowed"
                >
                  <RefreshCw className={`w-3 h-3 ${isRetryingFetch ? 'animate-spin' : ''}`} />
                  <span>Retry Fetch</span>
                </button>
              </div>
              <p className="text-[11px] text-amber-200/80">
                {currentStoreConfig.verificationPendingReason || 'The platform API could not verify this store warehouses and access. Intake and adjustment are paused to protect the inventory ledger.'}
              </p>
            </div>
          )}

          {/* SKU Not Mapped Alert */}
          {isUnmapped && (
            <div className="p-3.5 rounded-xl bg-orange-950/40 border border-orange-800/60 text-orange-200 text-xs flex items-center gap-2">
              <ShieldAlert className="w-4 h-4 text-orange-400 shrink-0" />
              <span>Map this SKU to the store before intake or count adjustment.</span>
            </div>
          )}

          {/* Multi-Warehouse Section: Warehouse Selection & Read-only List */}
          {hasWarehouses && !isVerificationPending && !isUnmapped && (
            <div className="space-y-3 p-3.5 rounded-xl bg-neutral-950/60 border border-neutral-800">
              <div className="flex items-center justify-between">
                <label className="text-xs font-bold text-purple-300 flex items-center gap-1.5">
                  <Warehouse className="w-4 h-4 text-purple-400" />
                  <span>Target Warehouse / Location Facility *</span>
                </label>
                <span className="text-[10px] text-neutral-400 font-mono">
                  Single facility mutation rule enforced
                </span>
              </div>

              {/* Warehouse Dropdown Select */}
              <select
                value={effectiveWarehouseId}
                onChange={e => setSelectedWarehouseId(e.target.value)}
                className="w-full px-3 py-2 text-xs rounded-xl border border-neutral-700 bg-neutral-900 text-white cursor-pointer focus:ring-2 focus:ring-purple-500"
              >
                {storeWarehouses.map(w => {
                  const b = currentSkuBalances[w.id];
                  const phys = b?.physicalStock ?? 'N/A';
                  return (
                    <option key={w.id} value={w.id}>
                      {w.name} [{w.codeType === 'location_id' ? 'location_id' : 'code'}: {w.codeValue}] — Phys: {phys}, Avail: {b?.availableStock ?? 'N/A'}
                    </option>
                  );
                })}
              </select>

              {/* Read-Only Table of All Registered Warehouses for This SKU in This Store */}
              <div className="mt-2 pt-2 border-t border-neutral-800">
                <div className="text-[11px] font-semibold text-neutral-400 mb-1.5 flex items-center justify-between">
                  <span>Registered Facilities in this Store (Read-Only Balance Overview):</span>
                  <span className="text-[10px] text-neutral-500">Only selected row will be modified</span>
                </div>
                <div className="max-h-36 overflow-y-auto rounded-lg border border-neutral-800 divide-y divide-neutral-850 text-xs">
                  {storeWarehouses.map(w => {
                    const b = currentSkuBalances[w.id];
                    const isTarget = w.id === effectiveWarehouseId;
                    return (
                      <div
                        key={w.id}
                        onClick={() => setSelectedWarehouseId(w.id)}
                        className={`p-2 flex items-center justify-between transition-colors cursor-pointer ${
                          isTarget ? 'bg-purple-950/40 text-purple-200' : 'bg-neutral-900/60 hover:bg-neutral-850 text-neutral-300'
                        }`}
                      >
                        <div className="flex items-center gap-2">
                          <input
                            type="radio"
                            name="targetWhModal"
                            checked={isTarget}
                            onChange={() => setSelectedWarehouseId(w.id)}
                            className="w-3.5 h-3.5 text-purple-600 focus:ring-purple-500 cursor-pointer"
                          />
                          <span className="font-semibold">{w.name}</span>
                          <span className="font-mono text-[10px] text-purple-300 bg-neutral-800 px-1.5 py-0.2 rounded">
                            {w.codeValue}
                          </span>
                        </div>
                        <div className="flex items-center gap-3 font-mono text-[11px]">
                          <span>Phys: <strong>{b?.physicalStock ?? 'N/A'}</strong></span>
                          <span>Res: <strong className="text-amber-400">{b?.reservedStock ?? 0}</strong></span>
                          <span>Avail: <strong className="text-emerald-400">{b?.availableStock ?? 'N/A'}</strong></span>
                        </div>
                      </div>
                    );
                  })}
                </div>
              </div>

              {/* Selected Target Warehouse Detailed Highlight Card */}
              {currentWarehouseObj && (
                <div className="p-3 rounded-xl bg-purple-950/20 border border-purple-800/40 flex items-center justify-between text-xs">
                  <div>
                    <span className="text-[10px] text-purple-300 font-semibold uppercase tracking-wider block">
                      Active Target Facility
                    </span>
                    <div className="font-bold text-white mt-0.5">
                      {currentWarehouseObj.name}
                    </div>
                    <div className="text-[11px] text-neutral-400 font-mono mt-0.5">
                      Platform ID: <strong className="text-purple-300">{currentWarehouseObj.codeValue}</strong>
                    </div>
                  </div>
                  <div className="flex items-center gap-4 text-xs font-mono text-right">
                    <div>
                      <span className="text-[10px] text-neutral-400 block font-sans">Current Phys</span>
                      <span className="font-bold text-white">{currentPhysical}</span>
                    </div>
                    <div>
                      <span className="text-[10px] text-neutral-400 block font-sans">Reserved</span>
                      <span className="font-bold text-amber-400">{currentReserved}</span>
                    </div>
                    <div>
                      <span className="text-[10px] text-neutral-400 block font-sans">Current Avail</span>
                      <span className="font-bold text-emerald-400">{currentAvailable}</span>
                    </div>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* Quantity Input */}
          <div className="space-y-1.5">
            <label className="text-xs font-bold text-neutral-800 dark:text-neutral-200">
              {actionType === 'intake' 
                ? 'Arrived Inbound Quantity (+ Units) *' 
                : 'New Confirmed Physical Stock Count (Total Units) *'}
            </label>
            <div className="relative">
              <input
                type="number"
                min={actionType === 'intake' ? 1 : 0}
                step="1"
                disabled={isVerificationPending || isUnmapped}
                placeholder={actionType === 'intake' ? 'e.g. 50' : 'e.g. 145'}
                value={quantityInput}
                onChange={e => setQuantityInput(e.target.value)}
                className="w-full px-3.5 py-2.5 text-sm font-mono font-bold rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white focus:ring-2 focus:ring-purple-500 disabled:opacity-40"
              />
              <div className="absolute right-3 top-2.5 text-xs text-neutral-400 pointer-events-none">
                Units
              </div>
            </div>
          </div>

          {/* Deficit warning */}
          {hasDeficit && (
            <div className="p-3 rounded-xl bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800/50 flex items-start gap-2.5 text-xs text-red-900 dark:text-red-200">
              <AlertTriangle className="w-4 h-4 text-red-600 dark:text-red-400 shrink-0 mt-0.5" />
              <span>
                New physical quantity ({newPhysical}) is less than locked customer orders ({currentReserved}) in this facility. Physical count cannot be reduced below active reserved orders.
              </span>
            </div>
          )}

          {/* Preview impact */}
          {isValidQty && !hasDeficit && !isVerificationPending && !isUnmapped && (
            <div className="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700 grid grid-cols-3 gap-2 text-center text-xs">
              <div>
                <span className="text-neutral-400 text-[10px] block">Previous Facility Physical</span>
                <span className="font-mono font-semibold text-neutral-700 dark:text-neutral-300">{currentPhysical}</span>
              </div>
              <div>
                <span className="text-neutral-400 text-[10px] block">
                  {actionType === 'intake' ? 'Added Quantity' : 'Audit Variance (Delta)'}
                </span>
                <span className={`font-mono font-bold ${delta >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'}`}>
                  {delta >= 0 ? `+${delta}` : delta}
                </span>
              </div>
              <div>
                <span className="text-neutral-400 text-[10px] block">New Facility Available</span>
                <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400">{newAvailable}</span>
              </div>
            </div>
          )}

          {/* Pre-Submission Confirmation Summary Card (Requirement C) */}
          {isValidQty && !hasDeficit && !isVerificationPending && !isUnmapped && (
            <div className="p-3.5 rounded-xl bg-purple-950/20 border border-purple-800/30 text-xs space-y-2">
              <div className="text-[11px] font-bold text-purple-300 uppercase tracking-wider">
                Pre-Submission Audit Confirmation Summary
              </div>
              <div className="grid grid-cols-2 gap-2 text-[11px] text-neutral-300">
                <div>
                  <span className="text-neutral-500 block">Target Store:</span>
                  <span className="font-semibold text-white">{currentStoreConfig?.storeNickname}</span>
                </div>
                <div>
                  <span className="text-neutral-500 block">Target Facility / ID:</span>
                  <span className="font-semibold text-white">
                    {currentWarehouseObj?.name} (<span className="font-mono text-purple-300">{currentWarehouseObj?.codeValue}</span>)
                  </span>
                </div>
                <div>
                  <span className="text-neutral-500 block">Stock Movement:</span>
                  <span className="font-mono font-bold text-emerald-400">
                    Phys: {currentPhysical} → {newPhysical} (Avail: {currentAvailable} → {newAvailable})
                  </span>
                </div>
                <div>
                  <span className="text-neutral-500 block">Operator:</span>
                  <span className="font-semibold text-white">{operator}</span>
                </div>
              </div>
            </div>
          )}

          {/* Reason & Notes */}
          <div className="space-y-3">
            <div className="space-y-1">
              <label className="text-xs font-bold text-neutral-700 dark:text-neutral-300">
                Reason *
              </label>
              <input
                type="text"
                value={reason}
                disabled={isVerificationPending || isUnmapped}
                onChange={e => setReason(e.target.value)}
                className="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white disabled:opacity-40"
              />
            </div>
            <div className="space-y-1">
              <label className="text-xs font-medium text-neutral-700 dark:text-neutral-300">
                Notes (Optional)
              </label>
              <input
                type="text"
                value={notes}
                disabled={isVerificationPending || isUnmapped}
                onChange={e => setNotes(e.target.value)}
                placeholder="e.g. PO invoice number or cycle audit notes..."
                className="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white disabled:opacity-40"
              />
            </div>
          </div>

          {/* Footer Buttons */}
          <div className="pt-3 flex items-center justify-end gap-2 border-t border-neutral-100 dark:border-neutral-800">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 text-xs font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 cursor-pointer"
            >
              Cancel
            </button>
            {saveError && <p role="alert" className="text-xs text-rose-500">{saveError}</p>}
            <button
              type="submit"
              disabled={isSubmissionBlocked}
              className="px-5 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold transition-all shadow-xs flex items-center gap-1.5 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
            >
              <CheckCircle2 className="w-3.5 h-3.5" />
              <span>{actionType === 'intake' ? 'Confirm Facility Intake' : 'Save Facility Adjustment'}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};
