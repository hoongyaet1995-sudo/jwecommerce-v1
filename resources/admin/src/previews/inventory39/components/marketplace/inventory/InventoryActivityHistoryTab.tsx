import React, { useState, useMemo } from 'react';
import {
  History,
  Search,
  Filter,
  CheckCircle2,
  AlertTriangle,
  FileText,
  Eye,
  X,
  Layers,
  Store,
  Boxes,
  User,
  Clock,
  ArrowRight,
  ShieldCheck,
  RotateCcw
} from 'lucide-react';
import { InventoryActivityItem, InventoryActivityActionType } from './inventoryTypes';
import { MarketplaceLogo } from '../MarketplaceLogo';

interface InventoryActivityHistoryTabProps {
  logs: InventoryActivityItem[];
}

export const InventoryActivityHistoryTab: React.FC<InventoryActivityHistoryTabProps> = ({ logs }) => {
  const [searchQuery, setSearchQuery] = useState('');
  const [filterActionType, setFilterActionType] = useState<string>('all');
  const [filterScope, setFilterScope] = useState<string>('all');
  const [inspectingItem, setInspectingItem] = useState<InventoryActivityItem | null>(null);

  // Extract scopes
  const safeLogs = logs || [];

  const uniqueScopes = useMemo(() => {
    const s = new Set<string>();
    safeLogs.forEach(l => {
      if (l.targetScope) s.add(l.targetScope);
    });
    return Array.from(s);
  }, [safeLogs]);

  // Filter logs
  const filteredLogs = useMemo(() => {
    return safeLogs.filter(l => {
      if (filterActionType !== 'all' && l.actionType !== filterActionType) return false;
      if (filterScope !== 'all' && l.targetScope !== filterScope) return false;

      if (searchQuery.trim()) {
        const q = searchQuery.toLowerCase();
        const matchesSku = l.masterSku.toLowerCase().includes(q);
        const matchesName = l.productName.toLowerCase().includes(q);
        const matchesOperator = l.operator.toLowerCase().includes(q);
        const matchesReason = l.reason.toLowerCase().includes(q);
        const matchesRef = l.referenceId ? l.referenceId.toLowerCase().includes(q) : false;
        if (!matchesSku && !matchesName && !matchesOperator && !matchesReason && !matchesRef) {
          return false;
        }
      }
      return true;
    });
  }, [logs, filterActionType, filterScope, searchQuery]);

  const getActionBadge = (type: InventoryActivityActionType) => {
    switch (type) {
      case 'first_count':
        return {
          label: '首次盘点 (First Count)',
          bg: 'bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200 border-neutral-200 dark:border-neutral-700'
        };
      case 'actual_intake':
        return {
          label: '实际入库 (Actual Intake)',
          bg: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-200'
        };
      case 'stock_adjustment':
        return {
          label: '盘点修正 (Audit Adjustment)',
          bg: 'bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200 border-neutral-200 dark:border-neutral-700'
        };
      case 'order_reserved':
        return {
          label: '订单预留 (Order Reserved)',
          bg: 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border-amber-200'
        };
      case 'order_released':
        return {
          label: '订单释放 (Order Released)',
          bg: 'bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200 border-neutral-200 dark:border-neutral-700'
        };
      case 'order_shipped':
        return {
          label: '发货扣减 (Order Shipped)',
          bg: 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border-rose-200'
        };
      case 'return_restocked':
        return {
          label: '退货验收入库 (Return Restocked)',
          bg: 'bg-teal-100 text-teal-800 dark:bg-teal-950/60 dark:text-teal-300 border-teal-200'
        };
      case 'store_transfer':
        return {
          label: '店铺调配 (Store Transfer)',
          bg: 'bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200 border-neutral-200 dark:border-neutral-700'
        };
      case 'platform_sync':
        return {
          label: '平台同步 (Platform Sync)',
          bg: 'bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-300 border-neutral-200 dark:border-neutral-700'
        };
      case 'mode_change':
        return {
          label: '模式变更 (Mode Change)',
          bg: 'bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200 border-neutral-200 dark:border-neutral-700'
        };
      case 'store_takeover':
        return {
          label: '店铺库存同步 (Store Inventory Sync)',
          bg: 'bg-green-100 text-green-800 dark:bg-green-950/60 dark:text-green-300 border-green-200'
        };
      default:
        return {
          label: type,
          bg: 'bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-300 border-neutral-200 dark:border-neutral-700'
        };
    }
  };

  return (
    <div id="inventory-activity-history-tab" className="space-y-6 animate-in fade-in duration-150">
      {/* Search & Filters */}
      <div className="p-4 sm:p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div className="flex-1 relative">
          <Search className="w-4 h-4 absolute left-3 top-2.5 text-neutral-400" />
          <input
            type="text"
            placeholder="Search by SKU, product, operator, reason, or order reference ID..."
            value={searchQuery}
            onChange={e => setSearchQuery(e.target.value)}
            className="w-full pl-9 pr-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-neutral-900 dark:text-white"
          />
        </div>

        <div className="flex flex-wrap items-center gap-2">
          {/* Action Type Filter */}
          <select
            value={filterActionType}
            onChange={e => setFilterActionType(e.target.value)}
            className="px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 font-medium cursor-pointer"
          >
            <option value="all">All Action Types</option>
            <option value="first_count">首次盘点 (First Count)</option>
            <option value="actual_intake">实际入库 (Actual Intake)</option>
            <option value="stock_adjustment">盘点修正 (Audit Adjustment)</option>
            <option value="order_reserved">订单预留 (Order Reserved)</option>
            <option value="order_released">订单释放 (Order Released)</option>
            <option value="order_shipped">发货扣减 (Order Shipped)</option>
            <option value="return_restocked">退货验收入库 (Return Restocked)</option>
            <option value="store_transfer">店铺调配 (Store Transfer)</option>
            <option value="platform_sync">平台同步 (Platform Sync)</option>
            <option value="mode_change">模式变更 (Mode Change)</option>
            <option value="store_takeover">店铺库存同步 (Store Inventory Sync)</option>
          </select>

          {/* Scope Filter */}
          <select
            value={filterScope}
            onChange={e => setFilterScope(e.target.value)}
            className="px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 font-medium cursor-pointer"
          >
            <option value="all">All Scopes / Stores</option>
            {(uniqueScopes || []).map(sc => (
              <option key={sc} value={sc}>{sc}</option>
            ))}
          </select>

          {(filterActionType !== 'all' || filterScope !== 'all' || searchQuery.trim() !== '') && (
            <button
              type="button"
              onClick={() => {
                setFilterActionType('all');
                setFilterScope('all');
                setSearchQuery('');
              }}
              className="px-3 py-2 text-xs font-semibold text-neutral-500 hover:text-neutral-900 dark:hover:text-white flex items-center gap-1 cursor-pointer"
            >
              <RotateCcw className="w-3.5 h-3.5" />
              <span>Reset</span>
            </button>
          )}
        </div>
      </div>

      {/* Logs Table */}
      <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead>
              <tr className="border-b border-neutral-100 dark:border-neutral-800 text-neutral-400 uppercase text-[10px] tracking-wider bg-neutral-50/50 dark:bg-neutral-800/40">
                <th className="py-2.5 px-3">Timestamp</th>
                <th className="py-2.5 px-3">Action Type</th>
                <th className="py-2.5 px-3">Product / SKU</th>
                <th className="py-2.5 px-3">Store Account / Scope</th>
                <th className="py-2.5 px-3">Quantity Delta & Result</th>
                <th className="py-2.5 px-3">Operator / Source</th>
                <th className="py-2.5 px-3">Reason / Ref ID</th>
                <th className="py-2.5 px-3 text-right">Details</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
              {(filteredLogs || []).map(item => {
                const badge = getActionBadge(item.actionType);
                return (
                  <tr key={item.id} className="hover:bg-neutral-50/60 dark:hover:bg-neutral-800/50 transition-colors">
                    <td className="py-3 px-3 whitespace-nowrap text-neutral-500 font-mono text-[11px]">
                      {new Date(item.timestamp).toLocaleString([], {
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                      })}
                    </td>
                    <td className="py-3 px-3">
                      <span className={`inline-block px-2 py-0.5 rounded-md text-[10px] font-bold border ${badge.bg}`}>
                        {badge.label}
                      </span>
                    </td>
                    <td className="py-3 px-3">
                      <div className="font-mono font-bold text-neutral-900 dark:text-white">{item.masterSku}</div>
                      <div className="text-[11px] text-neutral-700 dark:text-neutral-300 truncate max-w-[180px]">
                        {item.productName}
                      </div>
                    </td>
                    <td className="py-3 px-3">
                      <div className="font-medium text-neutral-800 dark:text-neutral-200">{item.targetScope}</div>
                    </td>
                    <td className="py-3 px-3">
                      <div className="font-mono text-[11px]">
                        <span className="text-neutral-400">{item.previousStock}</span> &rarr;{' '}
                        <strong className="text-neutral-900 dark:text-white">{item.resultingStock}</strong>
                      </div>
                      {item.reservedChange !== undefined && item.reservedChange !== 0 && (
                        <div className="text-[10px] text-amber-600">
                          Reserved: {item.reservedChange > 0 ? `+${item.reservedChange}` : item.reservedChange}
                        </div>
                      )}
                      {item.availableChange !== undefined && item.availableChange !== 0 && (
                        <div className="text-[10px] text-emerald-600">
                          Available: {item.availableChange > 0 ? `+${item.availableChange}` : item.availableChange}
                        </div>
                      )}
                    </td>
                    <td className="py-3 px-3">
                      <div className="text-neutral-800 dark:text-neutral-200 font-medium">{item.operator}</div>
                    </td>
                    <td className="py-3 px-3">
                      <div className="text-[11px] text-neutral-600 dark:text-neutral-400 truncate max-w-[200px]" title={item.reason}>
                        {item.reason}
                      </div>
                      {item.referenceId && (
                        <div className="font-mono text-[10px] text-neutral-400">{item.referenceId}</div>
                      )}
                    </td>
                    <td className="py-3 px-3 text-right">
                      <button
                        type="button"
                        onClick={() => setInspectingItem(item)}
                        className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800 transition cursor-pointer"
                        title="View audit event payload"
                      >
                        <Eye className="w-4 h-4" />
                      </button>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>

      {/* Inspection Drawer */}
      {inspectingItem && (
        <div className="fixed inset-0 z-50 overflow-y-auto bg-neutral-950/60 backdrop-blur-xs flex items-center justify-end animate-in fade-in duration-150">
          <div className="bg-white dark:bg-neutral-900 w-full max-w-md h-full shadow-2xl border-l border-neutral-200 dark:border-neutral-800 flex flex-col p-6 space-y-5 overflow-y-auto">
            <div className="flex items-center justify-between border-b border-neutral-100 dark:border-neutral-800 pb-3">
              <div className="flex items-center gap-2">
                <FileText className="w-5 h-5 text-neutral-400" />
                <h4 className="text-sm font-bold text-neutral-900 dark:text-white">Audit Event Details</h4>
              </div>
              <button
                type="button"
                onClick={() => setInspectingItem(null)}
                className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 cursor-pointer"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="space-y-3 text-xs">
              <div>
                <span className="text-neutral-400 block text-[10px] uppercase font-bold">Action Type</span>
                <span className="font-semibold text-neutral-900 dark:text-white">
                  {getActionBadge(inspectingItem.actionType).label}
                </span>
              </div>

              <div>
                <span className="text-neutral-400 block text-[10px] uppercase font-bold">Timestamp</span>
                <span className="font-mono text-neutral-800 dark:text-neutral-200">
                  {new Date(inspectingItem.timestamp).toLocaleString()}
                </span>
              </div>

              <div>
                <span className="text-neutral-400 block text-[10px] uppercase font-bold">Product & SKU</span>
                <div className="font-mono font-bold text-neutral-900 dark:text-white">{inspectingItem.masterSku}</div>
                <div className="text-neutral-700 dark:text-neutral-300">{inspectingItem.productName}</div>
              </div>

              <div>
                <span className="text-neutral-400 block text-[10px] uppercase font-bold">Target Scope / Store</span>
                <span className="font-semibold text-neutral-900 dark:text-white">{inspectingItem.targetScope}</span>
              </div>

              <div className="p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700 space-y-1">
                <div className="flex justify-between">
                  <span className="text-neutral-500">Stock Before:</span>
                  <span className="font-mono font-bold text-neutral-700 dark:text-neutral-300">{inspectingItem.previousStock}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-neutral-500">Stock After:</span>
                  <span className="font-mono font-bold text-neutral-900 dark:text-white">{inspectingItem.resultingStock}</span>
                </div>
                {inspectingItem.reservedChange !== undefined && (
                  <div className="flex justify-between text-amber-600">
                    <span>Reserved Change:</span>
                    <span className="font-mono font-bold">
                      {inspectingItem.reservedChange > 0 ? `+${inspectingItem.reservedChange}` : inspectingItem.reservedChange}
                    </span>
                  </div>
                )}
                {inspectingItem.availableChange !== undefined && (
                  <div className="flex justify-between text-emerald-600">
                    <span>Available Change:</span>
                    <span className="font-mono font-bold">
                      {inspectingItem.availableChange > 0 ? `+${inspectingItem.availableChange}` : inspectingItem.availableChange}
                    </span>
                  </div>
                )}
              </div>

              <div>
                <span className="text-neutral-400 block text-[10px] uppercase font-bold">Operator</span>
                <span className="font-semibold text-neutral-900 dark:text-white">{inspectingItem.operator}</span>
              </div>

              <div>
                <span className="text-neutral-400 block text-[10px] uppercase font-bold">Audit Reason</span>
                <p className="text-neutral-700 dark:text-neutral-300">{inspectingItem.reason}</p>
              </div>

              {inspectingItem.referenceId && (
                <div>
                  <span className="text-neutral-400 block text-[10px] uppercase font-bold">Reference Identifier</span>
                  <span className="font-mono font-bold text-neutral-800 dark:text-neutral-200">{inspectingItem.referenceId}</span>
                </div>
              )}

              {inspectingItem.detailsPayload && (
                <div>
                  <span className="text-neutral-400 block text-[10px] uppercase font-bold mb-1">Payload Audit Inspection</span>
                  <pre className="p-3 rounded-xl bg-neutral-950 text-neutral-200 font-mono text-[11px] border border-neutral-800 overflow-x-auto">
                    {JSON.stringify(inspectingItem.detailsPayload, null, 2)}
                  </pre>
                </div>
              )}
            </div>

            <div className="pt-4 border-t border-neutral-100 dark:border-neutral-800">
              <button
                type="button"
                onClick={() => setInspectingItem(null)}
                className="w-full py-2.5 rounded-xl bg-neutral-100 hover:bg-neutral-200 dark:bg-neutral-800 dark:hover:bg-neutral-700 text-neutral-700 dark:text-neutral-300 text-xs font-semibold cursor-pointer"
              >
                Close Details
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
