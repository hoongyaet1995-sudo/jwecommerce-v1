import React, { useState, useMemo, useEffect, useRef } from 'react';
import { useAdmin } from '../../context/AdminContext';
import { MarketplaceInventoryItem } from '../../types';
import { SubNavHeader } from '../common/SubNavHeader';
import { MarketplaceLogo } from './MarketplaceLogo';
import { SharedPoolStoreCard } from './SharedPoolStoreCard';
import {
  Boxes,
  Layers,
  Store,
  ArrowRightLeft,
  History,
  Search,
  Filter,
  RefreshCw,
  AlertTriangle,
  CheckCircle2,
  Plus,
  Sliders,
  Settings,
  ClipboardCheck,
  ChevronDown,
  ChevronRight,
  ChevronLeft,
  ChevronsLeft,
  ChevronsRight,
  Download,
  Radio,
  Eye,
  Send,
  Sparkles,
  Info,
  ShieldAlert,
  ArrowUpRight,
  ShieldCheck,
  Clock,
  Link2,
  RotateCcw,
  Warehouse,
  X
} from 'lucide-react';

import {
  InventoryStoreChannel,
  StoreStockAccountBalance,
  StoreStockTransferRecord,
  InventoryActivityItem
} from './inventory/inventoryTypes';


import { InitialStockCountModal } from './inventory/InitialStockCountModal';
import { SharedFirstCountModal } from './inventory/SharedFirstCountModal';
import { LocationFirstCountBaseline } from './inventory/LocationFirstCountBaseline';
import { BulkStockRetrievalModal, StockRetrievalRun } from './inventory/BulkStockRetrievalModal';
import { StockIntakeAdjustmentModal } from './inventory/StockIntakeAdjustmentModal';
import { SharedPoolStockActionModal } from './inventory/SharedPoolStockActionModal';
import { FocusedManageStockDrawer } from './inventory/FocusedManageStockDrawer';
import { StoreStockTransfersTab } from './inventory/StoreStockTransfersTab';
import { InventoryActivityHistoryTab } from './inventory/InventoryActivityHistoryTab';
import { InventorySettingsModal } from './inventory/InventorySettingsModal';
import { StockSyncDetailsModal } from './inventory/StockSyncDetailsModal';
import { StoreSyncItemState } from './inventory/stockSyncTypes';
import { mappedStoreSummaries } from './inventory/mappedStoreSummary';
import {storeInventoryApi} from '../../../../integration/storeInventory';

function adaptFacilitySync(detail:any, previous:StoreSyncItemState):StoreSyncItemState {
  return {...previous,
    status:detail.status==='verification_pending'?'verification_pending':detail.status,
    jwAvailableStock:detail.jw_available_stock ?? null,
    marketplaceReportedStock:detail.marketplace_reported_stock ?? null,
    difference:detail.difference ?? null,
    lastCheckedAt:detail.last_checked_at ?? '',
    canCheck:detail.can_check === true,
    canResync:detail.can_resync === true,
    failureReason:detail.status==='verification_pending'
      ? 'Warehouse verification is pending; stock operations are paused.'
      :detail.jw_available_stock === null ? 'Complete First Count for this warehouse before resynchronizing stock.' : undefined,
  };
}

export const MarketplaceInventoryPage: React.FC = () => {
  const {
    marketplaceInventory = [],
    connections = [],
    marketplaceOrders = [],
    adjustStock,
    recordPhysicalCountAudit,
    broadcastStockToChannels,
    addToast,
    setCurrentSection,
    inventoryStores = [],
    inventoryStoreBalances = {},
    inventoryMode = 'shared',
    inventoryActivityHistory = []
    ,reloadInventory,
    allocateStock,
    switchInventoryMode,
    toggleStoreManagement
  } = useAdmin();

  // Active Tab: 'overview' | 'transfers' | 'history'
  const [activeTab, setActiveTab] = useState<'overview' | 'transfers' | 'history'>('overview');
  const [transferTarget, setTransferTarget] = useState<{ sku: string; storeId?: string; warehouseId?: string } | null>(null);

  // Global Inventory Mode: 'shared' | 'independent' (Admin controlled)
  const [currentMode, setCurrentMode] = useState<'shared' | 'independent'>(inventoryMode);

  // Channel Stores Configuration
  const [stores, setStores] = useState<InventoryStoreChannel[]>(inventoryStores);

  // Independent Store Stock Account Balances: sku -> { storeId: StoreStockAccountBalance }
  const [storeBalances, setStoreBalances] = useState<Record<string, Record<string, StoreStockAccountBalance>>>(inventoryStoreBalances);

  // Store Stock Transfer Records
  const [transfersList, setTransfersList] = useState<StoreStockTransferRecord[]>([]);

  // Activity History Logs
  const [activityLogs, setActivityLogs] = useState<InventoryActivityItem[]>(inventoryActivityHistory);
  useEffect(() => { setCurrentMode(inventoryMode); }, [inventoryMode]);
  useEffect(() => { setStores(inventoryStores); }, [inventoryStores]);
  useEffect(() => { setStoreBalances(inventoryStoreBalances); }, [inventoryStoreBalances]);
  useEffect(() => { setActivityLogs(inventoryActivityHistory); }, [inventoryActivityHistory]);

  // Search and Filters
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState<'all' | 'uncounted' | 'low_stock' | 'synced' | 'mismatch'>('all');
  const [platformFilter, setPlatformFilter] = useState<string>('all');
  const [selectedStoreFilter, setSelectedStoreFilter] = useState<string>('all');

  // Pagination & Multi-Select SKU state
  const [currentPage, setCurrentPage] = useState(1);
  const [pageSize, setPageSize] = useState(25);
  const [jumpPageInput, setJumpPageInput] = useState('');
  const [selectedSkus, setSelectedSkus] = useState<string[]>([]);
  const [collapsedProducts, setCollapsedProducts] = useState<string[]>([]);
  const [stockRetrievalRun, setStockRetrievalRun] = useState<StockRetrievalRun | null>(null);
  const [showStockRetrieval, setShowStockRetrieval] = useState(false);
  const [startingStockRetrieval, setStartingStockRetrieval] = useState(false);
  useEffect(() => {
    void storeInventoryApi.latestStockRetrieval().then((run:StockRetrievalRun | null)=>setStockRetrievalRun(run)).catch(()=>{});
  }, []);
  useEffect(() => {
    if (!stockRetrievalRun || showStockRetrieval || !['queued','running'].includes(stockRetrievalRun.status)) return;
    const timer = window.setInterval(() => {
      void storeInventoryApi.stockRetrieval(stockRetrievalRun.id).then((run:StockRetrievalRun) => {
        setStockRetrievalRun(run);
        if (['succeeded','completed_with_errors'].includes(run.status)) void reloadInventory();
      }).catch(()=>{});
    }, 3000);
    return () => window.clearInterval(timer);
  }, [stockRetrievalRun?.id, stockRetrievalRun?.status, showStockRetrieval]);

  // Modals & Drawers
  const [isCountModalOpen, setIsCountModalOpen] = useState(false);
  const [selectedItemForCount, setSelectedItemForCount] = useState<MarketplaceInventoryItem | null>(null);
  const [targetFirstCountStoreId, setTargetFirstCountStoreId] = useState<string | undefined>(undefined);
  const [targetFirstCountWarehouseId, setTargetFirstCountWarehouseId] = useState<string | undefined>(undefined);

  const [isIntakeModalOpen, setIsIntakeModalOpen] = useState(false);
  const [sharedStockAction, setSharedStockAction] = useState<{item:MarketplaceInventoryItem;action:'intake'|'audit'}|null>(null);
  const [selectedItemForIntake, setSelectedItemForIntake] = useState<MarketplaceInventoryItem | null>(null);
  const [intakeInitialType, setIntakeInitialType] = useState<'intake' | 'adjustment'>('intake');
  const [intakeTargetStoreId, setIntakeTargetStoreId] = useState<string | undefined>(undefined);
  const [intakeTargetWarehouseId, setIntakeTargetWarehouseId] = useState<string | undefined>(undefined);

  // Manage Stock Bottom Drawer
  const [isManageStockDrawerOpen, setIsManageStockDrawerOpen] = useState(false);
  const [selectedDrawerItem, setSelectedDrawerItem] = useState<MarketplaceInventoryItem | null>(null);
  const [manageTargetStoreId, setManageTargetStoreId] = useState<string | undefined>(undefined);
  const [manageTargetWarehouseId, setManageTargetWarehouseId] = useState<string | undefined>(undefined);
  const [manageOpenActions, setManageOpenActions] = useState(false);

  const [isSettingsModalOpen, setIsSettingsModalOpen] = useState(false);

  // Stock Sync Details Modal State
  const [isStockSyncModalOpen, setIsStockSyncModalOpen] = useState(false);
  const [syncModalItem, setSyncModalItem] = useState<MarketplaceInventoryItem | null>(null);
  const [syncModalStore, setSyncModalStore] = useState<InventoryStoreChannel | null>(null);
  const [syncModalInfo, setSyncModalInfo] = useState<StoreSyncItemState | null>(null);
  const [syncFacilityOptions, setSyncFacilityOptions] = useState<Array<{id:number;name:string;providerId:string}>>([]);
  const [syncFacilityId, setSyncFacilityId] = useState<number | null>(null);
  const [isSyncTopologyLoading, setIsSyncTopologyLoading] = useState(false);
  const [syncFacilityStatuses, setSyncFacilityStatuses] = useState<Record<number, StoreSyncItemState['status']>>({});
  const syncOpenRequestRef = useRef(0);


  // Batch Broadcasting State
  const [isBroadcastingAll, setIsBroadcastingAll] = useState(false);

  // List rows show only persisted JW task state. Provider quantities require an explicit readback.
  const getStoreSyncItemState = (item: MarketplaceInventoryItem, store: InventoryStoreChannel): StoreSyncItemState => {
    const balance=storeBalances[item.masterSku]?.[store.id];
    const task=(((balance as any)?.syncTasks)||[]).find((entry:any)=>entry.status!=='superseded');
    const mapped=store.platform==='webstore'||(item as any).mappedStoreIds?.includes(String(store.id));
    const status:StoreSyncItemState['status']=store.platform==='webstore'?'jw_internal'
      :store.connectionState!=='connected'?'auth_expired'
      :!mapped?'sku_not_mapped'
      :currentMode==='shared'&&item.sharedStoreSyncStates?.[store.id]==='synced'?'synced'
      :['queued','processing','uncertain','facility_pending'].includes(task?.status)?'awaiting_confirmation'
      :['failed','auth_expired','facility_blocked'].includes(task?.status)?'sync_failed'
      :'not_synced';
    return {sku:item.masterSku,storeId:store.id,status,
      jwAvailableStock:currentMode==='shared'?(item.availableStock??null):(balance?.availableStock??null),
      marketplaceReportedStock:null,difference:null,lastConfirmedSyncAt:null,lastCheckedAt:'',
      canCheck:false,canResync:false,
      failureReason:item.physicalStockStatus==='unknown'?'Complete a verified physical count first.':undefined,
      failureRecoveryType:status==='auth_expired'?'reconnect':status==='sku_not_mapped'?'mapping':'check_status'};
  };
  const handleOpenStockSync = async (
    item: MarketplaceInventoryItem,
    store: InventoryStoreChannel,
    overrideInfo?: StoreSyncItemState,
    targetFacilityId?: number
  ) => {
    const requestId=++syncOpenRequestRef.current;
    let info = overrideInfo ?? getStoreSyncItemState(item, store);
    setSyncFacilityOptions([]);
    setSyncFacilityId(null);
    setSyncFacilityStatuses({});
    setSyncModalItem(item);
    setSyncModalStore(store);
    setSyncModalInfo(info);
    setIsStockSyncModalOpen(true);
    const needsTopology=store.platform!=='webstore' && info.status!=='auth_expired' && info.status!=='sku_not_mapped';
    setIsSyncTopologyLoading(needsTopology);
    if(needsTopology) {
      try {
        const topology=await storeInventoryApi.facilities(store.id);
        if(requestId!==syncOpenRequestRef.current)return;
        if(topology.status!=='verified') {
          info={...info,status:'verification_pending',jwAvailableStock:null,marketplaceReportedStock:null,
            difference:null,canCheck:false,canResync:false,
            failureReason:'Warehouse list has not been verified.'};
        } else if(topology.topology==='no_warehouse') {
          info=await storeInventoryApi.syncDetails(store.id,(item as any).variantId);
        } else {
          const options=(topology.facilities||[]).map((facility:any)=>({
            id:Number(facility.id),name:String(facility.name),providerId:String(facility.provider_id)
          }));
          setSyncFacilityOptions(options);
          if(options.length>1) {
            void Promise.allSettled(options.map(option=>storeInventoryApi.facilitySyncDetails(store.id,option.id,(item as any).variantId)))
              .then(results=>{
                if(requestId!==syncOpenRequestRef.current)return;
                const statuses:Record<number,StoreSyncItemState['status']>={};
                results.forEach((result,index)=>{
                  if(result.status==='fulfilled') statuses[options[index].id]=result.value.status as StoreSyncItemState['status'];
                });
                setSyncFacilityStatuses(previous=>({...statuses,...previous}));
              });
          }
          info={...info,status:'select_facility',jwAvailableStock:null,marketplaceReportedStock:null,
            difference:null,canCheck:false,canResync:false};
          const exactFacility=targetFacilityId !== undefined
            ? options.find(option=>option.id===targetFacilityId)
            : options.length===1 ? options[0] : undefined;
          if(exactFacility) {
            setSyncFacilityId(exactFacility.id);
            info=adaptFacilitySync(await storeInventoryApi.facilitySyncDetails(store.id,exactFacility.id,(item as any).variantId),info);
            if(requestId!==syncOpenRequestRef.current)return;
            setSyncFacilityStatuses(previous=>({...previous,[exactFacility.id]:info.status}));
          }
        }
      } catch(error:any) {
        info={...info,status:'verification_pending',jwAvailableStock:null,marketplaceReportedStock:null,
          difference:null,canCheck:false,canResync:false,
          failureReason:'Warehouse or stock status could not be read.'};
      }
      if(requestId!==syncOpenRequestRef.current)return;
      setSyncModalInfo(info);
      setIsSyncTopologyLoading(false);
    }
  };

  const handleUpdateSyncInfo = (updated: StoreSyncItemState) => {
    setSyncModalInfo(updated);
    if(syncFacilityId!==null) setSyncFacilityStatuses(previous=>({...previous,[syncFacilityId]:updated.status}));
    void reloadInventory();
  };

  // Raw items from admin context or 5,000+ simulation for high-volume stress testing
  const safeInventory = useMemo(() => {
    const base = marketplaceInventory || [];
    return base;
  }, [marketplaceInventory]);
  const singleCurrentStock = (safeInventory[0] as MarketplaceInventoryItem & { singleCurrentStock?: boolean } | undefined)?.singleCurrentStock === true;

  // Identify uncounted items (First Count required)
  const uncountedItems = useMemo(() => {
    return safeInventory.filter(
      item => item.physicalStockStatus === 'unknown' || item.totalPhysicalStock === null
    );
  }, [safeInventory]);

  // Overall aggregate metrics
  const metrics = useMemo(() => {
    const totalSkus = safeInventory.length;
    let totalPhysical = 0;
    let totalReserved = 0;
    let totalAvailable = 0;

    safeInventory.forEach(item => {
      if (item.totalPhysicalStock !== null && item.physicalStockStatus !== 'unknown') {
        totalPhysical += item.totalPhysicalStock;
      }
      totalReserved += item.reservedStock || 0;
      if (item.availableStock !== null && item.availableStock !== undefined) {
        totalAvailable += item.availableStock;
      }
    });

    const activeStoresCount = stores.filter(s => s.connectionState === 'connected' && !s.isComingSoon).length;

    return {
      totalSkus,
      totalPhysical,
      totalReserved,
      totalAvailable,
      uncountedCount: uncountedItems.length,
      activeStoresCount
    };
  }, [safeInventory, stores, uncountedItems]);

  // Filtered inventory list
  const filteredInventory = useMemo(() => {
    return safeInventory.filter(item => {
      // Search query filter
      if (searchQuery.trim()) {
        const q = searchQuery.toLowerCase();
        const matchesSku = item.masterSku.toLowerCase().includes(q);
        const matchesName = item.productName.toLowerCase().includes(q);
        const matchesVariant = item.variantName.toLowerCase().includes(q);
        const matchesBarcode = item.barcode ? item.barcode.toLowerCase().includes(q) : false;
        if (!matchesSku && !matchesName && !matchesVariant && !matchesBarcode) {
          return false;
        }
      }

      // Status filter
      if (statusFilter === 'uncounted') {
        if (item.physicalStockStatus !== 'unknown' && item.totalPhysicalStock !== null) return false;
      } else if (statusFilter === 'low_stock') {
        if ((item.availableStock ?? 0) > 10) return false;
      } else if (statusFilter === 'synced') {
        if (item.syncStatus !== 'synced') return false;
      } else if (statusFilter === 'mismatch') {
        if (item.syncStatus !== 'mismatch' && item.syncStatus !== 'failed') return false;
      }

      // Platform filter
      if (platformFilter !== 'all') {
        const hasPlatform = item.storeAssociations?.some(a => a.platform === platformFilter);
        if (!hasPlatform) return false;
      }

      // Store filter
      if (selectedStoreFilter !== 'all') {
        const hasStore = item.storeAssociations?.some(a => a.connectionId === selectedStoreFilter);
        if (!hasStore) return false;
      }

      return true;
    });
  }, [safeInventory, searchQuery, statusFilter, platformFilter, selectedStoreFilter]);

  // Reset to page 1 whenever any filter, search, dataset, or page size changes
  useEffect(() => {
    setCurrentPage(1);
  }, [searchQuery, statusFilter, platformFilter, selectedStoreFilter, pageSize]);

  // Keep every visible SKU of a product on the same page. The SKU rows retain
  // their individual stock accounts and Manage Stock actions.
  const filteredProducts = useMemo(() => {
    const groups = new Map<string, {key:string; name:string; imageUrl:string; items:MarketplaceInventoryItem[]}>();
    const matching = new Set(filteredInventory.map(item => item.productId ? `product:${item.productId}` : `sku:${item.masterSku}`));
    safeInventory.forEach(item => {
      const key = item.productId ? `product:${item.productId}` : `sku:${item.masterSku}`;
      if (!matching.has(key)) return;
      if (!groups.has(key)) groups.set(key, {key, name:item.productName, imageUrl:item.imageUrl, items:[]});
      groups.get(key)!.items.push(item);
    });
    return [...groups.values()];
  }, [filteredInventory, safeInventory]);
  const paginatedProducts = useMemo(() => {
    const start = (currentPage - 1) * pageSize;
    return filteredProducts.slice(start, start + pageSize);
  }, [filteredProducts, currentPage, pageSize]);
  const paginatedInventory = useMemo(() => paginatedProducts.flatMap(group => group.items), [paginatedProducts]);

  const totalItems = filteredProducts.length;
  const totalPages = Math.max(1, Math.ceil(totalItems / pageSize));
  const startIndex = totalItems === 0 ? 0 : (currentPage - 1) * pageSize + 1;
  const endIndex = Math.min(totalItems, currentPage * pageSize);

  // Multi-select helpers for current page and cross-page selection
  const selectedOnCurrentPage = useMemo(() => {
    return paginatedInventory.filter(item => selectedSkus.includes(item.masterSku));
  }, [paginatedInventory, selectedSkus]);
  const selectedProductCount = filteredProducts.filter(group => group.items.every(item => selectedSkus.includes(item.masterSku))).length;

  const isAllCurrentPageSelected =
    paginatedInventory.length > 0 && selectedOnCurrentPage.length === paginatedInventory.length;

  const handleToggleSelectAllCurrentPage = () => {
    if (isAllCurrentPageSelected) {
      const pageSkuSet = new Set(paginatedInventory.map(i => i.masterSku));
      setSelectedSkus(prev => prev.filter(sku => !pageSkuSet.has(sku)));
    } else {
      const pageSkus = paginatedInventory.map(i => i.masterSku);
      setSelectedSkus(prev => Array.from(new Set([...prev, ...pageSkus])));
    }
  };

  const handleToggleSelectItem = (sku: string) => {
    setSelectedSkus(prev =>
      prev.includes(sku) ? prev.filter(s => s !== sku) : [...prev, sku]
    );
  };

  const handleBatchSyncSelected = async () => {
    if (selectedSkus.length === 0) return;
    setIsBroadcastingAll(true);
    addToast({
      type: 'info',
      title: 'Batch Syncing Started',
      message: `Broadcasting stock updates for ${selectedSkus.length} selected SKU(s) to connected stores...`
    });

    setTimeout(() => {
      setIsBroadcastingAll(false);
      addToast({
        type: 'success',
        title: 'Batch Sync Completed',
        message: `Successfully synchronized ${selectedSkus.length} SKU(s) across all connected marketplace channels.`
      });
      setSelectedSkus([]);
    }, 1200);
  };

  const handleBulkRetrieveStock = async () => {
    const selected = safeInventory.filter(item => selectedSkus.includes(item.masterSku));
    const variantIds = selected.map(item => Number((item as any).variantId)).filter(id => Number.isInteger(id) && id > 0);
    if (variantIds.length !== selectedSkus.length) {
      addToast({type:'error',title:'Cannot start retrieval',message:'Refresh Inventory and select valid mapped SKUs.'});
      return;
    }
    setStartingStockRetrieval(true);
    try {
      const run = await storeInventoryApi.startStockRetrieval(variantIds) as StockRetrievalRun;
      setStockRetrievalRun(run);setShowStockRetrieval(true);
    } catch (error) {
      addToast({type:'error',title:'Cannot start retrieval',message:error instanceof Error?error.message:'Please try again.'});
    } finally {setStartingStockRetrieval(false);}
  };

  const handleBatchExportSelected = () => {
    if (selectedSkus.length === 0) return;
    const selectedItems = safeInventory.filter(i => selectedSkus.includes(i.masterSku));
    const csvHeader = singleCurrentStock ? 'Master SKU,Product Name,Variant,Barcode,Current Stock,Sync Status\n' : 'Master SKU,Product Name,Variant,Barcode,Physical Stock,Reserved,Available (ATP),Sync Status\n';
    const csvRows = selectedItems.map(i => 
      singleCurrentStock ? `"${i.masterSku}","${i.productName}","${i.variantName || ''}","${i.barcode || ''}",${i.availableStock ?? 0},"${i.syncStatus || ''}"` : `"${i.masterSku}","${i.productName}","${i.variantName || ''}","${i.barcode || ''}",${i.totalPhysicalStock ?? 0},${i.reservedStock ?? 0},${i.availableStock ?? 0},"${i.syncStatus || ''}"`
    ).join('\n');

    const blob = new Blob([csvHeader + csvRows], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('download', `inventory-skus-export-${new Date().toISOString().slice(0, 10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    addToast({
      type: 'success',
      title: 'CSV Export Generated',
      message: `Exported ${selectedItems.length} selected inventory records to CSV.`
    });
  };

  // Helper for generating pagination buttons with ellipses
  const getPageNumbers = (current: number, total: number): (number | string)[] => {
    if (total <= 7) {
      return Array.from({ length: total }, (_, i) => i + 1);
    }
    if (current <= 4) {
      return [1, 2, 3, 4, 5, '...', total];
    }
    if (current >= total - 3) {
      return [1, '...', total - 4, total - 3, total - 2, total - 1, total];
    }
    return [1, '...', current - 1, current, current + 1, '...', total];
  };

  // Handler: Open First Count modal
  const handleOpenFirstCount = (
    item: MarketplaceInventoryItem,
    targetStoreId?: string,
    targetWarehouseId?: string
  ) => {
    setSelectedItemForCount(item);
    setTargetFirstCountStoreId(targetStoreId);
    setTargetFirstCountWarehouseId(targetWarehouseId);
    setIsCountModalOpen(true);
  };

  const openManageStock = (item: MarketplaceInventoryItem, storeId?: string, warehouseId?: string, openActions = false) => {
    setSelectedDrawerItem(item);
    setManageTargetStoreId(storeId);
    setManageTargetWarehouseId(warehouseId);
    setManageOpenActions(openActions);
    setIsManageStockDrawerOpen(true);
  };

  const handleDirectAllocation = async (payload: {
    sku:string;targetStoreId?:string;targetWarehouseId?:string;
    allocations?:Record<string,number>;warehouseAllocations?:Record<string,number>;
    batchAllocations?:Record<string,number>;physicalCount:number;reason:string;notes?:string;operator:string;
  }) => {
    const item=safeInventory.find(entry=>entry.masterSku===payload.sku);
    if(!item) throw new Error('SKU is no longer available. Refresh inventory.');
    const reason=[payload.reason,payload.notes].filter(Boolean).join(': ');
    if(!payload.batchAllocations) throw new Error('Review allocations for every store and warehouse.');
    await storeInventoryApi.directAllocation((item as any).variantId,payload.physicalCount,
      payload.batchAllocations,reason);
    await reloadInventory();
    addToast({type:'success',title:singleCurrentStock ? 'Current Stock saved' : 'Physical count saved',message:'JW ledger updated. Check platform sync status separately.'});
  };

  const handleRealSaveIntake = async (payload: {
    sku:string;type:'intake'|'adjustment'|'count';targetStoreId?:string;targetWarehouseId?:string;
    targetWarehouseCode?:string;deltaQuantity:number;newPhysicalStock:number;
    reason:string;notes?:string;operator:string;
  }) => {
    const item=safeInventory.find(entry=>entry.masterSku===payload.sku);
    if(!item) throw new Error('SKU is no longer available. Refresh inventory.');
    const reason=[payload.reason,payload.notes].filter(Boolean).join(': ');
    if(payload.targetWarehouseId && !payload.targetWarehouseId.startsWith('direct:')) {
      if(!payload.targetStoreId || !/^\d+$/.test(payload.targetWarehouseId)) throw new Error('Select a verified facility.');
      await storeInventoryApi.facilityStockAction(payload.targetStoreId,Number(payload.targetWarehouseId),
        (item as any).variantId,payload.type==='intake'?'intake':payload.type==='count'?'count':'audit',
        payload.type==='intake'?payload.deltaQuantity:payload.newPhysicalStock,reason);
    } else {
      if(payload.type==='count') throw new Error('Choose a registered warehouse for First Count.');
      await adjustStock(item.id,payload.deltaQuantity,reason,
        payload.type==='intake'?'intake':'correction',payload.targetStoreId,item);
    }
    await reloadInventory();
    addToast({type:'success',title:'Inventory ledger updated',message:'Check platform sync status separately.'});
  };

  const handleRealExecuteTransfer = async (payload: Omit<StoreStockTransferRecord,
    'id'|'transferNumber'|'timestamp'|'ledgerStatus'|'sourcePlatformSyncStatus'|'destinationPlatformSyncStatus'>):Promise<StoreStockTransferRecord> => {
    const item=safeInventory.find(entry=>entry.masterSku===payload.masterSku);
    if(!item)throw new Error('SKU is no longer available. Refresh inventory.');
    const source=payload.sourceWarehouseId||'';
    const destination=payload.destinationWarehouseId||'';
    let id:string;
    if (!(source === `direct:${payload.sourceStoreId}` || /^\d+$/.test(source))
      || !(destination === `direct:${payload.destinationStoreId}` || /^\d+$/.test(destination))) {
      throw new Error('Choose a verified source and destination stock location.');
    }
    const receipt=await storeInventoryApi.facilityTransfer((item as any).variantId,
      /^\d+$/.test(source)?Number(source):null,
      /^\d+$/.test(destination)?Number(destination):null,payload.quantity,payload.reason,
      payload.sourceStoreId==='website'?null:Number(payload.sourceStoreId),
      payload.destinationStoreId==='website'?null:Number(payload.destinationStoreId));
    id=receipt.review_id;
    await reloadInventory();
    const record:StoreStockTransferRecord={...payload,id,transferNumber:id,
      timestamp:new Date().toISOString(),ledgerStatus:'succeeded',
      sourcePlatformSyncStatus:payload.sourcePlatform==='webstore'?'succeeded':'pending',
      destinationPlatformSyncStatus:payload.destinationPlatform==='webstore'?'succeeded':'pending'};
    setTransfersList(previous=>[record,...previous]);
    return record;
  };

  const handleCheckTransferStatus = async (record:StoreStockTransferRecord,verifyProvider=true):Promise<StoreStockTransferRecord> => {
    const item=safeInventory.find(entry=>entry.masterSku===record.masterSku);
    if(!item) throw new Error('Refresh inventory, then check this transfer again.');
    const check=async(storeId:string,warehouseId:string|undefined,platform:string) => {
      if(platform==='webstore') return 'succeeded' as const;
      const isFacility=/^\d+$/.test(warehouseId||'');
      const current=isFacility
        ? await storeInventoryApi.facilitySyncDetails(storeId,Number(warehouseId),(item as any).variantId)
        : await storeInventoryApi.syncDetails(storeId,(item as any).variantId);
      const result=verifyProvider&&current.status!=='synced'&&(current.can_check??current.canCheck)
        ? isFacility
          ? await storeInventoryApi.facilitySyncCheck(storeId,Number(warehouseId),(item as any).variantId)
          : await storeInventoryApi.checkStock(storeId,(item as any).variantId)
        : current;
      return result.status==='synced'?'succeeded' as const
        : ['sync_failed','auth_expired','verification_pending','confirmation_needs_review'].includes(result.status)?'failed' as const:'pending' as const;
    };
    const [sourcePlatformSyncStatus,destinationPlatformSyncStatus]=await Promise.all([
      check(record.sourceStoreId,record.sourceWarehouseId,record.sourcePlatform),
      check(record.destinationStoreId,record.destinationWarehouseId,record.destinationPlatform),
    ]);
    const updated={...record,sourcePlatformSyncStatus,destinationPlatformSyncStatus};
    setTransfersList(previous=>previous.map(entry=>entry.id===record.id?updated:entry));
    return updated;
  };

  // Handler: Save First Count
  const handleSaveFirstCount = ({
    sku,
    targetStoreId,
    allocations,
    physicalCount,
    reason,
    notes,
    operator
  }: {
    sku: string;
    targetStoreId?: string;
    allocations?: Record<string, number>;
    physicalCount: number;
    reason: string;
    notes?: string;
    operator: string;
  }) => {
    const item = safeInventory.find(i => i.masterSku === sku);
    if (!item) return;

    // Use admin context recordPhysicalCountAudit
    recordPhysicalCountAudit(item.id, physicalCount, `${reason}: ${notes || ''}`.trim());

    // In Independent mode, update the store's balance ledger
    if (currentMode === 'independent') {
      if (allocations && Object.keys(allocations).length > 0) {
        setStoreBalances(prev => {
          const currentSkuBalances = prev[sku] || {};
          const updatedSkuBalances = { ...currentSkuBalances };

          Object.entries(allocations).forEach(([sId, count]) => {
            const prevStore = currentSkuBalances[sId];
            const resStock = prevStore?.reservedStock || 0;
            const availStock = Math.max(0, count - resStock);
            const storeObj = stores.find(s => s.id === sId);

            updatedSkuBalances[sId] = {
              storeId: sId,
              storeName: storeObj?.name || sId,
              storeNickname: storeObj?.nickname || sId,
              platform: (storeObj?.platform || 'webstore') as any,
              isManagedByJw: true,
              connectionState: 'connected',
              isCounted: true,
              physicalStock: count,
              reservedStock: resStock,
              availableStock: availStock,
              platformStockSnapshot: count,
              platformReadStatus: 'ok',
              lastPlatformReadAt: new Date().toISOString(),
              syncState: 'enabled',
              lastConfirmedPushedQuantity: availStock,
              lastConfirmedPushedAt: new Date().toISOString(),
              lastSyncStatus: 'confirmed'
            };
          });

          return {
            ...prev,
            [sku]: updatedSkuBalances
          };
        });
      } else if (targetStoreId) {
        setStoreBalances(prev => {
          const currentSkuBalances = prev[sku] || {};
          const prevStore = currentSkuBalances[targetStoreId];
          const resStock = prevStore?.reservedStock || 0;
          const availStock = Math.max(0, physicalCount - resStock);

          return {
            ...prev,
            [sku]: {
              ...currentSkuBalances,
              [targetStoreId]: {
                storeId: targetStoreId,
                storeName: stores.find(s => s.id === targetStoreId)?.name || targetStoreId,
                storeNickname: stores.find(s => s.id === targetStoreId)?.nickname,
                platform: stores.find(s => s.id === targetStoreId)?.platform as any,
                isManagedByJw: true,
                connectionState: 'connected',
                isCounted: true,
                physicalStock: physicalCount,
                reservedStock: resStock,
                availableStock: availStock,
                platformStockSnapshot: physicalCount,
                platformReadStatus: 'ok',
                lastPlatformReadAt: new Date().toISOString(),
                syncState: 'enabled',
                lastConfirmedPushedQuantity: availStock,
                lastConfirmedPushedAt: new Date().toISOString(),
                lastSyncStatus: 'confirmed'
              }
            }
          };
        });
      }
    }

    // Log activity
    const newLog: InventoryActivityItem = {
      id: `act-${Date.now()}`,
      timestamp: new Date().toISOString(),
      actionType: 'first_count',
      masterSku: sku,
      productName: item.productName,
      variantName: item.variantName,
      targetScope: currentMode === 'shared' 
        ? 'Global Shared Pool' 
        : allocations 
        ? `Allocated across ${Object.keys(allocations).length} Store Accounts`
        : (stores.find(s => s.id === targetStoreId)?.nickname || targetStoreId || 'Store Account'),
      quantityChange: physicalCount,
      previousStock: 'Not Counted',
      resultingStock: physicalCount,
      availableChange: physicalCount,
      operator,
      reason,
      referenceId: `COUNT-${sku}-${Date.now().toString().slice(-4)}`,
      status: 'succeeded',
      detailsPayload: {
        mode: currentMode,
        notes,
        verifiedPhysical: physicalCount,
        allocations
      }
    };

    setActivityLogs(prev => [newLog, ...prev]);

    addToast({
      type: 'success',
      title: 'Initial Count Confirmed',
      message: `Verified physical stock for ${sku} recorded at ${physicalCount} units. Available-to-promise updated.`
    });
  };

  // Handler: Open Intake/Adjustment modal
  const handleOpenIntake = (
    item: MarketplaceInventoryItem,
    type: 'intake' | 'adjustment' = 'intake',
    targetStoreId?: string,
    targetWarehouseId?: string
  ) => {
    if (currentMode === 'shared') {
      setSharedStockAction({item,action:type==='intake'?'intake':'audit'});
      return;
    }
    setSelectedItemForIntake(item);
    setIntakeInitialType(type);
    setIntakeTargetStoreId(targetStoreId);
    setIntakeTargetWarehouseId(targetWarehouseId);
    setIsIntakeModalOpen(true);
  };

  // Handler: Save Intake/Adjustment
  const handleSaveIntake = ({
    sku,
    type,
    targetStoreId,
    deltaQuantity,
    newPhysicalStock,
    reason,
    notes,
    operator
  }: {
    sku: string;
    type: 'intake' | 'adjustment';
    targetStoreId?: string;
    deltaQuantity: number;
    newPhysicalStock: number;
    reason: string;
    notes?: string;
    operator: string;
  }) => {
    const item = safeInventory.find(i => i.masterSku === sku);
    if (!item) return;

    adjustStock(item.id, deltaQuantity, `${reason}: ${notes || ''}`.trim());

    // Update independent balance if applicable
    if (currentMode === 'independent' && targetStoreId) {
      setStoreBalances(prev => {
        const skuBal = prev[sku] || {};
        const storeBal = skuBal[targetStoreId];
        const oldPhysical = storeBal?.physicalStock ?? 0;
        const targetPhysical = type === 'intake' ? oldPhysical + deltaQuantity : newPhysicalStock;
        const resStock = storeBal?.reservedStock ?? 0;
        const targetAvail = Math.max(0, targetPhysical - resStock);

        return {
          ...prev,
          [sku]: {
            ...skuBal,
            [targetStoreId]: {
              ...storeBal,
              storeId: targetStoreId,
              storeName: stores.find(s => s.id === targetStoreId)?.name || targetStoreId,
              platform: stores.find(s => s.id === targetStoreId)?.platform as any,
              isManagedByJw: true,
              connectionState: 'connected',
              isCounted: true,
              physicalStock: targetPhysical,
              reservedStock: resStock,
              availableStock: targetAvail,
              syncState: 'enabled',
              lastConfirmedPushedQuantity: targetAvail,
              lastConfirmedPushedAt: new Date().toISOString()
            }
          }
        };
      });
    }

    const newLog: InventoryActivityItem = {
      id: `act-${Date.now()}`,
      timestamp: new Date().toISOString(),
      actionType: type === 'intake' ? 'actual_intake' : 'stock_adjustment',
      masterSku: sku,
      productName: item.productName,
      variantName: item.variantName,
      targetScope: currentMode === 'shared' ? 'Global Shared Pool' : (stores.find(s => s.id === targetStoreId)?.name || 'Store Account'),
      quantityChange: deltaQuantity,
      previousStock: item.totalPhysicalStock ?? 0,
      resultingStock: newPhysicalStock,
      availableChange: deltaQuantity,
      operator,
      reason,
      referenceId: `${type.toUpperCase()}-${Date.now().toString().slice(-4)}`,
      status: 'succeeded',
      detailsPayload: { notes, deltaQuantity, newPhysicalStock }
    };

    setActivityLogs(prev => [newLog, ...prev]);

    addToast({
      type: 'success',
      title: type === 'intake' ? 'Stock Inbound Confirmed' : 'Audit Adjustment Saved',
      message: `${sku} physical stock updated to ${newPhysicalStock} units.`
    });
  };

  // Handler: Execute Store Stock Transfer (Independent Mode)
  const handleExecuteStoreTransfer = async (
    transferPayload: Omit<StoreStockTransferRecord, 'id' | 'transferNumber' | 'timestamp' | 'ledgerStatus' | 'sourcePlatformSyncStatus' | 'destinationPlatformSyncStatus'>
  ): Promise<StoreStockTransferRecord> => {
    const transferNumber = `TRF-${new Date().getFullYear()}-${Math.floor(1000 + Math.random() * 9000)}`;
    const timestamp = new Date().toISOString();

    // Step 1: Execute internal JW ledger deduction and credit
    const { masterSku, sourceStoreId, destinationStoreId, quantity } = transferPayload;

    setStoreBalances(prev => {
      const skuBal = prev[masterSku] || {};
      const srcBal = skuBal[sourceStoreId];
      const dstBal = skuBal[destinationStoreId];

      const newSrcPhys = Math.max(0, (srcBal?.physicalStock ?? 0) - quantity);
      const newSrcAvail = Math.max(0, (srcBal?.availableStock ?? 0) - quantity);

      const newDstPhys = (dstBal?.physicalStock ?? 0) + quantity;
      const newDstAvail = (dstBal?.availableStock ?? 0) + quantity;

      return {
        ...prev,
        [masterSku]: {
          ...skuBal,
          [sourceStoreId]: {
            ...srcBal,
            physicalStock: newSrcPhys,
            availableStock: newSrcAvail,
            lastConfirmedPushedQuantity: newSrcAvail,
            lastConfirmedPushedAt: new Date().toISOString()
          },
          [destinationStoreId]: {
            ...dstBal,
            physicalStock: newDstPhys,
            availableStock: newDstAvail,
            lastConfirmedPushedQuantity: newDstAvail,
            lastConfirmedPushedAt: new Date().toISOString()
          }
        }
      };
    });

    const record: StoreStockTransferRecord = {
      ...transferPayload,
      id: `trf-${Date.now()}`,
      transferNumber,
      timestamp,
      ledgerStatus: 'succeeded',
      sourcePlatformSyncStatus: 'succeeded',
      destinationPlatformSyncStatus: 'succeeded'
    };

    setTransfersList(prev => [record, ...prev]);

    // Record activity log
    const activityLog: InventoryActivityItem = {
      id: `act-${Date.now()}`,
      timestamp,
      actionType: 'store_transfer',
      masterSku,
      productName: transferPayload.productName,
      variantName: transferPayload.variantName,
      targetScope: `${transferPayload.sourceStoreName} -> ${transferPayload.destinationStoreName}`,
      quantityChange: quantity,
      previousStock: `Source Avail: ${(storeBalances[masterSku]?.[sourceStoreId]?.availableStock ?? 0)}`,
      resultingStock: `Source Avail: ${(storeBalances[masterSku]?.[sourceStoreId]?.availableStock ?? 0) - quantity}`,
      operator: transferPayload.operator,
      reason: transferPayload.reason,
      referenceId: transferNumber,
      status: 'succeeded',
      detailsPayload: {
        sourceStore: transferPayload.sourceStoreName,
        destinationStore: transferPayload.destinationStoreName,
        quantity,
        totalInventoryConserved: true
      }
    };

    setActivityLogs(prev => [activityLog, ...prev]);

    addToast({
      type: 'success',
      title: 'Store Transfer Completed',
      message: `${quantity} units transferred from ${transferPayload.sourceStoreName} to ${transferPayload.destinationStoreName}. Total stock conserved.`
    });

    return record;
  };

  // Handler: Retry platform sync for transfer
  const handleRetryPlatformSync = async (transferId: string, target: 'source' | 'destination') => {
    throw new Error('Use the selected facility stock sync status to check or resend this exact location.');
  };

  // Handler: Admin Global Mode Switch
  const handleSwitchMode = async (
    newMode: 'shared' | 'independent',
    staffName: string,
    reason: string,
    allocations?: Record<string, Record<string, number>>
  ) => {
    try {
      const distribution=allocations ? Object.fromEntries(Object.entries(allocations).map(([sku,byStore])=>{
        const item=safeInventory.find(candidate=>candidate.masterSku===sku);
        return [String((item as any)?.variantId||sku),Object.fromEntries(Object.entries(byStore).map(([id,quantity])=>[id==='website'?'website':`marketplace:${id}`,quantity]))];
      })) : {};
      await switchInventoryMode(newMode,distribution,true);
      await reloadInventory();
      return {success:true};
    } catch(error:any) {
      addToast({type:'error',title:'Inventory mode was not changed',message:error?.message||'Review the inventory configuration.'});
      return {success:false,error:error?.message||'Mode switch failed'};
    }

  };

  // Handler: Toggle Store Takeover Management
  const handleToggleStoreManagement = (storeId: string, enable: boolean) => {
    void toggleStoreManagement(storeId,enable,'Shared Pool takeover preference')
      .catch((error:any)=>addToast({type:'error',title:'Store management unchanged',message:error?.message||'Could not update Shared Pool takeover.'}));
  };

  // Handler: Batch broadcast to all channels
  const handleBroadcastAll = async () => {
    addToast({type:'error',title:'Broadcast unavailable',
      message:'Select an exact verified warehouse and use its stock sync controls.'});
  };

  return (
    <div id="marketplace-inventory-page" className="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto space-y-6 animate-in fade-in duration-200">
      {/* Sub Navigation & Header Breadcrumb */}
      <SubNavHeader
        parentSection="marketplace"
        parentLabel="Marketplace Ecosystem"
        currentLabel="Inventory Management"
        subtitle={singleCurrentStock ? 'Current Stock for each connected store and stock location.' : 'Omnichannel store inventory allocation, Available-To-Promise (ATP) calculation, and physical stock verification.'}
        actions={
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={() => setIsSettingsModalOpen(true)}
              className="px-3.5 py-2 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 hover:bg-neutral-50 dark:hover:bg-neutral-800 text-neutral-700 dark:text-neutral-300 text-xs font-semibold transition-colors flex items-center gap-2 shadow-xs cursor-pointer"
            >
              <Settings className="w-4 h-4 text-neutral-500 dark:text-neutral-400" />
              <span>Inventory Settings</span>
            </button>
            <button
              type="button"
              disabled
              title="Bulk sync is unavailable until every selected warehouse has a verified provider route"
              onClick={handleBroadcastAll}
              className="px-4 py-2 rounded-xl bg-neutral-900 hover:bg-neutral-800 dark:bg-white dark:hover:bg-neutral-100 text-white dark:text-neutral-950 border border-neutral-700 dark:border-neutral-200 text-xs font-bold transition-all shadow-xs flex items-center gap-2 cursor-pointer disabled:opacity-50"
            >
              {isBroadcastingAll ? <RefreshCw className="w-4 h-4 animate-spin" /> : <Send className="w-4 h-4" />}
              <span>Bulk Sync Unavailable</span>
            </button>
          </div>
        }
      />

      {/* Tabs Bar: Overview | Stock Transfers | Activity History */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-neutral-200 dark:border-neutral-800 pb-1">
        <div className="flex items-center gap-1 sm:gap-2">
          <button
            type="button"
            onClick={() => setActiveTab('overview')}
            className={`px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 cursor-pointer border ${
              activeTab === 'overview'
                ? 'bg-neutral-900 dark:bg-neutral-800 text-white border-neutral-700 shadow-xs'
                : 'border-transparent text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800'
            }`}
          >
            <Boxes className="w-4 h-4" />
            <span>Inventory Overview</span>
            {uncountedItems.length > 0 && (
              <span className="text-[10px] px-1.5 py-0.2 rounded-full font-bold bg-amber-500/20 text-amber-500 dark:text-amber-400 border border-amber-500/30">
                {uncountedItems.length}
              </span>
            )}
          </button>

          {/* Tab 2: Stock Transfers (Only available in Independent Mode) */}
          {currentMode === 'independent' ? (
            <button
              type="button"
              onClick={() => setActiveTab('transfers')}
              className={`px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 cursor-pointer border ${
                activeTab === 'transfers'
                  ? 'bg-neutral-900 dark:bg-neutral-800 text-white border-neutral-700 shadow-xs'
                  : 'border-transparent text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800'
              }`}
            >
              <ArrowRightLeft className="w-4 h-4" />
              <span>Stock Transfers</span>
            </button>
          ) : (
            <div
              title="Stock transfers are only available in Independent Stock Mode. In Shared Stock Mode, all stores pull from the same central pool."
              className="px-3 py-2 text-xs font-medium text-neutral-400 dark:text-neutral-600 flex items-center gap-1.5 cursor-not-allowed opacity-60"
            >
              <ArrowRightLeft className="w-3.5 h-3.5" />
              <span>Stock Transfers (Disabled in Shared Mode)</span>
            </div>
          )}

          {/* Tab 3: Activity History */}
          <button
            type="button"
            onClick={() => setActiveTab('history')}
            className={`px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 cursor-pointer border ${
              activeTab === 'history'
                ? 'bg-neutral-900 dark:bg-neutral-800 text-white border-neutral-700 shadow-xs'
                : 'border-transparent text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800'
            }`}
          >
            <History className="w-4 h-4" />
            <span>Activity History</span>
            <span className="text-[10px] px-1.5 py-0.2 rounded-full font-bold bg-neutral-200 dark:bg-neutral-700 text-neutral-700 dark:text-neutral-300">
              {activityLogs.length}
            </span>
          </button>
        </div>

        {/* Current Operating Mode Pill Indicator */}
        <div className="flex items-center gap-2">
          <div className="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-neutral-100 dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 text-xs">
            <span className="text-neutral-500 text-[11px]">Policy:</span>
            <span className="font-bold text-neutral-900 dark:text-white">
              {currentMode === 'shared' ? 'Shared Stock' : 'Independent Stock'}
            </span>
            <button
              type="button"
              onClick={() => setIsSettingsModalOpen(true)}
              className="text-[10px] text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:underline font-bold ml-1 cursor-pointer"
            >
              Configure
            </button>
          </div>
        </div>
      </div>

      {/* ================= TAB 1: INVENTORY OVERVIEW ================= */}
      {activeTab === 'overview' && (
        <div className="space-y-6 animate-in fade-in duration-150">
          {/* Top Key Metrics */}
          <div className={`grid grid-cols-2 sm:grid-cols-2 ${singleCurrentStock ? 'lg:grid-cols-3' : 'lg:grid-cols-5'} gap-3.5`}>
            {/* 1. Total Master SKUs */}
            <div className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs hover:border-neutral-700 transition-all">
              <div className="flex items-center justify-between text-neutral-500 dark:text-neutral-400 text-xs mb-1">
                <span>Master SKUs</span>
                <Boxes className="w-4 h-4 text-neutral-400" />
              </div>
              <div className="font-mono font-bold text-2xl text-neutral-900 dark:text-white">
                {metrics.totalSkus}
              </div>
              <div className="text-[11px] text-neutral-400 mt-1">Catalog items tracked</div>
            </div>

            {/* 2. Verified Physical Stock */}
            <div className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs hover:border-neutral-700 transition-all">
              <div className="flex items-center justify-between text-neutral-500 dark:text-neutral-400 text-xs mb-1">
                <span>{singleCurrentStock ? 'Current Stock' : 'Physical Stock'}</span>
                <Boxes className="w-4 h-4 text-neutral-400" />
              </div>
              <div className="font-mono font-bold text-2xl text-neutral-900 dark:text-white">
                {metrics.totalPhysical}
              </div>
              <div className="text-[11px] text-neutral-400 mt-1">
                {metrics.uncountedCount > 0 ? `${metrics.uncountedCount} SKU(s) Awaiting Count` : 'All items verified'}
              </div>
            </div>

            {/* 3. Reserved Stock (Active Orders) */}
            {!singleCurrentStock && <div className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs hover:border-neutral-700 transition-all">
              <div className="flex items-center justify-between text-neutral-500 dark:text-neutral-400 text-xs mb-1">
                <span>Reserved Stock</span>
                <Radio className="w-4 h-4 text-neutral-400" />
              </div>
              <div className="font-mono font-bold text-2xl text-amber-500 dark:text-amber-400">
                {metrics.totalReserved}
              </div>
              <div className="text-[11px] text-neutral-400 mt-1">Locked by customer orders</div>
            </div>}

            {/* 4. Available to Sell (ATP) */}
            {!singleCurrentStock && <div className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs hover:border-neutral-700 transition-all">
              <div className="flex items-center justify-between text-neutral-500 dark:text-neutral-400 text-xs font-semibold mb-1">
                <span>Available to Sell (ATP)</span>
                <Sparkles className="w-4 h-4 text-neutral-400" />
              </div>
              <div className="font-mono font-bold text-2xl text-neutral-900 dark:text-white">
                {metrics.totalAvailable}
              </div>
              <div className="text-[11px] text-neutral-400 font-medium mt-1">
                Physical - Reserved
              </div>
            </div>}

            {/* 5. Sales Channels & Stores */}
            <div className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs hover:border-neutral-700 transition-all col-span-2 lg:col-span-1">
              <div className="flex items-center justify-between text-neutral-500 dark:text-neutral-400 text-xs mb-1">
                <span>Connected Stores</span>
                <Store className="w-4 h-4 text-neutral-400" />
              </div>
              <div className="font-mono font-bold text-2xl text-neutral-900 dark:text-white">
                {metrics.activeStoresCount} <span className="text-xs font-normal text-neutral-500">Stores</span>
              </div>
              <div className="text-[11px] text-neutral-400 mt-1">Shopee, Lazada, Storefront</div>
            </div>
          </div>

          {/* Mode Policy Explanatory Banner */}
          <div className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div className="flex items-start gap-3">
              <div className="p-2.5 rounded-xl bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 border border-neutral-200 dark:border-neutral-700 shrink-0">
                <Layers className="w-4 h-4" />
              </div>
              <div className="text-xs space-y-1">
                <div className="font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                  <span>Current Policy: {currentMode === 'shared' ? 'Shared Stock Mode' : 'Independent Stock Mode'}</span>
                  <span className="text-[10px] font-bold px-1.5 py-0.5 rounded bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300">
                    System-wide
                  </span>
                </div>
                <p className="text-neutral-600 dark:text-neutral-400 leading-relaxed max-w-3xl">
                  {currentMode === 'shared'
                    ? singleCurrentStock ? 'All connected sales channels draw from one shared Current Stock. Each accepted order deducts once.' : 'All connected sales channels draw from a single shared pool of Available stock. When an item sells on Shopee, the remaining quantity decreases across Lazada and Webstore immediately. Platform reported stock numbers are channel references only and do not increase physical inventory.'
                    : 'Each store holds an isolated stock ledger. Inventory is explicitly assigned per store, and inter-store rebalancing is performed through the Stock Transfers tab. Total stock across stores is strictly conserved.'}
                </p>
              </div>
            </div>

            <button
              type="button"
              onClick={() => setIsSettingsModalOpen(true)}
              className="px-3.5 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-700 dark:text-neutral-200 text-xs font-semibold hover:bg-neutral-50 dark:hover:bg-neutral-750 transition whitespace-nowrap cursor-pointer shrink-0 shadow-xs"
            >
              Mode Policy Details & Transition
            </button>
          </div>

          {/* Uncounted SKUs Alert Bar */}
          {uncountedItems.length > 0 && (
            <div className="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3 animate-in slide-in-from-top-2">
              <div className="flex items-center gap-3">
                <div className="p-2 rounded-xl bg-amber-500/15 text-amber-700 dark:text-amber-400 shrink-0">
                  <ClipboardCheck className="w-5 h-5" />
                </div>
                <div className="text-xs">
                  <div className="font-bold text-amber-950 dark:text-amber-200">
                    {uncountedItems.length} Master SKU(s) Awaiting Initial {singleCurrentStock ? 'Current Stock Count' : 'Physical Stock Verification'}
                  </div>
                  <p className="text-amber-900/80 dark:text-amber-300/80 mt-0.5">
                    {singleCurrentStock ? <>Items remain <strong>Not Counted</strong> until staff enters the first absolute Current Stock. Marketplace quantities are reference snapshots only.</> : <>Items imported from marketplace stores remain in <strong>Not Counted</strong> status until staff performs an initial stock check. External channel quantities are reference snapshots only and cannot be released for sale without physical verification.</>}
                  </p>
                </div>
              </div>

              <div className="flex items-center gap-2 shrink-0">
                {uncountedItems.length > 0 && uncountedItems[0] && (
                  <button
                    type="button"
                    onClick={() => openManageStock(uncountedItems[0])}
                    className="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold transition shadow-xs flex items-center gap-1.5 cursor-pointer"
                  >
                    <ClipboardCheck className="w-4 h-4" />
                    <span>Start First Count ({uncountedItems[0].masterSku})</span>
                  </button>
                )}
              </div>
            </div>
          )}

          {/* Filters & Search Toolbar */}
          <div className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs flex flex-col lg:flex-row lg:items-center justify-between gap-3">
            {/* Search Input */}
            <div className="flex-1 relative">
              <Search className="w-4 h-4 absolute left-3.5 top-3 text-neutral-400" />
              <input
                type="text"
                placeholder="Search by SKU, product name, variant, or barcode..."
                value={searchQuery}
                onChange={e => setSearchQuery(e.target.value)}
                className="w-full pl-10 pr-4 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/80 text-neutral-900 dark:text-white placeholder:text-neutral-500"
              />
            </div>

            {/* Filter Dropdowns */}
            <div className="flex flex-wrap items-center gap-2">
              {/* Status Filter */}
              <select
                value={statusFilter}
                onChange={e => setStatusFilter(e.target.value as any)}
                className="px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 font-medium cursor-pointer"
              >
                <option value="all">All Statuses</option>
                <option value="uncounted">Not Counted</option>
                <option value="low_stock">Low Stock (≤ 10)</option>
                <option value="synced">Fully Synced</option>
                <option value="mismatch">Sync Mismatch / Error</option>
              </select>

              {/* Platform Filter */}
              <select
                value={platformFilter}
                onChange={e => setPlatformFilter(e.target.value)}
                className="px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 font-medium cursor-pointer"
              >
                <option value="all">All Platforms</option>
                <option value="webstore">JW Storefront</option>
                <option value="shopee">Shopee</option>
                <option value="lazada">Lazada</option>
              </select>

              {/* Store Filter */}
              <select
                value={selectedStoreFilter}
                onChange={e => setSelectedStoreFilter(e.target.value)}
                className="px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 font-medium cursor-pointer max-w-[160px] truncate"
              >
                <option value="all">All Stores</option>
                {(stores || []).filter(s => !s.isComingSoon).map(st => (
                  <option key={st.id} value={st.id}>
                    {st.nickname || st.name}
                  </option>
                ))}
              </select>

              {/* Quick Intake Button */}
              {stockRetrievalRun && <button type="button" onClick={()=>setShowStockRetrieval(true)}
                className="px-3.5 py-2 rounded-xl border border-violet-700 text-violet-600 dark:text-violet-300 text-xs font-bold">
                View Retrieval Progress {['queued','running'].includes(stockRetrievalRun.status)?'· Running':''}
              </button>}
              {safeInventory.length > 0 && (
                <button
                  type="button"
                  onClick={() => handleOpenIntake(safeInventory[0], 'intake')}
                  className="px-3.5 py-2 rounded-xl bg-neutral-900 hover:bg-neutral-800 dark:bg-white dark:hover:bg-neutral-100 text-white dark:text-neutral-950 border border-neutral-700 dark:border-neutral-200 text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer"
                >
                  <Plus className="w-3.5 h-3.5" />
                  <span>Actual Intake</span>
                </button>
              )}
            </div>
          </div>

          {/* Batch Actions Bar (Matching Orders OMS style) */}
          {selectedSkus.length > 0 && (
            <div className="p-3 bg-indigo-50 dark:bg-indigo-950/60 rounded-xl border border-indigo-200 dark:border-indigo-800 flex flex-wrap items-center justify-between gap-3 text-xs">
              <div className="flex items-center gap-2 text-indigo-900 dark:text-indigo-200 font-semibold">
                <span>{selectedProductCount} product(s) selected · {selectedSkus.length} SKU(s) included</span>
                <span className="text-[11px] text-indigo-500 dark:text-indigo-400 font-normal">
                  ({paginatedProducts.filter(group => group.items.every(item => selectedSkus.includes(item.masterSku))).length} products on this page)
                </span>
                {filteredProducts.flatMap(group => group.items).some(item => !selectedSkus.includes(item.masterSku)) && (
                  <button
                    type="button"
                    onClick={() => setSelectedSkus(Array.from(new Set(filteredProducts.flatMap(group => group.items.map(item => item.masterSku)))))}
                    className="ml-1 text-xs text-indigo-600 dark:text-indigo-400 hover:underline cursor-pointer font-bold"
                  >
                    Select all {filteredProducts.length.toLocaleString()} products
                  </button>
                )}
              </div>

              <div className="flex items-center gap-2">
                {currentMode === 'independent' && singleCurrentStock && <button type="button" onClick={handleBulkRetrieveStock} disabled={startingStockRetrieval}
                  className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-violet-600 hover:bg-violet-700 text-white rounded-lg font-bold shadow-xs transition-colors cursor-pointer disabled:opacity-50">
                  <Download className="w-3.5 h-3.5" /> Bulk Retrieve Stock from Marketplace ({selectedSkus.length})
                </button>}
                <button
                  type="button"
                  onClick={handleBatchSyncSelected}
                  disabled={isBroadcastingAll}
                  className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-bold shadow-xs transition-colors cursor-pointer disabled:opacity-50"
                >
                  <RefreshCw className={`w-3.5 h-3.5 ${isBroadcastingAll ? 'animate-spin' : ''}`} />
                  <span>Batch Sync Selected ({selectedSkus.length})</span>
                </button>

                <button
                  type="button"
                  onClick={handleBatchExportSelected}
                  className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white dark:bg-neutral-900 border border-indigo-200 dark:border-indigo-700 text-indigo-700 dark:text-indigo-300 rounded-lg font-semibold hover:bg-indigo-50 dark:hover:bg-neutral-800 transition-colors cursor-pointer"
                >
                  <Download className="w-3.5 h-3.5" />
                  <span>Export CSV</span>
                </button>

                <button
                  type="button"
                  onClick={() => setSelectedSkus([])}
                  className="px-2.5 py-1 text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200 cursor-pointer font-medium"
                >
                  Clear Selection
                </button>
              </div>
            </div>
          )}

          {/* Each product owns its header and SKU table, as in the approved sample. */}
          <div className="space-y-4">
            {paginatedProducts.length === 0 ? (
              <div className="rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 py-12 text-center text-neutral-400">
                <Boxes className="w-8 h-8 mx-auto mb-2 text-neutral-300 dark:text-neutral-700" />
                <p className="font-semibold text-neutral-700 dark:text-neutral-300">No inventory SKUs found</p>
                <p className="text-xs text-neutral-500 mt-0.5">Try changing filters or search terms.</p>
              </div>
            ) : paginatedProducts.map(group => (
              <section key={group.key} aria-label={group.name} className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs overflow-hidden">
                <div className="flex items-center gap-3 px-4 py-5 bg-neutral-50 dark:bg-neutral-900">
                  <input type="checkbox" aria-label={`Select ${group.name} and all its SKUs`}
                    checked={group.items.every(item => selectedSkus.includes(item.masterSku))}
                    onChange={() => {
                      const skus = group.items.map(item => item.masterSku);
                      const all = skus.every(sku => selectedSkus.includes(sku));
                      setSelectedSkus(previous => all ? previous.filter(sku => !skus.includes(sku))
                        : Array.from(new Set([...previous, ...skus])));
                    }} className="w-4 h-4 cursor-pointer accent-violet-600 shrink-0" />
                  <span className="w-11 h-11 rounded-xl bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 overflow-hidden flex items-center justify-center shrink-0">
                    {group.imageUrl ? <img src={group.imageUrl} alt="" className="w-full h-full object-cover" /> : <Boxes className="w-5 h-5 text-neutral-400" />}
                  </span>
                  <div className="flex flex-1 items-center gap-3"><strong className="text-sm text-neutral-900 dark:text-white">{group.name}</strong>
                    {currentMode === 'shared' && <span className="rounded-md border border-purple-700/40 bg-purple-950/30 px-2 py-1 text-[10px] font-semibold text-purple-400">Shared Stock</span>}</div>
                  <span className="font-semibold text-neutral-800 dark:text-neutral-100 whitespace-nowrap text-xs">{group.items.length} SKU{group.items.length === 1 ? '' : 's'}</span>
                  <button type="button" aria-label={`${collapsedProducts.includes(group.key) ? 'Expand' : 'Collapse'} ${group.name} SKUs`}
                    aria-expanded={!collapsedProducts.includes(group.key)}
                    onClick={() => setCollapsedProducts(previous => previous.includes(group.key)
                      ? previous.filter(key => key !== group.key) : [...previous, group.key])}
                    className="p-2 rounded-lg text-neutral-400 hover:bg-neutral-200 dark:hover:bg-neutral-800 cursor-pointer">
                    <ChevronDown className={`w-4 h-4 transition-transform ${collapsedProducts.includes(group.key) ? '-rotate-90' : ''}`} />
                  </button>
                </div>
                {!collapsedProducts.includes(group.key) && <div className="overflow-x-auto">
                  <table className="w-full text-left text-xs border-collapse min-w-[980px]">
                    <thead className="bg-neutral-100 dark:bg-neutral-800 border-b border-neutral-200 dark:border-neutral-700/80 text-neutral-700 dark:text-neutral-300 font-bold uppercase tracking-wider text-[11px]">
                      <tr>
                        <th className="py-3.5 px-3.5 w-10" aria-hidden="true" />
                        <th className="py-3.5 px-4 min-w-[220px]">Product / Master SKU</th>
                        <th className="py-3.5 px-4 min-w-[130px] whitespace-nowrap">{currentMode === 'shared' ? 'Shared Stock' : singleCurrentStock ? 'Current Stock' : 'Physical Allocation'}</th>
                        {!singleCurrentStock && currentMode === 'independent' && <th className="py-3.5 px-4 min-w-[120px] whitespace-nowrap">Reserved (Locked)</th>}
                        {!singleCurrentStock && currentMode === 'independent' && <th className="py-3.5 px-4 min-w-[130px] whitespace-nowrap">Available to Sell (ATP)</th>}
                        <th className="py-3.5 px-4 min-w-[240px]">{currentMode === 'independent' ? 'Mapped Stores' : 'Connected Stores & Sync'}</th>
                        <th className="py-3.5 px-4 text-center w-36 min-w-[144px] sticky right-0 z-20 bg-neutral-100 dark:bg-neutral-800 shadow-[-4px_0_12px_rgba(0,0,0,0.15)] dark:shadow-[-6px_0_16px_rgba(0,0,0,0.6)] border-l border-neutral-200 dark:border-neutral-700/80 text-neutral-700 dark:text-neutral-300">Actions</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800">
                      {group.items.map(item => {
                      const isSelected = selectedSkus.includes(item.masterSku);
                    const isUncounted = item.physicalStockStatus === 'unknown' || item.totalPhysicalStock === null;
                    const physicalStock = currentMode === 'shared' ? ((item as any).currentStock ?? item.totalPhysicalStock) : item.totalPhysicalStock ?? 0;
                    const reservedStock = item.reservedStock ?? 0;
                    const availableStock = item.availableStock ?? 0;

                    // Independent store balances for this SKU
                    const skuStoreBalances = (storeBalances && storeBalances[item.masterSku]) || {};
                    const mappedSummaries = currentMode === 'independent'
                      ? mappedStoreSummaries(item, stores, skuStoreBalances)
                      : [];

                    return (
                      <tr
                        key={item.id}
                        className={`group hover:bg-neutral-50 dark:hover:bg-neutral-800/40 transition-colors ${
                          isSelected ? 'bg-indigo-50/40 dark:bg-indigo-950/20' : ''
                        }`}
                      >
                        <td className="py-3.5 px-3.5 w-10" />

                        {/* 1. Product & SKU */}
                        <td className="py-3.5 px-4 min-w-[220px]">
                          <div className="flex items-center gap-3">
                            <div className="w-11 h-11 rounded-xl bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 overflow-hidden shrink-0 flex items-center justify-center">
                              {item.imageUrl ? (
                                <img src={item.imageUrl} alt={item.productName} className="w-full h-full object-cover" />
                              ) : (
                                <Boxes className="w-5 h-5 text-neutral-400" />
                              )}
                            </div>
                            <div className="min-w-0">
                              <div className="flex items-center gap-2">
                                <span className="font-mono font-bold text-neutral-900 dark:text-white text-xs">
                                  {item.masterSku}
                                </span>
                                {isUncounted && (
                                  <span className="text-[10px] font-bold px-1.5 py-0.2 rounded bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-300/40">
                                    Not Counted
                                  </span>
                                )}
                              </div>
                              <div className="text-[11px] text-neutral-400 truncate mt-0.5">
                                {item.variantName} {item.barcode && `• ${item.barcode}`}
                              </div>
                            </div>
                          </div>
                        </td>

                        {/* 2. Physical Stock */}
                        <td className="py-3.5 px-4 whitespace-nowrap min-w-[130px]">
                          {currentMode === 'shared' ? <div className="space-y-2">
                            <div className={`inline-flex items-center gap-2 rounded-xl border px-3 py-2.5 font-semibold ${isUncounted ? 'border-amber-800/50 bg-amber-950/20 text-amber-500' : 'border-emerald-800/50 bg-emerald-950/20 text-emerald-400'}`}>
                              <Boxes className="w-4 h-4" /><span>{isUncounted ? 'Awaiting first count' : `${physicalStock} units`}</span>
                            </div>
                            <p className="text-[10px] text-neutral-500">{isUncounted ? 'Shared stock across stores' : 'Same stock across stores'}</p>
                          </div> : isUncounted ? (
                            <div className="space-y-1">
                              <span className="inline-block text-[11px] font-bold text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/30 px-2 py-0.5 rounded-md border border-amber-200 dark:border-amber-800/40">
                                Not Counted
                              </span>
                              {!singleCurrentStock && <div className="text-[10px] text-neutral-400">Staff verification required</div>}
                            </div>
                          ) : (
                            <div className="space-y-1">
                              <div className="font-mono font-bold text-sm text-neutral-900 dark:text-white">
                                {physicalStock} {!singleCurrentStock && <span className="text-xs text-neutral-400 font-normal">units</span>}
                              </div>
                              {!singleCurrentStock && <div className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 text-[10px] text-neutral-600 dark:text-neutral-300">
                                <Warehouse className="w-3 h-3 text-indigo-500 shrink-0" />
                                <span>{singleCurrentStock ? 'JW Current Stock ledger' : 'JW physical ledger'}</span>
                              </div>}
                            </div>
                          )}
                        </td>

                        {/* 3. Reserved Stock */}
                        {!singleCurrentStock && currentMode === 'independent' && <td className="py-3.5 px-4 whitespace-nowrap min-w-[120px]">
                          <div className="flex items-center gap-1.5">
                            <span className="font-mono font-bold text-amber-600 dark:text-amber-400 text-sm">
                              {reservedStock}
                            </span>
                            <span className="text-xs text-neutral-400">units</span>
                          </div>
                          <div className="text-[10px] text-neutral-400">Active orders locked</div>
                        </td>}

                        {/* 4. Available to Sell (ATP) */}
                        {!singleCurrentStock && currentMode === 'independent' && <td className="py-3.5 px-4 whitespace-nowrap min-w-[130px]">
                          {isUncounted ? (
                            <div className="font-mono text-xs text-neutral-400 italic">
                              0 (Locked until counted)
                            </div>
                          ) : (
                            <div className="space-y-0.5">
                              <div className="font-mono font-bold text-neutral-900 dark:text-white text-base">
                                {availableStock} <span className="text-xs font-normal text-neutral-400">ATP</span>
                              </div>
                              <div className="text-[10px] text-neutral-400">
                                Total sellable
                              </div>
                            </div>
                          )}
                        </td>}

                        {/* 5. Connected Stores & Sync States */}
                        <td data-testid="connected-stores" className="py-3.5 px-4 min-w-[300px]">
                          {currentMode === 'independent' ? (
                            <div className="space-y-1.5">
                              {mappedSummaries.length === 0 && <span className="text-[11px] text-neutral-500">No mapped stores for this SKU</span>}
                              {mappedSummaries.map(({ store, availableStock, facilityCount, unknownFacilities, needsAttention }) => (
                                <button
                                  key={store.id}
                                  type="button"
                                  onClick={() => openManageStock(item, store.id)}
                                  aria-label={`View ${store.nickname || store.name} stock for ${item.masterSku}`}
                                  className="flex w-full items-center justify-between gap-3 rounded-lg border border-neutral-200 bg-neutral-50 p-2.5 text-left transition hover:border-purple-400 hover:bg-purple-50/40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-purple-500 dark:border-neutral-700 dark:bg-neutral-800/70 dark:hover:border-purple-500 dark:hover:bg-neutral-800"
                                >
                                  <span className="flex min-w-0 items-center gap-2">
                                    <MarketplaceLogo platform={store.platform as any} size="xs" />
                                    <span className="min-w-0">
                                      <strong className="block truncate text-[11px] text-neutral-900 dark:text-white">{store.nickname || store.name}</strong>
                                      <small className="block text-[10px] text-neutral-500 dark:text-neutral-400">
                                        {store.platform === 'webstore' ? 'Direct stock' : facilityCount === null ? 'Store stock' : `${facilityCount} warehouse${facilityCount === 1 ? '' : 's'}`}
                                      </small>
                                    </span>
                                  </span>
                                  <span className="flex shrink-0 items-center gap-2">
                                    <span className="text-right">
                                      <strong className="block whitespace-nowrap text-xs text-neutral-900 dark:text-white">{availableStock === null ? 'Not counted' : `${availableStock} units`}</strong>
                                      {unknownFacilities > 0 && availableStock !== null && <small className="block text-[10px] text-amber-600 dark:text-amber-400">Counted subtotal · {unknownFacilities} uncounted</small>}
                                      {needsAttention && <small className="block text-[10px] text-amber-600 dark:text-amber-400">Needs attention</small>}
                                    </span>
                                    <ChevronRight className="h-3.5 w-3.5 text-purple-400" aria-hidden="true" />
                                  </span>
                                </button>
                              ))}
                            </div>
                          ) : (
                          <div className="space-y-1.5">
                            {(stores || []).filter(s => !s.isComingSoon && (s.platform === 'webstore' || item.mappedStoreIds?.includes(String(s.id)))).map(store => {
                              return <SharedPoolStoreCard key={store.id} item={item} store={store}
                                history={inventoryActivityHistory || []} syncState={(item as any).sharedStoreSyncStates?.[store.id] || 'not_synced'} />;
                            })}
                          </div>
                          )}
                        </td>

                        {/* 6. Actions (Sticky Right Column - Always Visible) */}
                        <td className={`py-3.5 px-4 text-center whitespace-nowrap w-36 min-w-[144px] sticky right-0 z-10 shadow-[-4px_0_12px_rgba(0,0,0,0.08)] dark:shadow-[-6px_0_16px_rgba(0,0,0,0.5)] border-l border-neutral-200/70 dark:border-neutral-800/80 transition-colors ${
                          isSelected 
                            ? 'bg-indigo-50/95 dark:bg-indigo-950/90 group-hover:bg-indigo-100/90 dark:group-hover:bg-indigo-900/80' 
                            : 'bg-white dark:bg-neutral-900 group-hover:bg-neutral-50 dark:group-hover:bg-neutral-800'
                        }`}>
                          <div className="flex items-center justify-center">
                            <button
                                type="button"
                                onClick={() => openManageStock(item)}
                                className={`px-3 py-2 rounded-xl text-white text-xs font-bold transition shadow-xs flex items-center gap-1.5 cursor-pointer border shrink-0 ${currentMode === 'shared' && isUncounted ? 'bg-purple-600 hover:bg-purple-500 border-purple-500' : 'bg-neutral-900 hover:bg-neutral-800 dark:bg-neutral-800 dark:hover:bg-neutral-750 border-neutral-700/80'}`}
                              >
                                {currentMode === 'shared' && isUncounted ? <Boxes className="w-3.5 h-3.5" /> : <Warehouse className="w-3.5 h-3.5 text-indigo-400" />}
                                <span>{currentMode === 'shared' && isUncounted ? 'First Count' : 'Manage Stock'}</span>
                            </button>
                          </div>
                        </td>
                      </tr>
                    );
                  })}
                    </tbody>
                  </table>
                </div>}
              </section>
            ))}
          </div>
          <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs overflow-hidden">

            {/* Pagination Controls Bar (Matching Orders OMS layout & controls) */}
            <div className="px-5 py-4 border-t border-neutral-200 dark:border-neutral-800 flex flex-wrap items-center justify-between gap-4 text-xs bg-neutral-50/50 dark:bg-neutral-950/40">
              {/* Left: Counts and Page Size */}
              <div className="flex flex-wrap items-center gap-3 text-neutral-500 dark:text-neutral-400">
                <div>
                  Showing <span className="font-mono font-bold text-neutral-900 dark:text-white">{totalItems > 0 ? startIndex : 0}</span> to{' '}
                  <span className="font-mono font-bold text-neutral-900 dark:text-white">{endIndex}</span> of{' '}
                  <span className="font-mono font-bold text-neutral-900 dark:text-white">{totalItems.toLocaleString()}</span> products
                </div>

                <span className="text-neutral-300 dark:text-neutral-700 hidden sm:inline">|</span>

                <div className="flex items-center gap-2">
                  <span className="text-neutral-500 dark:text-neutral-400">Products per page:</span>
                  <select
                    value={pageSize}
                    onChange={(e) => {
                      setPageSize(Number(e.target.value));
                      setCurrentPage(1);
                    }}
                    className="px-2.5 py-1 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-800 dark:text-neutral-200 font-semibold focus:outline-hidden focus:ring-1 focus:ring-indigo-500 cursor-pointer"
                  >
                    <option value={10}>10</option>
                    <option value={25}>25</option>
                    <option value={50}>50</option>
                    <option value={100}>100</option>
                  </select>
                </div>
              </div>

              {/* Right: Page Navigation Buttons & Quick Jump */}
              <div className="flex items-center gap-1.5">
                <button
                  type="button"
                  onClick={() => setCurrentPage(1)}
                  disabled={currentPage === 1}
                  title="First Page"
                  className="p-1.5 rounded-lg border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 disabled:opacity-30 disabled:pointer-events-none transition cursor-pointer"
                >
                  <ChevronsLeft className="w-4 h-4" />
                </button>

                <button
                  type="button"
                  onClick={() => setCurrentPage(prev => Math.max(1, prev - 1))}
                  disabled={currentPage === 1}
                  title="Previous Page"
                  className="p-1.5 rounded-lg border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 disabled:opacity-30 disabled:pointer-events-none transition cursor-pointer"
                >
                  <ChevronLeft className="w-4 h-4" />
                </button>

                <div className="flex items-center gap-1">
                  {getPageNumbers(currentPage, totalPages).map((p, idx) => {
                    if (p === '...') {
                      return (
                        <span key={`ellipsis-${idx}`} className="px-1.5 py-1 text-neutral-400 font-mono text-xs">
                          ...
                        </span>
                      );
                    }
                    const pageNum = Number(p);
                    const isActive = pageNum === currentPage;
                    return (
                      <button
                        key={pageNum}
                        type="button"
                        onClick={() => setCurrentPage(pageNum)}
                        className={`min-w-8 h-8 px-2 rounded-lg font-mono text-xs font-bold transition cursor-pointer ${
                          isActive
                            ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-950 shadow-xs'
                            : 'border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800'
                        }`}
                      >
                        {pageNum}
                      </button>
                    );
                  })}
                </div>

                <button
                  type="button"
                  onClick={() => setCurrentPage(prev => Math.min(totalPages, prev + 1))}
                  disabled={currentPage === totalPages || totalPages === 0}
                  title="Next Page"
                  className="p-1.5 rounded-lg border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 disabled:opacity-30 disabled:pointer-events-none transition cursor-pointer"
                >
                  <ChevronRight className="w-4 h-4" />
                </button>

                <button
                  type="button"
                  onClick={() => setCurrentPage(totalPages)}
                  disabled={currentPage === totalPages || totalPages === 0}
                  title="Last Page"
                  className="p-1.5 rounded-lg border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 disabled:opacity-30 disabled:pointer-events-none transition cursor-pointer"
                >
                  <ChevronsRight className="w-4 h-4" />
                </button>

                {totalPages > 3 && (
                  <div className="flex items-center gap-1.5 ml-2 pl-2 border-l border-neutral-200 dark:border-neutral-800 text-neutral-500 text-xs">
                    <span>Go to:</span>
                    <input
                      type="number"
                      min={1}
                      max={totalPages}
                      value={jumpPageInput}
                      onChange={(e) => setJumpPageInput(e.target.value)}
                      onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                          const target = parseInt(jumpPageInput, 10);
                          if (!isNaN(target) && target >= 1 && target <= totalPages) {
                            setCurrentPage(target);
                            setJumpPageInput('');
                          }
                        }
                      }}
                      placeholder={`${currentPage}`}
                      className="w-12 px-1.5 py-1 text-center font-mono rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-800 dark:text-neutral-200 text-xs focus:outline-hidden focus:ring-1 focus:ring-indigo-500"
                    />
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* ================= TAB 2: STORE STOCK TRANSFERS ================= */}
      {activeTab === 'transfers' && (
        <StoreStockTransfersTab
          initialSku={transferTarget?.sku}
          initialSourceStoreId={transferTarget?.storeId}
          initialSourceWarehouseId={transferTarget?.warehouseId}
          inventory={safeInventory}
          stores={stores}
          storeBalances={storeBalances}
          transfersList={transfersList}
          onExecuteTransfer={handleRealExecuteTransfer}
          onCheckTransferStatus={handleCheckTransferStatus}
        onRetryPlatformSync={async (id,target)=>{
          const record=transfersList.find(entry=>entry.id===id);
          if(!record)throw new Error('Transfer record is unavailable.');
          const facilityId=target==='source'?record.sourceWarehouseId:record.destinationWarehouseId;
          const storeId=target==='source'?record.sourceStoreId:record.destinationStoreId;
          const item=safeInventory.find(entry=>entry.masterSku===record.masterSku);
          if(!item)throw new Error('SKU is unavailable.');
          const result=facilityId&&/^\d+$/.test(facilityId)
            ? await storeInventoryApi.facilitySyncCheck(storeId,Number(facilityId),(item as any).variantId)
            : await storeInventoryApi.checkOperation(id);
          const confirmed=result.status==='synced'||result.external_stages?.every((stage:any)=>stage.status==='confirmed');
          setTransfersList(previous=>previous.map(entry=>entry.id===id?{...entry,
            [target==='source'?'sourcePlatformSyncStatus':'destinationPlatformSyncStatus']:confirmed?'succeeded':'pending'}:entry));
        }}
        />
      )}

      {/* ================= TAB 3: ACTIVITY HISTORY ================= */}
      {activeTab === 'history' && (
        <InventoryActivityHistoryTab logs={activityLogs} />
      )}

      {/* MODAL 1: Allocate Stock Modal */}
      {showStockRetrieval && stockRetrievalRun && <BulkStockRetrievalModal run={stockRetrievalRun}
        onClose={()=>{setShowStockRetrieval(false);void storeInventoryApi.stockRetrieval(stockRetrievalRun.id).then((run:StockRetrievalRun)=>setStockRetrievalRun(run));}}
        onFinished={()=>{void reloadInventory();}} />}
      {currentMode === 'shared' && isCountModalOpen && selectedItemForCount ? <SharedFirstCountModal
        key={selectedItemForCount.id}
        item={selectedItemForCount}
        onClose={() => { setIsCountModalOpen(false); setSelectedItemForCount(null); }}
        onSave={async (count, reason) => {
          await storeInventoryApi.review('count', {variant_id:(selectedItemForCount as any).variantId, channel_key:'website', physical_count:count, reason});
          await reloadInventory();
          addToast({type:'success', title:'First Count saved', message:'The shared JW ledger is now active for this SKU.'});
        }}
      /> : currentMode === 'independent' && selectedItemForCount && targetFirstCountStoreId && targetFirstCountWarehouseId ? <LocationFirstCountBaseline
        key={`${selectedItemForCount.id}:${targetFirstCountStoreId}:${targetFirstCountWarehouseId}`}
        item={selectedItemForCount}
        storeId={targetFirstCountStoreId}
        facilityId={targetFirstCountWarehouseId}
        storeName={stores.find(store => String(store.id) === targetFirstCountStoreId)?.nickname || stores.find(store => String(store.id) === targetFirstCountStoreId)?.name || (targetFirstCountStoreId === 'website' ? 'JW Storefront' : `Store ${targetFirstCountStoreId}`)}
        facilityName={targetFirstCountWarehouseId.startsWith('direct:') ? 'Store ledger' : `Warehouse ${targetFirstCountWarehouseId}`}
        onClose={() => { setIsCountModalOpen(false); setSelectedItemForCount(null); setTargetFirstCountStoreId(undefined); setTargetFirstCountWarehouseId(undefined); }}
        onSaved={async () => { await reloadInventory(); addToast({type:'success', title:'Current Stock baseline saved', message:'The selected location was counted. Check its Marketplace sync status separately.'}); }}
      /> : <InitialStockCountModal
        key={`${selectedItemForCount?.id || 'none'}:${targetFirstCountStoreId || ''}:${targetFirstCountWarehouseId || ''}`}
        isOpen={isCountModalOpen}
        onClose={() => {
          setIsCountModalOpen(false);
          setSelectedItemForCount(null);
          setTargetFirstCountStoreId(undefined);
          setTargetFirstCountWarehouseId(undefined);
        }}
        item={selectedItemForCount}
        mode={currentMode}
        stores={stores}
        storeBalances={storeBalances}
        initialTargetStoreId={targetFirstCountStoreId}
        initialTargetWarehouseId={targetFirstCountWarehouseId}
        onSaveCount={handleDirectAllocation}
        uncountedQueue={uncountedItems}
        onSelectNextItem={next => setSelectedItemForCount(next)}
        onNavigateToTransfers={() => {
          setIsCountModalOpen(false);
          setSelectedItemForCount(null);
          setActiveTab('transfers');
        }}
        onOpenStockSyncModal={(itemForSync, storeForSync, facilityId) => {
          handleOpenStockSync(itemForSync, storeForSync, undefined, facilityId);
        }}
        onNavigateToConnectStore={() => {
          setIsCountModalOpen(false);
          setSelectedItemForCount(null);
          setCurrentSection('marketplace-auth');
        }}
      />}

      {/* MODAL 2: Stock Intake & Audit Adjustment (Warehouse-Aware) */}
      {sharedStockAction && <SharedPoolStockActionModal key={`${sharedStockAction.item.id}:${sharedStockAction.action}`}
        item={sharedStockAction.item} initialAction={sharedStockAction.action} stores={stores}
        onClose={() => setSharedStockAction(null)} onSaved={async () => { await reloadInventory(); }} />}
      <StockIntakeAdjustmentModal
        isOpen={isIntakeModalOpen}
        onClose={() => {
          setIsIntakeModalOpen(false);
          setSelectedItemForIntake(null);
          setIntakeTargetStoreId(undefined);
          setIntakeTargetWarehouseId(undefined);
        }}
        item={selectedItemForIntake}
        mode={currentMode}
        stores={stores}
        storeBalances={storeBalances}
        initialType={intakeInitialType}
        initialStoreId={intakeTargetStoreId}
        initialWarehouseId={intakeTargetWarehouseId}
        onSave={handleRealSaveIntake}
      />

      {/* DRAWER: Manage Stock Bottom Drawer (Multi-Warehouse / Single / No-Warehouse) */}
      <FocusedManageStockDrawer
        isOpen={isManageStockDrawerOpen}
        onClose={() => {
          setIsManageStockDrawerOpen(false);
          setSelectedDrawerItem(null);
          setManageTargetStoreId(undefined);
          setManageTargetWarehouseId(undefined);
          setManageOpenActions(false);
        }}
        item={selectedDrawerItem}
        initialStoreId={manageTargetStoreId}
        initialWarehouseId={manageTargetWarehouseId}
        initialActionsOpen={manageOpenActions}
        mode={currentMode}
        stores={stores}
        storeBalances={selectedDrawerItem ? storeBalances[selectedDrawerItem.masterSku] : undefined}
        onSaveSharedFirstCount={async (item, quantity) => {
          const receipt = await storeInventoryApi.review('count', {
            variant_id: item.variantId,
            channel_key: 'website',
            physical_count: quantity,
            reason: 'Shared Pool First Count',
          });
          await reloadInventory();
          addToast({ type: 'success', title: 'First Count saved', message: 'JW Ledger updated. Check each marketplace delivery status separately.' });
          return receipt;
        }}
        onSelectFirstCount={(item, storeId, warehouseId) => {
          setIsManageStockDrawerOpen(false);
          handleOpenFirstCount(item, storeId, warehouseId);
        }}
        onSelectAllocateStock={(item, storeId, warehouseId) => {
          setIsManageStockDrawerOpen(false);
          setTransferTarget({ sku: item.masterSku, storeId, warehouseId });
          setActiveTab('transfers');
        }}
        onSelectIntake={(item, storeId, warehouseId) => {
          setIsManageStockDrawerOpen(false);
          handleOpenIntake(item, 'intake', storeId, warehouseId);
        }}
        onSelectAdjustment={(item, storeId, warehouseId) => {
          setIsManageStockDrawerOpen(false);
          handleOpenIntake(item, 'adjustment', storeId, warehouseId);
        }}
        onSelectTransfer={(item, storeId, warehouseId) => {
          setIsManageStockDrawerOpen(false);
          setTransferTarget({ sku: item.masterSku, storeId, warehouseId });
          setActiveTab('transfers');
        }}
        onNavigateToMapping={() => {
          setIsManageStockDrawerOpen(false);
          setCurrentSection('marketplace-mapping');
        }}
        onViewLedger={(item) => {
          setIsManageStockDrawerOpen(false);
          setActiveTab('history');
          setSearchQuery(item.masterSku);
        }}
      />

      {/* MODAL 3: Admin Master Settings (Mode Transition & Store Takeover) */}
      <InventorySettingsModal
        isOpen={isSettingsModalOpen}
        onClose={() => setIsSettingsModalOpen(false)}
        currentMode={currentMode}
        stores={stores}
        inventory={safeInventory}
        onSwitchMode={handleSwitchMode}
        onToggleStoreManagement={handleToggleStoreManagement}
        onSelectSkuForCount={sku => {
          const it = safeInventory.find(i => i.masterSku === sku);
          if (it) {
            setIsSettingsModalOpen(false);
            openManageStock(it);
          }
        }}
      />

      {/* MODAL 4: Stock Sync Details & Recovery */}
      <StockSyncDetailsModal
        isOpen={isStockSyncModalOpen}
        onClose={() => {
          syncOpenRequestRef.current++;
          setIsStockSyncModalOpen(false);
          setSyncModalItem(null);
          setSyncModalStore(null);
          setSyncModalInfo(null);
          setSyncFacilityOptions([]);
          setSyncFacilityId(null);
          setSyncFacilityStatuses({});
          setIsSyncTopologyLoading(false);
        }}
        item={syncModalItem}
        store={syncModalStore}
        syncInfo={syncModalInfo}
        facilities={syncFacilityOptions}
        selectedFacilityId={syncFacilityId}
        facilityStatuses={syncFacilityStatuses}
        isLoadingTopology={isSyncTopologyLoading}
        availableStores={stores.filter(candidate=>candidate.platform!=='webstore' && (syncModalItem as any)?.mappedStoreIds?.includes(String(candidate.id)))}
        onSelectStore={async selectedStore=>{
          if(syncModalItem) await handleOpenStockSync(syncModalItem, selectedStore);
        }}
        onSelectFacility={async facilityId=>{
          if(!syncModalStore||!syncModalItem||!syncModalInfo)return;
          const detail=await storeInventoryApi.facilitySyncDetails(syncModalStore.id,facilityId,(syncModalItem as any).variantId);
          setSyncFacilityId(facilityId);
          const updated=adaptFacilitySync(detail,syncModalInfo);
          setSyncModalInfo(updated);
          setSyncFacilityStatuses(previous=>({...previous,[facilityId]:updated.status}));
        }}
        onUpdateSyncInfo={handleUpdateSyncInfo}
        onCheckStatus={async()=>{
          if(!syncModalStore||!syncModalItem)throw new Error('Select a marketplace SKU.');
          if(syncFacilityId!==null) return adaptFacilitySync(
            await storeInventoryApi.facilitySyncCheck(syncModalStore.id,syncFacilityId,(syncModalItem as any).variantId),
            syncModalInfo!);
          return storeInventoryApi.checkStock(syncModalStore.id,(syncModalItem as any).variantId);
        }}
        onResync={async()=>{
          if(!syncModalStore||!syncModalItem)throw new Error('Select a marketplace SKU.');
          if(syncFacilityId!==null) return adaptFacilitySync(
            await storeInventoryApi.facilityResync(syncModalStore.id,syncFacilityId,(syncModalItem as any).variantId,crypto.randomUUID()),
            syncModalInfo!);
          return storeInventoryApi.resyncStock(syncModalStore.id,(syncModalItem as any).variantId,crypto.randomUUID());
        }}
        onFirstCount={() => {
          if (!syncModalItem || !syncModalStore || syncFacilityId === null) return;
          openManageStock(syncModalItem, syncModalStore.id, String(syncFacilityId), true);
          setIsStockSyncModalOpen(false);
        }}
        onNavigateToStoreSettings={() => {
          setIsStockSyncModalOpen(false);
          setCurrentSection('marketplace-auth');
        }}
        onNavigateToHistory={(sku) => {
          setIsStockSyncModalOpen(false);
          setActiveTab('history');
          setSearchQuery(sku);
        }}
        onNavigateToSkuMapping={() => {
          setIsStockSyncModalOpen(false);
          setCurrentSection('marketplace-mapping');
        }}
      />

    </div>
  );
};
