import React from 'react';
import {
  COURIER_SLA_METRICS
} from '../../data/reportsData';
import {
  Truck,
  CheckCircle2,
  AlertTriangle,
  Scale,
  DollarSign,
  Download,
  ShieldCheck,
  Building2
} from 'lucide-react';

export const LogisticsReportView: React.FC<{
  onExport: (reportName: string) => void;
}> = ({ onExport }) => {
  return (
    <div className="space-y-6">
      {/* 1. Logistics KPIs */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
              Network On-Time SLA
            </span>
            <span className="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400">
              <CheckCircle2 className="w-4 h-4" />
            </span>
          </div>
          <div className="mt-3">
            <span className="text-2xl font-black text-neutral-900 dark:text-neutral-50 font-mono">
              97.2%
            </span>
          </div>
          <p className="mt-1 text-xs text-emerald-600 dark:text-emerald-400 font-semibold">
            Across 3,454 dispatched consignments
          </p>
        </div>

        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
              Avg Peninsular Transit
            </span>
            <span className="p-1.5 rounded-lg bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400">
              <Truck className="w-4 h-4" />
            </span>
          </div>
          <div className="mt-3">
            <span className="text-2xl font-black text-neutral-900 dark:text-neutral-50 font-mono">
              1.42 Days
            </span>
          </div>
          <p className="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
            Next-day delivery in 82% of Klang Valley
          </p>
        </div>

        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
              Avg Cost Per Parcel
            </span>
            <span className="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
              <DollarSign className="w-4 h-4" />
            </span>
          </div>
          <div className="mt-3">
            <span className="text-2xl font-black text-neutral-900 dark:text-neutral-50 font-mono">
              RM 6.84
            </span>
          </div>
          <p className="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
            Direct API Negotiated Commercial Tier
          </p>
        </div>

        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
              Weight Discrepancy Total
            </span>
            <span className="p-1.5 rounded-lg bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400">
              <Scale className="w-4 h-4" />
            </span>
          </div>
          <div className="mt-3">
            <span className="text-2xl font-black text-amber-600 dark:text-amber-400 font-mono">
              RM 202.90
            </span>
          </div>
          <p className="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
            32 parcels flagged by courier scales
          </p>
        </div>
      </div>

      {/* 2. Courier SLA & Cost Audit Table */}
      <div className="p-6 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-neutral-100 dark:border-neutral-800">
          <div>
            <h3 className="text-base font-bold text-neutral-900 dark:text-neutral-100">
              Malaysian Courier SLA Performance & Billing Audit
            </h3>
            <p className="text-xs text-neutral-500 dark:text-neutral-400">
              Cross-carrier comparison of delivery punctuality, volumetric weight audits, and COD remittance accuracy.
            </p>
          </div>
          <button
            id="btn-export-courier-sla"
            onClick={() => onExport('Courier-SLA-Audit')}
            className="p-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 text-xs font-semibold flex items-center gap-1.5 shadow-2xs"
          >
            <Download className="w-3.5 h-3.5" />
            <span>Export Courier SLA</span>
          </button>
        </div>

        <div className="overflow-x-auto mt-4">
          <table className="w-full text-left text-xs">
            <thead>
              <tr className="border-b border-neutral-100 dark:border-neutral-800 text-neutral-400 dark:text-neutral-500">
                <th className="py-2.5 px-3 font-semibold">Courier Partner</th>
                <th className="py-2.5 px-3 font-semibold text-right">Parcels Handled</th>
                <th className="py-2.5 px-3 font-semibold text-right">On-Time SLA %</th>
                <th className="py-2.5 px-3 font-semibold text-right">Avg Transit Time</th>
                <th className="py-2.5 px-3 font-semibold text-right">First Attempt %</th>
                <th className="py-2.5 px-3 font-semibold text-right">Avg Freight Cost</th>
                <th className="py-2.5 px-3 font-semibold text-right">Weight Surcharge</th>
                <th className="py-2.5 px-3 font-semibold text-right">COD Accuracy</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
              {COURIER_SLA_METRICS.map(c => (
                <tr key={c.courierCode} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/40">
                  <td className="py-3 px-3 font-bold text-neutral-900 dark:text-neutral-100 flex items-center gap-2">
                    <Building2 className="w-4 h-4 text-neutral-400" />
                    <span>{c.courierName}</span>
                  </td>
                  <td className="py-3 px-3 text-right font-mono font-medium text-neutral-600 dark:text-neutral-400">
                    {c.totalParcels.toLocaleString()}
                  </td>
                  <td className="py-3 px-3 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">
                    {c.onTimePercent}%
                  </td>
                  <td className="py-3 px-3 text-right font-mono text-neutral-700 dark:text-neutral-300">
                    {c.avgTransitDays} Days
                  </td>
                  <td className="py-3 px-3 text-right font-mono text-neutral-700 dark:text-neutral-300">
                    {c.firstAttemptSuccessPercent}%
                  </td>
                  <td className="py-3 px-3 text-right font-mono font-bold text-neutral-900 dark:text-neutral-100">
                    RM {c.avgCostPerParcel.toFixed(2)}
                  </td>
                  <td className="py-3 px-3 text-right font-mono text-amber-600 dark:text-amber-400 font-medium">
                    RM {c.weightDiscrepancyAmountRM.toFixed(2)} ({c.weightDiscrepancyCount} bills)
                  </td>
                  <td className="py-3 px-3 text-right font-mono font-bold text-indigo-600 dark:text-indigo-400">
                    {c.codCollectionAccuracy}%
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
