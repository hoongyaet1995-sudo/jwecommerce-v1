import React, { useState } from 'react';
import { 
  DollarSign, 
  AlertTriangle, 
  CheckCircle2, 
  Scale, 
  Upload, 
  FileText, 
  Search, 
  Filter, 
  ArrowUpRight, 
  Download, 
  FileSpreadsheet, 
  Check, 
  MessageSquareWarning,
  Sparkles,
  RefreshCw,
  Clock,
  Send
} from 'lucide-react';
import { BillingReconciliationRecord, CourierCode } from '../../types/shipping';

interface BillingReconciliationTabProps {
  records: BillingReconciliationRecord[];
  onResolveDispute: (id: string) => void;
}

export const BillingReconciliationTab: React.FC<BillingReconciliationTabProps> = ({
  records,
  onResolveDispute
}) => {
  const [statusFilter, setStatusFilter] = useState<'all' | 'matched' | 'disputed' | 'resolved'>('all');
  const [courierFilter, setCourierFilter] = useState<string>('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedDisputeId, setSelectedDisputeId] = useState<string | null>(null);
  const [disputeModalOpen, setDisputeModalOpen] = useState(false);
  const [disputeNotes, setDisputeNotes] = useState('');
  const [uploadInvoiceSuccess, setUploadInvoiceSuccess] = useState(false);

  // Financial summary metrics
  const totalBilled = records.reduce((sum, r) => sum + r.courierBilledRateRM, 0);
  const totalSystemQuoted = records.reduce((sum, r) => sum + r.systemRateRM, 0);
  const totalVariance = records.reduce((sum, r) => sum + r.varianceRM, 0);
  const totalSst = records.reduce((sum, r) => sum + r.sstTaxRM, 0);
  const disputedCount = records.filter(r => r.reconciliationStatus === 'disputed').length;
  const matchedCount = records.filter(r => r.reconciliationStatus === 'matched').length;

  const filteredRecords = records.filter(record => {
    if (statusFilter !== 'all' && record.reconciliationStatus !== statusFilter) return false;
    if (courierFilter !== 'all' && record.courier !== courierFilter) return false;
    if (searchQuery.trim()) {
      const q = searchQuery.toLowerCase();
      const matchTracking = record.trackingNumber.toLowerCase().includes(q);
      const matchOrder = record.orderNumber.toLowerCase().includes(q);
      const matchInvoice = record.invoiceNumber.toLowerCase().includes(q);
      if (!matchTracking && !matchOrder && !matchInvoice) return false;
    }
    return true;
  });

  const handleOpenDispute = (record: BillingReconciliationRecord) => {
    setSelectedDisputeId(record.id);
    setDisputeNotes(
      `Dispute regarding AWB ${record.trackingNumber} (${record.orderNumber}): System weighed ${record.systemWeightKg}kg vs Courier billed ${record.courierBilledWeightKg}kg. Variance: RM +${record.varianceRM.toFixed(2)}. Requesting dimensional scale photo proof from sorting hub.`
    );
    setDisputeModalOpen(true);
  };

  const handleConfirmDisputeSubmit = () => {
    if (selectedDisputeId) {
      onResolveDispute(selectedDisputeId);
    }
    setDisputeModalOpen(false);
    setSelectedDisputeId(null);
  };

  const handleSimulateInvoiceImport = () => {
    setUploadInvoiceSuccess(true);
    setTimeout(() => {
      setUploadInvoiceSuccess(false);
    }, 4000);
  };

  const getCourierBadge = (courier: CourierCode) => {
    switch (courier) {
      case 'jnt':
        return <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-red-100 dark:bg-red-950/50 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-800">J&T Express</span>;
      case 'ninjavan':
        return <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 dark:bg-rose-950/50 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800">Ninja Van</span>;
      case 'posmalaysia':
        return <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 dark:bg-blue-950/50 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800">Pos Malaysia</span>;
      case 'flashexpress':
        return <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 dark:bg-amber-950/50 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800">Flash Express</span>;
      case 'dhlecommerce':
        return <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-yellow-100 dark:bg-yellow-950/50 text-yellow-800 dark:text-yellow-300 border border-yellow-200 dark:border-yellow-800">DHL eCommerce</span>;
      default:
        return <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-100 dark:bg-purple-950/50 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800">EasyParcel</span>;
    }
  };

  const getDiscrepancyBadge = (type: BillingReconciliationRecord['discrepancyType']) => {
    switch (type) {
      case 'volumetric_surge':
        return (
          <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-100 dark:bg-amber-950/50 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
            <Scale className="w-3 h-3" /> Volumetric Surge
          </span>
        );
      case 'surcharge_mismatch':
        return (
          <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-purple-100 dark:bg-purple-950/50 text-purple-800 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
            <AlertTriangle className="w-3 h-3" /> Remote Surcharge
          </span>
        );
      case 'unregistered_shipment':
        return (
          <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-red-100 dark:bg-red-950/50 text-red-800 dark:text-red-300 border border-red-200 dark:border-red-800">
            <MessageSquareWarning className="w-3 h-3" /> Unregistered AWB
          </span>
        );
      default:
        return (
          <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 dark:bg-emerald-950/50 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
            <Check className="w-3 h-3" /> Rate Matched
          </span>
        );
    }
  };

  return (
    <div className="space-y-6">
      {/* Top Banner: Financial Overview */}
      <div className="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
        <div className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 shadow-sm">
          <div className="flex items-center justify-between text-neutral-500 dark:text-neutral-400">
            <span className="text-xs font-medium">Total Courier Billed</span>
            <div className="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
              <DollarSign className="w-3.5 h-3.5" />
            </div>
          </div>
          <div className="text-2xl font-black font-mono text-neutral-900 dark:text-neutral-100 mt-2">
            RM {totalBilled.toFixed(2)}
          </div>
          <div className="text-[11px] text-neutral-500 dark:text-neutral-400 mt-1">
            Quoted: RM {totalSystemQuoted.toFixed(2)}
          </div>
        </div>

        <div className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 shadow-sm">
          <div className="flex items-center justify-between text-neutral-500 dark:text-neutral-400">
            <span className="text-xs font-medium">Overcharge Variance</span>
            <div className="w-7 h-7 rounded-lg bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center text-amber-600 dark:text-amber-400">
              <AlertTriangle className="w-3.5 h-3.5" />
            </div>
          </div>
          <div className="text-2xl font-black font-mono text-amber-600 dark:text-amber-400 mt-2">
            +RM {totalVariance.toFixed(2)}
          </div>
          <div className="text-[11px] text-amber-600 dark:text-amber-400 mt-1 font-medium">
            {disputedCount} parcels flagged with discrepancies
          </div>
        </div>

        <div className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 shadow-sm">
          <div className="flex items-center justify-between text-neutral-500 dark:text-neutral-400">
            <span className="text-xs font-medium">SST 6% Logistics Tax</span>
            <div className="w-7 h-7 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
              <FileText className="w-3.5 h-3.5" />
            </div>
          </div>
          <div className="text-2xl font-black font-mono text-neutral-900 dark:text-neutral-100 mt-2">
            RM {totalSst.toFixed(2)}
          </div>
          <div className="text-[11px] text-neutral-500 dark:text-neutral-400 mt-1">
            Applicable to freight & surcharges
          </div>
        </div>

        <div className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 shadow-sm">
          <div className="flex items-center justify-between text-neutral-500 dark:text-neutral-400">
            <span className="text-xs font-medium">Reconciliation Rate</span>
            <div className="w-7 h-7 rounded-lg bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400">
              <CheckCircle2 className="w-3.5 h-3.5" />
            </div>
          </div>
          <div className="text-2xl font-black font-mono text-neutral-900 dark:text-neutral-100 mt-2">
            {records.length > 0 ? Math.round((matchedCount / records.length) * 100) : 100}%
          </div>
          <div className="text-[11px] text-neutral-500 dark:text-neutral-400 mt-1">
            {matchedCount} / {records.length} records verified
          </div>
        </div>
      </div>

      {/* CSV Invoice Importer & Action Bar */}
      <div className="p-5 rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div className="space-y-1">
          <h3 className="text-sm font-bold text-neutral-900 dark:text-neutral-100 flex items-center gap-2">
            <FileSpreadsheet className="w-4 h-4 text-emerald-600" />
            Courier Monthly Statement Reconciliation & Weight Discrepancy Audit
          </h3>
          <p className="text-xs text-neutral-500 dark:text-neutral-400">
            Upload electronic monthly invoice reports (.csv / .xlsx) from Pos Malaysia, Ninja Van, or J&T to automatically cross-audit billed weights against warehouse packing scans.
          </p>
        </div>

        <div className="flex items-center gap-2 flex-wrap">
          <button
            onClick={handleSimulateInvoiceImport}
            className="flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white transition-colors cursor-pointer shadow-sm"
          >
            <Upload className="w-3.5 h-3.5" />
            Import Courier CSV Statement
          </button>
          <button
            onClick={() => alert("Exporting all flagged discrepancy records to Excel dispute format (J&T/Ninja Claim Template)...")}
            className="flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 hover:bg-neutral-100 dark:hover:bg-neutral-700 text-neutral-700 dark:text-neutral-200 transition-colors cursor-pointer"
          >
            <Download className="w-3.5 h-3.5" />
            Export Claim Form
          </button>
        </div>
      </div>

      {uploadInvoiceSuccess && (
        <div className="p-3.5 rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 flex items-center gap-2 text-xs font-medium animate-fadeIn">
          <CheckCircle2 className="w-4 h-4 text-emerald-600 dark:text-emerald-400 flex-shrink-0" />
          Statement parsed successfully! Audited 48 airwaybills against store packaging scans: 44 Matched, 4 Discrepancies identified and logged.
        </div>
      )}

      {/* Filter and Search Bar */}
      <div className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 shadow-sm space-y-3">
        <div className="flex flex-col sm:flex-row items-center justify-between gap-3">
          {/* Status Tabs */}
          <div className="flex items-center gap-1.5 p-1 rounded-lg bg-neutral-100 dark:bg-neutral-800 w-full sm:w-auto overflow-x-auto">
            <button
              onClick={() => setStatusFilter('all')}
              className={`px-3 py-1.5 rounded-md text-xs font-medium transition cursor-pointer whitespace-nowrap ${
                statusFilter === 'all'
                  ? 'bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white shadow-xs font-bold'
                  : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white'
              }`}
            >
              All Records ({records.length})
            </button>
            <button
              onClick={() => setStatusFilter('disputed')}
              className={`px-3 py-1.5 rounded-md text-xs font-medium transition cursor-pointer whitespace-nowrap flex items-center gap-1.5 ${
                statusFilter === 'disputed'
                  ? 'bg-amber-500 text-white shadow-xs font-bold'
                  : 'text-neutral-600 dark:text-neutral-400 hover:text-amber-600'
              }`}
            >
              <AlertTriangle className="w-3 h-3" />
              Disputed ({records.filter(r => r.reconciliationStatus === 'disputed').length})
            </button>
            <button
              onClick={() => setStatusFilter('matched')}
              className={`px-3 py-1.5 rounded-md text-xs font-medium transition cursor-pointer whitespace-nowrap flex items-center gap-1.5 ${
                statusFilter === 'matched'
                  ? 'bg-emerald-600 text-white shadow-xs font-bold'
                  : 'text-neutral-600 dark:text-neutral-400 hover:text-emerald-600'
              }`}
            >
              <Check className="w-3 h-3" />
              Matched ({records.filter(r => r.reconciliationStatus === 'matched').length})
            </button>
            <button
              onClick={() => setStatusFilter('resolved')}
              className={`px-3 py-1.5 rounded-md text-xs font-medium transition cursor-pointer whitespace-nowrap ${
                statusFilter === 'resolved'
                  ? 'bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white shadow-xs font-bold'
                  : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white'
              }`}
            >
              Resolved ({records.filter(r => r.reconciliationStatus === 'resolved').length})
            </button>
          </div>

          {/* Courier Selector */}
          <div className="flex items-center gap-2 w-full sm:w-auto">
            <select
              value={courierFilter}
              onChange={(e) => setCourierFilter(e.target.value)}
              className="px-3 py-1.5 text-xs font-medium rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200 focus:outline-none focus:ring-1 focus:ring-indigo-500"
            >
              <option value="all">All Couriers</option>
              <option value="jnt">J&T Express</option>
              <option value="ninjavan">Ninja Van</option>
              <option value="posmalaysia">Pos Malaysia</option>
              <option value="flashexpress">Flash Express</option>
              <option value="dhlecommerce">DHL eCommerce</option>
              <option value="easyparcel">EasyParcel</option>
            </select>

            {/* Search */}
            <div className="relative flex-1 sm:w-64">
              <Search className="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400" />
              <input
                type="text"
                placeholder="Search AWB, Order, Invoice..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="w-full pl-8.5 pr-3 py-1.5 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:outline-none focus:ring-1 focus:ring-indigo-500"
              />
            </div>
          </div>
        </div>
      </div>

      {/* Reconciliation Table */}
      <div className="rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse text-xs">
            <thead>
              <tr className="bg-neutral-50 dark:bg-neutral-800/60 border-b border-neutral-200 dark:border-neutral-800 text-neutral-500 dark:text-neutral-400 font-semibold">
                <th className="py-3 px-4">Invoice / Month</th>
                <th className="py-3 px-4">Tracking Number & Order</th>
                <th className="py-3 px-4">Courier</th>
                <th className="py-3 px-4">Weight (System vs Billed)</th>
                <th className="py-3 px-4">System Rate</th>
                <th className="py-3 px-4">Billed Rate</th>
                <th className="py-3 px-4">Variance / Type</th>
                <th className="py-3 px-4">Status</th>
                <th className="py-3 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800/60">
              {filteredRecords.length === 0 ? (
                <tr>
                  <td colSpan={9} className="py-10 text-center text-neutral-400">
                    No reconciliation records match your filter criteria.
                  </td>
                </tr>
              ) : (
                filteredRecords.map((rec) => {
                  const hasDiscrepancy = rec.varianceRM > 0;
                  return (
                    <tr 
                      key={rec.id}
                      className={`hover:bg-neutral-50/70 dark:hover:bg-neutral-800/40 transition-colors ${
                        rec.reconciliationStatus === 'disputed' ? 'bg-amber-50/20 dark:bg-amber-950/10' : ''
                      }`}
                    >
                      <td className="py-3.5 px-4 font-mono font-medium text-neutral-900 dark:text-neutral-100">
                        <div>{rec.invoiceNumber}</div>
                        <div className="text-[10px] text-neutral-400 font-normal">{rec.billingMonth}</div>
                      </td>

                      <td className="py-3.5 px-4">
                        <div className="font-mono font-semibold text-neutral-900 dark:text-neutral-100">
                          {rec.trackingNumber}
                        </div>
                        <div className="text-[11px] text-neutral-500 dark:text-neutral-400">
                          {rec.orderNumber}
                        </div>
                      </td>

                      <td className="py-3.5 px-4">
                        {getCourierBadge(rec.courier)}
                      </td>

                      <td className="py-3.5 px-4 font-mono">
                        <div className="flex items-center gap-1.5">
                          <span className="text-neutral-600 dark:text-neutral-400">{rec.systemWeightKg} kg</span>
                          <span className="text-neutral-400">→</span>
                          <span className={`font-bold ${rec.courierBilledWeightKg > rec.systemWeightKg ? 'text-amber-600 dark:text-amber-400' : 'text-neutral-900 dark:text-neutral-100'}`}>
                            {rec.courierBilledWeightKg} kg
                          </span>
                        </div>
                      </td>

                      <td className="py-3.5 px-4 font-mono text-neutral-700 dark:text-neutral-300">
                        RM {rec.systemRateRM.toFixed(2)}
                      </td>

                      <td className="py-3.5 px-4 font-mono font-semibold text-neutral-900 dark:text-neutral-100">
                        RM {rec.courierBilledRateRM.toFixed(2)}
                        <span className="text-[10px] text-neutral-400 block font-normal">+SST: RM {rec.sstTaxRM.toFixed(2)}</span>
                      </td>

                      <td className="py-3.5 px-4">
                        <div className="space-y-1">
                          <div className={`font-mono font-bold ${hasDiscrepancy ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'}`}>
                            {hasDiscrepancy ? `+RM ${rec.varianceRM.toFixed(2)}` : 'RM 0.00'}
                          </div>
                          <div>{getDiscrepancyBadge(rec.discrepancyType)}</div>
                        </div>
                      </td>

                      <td className="py-3.5 px-4">
                        {rec.reconciliationStatus === 'matched' && (
                          <span className="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                            Matched
                          </span>
                        )}
                        {rec.reconciliationStatus === 'disputed' && (
                          <span className="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                            Dispute Open
                          </span>
                        )}
                        {rec.reconciliationStatus === 'resolved' && (
                          <span className="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                            Resolved & Credit Issued
                          </span>
                        )}
                      </td>

                      <td className="py-3.5 px-4 text-right">
                        {rec.reconciliationStatus === 'disputed' ? (
                          <div className="flex items-center justify-end gap-1.5">
                            <button
                              onClick={() => handleOpenDispute(rec)}
                              className="px-2.5 py-1 text-[11px] font-semibold rounded-lg bg-amber-600 hover:bg-amber-700 text-white transition cursor-pointer"
                              title="Submit Dispute Ticket"
                            >
                              Dispute Form
                            </button>
                            <button
                              onClick={() => onResolveDispute(rec.id)}
                              className="px-2.5 py-1 text-[11px] font-semibold rounded-lg border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-700 dark:text-neutral-300 transition cursor-pointer"
                            >
                              Resolve
                            </button>
                          </div>
                        ) : rec.reconciliationStatus === 'resolved' ? (
                          <span className="text-[11px] text-neutral-400">Settled</span>
                        ) : (
                          <button
                            onClick={() => handleOpenDispute(rec)}
                            className="px-2.5 py-1 text-[11px] font-medium rounded-lg text-neutral-500 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800 transition cursor-pointer"
                          >
                            Review
                          </button>
                        )}
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Dispute Modal */}
      {disputeModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-fadeIn">
          <div className="w-full max-w-lg rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 shadow-2xl p-6 space-y-4">
            <div className="flex items-center justify-between pb-3 border-b border-neutral-200 dark:border-neutral-800">
              <h3 className="text-base font-bold text-neutral-900 dark:text-neutral-100 flex items-center gap-2">
                <MessageSquareWarning className="w-5 h-5 text-amber-600" />
                Submit Courier Overcharge Dispute Claim
              </h3>
              <button
                onClick={() => setDisputeModalOpen(false)}
                className="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 text-sm font-bold cursor-pointer"
              >
                ✕
              </button>
            </div>

            <div className="space-y-3">
              <div className="p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700 text-xs space-y-1">
                <div className="font-semibold text-neutral-700 dark:text-neutral-300">
                  Standard Malaysia Courier Dispute SLA:
                </div>
                <p className="text-neutral-500 dark:text-neutral-400">
                  Claims submitted within 14 working days of monthly statement receipt are typically audited via sorting hub automated dimensioners. If approved, courier will issue a Credit Note (CN) against your next billing cycle.
                </p>
              </div>

              <div>
                <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                  Dispute Reason & Warehouse Evidence
                </label>
                <textarea
                  rows={4}
                  value={disputeNotes}
                  onChange={(e) => setDisputeNotes(e.target.value)}
                  className="w-full p-3 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-1 focus:ring-indigo-500 font-mono"
                />
              </div>
            </div>

            <div className="flex items-center justify-end gap-2 pt-3 border-t border-neutral-200 dark:border-neutral-800">
              <button
                type="button"
                onClick={() => setDisputeModalOpen(false)}
                className="px-4 py-2 text-xs font-semibold rounded-xl border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-700 dark:text-neutral-300 transition cursor-pointer"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={handleConfirmDisputeSubmit}
                className="flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-xl bg-amber-600 hover:bg-amber-700 text-white transition cursor-pointer shadow-sm"
              >
                <Send className="w-3.5 h-3.5" />
                Submit Claim & Resolve
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
