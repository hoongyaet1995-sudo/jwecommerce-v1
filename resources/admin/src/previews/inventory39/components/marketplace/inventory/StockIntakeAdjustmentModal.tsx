import React, { useState, useEffect, useMemo } from 'react';
import {
  X,
  PlusCircle,
  Sliders,
  AlertTriangle,
  Info,
  CheckCircle2,
  Boxes,
  ArrowRight,
  ShieldAlert
} from 'lucide-react';
import { MarketplaceInventoryItem } from '../../../types';
import { InventoryStoreChannel, StoreStockAccountBalance } from './inventoryTypes';

export function calculateStoreStockChange(
  actionType: 'intake' | 'adjustment',
  enteredQuantity: number,
  currentPhysical: number,
) {
  const newPhysicalStock = actionType === 'intake'
    ? currentPhysical + enteredQuantity
    : enteredQuantity;

  return {
    deltaQuantity: newPhysicalStock - currentPhysical,
    newPhysicalStock,
  };
}

interface StockIntakeAdjustmentModalProps {
  isOpen: boolean;
  onClose: () => void;
  item: MarketplaceInventoryItem | null;
  mode: 'shared' | 'independent';
  stores: InventoryStoreChannel[];
  storeBalances: Record<string, Record<string, StoreStockAccountBalance>>;
  initialType?: 'intake' | 'adjustment';
  onSave: (payload: {
    sku: string;
    type: 'intake' | 'adjustment';
    targetStoreId?: string;
    deltaQuantity: number;
    newPhysicalStock: number;
    reason: string;
    notes?: string;
    operator: string;
  }) => void;
}

export const StockIntakeAdjustmentModal: React.FC<StockIntakeAdjustmentModalProps> = ({
  isOpen,
  onClose,
  item,
  mode,
  stores,
  storeBalances,
  initialType = 'intake',
  onSave
}) => {
  const [actionType, setActionType] = useState<'intake' | 'adjustment'>(initialType);
  const [quantityInput, setQuantityInput] = useState<string>('');
  const [selectedStoreId, setSelectedStoreId] = useState<string>('website');
  const [reason, setReason] = useState<string>('');
  const [notes, setNotes] = useState<string>('');
  const [operator, setOperator] = useState<string>('Alexander (Admin)');

  const eligibleStores = useMemo(() => {
    const mappedStoreIds = new Set<string>((item as any)?.mappedStoreIds || []);
    const associations = new Map<string, any>(((item as any)?.storeAssociations || [])
      .map((association: any) => [String(association.connectionId), association]));

    return (stores || []).filter(store => {
      if (store.isComingSoon) return false;
      if (store.platform === 'webstore') return store.connectionState === 'connected';

      return store.connectionState === 'connected'
        && mappedStoreIds.has(store.id)
        && associations.get(store.id)?.syncReadiness?.ready === true;
    });
  }, [item, stores]);

  useEffect(() => {
    if (isOpen) {
      setActionType(initialType);
      setQuantityInput('');
      setNotes('');
      setReason(initialType === 'intake' ? 'Supplier PO Delivery Verification' : 'Physical Cycle Count Reconciliation');
      setSelectedStoreId(current => eligibleStores.some(store => store.id === current)
        ? current
        : (eligibleStores[0]?.id || ''));
    }
  }, [isOpen, initialType, item?.id, eligibleStores]);

  if (!isOpen || !item) return null;

  const selectedBalance = mode === 'independent'
    ? storeBalances?.[item.masterSku]?.[selectedStoreId]
    : undefined;
  const currentPhysical = mode === 'independent'
    ? (selectedBalance?.physicalStock ?? 0)
    : (item.totalPhysicalStock ?? 0);
  const currentReserved = mode === 'independent'
    ? (selectedBalance?.reservedStock ?? 0)
    : (item.reservedStock ?? 0);
  const numericQty = quantityInput.trim() === '' ? NaN : Number(quantityInput);
  const isValidQty = !isNaN(numericQty) && Number.isInteger(numericQty) && (actionType === 'intake' ? numericQty > 0 : true);

  // For intake: delta is +numericQty. New physical is currentPhysical + numericQty.
  // For adjustment: numericQty is the new physical count, so delta is numericQty - currentPhysical.
  const change = isValidQty
    ? calculateStoreStockChange(actionType, numericQty, currentPhysical)
    : { deltaQuantity: 0, newPhysicalStock: currentPhysical };
  const newPhysical = change.newPhysicalStock;
  const delta = change.deltaQuantity;
  const newAvailable = Math.max(0, newPhysical - currentReserved);
  const hasDeficit = newPhysical < currentReserved;

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!isValidQty || hasDeficit) return;

    onSave({
      sku: item.masterSku,
      type: actionType,
      targetStoreId: mode === 'independent' ? selectedStoreId : undefined,
      deltaQuantity: delta,
      newPhysicalStock: newPhysical,
      reason,
      notes: notes.trim() || undefined,
      operator
    });
    onClose();
  };

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto bg-neutral-950/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 animate-in fade-in duration-150">
      <div 
        id="stock-intake-adjustment-modal"
        className="bg-white dark:bg-neutral-900 rounded-2xl shadow-2xl border border-neutral-200 dark:border-neutral-800 w-full max-w-xl overflow-hidden flex flex-col"
      >
        {/* Header */}
        <div className="px-6 py-4 border-b border-neutral-100 dark:border-neutral-800 flex items-center justify-between bg-neutral-50/50 dark:bg-neutral-900/50">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl flex items-center justify-center font-bold bg-neutral-100 dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200">
              {actionType === 'intake' ? <PlusCircle className="w-5 h-5" /> : <Sliders className="w-5 h-5" />}
            </div>
            <div>
              <h3 className="text-base font-bold text-neutral-900 dark:text-white">
                {actionType === 'intake' ? 'Actual Stock Intake (实际入库)' : 'Stock Audit Adjustment (盘点修正)'}
              </h3>
              <p className="text-xs text-neutral-500 dark:text-neutral-400">
                {actionType === 'intake'
                  ? 'Record newly arrived physical merchandise from suppliers or manufacturing.'
                  : 'Adjust inventory ledger to reflect physical stock variances, damage, or recounts.'}
              </p>
            </div>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="p-2 rounded-lg text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Mode Switch Pills */}
        <div className="px-6 pt-4">
          <div className="flex p-1 bg-neutral-100 dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 text-xs font-semibold">
            <button
              type="button"
              onClick={() => {
                setActionType('intake');
                setQuantityInput('');
                setReason('Supplier PO Delivery Verification');
              }}
              className={`flex-1 py-2 rounded-lg transition-all flex items-center justify-center gap-2 cursor-pointer ${
                actionType === 'intake'
                  ? 'bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white shadow-xs font-bold'
                  : 'text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200'
              }`}
            >
              <PlusCircle className="w-4 h-4" />
              <span>Actual Inbound Intake (+Qty)</span>
            </button>
            <button
              type="button"
              onClick={() => {
                setActionType('adjustment');
                setQuantityInput('');
                setReason('Physical Cycle Count Reconciliation');
              }}
              className={`flex-1 py-2 rounded-lg transition-all flex items-center justify-center gap-2 cursor-pointer ${
                actionType === 'adjustment'
                  ? 'bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white shadow-xs font-bold'
                  : 'text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200'
              }`}
            >
              <Sliders className="w-4 h-4" />
              <span>Audit Count Adjustment</span>
            </button>
          </div>
        </div>

        {/* Form Body */}
        <form onSubmit={handleSubmit} className="p-6 space-y-4">
          {/* Target SKU card */}
          <div className="p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700 flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-lg bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 overflow-hidden shrink-0 flex items-center justify-center">
                {item.imageUrl ? (
                  <img src={item.imageUrl} alt={item.productName} className="w-full h-full object-cover" />
                ) : (
                  <Boxes className="w-5 h-5 text-neutral-400" />
                )}
              </div>
              <div>
                <div className="font-mono text-xs font-bold text-neutral-900 dark:text-white">{item.masterSku}</div>
                <div className="text-xs font-semibold text-neutral-900 dark:text-white truncate max-w-xs">{item.productName}</div>
              </div>
            </div>
            <div className="text-right">
              <div className="text-[10px] text-neutral-400">Current Physical</div>
              <div className="font-mono font-bold text-neutral-800 dark:text-neutral-200 text-xs">
                {mode === 'independent'
                  ? (selectedBalance?.physicalStock !== null && selectedBalance?.physicalStock !== undefined
                    ? `${selectedBalance.physicalStock} units`
                    : 'Not Counted')
                  : (item.totalPhysicalStock !== null ? `${item.totalPhysicalStock} units` : 'Not Counted')}
              </div>
            </div>
          </div>

          {/* Independent Mode: Target Store Account */}
          {mode === 'independent' && (
            <div className="space-y-1.5 p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700">
              <label className="text-xs font-bold text-neutral-900 dark:text-neutral-100">
                Target Store Account *
              </label>
              <select
                value={selectedStoreId}
                onChange={e => setSelectedStoreId(e.target.value)}
                className="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white cursor-pointer focus:ring-2 focus:ring-neutral-400"
              >
                {eligibleStores.map(s => (
                  <option key={s.id} value={s.id}>
                    {s.name} ({s.platformDisplayName}) - {s.nickname}
                  </option>
                ))}
              </select>
            </div>
          )}

          {/* Quantity Input */}
          <div className="space-y-1.5">
            <label className="text-xs font-bold text-neutral-800 dark:text-neutral-200">
              {actionType === 'intake' 
                ? 'Arrived Inbound Quantity (+ Units) *' 
                : 'New Confirmed Physical Stock Count (Total Units) *'}
            </label>
            <div className="relative">
              <input
                type="number"
                min={actionType === 'intake' ? 1 : 0}
                step="1"
                placeholder={actionType === 'intake' ? 'e.g. 50' : 'e.g. 145'}
                value={quantityInput}
                onChange={e => setQuantityInput(e.target.value)}
                className="w-full px-3.5 py-2.5 text-sm font-mono font-bold rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white focus:ring-2 focus:ring-neutral-400"
              />
              <div className="absolute right-3 top-2.5 text-xs text-neutral-400 pointer-events-none">
                Units
              </div>
            </div>
          </div>

          {/* Deficit warning */}
          {hasDeficit && (
            <div className="p-3 rounded-xl bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800/50 flex items-start gap-2.5 text-xs text-red-900 dark:text-red-200">
              <AlertTriangle className="w-4 h-4 text-red-600 dark:text-red-400 shrink-0 mt-0.5" />
              <span>
                New physical quantity ({newPhysical}) is less than locked reserved orders ({currentReserved}). This would result in negative available inventory.
              </span>
            </div>
          )}

          {/* Preview impact */}
          {isValidQty && !hasDeficit && (
            <div className="p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700 grid grid-cols-3 gap-2 text-center text-xs">
              <div>
                <span className="text-neutral-400 text-[10px] block">Previous Physical</span>
                <span className="font-mono font-semibold text-neutral-700 dark:text-neutral-300">{currentPhysical}</span>
              </div>
              <div>
                <span className="text-neutral-400 text-[10px] block">Net Change</span>
                <span className={`font-mono font-bold ${delta >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'}`}>
                  {delta >= 0 ? `+${delta}` : delta}
                </span>
              </div>
              <div>
                <span className="text-neutral-400 text-[10px] block">New Available</span>
                <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400">{newAvailable}</span>
              </div>
            </div>
          )}

          {/* Reason & Notes */}
          <div className="space-y-3">
            <div className="space-y-1">
              <label className="text-xs font-bold text-neutral-700 dark:text-neutral-300">
                Reason *
              </label>
              <input
                type="text"
                value={reason}
                onChange={e => setReason(e.target.value)}
                className="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white"
              />
            </div>
            <div className="space-y-1">
              <label className="text-xs font-medium text-neutral-700 dark:text-neutral-300">
                Notes (Optional)
              </label>
              <input
                type="text"
                value={notes}
                onChange={e => setNotes(e.target.value)}
                placeholder="e.g. PO invoice number or cycle audit notes..."
                className="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white"
              />
            </div>
          </div>

          {/* Footer Buttons */}
          <div className="pt-3 flex items-center justify-end gap-2 border-t border-neutral-100 dark:border-neutral-800">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 text-xs font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 cursor-pointer"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={!isValidQty || hasDeficit}
              className="px-5 py-2 rounded-xl bg-neutral-900 hover:bg-neutral-800 dark:bg-white dark:hover:bg-neutral-100 text-white dark:text-neutral-900 text-xs font-bold transition-all shadow-xs flex items-center gap-1.5 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
            >
              <CheckCircle2 className="w-3.5 h-3.5" />
              <span>{actionType === 'intake' ? 'Confirm Inbound Intake' : 'Save Audit Adjustment'}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};
