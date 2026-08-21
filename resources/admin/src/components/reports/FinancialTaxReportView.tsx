import React from 'react';
import {
  MALAYSIA_TAX_REPORT,
  PAYMENT_GATEWAY_FEES_REPORT
} from '../../data/reportsData';
import {
  Receipt,
  CreditCard,
  Building,
  ShieldCheck,
  Download,
  DollarSign,
  Landmark,
  FileSpreadsheet
} from 'lucide-react';

export const FinancialTaxReportView: React.FC<{
  onExport: (reportName: string) => void;
}> = ({ onExport }) => {
  const totalTaxCollected = MALAYSIA_TAX_REPORT.reduce((acc, t) => acc + t.taxCollectedRM, 0);
  const totalTaxableVolume = MALAYSIA_TAX_REPORT.reduce((acc, t) => acc + t.taxableSalesRM, 0);
  const totalPaymentGatewayFees = PAYMENT_GATEWAY_FEES_REPORT.reduce((acc, g) => acc + g.totalFeesDeductedRM, 0);
  const totalSettledNet = PAYMENT_GATEWAY_FEES_REPORT.reduce((acc, g) => acc + g.netSettlementRM, 0);

  return (
    <div className="space-y-6">
      {/* 1. Financial & Tax KPI Row */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
              Total SST Tax Liability
            </span>
            <span className="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
              <Receipt className="w-4 h-4" />
            </span>
          </div>
          <div className="mt-3">
            <span className="text-2xl font-black text-neutral-900 dark:text-neutral-50 font-mono">
              RM {totalTaxCollected.toLocaleString(undefined, { minimumFractionDigits: 2 })}
            </span>
          </div>
          <p className="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
            SST-02 Filing Period: Aug 2026
          </p>
        </div>

        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
              Total Taxable Turnover
            </span>
            <span className="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400">
              <Landmark className="w-4 h-4" />
            </span>
          </div>
          <div className="mt-3">
            <span className="text-2xl font-black text-neutral-900 dark:text-neutral-50 font-mono">
              RM {totalTaxableVolume.toLocaleString(undefined, { minimumFractionDigits: 2 })}
            </span>
          </div>
          <p className="mt-1 text-xs text-emerald-600 dark:text-emerald-400 font-semibold">
            Registered SST No: W10-2401-3200911
          </p>
        </div>

        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
              Total Gateway Fees Paid
            </span>
            <span className="p-1.5 rounded-lg bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400">
              <CreditCard className="w-4 h-4" />
            </span>
          </div>
          <div className="mt-3">
            <span className="text-2xl font-black text-rose-600 dark:text-rose-400 font-mono">
              RM {totalPaymentGatewayFees.toLocaleString(undefined, { minimumFractionDigits: 2 })}
            </span>
          </div>
          <p className="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
            2.84% blended processing rate
          </p>
        </div>

        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
              Net Settled Bank Payout
            </span>
            <span className="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400">
              <DollarSign className="w-4 h-4" />
            </span>
          </div>
          <div className="mt-3">
            <span className="text-2xl font-black text-emerald-600 dark:text-emerald-400 font-mono">
              RM {totalSettledNet.toLocaleString(undefined, { minimumFractionDigits: 2 })}
            </span>
          </div>
          <p className="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
            Deposited into Maybank Corporate Account
          </p>
        </div>
      </div>

      {/* 2. Malaysian SST Tax Category Breakdown */}
      <div className="p-6 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-neutral-100 dark:border-neutral-800">
          <div>
            <h3 className="text-base font-bold text-neutral-900 dark:text-neutral-100">
              Malaysian Sales & Service Tax (SST) Summary (Royal Malaysian Customs)
            </h3>
            <p className="text-xs text-neutral-500 dark:text-neutral-400">
              Official taxable revenue breakdown, tax liability collected, and tax exemption codes for monthly filing.
            </p>
          </div>
          <button
            id="btn-export-sst-report"
            onClick={() => onExport('Customs-SST-Tax-Report')}
            className="p-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 text-xs font-semibold flex items-center gap-1.5 shadow-2xs"
          >
            <FileSpreadsheet className="w-3.5 h-3.5" />
            <span>Export SST-02 Schedule</span>
          </button>
        </div>

        <div className="overflow-x-auto mt-4">
          <table className="w-full text-left text-xs">
            <thead>
              <tr className="border-b border-neutral-100 dark:border-neutral-800 text-neutral-400 dark:text-neutral-500">
                <th className="py-2.5 px-3 font-semibold">Tax Category Description</th>
                <th className="py-2.5 px-3 font-semibold text-center">Tax Rate %</th>
                <th className="py-2.5 px-3 font-semibold text-right">Taxable Volume (RM)</th>
                <th className="py-2.5 px-3 font-semibold text-right">Tax Collected (RM)</th>
                <th className="py-2.5 px-3 font-semibold text-right">Exempt Volume (RM)</th>
                <th className="py-2.5 px-3 font-semibold text-right">Invoices</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
              {MALAYSIA_TAX_REPORT.map(t => (
                <tr key={t.taxCategory} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/40">
                  <td className="py-3 px-3 font-bold text-neutral-900 dark:text-neutral-100">
                    {t.taxCategory}
                  </td>
                  <td className="py-3 px-3 text-center font-mono font-bold text-indigo-600 dark:text-indigo-400">
                    {t.taxRatePercent}%
                  </td>
                  <td className="py-3 px-3 text-right font-mono text-neutral-800 dark:text-neutral-200">
                    RM {t.taxableSalesRM.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                  </td>
                  <td className="py-3 px-3 text-right font-mono font-bold text-neutral-900 dark:text-neutral-100">
                    RM {t.taxCollectedRM.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                  </td>
                  <td className="py-3 px-3 text-right font-mono text-neutral-500">
                    {t.exemptSalesRM > 0 ? `RM ${t.exemptSalesRM.toLocaleString(undefined, { minimumFractionDigits: 2 })}` : '-'}
                  </td>
                  <td className="py-3 px-3 text-right font-mono text-neutral-600 dark:text-neutral-400">
                    {t.invoiceCount.toLocaleString()}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* 3. Payment Gateway Fees & Settlement Schedule */}
      <div className="p-6 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-neutral-100 dark:border-neutral-800">
          <div>
            <h3 className="text-base font-bold text-neutral-900 dark:text-neutral-100">
              Payment Gateway Merchant Fees & Bank Settlement Schedule
            </h3>
            <p className="text-xs text-neutral-500 dark:text-neutral-400">
              MDR fees deducted by payment processors and payout cycle timing.
            </p>
          </div>
          <button
            id="btn-export-gateway-fees"
            onClick={() => onExport('Payment-Gateway-Reconciliation')}
            className="p-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 text-xs font-semibold flex items-center gap-1.5 shadow-2xs"
          >
            <Download className="w-3.5 h-3.5" />
            <span>Export Bank Settlement</span>
          </button>
        </div>

        <div className="overflow-x-auto mt-4">
          <table className="w-full text-left text-xs">
            <thead>
              <tr className="border-b border-neutral-100 dark:border-neutral-800 text-neutral-400 dark:text-neutral-500">
                <th className="py-2.5 px-3 font-semibold">Payment Channel</th>
                <th className="py-2.5 px-3 font-semibold text-right">Transactions</th>
                <th className="py-2.5 px-3 font-semibold text-right">Gross Volume (RM)</th>
                <th className="py-2.5 px-3 font-semibold text-right">Fee Rate (MDR)</th>
                <th className="py-2.5 px-3 font-semibold text-right">Total Fees Deducted</th>
                <th className="py-2.5 px-3 font-semibold text-right">Net Payout (RM)</th>
                <th className="py-2.5 px-3 font-semibold">Payout Cycle</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
              {PAYMENT_GATEWAY_FEES_REPORT.map(g => (
                <tr key={g.channelCode} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/40">
                  <td className="py-3 px-3 font-bold text-neutral-900 dark:text-neutral-100 flex items-center gap-2">
                    <CreditCard className="w-3.5 h-3.5 text-neutral-400" />
                    <span>{g.gatewayName}</span>
                  </td>
                  <td className="py-3 px-3 text-right font-mono text-neutral-600 dark:text-neutral-400">
                    {g.transactionCount.toLocaleString()}
                  </td>
                  <td className="py-3 px-3 text-right font-mono text-neutral-900 dark:text-neutral-100">
                    RM {g.grossVolumeRM.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                  </td>
                  <td className="py-3 px-3 text-right font-mono text-neutral-600 dark:text-neutral-400">
                    {g.feePercentage}% {g.fixedFeeRM > 0 ? `+ RM${g.fixedFeeRM}` : ''}
                  </td>
                  <td className="py-3 px-3 text-right font-mono font-medium text-rose-600 dark:text-rose-400">
                    - RM {g.totalFeesDeductedRM.toFixed(2)}
                  </td>
                  <td className="py-3 px-3 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">
                    RM {g.netSettlementRM.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                  </td>
                  <td className="py-3 px-3 text-neutral-500 font-medium">
                    {g.avgSettlementDays}
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
