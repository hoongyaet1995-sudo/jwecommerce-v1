import React from 'react';
import {
  MARKETING_CAMPAIGNS
} from '../../data/reportsData';
import {
  Megaphone,
  TrendingUp,
  Target,
  Sparkles,
  DollarSign,
  Download,
  MousePointerClick,
  Eye,
  CheckCircle2
} from 'lucide-react';

export const MarketingReportView: React.FC<{
  onExport: (reportName: string) => void;
}> = ({ onExport }) => {
  const totalSpend = MARKETING_CAMPAIGNS.reduce((acc, c) => acc + c.spend, 0);
  const totalRevenue = MARKETING_CAMPAIGNS.reduce((acc, c) => acc + c.revenueGenerated, 0);
  const overallRoas = totalRevenue / totalSpend;

  return (
    <div className="space-y-6">
      {/* 1. Marketing Campaign KPIs */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
              Blended Campaign ROAS
            </span>
            <span className="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400">
              <TrendingUp className="w-4 h-4" />
            </span>
          </div>
          <div className="mt-3">
            <span className="text-2xl font-black text-emerald-600 dark:text-emerald-400 font-mono">
              {overallRoas.toFixed(2)}x
            </span>
          </div>
          <p className="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
            RM 1 ad spend yields RM {overallRoas.toFixed(2)} in sales
          </p>
        </div>

        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
              Attributed Ad Revenue
            </span>
            <span className="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
              <DollarSign className="w-4 h-4" />
            </span>
          </div>
          <div className="mt-3">
            <span className="text-2xl font-black text-neutral-900 dark:text-neutral-50 font-mono">
              RM {totalRevenue.toLocaleString()}
            </span>
          </div>
          <p className="mt-1 text-xs text-emerald-600 dark:text-emerald-400 font-semibold">
            From total ad spend of RM {totalSpend.toLocaleString()}
          </p>
        </div>

        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
              Average CPA (Acquisition)
            </span>
            <span className="p-1.5 rounded-lg bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400">
              <Target className="w-4 h-4" />
            </span>
          </div>
          <div className="mt-3">
            <span className="text-2xl font-black text-neutral-900 dark:text-neutral-50 font-mono">
              RM 5.10
            </span>
          </div>
          <p className="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
            Lowest on WhatsApp Broadcast (RM 0.92)
          </p>
        </div>

        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
              Storefront Funnel Conv.
            </span>
            <span className="p-1.5 rounded-lg bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400">
              <Sparkles className="w-4 h-4" />
            </span>
          </div>
          <div className="mt-3">
            <span className="text-2xl font-black text-neutral-900 dark:text-neutral-50 font-mono">
              3.82%
            </span>
          </div>
          <p className="mt-1 text-xs text-emerald-600 dark:text-emerald-400 font-semibold">
            Industry fashion benchmark 2.2%
          </p>
        </div>
      </div>

      {/* 2. Conversion Funnel (Sessions to Checkout) */}
      <div className="p-6 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
        <h3 className="text-base font-bold text-neutral-900 dark:text-neutral-100 pb-3 border-b border-neutral-100 dark:border-neutral-800">
          Storefront E-Commerce Conversion Funnel (Last 30 Days)
        </h3>

        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
          <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200/80 dark:border-neutral-700/80">
            <div className="flex items-center justify-between text-xs text-neutral-500 mb-1">
              <span className="flex items-center gap-1.5"><Eye className="w-3.5 h-3.5 text-blue-500" /> 1. Storefront Visitors</span>
              <span className="font-mono font-bold">100%</span>
            </div>
            <div className="text-xl font-black font-mono text-neutral-900 dark:text-neutral-100">
              37,172
            </div>
            <p className="text-[11px] text-neutral-400 mt-1">Total Unique Web Sessions</p>
          </div>

          <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200/80 dark:border-neutral-700/80">
            <div className="flex items-center justify-between text-xs text-neutral-500 mb-1">
              <span className="flex items-center gap-1.5"><MousePointerClick className="w-3.5 h-3.5 text-indigo-500" /> 2. Added to Cart</span>
              <span className="font-mono font-bold text-indigo-600">12.4%</span>
            </div>
            <div className="text-xl font-black font-mono text-neutral-900 dark:text-neutral-100">
              4,610
            </div>
            <p className="text-[11px] text-neutral-400 mt-1">Cart interaction rate</p>
          </div>

          <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200/80 dark:border-neutral-700/80">
            <div className="flex items-center justify-between text-xs text-neutral-500 mb-1">
              <span className="flex items-center gap-1.5"><Target className="w-3.5 h-3.5 text-amber-500" /> 3. Reached Checkout</span>
              <span className="font-mono font-bold text-amber-600">6.2%</span>
            </div>
            <div className="text-xl font-black font-mono text-neutral-900 dark:text-neutral-100">
              2,305
            </div>
            <p className="text-[11px] text-neutral-400 mt-1">Entered shipping & address</p>
          </div>

          <div className="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/80">
            <div className="flex items-center justify-between text-xs text-emerald-700 dark:text-emerald-400 mb-1 font-bold">
              <span className="flex items-center gap-1.5"><CheckCircle2 className="w-3.5 h-3.5 text-emerald-600" /> 4. Purchased</span>
              <span className="font-mono">3.82%</span>
            </div>
            <div className="text-xl font-black font-mono text-emerald-900 dark:text-emerald-200">
              1,420
            </div>
            <p className="text-[11px] text-emerald-700/80 dark:text-emerald-400/80 mt-1">Completed Web Orders</p>
          </div>
        </div>
      </div>

      {/* 3. Campaign Attribution Table */}
      <div className="p-6 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-neutral-100 dark:border-neutral-800">
          <div>
            <h3 className="text-base font-bold text-neutral-900 dark:text-neutral-100">
              Paid Campaign Attribution & ROAS Analysis
            </h3>
            <p className="text-xs text-neutral-500 dark:text-neutral-400">
              Track multi-touch conversion attribution, customer acquisition costs (CPA), and return on ad spend.
            </p>
          </div>
          <button
            id="btn-export-marketing-report"
            onClick={() => onExport('Marketing-Attribution-Report')}
            className="p-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 text-xs font-semibold flex items-center gap-1.5 shadow-2xs"
          >
            <Download className="w-3.5 h-3.5" />
            <span>Export Marketing CSV</span>
          </button>
        </div>

        <div className="overflow-x-auto mt-4">
          <table className="w-full text-left text-xs">
            <thead>
              <tr className="border-b border-neutral-100 dark:border-neutral-800 text-neutral-400 dark:text-neutral-500">
                <th className="py-2.5 px-3 font-semibold">Campaign Name</th>
                <th className="py-2.5 px-3 font-semibold">Ad Channel</th>
                <th className="py-2.5 px-3 font-semibold text-right">Spend (RM)</th>
                <th className="py-2.5 px-3 font-semibold text-right">Clicks</th>
                <th className="py-2.5 px-3 font-semibold text-right">Conversions</th>
                <th className="py-2.5 px-3 font-semibold text-right">Revenue (RM)</th>
                <th className="py-2.5 px-3 font-semibold text-right">CPA (RM)</th>
                <th className="py-2.5 px-3 font-semibold text-right">ROAS</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
              {MARKETING_CAMPAIGNS.map(c => (
                <tr key={c.campaignName} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/40">
                  <td className="py-3 px-3 font-bold text-neutral-900 dark:text-neutral-100">
                    {c.campaignName}
                  </td>
                  <td className="py-3 px-3 text-neutral-600 dark:text-neutral-400">
                    <span className="px-2 py-0.5 rounded-md bg-neutral-100 dark:bg-neutral-800 text-[11px]">
                      {c.channel}
                    </span>
                  </td>
                  <td className="py-3 px-3 text-right font-mono text-neutral-600 dark:text-neutral-400">
                    RM {c.spend.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                  </td>
                  <td className="py-3 px-3 text-right font-mono text-neutral-600 dark:text-neutral-400">
                    {c.clicks.toLocaleString()}
                  </td>
                  <td className="py-3 px-3 text-right font-mono font-bold text-neutral-900 dark:text-neutral-100">
                    {c.conversions.toLocaleString()}
                  </td>
                  <td className="py-3 px-3 text-right font-mono font-bold text-indigo-600 dark:text-indigo-400">
                    RM {c.revenueGenerated.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                  </td>
                  <td className="py-3 px-3 text-right font-mono font-medium text-neutral-800 dark:text-neutral-200">
                    RM {c.cpa.toFixed(2)}
                  </td>
                  <td className="py-3 px-3 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">
                    {c.roas.toFixed(2)}x
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
