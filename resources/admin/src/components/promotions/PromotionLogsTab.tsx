import React, { useState } from 'react';
import { 
  History, 
  Search, 
  Download, 
  CheckCircle2, 
  Clock, 
  RefreshCcw, 
  TrendingUp, 
  Tag, 
  Coins, 
  Filter
} from 'lucide-react';
import { PromotionUsageLog } from '../../types/promotions';

interface Props {
  usages: PromotionUsageLog[];
}

export const PromotionLogsTab: React.FC<Props> = ({ usages }) => {
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');

  const filteredUsages = usages.filter(log => {
    const matchesSearch = log.promotionName.toLowerCase().includes(searchQuery.toLowerCase()) ||
      log.orderNumber.toLowerCase().includes(searchQuery.toLowerCase()) ||
      log.customerName.toLowerCase().includes(searchQuery.toLowerCase()) ||
      (log.couponCode && log.couponCode.toLowerCase().includes(searchQuery.toLowerCase()));
    const matchesStatus = statusFilter === 'all' || log.status === statusFilter;
    return matchesSearch && matchesStatus;
  });

  return (
    <div className="space-y-5">
      {/* Search and Filters */}
      <div className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-3">
        <div className="relative flex-1 w-full max-w-md">
          <Search className="w-4 h-4 text-neutral-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            placeholder="Search by Order #, Customer, or Voucher Code..."
            className="w-full pl-9.5 pr-4 py-2 rounded-xl bg-neutral-50 dark:bg-neutral-800/80 border border-neutral-200 dark:border-neutral-700 text-xs text-neutral-900 dark:text-white"
          />
        </div>

        <div className="flex items-center gap-2 w-full sm:w-auto">
          <select
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
            className="px-3 py-2 rounded-xl bg-neutral-50 dark:bg-neutral-800/80 border border-neutral-200 dark:border-neutral-700 text-xs text-neutral-900 dark:text-white"
          >
            <option value="all">All Audit Status</option>
            <option value="settled">Settled (Completed)</option>
            <option value="applied">Applied (In Checkout)</option>
            <option value="reverted">Reverted / Cancelled</option>
          </select>
        </div>
      </div>

      {/* Usage Table */}
      <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 overflow-hidden shadow-xs">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-neutral-50 dark:bg-neutral-800/50 border-b border-neutral-200 dark:border-neutral-800 text-neutral-500 dark:text-neutral-400">
              <tr>
                <th className="px-5 py-3 font-semibold">Order / Reference</th>
                <th className="px-5 py-3 font-semibold">Customer</th>
                <th className="px-5 py-3 font-semibold">Promotion / Campaign</th>
                <th className="px-5 py-3 font-semibold">Voucher Code</th>
                <th className="px-5 py-3 font-semibold text-right">Discount Saved</th>
                <th className="px-5 py-3 font-semibold text-right">Coins Issued</th>
                <th className="px-5 py-3 font-semibold text-center">Status</th>
                <th className="px-5 py-3 font-semibold text-right">Timestamp</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800 text-neutral-700 dark:text-neutral-300">
              {filteredUsages.map((log) => (
                <tr key={log.id} className="hover:bg-neutral-50/50 dark:hover:bg-neutral-800/40 transition">
                  <td className="px-5 py-3.5 font-bold font-mono text-indigo-600 dark:text-indigo-400">
                    {log.orderNumber}
                  </td>
                  <td className="px-5 py-3.5">
                    <div className="font-semibold text-neutral-900 dark:text-white">{log.customerName}</div>
                    <div className="text-[11px] text-neutral-400">{log.customerEmail}</div>
                  </td>
                  <td className="px-5 py-3.5 font-medium max-w-[220px] truncate">
                    {log.promotionName}
                  </td>
                  <td className="px-5 py-3.5 font-mono font-semibold">
                    {log.couponCode ? (
                      <span className="px-2 py-0.5 rounded bg-neutral-100 dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200">
                        {log.couponCode}
                      </span>
                    ) : (
                      <span className="text-neutral-400">—</span>
                    )}
                  </td>
                  <td className="px-5 py-3.5 text-right font-bold text-emerald-600 dark:text-emerald-400">
                    {log.discountAmount > 0 ? `RM ${log.discountAmount.toFixed(2)}` : '—'}
                  </td>
                  <td className="px-5 py-3.5 text-right font-bold text-cyan-600 dark:text-cyan-400">
                    {log.cashbackCoinsIssued > 0 ? `+${log.cashbackCoinsIssued}` : '—'}
                  </td>
                  <td className="px-5 py-3.5 text-center">
                    <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${
                      log.status === 'settled'
                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-500/30'
                        : log.status === 'applied'
                        ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-500/30'
                        : 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-500/30'
                    }`}>
                      {log.status.toUpperCase()}
                    </span>
                  </td>
                  <td className="px-5 py-3.5 text-right text-neutral-400 text-[11px]">
                    {new Date(log.createdAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
};
