import React from 'react';
import {
  ORDER_FUNNEL_STAGES,
  PAYMENT_GATEWAY_FEES_REPORT
} from '../../data/reportsData';
import {
  Clock,
  CheckCircle2,
  AlertCircle,
  Truck,
  RotateCcw,
  Banknote,
  Download,
  ChevronRight,
  ShieldCheck
} from 'lucide-react';

export const OrdersReportView: React.FC<{
  onExport: (reportName: string) => void;
}> = ({ onExport }) => {
  return (
    <div className="space-y-6">
      {/* 1. Fulfillment KPI Summary */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
              Avg Order-to-Pack Time
            </span>
            <span className="p-1.5 rounded-lg bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400">
              <Clock className="w-4 h-4" />
            </span>
          </div>
          <div className="mt-3">
            <span className="text-2xl font-black text-neutral-900 dark:text-neutral-50 font-mono">
              2.4 Hours
            </span>
          </div>
          <p className="mt-1 text-xs text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1">
            <span>-35 mins faster than target SLA</span>
          </p>
        </div>

        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
              Avg Doorstep Delivery
            </span>
            <span className="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400">
              <Truck className="w-4 h-4" />
            </span>
          </div>
          <div className="mt-3">
            <span className="text-2xl font-black text-neutral-900 dark:text-neutral-50 font-mono">
              1.4 Days
            </span>
          </div>
          <p className="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
            Peninsular Malaysia (Pos Laju / Ninja)
          </p>
        </div>

        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
              Return / RMA Rate
            </span>
            <span className="p-1.5 rounded-lg bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400">
              <RotateCcw className="w-4 h-4" />
            </span>
          </div>
          <div className="mt-3">
            <span className="text-2xl font-black text-neutral-900 dark:text-neutral-50 font-mono">
              1.42%
            </span>
          </div>
          <p className="mt-1 text-xs text-emerald-600 dark:text-emerald-400 font-semibold">
            Industry Benchmark is 3.5%
          </p>
        </div>

        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
              COD Fulfillment Success
            </span>
            <span className="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
              <Banknote className="w-4 h-4" />
            </span>
          </div>
          <div className="mt-3">
            <span className="text-2xl font-black text-neutral-900 dark:text-neutral-50 font-mono">
              98.2%
            </span>
          </div>
          <p className="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
            460 COD Parcels (RM 18,820 settled)
          </p>
        </div>
      </div>

      {/* 2. Order Funnel & Processing Lifecycle */}
      <div className="p-6 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-neutral-100 dark:border-neutral-800">
          <div>
            <h3 className="text-base font-bold text-neutral-900 dark:text-neutral-100">
              Order Fulfillment Lifecycle & Funnel Efficiency
            </h3>
            <p className="text-xs text-neutral-500 dark:text-neutral-400">
              Track conversion from order checkout auth through warehouse thermal printing, courier dispatch, and final doorstep delivery.
            </p>
          </div>
          <button
            id="btn-export-order-funnel"
            onClick={() => onExport('Order-Fulfillment-Funnel')}
            className="p-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 text-xs font-semibold flex items-center gap-1.5 self-start sm:self-auto shadow-2xs"
          >
            <Download className="w-3.5 h-3.5" />
            <span>Export Pipeline Log</span>
          </button>
        </div>

        <div className="mt-6 space-y-4">
          {ORDER_FUNNEL_STAGES.map((stage, idx) => (
            <div
              key={stage.stage}
              className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200/80 dark:border-neutral-700/80 flex flex-col md:flex-row md:items-center justify-between gap-4 hover:bg-neutral-100/60 dark:hover:bg-neutral-800 transition-all"
            >
              <div className="flex items-center gap-3">
                <div className="w-7 h-7 rounded-lg bg-indigo-600 text-white font-bold text-xs flex items-center justify-center font-mono">
                  {idx + 1}
                </div>
                <div>
                  <h4 className="text-xs font-bold text-neutral-900 dark:text-neutral-100">
                    {stage.stage}
                  </h4>
                  <p className="text-[11px] text-neutral-500 dark:text-neutral-400">
                    Avg Duration: <span className="font-mono font-medium">{stage.avgTimeSpentHours > 0 ? `${stage.avgTimeSpentHours} Hours` : 'Completed'}</span>
                  </p>
                </div>
              </div>

              <div className="flex items-center gap-6">
                <div className="w-48 hidden lg:block">
                  <div className="flex justify-between text-[11px] font-mono text-neutral-500 mb-1">
                    <span>Funnel Retention</span>
                    <span className="font-bold text-neutral-800 dark:text-neutral-200">{stage.percentage}%</span>
                  </div>
                  <div className="w-full h-2 rounded-full bg-neutral-200 dark:bg-neutral-700 overflow-hidden">
                    <div
                      className="h-full rounded-full bg-indigo-600 dark:bg-indigo-400"
                      style={{ width: `${stage.percentage}%` }}
                    />
                  </div>
                </div>

                <div className="text-right min-w-[90px]">
                  <span className="text-sm font-black font-mono text-neutral-900 dark:text-neutral-100">
                    {stage.count.toLocaleString()}
                  </span>
                  <span className="block text-[10px] text-neutral-400">Parcels</span>
                </div>

                {stage.dropOffRate !== undefined && stage.dropOffRate > 0 && (
                  <span className="text-[11px] px-2 py-0.5 rounded-md font-mono font-bold bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800">
                    -{stage.dropOffRate}% drop
                  </span>
                )}
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* 3. Return & RMA Reason Diagnostics */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div className="p-6 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <h3 className="text-base font-bold text-neutral-900 dark:text-neutral-100 pb-3 border-b border-neutral-100 dark:border-neutral-800">
            Top Return & RMA Reasons
          </h3>
          <div className="mt-4 space-y-3">
            {[
              { reason: 'Customer requested size exchange (Oversized fit preference)', count: 28, percent: 56.0 },
              { reason: 'Incorrect color variant chosen at checkout', count: 12, percent: 24.0 },
              { reason: 'Courier packaging damaged in transit (Replaced free)', count: 6, percent: 12.0 },
              { reason: 'Refused at door on COD delivery', count: 4, percent: 8.0 },
            ].map(r => (
              <div key={r.reason} className="p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-100 dark:border-neutral-800">
                <div className="flex justify-between text-xs font-semibold mb-1">
                  <span className="text-neutral-800 dark:text-neutral-200">{r.reason}</span>
                  <span className="font-mono text-neutral-900 dark:text-neutral-100">{r.count} cases ({r.percent}%)</span>
                </div>
                <div className="w-full h-1.5 rounded-full bg-neutral-200 dark:bg-neutral-700 overflow-hidden">
                  <div className="h-full bg-amber-500 rounded-full" style={{ width: `${r.percent}%` }} />
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Cash on Delivery Remittance Status */}
        <div className="p-6 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <h3 className="text-base font-bold text-neutral-900 dark:text-neutral-100 pb-3 border-b border-neutral-100 dark:border-neutral-800">
            COD Remittance & Cash Collection SLA
          </h3>
          <div className="mt-4 space-y-4">
            <div className="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/80">
              <div className="flex items-center justify-between">
                <span className="text-xs font-bold text-emerald-800 dark:text-emerald-300">Pos Malaysia COD Settlement</span>
                <span className="text-xs font-mono font-bold text-emerald-700 dark:text-emerald-400">Settled (RM 11,420.00)</span>
              </div>
              <p className="text-[11px] text-emerald-700/80 dark:text-emerald-400/80 mt-1">
                Bank-in reference: MBB-COD-POS-88219 (Credited to Maybank Main Operational Account).
              </p>
            </div>

            <div className="p-4 rounded-xl bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/80">
              <div className="flex items-center justify-between">
                <span className="text-xs font-bold text-blue-800 dark:text-blue-300">Ninja Van COD Settlement</span>
                <span className="text-xs font-mono font-bold text-blue-700 dark:text-blue-400">Settled (RM 7,023.60)</span>
              </div>
              <p className="text-[11px] text-blue-700/80 dark:text-blue-400/80 mt-1">
                Weekly Friday automated payout completed with zero cash discrepancies.
              </p>
            </div>

            <div className="p-4 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/80">
              <div className="flex items-center justify-between">
                <span className="text-xs font-bold text-amber-800 dark:text-amber-300">Pending Courier Clearance</span>
                <span className="text-xs font-mono font-bold text-amber-700 dark:text-amber-400">Pending (RM 376.40)</span>
              </div>
              <p className="text-[11px] text-amber-700/80 dark:text-amber-400/80 mt-1">
                8 parcels collected yesterday, undergoing courier vault counting.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};
