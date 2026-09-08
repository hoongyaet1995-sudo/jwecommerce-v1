import React, { useState, useMemo } from 'react';
import { useAdmin } from '../../context/AdminContext';
import { MarketplaceInventoryItem, MarketplacePlatform, ChannelSkuMapping } from '../../types';
import { ChannelMappingModal } from './ChannelMappingModal';
import { SubNavHeader } from '../common/SubNavHeader';
import { Warehouse, Package, RefreshCw, Search, Link2, AlertTriangle, CheckCircle2, Plus, Minus, History, Sparkles, Boxes, MapPin, X, Download } from 'lucide-react';

export const MarketplaceInventoryPage: React.FC = () => {
  const {
    marketplaceInventory = [],
    marketplaceOrders = [],
    marketplaceStoreProducts = [],
    connections = [],
    syncLogs = [],
    saveInventoryItem,
    adjustStock,
    broadcastStockToChannels,
    broadcastAllStockToChannels,
    linkChannelSku,
    unlinkChannelSku,
    addToast
  } = useAdmin();

  const safeMpInv = marketplaceInventory || [];
  const safeMpOrders = marketplaceOrders || [];
  const safeStoreProds = marketplaceStoreProducts || [];
  const safeConns = connections || [];
  const safeLogs = syncLogs || [];

  const [searchQuery, setSearchQuery] = useState('');
  const [filterStockStatus, setFilterStockStatus] = useState<'all' | 'low_stock' | 'healthy' | 'oos'>('all');
  const [selectedMappingItem, setSelectedMappingItem] = useState<MarketplaceInventoryItem | null>(null);
  const [selectedAuditItem, setSelectedAuditItem] = useState<MarketplaceInventoryItem | null>(null);

  // Stock Adjustment Modal
  const [adjustModalItem, setAdjustModalItem] = useState<MarketplaceInventoryItem | null>(null);
  const [adjustDelta, setAdjustDelta] = useState<number>(10);
  const [adjustReason, setAdjustReason] = useState('Supplier Stock Inbound (PO Receipt)');
  const [adjustUser, setAdjustUser] = useState('Warehouse Operator');

  // Filter items
  const filteredInventory = useMemo(() => {
    return safeMpInv.filter(item => {
      // Stock status filter
      if (filterStockStatus === 'low_stock' && item.availableStock > item.safetyStock) return false;
      if (filterStockStatus === 'oos' && item.availableStock > 0) return false;
      if (filterStockStatus === 'healthy' && item.availableStock <= item.safetyStock) return false;

      // Search
      if (searchQuery.trim()) {
        const q = searchQuery.toLowerCase();
        const matchesSku = item.masterSku.toLowerCase().includes(q);
        const matchesName = item.productName.toLowerCase().includes(q);
        const matchesVariant = item.variantName.toLowerCase().includes(q);
        const matchesBin = item.warehouseLocation.toLowerCase().includes(q);
        const mappings = Object.values(item.channelMappings) as (ChannelSkuMapping | undefined)[];
        const matchesChannelSku = mappings.some(m => 
          (m?.channelSku && m.channelSku.toLowerCase().includes(q)) || 
          (m?.channelItemId && m.channelItemId.toLowerCase().includes(q)) ||
          (m?.channelSellerSku && m.channelSellerSku.toLowerCase().includes(q))
        );
        if (!matchesSku && !matchesName && !matchesVariant && !matchesBin && !matchesChannelSku) {
          return false;
        }
      }
      return true;
    });
  }, [marketplaceInventory, filterStockStatus, searchQuery]);

  const handleApplyAdjustment = (e: React.FormEvent) => {
    e.preventDefault();
    if (!adjustModalItem) return;
    adjustStock(adjustModalItem.id, adjustDelta, adjustReason, adjustUser);
    setAdjustModalItem(null);
  };

  const handleExportInventoryCSV = () => {
    const headers = ['Master SKU', 'Product Name', 'Variant', 'Warehouse Bin', 'Physical Stock', 'Reserved', 'Safety Stock', 'ATP Available', 'Base Price'];
    const rows = filteredInventory.map(i => [
      i.masterSku,
      `"${i.productName}"`,
      `"${i.variantName}"`,
      i.warehouseLocation,
      i.totalPhysicalStock,
      i.reservedStock,
      i.safetyStock,
      i.availableStock,
      i.sellingPrice
    ]);
    const csvContent = 'data:text/csv;charset=utf-8,' + [headers.join(','), ...rows.map(e => e.join(','))].join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `marketplace_inventory_matrix_${Date.now()}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    addToast({ type: 'success', title: 'Inventory Exported', message: `Exported ${filteredInventory.length} SKUs.` });
  };

  const platforms: { key: MarketplacePlatform; label: string; tag: string }[] = [
    { key: 'tiktok', label: 'TikTok', tag: 'TTS' },
    { key: 'shopee', label: 'Shopee', tag: 'SHP' },
    { key: 'lazada', label: 'Lazada', tag: 'LAZ' },
    { key: 'shopify', label: 'Shopify', tag: 'SPF' }
  ];

  const totalPhysical = safeMpInv.reduce((s, i) => s + i.totalPhysicalStock, 0);
  const totalReserved = safeMpInv.reduce((s, i) => s + i.reservedStock, 0);
  const totalAvailable = safeMpInv.reduce((s, i) => s + i.availableStock, 0);
  const lowStockCount = safeMpInv.filter(i => i.availableStock <= i.safetyStock).length;

  const connectedCount = safeConns.filter(c => c.status === 'connected').length;
  const pendingOrdersCount = safeMpOrders.filter(o => o.channelStatus === 'AWAITING_COLLECTION' || o.channelStatus === 'UNPAID' || !o.airwayBillGenerated).length;
  const unmappedCount = safeStoreProds.reduce((acc, p) => acc + p.variants.filter(v => v.mappingStatus === 'unmapped').length, 0);

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

      {/* Top Banner & Omnichannel Broadcast Button */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2">
            <h1 className="text-2xl font-black tracking-tight text-neutral-900 dark:text-white">
              Marketplace Inventory & WMS Allocation
            </h1>
            <span className="text-[11px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
              Real-time ATP Engine
            </span>
          </div>
          <p className="text-xs text-neutral-500 mt-1">
            Centrally manage Available-to-Promise (ATP) stock balances across TikTok Shop, Shopee, Lazada, and Shopify.
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-2.5">
          <button
            onClick={broadcastAllStockToChannels}
            className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-md shadow-indigo-500/20 transition-all cursor-pointer"
          >
            <RefreshCw className="w-3.5 h-3.5" />
            Broadcast All Stocks to Marketplaces
          </button>
          <button
            onClick={handleExportInventoryCSV}
            className="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 hover:bg-neutral-50 dark:hover:bg-neutral-800 text-neutral-700 dark:text-neutral-300 text-xs font-semibold transition-colors cursor-pointer"
          >
            <Download className="w-3.5 h-3.5" />
            Export Matrix
          </button>
        </div>
      </div>

      {/* Metrics Row */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between text-neutral-500 mb-2">
            <span className="text-xs font-bold uppercase tracking-wider">Total Physical Units</span>
            <Warehouse className="w-4 h-4 text-indigo-500" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-black text-neutral-900 dark:text-white">{totalPhysical}</span>
            <span className="text-xs text-neutral-400">In Central Warehouse</span>
          </div>
          <div className="mt-3 flex items-center gap-1.5 text-[11px] text-neutral-500 font-medium">
            <span>Cyberjaya Central Hub & North Hub</span>
          </div>
        </div>

        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between text-neutral-500 mb-2">
            <span className="text-xs font-bold uppercase tracking-wider">Reserved / In-Order</span>
            <Boxes className="w-4 h-4 text-amber-500" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-black text-amber-600 dark:text-amber-400">{totalReserved}</span>
            <span className="text-xs text-neutral-400">Locked for OMS Packing</span>
          </div>
          <div className="mt-3 flex items-center gap-1.5 text-[11px] text-amber-600 dark:text-amber-400 font-medium">
            <CheckCircle2 className="w-3.5 h-3.5" />
            <span>Prevents multi-channel overselling</span>
          </div>
        </div>

        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between text-neutral-500 mb-2">
            <span className="text-xs font-bold uppercase tracking-wider">Available-To-Promise</span>
            <Sparkles className="w-4 h-4 text-emerald-500" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-black text-emerald-600 dark:text-emerald-400">{totalAvailable}</span>
            <span className="text-xs text-neutral-400">Live Channel Stock</span>
          </div>
          <div className="mt-3 flex items-center gap-1.5 text-[11px] text-emerald-600 dark:text-emerald-400 font-medium">
            <span>Pushed to TikTok, Shopee, Lazada & Shopify</span>
          </div>
        </div>

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
            <span>Safety buffer prevents stockout penalties</span>
          </div>
        </div>
      </div>

      {/* Filter & Search Bar */}
      <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-2 shadow-xs flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-1">
          {[
            { id: 'all', label: 'All SKUs' },
            { id: 'healthy', label: 'Healthy Stock' },
            { id: 'low_stock', label: 'Low Stock Buffer' },
            { id: 'oos', label: 'Out of Stock' },
          ].map(f => {
            const isActive = filterStockStatus === f.id;
            return (
              <button
                key={f.id}
                onClick={() => setFilterStockStatus(f.id as any)}
                className={`px-3 py-1.5 rounded-xl text-xs font-semibold transition-all cursor-pointer ${
                  isActive
                    ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 shadow-xs'
                    : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'
                }`}
              >
                {f.label}
              </button>
            );
          })}
        </div>

        <div className="relative w-full sm:w-72">
          <Search className="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400" />
          <input
            type="text"
            value={searchQuery}
            onChange={e => setSearchQuery(e.target.value)}
            placeholder="Search SKU, Product, Bin location..."
            className="w-full pl-9 pr-3 py-1.5 text-xs rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 text-neutral-900 dark:text-white"
          />
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
                <th className="py-3.5 px-4">Marketplace Channels & Link Status</th>
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
                  const isLow = item.availableStock <= item.safetyStock;
                  const isOOS = item.availableStock <= 0;

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
                              className="w-11 h-11 rounded-lg object-cover border border-neutral-200 dark:border-neutral-800 flex-shrink-0"
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
                          <MapPin className="w-3.5 h-3.5 text-neutral-400" />
                          <span>{item.warehouseLocation}</span>
                        </div>
                        <p className="text-[10px] text-neutral-500 mt-0.5">{item.warehouseName}</p>
                      </td>

                      {/* Physical Stock */}
                      <td className="py-4 px-4 text-center font-bold text-neutral-900 dark:text-white">
                        {item.totalPhysicalStock}
                      </td>

                      {/* Reserved */}
                      <td className="py-4 px-4 text-center font-bold text-amber-600 dark:text-amber-400">
                        {item.reservedStock}
                      </td>

                      {/* Safety Buffer */}
                      <td className="py-4 px-4 text-center font-mono text-neutral-500">
                        {item.safetyStock}
                      </td>

                      {/* ATP Available */}
                      <td className="py-4 px-4 text-center">
                        <span
                          className={`inline-block font-black text-sm px-2.5 py-1 rounded-lg ${
                            isOOS
                              ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-200'
                              : isLow
                              ? 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200'
                              : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200'
                          }`}
                        >
                          {item.availableStock}
                        </span>
                      </td>

                      {/* Channels & Link Status */}
                      <td className="py-4 px-4">
                        <div className="flex items-center gap-1.5">
                          {platforms.map(p => {
                            const mapping = item.channelMappings[p.key];
                            const isLinked = mapping?.isLinked;
                            return (
                              <button
                                key={p.key}
                                onClick={() => setSelectedMappingItem(item)}
                                className={`px-2 py-1 rounded-md text-[10px] font-bold border transition-all cursor-pointer flex items-center gap-1 ${
                                  isLinked
                                    ? 'bg-neutral-100 dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200 border-neutral-300 dark:border-neutral-700 hover:border-indigo-500'
                                    : 'bg-neutral-50 dark:bg-neutral-900 text-neutral-400 border-dashed border-neutral-200 dark:border-neutral-800 opacity-60'
                                }`}
                                title={`${p.label}: ${isLinked ? `Linked (${mapping?.channelSku})` : 'Not mapped'}`}
                              >
                                <span>{p.tag}</span>
                                {isLinked && <span className="w-1.5 h-1.5 rounded-full bg-emerald-500" />}
                              </button>
                            );
                          })}
                        </div>
                        <p className="text-[10px] text-neutral-400 mt-1">
                          Last sync: {new Date(item.lastBroadcastAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                        </p>
                      </td>

                      {/* Operations */}
                      <td className="py-4 px-4 text-right">
                        <div className="inline-flex items-center gap-1.5 justify-end">
                          {/* Broadcast single SKU */}
                          <button
                            onClick={() => broadcastStockToChannels(item.masterSku)}
                            className="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 border border-indigo-200 dark:border-indigo-800 transition-colors cursor-pointer"
                            title="Push Stock to Channels"
                          >
                            <RefreshCw className="w-4 h-4" />
                          </button>

                          {/* Adjust Stock */}
                          <button
                            onClick={() => {
                              setAdjustModalItem(item);
                              setAdjustDelta(10);
                              setAdjustReason('Stock Inbound Receipt');
                            }}
                            className="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-100 border border-emerald-200 dark:border-emerald-800 transition-colors cursor-pointer"
                            title="Adjust / Inbound Stock"
                          >
                            <Plus className="w-4 h-4" />
                          </button>

                          {/* Map Channels */}
                          <button
                            onClick={() => setSelectedMappingItem(item)}
                            className="p-1.5 rounded-lg bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-200 transition-colors cursor-pointer"
                            title="Configure Channel SKU Mapping"
                          >
                            <Link2 className="w-4 h-4" />
                          </button>

                          {/* View Audit Ledger */}
                          <button
                            onClick={() => setSelectedAuditItem(item)}
                            className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors cursor-pointer"
                            title="View Stock Ledger Logs"
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

      {/* Stock Adjustment Modal */}
      {adjustModalItem && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
          <div className="relative w-full max-w-md bg-white dark:bg-neutral-900 rounded-2xl shadow-2xl border border-neutral-200 dark:border-neutral-800 overflow-hidden">
            <form onSubmit={handleApplyAdjustment}>
              <div className="p-6 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950/50">
                <div className="flex items-center gap-2.5">
                  <div className="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">
                    <Warehouse className="w-5 h-5" />
                  </div>
                  <div>
                    <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                      Physical Stock Adjustment / Inbound
                    </h3>
                    <p className="text-xs text-neutral-500">
                      SKU: {adjustModalItem.masterSku} • Current Physical: {adjustModalItem.totalPhysicalStock}
                    </p>
                  </div>
                </div>
              </div>

              <div className="p-6 space-y-4 text-xs">
                <div>
                  <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                    Stock Change (Delta Units)
                  </label>
                  <div className="flex items-center gap-2">
                    <button
                      type="button"
                      onClick={() => setAdjustDelta(prev => prev - 1)}
                      className="p-2 rounded-lg bg-neutral-100 dark:bg-neutral-800 hover:bg-neutral-200 text-neutral-700 dark:text-neutral-300 cursor-pointer"
                    >
                      <Minus className="w-4 h-4" />
                    </button>
                    <input
                      type="number"
                      value={adjustDelta}
                      onChange={e => setAdjustDelta(parseInt(e.target.value) || 0)}
                      className="w-full text-center font-bold text-base px-3 py-2 rounded-xl bg-white dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 text-neutral-900 dark:text-white"
                    />
                    <button
                      type="button"
                      onClick={() => setAdjustDelta(prev => prev + 1)}
                      className="p-2 rounded-lg bg-neutral-100 dark:bg-neutral-800 hover:bg-neutral-200 text-neutral-700 dark:text-neutral-300 cursor-pointer"
                    >
                      <Plus className="w-4 h-4" />
                    </button>
                  </div>
                  <p className="text-[11px] text-neutral-500 mt-1">
                    New Physical Stock: <strong className="text-neutral-900 dark:text-white">{Math.max(0, adjustModalItem.totalPhysicalStock + adjustDelta)}</strong> | New ATP Available: <strong className="text-emerald-600">{Math.max(0, adjustModalItem.totalPhysicalStock + adjustDelta - adjustModalItem.reservedStock - adjustModalItem.safetyStock)}</strong>
                  </p>
                </div>

                <div>
                  <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                    Adjustment Reason / Purchase Order Ref
                  </label>
                  <select
                    value={adjustReason}
                    onChange={e => setAdjustReason(e.target.value)}
                    className="w-full px-3 py-2 rounded-xl bg-white dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 text-neutral-900 dark:text-white font-medium"
                  >
                    <option value="Supplier Stock Inbound (PO Receipt)">Supplier Stock Inbound (PO Receipt)</option>
                    <option value="Physical Warehouse Inventory Audit">Physical Warehouse Inventory Audit</option>
                    <option value="Damaged / Defective Stock Write-off">Damaged / Defective Stock Write-off</option>
                    <option value="Customer Return Restock">Customer Return Restock</option>
                    <option value="Safety Stock Buffer Recalibration">Safety Stock Buffer Recalibration</option>
                  </select>
                </div>

                <div>
                  <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                    Staff Operator
                  </label>
                  <input
                    type="text"
                    value={adjustUser}
                    onChange={e => setAdjustUser(e.target.value)}
                    className="w-full px-3 py-2 rounded-xl bg-white dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 text-neutral-900 dark:text-white"
                  />
                </div>
              </div>

              <div className="flex items-center justify-between px-6 py-4 border-t border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950/50">
                <button
                  type="button"
                  onClick={() => setAdjustModalItem(null)}
                  className="px-4 py-2 text-xs font-semibold text-neutral-600 dark:text-neutral-400 hover:bg-neutral-200 dark:hover:bg-neutral-800 rounded-xl cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-xs cursor-pointer"
                >
                  Confirm & Broadcast Delta
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Stock Ledger History Drawer */}
      {selectedAuditItem && (
        <div className="fixed inset-0 z-50 flex justify-end bg-black/50 backdrop-blur-xs">
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
                className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-800 dark:hover:text-neutral-200 cursor-pointer"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="p-6 overflow-y-auto space-y-4 text-xs flex-1">
              <div className="space-y-3">
                {(selectedAuditItem.historyLog || []).map((log, idx) => (
                  <div key={idx} className="p-3 bg-neutral-50 dark:bg-neutral-950 rounded-xl border border-neutral-200 dark:border-neutral-800 space-y-1">
                    <div className="flex items-center justify-between">
                      <span className="font-bold text-neutral-900 dark:text-white">{log.action}</span>
                      <span
                        className={`font-black text-xs px-2 py-0.5 rounded ${
                          log.change >= 0
                            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                            : 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300'
                        }`}
                      >
                        {log.change >= 0 ? `+${log.change}` : log.change} units
                      </span>
                    </div>
                    <p className="text-[11px] text-neutral-600 dark:text-neutral-400">{log.reason}</p>
                    <div className="flex items-center justify-between text-[10px] text-neutral-400 pt-1 border-t border-neutral-200 dark:border-neutral-800">
                      <span>Operator: {log.user}</span>
                      <span>{new Date(log.timestamp).toLocaleString()}</span>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Channel Mapping Modal */}
      <ChannelMappingModal
        item={selectedMappingItem}
        isOpen={Boolean(selectedMappingItem)}
        onClose={() => setSelectedMappingItem(null)}
        onSave={(id, platform, mapping) => linkChannelSku(id, platform, mapping)}
      />
    </div>
  );
};

