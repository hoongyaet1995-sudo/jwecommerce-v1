import React, { useState, useMemo, useEffect } from 'react';
import {
  X,
  Warehouse,
  Boxes,
  CheckCircle2,
  AlertTriangle,
  Clock,
  RefreshCw,
  Search,
  ExternalLink,
  Sliders,
  PlusCircle,
  ClipboardCheck,
  ArrowRightLeft,
  ChevronDown,
  ChevronUp,
  ShieldAlert,
  Info,
  Layers,
  History,
  AlertCircle,
  HelpCircle,
  Sparkles,
  MapPin,
  Lock
} from 'lucide-react';
import { MarketplaceInventoryItem } from '../../../types';
import {
  StoreWithWarehouses,
  StorePlatformWarehouse,
  SkuWarehouseStockBalance,
  WarehouseSyncState,
  WarehouseLedgerEntry
} from './warehouseInventoryTypes';
import {storeInventoryApi} from '../../../../../integration/storeInventory';
import { MarketplaceLogo } from '../MarketplaceLogo';

interface ManageStockDrawerProps {
  isOpen: boolean;
  onClose: () => void;
  item: MarketplaceInventoryItem | null;
  mode: 'shared' | 'independent';
  stores?: any[];
  storeBalances?: any;
  onSelectFirstCount?: (item: MarketplaceInventoryItem, targetStoreId?: string, targetWarehouseId?: string) => void;
  onSelectAllocateStock?: (item: MarketplaceInventoryItem) => void;
  onSelectIntake?: (item: MarketplaceInventoryItem, targetStoreId?: string, targetWarehouseId?: string) => void;
  onSelectAdjustment?: (item: MarketplaceInventoryItem, targetStoreId?: string, targetWarehouseId?: string) => void;
  onSelectTransfer?: (item: MarketplaceInventoryItem, sourceStoreId?: string, sourceWarehouseId?: string) => void;
  onViewLedger?: (item: MarketplaceInventoryItem, storeId?: string, warehouseId?: string) => void;
  onOpenFirstCount?: (item: MarketplaceInventoryItem, targetStoreId?: string, targetWarehouseId?: string) => void;
  onOpenAllocateStock?: (item: MarketplaceInventoryItem) => void;
  onOpenIntake?: (item: MarketplaceInventoryItem, type: 'intake' | 'adjustment', targetStoreId?: string, targetWarehouseId?: string) => void;
  onOpenTransfer?: (item: MarketplaceInventoryItem, sourceStoreId?: string, sourceWarehouseId?: string) => void;
  onNavigateToMapping?: () => void;
}

export const ManageStockDrawer: React.FC<ManageStockDrawerProps> = ({
  isOpen,
  onClose,
  item,
  mode,
  stores,
  storeBalances,
  onSelectFirstCount,
  onSelectAllocateStock,
  onSelectIntake,
  onSelectAdjustment,
  onSelectTransfer,
  onViewLedger,
  onOpenFirstCount,
  onOpenAllocateStock,
  onOpenIntake,
  onOpenTransfer,
  onNavigateToMapping
}) => {
  // Warehouse balances state
  const [warehouseBalances, setWarehouseBalances] = useState<Record<string, Record<string, SkuWarehouseStockBalance>>>({});

  // Active Store Configs
  const [storeConfigs, setStoreConfigs] = useState<StoreWithWarehouses[]>([]);

  // Search & filter inside drawer
  const [searchQuery, setSearchQuery] = useState('');

  // Currently selected warehouse for operations
  const [selectedWarehouseId, setSelectedWarehouseId] = useState<string>('');
  const [selectedStoreId, setSelectedStoreId] = useState<string>('');

  // Expanded stores state
  const [expandedStoreIds, setExpandedStoreIds] = useState<Record<string, boolean>>({});

  // Ledger slide-over state
  const [isLedgerOpen, setIsLedgerOpen] = useState(false);
  const [ledgerEntries, setLedgerEntries] = useState<WarehouseLedgerEntry[]>([]);

  // Retrying push state
  const [retryingWarehouseId, setRetryingWarehouseId] = useState<string | null>(null);
  const [refreshNonce, setRefreshNonce] = useState(0);
  const [facilitySync, setFacilitySync] = useState<any>(null);
  const [facilitySyncError, setFacilitySyncError] = useState('');
  const [facilitySyncBusy, setFacilitySyncBusy] = useState(false);
  const [physicalWarehouses,setPhysicalWarehouses]=useState<any[]>([]);
  const [physicalWarehouseId,setPhysicalWarehouseId]=useState('');
  const [bindingBusy,setBindingBusy]=useState(false);
  const [bindingError,setBindingError]=useState('');
  const [bindingReview,setBindingReview]=useState<{id:string;storeId:string;facilityId:string;warehouseId:string}|null>(null);

  useEffect(() => {
    if (!isOpen || !(item as any)?.variantId) return;
    let active = true;
    const storeRows = (stores || []).filter((store:any) => !store.isComingSoon);
    Promise.all(storeRows.map(async (store:any):Promise<{config:StoreWithWarehouses;balances:Record<string,SkuWarehouseStockBalance>}> => {
      const storeId = String(store.id);
      const basic = {storeId,storeName:store.name,storeNickname:store.nickname || store.name,
        platform:store.platform, isSkuMapped:store.platform === 'webstore' || (item as any).mappedStoreIds?.includes(storeId)};
      if (store.platform === 'webstore') {
        const id = `direct:${storeId}`;
        const row = storeBalances?.[storeId];
        return {config:{...basic,warehouseMode:'no_warehouse',warehouseSyncStatus:'synced',warehouses:[]} as StoreWithWarehouses,
          balances:{[id]:{warehouseId:id,storeId,warehouseName:store.name,codeType:'internal_bin',codeValue:'DIRECT-WEB',
            isCounted:!!row?.isCounted,physicalStock:row?.physicalStock ?? null,reservedStock:row?.reservedStock ?? 0,
            availableStock:row?.availableStock ?? null,lastSyncAt:'',syncState:'synced'}}};
      }
      try {
        const response = await storeInventoryApi.facilityBalances(storeId, (item as any).variantId);
        const verified = response.status === 'verified';
        const stockReady = verified && response.stock_operations_ready === true && response.operations_enabled === true;
        if(verified && response.topology === 'no_warehouse') {
          const id=`direct:${storeId}`;const row=storeBalances?.[storeId];
          return {config:{...basic,warehouseMode:'no_warehouse',warehouseSyncStatus:'synced',warehouses:[]} as StoreWithWarehouses,
            balances:{[id]:{warehouseId:id,storeId,warehouseName:store.name,codeType:'internal_bin',codeValue:'DIRECT',
              isCounted:!!row?.isCounted,physicalStock:row?.physicalStock??null,reservedStock:row?.reservedStock??0,
              availableStock:row?.availableStock??null,lastSyncAt:'',syncState:'sync_unknown'}}};
        }
        const warehouses:StorePlatformWarehouse[] = (response.facilities || []).map((facility:any) => ({
          id:String(facility.id),storeId,storeName:store.name,storeNickname:store.nickname || store.name,
          platform:store.platform,name:facility.name,inventoryWarehouseId:facility.inventory_warehouse_id,
          codeType:store.platform === 'shopee' ? 'location_id' : 'warehouse_code',
          codeValue:facility.provider_id,region:'',status:stockReady ? 'active' : 'verification_pending'
        }));
        const balances:Record<string,SkuWarehouseStockBalance> = {};
        for(const facility of response.facilities || []) {
          const id=String(facility.id);
          balances[id]={warehouseId:id,storeId,warehouseName:facility.name,
            physicalOwnerId:facility.inventory_warehouse_id?`warehouse:${facility.inventory_warehouse_id}`:undefined,
            codeType:store.platform === 'shopee' ? 'location_id' : 'warehouse_code',codeValue:facility.provider_id,
            isCounted:!!facility.counted,physicalStock:facility.physical ?? null,
            reservedStock:facility.reserved ?? 0,availableStock:facility.available ?? null,lastSyncAt:'',
            syncState:stockReady ? 'sync_unknown' : 'verification_pending'};
        }
        return {config:{...basic,warehouseMode:response.topology || 'no_warehouse',
          warehouseSyncStatus:stockReady ? 'synced' : 'verification_pending',
          verificationPendingReason:response.error_code || (!stockReady && verified ? 'Warehouse stock actions are pending activation' : undefined),warehouses} as StoreWithWarehouses,balances};
      } catch(error:any) {
        return {config:{...basic,warehouseMode:'no_warehouse',warehouseSyncStatus:'verification_pending',
          verificationPendingReason:error?.message || 'Warehouse list unavailable',warehouses:[]} as StoreWithWarehouses,
          balances:{}};
      }
    })).then(rows => {
      if(!active) return;
      const configurations=rows.map(row=>row.config);
      setStoreConfigs(configurations);
      setWarehouseBalances({[item.masterSku]:Object.assign({},...rows.map(row=>row.balances))});
      const first=configurations.flatMap(store=>store.warehouseMode==='no_warehouse' && store.warehouseSyncStatus==='synced'
        ? [{storeId:store.storeId,id:`direct:${store.storeId}`}]
        : store.warehouses.map(warehouse=>({storeId:store.storeId,id:warehouse.id})))[0];
      setSelectedStoreId(first?.storeId || '');setSelectedWarehouseId(first?.id || '');
      setExpandedStoreIds(Object.fromEntries(configurations.map(store=>[store.storeId,true])));
    });
    return () => {active=false};
  },[isOpen,(item as any)?.variantId,item?.masterSku,stores,storeBalances,refreshNonce]);

  const currentSku = item?.masterSku || '';
  const currentSkuBalances = item ? (warehouseBalances[item.masterSku] || {}) : {};
  const hasSkuBalances = Object.keys(currentSkuBalances).length > 0;

  // Compute grand totals across all counted warehouses
  const totalPhysical = useMemo(() => {
    const owners=new Set<string>();
    return Object.values(currentSkuBalances).reduce((acc, wb) => {
      const owner=wb.physicalOwnerId||wb.warehouseId;
      if(owners.has(owner))return acc;
      owners.add(owner);
      return acc + (wb.physicalStock !== null && wb.syncState !== 'verification_pending' && wb.syncState !== 'unmapped' ? wb.physicalStock : 0);
    }, 0);
  }, [currentSkuBalances]);

  const totalReserved = useMemo(() => {
    const owners=new Set<string>();
    return Object.values(currentSkuBalances).reduce((acc, wb) => {
      const owner=wb.physicalOwnerId||wb.warehouseId;
      if(owners.has(owner))return acc;
      owners.add(owner);
      return acc+wb.reservedStock;
    }, 0);
  }, [currentSkuBalances]);

  const totalAvailable = Math.max(0, totalPhysical - totalReserved);

  // Count total warehouses
  const totalWarehousesCount = useMemo(() => {
    return storeConfigs.reduce((acc, sc) => acc + (sc.warehouseMode === 'no_warehouse' ? (sc.warehouseSyncStatus === 'synced' ? 1 : 0) : sc.warehouses.length), 0);
  }, [storeConfigs]);

  // Filtered stores
  const filteredStores = useMemo(() => {
    return storeConfigs.filter(st => {
      // Text search
      if (!searchQuery.trim()) return true;
      const q = searchQuery.toLowerCase();
      const matchStore = st.storeName.toLowerCase().includes(q) || st.storeNickname.toLowerCase().includes(q);
      const matchWarehouse = st.warehouses.some(w => 
        w.name.toLowerCase().includes(q) || 
        w.codeValue.toLowerCase().includes(q) || 
        w.region.toLowerCase().includes(q)
      );
      return matchStore || matchWarehouse;
    });
  }, [storeConfigs, searchQuery]);

  // Selected warehouse object
  const selectedWarehouseObj = useMemo(() => {
    for (const sc of storeConfigs) {
      if (sc.warehouseMode === 'no_warehouse' && sc.warehouseSyncStatus === 'synced' && selectedWarehouseId === `direct:${sc.storeId}` && sc.storeId === selectedStoreId) {
        return {
          id: `direct:${sc.storeId}`,
          name: `${sc.storeName} Direct Inventory (No Platform Warehouse)`,
          codeType: 'internal_bin' as const,
          codeValue: 'DIRECT-WEB',
          store: sc,
          balance: currentSkuBalances[`direct:${sc.storeId}`]
        };
      }
      const foundWh = sc.warehouses.find(w => w.id === selectedWarehouseId);
      if (foundWh) {
        return {
          ...foundWh,
          store: sc,
          balance: currentSkuBalances[foundWh.id]
        };
      }
    }
    return null;
  }, [storeConfigs, selectedWarehouseId, selectedStoreId, currentSkuBalances]);

  const toggleExpandStore = (storeId: string) => {
    setExpandedStoreIds(prev => ({ ...prev, [storeId]: !prev[storeId] }));
  };

  const handleSelectWarehouse = (storeId: string, warehouseId: string) => {
    setSelectedStoreId(storeId);
    setSelectedWarehouseId(warehouseId);
  };

  const selectedStoreReady = selectedWarehouseObj?.store.warehouseSyncStatus === 'synced'
    && selectedWarehouseObj.store.isSkuMapped
    && (selectedWarehouseObj.store.warehouseMode === 'no_warehouse' || ('status' in selectedWarehouseObj
      && selectedWarehouseObj.status === 'active' && !!selectedWarehouseObj.inventoryWarehouseId));
  const selectedCounted = selectedWarehouseObj?.balance?.isCounted === true;
  const selectedAvailable = selectedWarehouseObj?.balance?.availableStock ?? 0;
  const canFirstCount = selectedStoreReady && !selectedCounted;
  const canAdjust = selectedStoreReady && selectedCounted;
  const canAllocate = canAdjust && mode === 'independent'
    && selectedWarehouseObj?.store.warehouseMode === 'no_warehouse';
  const canTransfer = canAdjust && mode === 'independent' && selectedAvailable > 0;

  useEffect(()=>{
    if(!isOpen || !selectedWarehouseObj || !('inventoryWarehouseId' in selectedWarehouseObj)
      || selectedWarehouseObj.inventoryWarehouseId || selectedWarehouseObj.store.warehouseSyncStatus!=='synced')return;
    let active=true;
    fetch('/admin/api/inventory/warehouses',{credentials:'same-origin',headers:{Accept:'application/json'}})
      .then(async response=>{if(!response.ok)throw new Error('JW warehouse list unavailable');return response.json()})
      .then(result=>{if(active)setPhysicalWarehouses((result.data||[]).filter((warehouse:any)=>warehouse.is_active))})
      .catch(error=>{if(active)setBindingError(error?.message||'JW warehouse list unavailable')});
    return()=>{active=false};
  },[isOpen,selectedWarehouseId,selectedWarehouseObj?.store.warehouseSyncStatus]);

  const bindSelectedFacility=async()=>{
    if(!/^\d+$/.test(selectedWarehouseId)||!/^\d+$/.test(physicalWarehouseId))return;
    setBindingBusy(true);setBindingError('');
    try{
      const review=await storeInventoryApi.reviewFacilityBinding(selectedStoreId,Number(selectedWarehouseId),Number(physicalWarehouseId),'Verified physical facility link');
      setBindingReview({id:review.id,storeId:selectedStoreId,facilityId:selectedWarehouseId,warehouseId:physicalWarehouseId});
    }catch(error:any){setBindingError(error?.message||'Facility link failed');}
    finally{setBindingBusy(false);}
  };
  const confirmSelectedBinding=async()=>{
    if(!bindingReview || bindingReview.storeId!==selectedStoreId || bindingReview.facilityId!==selectedWarehouseId
      || bindingReview.warehouseId!==physicalWarehouseId)return;
    setBindingBusy(true);setBindingError('');
    try{
      await storeInventoryApi.confirmFacilityBinding(bindingReview.id,crypto.randomUUID());
      setBindingReview(null);setPhysicalWarehouseId('');setRefreshNonce(value=>value+1);
    }catch(error:any){setBindingError(error?.message||'Facility link failed');}
    finally{setBindingBusy(false);}
  };

  useEffect(() => {
    if (!isOpen || !item || !/^\d+$/.test(selectedWarehouseId) || !selectedStoreReady) {
      setFacilitySync(null);
      return;
    }
    let active=true;
    setFacilitySync(null);
    storeInventoryApi.facilitySyncDetails(selectedStoreId,Number(selectedWarehouseId),(item as any).variantId)
      .then(result=>{if(active)setFacilitySync(result)})
      .catch(error=>{if(active)setFacilitySyncError(error?.message||'Sync status unavailable')});
    return()=>{active=false};
  },[isOpen,(item as any)?.variantId,selectedStoreId,selectedWarehouseId,selectedStoreReady,refreshNonce]);

  const refreshExactSync = async (resend:boolean) => {
    if (!item || !/^\d+$/.test(selectedWarehouseId)) return;
    setFacilitySyncBusy(true);setFacilitySyncError('');
    try {
      const result=resend
        ? await storeInventoryApi.facilityResync(selectedStoreId,Number(selectedWarehouseId),(item as any).variantId,crypto.randomUUID())
        : await storeInventoryApi.facilitySyncCheck(selectedStoreId,Number(selectedWarehouseId),(item as any).variantId);
      setFacilitySync(result);
    } catch(error:any) {setFacilitySyncError(error?.message||'Exact warehouse stock check failed');}
    finally {setFacilitySyncBusy(false);}
  };

  const handleRetryWarehouseFetch = async (storeId: string) => {
    if (!item) return;
    setStoreConfigs(prev => prev.map(s => {
      if (s.storeId === storeId) {
        return { ...s, warehouseSyncStatus: 'syncing' };
      }
      return s;
    }));
    try {
      await storeInventoryApi.refreshFacilities(storeId);
      setRefreshNonce(value => value + 1);
    } catch (error:any) {
      setStoreConfigs(prev => prev.map(s => s.storeId === storeId
        ? {...s,warehouseSyncStatus:'verification_pending',
          verificationPendingReason:error?.message || 'Warehouse refresh failed'} : s));
    }
  };

  const handleFirstCountClick = () => {
    if (!item || !canFirstCount) return;
    if (onSelectFirstCount) onSelectFirstCount(item, selectedStoreId, selectedWarehouseId);
    else onOpenFirstCount?.(item, selectedStoreId, selectedWarehouseId);
  };

  const handleAllocateClick = () => {
    if (!item || !canAllocate) return;
    if (onSelectAllocateStock) onSelectAllocateStock(item);
    else onOpenAllocateStock?.(item);
  };

  const handleIntakeClick = () => {
    if (!item || !canAdjust) return;
    if (onSelectIntake) onSelectIntake(item, selectedStoreId, selectedWarehouseId);
    else onOpenIntake?.(item, 'intake', selectedStoreId, selectedWarehouseId);
  };

  const handleAdjustmentClick = () => {
    if (!item || !canAdjust) return;
    if (onSelectAdjustment) onSelectAdjustment(item, selectedStoreId, selectedWarehouseId);
    else onOpenIntake?.(item, 'adjustment', selectedStoreId, selectedWarehouseId);
  };

  const handleTransferClick = () => {
    if (!item || !canTransfer) return;
    if (onSelectTransfer) onSelectTransfer(item, selectedStoreId, selectedWarehouseId);
    else onOpenTransfer?.(item, selectedStoreId, selectedWarehouseId);
  };

  // Safe early return after all React hooks are executed unconditionally
  if (!isOpen || !item) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-black/80 backdrop-blur-sm animate-in fade-in duration-200">
      {/* Backdrop click to dismiss */}
      <div className="fixed inset-0" onClick={onClose} aria-hidden="true" />

      {/* Centered Modal Window Container */}
      <div 
        className="relative w-full max-w-6xl bg-neutral-900 border border-neutral-750 rounded-2xl shadow-2xl max-h-[88vh] flex flex-col overflow-hidden text-neutral-100 z-10 animate-in zoom-in-95 duration-150"
        role="dialog"
        aria-modal="true"
      >
        {/* Purple top accent line matching JW Studio enterprise modal system */}
        <div className="h-1 bg-gradient-to-r from-purple-600 via-indigo-500 to-purple-400 w-full shrink-0" />

        {/* Modal Header */}
        <div className="px-6 py-3.5 border-b border-neutral-800 flex flex-wrap items-center justify-between gap-4 bg-neutral-900/95">
          <div className="flex items-center gap-3.5 min-w-0">
            <div className="w-12 h-12 rounded-xl bg-neutral-800 border border-neutral-700 overflow-hidden shrink-0 flex items-center justify-center">
              {item.imageUrl ? (
                <img src={item.imageUrl} alt={item.productName} className="w-full h-full object-cover" />
              ) : (
                <Boxes className="w-6 h-6 text-neutral-400" />
              )}
            </div>
            <div className="min-w-0">
              <div className="flex items-center gap-2">
                <span className="font-mono font-bold text-sm text-white tracking-wide">
                  {item.masterSku}
                </span>
                <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-neutral-800 border border-neutral-700 text-neutral-300">
                  {mode === 'shared' ? 'Shared Stock Pool' : 'Independent Warehouse Mode'}
                </span>
                <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-950/60 border border-purple-800/60 text-purple-300 flex items-center gap-1">
                  <Warehouse className="w-3 h-3" />
                  <span>{storeConfigs.some(store=>store.isSkuMapped&&store.warehouseMode==='multi_warehouse')
                    ? 'Multi-Warehouse' : storeConfigs.some(store=>store.isSkuMapped&&store.warehouseMode==='single_warehouse')
                      ? 'Single-Warehouse' : 'No Platform Warehouse'}</span>
                </span>
              </div>
              <h2 className="text-sm font-semibold text-neutral-200 truncate max-w-xl mt-0.5">
                {item.productName}
              </h2>
              <div className="text-xs text-neutral-400 flex items-center gap-2 mt-0.5">
                <span>{item.variantName}</span>
                {item.barcode && <span>• Barcode: <span className="font-mono">{item.barcode}</span></span>}
              </div>
            </div>
          </div>

          {/* Right Action & Close */}
          <div className="flex items-center gap-3">
            <button
              type="button"
              onClick={() => setIsLedgerOpen(true)}
              className="px-3 py-1.5 rounded-xl bg-neutral-800 hover:bg-neutral-750 border border-neutral-700 text-xs font-semibold text-neutral-300 hover:text-white transition flex items-center gap-1.5 cursor-pointer"
              title="View warehouse ledger transaction history"
            >
              <History className="w-3.5 h-3.5 text-neutral-400" />
              <span>Warehouse Ledger</span>
            </button>
            <button
              type="button"
              onClick={onClose}
              className="p-2 rounded-xl text-neutral-400 hover:text-white hover:bg-neutral-800 transition-colors cursor-pointer"
            >
              <X className="w-5 h-5" />
            </button>
          </div>
        </div>

        {/* Read-Only Live Stock Summary Bar (Rule: Summary numbers are strictly read-only, never editable inputs) */}
        <div className="px-6 py-2.5 bg-neutral-950 border-b border-neutral-800/80 flex flex-wrap items-center justify-between gap-4 text-xs">
          <div className="flex items-center gap-6">
            <div className="flex items-center gap-2">
              <span className="text-neutral-400">Total Physical:</span>
              <span className="font-mono font-bold text-white text-sm">{hasSkuBalances ? totalPhysical : 'Unknown'}</span>
              <span className="text-[11px] text-neutral-500">units</span>
            </div>
            <div className="w-px h-3.5 bg-neutral-800" />
            <div className="flex items-center gap-2">
              <span className="text-neutral-400">Locked In Orders (Reserved):</span>
              <span className="font-mono font-bold text-amber-400 text-sm">{hasSkuBalances ? totalReserved : 'Unknown'}</span>
              <span className="text-[11px] text-neutral-500">units</span>
            </div>
            <div className="w-px h-3.5 bg-neutral-800" />
            <div className="flex items-center gap-2">
              <span className="text-neutral-400">Available to Sell (ATP):</span>
              <span className="font-mono font-bold text-emerald-400 text-base">{hasSkuBalances ? totalAvailable : 'Unknown'}</span>
              <span className="text-[11px] text-neutral-500">sellable</span>
            </div>
          </div>

          <div className="flex items-center gap-2 text-neutral-400 text-[11px]">
            <span className="inline-block w-2 h-2 rounded-full bg-emerald-500" />
            <span>Summary calculated dynamically from {totalWarehousesCount} registered inventory locations across {storeConfigs.length} connected stores</span>
          </div>
        </div>

        {/* Warehouse Search */}
        <div className="px-6 py-2.5 bg-neutral-900 border-b border-neutral-800 flex items-center justify-end gap-3 text-xs">
          <div className="relative w-64">
            <Search className="w-3.5 h-3.5 absolute left-3 top-2.5 text-neutral-500" />
            <input
              type="text"
              placeholder="Search warehouse code, location ID..."
              value={searchQuery}
              onChange={e => setSearchQuery(e.target.value)}
              className="w-full pl-9 pr-3 py-1.5 text-xs rounded-xl bg-neutral-950 border border-neutral-750 text-white placeholder:text-neutral-500 focus:outline-hidden focus:border-purple-500"
            />
          </div>
        </div>

        {/* Drawer Scrollable Content: Stores > Platform Warehouses */}
        <div className="flex-1 overflow-y-auto p-6 space-y-4">
          {filteredStores.map(store => {
            const isExpanded = expandedStoreIds[store.storeId] ?? true;
            const isVerificationPending = store.warehouseSyncStatus === 'verification_pending';
            const isUnmapped = !store.isSkuMapped;

            // Compute store level physical, reserved, available sum
            let storePhysical = 0;
            let storeReserved = 0;
            let storeAvailable = 0;

            if (store.warehouseMode === 'no_warehouse') {
              const directBal = currentSkuBalances[`direct:${store.storeId}`];
              storePhysical = directBal?.physicalStock ?? 0;
              storeReserved = directBal?.reservedStock ?? 0;
              storeAvailable = directBal?.availableStock ?? 0;
            } else {
              store.warehouses.forEach(wh => {
                const bal = currentSkuBalances[wh.id];
                if (bal && bal.physicalStock !== null) {
                  storePhysical += bal.physicalStock;
                  storeReserved += bal.reservedStock;
                  storeAvailable += (bal.availableStock ?? 0);
                }
              });
            }

            return (
              <div 
                key={store.storeId}
                className="bg-neutral-950/70 rounded-2xl border border-neutral-800 overflow-hidden transition-all"
              >
                {/* Store Header Bar */}
                <div 
                  onClick={() => toggleExpandStore(store.storeId)}
                  className="px-5 py-3.5 bg-neutral-900/80 hover:bg-neutral-850 flex flex-wrap items-center justify-between gap-3 cursor-pointer select-none border-b border-neutral-800/80"
                >
                  <div className="flex items-center gap-3">
                    <MarketplaceLogo platform={store.platform as any} size="sm" />
                    <div>
                      <div className="flex items-center gap-2">
                        <span className="font-bold text-neutral-100 text-sm">
                          {store.storeNickname}
                        </span>
                        <span className="text-[11px] text-neutral-400">
                          ({store.storeName})
                        </span>

                        {/* Store Mode Badge */}
                        {store.warehouseMode === 'no_warehouse' && !isVerificationPending && (
                          <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-neutral-800 text-neutral-300 border border-neutral-700">
                            No Platform Warehouse
                          </span>
                        )}
                        {store.warehouseMode === 'no_warehouse' && isVerificationPending && (
                          <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-950/60 text-amber-300 border border-amber-800/60">
                            Warehouse Status Unknown
                          </span>
                        )}
                        {store.warehouseMode === 'single_warehouse' && (
                          <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-950/60 text-blue-300 border border-blue-800/60">
                            1 Registered Warehouse
                          </span>
                        )}
                        {store.warehouseMode === 'multi_warehouse' && (
                          <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-950/60 text-purple-300 border border-purple-800/60">
                            {store.warehouses.length} Platform Warehouses
                          </span>
                        )}

                        {/* Status Warnings */}
                        {isVerificationPending && (
                          <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-950/60 text-amber-300 border border-amber-800/60 flex items-center gap-1">
                            <AlertTriangle className="w-3 h-3 text-amber-400" />
                            <span>Warehouse verification pending (Verification Pending)</span>
                          </span>
                        )}
                        {isUnmapped && (
                          <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-orange-950/60 text-orange-300 border border-orange-800/60 flex items-center gap-1">
                            <ShieldAlert className="w-3 h-3 text-orange-400" />
                            <span>SKU Not Mapped</span>
                          </span>
                        )}
                      </div>
                    </div>
                  </div>

                  {/* Right: Read-only Store Stock Summary & Expand arrow */}
                  <div className="flex items-center gap-5">
                    {!isVerificationPending && !isUnmapped && (
                      <div className="flex items-center gap-4 text-xs font-mono">
                        <div>
                          <span className="text-neutral-500 font-sans text-[11px] mr-1">Phys:</span>
                          <span className="font-bold text-neutral-200">{storePhysical}</span>
                        </div>
                        <div>
                          <span className="text-neutral-500 font-sans text-[11px] mr-1">Res:</span>
                          <span className="font-bold text-amber-400">{storeReserved}</span>
                        </div>
                        <div>
                          <span className="text-neutral-500 font-sans text-[11px] mr-1">Avail:</span>
                          <span className="font-bold text-emerald-400">{storeAvailable}</span>
                        </div>
                      </div>
                    )}
                    <button type="button" className="text-neutral-400 hover:text-white">
                      {isExpanded ? <ChevronUp className="w-4 h-4" /> : <ChevronDown className="w-4 h-4" />}
                    </button>
                  </div>
                </div>

                {/* Collapsible Store Body */}
                {isExpanded && (
                  <div className="p-4 space-y-3">
                    {/* Special Warning 1: Warehouse verification pending */}
                    {isVerificationPending && (
                      <div className="p-3.5 rounded-xl bg-amber-950/40 border border-amber-800/60 text-amber-200 text-xs flex items-start justify-between gap-3">
                        <div className="flex items-start gap-2.5">
                          <AlertTriangle className="w-4 h-4 text-amber-400 shrink-0 mt-0.5" />
                          <div>
                            <div className="font-bold text-amber-300">
                              Warehouse verification pending — stock operations paused
                            </div>
                            <p className="text-amber-200/80 mt-0.5">
                              {store.verificationPendingReason || 'Platform warehouse access failed or timed out; warehouse inventory cannot be verified.'}
                            </p>
                            <p className="text-[11px] text-amber-400/70 mt-1">
                              <strong>Safety rule: </strong>First Count, Allocate Stock, Intake, Audit Adjustment, Transfer and platform stock push are paused for this store. Unknown stock stays unknown; no fallback to no-warehouse mode or zero.
                            </p>
                          </div>
                        </div>
                        <button
                          type="button"
                          disabled
                          title="Available after a verified warehouse API connection is implemented"
                          className="px-3 py-1.5 rounded-lg bg-amber-600 text-white font-bold text-xs shrink-0 transition flex items-center gap-1 opacity-50 cursor-not-allowed"
                        >
                          <RefreshCw className="w-3.5 h-3.5" />
                          <span>Retry Verification</span>
                        </button>
                      </div>
                    )}

                    {/* Special Warning 2: SKU Not Mapped */}
                    {isUnmapped && (
                      <div className="p-3.5 rounded-xl bg-orange-950/40 border border-orange-800/60 text-orange-200 text-xs flex items-start justify-between gap-3">
                        <div className="flex items-start gap-2.5">
                          <ShieldAlert className="w-4 h-4 text-orange-400 shrink-0 mt-0.5" />
                          <div>
                            <div className="font-bold text-orange-300">
                              SKU Not Mapped to This Store
                            </div>
                            <p className="text-orange-200/80 mt-0.5">
                              {store.unmappedReason || 'Map this marketplace SKU to a JW Master SKU before recording or allocating stock in this store.'}
                            </p>
                          </div>
                        </div>
                        {onNavigateToMapping && (
                          <button
                            type="button"
                            onClick={onNavigateToMapping}
                            className="px-3 py-1.5 rounded-lg bg-orange-600 hover:bg-orange-500 text-white font-bold text-xs shrink-0 transition flex items-center gap-1 cursor-pointer"
                          >
                            <ExternalLink className="w-3.5 h-3.5" />
                            <span>Go to SKU Mapping</span>
                          </button>
                        )}
                      </div>
                    )}

                    {/* Scenario 1: No Platform Warehouse (无仓) */}
                    {store.warehouseMode === 'no_warehouse' && !isVerificationPending && (
                      <div 
                        onClick={() => handleSelectWarehouse(store.storeId, `direct:${store.storeId}`)}
                        className={`p-3.5 rounded-xl border transition-all cursor-pointer flex flex-wrap items-center justify-between gap-4 ${
                          selectedWarehouseId === `direct:${store.storeId}` && selectedStoreId === store.storeId
                            ? 'bg-purple-950/30 border-purple-500/80 shadow-xs'
                            : 'bg-neutral-900 border-neutral-800 hover:border-neutral-700'
                        }`}
                      >
                        <div className="flex items-center gap-3">
                          <input
                            type="radio"
                            name="selectedWarehouse"
                            checked={selectedWarehouseId === `direct:${store.storeId}` && selectedStoreId === store.storeId}
                            onChange={() => handleSelectWarehouse(store.storeId, `direct:${store.storeId}`)}
                            className="w-4 h-4 text-purple-600 focus:ring-purple-500 cursor-pointer"
                          />
                          <div>
                            <div className="flex items-center gap-2">
                              <span className="font-bold text-white text-xs">Direct Online Inventory (Storefront Stock)</span>
                              <span className="px-1.5 py-0.2 rounded text-[10px] font-mono bg-neutral-800 text-neutral-300">
                                DIRECT-WEB
                              </span>
                            </div>
                            <div className="text-[11px] text-neutral-400 mt-0.5">
                              No platform multi-warehouse routing. Orders directly deduct from this account ledger.
                            </div>
                          </div>
                        </div>

                        {/* Stock metrics */}
                        <div className="flex items-center gap-6 text-xs font-mono">
                          <div>
                            <span className="text-[10px] text-neutral-400 font-sans block">Physical</span>
                            <span className="font-bold text-white text-sm">
                              {currentSkuBalances[`direct:${store.storeId}`]?.physicalStock ?? 'N/A'}
                            </span>
                          </div>
                          <div>
                            <span className="text-[10px] text-neutral-400 font-sans block">Reserved</span>
                            <span className="font-bold text-amber-400 text-sm">
                              {currentSkuBalances[`direct:${store.storeId}`]?.reservedStock ?? 0}
                            </span>
                          </div>
                          <div>
                            <span className="text-[10px] text-neutral-400 font-sans block">Available (ATP)</span>
                            <span className="font-bold text-emerald-400 text-base">
                              {currentSkuBalances[`direct:${store.storeId}`]?.availableStock ?? 'N/A'}
                            </span>
                          </div>
                          <div className="flex items-center gap-1 text-[10px] font-sans text-neutral-400 bg-neutral-800 px-2 py-1 rounded-md border border-neutral-700">
                            <span>Stock sync status requires provider check</span>
                          </div>
                        </div>
                      </div>
                    )}

                    {/* Scenario 2 & 3: Single Warehouse & Multi-Warehouse List */}
                    {store.warehouseMode !== 'no_warehouse' && (
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                        {store.warehouses.map(wh => {
                          const bal = currentSkuBalances[wh.id];
                          const isSelected = selectedWarehouseId === wh.id && selectedStoreId === store.storeId;
                          const isPushFailed = bal?.syncState === 'push_failed';
                          const isPushQueued = bal?.syncState === 'push_queued';
                          const isUnverified = bal?.syncState === 'verification_pending' || isVerificationPending;
                          const isWhUnmapped = bal?.syncState === 'unmapped' || isUnmapped;

                          return (
                            <div
                              key={wh.id}
                              onClick={() => {
                                if (!isUnverified && !isWhUnmapped) {
                                  handleSelectWarehouse(store.storeId, wh.id);
                                }
                              }}
                              className={`p-3.5 rounded-xl border transition-all ${
                                isUnverified || isWhUnmapped
                                  ? 'opacity-60 bg-neutral-900/50 border-neutral-800 cursor-not-allowed'
                                  : isSelected
                                  ? 'bg-purple-950/30 border-purple-500 shadow-md cursor-pointer'
                                  : 'bg-neutral-900 border-neutral-800 hover:border-neutral-700 cursor-pointer'
                              }`}
                            >
                              {/* Top row: Radio + Name + Platform ID Tag */}
                              <div className="flex items-start justify-between gap-2">
                                <div className="flex items-start gap-2.5 min-w-0">
                                  <input
                                    type="radio"
                                    name="selectedWarehouse"
                                    disabled={isUnverified || isWhUnmapped}
                                    checked={isSelected}
                                    onChange={() => handleSelectWarehouse(store.storeId, wh.id)}
                                    className="w-4 h-4 text-purple-600 focus:ring-purple-500 mt-0.5 cursor-pointer disabled:opacity-40"
                                  />
                                  <div className="min-w-0">
                                    <div className="flex items-center gap-1.5 flex-wrap">
                                      <span className="font-bold text-xs text-white truncate">
                                        {wh.name}
                                      </span>
                                      {wh.isDefault && (
                                        <span className="text-[9px] font-bold px-1.5 py-0.2 rounded bg-neutral-800 text-neutral-300">
                                          Default
                                        </span>
                                      )}
                                    </div>
                                    <div className="flex items-center gap-1.5 text-[10px] text-neutral-400 mt-0.5">
                                      <span className="px-1.5 py-0.2 rounded font-mono font-bold bg-neutral-800 text-purple-300 border border-purple-900/40">
                                        {wh.codeType === 'location_id' ? 'location_id' : 'warehouse_code'}: {wh.codeValue}
                                      </span>
                                      <span className="truncate">{wh.region}</span>
                                    </div>
                                  </div>
                                </div>

                                {/* Platform Sync Tag */}
                                <div className="shrink-0">
                                  {isPushFailed ? (
                                    <span className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-950/60 text-rose-300 border border-rose-800/60">
                                      <AlertCircle className="w-3 h-3 text-rose-400" />
                                      <span>Push Failed</span>
                                    </span>
                                  ) : isPushQueued ? (
                                    <span className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-950/60 text-amber-300 border border-amber-800/60">
                                      <Clock className="w-3 h-3 text-amber-400 animate-spin" />
                                      <span>Push Queued</span>
                                    </span>
                                  ) : isUnverified ? (
                                    <span className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-neutral-800 text-neutral-400">
                                      <Lock className="w-3 h-3" />
                                      <span>Paused</span>
                                    </span>
                                  ) : isWhUnmapped ? (
                                    <span className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-neutral-800 text-neutral-400">
                                      <span>Unmapped</span>
                                    </span>
                                  ) : bal?.syncState === 'sync_unknown' ? (
                                    <span className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-neutral-800 text-neutral-300">
                                      <span>Sync not checked</span>
                                    </span>
                                  ) : (
                                    <span className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-950/60 text-emerald-300 border border-emerald-800/60">
                                      <CheckCircle2 className="w-3 h-3 text-emerald-400" />
                                      <span>Synced</span>
                                    </span>
                                  )}
                                </div>
                              </div>

                              {/* Mid row: Physical, Reserved, Available */}
                              <div className="mt-3 pt-2.5 border-t border-neutral-800/70 flex items-center justify-between text-xs font-mono">
                                <div>
                                  <span className="text-[10px] font-sans text-neutral-500 block">Physical</span>
                                  <span className="font-bold text-white text-sm">
                                    {isUnverified ? (
                                      <span className="text-amber-400 text-xs italic">Pending</span>
                                    ) : (
                                      bal?.physicalStock ?? 'N/A'
                                    )}
                                  </span>
                                </div>
                                <div>
                                  <span className="text-[10px] font-sans text-neutral-500 block">Reserved</span>
                                  <span className="font-bold text-amber-400 text-sm">
                                    {isUnverified ? '-' : bal?.reservedStock ?? 0}
                                  </span>
                                </div>
                                <div>
                                  <span className="text-[10px] font-sans text-neutral-500 block">Available (ATP)</span>
                                  <span className="font-bold text-emerald-400 text-base">
                                    {isUnverified ? (
                                      <span className="text-amber-400 text-xs italic">Verification pending</span>
                                    ) : (
                                      bal?.availableStock ?? 'N/A'
                                    )}
                                  </span>
                                </div>
                              </div>

                              {/* Push Failed Alert and Retry Action */}
                              {isPushFailed && bal?.failureReason && (
                                <div className="mt-2.5 p-2 rounded-lg bg-rose-950/40 border border-rose-900/50 text-[11px] text-rose-300 flex items-center justify-between gap-2">
                                  <span className="truncate">{bal.failureReason}</span>
                                  <button
                                    type="button"
                                    disabled
                                    title="Available after a verified stock-push API connection is implemented"
                                    className="px-2 py-0.5 rounded bg-rose-600 text-white font-bold shrink-0 transition flex items-center gap-1 opacity-50 cursor-not-allowed"
                                  >
                                    <RefreshCw className={`w-3 h-3 ${retryingWarehouseId === wh.id ? 'animate-spin' : ''}`} />
                                    <span>Retry Push</span>
                                  </button>
                                </div>
                              )}
                            </div>
                          );
                        })}
                      </div>
                    )}
                  </div>
                )}
              </div>
            );
          })}
        </div>

        {/* Selected Warehouse Action Bar Footer */}
        <div className="shrink-0 px-6 py-4 bg-neutral-950/95 border-t border-neutral-800 rounded-b-2xl flex flex-wrap items-center justify-between gap-4">
          {/* Left: Current Active Target Affordance */}
          <div className="flex items-center gap-3">
            <div className="w-8 h-8 rounded-lg bg-neutral-800 flex items-center justify-center text-purple-400 shrink-0">
              <Warehouse className="w-4 h-4" />
            </div>
            <div>
              <div className="text-[11px] text-neutral-400">Target Selected Facility:</div>
              <div className="flex items-center gap-2">
                <span className="font-bold text-xs text-white">
                  {selectedWarehouseObj?.name || 'Please select a facility above'}
                </span>
                {selectedWarehouseObj && (
                  <span className="px-1.5 py-0.2 rounded text-[10px] font-mono bg-purple-950/60 text-purple-300 border border-purple-800/60">
                    {selectedWarehouseObj.codeValue}
                  </span>
                )}
              </div>
            </div>
          </div>

          {/* Right: Operational Buttons explicitly targeting the selected facility */}
          <div className="flex flex-wrap items-center gap-2">
            {!selectedStoreReady && (
              <span className="text-xs font-semibold text-amber-400">
                {!selectedWarehouseObj ? 'Select a facility' : !selectedWarehouseObj.store.isSkuMapped
                  ? 'Map this SKU before managing stock'
                  : 'inventoryWarehouseId' in selectedWarehouseObj && !selectedWarehouseObj.inventoryWarehouseId
                    ? 'Link a JW physical warehouse before managing stock'
                    : selectedWarehouseObj.store.verificationPendingReason || 'Warehouse verification pending'}
              </span>
            )}
            {canFirstCount && (
            <button
              type="button"
              onClick={handleFirstCountClick}
              className="px-3.5 py-2 rounded-xl bg-neutral-850 hover:bg-neutral-800 border border-neutral-700 text-xs font-bold text-neutral-200 hover:text-white transition flex items-center gap-1.5 cursor-pointer shadow-xs"
              title="Establish auditable initial count for this facility"
            >
              <ClipboardCheck className="w-3.5 h-3.5 text-amber-400" />
              <span>First Count</span>
            </button>
            )}

            {canAllocate && (
            <button
              type="button"
              onClick={handleAllocateClick}
              className="px-3.5 py-2 rounded-xl bg-neutral-850 hover:bg-neutral-800 border border-neutral-700 text-xs font-bold text-neutral-200 hover:text-white transition flex items-center gap-1.5 cursor-pointer shadow-xs"
              title="Distribute inventory across stores and individual warehouses"
            >
              <Layers className="w-3.5 h-3.5 text-blue-400" />
              <span>Allocate Stock</span>
            </button>
            )}

            {canAdjust && (
            <button
              type="button"
              onClick={handleIntakeClick}
              className="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-xs"
              title="Record inbound PO units to this facility"
            >
              <PlusCircle className="w-3.5 h-3.5" />
              <span>Actual Intake (+Qty)</span>
            </button>
            )}

            {canAdjust && (
            <button
              type="button"
              onClick={handleAdjustmentClick}
              className="px-3.5 py-2 rounded-xl bg-neutral-800 hover:bg-neutral-750 border border-neutral-700 text-xs font-bold text-neutral-200 hover:text-white transition flex items-center gap-1.5 cursor-pointer shadow-xs"
              title="Adjust physical count for this facility"
            >
              <Sliders className="w-3.5 h-3.5 text-neutral-400" />
              <span>Audit Adjustment</span>
            </button>
            )}

            {canTransfer && (
            <button
              type="button"
              onClick={handleTransferClick}
              className="px-3.5 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-xs"
              title="Rebalance stock from this facility to another store/warehouse"
            >
              <ArrowRightLeft className="w-3.5 h-3.5" />
              <span>Transfer Stock</span>
            </button>
            )}
          </div>
          {selectedWarehouseObj && 'inventoryWarehouseId' in selectedWarehouseObj
            && !selectedWarehouseObj.inventoryWarehouseId && selectedWarehouseObj.store.warehouseSyncStatus==='synced' && (
            <div className="mt-3 flex flex-wrap items-center gap-2 text-xs text-neutral-300">
              <label htmlFor="physical-warehouse-link">JW physical warehouse</label>
              <select id="physical-warehouse-link" value={physicalWarehouseId}
                onChange={event=>{setPhysicalWarehouseId(event.target.value);setBindingReview(null)}}
                className="bg-neutral-900 border border-neutral-700 rounded px-2 py-1">
                <option value="">Select a verified JW warehouse</option>
                {physicalWarehouses.map(warehouse=><option key={warehouse.id} value={warehouse.id}>{warehouse.name} ({warehouse.code})</option>)}
              </select>
              <button type="button" disabled={bindingBusy||!physicalWarehouseId}
                onClick={()=>void bindSelectedFacility()}
                className="px-2 py-1 rounded border border-purple-700 disabled:opacity-40">Review & Link</button>
              {bindingReview?.storeId===selectedStoreId && bindingReview.facilityId===selectedWarehouseId
                && bindingReview.warehouseId===physicalWarehouseId && (
                <div className="w-full rounded-xl border border-purple-700 bg-purple-950/30 p-3 space-y-2">
                  <p>Confirm route: {selectedWarehouseObj.name} ({selectedWarehouseObj.codeValue}) → {physicalWarehouses.find(warehouse=>String(warehouse.id)===physicalWarehouseId)?.name}.</p>
                  <p>Stock quantities will not change. This route will determine future facility inventory operations.</p>
                  <button type="button" disabled={bindingBusy} onClick={()=>void confirmSelectedBinding()}
                    className="px-2 py-1 rounded border border-purple-700 disabled:opacity-40">Confirm Warehouse Link</button>
                </div>
              )}
              {bindingError && <span role="alert" className="text-rose-400">{bindingError}</span>}
            </div>
          )}
          {selectedStoreReady && /^\d+$/.test(selectedWarehouseId) && (
            <div className="mt-3 flex flex-wrap items-center gap-2 text-[11px] text-neutral-300">
              <span>Platform stock: {facilitySync?.marketplace_reported_stock ?? 'Unknown'}</span>
              <span>· JW ATP: {facilitySync?.jw_available_stock ?? 'Not counted'}</span>
              <span>· Status: {facilitySync?.status?.replaceAll('_',' ') ?? 'Loading'}</span>
              <button type="button" disabled={facilitySyncBusy || !facilitySync?.can_check}
                onClick={()=>void refreshExactSync(false)} className="px-2 py-1 rounded border border-neutral-700 disabled:opacity-40">Check Status</button>
              <button type="button" disabled={facilitySyncBusy || !facilitySync?.can_resync}
                onClick={()=>void refreshExactSync(true)} className="px-2 py-1 rounded border border-neutral-700 disabled:opacity-40">Resync Stock</button>
              {facilitySyncError && <span role="alert" className="text-rose-400">{facilitySyncError}</span>}
            </div>
          )}
        </div>
      </div>

      {/* Slide-over Warehouse Ledger Drawer */}
      {isLedgerOpen && (
        <div className="fixed inset-0 z-60 flex justify-end bg-black/50 backdrop-blur-xs">
          <div className="w-full max-w-xl bg-neutral-900 border-l border-neutral-800 h-full flex flex-col p-6 shadow-2xl text-neutral-100 animate-in slide-in-from-right duration-200">
            <div className="flex items-center justify-between pb-4 border-b border-neutral-800">
              <div className="flex items-center gap-2">
                <History className="w-5 h-5 text-purple-400" />
                <h3 className="font-bold text-base text-white">Warehouse Stock Ledger</h3>
              </div>
              <button
                type="button"
                onClick={() => setIsLedgerOpen(false)}
                className="p-1.5 rounded-lg text-neutral-400 hover:text-white hover:bg-neutral-800"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <p className="text-xs text-neutral-400 mt-2">
              Auditable transaction stream tracking each unit mutation per physical warehouse code / location ID.
            </p>

            <div className="flex-1 overflow-y-auto mt-4 space-y-3 pr-1">
              {ledgerEntries.map(entry => (
                <div key={entry.id} className="p-3.5 rounded-xl bg-neutral-950 border border-neutral-800 text-xs space-y-1.5">
                  <div className="flex items-center justify-between">
                    <span className="font-mono text-[10px] text-neutral-500">{entry.id}</span>
                    <span className="text-[11px] text-neutral-400">
                      {new Date(entry.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                    </span>
                  </div>
                  <div className="flex items-center justify-between">
                    <div className="font-bold text-neutral-200 flex items-center gap-1.5">
                      <span>{entry.warehouseName}</span>
                      <span className="font-mono text-[10px] text-purple-300 bg-purple-950/60 px-1.5 py-0.2 rounded border border-purple-900/40">
                        {entry.codeValue}
                      </span>
                    </div>
                    <span className={`font-mono font-bold ${entry.quantityChange >= 0 ? 'text-emerald-400' : 'text-amber-400'}`}>
                      {entry.quantityChange > 0 ? `+${entry.quantityChange}` : entry.quantityChange} units
                    </span>
                  </div>
                  <p className="text-neutral-400 text-[11px]">{entry.reason}</p>
                  <div className="flex items-center justify-between text-[10px] text-neutral-500 pt-1 border-t border-neutral-900">
                    <span>Operator: {entry.operator}</span>
                    <span>Resulting Phys: {entry.resultingPhysical} (Avail: {entry.resultingAvailable})</span>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
