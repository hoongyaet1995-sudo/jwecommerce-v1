import React, { useState } from 'react';
import { MarketplaceInventoryItem, EntityWarehouse, StaffUser } from '../../types';
import {
  ArrowRightLeft,
  Warehouse,
  AlertTriangle,
  CheckCircle2,
  X,
  Truck,
  FileText,
  UserCheck
} from 'lucide-react';

interface CreateWarehouseTransferModalProps {
  isOpen: boolean;
  onClose: () => void;
  inventory: MarketplaceInventoryItem[];
  warehouses: EntityWarehouse[];
  staffUsers: StaffUser[];
  currentUserName?: string;
  onCreateTransfer: (data: {
    masterSku: string;
    sourceWarehouseId: string;
    destinationWarehouseId: string;
    quantity: number;
    reason: string;
    operator: string;
    trackingNumber?: string;
  }) => { success: boolean; error?: string } | Promise<{success:boolean;error?:string}>;
}

export const CreateWarehouseTransferModal: React.FC<CreateWarehouseTransferModalProps> = ({
  isOpen,
  onClose,
  inventory = [],
  warehouses = [],
  staffUsers = [],
  currentUserName,
  onCreateTransfer
}) => {
  const activeWarehouses = warehouses.filter(w => w.status !== 'inactive');
  const [selectedSku, setSelectedSku] = useState<string>(inventory[0]?.masterSku || '');
  const [sourceWarehouseId, setSourceWarehouseId] = useState<string>(activeWarehouses[0]?.id || '');
  const [destinationWarehouseId, setDestinationWarehouseId] = useState<string>(activeWarehouses[1]?.id || activeWarehouses[0]?.id || '');
  const [quantity, setQuantity] = useState<number>(10);
  const [reason, setReason] = useState<string>('Rebalancing regional inventory buffer for Northern Hub');
  const [trackingNumber, setTrackingNumber] = useState<string>('');
  const [selectedStaffId, setSelectedStaffId] = useState<string>(() => {
    const matched = staffUsers.find(
      s => s.fullName.toLowerCase().includes(currentUserName?.toLowerCase() || '') ||
           s.username.toLowerCase().includes(currentUserName?.toLowerCase() || '')
    );
    return matched ? matched.id : (staffUsers[0]?.id || '');
  });
  const [errorMsg, setErrorMsg] = useState<string | null>(null);

  const selectedItem = inventory.find(i => i.masterSku === selectedSku);
  const sourceWh = warehouses.find(w => w.id === sourceWarehouseId);
  const destWh = warehouses.find(w => w.id === destinationWarehouseId);

  // Source available stock
  const sourceBalance = selectedItem?.warehouseBalances?.find(b => b.warehouseId === sourceWarehouseId);
  const availableAtpAtSource = sourceBalance
    ? sourceBalance.availableAtp
    : (selectedItem?.availableStock ?? 0);

  const selectedStaff = staffUsers.find(s => s.id === selectedStaffId) || staffUsers[0];
  const operatorName = selectedStaff
    ? `${selectedStaff.fullName} (${selectedStaff.position || 'Staff'})`
    : (currentUserName || 'Authorized Staff');

  // Defensive validation:
  const isZeroOrNegative = quantity <= 0;
  const isExceedingStock = quantity > availableAtpAtSource;

  const [saving,setSaving]=useState(false);
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if(saving)return;
    if (!selectedSku) {
      setErrorMsg('Please select a SKU to transfer.');
      return;
    }
    if (sourceWarehouseId === destinationWarehouseId) {
      setErrorMsg('Source and Destination warehouses cannot be identical.');
      return;
    }
    if (isZeroOrNegative) {
      setErrorMsg('Transfer quantity must be greater than zero.');
      return;
    }
    if (isExceedingStock) {
      setErrorMsg(`Transfer quantity (${quantity}) exceeds source available ATP (${availableAtpAtSource}).`);
      return;
    }

    setSaving(true);
    try {
    const res = await onCreateTransfer({
      masterSku: selectedSku,
      sourceWarehouseId,
      destinationWarehouseId,
      quantity,
      reason: reason.trim(),
      operator: operatorName,
      trackingNumber: trackingNumber.trim() || undefined
    });

    if (!res.success) {
      setErrorMsg(res.error || 'Failed to initiate transfer order.');
      return;
    }

    onClose();
    } catch(error) {setErrorMsg(error instanceof Error?error.message:'Transfer creation failed.');}
    finally {setSaving(false);}
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/70 backdrop-blur-xs overflow-y-auto">
      <div className="relative w-full max-w-lg max-h-[calc(100dvh-1.5rem)] sm:max-h-[calc(100dvh-2.5rem)] my-auto flex flex-col bg-white dark:bg-neutral-900 rounded-2xl shadow-2xl border border-neutral-200 dark:border-neutral-800 overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        
        {/* Header - Pinned */}
        <div className="p-4 sm:p-5 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950/70 flex items-center justify-between shrink-0">
          <div className="flex items-center gap-3">
            <div className="p-2.5 rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20">
              <ArrowRightLeft className="w-5 h-5" />
            </div>
            <div>
              <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                Create Inter-Warehouse Stock Transfer
              </h3>
              <p className="text-xs text-neutral-500 mt-0.5">
                Relocate inventory physical units between authorized entity facilities
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 cursor-pointer transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="flex flex-col flex-1 min-h-0 overflow-hidden">
          <div className="p-4 sm:p-6 space-y-4 text-xs overflow-y-auto flex-1 min-h-0">
            {errorMsg && (
              <div className="p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-700 dark:text-rose-300 flex items-center gap-2">
                <AlertTriangle className="w-4 h-4 shrink-0 text-rose-500" />
                <span>{errorMsg}</span>
              </div>
            )}

            {/* Master SKU Selection */}
            <div>
              <label className="block font-bold text-neutral-700 dark:text-neutral-300 mb-1.5">
                Select SKU to Relocate <span className="text-rose-500">*</span>
              </label>
              <select
                value={selectedSku}
                onChange={e => setSelectedSku(e.target.value)}
                required
                className="w-full px-3 py-2.5 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white font-medium"
              >
                {inventory.map(item => (
                  <option key={item.id} value={item.masterSku}>
                    {item.masterSku} — {item.productName} ({item.variantName})
                  </option>
                ))}
              </select>
            </div>

            {/* Source and Destination Warehouses */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label className="block font-bold text-neutral-700 dark:text-neutral-300 mb-1.5 flex items-center justify-between">
                  <span>Source Warehouse</span>
                  <span className="text-[10px] text-emerald-600 dark:text-emerald-400 font-mono">
                    ATP: {availableAtpAtSource} units
                  </span>
                </label>
                <select
                  value={sourceWarehouseId}
                  onChange={e => setSourceWarehouseId(e.target.value)}
                  required
                  className="w-full px-3 py-2.5 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white font-medium"
                >
                  {activeWarehouses.map(wh => (
                    <option key={wh.id} value={wh.id}>
                      {wh.code} — {wh.name}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block font-bold text-neutral-700 dark:text-neutral-300 mb-1.5">
                  Destination Warehouse
                </label>
                <select
                  value={destinationWarehouseId}
                  onChange={e => setDestinationWarehouseId(e.target.value)}
                  required
                  className="w-full px-3 py-2.5 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white font-medium"
                >
                  {activeWarehouses.map(wh => (
                    <option key={wh.id} value={wh.id} disabled={wh.id === sourceWarehouseId}>
                      {wh.code} — {wh.name} {wh.id === sourceWarehouseId ? '(Source)' : ''}
                    </option>
                  ))}
                </select>
              </div>
            </div>

            {/* Quantity Input with Defensive Bounds Checking */}
            <div className="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 space-y-2">
              <div className="flex items-center justify-between">
                <span className="font-bold text-neutral-700 dark:text-neutral-300">
                  Transfer Quantity (Units) <span className="text-rose-500">*</span>
                </span>
                <span className="text-[11px] text-neutral-500">
                  Max available at source: <strong>{availableAtpAtSource} units</strong>
                </span>
              </div>
              <input
                type="number"
                min="1"
                max={availableAtpAtSource}
                value={quantity}
                onChange={e => setQuantity(parseInt(e.target.value) || 0)}
                className="w-full text-center font-black text-xl px-3 py-2.5 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white"
              />
              {isExceedingStock && (
                <p className="text-[11px] text-rose-600 dark:text-rose-400 font-medium">
                  Defensive Check: Cannot transfer more units than available at source facility ({availableAtpAtSource}).
                </p>
              )}
              {isZeroOrNegative && (
                <p className="text-[11px] text-rose-600 dark:text-rose-400 font-medium">
                  Defensive Check: Quantity must be at least 1 unit.
                </p>
              )}
            </div>

            {/* Operator Identity Selection */}
            <div>
              <label className="block font-bold text-neutral-700 dark:text-neutral-300 mb-1.5 flex items-center justify-between">
                <span className="flex items-center gap-1.5">
                  <UserCheck className="w-3.5 h-3.5 text-neutral-500" />
                  Authorizing Dispatch Officer <span className="text-rose-500">*</span>
                </span>
                <span className="text-[10px] text-neutral-400 font-normal">Audit-logged</span>
              </label>
              <select
                value={selectedStaffId}
                onChange={e => setSelectedStaffId(e.target.value)}
                required
                className="w-full px-3 py-2.5 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white font-medium"
              >
                {staffUsers.map(staff => (
                  <option key={staff.id} value={staff.id}>
                    {staff.fullName} — {staff.position || 'Staff'} ({staff.email})
                  </option>
                ))}
              </select>
            </div>

            {/* Reason & Tracking Number */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                  Transfer Purpose
                </label>
                <input
                  type="text"
                  value={reason}
                  onChange={e => setReason(e.target.value)}
                  placeholder="e.g. Stock rebalance for upcoming flash sale"
                  className="w-full px-3 py-2 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white"
                />
              </div>
              <div>
                <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1 flex items-center gap-1">
                  <Truck className="w-3.5 h-3.5 text-neutral-400" />
                  <span>Inter-Warehouse Waybill (Optional)</span>
                </label>
                <input
                  type="text"
                  value={trackingNumber}
                  onChange={e => setTrackingNumber(e.target.value)}
                  placeholder="e.g. TRK-MY-2026-0901"
                  className="w-full px-3 py-2 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white font-mono"
                />
              </div>
            </div>
          </div>

          {/* Action Buttons - Pinned Footer */}
          <div className="p-3.5 sm:px-6 sm:py-3.5 flex items-center justify-between border-t border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950/70 shrink-0">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2.5 text-xs font-semibold text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-xl cursor-pointer transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={saving || isZeroOrNegative || isExceedingStock || sourceWarehouseId === destinationWarehouseId}
              className="px-5 py-2.5 bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-bold rounded-xl shadow-xs flex items-center gap-1.5 cursor-pointer transition-colors"
            >
              <CheckCircle2 className="w-3.5 h-3.5" />
              <span>Initiate Stock Transfer</span>
            </button>
          </div>

        </form>

      </div>
    </div>
  );
};
