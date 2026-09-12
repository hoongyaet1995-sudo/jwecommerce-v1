import React, { useState, useMemo } from 'react';
import { InterWarehouseTransfer, EntityWarehouse, StaffUser } from '../../types';
import {
  ArrowRightLeft,
  Truck,
  CheckCircle2,
  Clock,
  XCircle,
  Plus,
  Search,
  Filter,
  Warehouse,
  ArrowRight,
  ShieldCheck,
  FileText
} from 'lucide-react';

interface WarehouseTransferTabProps {
  transfers: InterWarehouseTransfer[];
  warehouses: EntityWarehouse[];
  staffUsers: StaffUser[];
  currentUserName?: string;
  onOpenCreateTransferModal: () => void;
  onUpdateTransferStatus: (
    transferId: string,
    newStatus: InterWarehouseTransfer['status'],
    operator: string,
    notes?: string
  ) => { success: boolean; error?: string } | Promise<{success:boolean;error?:string}>;
}

export const WarehouseTransferTab: React.FC<WarehouseTransferTabProps> = ({
  transfers = [],
  warehouses = [],
  staffUsers = [],
  currentUserName,
  onOpenCreateTransferModal,
  onUpdateTransferStatus
}) => {
  const [statusFilter, setStatusFilter] = useState<'all' | 'draft' | 'in_transit' | 'completed' | 'cancelled'>('all');
  const [searchQuery, setSearchQuery] = useState<string>('');

  const filteredTransfers = useMemo(() => {
    return transfers.filter(t => {
      if (statusFilter !== 'all' && t.status !== statusFilter) return false;
      if (searchQuery.trim()) {
        const q = searchQuery.toLowerCase();
        const matchesId = t.id.toLowerCase().includes(q);
        const matchesSku = t.masterSku.toLowerCase().includes(q);
        const matchesProduct = (t.productName || '').toLowerCase().includes(q);
        const matchesSource = (t.sourceWarehouseName || '').toLowerCase().includes(q);
        const matchesDest = (t.destinationWarehouseName || '').toLowerCase().includes(q);
        const matchesTrack = (t.trackingNumber || '').toLowerCase().includes(q);
        if (!matchesId && !matchesSku && !matchesProduct && !matchesSource && !matchesDest && !matchesTrack) {
          return false;
        }
      }
      return true;
    });
  }, [transfers, statusFilter, searchQuery]);

  const inTransitCount = transfers.filter(t => t.status === 'in_transit').length;
  const completedCount = transfers.filter(t => t.status === 'completed').length;
  const draftCount = transfers.filter(t => t.status === 'draft').length;

  const busy=React.useRef(false);
  const handleStatusChange = async (
    transferId: string,
    newStatus: InterWarehouseTransfer['status'],
    actionLabel: string
  ) => {
    if(busy.current)return;
    const operator = currentUserName || 'Warehouse Staff';
    const notes = prompt(`Enter optional confirmation notes for "${actionLabel}":`);
    if(notes===null)return;
    busy.current=true;
    try {
    const res = await onUpdateTransferStatus(transferId, newStatus, operator, notes);
    if (!res.success) {
      alert(res.error || 'Failed to update transfer status');
    }
    } catch(error) {alert(error instanceof Error?error.message:'Transfer update failed.');}
    finally {busy.current=false;}
  };

  return (
    <div className="space-y-6">
      
      {/* Header & Metrics */}
      <div className="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between text-neutral-500 mb-1">
            <span className="text-xs font-bold uppercase tracking-wider">Total Relocations</span>
            <ArrowRightLeft className="w-4 h-4 text-neutral-700 dark:text-neutral-300" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-black text-neutral-900 dark:text-white">{transfers.length}</span>
            <span className="text-xs text-neutral-400">Transfer Orders</span>
          </div>
        </div>

        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between text-neutral-500 mb-1">
            <span className="text-xs font-bold uppercase tracking-wider">Active In-Transit</span>
            <Truck className="w-4 h-4 text-purple-500" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-black text-purple-600 dark:text-purple-400">{inTransitCount}</span>
            <span className="text-xs text-neutral-400">Locked in Transit</span>
          </div>
          <p className="text-[11px] text-purple-600 dark:text-purple-400 mt-2 font-medium">
            Reserved at source until received
          </p>
        </div>

        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between text-neutral-500 mb-1">
            <span className="text-xs font-bold uppercase tracking-wider">Completed</span>
            <CheckCircle2 className="w-4 h-4 text-emerald-500" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-black text-emerald-600 dark:text-emerald-400">{completedCount}</span>
            <span className="text-xs text-neutral-400">Successfully Received</span>
          </div>
          <p className="text-[11px] text-emerald-600 dark:text-emerald-400 mt-2 font-medium">
            Stock added to dest physical ATP
          </p>
        </div>

        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between text-neutral-500 mb-1">
            <span className="text-xs font-bold uppercase tracking-wider">Draft Orders</span>
            <Clock className="w-4 h-4 text-amber-500" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-black text-amber-600 dark:text-amber-400">{draftCount}</span>
            <span className="text-xs text-neutral-400">Awaiting Dispatch</span>
          </div>
          <p className="text-[11px] text-neutral-500 mt-2 font-medium">
            Not yet released from source
          </p>
        </div>
      </div>

      {/* Filter & Action Bar */}
      <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-3 shadow-xs flex flex-wrap items-center justify-between gap-3">
        
        {/* Status Filter Buttons */}
        <div className="flex flex-wrap items-center gap-1">
          {[
            { id: 'all', label: 'All Transfers' },
            { id: 'in_transit', label: `In Transit (${inTransitCount})` },
            { id: 'draft', label: `Draft (${draftCount})` },
            { id: 'completed', label: `Completed (${completedCount})` },
            { id: 'cancelled', label: 'Cancelled' },
          ].map(f => (
            <button
              key={f.id}
              onClick={() => setStatusFilter(f.id as any)}
              className={`px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer ${
                statusFilter === f.id
                  ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 shadow-xs'
                  : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'
              }`}
            >
              {f.label}
            </button>
          ))}
        </div>

        <div className="flex items-center gap-3 w-full sm:w-auto">
          {/* Search */}
          <div className="relative w-full sm:w-64">
            <Search className="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400" />
            <input
              type="text"
              value={searchQuery}
              onChange={e => setSearchQuery(e.target.value)}
              placeholder="Search SKU, transfer #, tracking..."
              className="w-full pl-9 pr-3 py-1.5 text-xs rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 text-neutral-900 dark:text-white"
            />
          </div>

          {/* Create Button */}
          <button
            onClick={onOpenCreateTransferModal}
            className="px-4 py-2 rounded-xl bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100 text-xs font-bold shadow-xs flex items-center gap-1.5 cursor-pointer shrink-0 transition-colors"
          >
            <Plus className="w-3.5 h-3.5" />
            <span>Create Transfer</span>
          </button>
        </div>
      </div>

      {/* Transfers Table */}
      <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-neutral-50 dark:bg-neutral-950/70 border-b border-neutral-200 dark:border-neutral-800 text-neutral-500 font-bold uppercase tracking-wider text-[10px]">
              <tr>
                <th className="py-3.5 px-4">Transfer Order / Date</th>
                <th className="py-3.5 px-4">SKU & Product</th>
                <th className="py-3.5 px-4">Route (Source → Destination)</th>
                <th className="py-3.5 px-4 text-center">Quantity</th>
                <th className="py-3.5 px-4">Waybill / Reason</th>
                <th className="py-3.5 px-4 text-center">Status</th>
                <th className="py-3.5 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800">
              {filteredTransfers.length === 0 ? (
                <tr>
                  <td colSpan={7} className="py-12 text-center text-neutral-400">
                    <ArrowRightLeft className="w-8 h-8 mx-auto mb-2 text-neutral-300 dark:text-neutral-700" />
                    <p className="font-semibold">No transfer records found</p>
                  </td>
                </tr>
              ) : (
                filteredTransfers.map(transfer => {
                  return (
                    <tr key={transfer.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/40 transition-colors">
                      {/* Order ID & Date */}
                      <td className="py-4 px-4">
                        <span className="font-mono font-bold text-neutral-900 dark:text-white">
                          #{transfer.id.slice(-8).toUpperCase()}
                        </span>
                        <p className="text-[11px] text-neutral-400 font-mono mt-0.5">
                          {new Date(transfer.createdAt).toLocaleDateString()} {new Date(transfer.createdAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                        </p>
                      </td>

                      {/* SKU & Product */}
                      <td className="py-4 px-4">
                        <span className="font-mono font-bold text-neutral-900 dark:text-white">
                          {transfer.masterSku}
                        </span>
                        <p className="font-semibold text-neutral-800 dark:text-neutral-200 text-xs line-clamp-1">
                          {transfer.productName}
                        </p>
                      </td>

                      {/* Route */}
                      <td className="py-4 px-4">
                        <div className="flex items-center gap-2">
                          <span className="font-medium text-neutral-800 dark:text-neutral-200">
                            {transfer.sourceWarehouseName}
                          </span>
                          <ArrowRight className="w-3.5 h-3.5 text-neutral-400 shrink-0" />
                          <span className="font-medium text-purple-600 dark:text-purple-400 font-bold">
                            {transfer.destinationWarehouseName}
                          </span>
                        </div>
                      </td>

                      {/* Quantity */}
                      <td className="py-4 px-4 text-center">
                        <span className="font-black text-sm text-neutral-900 dark:text-white">
                          {transfer.quantity}
                        </span>
                        <span className="block text-[10px] text-neutral-400">units</span>
                      </td>

                      {/* Waybill & Reason */}
                      <td className="py-4 px-4">
                        {transfer.trackingNumber && (
                          <div className="flex items-center gap-1 font-mono text-[11px] text-neutral-700 dark:text-neutral-300">
                            <Truck className="w-3.5 h-3.5 text-neutral-400 shrink-0" />
                            <span>{transfer.trackingNumber}</span>
                          </div>
                        )}
                        <p className="text-[11px] text-neutral-500 line-clamp-1">
                          {transfer.reason}
                        </p>
                        <p className="text-[10px] text-neutral-400 mt-0.5">
                          By: {transfer.operator}
                        </p>
                      </td>

                      {/* Status */}
                      <td className="py-4 px-4 text-center">
                        <span className={`inline-block font-bold text-[10px] px-2.5 py-1 rounded-full border ${
                          transfer.status === 'in_transit'
                            ? 'bg-purple-500/10 text-purple-700 dark:text-purple-300 border-purple-500/20'
                            : transfer.status === 'completed'
                            ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/20'
                            : transfer.status === 'draft'
                            ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/20'
                            : 'bg-neutral-200 dark:bg-neutral-800 text-neutral-500 border-neutral-300 dark:border-neutral-700'
                        }`}>
                          {transfer.status.replace('_', ' ').toUpperCase()}
                        </span>
                      </td>

                      {/* Actions */}
                      <td className="py-4 px-4 text-right">
                        <div className="inline-flex items-center gap-1.5">
                          {transfer.status === 'draft' && (
                            <button
                              onClick={() => handleStatusChange(transfer.id, 'in_transit', 'Dispatch Transfer')}
                              className="px-2.5 py-1 rounded-lg bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 font-bold text-[11px] hover:bg-neutral-800 dark:hover:bg-neutral-100 cursor-pointer"
                            >
                              Dispatch
                            </button>
                          )}

                          {transfer.status === 'in_transit' && (
                            <button
                              onClick={() => handleStatusChange(transfer.id, 'completed', 'Receive Goods')}
                              className="px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[11px] cursor-pointer flex items-center gap-1"
                            >
                              <CheckCircle2 className="w-3 h-3" />
                              <span>Receive</span>
                            </button>
                          )}

                          {(transfer.status === 'draft' || transfer.status === 'in_transit') && (
                            <button
                              onClick={() => handleStatusChange(transfer.id, 'cancelled', 'Cancel Transfer')}
                              className="p-1 rounded-lg text-neutral-400 hover:text-rose-500 hover:bg-rose-500/10 transition-colors cursor-pointer"
                              title="Cancel Transfer Order"
                            >
                              <XCircle className="w-4 h-4" />
                            </button>
                          )}
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

    </div>
  );
};
