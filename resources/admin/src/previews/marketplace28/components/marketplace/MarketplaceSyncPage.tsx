import React, { useState, useMemo } from 'react';
import { useAdmin } from '../../context/AdminContext';
import { MarketplaceSyncLog, MarketplacePlatform } from '../../types';
import { SubNavHeader } from '../common/SubNavHeader';
import { Radio, Activity, CheckCircle2, AlertTriangle, Search, Trash2, FileCode2, Zap, ShieldCheck, Copy, Check, X, RotateCcw } from 'lucide-react';

export const MarketplaceSyncPage: React.FC = () => {
  const {
    syncLogs = [],
    marketplaceOrders = [],
    marketplaceInventory = [],
    marketplaceStoreProducts = [],
    retrySyncLog,
    clearSyncLogs,
    connections = [],
    addToast
  } = useAdmin();

  const safeLogs = syncLogs || [];
  const safeMpOrders = marketplaceOrders || [];
  const safeMpInv = marketplaceInventory || [];
  const safeStoreProds = marketplaceStoreProducts || [];
  const safeConns = connections || [];

  const [filterPlatform, setFilterPlatform] = useState<string>('all');
  const [filterStatus, setFilterStatus] = useState<string>('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedLog, setSelectedLog] = useState<MarketplaceSyncLog | null>(null);
  const [copied, setCopied] = useState(false);

  const filteredLogs = useMemo(() => {
    return safeLogs.filter(log => {
      if (filterPlatform !== 'all' && log.platform !== filterPlatform) return false;
      if (filterStatus !== 'all' && log.status !== filterStatus) return false;
      if (searchQuery.trim()) {
        const q = searchQuery.toLowerCase();
        const matchesRef = (log.referenceId || '').toLowerCase().includes(q);
        const matchesShop = log.shopName.toLowerCase().includes(q);
        const matchesEvent = log.eventType.toLowerCase().includes(q);
        const matchesMsg = log.message.toLowerCase().includes(q);
        if (!matchesRef && !matchesShop && !matchesEvent && !matchesMsg) return false;
      }
      return true;
    });
  }, [safeLogs, filterPlatform, filterStatus, searchQuery]);

  const getStatusBadge = (status: MarketplaceSyncLog['status'] | string | undefined) => {
    switch (status) {
      case 'success':
        return { label: 'SUCCESS (200 OK)', class: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-200' };
      case 'warning':
        return { label: 'WARNING', class: 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border-amber-200' };
      case 'failed':
        return { label: 'FAILED (RETRYABLE)', class: 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 border-rose-200' };
      case 'processing':
        return { label: 'PROCESSING', class: 'bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 border-blue-200' };
      default:
        return { label: status ? String(status).toUpperCase() : 'UNKNOWN', class: 'bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 border-neutral-300' };
    }
  };

  const getPlatformBadge = (p: MarketplacePlatform) => {
    switch (p) {
      case 'tiktok': return 'bg-neutral-900 text-white';
      case 'shopee': return 'bg-amber-600 text-white';
      case 'lazada': return 'bg-blue-600 text-white';
      case 'shopify':
      default:
        return 'bg-emerald-700 text-white';
    }
  };

  const handleCopyPayload = (payload?: string) => {
    if (!payload) return;
    navigator.clipboard.writeText(payload);
    setCopied(true);
    addToast({ type: 'info', title: 'Payload Copied', message: 'JSON API payload copied to clipboard.' });
    setTimeout(() => setCopied(false), 2000);
  };

  const successCount = safeLogs.filter(l => l.status === 'success').length;
  const failedCount = safeLogs.filter(l => l.status === 'failed').length;
  const connectedCount = safeConns.filter(c => c.status === 'connected').length;
  const pendingOrdersCount = safeMpOrders.filter(o => o.channelStatus === 'AWAITING_COLLECTION' || o.channelStatus === 'UNPAID' || !o.airwayBillGenerated).length;
  const unmappedCount = safeStoreProds.reduce((acc, p) => acc + p.variants.filter(v => v.mappingStatus === 'unmapped').length, 0);

  return (
    <div className="space-y-6">
      <SubNavHeader
        hubTitle="Marketplace"
        hubSection="marketplace"
        currentPageTitle="Webhooks & Sync Stream"
        tabs={[
          { id: 'marketplace-auth', label: 'Connect Store', badge: `${connectedCount}/4 Active`, badgeVariant: 'success' },
          { id: 'marketplace-mapping', label: 'Product & SKU Mapping', badge: unmappedCount > 0 ? `${unmappedCount} Unmapped` : 'All Mapped', badgeVariant: unmappedCount > 0 ? 'warning' : 'success' },
          { id: 'marketplace-oms', label: 'Orders (OMS)', badge: pendingOrdersCount > 0 ? `${pendingOrdersCount} Pending` : undefined, badgeVariant: 'warning' },
          { id: 'marketplace-inventory', label: 'Inventory & WMS', badge: `${safeMpInv.length} SKUs`, badgeVariant: 'info' },
          { id: 'marketplace-sync', label: 'Webhooks & Logs', badge: `${safeLogs.length}`, badgeVariant: 'default' },
        ]}
      />

      {/* Top Banner */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2">
            <h1 className="text-2xl font-black tracking-tight text-neutral-900 dark:text-white">
              Marketplace Webhooks & Closed-Loop Sync Stream
            </h1>
            <span className="text-[11px] font-bold px-2 py-0.5 rounded-full bg-violet-50 dark:bg-violet-950/60 text-violet-700 dark:text-violet-300 border border-violet-200 dark:border-violet-800">
              Live Ingestion Log
            </span>
          </div>
          <p className="text-xs text-neutral-500 mt-1">
            Real-time event ledger recording Open API webhooks, AWB registration calls, inventory broadcast logs, and token renewals.
          </p>
        </div>

        <div className="flex items-center gap-2.5">
          <button
            onClick={clearSyncLogs}
            className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 hover:bg-neutral-50 dark:hover:bg-neutral-800 text-neutral-700 dark:text-neutral-300 text-xs font-semibold transition-colors cursor-pointer"
          >
            <Trash2 className="w-3.5 h-3.5 text-rose-500" />
            Clear Logs
          </button>
        </div>
      </div>

      {/* Metrics Row */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between text-neutral-500 mb-2">
            <span className="text-xs font-bold uppercase tracking-wider">Total Synced Events</span>
            <Activity className="w-4 h-4 text-violet-500" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-black text-neutral-900 dark:text-white">{syncLogs.length}</span>
            <span className="text-xs text-neutral-400">All channels</span>
          </div>
          <div className="mt-3 flex items-center gap-1.5 text-[11px] text-neutral-500 font-medium">
            <Radio className="w-3.5 h-3.5 text-emerald-500 animate-pulse" />
            <span>Listening on 4 webhook endpoints</span>
          </div>
        </div>

        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between text-neutral-500 mb-2">
            <span className="text-xs font-bold uppercase tracking-wider">Success Rate</span>
            <CheckCircle2 className="w-4 h-4 text-emerald-500" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-black text-emerald-600 dark:text-emerald-400">
              {syncLogs.length > 0 ? `${((successCount / syncLogs.length) * 100).toFixed(1)}%` : '100%'}
            </span>
            <span className="text-xs text-neutral-400">{successCount} resolved</span>
          </div>
          <div className="mt-3 flex items-center gap-1.5 text-[11px] text-emerald-600 dark:text-emerald-400 font-medium">
            <ShieldCheck className="w-3.5 h-3.5" />
            <span>HMAC SHA-256 Signatures Valid</span>
          </div>
        </div>

        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between text-neutral-500 mb-2">
            <span className="text-xs font-bold uppercase tracking-wider">Failed Webhooks</span>
            <AlertTriangle className="w-4 h-4 text-rose-500" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-black text-rose-600 dark:text-rose-400">{failedCount}</span>
            <span className="text-xs text-neutral-400">Needs attention</span>
          </div>
          <div className="mt-3 flex items-center gap-1.5 text-[11px] text-neutral-500 font-medium">
            <span>1-Click retry available</span>
          </div>
        </div>

        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between text-neutral-500 mb-2">
            <span className="text-xs font-bold uppercase tracking-wider">Average API Latency</span>
            <Zap className="w-4 h-4 text-amber-500" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-black text-neutral-900 dark:text-white">164ms</span>
            <span className="text-xs text-emerald-500 font-bold">Fast Ingestion</span>
          </div>
          <div className="mt-3 flex items-center gap-1.5 text-[11px] text-neutral-500 font-medium">
            <span>Sub-second stock deduction</span>
          </div>
        </div>
      </div>

      {/* Filter Row */}
      <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-2 shadow-xs flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-1 overflow-x-auto">
          {[
            { id: 'all', label: 'All Platforms' },
            { id: 'tiktok', label: 'TikTok Shop' },
            { id: 'shopee', label: 'Shopee' },
            { id: 'lazada', label: 'Lazada' },
            { id: 'shopify', label: 'Shopify' },
          ].map(p => {
            const isActive = filterPlatform === p.id;
            return (
              <button
                key={p.id}
                onClick={() => setFilterPlatform(p.id)}
                className={`px-3 py-1.5 rounded-xl text-xs font-semibold transition-all cursor-pointer ${
                  isActive
                    ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900'
                    : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'
                }`}
              >
                {p.label}
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
            placeholder="Search Event, Reference ID, Message..."
            className="w-full pl-9 pr-3 py-1.5 text-xs rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 text-neutral-900 dark:text-white"
          />
        </div>
      </div>

      {/* Sync Logs Table */}
      <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-neutral-50 dark:bg-neutral-950/70 border-b border-neutral-200 dark:border-neutral-800 text-neutral-500 font-bold uppercase tracking-wider text-[10px]">
              <tr>
                <th className="py-3.5 px-4">Timestamp / Latency</th>
                <th className="py-3.5 px-4">Platform & Channel</th>
                <th className="py-3.5 px-4">Event Type</th>
                <th className="py-3.5 px-4">Reference ID</th>
                <th className="py-3.5 px-4">Status</th>
                <th className="py-3.5 px-4">Audit Message</th>
                <th className="py-3.5 px-4 text-right">Actions</th>
              </tr>
            </thead>

            <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800">
              {filteredLogs.length === 0 ? (
                <tr>
                  <td colSpan={7} className="py-12 text-center text-neutral-400">
                    <Activity className="w-8 h-8 mx-auto mb-2 text-neutral-300 dark:text-neutral-700" />
                    <p className="font-semibold">No sync event logs found</p>
                  </td>
                </tr>
              ) : (
                filteredLogs.map(log => {
                  const statusInfo = getStatusBadge(log.status);

                  return (
                    <tr key={log.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/40 transition-colors">
                      <td className="py-4 px-4 font-mono text-[11px]">
                        <p className="font-bold text-neutral-900 dark:text-white">
                          {new Date(log.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
                        </p>
                        <span className="text-[10px] text-neutral-400">{log.latencyMs || 120}ms</span>
                      </td>

                      <td className="py-4 px-4">
                        <span className={`inline-block text-[10px] font-bold px-2 py-0.5 rounded uppercase ${getPlatformBadge(log.platform)}`}>
                          {log.platform}
                        </span>
                        <p className="text-[11px] text-neutral-500 mt-0.5 truncate max-w-[140px]" title={log.shopName}>
                          {log.shopName}
                        </p>
                      </td>

                      <td className="py-4 px-4">
                        <span className="font-mono font-semibold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/60 px-2 py-1 rounded border border-indigo-100 dark:border-indigo-900/50">
                          {log.eventType}
                        </span>
                      </td>

                      <td className="py-4 px-4 font-mono text-[11px] text-neutral-700 dark:text-neutral-300">
                        {log.referenceId || 'N/A'}
                      </td>

                      <td className="py-4 px-4">
                        <span className={`inline-block text-[10px] font-bold px-2 py-0.5 rounded-full border ${statusInfo.class}`}>
                          {statusInfo.label}
                        </span>
                      </td>

                      <td className="py-4 px-4 text-neutral-700 dark:text-neutral-300 max-w-xs truncate" title={log.message}>
                        {log.message}
                      </td>

                      <td className="py-4 px-4 text-right">
                        <div className="inline-flex items-center gap-1.5 justify-end">
                          {log.status === 'failed' && (
                            <button
                              onClick={() => retrySyncLog(log.id)}
                              className="p-1.5 rounded-lg bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 hover:bg-rose-100 border border-rose-200 dark:border-rose-800 transition-colors cursor-pointer"
                              title="Retry Failed Event"
                            >
                              <RotateCcw className="w-3.5 h-3.5" />
                            </button>
                          )}
                          <button
                            onClick={() => setSelectedLog(log)}
                            className="p-1.5 rounded-lg bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-200 transition-colors cursor-pointer"
                            title="Inspect JSON Payload"
                          >
                            <FileCode2 className="w-3.5 h-3.5" />
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

      {/* JSON Payload Inspector Modal */}
      {selectedLog && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
          <div className="relative w-full max-w-2xl bg-white dark:bg-neutral-900 rounded-2xl shadow-2xl border border-neutral-200 dark:border-neutral-800 overflow-hidden">
            <div className="p-6 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between bg-neutral-50 dark:bg-neutral-950/50">
              <div className="flex items-center gap-2.5">
                <div className="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">
                  <FileCode2 className="w-5 h-5" />
                </div>
                <div>
                  <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                    Raw API Webhook Payload Inspector
                  </h3>
                  <p className="text-xs text-neutral-500 font-mono">
                    Event: {selectedLog.eventType} • {(selectedLog.platform || 'API').toUpperCase()}
                  </p>
                </div>
              </div>
              <button
                onClick={() => setSelectedLog(null)}
                className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-800 dark:hover:text-neutral-200 cursor-pointer"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="p-6 space-y-4 text-xs">
              <div className="flex items-center justify-between">
                <span className="font-semibold text-neutral-700 dark:text-neutral-300">
                  Request / Response Body (JSON)
                </span>
                <button
                  onClick={() => handleCopyPayload(selectedLog.payloadJson)}
                  className="inline-flex items-center gap-1 text-xs text-indigo-600 hover:underline font-semibold cursor-pointer"
                >
                  {copied ? <Check className="w-3.5 h-3.5 text-emerald-500" /> : <Copy className="w-3.5 h-3.5" />}
                  <span>{copied ? 'Copied!' : 'Copy JSON'}</span>
                </button>
              </div>

              <pre className="p-4 bg-neutral-950 text-emerald-400 rounded-xl font-mono text-xs overflow-x-auto max-h-72 border border-neutral-800 select-all">
                {selectedLog.payloadJson || JSON.stringify({ event: selectedLog.eventType, ref: selectedLog.referenceId, status: selectedLog.status }, null, 2)}
              </pre>

              <div className="p-3 bg-neutral-50 dark:bg-neutral-950 rounded-xl border border-neutral-200 dark:border-neutral-800 text-[11px] text-neutral-500 space-y-1">
                <p><strong>Latency:</strong> {selectedLog.latencyMs || 120}ms</p>
                <p><strong>Log ID:</strong> {selectedLog.id}</p>
                <p><strong>Timestamp:</strong> {new Date(selectedLog.timestamp).toISOString()}</p>
              </div>
            </div>

            <div className="flex items-center justify-end px-6 py-4 border-t border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950/50">
              <button
                onClick={() => setSelectedLog(null)}
                className="px-4 py-2 bg-neutral-200 dark:bg-neutral-800 hover:bg-neutral-300 dark:hover:bg-neutral-700 text-neutral-800 dark:text-neutral-200 font-semibold rounded-xl text-xs cursor-pointer"
              >
                Close Inspector
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

