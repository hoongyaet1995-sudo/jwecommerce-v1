import React, { useState } from 'react';
import { InventoryOrderException } from '../../types';
import { MarketplaceLogo } from './MarketplaceLogo';
import {
  X,
  AlertTriangle,
  AlertCircle,
  HelpCircle,
  Clock,
  ExternalLink,
  Filter,
  CheckCircle2,
  FileSpreadsheet
} from 'lucide-react';

interface InventoryOrderExceptionsDrawerProps {
  isOpen: boolean;
  onClose: () => void;
  exceptions: InventoryOrderException[];
}

export const InventoryOrderExceptionsDrawer: React.FC<InventoryOrderExceptionsDrawerProps> = ({
  isOpen,
  onClose,
  exceptions = []
}) => {
  const [activeFilter, setActiveFilter] = useState<'all' | 'unmapped_sku' | 'insufficient_stock' | 'predates_activation'>('all');

  const filtered = exceptions.filter(ex => {
    if (activeFilter === 'all') return true;
    return ex.type === activeFilter;
  });

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex justify-end bg-black/60 backdrop-blur-xs">
      <div className="w-full max-w-xl bg-white dark:bg-neutral-900 h-full shadow-2xl flex flex-col border-l border-neutral-200 dark:border-neutral-800 overflow-hidden animate-in slide-in-from-right duration-200">
        
        {/* Header */}
        <div className="p-6 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between bg-neutral-50 dark:bg-neutral-950/60">
          <div className="flex items-center gap-3">
            <div className="p-2.5 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
              <AlertTriangle className="w-5 h-5" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h3 className="font-bold text-sm text-neutral-900 dark:text-white">
                  Inventory Order Exceptions & Deductions
                </h3>
                <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300">
                  {exceptions.length} Total
                </span>
              </div>
              <p className="text-xs text-neutral-500">
                Audit logs for stock reservations, unmapped SKUs, and pre-activation orders
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

        {/* Filter Pills */}
        <div className="px-6 py-3 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-950/30 flex items-center gap-1.5 overflow-x-auto text-xs">
          {[
            { id: 'all', label: 'All Exceptions' },
            { id: 'predates_activation', label: 'Historical Pre-Activation' },
            { id: 'unmapped_sku', label: 'Unmapped SKUs' },
            { id: 'insufficient_stock', label: 'Stock Insufficient' }
          ].map(f => (
            <button
              key={f.id}
              onClick={() => setActiveFilter(f.id as any)}
              className={`px-3 py-1.5 rounded-xl font-bold whitespace-nowrap transition-all cursor-pointer ${
                activeFilter === f.id
                  ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 shadow-xs'
                  : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-200/60 dark:hover:bg-neutral-800'
              }`}
            >
              {f.label}
            </button>
          ))}
        </div>

        {/* List of Exceptions */}
        <div className="p-6 overflow-y-auto space-y-4 text-xs flex-1">
          {filtered.length === 0 ? (
            <div className="p-12 text-center text-neutral-400">
              <CheckCircle2 className="w-8 h-8 mx-auto mb-2 text-neutral-300 dark:text-neutral-700" />
              <p className="font-semibold">No order exceptions in this category</p>
            </div>
          ) : (
            filtered.map(ex => {
              const isHistorical = ex.type === 'predates_activation';
              const isUnmapped = ex.type === 'unmapped_sku';
              const isInsufficient = ex.type === 'insufficient_stock';

              return (
                <div
                  key={ex.id}
                  className="p-4 rounded-2xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 space-y-3"
                >
                  <div className="flex items-start justify-between gap-3">
                    <div className="flex items-start gap-2.5">
                      <div className="p-1.5 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shrink-0">
                        <MarketplaceLogo platform={ex.platform} size={18} />
                      </div>
                      <div>
                        <div className="flex items-center gap-2">
                          <span className="font-mono font-bold text-neutral-900 dark:text-white">
                            {ex.orderNumber}
                          </span>
                          <span className="text-[10px] text-neutral-500 font-medium">
                            {ex.storeName}
                          </span>
                        </div>
                        <p className="text-[11px] text-neutral-400 font-mono">
                          {new Date(ex.placedAt).toLocaleString()}
                        </p>
                      </div>
                    </div>

                    <span
                      className={`text-[10px] font-bold px-2 py-0.5 rounded-full border ${
                        isHistorical
                          ? 'bg-neutral-200 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 border-neutral-300 dark:border-neutral-700'
                          : isUnmapped
                          ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/20'
                          : 'bg-rose-500/10 text-rose-700 dark:text-rose-400 border-rose-500/20'
                      }`}
                    >
                      {isHistorical ? 'Excluded from WMS' : isUnmapped ? 'Unmapped SKU' : 'Stock Depleted'}
                    </span>
                  </div>

                  {/* Channel item info */}
                  <div className="p-2.5 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 space-y-1">
                    <div className="flex items-center justify-between text-[11px]">
                      <span className="font-semibold text-neutral-800 dark:text-neutral-200 line-clamp-1">
                        {ex.channelItemTitle}
                      </span>
                      <span className="font-bold text-neutral-900 dark:text-white shrink-0 ml-2">
                        Qty: {ex.requestedQuantity}
                      </span>
                    </div>
                    <div className="flex items-center justify-between text-[10px] text-neutral-500 font-mono">
                      <span>Channel SKU: {ex.channelSku}</span>
                      {ex.availableAtp !== null && <span>Available ATP: {ex.availableAtp}</span>}
                    </div>
                  </div>

                  {/* Explanation Banner */}
                  <div className="p-2.5 rounded-xl bg-neutral-100/80 dark:bg-neutral-900/60 border border-neutral-200 dark:border-neutral-800 text-[11px] text-neutral-600 dark:text-neutral-400 leading-relaxed">
                    <p>{ex.explanation}</p>
                  </div>
                </div>
              );
            })
          )}
        </div>

      </div>
    </div>
  );
};
