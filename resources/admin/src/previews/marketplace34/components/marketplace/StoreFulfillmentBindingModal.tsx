import React, { useState, useEffect } from 'react';
import { StoreFulfillmentBinding, EntityWarehouse } from '../../types';
import { MarketplaceLogo } from '../../../marketplace33/components/marketplace/MarketplaceLogo';
import {
  Store,
  Warehouse,
  CheckCircle2,
  X,
  MapPin,
  Link2,
  AlertTriangle,
  Info
} from 'lucide-react';

interface StoreFulfillmentBindingModalProps {
  isOpen: boolean;
  onClose: () => void;
  binding: StoreFulfillmentBinding | null;
  warehouses: EntityWarehouse[];
  onSave: (data: Partial<StoreFulfillmentBinding>) => Promise<{ success: boolean; error?: string }>;
}

export const StoreFulfillmentBindingModal: React.FC<StoreFulfillmentBindingModalProps> = ({
  isOpen,
  onClose,
  binding,
  warehouses = [],
  onSave
}) => {
  const [selectedWarehouseId, setSelectedWarehouseId] = useState<string>(binding?.boundWarehouseId || '');
  const [platformLocationId, setPlatformLocationId] = useState<string>(binding?.platformLocationId || '');
  const [platformLocationName, setPlatformLocationName] = useState<string>(binding?.platformLocationName || '');
  const [fulfillmentPriority, setFulfillmentPriority] = useState<number>(binding?.fulfillmentPriority || 1);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (binding) {
      setSelectedWarehouseId(binding.boundWarehouseId);
      setPlatformLocationId(binding.platformLocationId || '');
      setPlatformLocationName(binding.platformLocationName || '');
      setFulfillmentPriority(binding.fulfillmentPriority || 1);
      setErrorMsg(null);
    }
  }, [binding]);

  const targetWh = warehouses.find(w => w.id === selectedWarehouseId);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!binding || saving) return;
    if (!selectedWarehouseId) {
      setErrorMsg('Please select an entity warehouse.');
      return;
    }

    const payload: Partial<StoreFulfillmentBinding> = {
      id: binding.id,
      storeIdentifier: binding.storeIdentifier,
      boundWarehouseId: selectedWarehouseId,
      boundWarehouseName: targetWh?.name || 'Selected Warehouse',
      boundWarehouseCode: targetWh?.code || 'WH',
      platformLocationId: platformLocationId.trim() || undefined,
      platformLocationName: platformLocationName.trim() || undefined,
      fulfillmentPriority
    };

    setSaving(true);
    try {
    const res = await onSave(payload);
    if (!res.success) {
      setErrorMsg(res.error || 'Failed to update store fulfillment binding.');
      return;
    }

    onClose();
    } catch(error) {
      setErrorMsg(error instanceof Error ? error.message : 'Failed to update store fulfillment binding.');
    } finally { setSaving(false); }
  };

  if (!isOpen || !binding) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/70 backdrop-blur-xs overflow-y-auto">
      <div inert={saving} aria-busy={saving} className="relative w-full max-w-lg max-h-[calc(100dvh-1.5rem)] sm:max-h-[calc(100dvh-2.5rem)] my-auto flex flex-col bg-white dark:bg-neutral-900 rounded-2xl shadow-2xl border border-neutral-200 dark:border-neutral-800 overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        
        {/* Header - Pinned */}
        <div className="p-4 sm:p-5 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950/70 flex items-center justify-between shrink-0">
          <div className="flex items-center gap-3">
            <div className="p-2.5 rounded-xl bg-neutral-100 dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200 border border-neutral-200 dark:border-neutral-700 flex items-center justify-center">
              <MarketplaceLogo platform={binding.platform} size={22} />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                  Configure Store Fulfillment Route
                </h3>
                <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 border border-neutral-300 dark:border-neutral-700">
                  {binding.platform.toUpperCase()}
                </span>
              </div>
              <p className="text-xs text-neutral-500 mt-0.5">
                {binding.storeName}
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

          {/* Educational Notice */}
          <div className="p-3.5 rounded-xl bg-blue-500/10 border border-blue-500/20 text-blue-800 dark:text-blue-300 space-y-1">
            <div className="flex items-center gap-1.5 font-bold text-xs">
              <Info className="w-4 h-4 text-blue-500 shrink-0" />
              <span>Independent Warehouse Fulfillment Binding</span>
            </div>
            <p className="text-[11px] opacity-90 leading-relaxed">
              When in independent warehouse mode, this store will publish stock and fulfill orders strictly from the selected entity warehouse. Multiple stores may safely share the same warehouse without double-counting stock.
            </p>
          </div>

          {/* Bound Warehouse Selection */}
          <div>
            <label className="block font-bold text-neutral-700 dark:text-neutral-300 mb-1.5">
              Fulfilling Entity Warehouse <span className="text-rose-500">*</span>
            </label>
            <select
              value={selectedWarehouseId}
              onChange={e => setSelectedWarehouseId(e.target.value)}
              required
              className="w-full px-3 py-2.5 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white font-medium"
            >
              <option value="" disabled>Select an entity warehouse</option>
              {warehouses.map(wh => (
                <option key={wh.id} value={wh.id}>
                  {wh.name} ({wh.code}) — {wh.address?.city}, {wh.address?.state} {wh.isDefaultMaster ? '★ Default Master' : ''}
                </option>
              ))}
            </select>
          </div>

          {/* Platform Location ID Mapping */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                Platform Location ID
              </label>
              <input
                type="text"
                disabled={binding.platform==='webstore'}
                value={platformLocationId}
                onChange={e => setPlatformLocationId(e.target.value)}
                placeholder={binding.platform === 'shopee' ? 'e.g. SPX-MY-CENTRAL-01' : 'e.g. LEX-WH-CYB-01'}
                className="w-full px-3 py-2 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white font-mono"
              />
              <span className="text-[10px] text-neutral-400 mt-1 block">
                Channel warehouse location ID registered with {binding.platform.toUpperCase()}
              </span>
            </div>

            <div>
              <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                Platform Location Label
              </label>
              <input
                type="text"
                disabled={binding.platform==='webstore'}
                value={platformLocationName}
                onChange={e => setPlatformLocationName(e.target.value)}
                placeholder="e.g. Central Malaysia Sorting Hub"
                className="w-full px-3 py-2 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white"
              />
              <span className="text-[10px] text-neutral-400 mt-1 block">
                Human-friendly platform warehouse name
              </span>
            </div>
          </div>

          {/* Priority & Status */}
          <div>
            <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
              Fulfillment Allocation Priority
            </label>
            <div className="flex items-center gap-3">
              <input
                type="number"
                min="1"
                max="10"
                value={fulfillmentPriority}
                onChange={e => setFulfillmentPriority(parseInt(e.target.value) || 1)}
                className="w-24 px-3 py-2 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white font-bold text-center"
              />
              <span className="text-[11px] text-neutral-500">
                Priority 1 = Highest priority for order fulfillment dispatch
              </span>
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
              className="px-5 py-2.5 bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100 text-xs font-bold rounded-xl shadow-xs flex items-center gap-1.5 cursor-pointer transition-colors"
            >
              <CheckCircle2 className="w-3.5 h-3.5" />
              <span>Save Binding</span>
            </button>
          </div>

        </form>

      </div>
    </div>
  );
};
