import React, { useState, useMemo } from 'react';
import { useAdmin } from '../../context/ContextBridge';
import { MarketplaceOrder, MarketplacePlatform, ChannelOrderStatus } from '../../types';
import { ShippingLabelModal } from './ShippingLabelModal';
import { MarketplaceLogo } from './MarketplaceLogo';
import { SubNavHeader } from '../common/SubNavHeader';
import {OrderSummaryRows} from './OrderSummaryRows';
import type {OrderSummary,StoreConnection} from '../../../../integration/marketplaceOperations';
import { Boxes, Search, Printer, PackageCheck, Truck, CheckCircle2, Clock, CloudDownload, Download, Zap, Eye, X, User, MapPin, ArrowUpRight, RefreshCw, AlertTriangle } from 'lucide-react';

export interface OmsServerData {orders:OrderSummary[];summary:{total:number;statuses:Record<string,number>};onSelect:(id:string)=>void;onQuery:(query:{platform:string;status:string;q:string})=>void;stores?:StoreConnection[];storeId?:string;onStore?:(id:string)=>void;onFetch?:()=>void;canFetch?:boolean;recoverySummary?:{running:number;failed:number};}
export const MarketplaceOMSPage: React.FC<{operational?:boolean;serverData?:OmsServerData}> = ({operational=false,serverData}) => {
  const {
    marketplaceOrders = [],
    marketplaceInventory = [],
    marketplaceStoreProducts = [],
    syncLogs = [],
    selectedMarketplaceOrderId,
    setSelectedMarketplaceOrderId,
    generateAirwayBill,
    markOrderPacked,
    markOrderShipped,
    batchPrintAWB,
    batchPackOrders,
    simulateIncomingOrder,
    connections = [],
    addToast
  } = useAdmin();

  const safeMpOrders = marketplaceOrders || [];
  const safeMpInv = marketplaceInventory || [];
  const safeStoreProds = marketplaceStoreProducts || [];
  const safeLogs = syncLogs || [];
  const safeConns = connections || [];

  // Filters
  const [selectedChannel, setSelectedChannel] = useState<string>('all');
  const [selectedStatusTab, setSelectedStatusTab] = useState<string>('all');
  const [searchQuery, setSearchQuery] = useState('');
  const onQuery=serverData?.onQuery;
  React.useEffect(()=>{onQuery?.({platform:selectedChannel,status:selectedStatusTab,q:searchQuery});},[onQuery,selectedChannel,selectedStatusTab,searchQuery]);
  const [selectedOrderIds, setSelectedOrderIds] = useState<string[]>([]);
  const [activeAwbOrder, setActiveAwbOrder] = useState<MarketplaceOrder | null>(null);
  const [dispatchModalOrder, setDispatchModalOrder] = useState<MarketplaceOrder | null>(null);
  const [dispatchTrackingNum, setDispatchTrackingNum] = useState('');
  const [dispatchCarrier, setDispatchCarrier] = useState('');

  // Selected Order Drawer
  const activeOrder = useMemo(() => {
    return safeMpOrders.find(o => o.id === selectedMarketplaceOrderId) || null;
  }, [safeMpOrders, selectedMarketplaceOrderId]);

  // Filtered Orders
  const filteredOrders = useMemo(() => {
    return safeMpOrders.filter(order => {
      // Channel filter
      if (selectedChannel !== 'all' && order.channel !== selectedChannel) {
        return false;
      }
      // Status tab filter
      if (selectedStatusTab === 'unpaid' && order.channelStatus !== 'UNPAID') return false;
      if (selectedStatusTab === 'to_ship' && (order.channelStatus !== 'AWAITING_COLLECTION' && order.channelStatus !== 'READY_TO_SHIP')) return false;
      if (selectedStatusTab === 'in_transit' && order.channelStatus !== 'IN_TRANSIT') return false;
      if (selectedStatusTab === 'delivered' && (order.channelStatus !== 'DELIVERED' && order.channelStatus !== 'COMPLETED')) return false;
      if (selectedStatusTab === 'cancelled' && (order.channelStatus !== 'CANCELLED' && order.channelStatus !== 'RETURNED')) return false;

      // Search query
      if (searchQuery.trim()) {
        const q = searchQuery.toLowerCase();
        const matchesNum = order.orderNumber.toLowerCase().includes(q);
        const matchesChannelRef = order.channelOrderId.toLowerCase().includes(q);
        const matchesBuyer = (order.marketplaceBuyerUsername || '').toLowerCase().includes(q) || order.customerName.toLowerCase().includes(q);
        const matchesSku = order.items.some(i => i.sku.toLowerCase().includes(q) || i.title.toLowerCase().includes(q));
        const matchesTracking = (order.trackingNumber || '').toLowerCase().includes(q);
        if (!matchesNum && !matchesChannelRef && !matchesBuyer && !matchesSku && !matchesTracking) {
          return false;
        }
      }
      return true;
    });
  }, [marketplaceOrders, selectedChannel, selectedStatusTab, searchQuery]);

  // Handle select all checkbox
  const handleSelectAll = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.checked) {
      setSelectedOrderIds(filteredOrders.map(o => o.id));
    } else {
      setSelectedOrderIds([]);
    }
  };

  const handleToggleSelectOne = (id: string) => {
    if (selectedOrderIds.includes(id)) {
      setSelectedOrderIds(selectedOrderIds.filter(i => i !== id));
    } else {
      setSelectedOrderIds([...selectedOrderIds, id]);
    }
  };

  const getPlatformBadge = (platform: MarketplacePlatform) => {
    switch (platform) {
      case 'tiktok':
        return { label: 'TikTok Shop', bg: 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900', border: 'border-neutral-800' };
      case 'shopee':
        return { label: 'Shopee Shop', bg: 'bg-amber-600 text-white', border: 'border-amber-600' };
      case 'lazada':
        return { label: 'Lazada Shop', bg: 'bg-blue-600 text-white', border: 'border-blue-600' };
      case 'shopify':
      default:
        return { label: 'Shopify Shop', bg: 'bg-emerald-700 text-white', border: 'border-emerald-700' };
    }
  };

  const getStatusBadge = (status: ChannelOrderStatus | string | undefined) => {
    switch (status) {
      case 'UNPAID':
        return { label: 'Unpaid', class: 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400 border-neutral-300' };
      case 'AWAITING_COLLECTION':
      case 'UNPACKED':
        return { label: 'Awaiting Collection (To Pack)', class: 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300 border-amber-200 dark:border-amber-800' };
      case 'READY_TO_SHIP':
        return { label: 'Ready To Ship (AWB Printed)', class: 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800' };
      case 'IN_TRANSIT':
        return { label: 'In Transit (With Courier)', class: 'bg-cyan-50 text-cyan-700 dark:bg-cyan-950/50 dark:text-cyan-300 border-cyan-200 dark:border-cyan-800' };
      case 'DELIVERED':
      case 'COMPLETED':
        return { label: 'Delivered / Completed', class: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800' };
      case 'CANCELLED':
      case 'RETURNED':
        return { label: 'Cancelled / Returned', class: 'bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300 border-rose-200 dark:border-rose-800' };
      case 'UNFULFILLED':
      default:
        return { 
          label: status ? String(status).replace(/_/g, ' ') : 'Pending', 
          class: 'bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 border-neutral-300 dark:border-neutral-700' 
        };
    }
  };

  // Open courier handover modal
  const openDispatchModal = (order: MarketplaceOrder) => {
    if(operational)return;
    setDispatchModalOrder(order);
    setDispatchCarrier(order.trackingCarrier || (order.channel === 'tiktok' ? 'J&T Express' : order.channel === 'shopee' ? 'Shopee Xpress' : order.channel === 'lazada' ? 'LEX Express' : 'DHL Express'));
    setDispatchTrackingNum(order.trackingNumber || `${(order.channel || 'mp').substring(0, 2).toUpperCase()}${Math.floor(1000000000 + Math.random() * 9000000000)}MY`);
  };

  const handleConfirmDispatch = (e: React.FormEvent) => {
    e.preventDefault();
    if (!dispatchModalOrder) return;
    markOrderShipped(dispatchModalOrder.id, dispatchTrackingNum, dispatchCarrier);
    setDispatchModalOrder(null);
  };

  // Export CSV
  const handleExportCSV = () => {
    const headers = ['Order Number', 'Channel', 'Channel Order ID', 'Buyer', 'Status', 'Total Amount', 'Tracking Number', 'Created At'];
    const rows = filteredOrders.map(o => [
      o.orderNumber,
      (o.channel || 'MARKETPLACE').toUpperCase(),
      o.channelOrderId,
      o.marketplaceBuyerUsername || o.customerName,
      o.channelStatus,
      `${o.currency} ${o.totalAmount.toFixed(2)}`,
      o.trackingNumber || 'N/A',
      new Date(o.createdAt).toLocaleString()
    ]);
    const csvContent = 'data:text/csv;charset=utf-8,' + [headers.join(','), ...rows.map(e => e.join(','))].join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `marketplace_orders_${Date.now()}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    addToast({ type: 'success', title: 'Export Generated', message: `Exported ${filteredOrders.length} orders to CSV.` });
  };

  // Counts for status tabs
  const counts = useMemo(() => {
    const total = safeMpOrders.length;
    const unpaid = safeMpOrders.filter(o => o.channelStatus === 'UNPAID').length;
    const toShip = safeMpOrders.filter(o => o.channelStatus === 'AWAITING_COLLECTION' || o.channelStatus === 'READY_TO_SHIP').length;
    const inTransit = safeMpOrders.filter(o => o.channelStatus === 'IN_TRANSIT').length;
    const delivered = safeMpOrders.filter(o => o.channelStatus === 'DELIVERED' || o.channelStatus === 'COMPLETED').length;
    const cancelled = safeMpOrders.filter(o => o.channelStatus === 'CANCELLED' || o.channelStatus === 'RETURNED').length;
    return { total, unpaid, toShip, inTransit, delivered, cancelled };
  }, [safeMpOrders]);

  const connectedCount = safeConns.filter(c => c.status === 'connected').length;
  const pendingOrdersCount = safeMpOrders.filter(o => o.channelStatus === 'AWAITING_COLLECTION' || o.channelStatus === 'UNPAID' || !o.airwayBillGenerated).length;
  const unmappedCount = safeStoreProds.reduce((acc, p) => acc + p.variants.filter(v => v.mappingStatus === 'unmapped').length, 0);

  return (
    <div className="space-y-6">
      <SubNavHeader
        hubTitle="Marketplace"
        hubSection="marketplace"
        currentPageTitle="Marketplace Orders (OMS)"
        tabs={[
          { id: 'marketplace-auth', label: 'Connect Store', badge: operational ? undefined : `${connectedCount}/4 Active`, badgeVariant: 'success' },
          { id: 'marketplace-mapping', label: 'Product & SKU Mapping', badge: operational ? undefined : unmappedCount > 0 ? `${unmappedCount} Unmapped` : 'All Mapped', badgeVariant: unmappedCount > 0 ? 'warning' : 'success' },
          { id: 'marketplace-oms', label: 'Orders (OMS)', badge: pendingOrdersCount > 0 ? `${pendingOrdersCount} Pending` : undefined, badgeVariant: 'warning' },
          { id: 'marketplace-inventory', label: 'Inventory & WMS', badge: operational ? undefined : `${safeMpInv.length} SKUs`, badgeVariant: 'info' },
          { id: 'marketplace-sync', label: 'Webhooks & Logs', badge: operational ? undefined : `${safeLogs.length}`, badgeVariant: 'default' },
        ]}
      />

      {/* Top Header & Simulation Trigger */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2">
            <h1 className="text-2xl font-black tracking-tight text-neutral-900 dark:text-white">
              Marketplace Order Management System (OMS)
            </h1>
            <span className="text-[11px] font-bold px-2 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
              {operational ? 'Platform Order Records' : 'Omnichannel Fulfillment'}
            </span>
          </div>
          <p className="text-xs text-neutral-500 mt-1">
            {operational ? 'Inspect fetched platform orders and SKU mappings. Marketplace fulfillment is not enabled yet.' : 'Centralized fulfillment hub for TikTok Shop, Shopee, Lazada, and Shopify. One-click AWB label generation & courier handover.'}
          </p>
        </div>

        {/* Action buttons & Live Order Simulator */}
        <div className="flex flex-wrap items-center gap-2.5">
          {!operational && <div className="relative group">
            <button className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-600 hover:to-orange-700 text-white text-xs font-bold shadow-xs transition-all cursor-pointer">
              <Zap className="w-3.5 h-3.5" />
              <span>Simulate Inbound Order</span>
            </button>
            {/* Dropdown for choosing which marketplace to simulate */}
            <div className="absolute right-0 top-full mt-1.5 w-56 bg-white dark:bg-neutral-900 rounded-xl shadow-xl border border-neutral-200 dark:border-neutral-800 py-1.5 z-30 hidden group-hover:block">
              <div className="px-3 py-1 text-[10px] uppercase font-bold text-neutral-400">Choose Source Channel</div>
              <button
                onClick={() => simulateIncomingOrder('tiktok')}
                className="w-full px-3 py-1.5 text-left text-xs font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 flex items-center justify-between cursor-pointer"
              >
                <span>TikTok Shop (MY)</span>
                <span className="text-[10px] px-1.5 py-0.5 rounded bg-neutral-900 text-white font-mono">TTS</span>
              </button>
              <button
                onClick={() => simulateIncomingOrder('shopee')}
                className="w-full px-3 py-1.5 text-left text-xs font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 flex items-center justify-between cursor-pointer"
              >
                <span>Shopee Mall (MY)</span>
                <span className="text-[10px] px-1.5 py-0.5 rounded bg-amber-600 text-white font-mono">SHP</span>
              </button>
              <button
                onClick={() => simulateIncomingOrder('lazada')}
                className="w-full px-3 py-1.5 text-left text-xs font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 flex items-center justify-between cursor-pointer"
              >
                <span>Lazada LazMall</span>
                <span className="text-[10px] px-1.5 py-0.5 rounded bg-blue-600 text-white font-mono">LAZ</span>
              </button>
              <button
                onClick={() => simulateIncomingOrder('shopify')}
                className="w-full px-3 py-1.5 text-left text-xs font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 flex items-center justify-between cursor-pointer"
              >
                <span>Shopify Store Global</span>
                <span className="text-[10px] px-1.5 py-0.5 rounded bg-emerald-600 text-white font-mono">SPF</span>
              </button>
            </div>
          </div>}

          <div className="flex items-center gap-2">
          {operational&&serverData?.recoverySummary&&serverData.recoverySummary.running>0&&<button onClick={serverData.onFetch} className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-300 text-xs font-semibold"><RefreshCw className="w-3.5 h-3.5 animate-spin"/>Order recovery running for {serverData.recoverySummary.running} {serverData.recoverySummary.running===1?'store':'stores'}</button>}
          {operational&&serverData?.recoverySummary&&serverData.recoverySummary.failed>0&&<button onClick={serverData.onFetch} className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-rose-500/30 bg-rose-500/10 text-rose-600 dark:text-rose-300 text-xs font-semibold"><AlertTriangle className="w-3.5 h-3.5"/>{serverData.recoverySummary.failed} {serverData.recoverySummary.failed===1?'store requires':'stores require'} attention</button>}
          <button
            disabled={operational&&!serverData?.canFetch}
            title={operational ? 'Recover missing or delayed marketplace orders' : undefined}
            onClick={operational ? serverData?.onFetch : handleExportCSV}
            className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 hover:bg-neutral-50 dark:hover:bg-neutral-800 text-neutral-700 dark:text-neutral-300 text-xs font-semibold transition-colors cursor-pointer"
          >
            {operational?<CloudDownload className="w-3.5 h-3.5"/>:<Download className="w-3.5 h-3.5" />}
            {operational ? 'Fetch Marketplace Orders' : 'Export CSV'}
          </button>
          </div>
        </div>
      </div>

      {/* Channel Tabs */}
      <div className="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none">
        {[
          { id: 'all', label: 'All Marketplaces' },
          { id: 'tiktok', label: 'TikTok Shop' },
          { id: 'shopee', label: 'Shopee Shop' },
          { id: 'lazada', label: 'Lazada Shop' },
          { id: 'shopify', label: 'Shopify' },
        ].map(ch => {
          const isSelected = selectedChannel === ch.id;
          return (
            <button
              key={ch.id}
              onClick={() => setSelectedChannel(ch.id)}
              className={`flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition-all cursor-pointer ${
                isSelected
                  ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900 shadow-xs'
                  : 'bg-white dark:bg-neutral-900 text-neutral-600 dark:text-neutral-400 border border-neutral-200 dark:border-neutral-800 hover:bg-neutral-50 dark:hover:bg-neutral-800'
              }`}
            >
              {ch.id !== 'all' && (
                <span className={`shrink-0 ${isSelected ? '' : 'opacity-80'}`}>
                  <MarketplaceLogo platform={ch.id} size={16} />
                </span>
              )}
              <span>{ch.label}</span>
            </button>
          );
        })}
      </div>

      {/* Status Pipeline Filter Row */}
      <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-2 shadow-xs flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-1 overflow-x-auto w-full sm:w-auto">
          {(serverData ? [{id:'all',label:'All Orders',count:serverData.summary.total,highlight:false},...Object.entries(serverData.summary.statuses).map(([id,count])=>({id,label:id.replace(/_/g,' '),count,highlight:false}))] : [
            { id: 'all', label: 'All Orders', count: counts.total },
            { id: 'to_ship', label: 'To Ship / Ready', count: counts.toShip, highlight: counts.toShip > 0 },
            { id: 'in_transit', label: 'In Transit', count: counts.inTransit },
            { id: 'delivered', label: 'Delivered', count: counts.delivered },
            { id: 'unpaid', label: 'Unpaid', count: counts.unpaid },
            { id: 'cancelled', label: 'Cancelled', count: counts.cancelled },
          ]).map(tab => {
            const isActive = selectedStatusTab === tab.id;
            return (
              <button
                key={tab.id}
                onClick={() => setSelectedStatusTab(tab.id)}
                className={`flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-semibold transition-all cursor-pointer ${
                  isActive
                    ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 shadow-xs'
                    : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800/60'
                }`}
              >
                <span>{tab.label}</span>
                <span
                  className={`text-[10px] px-1.5 py-0.2 rounded-full font-bold ${
                    tab.highlight
                      ? 'bg-amber-500 text-white'
                      : isActive
                      ? 'bg-indigo-200 dark:bg-indigo-800 text-indigo-900 dark:text-indigo-100'
                      : 'bg-neutral-200 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400'
                  }`}
                >
                  {tab.count}
                </span>
              </button>
            );
          })}
        </div>

        {/* Search Input */}
        {serverData?.onStore&&<select aria-label="Filter orders by store" value={serverData.storeId} onChange={event=>serverData.onStore?.(event.target.value)} className="text-xs px-3 py-1.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-neutral-900 dark:text-white font-medium focus:ring-2 focus:ring-indigo-500 cursor-pointer">
          <option value="all">All Stores</option>
          {serverData.stores?.map(store=><option key={store.id} value={store.id}>{store.shopName} ({store.platform})</option>)}
        </select>}
        <div className="relative w-full sm:w-72">
          <Search className="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400" />
          <input
            type="text"
            value={searchQuery}
            onChange={e => setSearchQuery(e.target.value)}
            placeholder={operational ? 'Search platform order reference...' : 'Search Order #, Channel Ref, Buyer, SKU...'}
            className="w-full pl-9 pr-3 py-1.5 text-xs rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 text-neutral-900 dark:text-white"
          />
        </div>
      </div>

      {/* Batch Operations Bar (When items selected) */}
      {selectedOrderIds.length > 0 && (
        <div className="p-3 bg-indigo-50 dark:bg-indigo-950/60 rounded-xl border border-indigo-200 dark:border-indigo-800 flex flex-wrap items-center justify-between gap-3 text-xs">
          <div className="flex items-center gap-2 text-indigo-900 dark:text-indigo-200 font-semibold">
            <span>{selectedOrderIds.length} order(s) selected</span>
          </div>

          <div className="flex items-center gap-2">
            <button
              disabled={operational}
              onClick={() => {
                batchPrintAWB(selectedOrderIds);
                setSelectedOrderIds([]);
              }}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-bold shadow-xs transition-colors cursor-pointer"
            >
              <Printer className="w-3.5 h-3.5" />
              Batch Generate & Print AWBs
            </button>
            <button
              disabled={operational}
              onClick={() => {
                batchPackOrders(selectedOrderIds);
                setSelectedOrderIds([]);
              }}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white dark:bg-neutral-900 border border-indigo-200 dark:border-indigo-700 text-indigo-700 dark:text-indigo-300 rounded-lg font-semibold hover:bg-indigo-50 transition-colors cursor-pointer"
            >
              <PackageCheck className="w-3.5 h-3.5" />
              Batch Mark Packed
            </button>
            <button
              onClick={() => setSelectedOrderIds([])}
              className="px-2 py-1 text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200"
            >
              Clear
            </button>
          </div>
        </div>
      )}

      {/* Orders Table */}
      <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-neutral-50 dark:bg-neutral-950/70 border-b border-neutral-200 dark:border-neutral-800 text-neutral-500 font-bold uppercase tracking-wider text-[10px]">
              <tr>
                <th className="py-3.5 px-4 w-10">
                  <input
                    type="checkbox"
                    disabled={operational}
                    checked={selectedOrderIds.length > 0 && selectedOrderIds.length === filteredOrders.length}
                    onChange={handleSelectAll}
                    className="rounded border-neutral-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4 cursor-pointer"
                  />
                </th>
                <th className="py-3.5 px-4">Order Details</th>
                <th className="py-3.5 px-4">Platform / Store</th>
                <th className="py-3.5 px-4">Items / SKUs</th>
                <th className="py-3.5 px-4">Total Value</th>
                <th className="py-3.5 px-4">Fulfillment Status & SLA</th>
                <th className="py-3.5 px-4">Courier / Tracking</th>
                <th className="py-3.5 px-4 text-right">Fulfillment Actions</th>
              </tr>
            </thead>

            <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800">
              {serverData ? <OrderSummaryRows orders={serverData.orders} onSelect={serverData.onSelect}/> : filteredOrders.length === 0 ? (
                <tr>
                  <td colSpan={8} className="py-12 text-center text-neutral-400">
                    <Boxes className="w-8 h-8 mx-auto mb-2 text-neutral-300 dark:text-neutral-700" />
                    <p className="font-semibold">No marketplace orders found</p>
                    <p className="text-xs text-neutral-500 mt-0.5">{operational ? 'No fetched orders match these filters.' : 'Try changing the channel filter or simulate an incoming order.'}</p>
                  </td>
                </tr>
              ) : (
                filteredOrders.map(order => {
                  const plat = getPlatformBadge(order.channel);
                  const status = getStatusBadge(order.channelStatus);
                  if(operational)status.label=order.channelStatus ? String(order.channelStatus).replace(/_/g,' ') : 'Unavailable';
                  const isSelected = selectedOrderIds.includes(order.id);
                  const totalQty = order.items.reduce((s, i) => s + i.quantity, 0);

                  return (
                    <tr
                      key={order.id}
                      className={`hover:bg-neutral-50 dark:hover:bg-neutral-800/40 transition-colors ${
                        isSelected ? 'bg-indigo-50/40 dark:bg-indigo-950/20' : ''
                      }`}
                    >
                      {/* Checkbox */}
                      <td className="py-4 px-4">
                        <input
                          type="checkbox"
                          checked={isSelected}
                          onChange={() => handleToggleSelectOne(order.id)}
                          className="rounded border-neutral-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4 cursor-pointer"
                        />
                      </td>

                      {/* Order Numbers & Buyer */}
                      <td className="py-4 px-4">
                        <div className="space-y-0.5">
                          <button
                            onClick={() => setSelectedMarketplaceOrderId(order.id)}
                            className="font-bold text-neutral-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 flex items-center gap-1 cursor-pointer font-mono"
                          >
                            <span>{order.orderNumber}</span>
                            <ArrowUpRight className="w-3 h-3 text-neutral-400" />
                          </button>
                          <p className="text-[11px] font-mono text-neutral-500">
                            Ref: {order.channelOrderId}
                          </p>
                          <div className="flex items-center gap-1 text-[11px] text-neutral-600 dark:text-neutral-300">
                            <User className="w-3 h-3 text-neutral-400" />
                            <span>{order.marketplaceBuyerUsername || order.customerName}</span>
                          </div>
                        </div>
                      </td>

                      {/* Platform & Store */}
                      <td className="py-4 px-4">
                        <MarketplaceLogo platform={order.channel} variant="badge" />
                        <p className="text-[11px] text-neutral-500 mt-1 truncate max-w-[140px]" title={order.channelStoreName}>
                          {order.channelStoreName}
                        </p>
                      </td>

                      {/* Items */}
                      <td className="py-4 px-4">
                        <div className="flex items-center gap-2">
                          {order.items[0]?.image && (
                            <img
                              src={order.items[0].image}
                              alt={order.items[0].title}
                              referrerPolicy="no-referrer"
                              className="w-9 h-9 rounded-lg object-cover border border-neutral-200 dark:border-neutral-800 flex-shrink-0"
                            />
                          )}
                          <div className="min-w-0">
                            <p className="font-semibold text-neutral-900 dark:text-white truncate max-w-[150px]" title={order.items[0]?.title}>
                              {order.items[0]?.title || 'Item'}
                            </p>
                            <p className="text-[10px] font-mono text-neutral-500">
                              SKU: {order.items[0]?.sku} • {totalQty} unit(s)
                            </p>
                          </div>
                        </div>
                      </td>

                      {/* Total Value */}
                      <td className="py-4 px-4">
                        <span className="font-bold text-neutral-900 dark:text-white">
                          {order.currency} {order.totalAmount.toFixed(2)}
                        </span>
                        <span className="block text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold uppercase">
                          {order.paymentGatewayStatus}
                        </span>
                      </td>

                      {/* Status & SLA */}
                      <td className="py-4 px-4 space-y-1.5">
                        <span className={`inline-block text-[10px] font-bold px-2 py-0.5 rounded-full border ${status.class}`}>
                          {status.label}
                        </span>
                        {order.slaShipDeadline && (
                          <div className="flex items-center gap-1 text-[10px] text-neutral-500 font-medium">
                            <Clock className="w-3 h-3 text-amber-500" />
                            <span>Ship by: {new Date(order.slaShipDeadline).toLocaleDateString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</span>
                          </div>
                        )}
                      </td>

                      {/* Carrier & Tracking */}
                      <td className="py-4 px-4">
                        {order.trackingNumber ? (
                          <div className="space-y-0.5 font-mono">
                            <p className="font-bold text-neutral-800 dark:text-neutral-200 text-[11px] truncate max-w-[140px]">
                              {order.trackingNumber}
                            </p>
                            <p className="text-[10px] text-neutral-500 truncate max-w-[140px]">
                              {order.trackingCarrier}
                            </p>
                          </div>
                        ) : (
                          <span className="text-[11px] text-neutral-400 italic">No AWB yet</span>
                        )}
                      </td>

                      {/* Actions */}
                      <td className="py-4 px-4 text-right">
                        <div className="inline-flex items-center gap-1.5 justify-end">
                          {/* Print/Gen AWB */}
                          {!order.airwayBillGenerated ? (
                            <button
                              disabled={operational}
                              onClick={() => {
                                generateAirwayBill(order.id);
                                setActiveAwbOrder(order);
                              }}
                              className="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 dark:hover:bg-indigo-900 border border-indigo-200 dark:border-indigo-800 transition-colors cursor-pointer"
                              title="Generate Official Airway Bill (AWB)"
                            >
                              <Printer className="w-4 h-4" />
                            </button>
                          ) : (
                            <button
                              disabled={operational}
                              onClick={() => setActiveAwbOrder(order)}
                              className="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-100 dark:hover:bg-emerald-900 border border-emerald-200 dark:border-emerald-800 transition-colors cursor-pointer"
                              title="Reprint AWB Shipping Label"
                            >
                              <Printer className="w-4 h-4" />
                            </button>
                          )}

                          {/* Mark Packed */}
                          {order.channelStatus === 'AWAITING_COLLECTION' && (
                            <button
                              disabled={operational}
                              onClick={() => markOrderPacked(order.id, 'Warehouse Staff')}
                              className="p-1.5 rounded-lg bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 hover:bg-amber-100 border border-amber-200 dark:border-amber-800 transition-colors cursor-pointer"
                              title="Mark Picked & Packed"
                            >
                              <PackageCheck className="w-4 h-4" />
                            </button>
                          )}

                          {/* Handover to Courier */}
                          {(order.channelStatus === 'AWAITING_COLLECTION' || order.channelStatus === 'READY_TO_SHIP') && (
                            <button
                              disabled={operational}
                              onClick={() => openDispatchModal(order)}
                              className="p-1.5 rounded-lg bg-cyan-50 dark:bg-cyan-950/60 text-cyan-600 dark:text-cyan-400 hover:bg-cyan-100 border border-cyan-200 dark:border-cyan-800 transition-colors cursor-pointer"
                              title="Dispatch / Courier Handover"
                            >
                              <Truck className="w-4 h-4" />
                            </button>
                          )}

                          {/* View Drawer */}
                          <button
                            onClick={() => setSelectedMarketplaceOrderId(order.id)}
                            className="p-1.5 rounded-lg text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors cursor-pointer"
                            title="Inspect Order Audit"
                          >
                            <Eye className="w-4 h-4" />
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

      {/* Order Inspection Drawer */}
      {!operational && activeOrder && (
        <div className="fixed inset-0 z-50 flex justify-end bg-black/50 backdrop-blur-xs">
          <div className="w-full max-w-xl bg-white dark:bg-neutral-900 h-full shadow-2xl flex flex-col border-l border-neutral-200 dark:border-neutral-800 overflow-hidden animate-in slide-in-from-right duration-200">
            {/* Drawer Header */}
            <div className="p-6 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between bg-neutral-50 dark:bg-neutral-950/50">
              <div>
                <div className="flex items-center gap-2.5">
                  <MarketplaceLogo platform={activeOrder.channel} variant="badge" />
                  <h3 className="font-black text-base text-neutral-900 dark:text-white font-mono">
                    #{activeOrder.orderNumber}
                  </h3>
                </div>
                <p className="text-xs text-neutral-500 font-mono mt-0.5">
                  Platform ID: {activeOrder.channelOrderId} • Store: {activeOrder.channelStoreName}
                </p>
              </div>

              <button
                onClick={() => setSelectedMarketplaceOrderId(null)}
                className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-800 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors cursor-pointer"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            {/* Drawer Content */}
            <div className="p-6 overflow-y-auto space-y-6 text-xs flex-1">
              {/* Quick Actions Toolbar */}
              <div className="p-4 bg-indigo-50/50 dark:bg-indigo-950/30 rounded-2xl border border-indigo-100 dark:border-indigo-900/50 flex flex-wrap items-center justify-between gap-3">
                <div>
                  <p className="font-bold text-neutral-900 dark:text-white">Fulfillment Station</p>
                  <p className="text-[11px] text-neutral-500">AWB status: {activeOrder.airwayBillGenerated ? 'Generated (Ready)' : 'Pending Label'}</p>
                </div>
                <div className="flex items-center gap-2">
                  <button
                    onClick={() => {
                      generateAirwayBill(activeOrder.id);
                      setActiveAwbOrder(activeOrder);
                    }}
                    className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold shadow-xs cursor-pointer"
                  >
                    <Printer className="w-3.5 h-3.5" />
                    Print AWB Label
                  </button>
                  {activeOrder.channelStatus !== 'IN_TRANSIT' && (
                    <button
                      onClick={() => openDispatchModal(activeOrder)}
                      className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-cyan-600 hover:bg-cyan-700 text-white font-bold shadow-xs cursor-pointer"
                    >
                      <Truck className="w-3.5 h-3.5" />
                      Dispatch
                    </button>
                  )}
                </div>
              </div>

              {/* Warehouse & Shipping Address */}
              <div className="grid grid-cols-2 gap-3">
                <div className="p-4 bg-neutral-50 dark:bg-neutral-950 rounded-xl border border-neutral-200 dark:border-neutral-800 space-y-1">
                  <span className="text-[10px] font-bold uppercase text-neutral-400">Allocated WMS Hub</span>
                  <p className="font-bold text-neutral-900 dark:text-white">{activeOrder.allocatedWarehouse || 'Warehouse Alpha (Central Hub)'}</p>
                  <p className="text-[11px] text-neutral-500">Dropoff / Pickup: {(activeOrder.pickupType || 'DROPOFF').toUpperCase()}</p>
                </div>

                <div className="p-4 bg-neutral-50 dark:bg-neutral-950 rounded-xl border border-neutral-200 dark:border-neutral-800 space-y-1">
                  <span className="text-[10px] font-bold uppercase text-neutral-400">Buyer Marketplace Profile</span>
                  <p className="font-bold text-neutral-900 dark:text-white">{activeOrder.marketplaceBuyerUsername || activeOrder.customerName}</p>
                  <p className="text-[11px] text-neutral-500">{activeOrder.customerPhone || 'Phone masked by API'}</p>
                </div>
              </div>

              {/* Shipping Address */}
              <div className="p-4 bg-neutral-50 dark:bg-neutral-950 rounded-xl border border-neutral-200 dark:border-neutral-800 space-y-1">
                <div className="flex items-center gap-1.5 text-neutral-700 dark:text-neutral-300 font-bold mb-1">
                  <MapPin className="w-3.5 h-3.5 text-indigo-500" />
                  <span>Consignee Delivery Address</span>
                </div>
                <p className="font-semibold text-neutral-900 dark:text-white">{activeOrder.shippingAddress.firstName} {activeOrder.shippingAddress.lastName}</p>
                <p className="text-neutral-600 dark:text-neutral-400">{activeOrder.shippingAddress.address1}</p>
                <p className="text-neutral-600 dark:text-neutral-400">{activeOrder.shippingAddress.city}, {activeOrder.shippingAddress.province} {activeOrder.shippingAddress.postalCode}</p>
                <p className="text-neutral-600 dark:text-neutral-400">{activeOrder.shippingAddress.country}</p>
              </div>

              {/* Items List */}
              <div className="space-y-3">
                <h4 className="font-bold text-neutral-900 dark:text-white">Ordered Items ({activeOrder.items?.length || 0})</h4>
                <div className="space-y-2">
                  {(activeOrder.items || []).map((item, idx) => (
                    <div key={idx} className="p-3 bg-neutral-50 dark:bg-neutral-950 rounded-xl border border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
                      <div className="flex items-center gap-3">
                        {item.image && (
                          <img src={item.image} alt={item.title} referrerPolicy="no-referrer" className="w-11 h-11 rounded-lg object-cover border border-neutral-200 dark:border-neutral-800" />
                        )}
                        <div>
                          <p className="font-bold text-neutral-900 dark:text-white">{item.title}</p>
                          <p className="text-[11px] font-mono text-neutral-500">Master SKU: {item.sku} {item.variantTitle ? `• ${item.variantTitle}` : ''}</p>
                        </div>
                      </div>
                      <div className="text-right">
                        <span className="font-bold text-neutral-900 dark:text-white">x{item.quantity}</span>
                        <p className="text-neutral-500 font-semibold">{activeOrder.currency} {item.price.toFixed(2)}</p>
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              {/* Order Timeline Audit */}
              <div className="space-y-3">
                <h4 className="font-bold text-neutral-900 dark:text-white">Fulfillment Timeline & Webhook Events</h4>
                <div className="space-y-2 relative border-l-2 border-indigo-200 dark:border-indigo-900 ml-2 pl-4">
                  {(activeOrder.timeline || []).map((evt, idx) => (
                    <div key={idx} className="relative pb-2">
                      <div className="w-2.5 h-2.5 rounded-full bg-indigo-600 absolute -left-[21px] top-1" />
                      <div className="flex items-center justify-between">
                        <p className="font-bold text-neutral-900 dark:text-white text-[11px]">{evt.title}</p>
                        <span className="text-[10px] text-neutral-400">{new Date(evt.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
                      </div>
                      <p className="text-[11px] text-neutral-600 dark:text-neutral-400 mt-0.5">{evt.description}</p>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Dispatch / Courier Handover Modal */}
      {dispatchModalOrder && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
          <div className="relative w-full max-w-md bg-white dark:bg-neutral-900 rounded-2xl shadow-2xl border border-neutral-200 dark:border-neutral-800 overflow-hidden">
            <form onSubmit={handleConfirmDispatch}>
              <div className="p-6 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950/50">
                <div className="flex items-center gap-2.5">
                  <div className="p-2 rounded-xl bg-cyan-50 dark:bg-cyan-950/50 text-cyan-600 dark:text-cyan-400 border border-cyan-200 dark:border-cyan-800">
                    <Truck className="w-5 h-5" />
                  </div>
                  <div>
                    <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                      Confirm Courier Handover
                    </h3>
                    <p className="text-xs text-neutral-500">
                      Order #{dispatchModalOrder.orderNumber} • {(dispatchModalOrder.channel || 'MARKETPLACE').toUpperCase()}
                    </p>
                  </div>
                </div>
              </div>

              <div className="p-6 space-y-4 text-xs">
                <div>
                  <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                    Logistics Carrier Name
                  </label>
                  <input
                    type="text"
                    value={dispatchCarrier}
                    onChange={e => setDispatchCarrier(e.target.value)}
                    className="w-full px-3 py-2 rounded-xl bg-white dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 text-neutral-900 dark:text-white font-semibold"
                    required
                  />
                </div>

                <div>
                  <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                    Carrier Tracking Number
                  </label>
                  <input
                    type="text"
                    value={dispatchTrackingNum}
                    onChange={e => setDispatchTrackingNum(e.target.value)}
                    className="w-full px-3 py-2 rounded-xl bg-white dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 font-mono text-neutral-900 dark:text-white font-bold"
                    required
                  />
                </div>

                <div className="p-3 bg-cyan-50 dark:bg-cyan-950/40 rounded-xl border border-cyan-200 dark:border-cyan-800 flex items-start gap-2 text-cyan-900 dark:text-cyan-200">
                  <CheckCircle2 className="w-4 h-4 text-cyan-600 flex-shrink-0 mt-0.5" />
                  <p className="text-[11px]">
                    Submitting this will trigger the Open API writeback to {(dispatchModalOrder.channel || 'Marketplace').toUpperCase()} and mark order status as <strong>SHIPPED / IN_TRANSIT</strong>.
                  </p>
                </div>
              </div>

              <div className="flex items-center justify-between px-6 py-4 border-t border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950/50">
                <button
                  type="button"
                  onClick={() => setDispatchModalOrder(null)}
                  className="px-4 py-2 text-xs font-semibold text-neutral-600 dark:text-neutral-400 hover:bg-neutral-200 dark:hover:bg-neutral-800 rounded-xl cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 bg-cyan-600 hover:bg-cyan-700 text-white text-xs font-bold rounded-xl shadow-xs cursor-pointer"
                >
                  Confirm Dispatch & Sync
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Printable Shipping Label Modal */}
      <ShippingLabelModal
        order={activeAwbOrder}
        isOpen={Boolean(activeAwbOrder)}
        onClose={() => setActiveAwbOrder(null)}
      />
    </div>
  );
};
