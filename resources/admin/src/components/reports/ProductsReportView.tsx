import React, { useState } from 'react';
import {
  PRODUCT_PERFORMANCE_LIST
} from '../../data/reportsData';
import {
  Package,
  AlertTriangle,
  Flame,
  CheckCircle,
  TrendingDown,
  Download,
  Search,
  Filter,
  Layers
} from 'lucide-react';

export const ProductsReportView: React.FC<{
  onExport: (reportName: string) => void;
}> = ({ onExport }) => {
  const [searchTerm, setSearchTerm] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('all');
  const [velocityFilter, setVelocityFilter] = useState('all');

  const filteredProducts = PRODUCT_PERFORMANCE_LIST.filter(p => {
    const matchesSearch =
      p.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      p.sku.toLowerCase().includes(searchTerm.toLowerCase());
    const matchesCategory = categoryFilter === 'all' || p.category === categoryFilter;
    const matchesVelocity = velocityFilter === 'all' || p.velocityScore === velocityFilter;
    return matchesSearch && matchesCategory && matchesVelocity;
  });

  const categories = Array.from(new Set(PRODUCT_PERFORMANCE_LIST.map(p => p.category)));

  return (
    <div className="space-y-6">
      {/* 1. Inventory Health Snapshot Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
              Fast Moving SKUs
            </span>
            <span className="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400">
              <Flame className="w-4 h-4" />
            </span>
          </div>
          <div className="mt-3">
            <span className="text-2xl font-black text-neutral-900 dark:text-neutral-50 font-mono">
              4 SKUs
            </span>
          </div>
          <p className="mt-1 text-xs text-emerald-600 dark:text-emerald-400 font-semibold">
            Generates 74.8% of total gross revenue
          </p>
        </div>

        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
              Low Stock Restock Alert
            </span>
            <span className="p-1.5 rounded-lg bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400">
              <AlertTriangle className="w-4 h-4" />
            </span>
          </div>
          <div className="mt-3">
            <span className="text-2xl font-black text-amber-600 dark:text-amber-400 font-mono">
              3 SKUs
            </span>
          </div>
          <p className="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
            Less than 10 days inventory remaining
          </p>
        </div>

        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
              Avg Gross Profit Margin
            </span>
            <span className="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
              <Package className="w-4 h-4" />
            </span>
          </div>
          <div className="mt-3">
            <span className="text-2xl font-black text-neutral-900 dark:text-neutral-50 font-mono">
              60.8%
            </span>
          </div>
          <p className="mt-1 text-xs text-emerald-600 dark:text-emerald-400 font-semibold">
            Direct Apparel Factory Production
          </p>
        </div>

        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
              Dead Stock / Slow Moving
            </span>
            <span className="p-1.5 rounded-lg bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400">
              <TrendingDown className="w-4 h-4" />
            </span>
          </div>
          <div className="mt-3">
            <span className="text-2xl font-black text-rose-600 dark:text-rose-400 font-mono">
              1 SKU
            </span>
          </div>
          <p className="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
            &gt;60 days sales dormancy (Recommend bundle)
          </p>
        </div>
      </div>

      {/* 2. Product Velocity & Profitability Matrix */}
      <div className="p-6 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
        <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-4 border-b border-neutral-100 dark:border-neutral-800">
          <div>
            <h3 className="text-base font-bold text-neutral-900 dark:text-neutral-100">
              SKU Performance, Profitability & Inventory Velocity
            </h3>
            <p className="text-xs text-neutral-500 dark:text-neutral-400">
              Detailed unit sales, COGS, gross margins, current stock level, and days of inventory remaining (DOI).
            </p>
          </div>

          <div className="flex items-center gap-3 flex-wrap">
            <div className="relative min-w-[200px]">
              <Search className="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400" />
              <input
                type="text"
                placeholder="Search SKU or name..."
                value={searchTerm}
                onChange={e => setSearchTerm(e.target.value)}
                className="w-full pl-8.5 pr-3 py-1.5 text-xs rounded-xl bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-1 focus:ring-indigo-500"
              />
            </div>

            <select
              value={categoryFilter}
              onChange={e => setCategoryFilter(e.target.value)}
              className="text-xs px-3 py-1.5 rounded-xl bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300"
            >
              <option value="all">All Categories</option>
              {categories.map(c => (
                <option key={c} value={c}>{c}</option>
              ))}
            </select>

            <select
              value={velocityFilter}
              onChange={e => setVelocityFilter(e.target.value)}
              className="text-xs px-3 py-1.5 rounded-xl bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300"
            >
              <option value="all">All Velocity</option>
              <option value="Fast">Fast Moving</option>
              <option value="Normal">Normal</option>
              <option value="Slow">Slow Moving</option>
              <option value="Dead">Dead Stock</option>
            </select>

            <button
              id="btn-export-products-report"
              onClick={() => onExport('Product-Velocity-Report')}
              className="p-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 text-xs font-semibold flex items-center gap-1.5 shadow-2xs"
            >
              <Download className="w-3.5 h-3.5" />
              <span>Export CSV</span>
            </button>
          </div>
        </div>

        <div className="overflow-x-auto mt-4">
          <table className="w-full text-left text-xs">
            <thead>
              <tr className="border-b border-neutral-100 dark:border-neutral-800 text-neutral-400 dark:text-neutral-500">
                <th className="py-2.5 px-3 font-semibold">SKU & Item Details</th>
                <th className="py-2.5 px-3 font-semibold">Category</th>
                <th className="py-2.5 px-3 font-semibold text-right">Units Sold</th>
                <th className="py-2.5 px-3 font-semibold text-right">Revenue (RM)</th>
                <th className="py-2.5 px-3 font-semibold text-right">Gross Margin</th>
                <th className="py-2.5 px-3 font-semibold text-right">Current Stock</th>
                <th className="py-2.5 px-3 font-semibold text-right">Days of Inv. (DOI)</th>
                <th className="py-2.5 px-3 font-semibold text-center">Velocity</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
              {filteredProducts.map(p => (
                <tr key={p.sku} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/40">
                  <td className="py-3 px-3">
                    <div className="font-bold text-neutral-900 dark:text-neutral-100">{p.name}</div>
                    <div className="text-[11px] font-mono text-neutral-400">{p.sku}</div>
                  </td>
                  <td className="py-3 px-3 text-neutral-600 dark:text-neutral-400">
                    <span className="px-2 py-0.5 rounded-md bg-neutral-100 dark:bg-neutral-800 text-[11px] font-medium">
                      {p.category}
                    </span>
                  </td>
                  <td className="py-3 px-3 text-right font-mono font-bold text-neutral-800 dark:text-neutral-200">
                    {p.unitsSold.toLocaleString()}
                  </td>
                  <td className="py-3 px-3 text-right font-mono font-bold text-indigo-600 dark:text-indigo-400">
                    RM {p.revenue.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                  </td>
                  <td className="py-3 px-3 text-right font-mono text-emerald-600 dark:text-emerald-400 font-bold">
                    {p.grossMarginPercent}%
                  </td>
                  <td className="py-3 px-3 text-right font-mono">
                    <span
                      className={`px-2 py-0.5 rounded-md font-bold text-[11px] ${
                        p.stockStatus === 'low_stock'
                          ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400 border border-amber-200 dark:border-amber-800'
                          : p.stockStatus === 'overstocked'
                          ? 'bg-purple-50 text-purple-700 dark:bg-purple-950/60 dark:text-purple-400 border border-purple-200 dark:border-purple-800'
                          : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800'
                      }`}
                    >
                      {p.currentStock} units
                    </span>
                  </td>
                  <td className="py-3 px-3 text-right font-mono font-medium text-neutral-600 dark:text-neutral-400">
                    {p.daysOfInventory} Days
                  </td>
                  <td className="py-3 px-3 text-center">
                    <span
                      className={`inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold ${
                        p.velocityScore === 'Fast'
                          ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
                          : p.velocityScore === 'Normal'
                          ? 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300'
                          : p.velocityScore === 'Slow'
                          ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'
                          : 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300'
                      }`}
                    >
                      {p.velocityScore}
                    </span>
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
