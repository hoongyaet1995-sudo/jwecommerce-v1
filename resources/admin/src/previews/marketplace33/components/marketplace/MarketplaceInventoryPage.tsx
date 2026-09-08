import React, { useState, useMemo, useEffect } from 'react';
import { useAdmin } from '../../context/AdminContext';
import { MarketplaceInventoryItem, MarketplacePlatform, ChannelSkuMapping } from '../../types';
import { ChannelMappingModal } from './ChannelMappingModal';
import { MarketplaceLogo } from './MarketplaceLogo';
import { SubNavHeader } from '../common/SubNavHeader';
import { PhysicalCountModal as InventoryAdjustmentModal } from '../../../marketplace34/PhysicalCountModal';
import { InventoryStoreBroadcastModal } from './InventoryStoreBroadcastModal';
import { StoreActivationsDrawer } from './StoreActivationsDrawer';
import { InventoryOrderExceptionsDrawer } from './InventoryOrderExceptionsDrawer';
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
  ClipboardCheck
} from 'lucide-react';

export const MarketplaceInventoryPage: React.FC = () => {
  const {
    marketplaceInventory = [],
    inventoryLoaded = false,
    marketplaceOrders = [],
    marketplaceStoreProducts = [],
    connections = [],
    connectionsStatus = 'Loading',
    syncLogs = [],
    storeActivations = [],
    inventoryOrderExceptions = [],
    staffUsers = [],
    user,
    loadInventoryHistory,
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

  const safeMpInv = marketplaceInventory || [];
  const safeMpOrders = marketplaceOrders || [];
  const safeStoreProds = marketplaceStoreProducts || [];
  const safeConns = connections || [];
  const safeLogs = syncLogs || [];
  const safeActivations = storeActivations || [];
  const safeExceptions = inventoryOrderExceptions || [];

  const [searchQuery, setSearchQuery] = useState('');
  const [filterStockStatus, setFilterStockStatus] = useState<'all' | 'healthy' | 'low_stock' | 'oos' | 'awaiting_audit'>('all');
  const [filterPlatform, setFilterPlatform] = useState<'all' | 'shopee' | 'lazada'>('all');

  // Modals & Drawers
  const [selectedMappingItem, setSelectedMappingItem] = useState<MarketplaceInventoryItem | null>(null);
  const [selectedAuditItem, setSelectedAuditItem] = useState<MarketplaceInventoryItem | null>(null);
  const [ledgerStatus, setLedgerStatus] = useState('');
  useEffect(() => {
    if (!selectedAuditItem || !loadInventoryHistory) return;
    const id = selectedAuditItem.id;
    const abort = new AbortController();
    setLedgerStatus('Loading available-stock movements…');
    loadInventoryHistory(id, abort.signal).then((historyLog: MarketplaceInventoryItem['historyLog']) => {
      if (!abort.signal.aborted) {
        setSelectedAuditItem(current => current?.id === id ? {...current, historyLog} : current);
        setLedgerStatus(historyLog.length ? `${historyLog.length} available-stock movements loaded` : 'No recorded available-stock movements. This does not verify physical stock.');
      }
    }).catch((error: Error) => {
      if (!abort.signal.aborted) {
        setLedgerStatus('Ledger unavailable. Close and reopen to retry.');
        addToast({type:'error', title:'Ledger unavailable', message:error.message});
      }
    });
    return () => abort.abort();
  }, [selectedAuditItem?.id, loadInventoryHistory]);
  const [adjustModalItem, setAdjustModalItem] = useState<MarketplaceInventoryItem | null>(null);
  const [broadcastModalItem, setBroadcastModalItem] = useState<MarketplaceInventoryItem | null>(null);
  const [isStoreActivationsOpen, setIsStoreActivationsOpen] = useState<boolean>(false);
  const [isExceptionsDrawerOpen, setIsExceptionsDrawerOpen] = useState<boolean>(false);

  // Filter items
  const filteredInventory = useMemo(() => {
    return safeMpInv.filter(item => {
      const isUnknown = item.physicalStockStatus === 'unknown' || item.totalPhysicalStock === null;
      const atp = item.availableStock ?? 0;

      // Stock status filter
      if (filterStockStatus === 'awaiting_audit' && !isUnknown) return false;
      if (filterStockStatus === 'low_stock' && (isUnknown || atp > item.safetyStock)) return false;
      if (filterStockStatus === 'oos' && (isUnknown || atp > 0)) return false;
      if (filterStockStatus === 'healthy' && (isUnknown || atp <= item.safetyStock)) return false;

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
  }, [safeMpInv, filterStockStatus, filterPlatform, searchQuery]);

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
      i.safetyStock,
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
  const totalAvailable = verifiedItems.reduce((s, i) => s + (i.availableStock || 0), 0);
  const lowStockCount = verifiedItems.filter(i => i.availableStock !== null && i.availableStock <= i.safetyStock).length;

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
          { id: 'marketplace-auth', label: 'Connect Store', badge: connectionsStatus === 'Loaded' ? `${connectedCount}/${safeConns.length} Active` : connectionsStatus, badgeVariant: 'success' },
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

      {/* Metrics Row */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {/* Total Physical Stock */}
        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between text-neutral-500 mb-2">
            <span className="text-xs font-bold uppercase tracking-wider">Total Physical Stock</span>
            <Warehouse className="w-4 h-4 text-neutral-700 dark:text-neutral-300" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-black text-neutral-900 dark:text-white">{!inventoryLoaded || unverifiedCount ? 'Pending Count' : totalPhysical}</span>
            <span className="text-xs text-neutral-400">Verified Units</span>
          </div>
          <div className="mt-3 flex items-center justify-between text-[11px] text-neutral-500 font-medium">
            <span>Warehouse assignment requires verification</span>
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
            <span className="text-2xl font-black text-amber-600 dark:text-amber-400">{inventoryLoaded ? totalReserved : 'Loading'}</span>
            <span className="text-xs text-neutral-400">Recorded Marketplace reservations</span>
          </div>
          <div className="mt-3 flex items-center gap-1.5 text-[11px] text-amber-600 dark:text-amber-400 font-medium">
            <CheckCircle2 className="w-3.5 h-3.5" />
            <span>Order coverage requires reconciliation</span>
          </div>
        </div>

        {/* Available-To-Promise (ATP) */}
        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between text-neutral-500 mb-2">
            <span className="text-xs font-bold uppercase tracking-wider">Available-To-Promise</span>
            <Sparkles className="w-4 h-4 text-emerald-500" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-black text-emerald-600 dark:text-emerald-400">{!inventoryLoaded || unverifiedCount ? 'Pending Count' : totalAvailable}</span>
            <span className="text-xs text-neutral-400">Physical and safety counts required</span>
          </div>
          <div className="mt-3 flex items-center gap-1.5 text-[11px] text-emerald-600 dark:text-emerald-400 font-medium">
            <span>Stock synchronization is not enabled</span>
          </div>
        </div>

        {/* Low Stock Warnings */}
        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between text-neutral-500 mb-2">
            <span className="text-xs font-bold uppercase tracking-wider">Low Stock Warnings</span>
            <AlertTriangle className="w-4 h-4 text-rose-500" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-black text-rose-600 dark:text-rose-400">{!inventoryLoaded || unverifiedCount ? 'Pending Count' : lowStockCount}</span>
            <span className="text-xs text-neutral-400">SKUs below safety threshold</span>
          </div>
          <div className="mt-3 flex items-center gap-1.5 text-[11px] text-neutral-500 font-medium">
            <span>Safety thresholds require verification</span>
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
                <th className="py-3.5 px-4 text-center">ATP Available</th>
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
                  const isLow = !isUnknown && item.availableStock !== null && item.availableStock <= item.safetyStock;
                  const isOOS = !isUnknown && (item.availableStock === null || item.availableStock <= 0);
                  const associations = item.storeAssociations || [];

                  return (
                    <tr key={item.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/40 transition-colors">
                      {/* SKU & Product */}
                      <td className="py-4 px-4">
                        <div className="flex items-center gap-3">
                          {item.imageUrl && (
                            <img
                              src={item.imageUrl}
                              alt={item.productName}
                              referrerPolicy="no-referrer"
                              className="w-11 h-11 rounded-lg object-cover border border-neutral-200 dark:border-neutral-800 shrink-0"
                            />
                          )}
                          <div>
                            <span className="font-mono font-bold text-neutral-900 dark:text-white text-xs">
                              {item.masterSku}
                            </span>
                            <p className="font-semibold text-neutral-800 dark:text-neutral-200 text-xs line-clamp-1">
                              {item.productName}
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
                        {item.safetyStock ?? 'Not verified'}
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
                            {item.availableStock}
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
                  );
                })
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Stock Adjustment & Audit Modal (Authenticated Staff Identity + 2-step Review) */}
      <InventoryAdjustmentModal
        item={adjustModalItem}
        isOpen={Boolean(adjustModalItem)}
        onClose={() => setAdjustModalItem(null)}
        staffUsers={staffUsers}
        currentUserName={user?.name}
        onApplyAdjustment={adjustStock}
        onRecordAuditCount={recordPhysicalCountAudit}
      />

      {/* Store Broadcast Modal (Multi-Store Release 1 Controller) */}
      <InventoryStoreBroadcastModal
        item={broadcastModalItem}
        isOpen={Boolean(broadcastModalItem)}
        onClose={() => setBroadcastModalItem(null)}
        storeActivations={safeActivations}
        onBroadcastToStore={broadcastStockToStore}
        onBroadcastToAllStores={broadcastStockToAllStores}
        onOpenAuditModal={itemToAudit => setAdjustModalItem(itemToAudit)}
      />

      {/* Store Activations Drawer */}
      <StoreActivationsDrawer
        isOpen={isStoreActivationsOpen}
        onClose={() => setIsStoreActivationsOpen(false)}
        storeActivations={safeActivations}
        onSaveActivation={saveStoreActivation}
      />

      {/* Inventory Order Exceptions Drawer */}
      <InventoryOrderExceptionsDrawer
        isOpen={isExceptionsDrawerOpen}
        onClose={() => setIsExceptionsDrawerOpen(false)}
        exceptions={safeExceptions}
      />

      {/* Channel Mapping Modal */}
      <ChannelMappingModal
        item={selectedMappingItem}
        isOpen={Boolean(selectedMappingItem)}
        onClose={() => setSelectedMappingItem(null)}
        onSave={(id, platform, mapping) => linkChannelSku(id, platform, mapping)}
      />

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
                  {selectedAuditItem.masterSku} • {selectedAuditItem.productName} • {ledgerStatus}
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
                {(selectedAuditItem.historyLog || []).map((log, idx) => (
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
                        {log.change === null ? 'Initial count' : `${log.change >= 0 ? `+${log.change}` : log.change} units`}
                      </span>
                    </div>
                    <p className="text-[11px] text-neutral-600 dark:text-neutral-400">{log.reason}</p>
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
