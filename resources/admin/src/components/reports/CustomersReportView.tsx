import React from 'react';
import {
  CUSTOMER_COHORT_MATRIX,
  RFM_SEGMENTS
} from '../../data/reportsData';
import {
  Users,
  Award,
  HeartHandshake,
  UserCheck,
  AlertOctagon,
  Download,
  Flame,
  ArrowRight
} from 'lucide-react';

export const CustomersReportView: React.FC<{
  onExport: (reportName: string) => void;
}> = ({ onExport }) => {
  return (
    <div className="space-y-6">
      {/* 1. Customer Intelligence Summary KPIs */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
              Total Customer Database
            </span>
            <span className="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
              <Users className="w-4 h-4" />
            </span>
          </div>
          <div className="mt-3">
            <span className="text-2xl font-black text-neutral-900 dark:text-neutral-50 font-mono">
              3,445 Buyers
            </span>
          </div>
          <p className="mt-1 text-xs text-emerald-600 dark:text-emerald-400 font-semibold">
            +980 new customers added this month
          </p>
        </div>

        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
              Average Customer LTV
            </span>
            <span className="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400">
              <Award className="w-4 h-4" />
            </span>
          </div>
          <div className="mt-3">
            <span className="text-2xl font-black text-neutral-900 dark:text-neutral-50 font-mono">
              RM 112.40
            </span>
          </div>
          <p className="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
            Top 10% VIP Champions average RM 285.50
          </p>
        </div>

        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
              Repeat Purchase Ratio
            </span>
            <span className="p-1.5 rounded-lg bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400">
              <HeartHandshake className="w-4 h-4" />
            </span>
          </div>
          <div className="mt-3">
            <span className="text-2xl font-black text-neutral-900 dark:text-neutral-50 font-mono">
              34.2%
            </span>
          </div>
          <p className="mt-1 text-xs text-emerald-600 dark:text-emerald-400 font-semibold">
            Strong loyalty in streetwear segment
          </p>
        </div>

        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
              VIP Champions Count
            </span>
            <span className="p-1.5 rounded-lg bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400">
              <Flame className="w-4 h-4" />
            </span>
          </div>
          <div className="mt-3">
            <span className="text-2xl font-black text-neutral-900 dark:text-neutral-50 font-mono">
              385 VIPs
            </span>
          </div>
          <p className="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
            Generates 29.8% of total gross revenue
          </p>
        </div>
      </div>

      {/* 2. RFM Customer Segmentation & Automated Playbook Matrix */}
      <div className="p-6 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-neutral-100 dark:border-neutral-800">
          <div>
            <h3 className="text-base font-bold text-neutral-900 dark:text-neutral-100">
              RFM (Recency, Frequency, Monetary) Customer Segmentation
            </h3>
            <p className="text-xs text-neutral-500 dark:text-neutral-400">
              Automated behavioral cohort classification with personalized retention strategies.
            </p>
          </div>
          <button
            id="btn-export-rfm-cohort"
            onClick={() => onExport('RFM-Customer-Segments')}
            className="p-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 text-xs font-semibold flex items-center gap-1.5 shadow-2xs"
          >
            <Download className="w-3.5 h-3.5" />
            <span>Export RFM Matrix</span>
          </button>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-6">
          {RFM_SEGMENTS.map(rfm => (
            <div
              key={rfm.segment}
              className="p-5 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200/80 dark:border-neutral-700/80 flex flex-col justify-between"
            >
              <div>
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <span className="w-3 h-3 rounded-full" style={{ backgroundColor: rfm.color }} />
                    <h4 className="text-xs font-bold text-neutral-900 dark:text-neutral-100">
                      {rfm.segment}
                    </h4>
                  </div>
                  <span className="text-[11px] font-mono font-bold text-neutral-600 dark:text-neutral-400">
                    {rfm.customerCount} ({rfm.percentOfTotal}%)
                  </span>
                </div>

                <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-2">
                  {rfm.description}
                </p>

                <div className="mt-4 pt-3 border-t border-neutral-200/60 dark:border-neutral-700/60 flex items-center justify-between text-xs font-mono">
                  <div>
                    <span className="text-[10px] text-neutral-400 block uppercase">Avg LTV</span>
                    <span className="font-bold text-neutral-900 dark:text-neutral-100">RM {rfm.avgLTV.toFixed(2)}</span>
                  </div>
                  <div className="text-right">
                    <span className="text-[10px] text-neutral-400 block uppercase">Total GMV</span>
                    <span className="font-bold text-indigo-600 dark:text-indigo-400">RM {rfm.totalRevenue.toLocaleString()}</span>
                  </div>
                </div>
              </div>

              <div className="mt-4 pt-3 border-t border-neutral-200/60 dark:border-neutral-700/60">
                <div className="text-[11px] font-bold text-neutral-700 dark:text-neutral-300 flex items-center gap-1 mb-1">
                  <ArrowRight className="w-3 h-3 text-indigo-500" />
                  <span>Action Playbook:</span>
                </div>
                <p className="text-[11px] text-neutral-600 dark:text-neutral-400 italic">
                  {rfm.recommendedAction}
                </p>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* 3. Customer Cohort Retention Matrix Heatmap */}
      <div className="p-6 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
        <div className="flex items-center justify-between pb-4 border-b border-neutral-100 dark:border-neutral-800">
          <div>
            <h3 className="text-base font-bold text-neutral-900 dark:text-neutral-100">
              Customer Retention Cohort Heatmap
            </h3>
            <p className="text-xs text-neutral-500 dark:text-neutral-400">
              Monthly acquisition cohort retention rates across 6 months of active purchasing.
            </p>
          </div>
          <span className="text-xs font-mono px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 font-bold border border-emerald-200 dark:border-emerald-800">
            Avg M1 Retention: 38.1%
          </span>
        </div>

        <div className="overflow-x-auto mt-4">
          <table className="w-full text-left text-xs">
            <thead>
              <tr className="border-b border-neutral-100 dark:border-neutral-800 text-neutral-400 dark:text-neutral-500 font-mono">
                <th className="py-2.5 px-3 font-semibold">Cohort Month</th>
                <th className="py-2.5 px-3 font-semibold text-right">New Users</th>
                <th className="py-2.5 px-3 font-semibold text-center">Month 0</th>
                <th className="py-2.5 px-3 font-semibold text-center">Month 1</th>
                <th className="py-2.5 px-3 font-semibold text-center">Month 2</th>
                <th className="py-2.5 px-3 font-semibold text-center">Month 3</th>
                <th className="py-2.5 px-3 font-semibold text-center">Month 4</th>
                <th className="py-2.5 px-3 font-semibold text-center">Month 5</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800 font-mono">
              {CUSTOMER_COHORT_MATRIX.map(c => (
                <tr key={c.cohortMonth} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/40">
                  <td className="py-3 px-3 font-bold text-neutral-900 dark:text-neutral-100">
                    {c.cohortMonth}
                  </td>
                  <td className="py-3 px-3 text-right font-medium text-neutral-600 dark:text-neutral-400">
                    {c.initialCustomers} users
                  </td>
                  <td className="py-3 px-3 text-center bg-indigo-500/20 font-bold text-indigo-600 dark:text-indigo-400">
                    100%
                  </td>
                  <td className={`py-3 px-3 text-center ${c.m1 > 0 ? 'bg-indigo-500/15 font-bold text-neutral-800 dark:text-neutral-200' : 'text-neutral-300 dark:text-neutral-700'}`}>
                    {c.m1 > 0 ? `${c.m1}%` : '-'}
                  </td>
                  <td className={`py-3 px-3 text-center ${c.m2 > 0 ? 'bg-indigo-500/10 font-bold text-neutral-800 dark:text-neutral-200' : 'text-neutral-300 dark:text-neutral-700'}`}>
                    {c.m2 > 0 ? `${c.m2}%` : '-'}
                  </td>
                  <td className={`py-3 px-3 text-center ${c.m3 > 0 ? 'bg-indigo-500/10 font-bold text-neutral-800 dark:text-neutral-200' : 'text-neutral-300 dark:text-neutral-700'}`}>
                    {c.m3 > 0 ? `${c.m3}%` : '-'}
                  </td>
                  <td className={`py-3 px-3 text-center ${c.m4 > 0 ? 'bg-indigo-500/5 font-bold text-neutral-800 dark:text-neutral-200' : 'text-neutral-300 dark:text-neutral-700'}`}>
                    {c.m4 > 0 ? `${c.m4}%` : '-'}
                  </td>
                  <td className={`py-3 px-3 text-center ${c.m5 > 0 ? 'bg-indigo-500/5 font-bold text-neutral-800 dark:text-neutral-200' : 'text-neutral-300 dark:text-neutral-700'}`}>
                    {c.m5 > 0 ? `${c.m5}%` : '-'}
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
