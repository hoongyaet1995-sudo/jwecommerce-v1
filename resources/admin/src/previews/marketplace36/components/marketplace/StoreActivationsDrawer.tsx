import React, { useState } from 'react';
import { StoreInventoryActivation } from '../../types';
import { MarketplaceLogo } from './MarketplaceLogo';
import {
  X,
  Store,
  CheckCircle2,
  AlertTriangle,
  MapPin,
  Layers,
  ShieldCheck,
  Info,
  Clock,
  Settings,
  Save,
  ChevronRight
} from 'lucide-react';

interface StoreActivationsDrawerProps {
  isOpen: boolean;
  onClose: () => void;
  storeActivations: StoreInventoryActivation[];
  onSaveActivation: (activation: StoreInventoryActivation) => void;
}

export const StoreActivationsDrawer: React.FC<StoreActivationsDrawerProps> = ({
  isOpen,
  onClose,
  storeActivations = [],
  onSaveActivation
}) => {
  const [editingActivation, setEditingActivation] = useState<StoreInventoryActivation | null>(null);

  const handleToggleSyncState = (act: StoreInventoryActivation) => {
    const nextState = act.syncState === 'enabled' ? 'disabled' : 'enabled';
    const updated: StoreInventoryActivation = {
      ...act,
      syncState: nextState
    };
    onSaveActivation(updated);
  };

  const handleSaveBuffer = (act: StoreInventoryActivation, bufferVal: number) => {
    const updated: StoreInventoryActivation = {
      ...act,
      safetyBufferPercent: bufferVal
    };
    onSaveActivation(updated);
    setEditingActivation(null);
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex justify-end bg-black/60 backdrop-blur-xs">
      <div className="w-full max-w-xl bg-white dark:bg-neutral-900 h-full shadow-2xl flex flex-col border-l border-neutral-200 dark:border-neutral-800 overflow-hidden animate-in slide-in-from-right duration-200">
        
        {/* Header */}
        <div className="p-6 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between bg-neutral-50 dark:bg-neutral-950/60">
          <div className="flex items-center gap-3">
            <div className="p-2.5 rounded-xl bg-neutral-100 dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200 border border-neutral-200 dark:border-neutral-700">
              <Store className="w-5 h-5" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h3 className="font-bold text-sm text-neutral-900 dark:text-white">
                  Store Inventory Activations & Sync State
                </h3>
                <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300">
                  Release 1
                </span>
              </div>
              <p className="text-xs text-neutral-500">
                Connected Shopee & Lazada multi-store channel warehouse mappings
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-800 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 cursor-pointer"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Operational Guidance Notice */}
        <div className="mx-6 mt-5 p-3.5 rounded-xl bg-neutral-100 dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 text-neutral-700 dark:text-neutral-300 text-xs flex items-start gap-2.5">
          <Info className="w-4 h-4 text-neutral-500 shrink-0 mt-0.5" />
          <div className="space-y-1">
            <p className="font-bold text-neutral-900 dark:text-white">Connected Store vs Inventory Write Permissions</p>
            <p className="text-[11px] text-neutral-500 dark:text-neutral-400 leading-relaxed">
              A connected OAuth store does not imply enabled inventory writes. Each store requires active reconciliation, verified physical stock, and an active store inventory write toggle.
            </p>
          </div>
        </div>

        {/* Store Activations List */}
        <div className="p-6 overflow-y-auto space-y-4 text-xs flex-1">
          {storeActivations.map(act => {
            const isEditing = editingActivation?.connectionId === act.connectionId;
            const isExpired = act.syncState === 'auth_expired';
            const isEnabled = act.syncState === 'enabled';

            return (
              <div
                key={act.connectionId}
                className="p-5 rounded-2xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 space-y-4"
              >
                {/* Store Header */}
                <div className="flex items-start justify-between gap-3">
                  <div className="flex items-start gap-3">
                    <div className="p-2 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shrink-0">
                      <MarketplaceLogo platform={act.platform} size={24} />
                    </div>
                    <div>
                      <div className="flex items-center gap-2">
                        <span className="font-bold text-sm text-neutral-900 dark:text-white">
                          {act.storeName}
                        </span>
                        <span className="text-[10px] uppercase font-mono px-1.5 py-0.5 rounded bg-neutral-200 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400">
                          {act.platform}
                        </span>
                      </div>
                      <p className="text-[11px] text-neutral-500 font-mono mt-0.5">
                        Connection: {act.connectionId}
                      </p>
                    </div>
                  </div>

                  {/* Sync State Badge */}
                  <span
                    className={`text-xs font-bold px-2.5 py-1 rounded-full border ${
                      isEnabled
                        ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20'
                        : isExpired
                        ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20'
                        : act.syncState === 'awaiting_reconciliation'
                        ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20'
                        : 'bg-neutral-200 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 border-neutral-300 dark:border-neutral-700'
                    }`}
                  >
                    {isExpired ? 'OAuth Expired' : act.syncState === 'enabled' ? 'Writes Active' : act.syncState}
                  </span>
                </div>

                {/* Channel Warehouse Location */}
                <div className="p-3 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 flex items-center justify-between text-[11px]">
                  <div className="flex items-center gap-2 text-neutral-700 dark:text-neutral-300">
                    <MapPin className="w-3.5 h-3.5 text-neutral-400" />
                    <div>
                      <span className="text-neutral-400 block text-[10px]">Channel Warehouse Destination</span>
                      <strong className="font-semibold text-neutral-900 dark:text-white">{act.channelWarehouseLocationName}</strong>
                    </div>
                  </div>
                  <span className="font-mono text-[10px] text-neutral-400">{act.channelWarehouseLocationId}</span>
                </div>

                {/* Metrics Breakdown Grid */}
                <div className="grid grid-cols-3 gap-2 text-center text-[11px]">
                  <div className="p-2.5 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800">
                    <span className="text-[10px] text-neutral-400 block font-bold uppercase">Mapped SKUs</span>
                    <span className="text-base font-black text-neutral-900 dark:text-white">{act.mappedSkuCount}</span>
                    <span className="text-[10px] text-neutral-400 block">{act.unmappedSkuCount} unmapped</span>
                  </div>
                  <div className="p-2.5 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800">
                    <span className="text-[10px] text-neutral-400 block font-bold uppercase">Open Orders</span>
                    <span className="text-base font-black text-amber-600 dark:text-amber-400">{act.openOrdersCount}</span>
                    <span className="text-[10px] text-neutral-400 block">Deducting ATP</span>
                  </div>
                  <div className="p-2.5 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800">
                    <span className="text-[10px] text-neutral-400 block font-bold uppercase">Pre-Act Excluded</span>
                    <span className="text-base font-black text-neutral-500 dark:text-neutral-400">{act.historicalOrdersExcludedCount}</span>
                    <span className="text-[10px] text-neutral-400 block">No stock deduct</span>
                  </div>
                </div>

                {/* Historical Orders Exclusion Explanation */}
                {act.historicalOrdersExcludedCount > 0 && (
                  <div className="p-2.5 rounded-xl bg-neutral-100/70 dark:bg-neutral-900/60 border border-neutral-200 dark:border-neutral-800 text-[11px] text-neutral-500 leading-snug">
                    <p>
                      <strong>{act.historicalOrdersExcludedCount} historical orders</strong> predating activation were imported for reporting only. No physical warehouse stock was deducted.
                    </p>
                  </div>
                )}

                {/* Controls & Buffer Config */}
                <div className="pt-2 border-t border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <span className="text-[11px] text-neutral-500">Safety Buffer:</span>
                    <span className="font-mono font-bold text-neutral-900 dark:text-white text-xs">
                      {act.safetyBufferPercent}%
                    </span>
                  </div>

                  <div className="flex items-center gap-2">
                    <button
                      type="button"
                      disabled={isExpired}
                      onClick={() => handleToggleSyncState(act)}
                      className={`px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer ${
                        isEnabled
                          ? 'bg-neutral-200 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-300 dark:hover:bg-neutral-700'
                          : 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100 shadow-xs'
                      }`}
                    >
                      {isEnabled ? 'Disable Inventory Writes' : 'Enable Inventory Writes'}
                    </button>
                  </div>
                </div>

              </div>
            );
          })}
        </div>

      </div>
    </div>
  );
};
