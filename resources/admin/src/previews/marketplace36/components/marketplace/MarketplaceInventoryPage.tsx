import React, { useState, useMemo, useEffect } from 'react';
import {readPhysicalHistory} from '../../readPhysicalHistory';
import {readSafetyHistory} from '../../readSafetyHistory';
import {readSandboxHistory} from '../../readSandboxHistory';
import { useAdmin } from '../../context/AdminContext';
import {
  MarketplaceInventoryItem,
  MarketplacePlatform,
  ChannelSkuMapping,
  EntityWarehouse,
  StoreFulfillmentBinding,
  WarehouseStockBalance,
  InventoryOperatingMode
} from '../../types';
import { ChannelMappingModal } from './ChannelMappingModal';
import { MarketplaceLogo } from './MarketplaceLogo';
import { SubNavHeader } from '../common/SubNavHeader';
import { PhysicalCountModal as InventoryAdjustmentModal } from '../../../marketplace34/PhysicalCountModal';
import { InventoryStoreBroadcastModal } from './InventoryStoreBroadcastModal';
import { StoreActivationsDrawer } from './StoreActivationsDrawer';
import {OrderActivationControl} from '../../OrderActivationControl';
import { InventoryOrderExceptionsDrawer } from './InventoryOrderExceptionsDrawer';
import { ModeAndWarehouseConfigTab } from './ModeAndWarehouseConfigTab';
import { StoreWarehouseBindingTab } from './StoreWarehouseBindingTab';
import { WarehouseTransferTab } from './WarehouseTransferTab';
import { ModeSwitchModal } from './ModeSwitchModal';
import { EntityWarehouseModal } from './EntityWarehouseModal';
import { StoreFulfillmentBindingModal } from './StoreFulfillmentBindingModal';
import { CreateWarehouseTransferModal } from './CreateWarehouseTransferModal';
import { ConnectedReconciliationModal } from '../../ConnectedReconciliationModal';
import {readWarehouseBalances} from '../../readWarehouseBalances';
import {matchesStockFilter} from '../../stockFilter';
import {inventoryIntakeItems} from '../../../../marketplace/inventoryIntake';
import {
  Warehouse,
  Package,
  Layers,
  RefreshCw,
  Search,
  Link2,
  AlertTriangle,
  CheckCircle2,
  Plus,
  History,
  Sparkles,
  Boxes,
  MapPin,
  X,
  Download,
  Store,
  FileSpreadsheet,
  ShieldAlert,
  ClipboardCheck,
  ChevronDown,
  ChevronUp,
  Split,
  Merge,
  ArrowRightLeft,
  Sliders,
  Settings,
  ShieldCheck
} from 'lucide-react';

export const MarketplaceInventoryPage: React.FC<{intakeProductIds?:number[]}> = ({intakeProductIds=[]}) => {
  const {
    marketplaceInventory = [],
    marketplaceOrders = [],
    marketplaceStoreProducts = [],
    connections = [],
    syncLogs = [],
    storeActivations = [],
    inventoryOrderExceptions = [],
    staffUsers = [],
    user,
    inventoryMode = 'independent_warehouses',
    entityWarehouses = [],
    storeFulfillmentBindings = [],
    interWarehouseTransfers = [],
    inventoryModeSwitchAudits = [],
    setInventoryModeWithAudit,
    setInventoryModeDirect,
    checkModeSwitchReadiness,
    reconcileWarehouseInventory,
    reloadInventory,
    saveEntityWarehouse,
    deleteEntityWarehouse,
    saveStoreFulfillmentBinding,
    createWarehouseTransfer,
    prepareWarehouseTransfer,
    updateTransferStatus,
    saveStoreActivation,
    adjustStock,
    recordPhysicalCountAudit,
    broadcastStockToStore,
    broadcastStockToAllStores,
    broadcastStockToChannels,
    broadcastAllStockToChannels,
    linkChannelSku,
    addToast
  } = useAdmin();

  const [loadedBalances,setLoadedBalances]=useState<Record<string,WarehouseStockBalance[]>>({});
  const safeMpInv = (marketplaceInventory || []).map(item=>({...item,
    warehouseBalances:loadedBalances[item.id]??item.warehouseBalances}));
  const safeMpOrders = marketplaceOrders || [];
  const safeStoreProds = marketplaceStoreProducts || [];
  const safeConns = connections || [];
  const safeLogs = syncLogs || [];
  const safeActivations = storeActivations || [];
  const safeExceptions = inventoryOrderExceptions || [];
  const safeTransfers = interWarehouseTransfers || [];

  // Tab State
  const [activeTab, setActiveTab] = useState<'matrix' | 'mode_warehouses' | 'store_bindings' | 'transfers'>('matrix');
  const [expandedSkuRows, setExpandedSkuRows] = useState<Record<string, boolean>>({});
  const [adjustModalTargetWarehouseId, setAdjustModalTargetWarehouseId] = useState<string | undefined>(undefined);

  // Modals State
  const [isModeSwitchModalOpen, setIsModeSwitchModalOpen] = useState<boolean>(false);
  const [modeSwitchInitialTarget, setModeSwitchInitialTarget] = useState<InventoryOperatingMode | undefined>(undefined);
  const [isWarehouseModalOpen, setIsWarehouseModalOpen] = useState<boolean>(false);
  const [editingWarehouse, setEditingWarehouse] = useState<EntityWarehouse | null>(null);
  const [editingBinding, setEditingBinding] = useState<StoreFulfillmentBinding | null>(null);
  const [isCreateTransferModalOpen, setIsCreateTransferModalOpen] = useState<boolean>(false);
  const [transferInventory,setTransferInventory]=useState<MarketplaceInventoryItem[]>([]);
  const [reconcileTarget, setReconcileTarget] = useState<{
    item: MarketplaceInventoryItem;
    balance: WarehouseStockBalance;
  } | null>(null);

  const toggleExpandSku = async (sku: string) => {
    if(!expandedSkuRows[sku]) {
      const item=safeMpInv.find(item=>item.masterSku===sku);
      if(!item)return;
      try {
        const balances=await readWarehouseBalances(Number(item.id));
        setLoadedBalances(previous=>({...previous,[item.id]:balances}));
      } catch(error) {
        addToast({type:'error',title:'Warehouse balances unavailable',message:(error as Error).message});
        return;
      }
    }
    setExpandedSkuRows(prev => ({
      ...prev,
      [sku]: !prev[sku]
    }));
  };

  const [searchQuery, setSearchQuery] = useState('');
  const [filterStockStatus, setFilterStockStatus] = useState<'all' | 'healthy' | 'low_stock' | 'oos' | 'awaiting_audit'>('all');
  const [filterPlatform, setFilterPlatform] = useState<'all' | 'shopee' | 'lazada'>('all');

  // Modals & Drawers
  const [selectedMappingItem, setSelectedMappingItem] = useState<MarketplaceInventoryItem | null>(null);
  const [selectedAuditItem, setSelectedAuditItem] = useState<MarketplaceInventoryItem | null>(null);
  const [physicalHistory,setPhysicalHistory]=useState<Awaited<ReturnType<typeof readPhysicalHistory>>>([]);
  const [historyMessage,setHistoryMessage]=useState('');
  const [historyRevision,setHistoryRevision]=useState(0);
  const [rechecking,setRechecking]=useState(false);
  const recheckAttempt=async(id:string)=>{
    if(rechecking)return;
    setRechecking(true);
    try {
      const csrf=document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
      if(!csrf)throw new Error('Reload the authenticated session.');
      const response=await fetch(`/admin/api/inventory/sandbox-attempts/${encodeURIComponent(id)}/recheck`,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':csrf}});
      const result=await response.json();
      if(!response.ok)throw new Error(result.message||'Platform recheck failed.');
      if(result.id!==id)throw new Error('Recheck receipt identity mismatch.');
      setHistoryRevision(value=>value+1);
    }catch(error){setHistoryMessage(error instanceof Error?error.message:'Platform recheck failed.');}
    finally{setRechecking(false);}
  };
  useEffect(()=>{
    const abort=new AbortController();
    setPhysicalHistory([]);
    if(!selectedAuditItem)return()=>abort.abort();
    setHistoryMessage('Loading stock ledger…');
    Promise.all([readPhysicalHistory(selectedAuditItem.id,abort.signal),readSafetyHistory(selectedAuditItem.id,abort.signal),readSandboxHistory(selectedAuditItem.id,abort.signal)]).then(histories=>{
      const rows=histories.flat().sort((a,b)=>Date.parse(b.timestamp)-Date.parse(a.timestamp));
      if(abort.signal.aborted)return;
      setPhysicalHistory(rows);setHistoryMessage(rows.length?'':'No recorded stock operations.');
    }).catch(error=>{if(!abort.signal.aborted)setHistoryMessage(error.message);});
    return()=>abort.abort();
  },[selectedAuditItem?.id,historyRevision]);
  const [adjustModalItem, setAdjustModalItem] = useState<MarketplaceInventoryItem | null>(null);
  const [intakeQueue,setIntakeQueue]=useState<string[]>([]);
  const [intakeActive,setIntakeActive]=useState(intakeProductIds.length>0);
  const countConfirmed=React.useRef(false);
  useEffect(()=>{
    if(!intakeActive||!intakeProductIds.length||!safeMpInv.length||intakeQueue.length||adjustModalItem)return;
    const imported=inventoryIntakeItems<MarketplaceInventoryItem>(safeMpInv,intakeProductIds);
    if(!imported.length)return;
    const names=Array.from(new Set(imported.map(item=>item.productName)));
    setSearchQuery(names.length===1?names[0]:'');
    setFilterStockStatus('all');setFilterPlatform('all');setActiveTab('matrix');
    const pending=imported.filter(item=>item.physicalStockStatus==='unknown'||item.totalPhysicalStock===null).map(item=>item.id);
    setIntakeQueue(pending);
    if(pending.length)setAdjustModalItem(imported.find(item=>item.id===pending[0])??null);
    else setIntakeActive(false);
  },[intakeActive,intakeProductIds.join(','),safeMpInv.length]);
  const [broadcastModalItem, setBroadcastModalItem] = useState<MarketplaceInventoryItem | null>(null);
  const [isStoreActivationsOpen, setIsStoreActivationsOpen] = useState<boolean>(false);
  const [isExceptionsDrawerOpen, setIsExceptionsDrawerOpen] = useState<boolean>(false);

  // Filter items
  const filteredInventory = useMemo(() => {
    return safeMpInv.filter(item => {
      if(intakeActive&&intakeProductIds.length&&!intakeProductIds.map(String).includes(String(item.jwProductId)))return false;
      if (!matchesStockFilter(item,filterStockStatus)) return false;

      // Platform filter (Release 1: Shopee & Lazada)
      if (filterPlatform !== 'all') {
        const hasStore = (item.storeAssociations || []).some(a => a.platform === filterPlatform);
        if (!hasStore) return false;
      }

      // Search
      if (searchQuery.trim()) {
        const q = searchQuery.toLowerCase();
        const matchesSku = item.masterSku.toLowerCase().includes(q);
        const matchesName = item.productName.toLowerCase().includes(q);
        const matchesVariant = item.variantName.toLowerCase().includes(q);
        const matchesBin = item.warehouseLocation.toLowerCase().includes(q);
        const matchesStore = (item.storeAssociations || []).some(a => 
          a.storeName.toLowerCase().includes(q) ||
          a.externalSku.toLowerCase().includes(q) ||
          (a.storeNickname && a.storeNickname.toLowerCase().includes(q))
        );
        if (!matchesSku && !matchesName && !matchesVariant && !matchesBin && !matchesStore) {
          return false;
        }
      }
      return true;
    });
  }, [safeMpInv, filterStockStatus, filterPlatform, searchQuery, intakeActive, intakeProductIds.join(',')]);

  const handleExportInventoryCSV = () => {
    const headers = [
      'Master SKU',
      'Product Name',
      'Variant',
      'Warehouse Bin',
      'Physical Stock Status',
      'Physical Stock',
      'Reserved Stock',
      'Safety Stock',
      'ATP Available',
      'Selling Price',
      'Connected Stores'
    ];
    const rows = filteredInventory.map(i => [
      i.masterSku,
      `"${i.productName}"`,
      `"${i.variantName}"`,
      i.warehouseLocation,
      i.physicalStockStatus || 'verified',
      i.totalPhysicalStock !== null ? i.totalPhysicalStock : 'Unverified',
      i.reservedStock,
      i.safetyStock ?? 0,
      i.availableStock !== null ? i.availableStock : 'Pending Audit',
      i.sellingPrice,
      `"${(i.storeAssociations || []).map(a => `${a.storeName} [${a.externalSku}]`).join('; ')}"`
    ]);
    const csvContent = 'data:text/csv;charset=utf-8,' + [headers.join(','), ...rows.map(e => e.join(','))].join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `marketplace_inventory_matrix_${Date.now()}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    addToast({ type: 'success', title: 'Inventory Matrix Exported', message: `Exported ${filteredInventory.length} SKUs.` });
  };

  // Metrics
  const verifiedItems = safeMpInv.filter(i => i.totalPhysicalStock !== null && i.physicalStockStatus !== 'unknown');
  const unverifiedCount = safeMpInv.length - verifiedItems.length;
  const totalPhysical = verifiedItems.reduce((s, i) => s + (i.totalPhysicalStock || 0), 0);
  const totalReserved = safeMpInv.reduce((s, i) => s + i.reservedStock, 0);
  const totalAvailable = safeMpInv.length>0 && safeMpInv.every(i=>i.availableStock!=null)
    ? safeMpInv.reduce((s,i)=>s+i.availableStock,0) : null;
  const lowStockCount = verifiedItems.filter(i => i.availableStock !== null && i.availableStock <= (i.safetyStock ?? 0)).length;

  const connectedCount = safeConns.filter(c => c.status === 'connected').length;
  const pendingOrdersCount = safeMpOrders.filter(o => o.channelStatus === 'AWAITING_COLLECTION' || o.channelStatus === 'UNPAID' || !o.airwayBillGenerated).length;
  const unmappedCount = safeStoreProds.reduce((acc, p) => acc + p.variants.filter(v => v.mappingStatus === 'unmapped').length, 0);
  const activeActivationsCount = safeActivations.filter(a => a.syncState === 'enabled').length;

  return (
    <div className="space-y-6">
      <SubNavHeader
        hubTitle="Marketplace"
        hubSection="marketplace"
        currentPageTitle="Marketplace Inventory & WMS"
        tabs={[
          { id: 'marketplace-auth', label: 'Connect Store', badge: `${connectedCount}/4 Active`, badgeVariant: 'success' },
          { id: 'marketplace-mapping', label: 'Product & SKU Mapping', badge: unmappedCount > 0 ? `${unmappedCount} Unmapped` : 'All Mapped', badgeVariant: unmappedCount > 0 ? 'warning' : 'success' },
          { id: 'marketplace-oms', label: 'Orders (OMS)', badge: pendingOrdersCount > 0 ? `${pendingOrdersCount} Pending` : undefined, badgeVariant: 'warning' },
          { id: 'marketplace-inventory', label: 'Inventory & WMS', badge: `${safeMpInv.length} SKUs`, badgeVariant: 'info' },
          { id: 'marketplace-sync', label: 'Webhooks & Logs', badge: `${safeLogs.length}`, badgeVariant: 'default' },
        ]}
      />

      {/* Top Banner & Omnichannel Action Buttons */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2">
            <h1 className="text-2xl font-black tracking-tight text-neutral-900 dark:text-white">
              Marketplace Inventory & WMS Allocation
            </h1>
            <span className="text-[11px] font-bold px-2 py-0.5 rounded-full bg-neutral-100 dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200 border border-neutral-300 dark:border-neutral-700">
              Release 1: Shopee & Lazada
            </span>
          </div>
          <p className="text-xs text-neutral-500 mt-1">
            Centrally allocate Available-to-Promise (ATP) stock balances across connected Shopee and Lazada flagship and branch stores.
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-2.5">
          {/* Store Activations Drawer Trigger */}
          <button
            onClick={() => setIsStoreActivationsOpen(true)}
            className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 hover:bg-neutral-50 dark:hover:bg-neutral-800 text-neutral-700 dark:text-neutral-300 text-xs font-semibold transition-colors cursor-pointer shadow-xs"
          >
            <Store className="w-3.5 h-3.5 text-neutral-500" />
            <span>Store Activations</span>
            <span className="text-[10px] px-1.5 py-0.2 rounded-full bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 font-bold">
              {activeActivationsCount}/{safeActivations.length}
            </span>
          </button>

          {/* Exceptions Drawer Trigger */}
          <button
            onClick={() => setIsExceptionsDrawerOpen(true)}
            className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 hover:bg-neutral-50 dark:hover:bg-neutral-800 text-neutral-700 dark:text-neutral-300 text-xs font-semibold transition-colors cursor-pointer shadow-xs"
          >
            <AlertTriangle className="w-3.5 h-3.5 text-amber-500" />
            <span>Order Exceptions</span>
            {safeExceptions.length > 0 && (
              <span className="text-[10px] px-1.5 py-0.2 rounded-full bg-amber-500/15 text-amber-700 dark:text-amber-400 font-bold">
                {safeExceptions.length}
              </span>
            )}
          </button>

          {/* Broadcast All */}
          <button
            onClick={broadcastAllStockToChannels}
            className="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100 text-xs font-bold shadow-xs transition-all cursor-pointer"
          >
            <RefreshCw className="w-3.5 h-3.5" />
            Broadcast All Verified ATP
          </button>

          {/* Export Matrix */}
          <button
            onClick={handleExportInventoryCSV}
            className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 hover:bg-neutral-50 dark:hover:bg-neutral-800 text-neutral-700 dark:text-neutral-300 text-xs font-semibold transition-colors cursor-pointer"
            title="Export CSV Matrix"
          >
            <Download className="w-3.5 h-3.5" />
          </button>
        </div>
      </div>

      {/* Secondary Architecture Tabs */}
      <div className="flex flex-wrap items-center gap-2 border-b border-neutral-200 dark:border-neutral-800 pb-3">
        <button
          onClick={() => setActiveTab('matrix')}
          className={`px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 cursor-pointer ${
            activeTab === 'matrix'
              ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 shadow-xs'
              : 'bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-800'
          }`}
        >
          <Layers className="w-3.5 h-3.5" />
          <span>Inventory Overview / SKU Matrix</span>
          <span className="text-[10px] px-1.5 py-0.5 rounded-full bg-neutral-100 dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200 font-mono">
            {safeMpInv.length}
          </span>
        </button>

        <button
          onClick={() => setActiveTab('mode_warehouses')}
          className={`px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 cursor-pointer ${
            activeTab === 'mode_warehouses'
              ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 shadow-xs'
              : 'bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-800'
          }`}
        >
          <Warehouse className="w-3.5 h-3.5" />
          <span>Mode & Warehouses</span>
          <span className="text-[10px] px-1.5 py-0.5 rounded-full bg-purple-500/15 text-purple-700 dark:text-purple-300 font-bold whitespace-nowrap">
            {inventoryMode === 'independent_warehouses' ? 'Independent' : inventoryMode === 'shared_pool' ? 'Shared Pool' : 'Not Configured'} • {entityWarehouses.length} WH
          </span>
        </button>

        <button
          onClick={() => setActiveTab('store_bindings')}
          className={`px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 cursor-pointer ${
            activeTab === 'store_bindings'
              ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 shadow-xs'
              : 'bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-800'
          }`}
        >
          <Store className="w-3.5 h-3.5" />
          <span>Store Bindings & Locations</span>
          <span className="text-[10px] px-1.5 py-0.5 rounded-full bg-neutral-100 dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200 font-mono whitespace-nowrap">
            {storeFulfillmentBindings.length} Stores
          </span>
        </button>

        <button
          onClick={() => setActiveTab('transfers')}
          className={`px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 cursor-pointer ${
            activeTab === 'transfers'
              ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 shadow-xs'
              : 'bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-800'
          }`}
        >
          <ArrowRightLeft className="w-3.5 h-3.5" />
          <span>Warehouse Transfers</span>
          {safeTransfers.filter(t => t.status === 'in_transit').length > 0 && (
            <span className="text-[10px] px-1.5 py-0.5 rounded-full bg-purple-500/20 text-purple-700 dark:text-purple-300 font-bold whitespace-nowrap">
              {safeTransfers.filter(t => t.status === 'in_transit').length} In Transit
            </span>
          )}
        </button>
      </div>

      {/* TAB 1: SKU MATRIX */}
      {activeTab === 'matrix' && (
        <div className="space-y-6">
          {/* Active Operating Architecture Notification Banner */}
          <div className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div className="flex items-center gap-3">
              <div className={`p-2.5 rounded-xl border ${
                inventoryMode === 'unconfigured'
                  ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20'
                  : inventoryMode === 'independent_warehouses'
                    ? 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/20'
                    : 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20'
              }`}>
                {inventoryMode === 'unconfigured' ? (
                  <AlertTriangle className="w-4 h-4" />
                ) : inventoryMode === 'independent_warehouses' ? (
                  <Split className="w-4 h-4" />
                ) : (
                  <Merge className="w-4 h-4" />
                )}
              </div>
              <div>
                <div className="flex items-center gap-2">
                  <span className="text-xs font-bold text-neutral-900 dark:text-white">
                    {inventoryMode === 'unconfigured'
                      ? 'Operating Architecture: Not Configured (Awaiting Selection)'
                      : inventoryMode === 'independent_warehouses'
                        ? 'Operating Mode: Independent Warehouses'
                        : 'Operating Mode: Shared Inventory Pool'}
                  </span>
                  <span className="text-[10px] px-2 py-0.5 rounded-full bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-300 font-medium">
                    {inventoryMode === 'unconfigured'
                      ? 'Action Required'
                      : inventoryMode === 'independent_warehouses'
                        ? `${entityWarehouses.length} Warehouses Independent ATP`
                        : 'Omnichannel Shared Master Stock'}
                  </span>
                </div>
                <p className="text-[11px] text-neutral-500 mt-0.5">
                  {inventoryMode === 'unconfigured'
                    ? 'No architecture configured. Please choose between Independent Warehouses or Unified Shared Pool to initialize routing.'
                    : inventoryMode === 'independent_warehouses'
                      ? 'Each configured physical warehouse tracks its recorded stock independently. Store bindings do not enable automatic order deductions. Expand rows below to view recorded balances.'
                      : 'Single shared inventory pool per SKU across JW Online, Shopee, and Lazada stores; shelf locations act as physical placement tags.'}
                </p>
              </div>
            </div>

            <button
              onClick={() => setIsModeSwitchModalOpen(true)}
              className="px-3 py-1.5 rounded-xl border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-700 dark:text-neutral-300 text-xs font-bold flex items-center gap-1.5 cursor-pointer shrink-0 transition-colors"
            >
              <Sliders className="w-3.5 h-3.5" />
              <span>{inventoryMode === 'unconfigured' ? 'Configure Architecture' : 'Switch Mode'}</span>
            </button>
          </div>

      {/* Metrics Row */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {/* Total Physical Stock */}
        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between text-neutral-500 mb-2">
            <span className="text-xs font-bold uppercase tracking-wider">Total Physical Stock</span>
            <Warehouse className="w-4 h-4 text-neutral-700 dark:text-neutral-300" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-black text-neutral-900 dark:text-white">{totalPhysical}</span>
            <span className="text-xs text-neutral-400">Verified Units</span>
          </div>
          <div className="mt-3 flex items-center justify-between text-[11px] text-neutral-500 font-medium">
            <span>Recorded WMS facilities</span>
            {unverifiedCount > 0 && (
              <span className="text-amber-600 dark:text-amber-400 font-bold bg-amber-500/10 px-1.5 py-0.5 rounded">
                {unverifiedCount} unverified
              </span>
            )}
          </div>
        </div>

        {/* Reserved / In-Order */}
        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between text-neutral-500 mb-2">
            <span className="text-xs font-bold uppercase tracking-wider">Reserved / In-Order</span>
            <Boxes className="w-4 h-4 text-amber-500" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-black text-amber-600 dark:text-amber-400">{totalReserved}</span>
            <span className="text-xs text-neutral-400">Recorded reservations only</span>
          </div>
          <div className="mt-3 flex items-center gap-1.5 text-[11px] text-amber-600 dark:text-amber-400 font-medium">
            <CheckCircle2 className="w-3.5 h-3.5" />
            <span>Unallocated orders are excluded</span>
          </div>
        </div>

        {/* Available-To-Promise (ATP) */}
        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between text-neutral-500 mb-2">
            <span className="text-xs font-bold uppercase tracking-wider">Available-To-Promise</span>
            <Sparkles className="w-4 h-4 text-emerald-500" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-black text-emerald-600 dark:text-emerald-400">{totalAvailable ?? 'Unknown'}</span>
            <span className="text-xs text-neutral-400">Recorded availability only</span>
          </div>
          <div className="mt-3 flex items-center gap-1.5 text-[11px] text-emerald-600 dark:text-emerald-400 font-medium">
            <span>Platform stock broadcasting is disabled</span>
          </div>
        </div>

        {/* Low Stock Warnings */}
        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between text-neutral-500 mb-2">
            <span className="text-xs font-bold uppercase tracking-wider">Low Stock Warnings</span>
            <AlertTriangle className="w-4 h-4 text-rose-500" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-black text-rose-600 dark:text-rose-400">{lowStockCount}</span>
            <span className="text-xs text-neutral-400">SKUs below safety threshold</span>
          </div>
          <div className="mt-3 flex items-center gap-1.5 text-[11px] text-neutral-500 font-medium">
            <span>Only known availability can be assessed</span>
          </div>
        </div>
      </div>

      {/* Filter & Search Bar */}
      <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-2.5 shadow-xs flex flex-wrap items-center justify-between gap-3">
        {/* Status Pills */}
        <div className="flex flex-wrap items-center gap-1">
          {[
            { id: 'all', label: 'All SKUs' },
            { id: 'healthy', label: 'Healthy Stock' },
            { id: 'low_stock', label: 'Low Stock Buffer' },
            { id: 'oos', label: 'Out of Stock' },
            { id: 'awaiting_audit', label: `Awaiting Audit (${unverifiedCount})` },
          ].map(f => {
            const isActive = filterStockStatus === f.id;
            return (
              <button
                key={f.id}
                onClick={() => setFilterStockStatus(f.id as any)}
                className={`px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer ${
                  isActive
                    ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 shadow-xs'
                    : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'
                }`}
              >
                {f.label}
              </button>
            );
          })}
        </div>

        {/* Platform Scope Toggle & Search Input */}
        <div className="flex items-center gap-2.5 w-full sm:w-auto">
          <div className="flex items-center rounded-xl bg-neutral-100 dark:bg-neutral-950 p-1 border border-neutral-200 dark:border-neutral-800 text-xs font-semibold">
            <button
              onClick={() => setFilterPlatform('all')}
              className={`px-2.5 py-1 rounded-lg transition-all cursor-pointer ${
                filterPlatform === 'all'
                  ? 'bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white shadow-xs font-bold'
                  : 'text-neutral-500 hover:text-neutral-900 dark:hover:text-white'
              }`}
            >
              All Stores
            </button>
            <button
              onClick={() => setFilterPlatform('shopee')}
              className={`px-2.5 py-1 rounded-lg transition-all cursor-pointer flex items-center gap-1 ${
                filterPlatform === 'shopee'
                  ? 'bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white shadow-xs font-bold'
                  : 'text-neutral-500 hover:text-neutral-900 dark:hover:text-white'
              }`}
            >
              <MarketplaceLogo platform="shopee" size={12} />
              <span>Shopee</span>
            </button>
            <button
              onClick={() => setFilterPlatform('lazada')}
              className={`px-2.5 py-1 rounded-lg transition-all cursor-pointer flex items-center gap-1 ${
                filterPlatform === 'lazada'
                  ? 'bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white shadow-xs font-bold'
                  : 'text-neutral-500 hover:text-neutral-900 dark:hover:text-white'
              }`}
            >
              <MarketplaceLogo platform="lazada" size={12} />
              <span>Lazada</span>
            </button>
          </div>

          <div className="relative w-full sm:w-64">
            <Search className="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400" />
            <input
              type="text"
              value={searchQuery}
              onChange={e => setSearchQuery(e.target.value)}
              placeholder="Search Master SKU, product, store..."
              className="w-full pl-9 pr-3 py-1.5 text-xs rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 focus:outline-hidden focus:ring-2 focus:ring-neutral-400 text-neutral-900 dark:text-white"
            />
          </div>
        </div>
      </div>

      {/* Master SKU Inventory Matrix Table */}
      <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-neutral-50 dark:bg-neutral-950/70 border-b border-neutral-200 dark:border-neutral-800 text-neutral-500 font-bold uppercase tracking-wider text-[10px]">
              <tr>
                <th className="py-3.5 px-4">Master SKU / Product</th>
                <th className="py-3.5 px-4">Warehouse Bin</th>
                <th className="py-3.5 px-4 text-center">Physical Stock</th>
                <th className="py-3.5 px-4 text-center">Reserved</th>
                <th className="py-3.5 px-4 text-center">Safety Buffer</th>
                <th className="py-3.5 px-4 text-center">Basic Available (excludes unallocated orders)</th>
                <th className="py-3.5 px-4">Marketplace Stores & Link Status</th>
                <th className="py-3.5 px-4 text-right">WMS Operations</th>
              </tr>
            </thead>

            <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800">
              {filteredInventory.length === 0 ? (
                <tr>
                  <td colSpan={8} className="py-12 text-center text-neutral-400">
                    <Package className="w-8 h-8 mx-auto mb-2 text-neutral-300 dark:text-neutral-700" />
                    <p className="font-semibold">No inventory records found</p>
                  </td>
                </tr>
              ) : (
                filteredInventory.map(item => {
                  const isUnknown = item.physicalStockStatus === 'unknown' || item.totalPhysicalStock === null;
                  const isLow = !isUnknown && item.availableStock !== null && item.availableStock <= (item.safetyStock ?? 0);
                  const isOOS = !isUnknown && item.availableStock !== null && item.availableStock <= 0;
                  const associations = item.storeAssociations || [];

                  return (
                    <React.Fragment key={item.id}>
                      <tr className="hover:bg-neutral-50 dark:hover:bg-neutral-800/40 transition-colors">
                      {/* SKU & Product */}
                      <td className="py-4 px-4">
                        <div className="flex items-center gap-2.5">
                          {/* Expansion Toggle Button */}
                          <button
                            type="button"
                            onClick={() => toggleExpandSku(item.masterSku)}
                            className={`p-1 rounded-md text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors cursor-pointer shrink-0 ${
                              expandedSkuRows[item.masterSku] ? 'bg-purple-500/10 text-purple-600 dark:text-purple-400' : ''
                            }`}
                            title="View multi-warehouse stock and bin location details"
                          >
                            {expandedSkuRows[item.masterSku] ? (
                              <ChevronUp className="w-4 h-4" />
                            ) : (
                              <ChevronDown className="w-4 h-4" />
                            )}
                          </button>

                          {item.imageUrl && (
                            <img
                              src={item.imageUrl}
                              alt={item.productName}
                              referrerPolicy="no-referrer"
                              className="w-11 h-11 rounded-lg object-cover border border-neutral-200 dark:border-neutral-800 shrink-0"
                            />
                          )}
                          <div>
                            <div className="flex items-center gap-1.5 flex-wrap">
                              <span className="font-mono font-bold text-neutral-900 dark:text-white text-xs">
                                {item.masterSku}
                              </span>
                              {(item.warehouseBalances || []).length > 0 && (
                                <span className="text-[9px] px-1.5 py-0.5 rounded bg-neutral-100 dark:bg-neutral-800 text-neutral-500 font-mono whitespace-nowrap">
                                  {inventoryMode === 'shared_pool' ? 'Shared Pool' : `${item.warehouseBalances.length} WH`}
                                </span>
                              )}
                            </div>
                            <p className="font-semibold text-neutral-800 dark:text-neutral-200 text-xs line-clamp-1">
                              {item.dataClassification==='test'?'[TEST] ':item.dataClassification==='operational'?'':'[Unclassified] '}{item.productName}
                            </p>
                            <p className="text-[11px] text-neutral-500">
                              {item.variantName} • RM {item.sellingPrice.toFixed(2)}
                            </p>
                          </div>
                        </div>
                      </td>

                      {/* Warehouse Location */}
                      <td className="py-4 px-4">
                        <div className="flex items-center gap-1.5 text-neutral-700 dark:text-neutral-300 font-mono text-[11px]">
                          <MapPin className="w-3.5 h-3.5 text-neutral-400 shrink-0" />
                          <span>{item.warehouseLocation}</span>
                        </div>
                        <p className="text-[10px] text-neutral-500 mt-0.5">{item.warehouseName}</p>
                      </td>

                      {/* Physical Stock */}
                      <td className="py-4 px-4 text-center">
                        {isUnknown ? (
                          <div className="inline-flex flex-col items-center">
                            <span className="font-bold text-[11px] px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20">
                              Awaiting Audit
                            </span>
                            <span className="text-[10px] text-neutral-400 mt-0.5">Unverified</span>
                          </div>
                        ) : (
                          <div>
                            <span className="font-bold text-sm text-neutral-900 dark:text-white">
                              {item.totalPhysicalStock}
                            </span>
                            <span className="block text-[9px] text-emerald-600 dark:text-emerald-400 font-medium">
                              Verified
                            </span>
                          </div>
                        )}
                      </td>

                      {/* Reserved */}
                      <td className="py-4 px-4 text-center font-bold text-amber-600 dark:text-amber-400">
                        {item.reservedStock}
                      </td>

                      {/* Safety Buffer */}
                      <td className="py-4 px-4 text-center font-mono text-neutral-500">
                        {item.safetyStock ?? 'Unknown'}
                      </td>

                      {/* ATP Available */}
                      <td className="py-4 px-4 text-center">
                        {isUnknown ? (
                          <span className="inline-block text-[11px] font-bold px-2 py-1 rounded-lg bg-neutral-100 dark:bg-neutral-800 text-neutral-500 border border-neutral-200 dark:border-neutral-700">
                            Pending Count
                          </span>
                        ) : (
                          <span
                            className={`inline-block font-black text-sm px-2.5 py-1 rounded-lg ${
                              isOOS
                                ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-200 dark:border-rose-900'
                                : isLow
                                ? 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200 dark:border-amber-900'
                                : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-900'
                            }`}
                          >
                            {item.availableStock ?? 'Unknown'}
                          </span>
                        )}
                      </td>

                      {/* Marketplace Stores & Link Status (Multi-store associations) */}
                      <td className="py-4 px-4">
                        <div className="flex flex-wrap items-center gap-1.5">
                          {associations.length === 0 ? (
                            <span className="text-[11px] text-neutral-400 italic">No stores associated</span>
                          ) : (
                            associations.map(assoc => {
                              const isEnabled = assoc.syncState === 'enabled' && assoc.connectionState !== 'expired';
                              const isExpired = assoc.connectionState === 'expired' || assoc.syncState === 'auth_expired';

                              return (
                                <button
                                  key={assoc.connectionId}
                                  onClick={() => setBroadcastModalItem(item)}
                                  className={`px-2 py-1 rounded-lg text-[10px] font-bold border transition-all cursor-pointer flex items-center gap-1.5 ${
                                    isEnabled
                                      ? 'bg-white dark:bg-neutral-800/90 text-neutral-800 dark:text-neutral-200 border-neutral-300 dark:border-neutral-700 shadow-xs hover:border-neutral-500'
                                      : isExpired
                                      ? 'bg-rose-50/70 dark:bg-rose-950/30 text-rose-700 dark:text-rose-300 border-rose-300 dark:border-rose-800'
                                      : 'bg-neutral-50 dark:bg-neutral-900/60 text-neutral-400 border-dashed border-neutral-200 dark:border-neutral-800 opacity-60'
                                  }`}
                                  title={`${assoc.storeName} (${assoc.platform.toUpperCase()}): ${
                                    isExpired
                                      ? 'OAuth Expired'
                                      : isEnabled
                                      ? `Sync Active • Pushed: ${assoc.lastConfirmedPushedQuantity ?? 'None'}`
                                      : 'Sync Inactive'
                                  }`}
                                >
                                  <span className={`shrink-0 ${isEnabled ? '' : 'grayscale opacity-75'}`}>
                                    <MarketplaceLogo platform={assoc.platform} size={15} />
                                  </span>
                                  <span className="font-semibold text-[10px]">
                                    {assoc.storeNickname || assoc.storeName}
                                  </span>
                                  <span
                                    className={`w-1.5 h-1.5 rounded-full ${
                                      isEnabled
                                        ? 'bg-emerald-500 shadow-xs ring-1 ring-emerald-400/40'
                                        : isExpired
                                        ? 'bg-rose-500'
                                        : 'bg-neutral-300 dark:bg-neutral-700'
                                    }`}
                                  />
                                </button>
                              );
                            })
                          )}
                        </div>

                        <p className="text-[10px] text-neutral-400 mt-1.5 flex items-center gap-1">
                          <span>Last sync:</span>
                          <span className="font-mono text-neutral-500 dark:text-neutral-400">
                            {item.lastBroadcastAt && !isNaN(new Date(item.lastBroadcastAt).getTime())
                              ? new Date(item.lastBroadcastAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                              : 'Pending Broadcast'}
                          </span>
                        </p>
                      </td>

                      {/* Operations */}
                      <td className="py-4 px-4 text-right">
                        <div className="inline-flex items-center gap-1.5 justify-end">
                          {/* Broadcast single SKU to stores */}
                          <button
                            onClick={() => setBroadcastModalItem(item)}
                            className="p-1.5 rounded-lg bg-neutral-100 dark:bg-neutral-800 hover:bg-neutral-200 dark:hover:bg-neutral-700 text-neutral-700 dark:text-neutral-300 border border-neutral-200 dark:border-neutral-700 transition-colors cursor-pointer"
                            title="Open Store Broadcast Controller"
                          >
                            <RefreshCw className="w-4 h-4" />
                          </button>

                          {/* Adjust / Audit Stock (Authenticated staff identity flow) */}
                          <button
                            onClick={() => setAdjustModalItem(item)}
                            className={`p-1.5 rounded-lg transition-colors cursor-pointer border ${
                              isUnknown
                                ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/30 hover:bg-amber-500/20'
                                : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 border-neutral-200 dark:border-neutral-700 hover:bg-neutral-200 dark:hover:bg-neutral-700'
                            }`}
                            title={isUnknown ? 'Record Physical Stock Audit' : 'Adjust Stock Delta'}
                          >
                            {isUnknown ? <ClipboardCheck className="w-4 h-4" /> : <Plus className="w-4 h-4" />}
                          </button>

                          {/* Map Channels */}
                          <button
                            onClick={() => setSelectedMappingItem(item)}
                            className="p-1.5 rounded-lg bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-200 dark:hover:bg-neutral-700 transition-colors cursor-pointer"
                            title="Configure Channel SKU Mapping"
                          >
                            <Link2 className="w-4 h-4" />
                          </button>

                          {/* View Audit Ledger */}
                          <button
                            onClick={() => setSelectedAuditItem(item)}
                            className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors cursor-pointer"
                            title="View Stock Ledger & Audit Trail"
                          >
                            <History className="w-4 h-4" />
                          </button>
                        </div>
                      </td>
                    </tr>

                    {/* Warehouse Balances Sub-Table (Expanded SKU Row) */}
                    {expandedSkuRows[item.masterSku] && (
                      <tr className="bg-neutral-50/70 dark:bg-neutral-950/70 border-b border-neutral-200 dark:border-neutral-800">
                        <td colSpan={8} className="py-3 px-6 pl-14">
                          <div className="rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 overflow-hidden shadow-xs">
                            <div className="px-4 py-2.5 bg-neutral-100/70 dark:bg-neutral-800/60 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
                              <div className="flex items-center gap-2">
                                <Warehouse className="w-3.5 h-3.5 text-purple-600 dark:text-purple-400" />
                                <span className="font-bold text-[11px] text-neutral-800 dark:text-neutral-200">
                                  {inventoryMode==='shared_pool'?'Shared Inventory Pool':`Physical Warehouse Inventory & ATP Allocation (${(item.warehouseBalances || []).length} Warehouses)`}
                                </span>
                                <span className="text-[10px] text-neutral-400">
                                  {inventoryMode === 'independent_warehouses'
                                    ? '• Independent stock accounting & reservation per warehouse'
                                    : inventoryMode === 'shared_pool' ? '• Multi-warehouse bin locations (Active Shared Pool)' : '• Not Configured (Awaiting Selection)'}
                                </span>
                              </div>

                              <button
                                onClick={() => {
                                  setAdjustModalTargetWarehouseId(undefined);
                                  setAdjustModalItem(item);
                                }}
                                className="text-[10px] font-bold text-purple-600 dark:text-purple-400 hover:underline flex items-center gap-1 cursor-pointer"
                              >
                                <Plus className="w-3 h-3" />
                                <span>Audit / Adjust</span>
                              </button>
                            </div>

                            {(!item.warehouseBalances || item.warehouseBalances.length === 0) ? (
                              <div className="p-4 text-center text-neutral-400 text-xs italic">
                                No warehouse balance records. Click Audit / Adjust to record initial inventory.
                              </div>
                            ) : (
                              <table className="w-full text-left text-xs">
                                <thead className="bg-neutral-50/50 dark:bg-neutral-950/40 text-neutral-400 text-[9px] uppercase tracking-wider border-b border-neutral-200 dark:border-neutral-800">
                                  <tr>
                                    <th className="py-2 px-4">Warehouse</th>
                                    <th className="py-2 px-4">Bin Location</th>
                                    <th className="py-2 px-4 text-center">Physical Stock</th>
                                    <th className="py-2 px-4 text-center">Reserved</th>
                                    <th className="py-2 px-4 text-center">Safety Stock</th>
                                    <th className="py-2 px-4 text-center">{item.warehouseBalances.some(w=>w.basicWms)?'Basic Available (excludes unallocated orders)':'Warehouse ATP'}</th>
                                    <th className="py-2 px-4">Last Audit</th>
                                    <th className="py-2 px-4 text-right">Actions</th>
                                  </tr>
                                </thead>
                                <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800/60">
                                  {item.warehouseBalances.map(wb => (
                                    <tr key={wb.warehouseId} className="hover:bg-neutral-50/50 dark:hover:bg-neutral-800/30">
                                      <td className="py-2.5 px-4">
                                        <div className="flex items-center gap-2">
                                          <span className="font-mono font-bold text-neutral-900 dark:text-white text-[11px]">{wb.warehouseCode}</span>
                                          <span className="text-neutral-600 dark:text-neutral-300 font-medium text-[11px]">{wb.warehouseName}</span>
                                        </div>
                                      </td>
                                      <td className="py-2.5 px-4 font-mono text-[11px] text-neutral-600 dark:text-neutral-400">
                                        {wb.locationBin || '—'}
                                      </td>
                                      <td className="py-2.5 px-4 text-center font-bold font-mono text-neutral-900 dark:text-white">
                                        {wb.physicalStock == null ? 'Pending Count' : `${wb.physicalStock} units`}
                                      </td>
                                      <td className="py-2.5 px-4 text-center font-mono text-amber-600 dark:text-amber-400">
                                        {(wb.basicWms ? wb.basicOwnedReserved : wb.reservedStock) ?? 'Unknown'}
                                      </td>
                                      <td className="py-2.5 px-4 text-center font-mono text-neutral-500">
                                        {wb.safetyStock ?? 'Unknown'}
                                      </td>
                                      <td className="py-2.5 px-4 text-center">
                                        <span className="font-mono font-black text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20 text-[11px]">
                                          {(wb.basicWms ? wb.basicAvailable : wb.availableAtp) ?? 'Unknown'}
                                        </span>
                                      </td>
                                      <td className="py-2.5 px-4 text-[10px] text-neutral-400">
                                        {wb.lastAuditAt ? (
                                          <span>{new Date(wb.lastAuditAt).toLocaleDateString()} ({wb.lastAuditBy || 'Staff'})</span>
                                        ) : (
                                          <span className="italic">Not yet audited</span>
                                        )}
                                      </td>
                                      <td className="py-2.5 px-4 text-right">
                                        <div className="flex items-center justify-end gap-1.5">
                                          <button
                                            type="button"
                                            onClick={() => {
                                              setReconcileTarget({ item, balance: wb });
                                            }}
                                            className="px-2 py-1 rounded bg-purple-500/10 hover:bg-purple-500/20 text-purple-700 dark:text-purple-300 text-[10px] font-bold cursor-pointer transition-colors flex items-center gap-1"
                                            title="Server-Contract Inventory Ledger Reconciliation"
                                          >
                                            <RefreshCw className="w-3 h-3" />
                                            <span>Reconcile</span>
                                          </button>
                                          <button
                                            type="button"
                                            onClick={() => {
                                              setAdjustModalTargetWarehouseId(wb.warehouseId);
                                              setAdjustModalItem(item);
                                            }}
                                            className="px-2 py-1 rounded bg-neutral-100 dark:bg-neutral-800 hover:bg-neutral-200 dark:hover:bg-neutral-700 text-neutral-700 dark:text-neutral-300 text-[10px] font-bold cursor-pointer transition-colors"
                                          >
                                            Audit / Adjust
                                          </button>
                                        </div>
                                        {wb.balanceId && wb.physicalStockStatus==='verified' && <OrderActivationControl balanceId={wb.balanceId} sku={item.masterSku} channels={storeFulfillmentBindings.filter(binding=>inventoryMode==='shared_pool'||binding.boundWarehouseId===wb.warehouseId).map(binding=>({id:binding.id,name:binding.storeName}))}/>}
                                      </td>
                                    </tr>
                                  ))}
                                </tbody>
                              </table>
                            )}
                          </div>
                        </td>
                      </tr>
                    )}
                  </React.Fragment>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  )}

      {/* TAB 2: MODE & WAREHOUSES CONFIG */}
      {activeTab === 'mode_warehouses' && (
        <ModeAndWarehouseConfigTab
          currentMode={inventoryMode}
          entityWarehouses={entityWarehouses}
          storeFulfillmentBindings={storeFulfillmentBindings}
          inventoryItems={safeMpInv}
          auditLogs={inventoryModeSwitchAudits}
          onOpenSwitchModal={(target) => {
            setModeSwitchInitialTarget(target);
            setIsModeSwitchModalOpen(true);
          }}
          onResetToUnconfigured={() => setInventoryModeDirect('unconfigured')}
          onOpenAddWarehouseModal={() => {
            setEditingWarehouse(null);
            setIsWarehouseModalOpen(true);
          }}
          onOpenEditWarehouseModal={(wh) => {
            setEditingWarehouse(wh);
            setIsWarehouseModalOpen(true);
          }}
          onDeleteWarehouse={deleteEntityWarehouse}
        />
      )}

      {/* TAB 3: STORE FULFILLMENT BINDINGS */}
      {activeTab === 'store_bindings' && (
        <StoreWarehouseBindingTab
          bindings={storeFulfillmentBindings}
          warehouses={entityWarehouses}
          onOpenEditBindingModal={(binding) => {
            setEditingBinding(binding);
          }}
        />
      )}

      {/* TAB 4: INTER-WAREHOUSE TRANSFERS */}
      {activeTab === 'transfers' && (
        <WarehouseTransferTab
          transfers={safeTransfers}
          warehouses={entityWarehouses}
          staffUsers={staffUsers}
          currentUserName={user?.name}
          onOpenCreateTransferModal={async() => {
            try {
              const options=await prepareWarehouseTransfer();
              setTransferInventory(safeMpInv.filter(item=>options.some(row=>String(row.variant_id)===item.id)).map(item=>({...item,
                warehouseBalances:options.filter(row=>String(row.variant_id)===item.id).map(row=>({
                  balanceId:row.balance_id,poolId:row.pool_id,warehouseId:String(row.warehouse_id),warehouseCode:row.warehouse_code,
                  warehouseName:row.warehouse_name,physicalStock:row.physical_stock,reservedStock:row.reserved_stock,
                  safetyStock:row.safety_stock,availableAtp:row.atp,physicalStockStatus:row.verified?'verified':'unknown',version:row.version
                }))})));
              setIsCreateTransferModalOpen(true);
            } catch(error) {addToast({type:'error',title:'Transfer unavailable',message:(error as Error).message});}
          }}
          onUpdateTransferStatus={updateTransferStatus}
        />
      )}

      {/* Stock Adjustment & Audit Modal (Authenticated Staff Identity + 2-step Review) */}
      {adjustModalItem && (
        <InventoryAdjustmentModal
          item={adjustModalItem}
          isOpen={Boolean(adjustModalItem)}
          targetWarehouseId={adjustModalTargetWarehouseId}
          inventoryMode={inventoryMode}
          warehouses={entityWarehouses}
          onClose={() => {
            const variantId=adjustModalItem.id;
            const confirmed=countConfirmed.current;
            countConfirmed.current=false;
            reloadInventory?.();
            readWarehouseBalances(Number(variantId))
              .then(balances=>setLoadedBalances(previous=>({...previous,[variantId]:balances})))
              .catch(error=>addToast({type:'error',title:'Reload warehouse balances',message:error.message}));
            setAdjustModalItem(null);
            setAdjustModalTargetWarehouseId(undefined);
            if(confirmed){
              const remaining=intakeQueue.filter(id=>id!==variantId);
              setIntakeQueue(remaining);
              if(remaining.length)setTimeout(()=>setAdjustModalItem(safeMpInv.find(item=>item.id===remaining[0])??null),0);
              else setIntakeActive(false);
            }else if(intakeActive)setIntakeActive(false);
          }}
          onConfirmed={()=>{countConfirmed.current=true;}}
          staffUsers={staffUsers}
          currentUserName={user?.name}
          onApplyAdjustment={adjustStock}
          onRecordAuditCount={recordPhysicalCountAudit}
        />
      )}

      {/* Store Broadcast Modal (Multi-Store Release 1 Controller) */}
      {broadcastModalItem && (
        <InventoryStoreBroadcastModal
          item={broadcastModalItem}
          isOpen={Boolean(broadcastModalItem)}
          onClose={() => setBroadcastModalItem(null)}
          storeActivations={safeActivations}
          onBroadcastToStore={broadcastStockToStore}
          onBroadcastToAllStores={broadcastStockToAllStores}
          onOpenAuditModal={itemToAudit => setAdjustModalItem(itemToAudit)}
        />
      )}

      {/* Store Activations Drawer */}
      {isStoreActivationsOpen && (
        <StoreActivationsDrawer
          isOpen={isStoreActivationsOpen}
          onClose={() => setIsStoreActivationsOpen(false)}
          storeActivations={safeActivations}
          onSaveActivation={saveStoreActivation}
        />
      )}

      {/* Inventory Order Exceptions Drawer */}
      {isExceptionsDrawerOpen && (
        <InventoryOrderExceptionsDrawer
          isOpen={isExceptionsDrawerOpen}
          onClose={() => setIsExceptionsDrawerOpen(false)}
          exceptions={safeExceptions}
        />
      )}

      {/* Channel Mapping Modal */}
      {selectedMappingItem && (
        <ChannelMappingModal
          item={selectedMappingItem}
          isOpen={Boolean(selectedMappingItem)}
          onClose={() => setSelectedMappingItem(null)}
          onSave={(id, platform, mapping) => linkChannelSku(id, platform, mapping)}
        />
      )}

      {/* Mode Switch Modal */}
      {isModeSwitchModalOpen && (
        <ModeSwitchModal
          isOpen={isModeSwitchModalOpen}
          onClose={() => {
            setIsModeSwitchModalOpen(false);
            setModeSwitchInitialTarget(undefined);
          }}
          currentMode={inventoryMode}
          initialTargetMode={modeSwitchInitialTarget}
          staffUsers={staffUsers}
          currentUserName={user?.name}
          skuCount={safeMpInv.length}
          entityWarehouses={entityWarehouses}
          inventoryItems={safeMpInv}
          checkReadiness={checkModeSwitchReadiness}
          onConfirmSwitch={(newMode, reason, operator, strategy, explicitAllocations) => {
            return setInventoryModeWithAudit(newMode, operator, reason, strategy, explicitAllocations);
          }}
        />
      )}

      {/* Warehouse Inventory Reconcile Modal (Server Contract) */}
      {reconcileTarget && (
        <ConnectedReconciliationModal
          onClose={() => setReconcileTarget(null)}
          item={reconcileTarget.item}
          identity={{balanceId:reconcileTarget.balance.balanceId!,poolId:reconcileTarget.balance.poolId!,poolKind:reconcileTarget.balance.poolKind,
            warehouseId:reconcileTarget.balance.warehouseId,warehouseCode:reconcileTarget.balance.warehouseCode,
            warehouseName:reconcileTarget.balance.warehouseName}}
          storeBindings={storeFulfillmentBindings}
          exceptions={safeExceptions}
          onError={message=>{setReconcileTarget(null);addToast({type:'error',title:'Reconciliation unavailable',message});}}
          onCommitted={()=>{
            readWarehouseBalances(Number(reconcileTarget.item.id))
              .then(balances=>setLoadedBalances(previous=>({...previous,[reconcileTarget.item.id]:balances})))
              .catch(error=>addToast({type:'error',title:'Reload warehouse balances',message:error.message}));
            reloadInventory?.();
          }}
          onOpenAuditModal={(itm, whId) => {
            setAdjustModalTargetWarehouseId(whId);
            setAdjustModalItem(itm);
          }}
        />
      )}

      {/* Entity Warehouse Modal */}
      {isWarehouseModalOpen && (
        <EntityWarehouseModal
          isOpen={isWarehouseModalOpen}
          onClose={() => {
            setIsWarehouseModalOpen(false);
            setEditingWarehouse(null);
          }}
          warehouse={editingWarehouse}
          onSave={saveEntityWarehouse}
        />
      )}

      {/* Store Fulfillment Binding Modal */}
      {editingBinding && (
        <StoreFulfillmentBindingModal
          isOpen={Boolean(editingBinding)}
          onClose={() => setEditingBinding(null)}
          binding={editingBinding}
          warehouses={entityWarehouses}
          onSave={saveStoreFulfillmentBinding}
        />
      )}

      {/* Create Warehouse Transfer Modal */}
      {isCreateTransferModalOpen && (
        <CreateWarehouseTransferModal
          isOpen={isCreateTransferModalOpen}
          onClose={() => setIsCreateTransferModalOpen(false)}
          warehouses={entityWarehouses}
          inventory={transferInventory}
          staffUsers={staffUsers}
          currentUserName={user?.name}
          onCreateTransfer={createWarehouseTransfer}
        />
      )}

      {/* Stock Ledger History Drawer */}
      {selectedAuditItem && (
        <div className="fixed inset-0 z-50 flex justify-end bg-black/60 backdrop-blur-xs">
          <div className="w-full max-w-md bg-white dark:bg-neutral-900 h-full shadow-2xl flex flex-col border-l border-neutral-200 dark:border-neutral-800 overflow-hidden animate-in slide-in-from-right duration-200">
            <div className="p-6 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between bg-neutral-50 dark:bg-neutral-950/50">
              <div>
                <h3 className="font-bold text-sm text-neutral-900 dark:text-white">
                  Stock Ledger & Audit Trail
                </h3>
                <p className="text-xs text-neutral-500 font-mono">
                  {selectedAuditItem.masterSku} • {selectedAuditItem.productName}
                </p>
              </div>
              <button
                onClick={() => setSelectedAuditItem(null)}
                className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-800 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 cursor-pointer"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="p-6 overflow-y-auto space-y-4 text-xs flex-1">
              <div className="space-y-3">
                {historyMessage}
                {physicalHistory.map((log, idx) => (
                  <div key={idx} className="p-3.5 bg-neutral-50 dark:bg-neutral-950 rounded-xl border border-neutral-200 dark:border-neutral-800 space-y-1.5">
                    <div className="flex items-center justify-between">
                      <span className="font-bold text-neutral-900 dark:text-white">{log.action}</span>
                      <span
                        className={`font-black text-xs px-2 py-0.5 rounded ${
                          log.change >= 0
                            ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20'
                            : 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20'
                        }`}
                      >
                        {log.change === null ? 'Initial count' : log.change >= 0 ? `+${log.change}` : log.change} {log.change === null ? '' : 'units'}
                      </span>
                    </div>
                    <p className="text-[11px] text-neutral-600 dark:text-neutral-400">{log.reason}</p>
                    {'attemptId' in log && typeof log.attemptId==='string' && <button disabled={rechecking} onClick={()=>recheckAttempt(log.attemptId as string)} className="px-3 py-1.5 rounded-lg bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 text-xs font-bold">{rechecking?'Reading platform…':'Recheck platform quantity (no write)'}</button>}
                    <div className="flex items-center justify-between text-[10px] text-neutral-400 pt-1.5 border-t border-neutral-200 dark:border-neutral-800">
                      <span>Operator: <strong>{log.user}</strong></span>
                      <span>{new Date(log.timestamp).toLocaleString()}</span>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
