import React, { useState } from 'react';
import { MarketplaceInventoryItem, StoreInventoryActivation, StoreBroadcastResult } from '../../types';
import { MarketplaceLogo } from './MarketplaceLogo';
import {
  RefreshCw,
  X,
  AlertTriangle,
  CheckCircle2,
  XCircle,
  Clock,
  Layers,
  MapPin,
  ExternalLink,
  ShieldAlert,
  ArrowRight
} from 'lucide-react';

interface InventoryStoreBroadcastModalProps {
  item: MarketplaceInventoryItem | null;
  isOpen: boolean;
  onClose: () => void;
  storeActivations: StoreInventoryActivation[];
  onBroadcastToStore: (masterSku: string, connectionId: string) => Promise<StoreBroadcastResult>;
  onBroadcastToAllStores: (masterSku: string) => Promise<StoreBroadcastResult[]>;
  onOpenAuditModal: (item: MarketplaceInventoryItem) => void;
}

export const InventoryStoreBroadcastModal: React.FC<InventoryStoreBroadcastModalProps> = ({
  item,
  isOpen,
  onClose,
  storeActivations = [],
  onBroadcastToStore,
  onBroadcastToAllStores,
  onOpenAuditModal
}) => {
  const isUnknownPhysical = Boolean(item && (item.physicalStockStatus === 'unknown' || item.totalPhysicalStock === null));
  const [loadingConnectionId, setLoadingConnectionId] = useState<string | null>(null);
  const [isBroadcastingAll, setIsBroadcastingAll] = useState<boolean>(false);
  const [resultsMap, setResultsMap] = useState<Record<string, StoreBroadcastResult>>({});

  const associations = item?.storeAssociations || [];

  const handlePushSingle = async (connectionId: string) => {
    if (!item) return;
    setLoadingConnectionId(connectionId);
    try {
      const res = await onBroadcastToStore(item.masterSku, connectionId);
      setResultsMap(prev => ({ ...prev, [connectionId]: res }));
    } finally {
      setLoadingConnectionId(null);
    }
  };

  const handlePushAll = async () => {
    if (!item) return;
    setIsBroadcastingAll(true);
    try {
      const results = await onBroadcastToAllStores(item.masterSku);
      const newMap: Record<string, StoreBroadcastResult> = {};
      results.forEach(r => {
        newMap[r.connectionId] = r;
      });
      setResultsMap(prev => ({ ...prev, ...newMap }));
    } finally {
      setIsBroadcastingAll(false);
    }
  };

  if (!isOpen || !item) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/70 backdrop-blur-xs overflow-y-auto">
      <div className="relative w-full max-w-2xl max-h-[calc(100dvh-1.5rem)] sm:max-h-[calc(100dvh-2.5rem)] my-auto flex flex-col bg-white dark:bg-neutral-900 rounded-2xl shadow-2xl border border-neutral-200 dark:border-neutral-800 overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        
        {/* Header - Pinned */}
        <div className="p-4 sm:p-5 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950/60 flex items-center justify-between shrink-0">
          <div className="flex items-center gap-3">
            <div className="p-2.5 rounded-xl bg-neutral-100 dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200 border border-neutral-200 dark:border-neutral-700">
              <RefreshCw className={`w-5 h-5 ${isBroadcastingAll ? 'animate-spin' : ''}`} />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                  Multi-Store Inventory Broadcast
                </h3>
                <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 border border-neutral-200 dark:border-neutral-700">
                  Release 1: Shopee & Lazada
                </span>
              </div>
              <p className="text-xs text-neutral-500 font-mono mt-0.5">
                {item.masterSku} • {item.productName} ({item.variantName})
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

        {/* SKU Metrics Summary Strip - Pinned */}
        <div className="p-3 sm:p-4 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-100/50 dark:bg-neutral-950/30 grid grid-cols-4 gap-2 text-center text-xs shrink-0">
          <div className="p-2 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800">
            <span className="text-[10px] uppercase tracking-wider text-neutral-400 block font-bold">Physical Stock</span>
            <span className={`text-base font-black ${isUnknownPhysical ? 'text-amber-600 dark:text-amber-400' : 'text-neutral-900 dark:text-white'}`}>
              {item.totalPhysicalStock !== null ? `${item.totalPhysicalStock}` : 'Unverified'}
            </span>
          </div>
          <div className="p-2 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800">
            <span className="text-[10px] uppercase tracking-wider text-neutral-400 block font-bold">Reserved</span>
            <span className="text-base font-black text-amber-600 dark:text-amber-400">
              {item.reservedStock}
            </span>
          </div>
          <div className="p-2 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800">
            <span className="text-[10px] uppercase tracking-wider text-neutral-400 block font-bold">Safety Buffer</span>
            <span className="text-base font-black text-neutral-600 dark:text-neutral-400">
              {item.safetyStock}
            </span>
          </div>
          <div className="p-2 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800">
            <span className="text-[10px] uppercase tracking-wider text-neutral-400 block font-bold">ATP Live Stock</span>
            <span className={`text-base font-black ${isUnknownPhysical ? 'text-neutral-400' : 'text-emerald-600 dark:text-emerald-400'}`}>
              {item.availableStock !== null ? `${item.availableStock}` : 'Pending Audit'}
            </span>
          </div>
        </div>

        {/* Scrollable Content (Warning + Store Associations) */}
        <div className="flex-1 min-h-0 overflow-y-auto">
          {/* Warning if unverified physical stock */}
          {isUnknownPhysical && (
            <div className="m-4 sm:m-5 p-4 rounded-xl bg-amber-500/10 border border-amber-500/25 text-amber-700 dark:text-amber-300 text-xs flex items-start justify-between gap-3">
              <div className="flex items-start gap-2.5">
                <ShieldAlert className="w-5 h-5 shrink-0 text-amber-500 mt-0.5" />
                <div className="space-y-1">
                  <p className="font-bold">Outbound Stock Writes Disabled</p>
                  <p className="text-[11px] opacity-90 leading-relaxed">
                    Physical stock is marked as <strong>Unverified / Awaiting Count</strong>. No platform snapshot may initialize physical stock automatically. Complete a physical warehouse audit to enable inventory push.
                  </p>
                </div>
              </div>
              <button
                onClick={() => {
                  onClose();
                  onOpenAuditModal(item);
                }}
                className="shrink-0 px-3 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs shadow-xs cursor-pointer flex items-center gap-1"
              >
                <span>Record Audit</span>
                <ArrowRight className="w-3.5 h-3.5" />
              </button>
            </div>
          )}

          {/* Store Associations List */}
          <div className="p-4 sm:p-5 space-y-3">
            <div className="flex items-center justify-between text-xs text-neutral-500 px-1">
              <span className="font-bold uppercase tracking-wider text-[10px]">
                Connected Store Associations ({associations.length})
              </span>
              <span className="text-[11px]">Pushing ATP: <strong>{item.availableStock ?? 0} units</strong></span>
            </div>

          {associations.length === 0 ? (
            <div className="p-8 text-center text-neutral-400 border border-dashed border-neutral-200 dark:border-neutral-800 rounded-xl">
              <p className="font-semibold text-xs">No store SKU associations configured for this item.</p>
              <p className="text-[11px] mt-1">Publish this SKU to your Shopee or Lazada stores via Product Mapping.</p>
            </div>
          ) : (
            associations.map(assoc => {
              const activation = storeActivations.find(a => a.connectionId === assoc.connectionId);
              const result = resultsMap[assoc.connectionId];
              const isLoading = loadingConnectionId === assoc.connectionId;

              // Write permission evaluation
              const isSyncEnabled = activation ? activation.syncState === 'enabled' : false;
              const isExpired = assoc.connectionState === 'expired' || (activation && activation.syncState === 'auth_expired');
              const canWrite = !isUnknownPhysical && isSyncEnabled && !isExpired;

              let blockReason = '';
              if (isUnknownPhysical) {
                blockReason = 'Physical count unverified';
              } else if (isExpired) {
                blockReason = 'OAuth token expired — Re-authenticate store';
              } else if (activation && activation.syncState === 'awaiting_reconciliation') {
                blockReason = 'Awaiting initial catalog reconciliation';
              } else if (!isSyncEnabled) {
                blockReason = 'Inventory writes disabled in store activation';
              }

              return (
                <div
                  key={assoc.connectionId}
                  className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 space-y-3"
                >
                  <div className="flex items-start justify-between gap-3">
                    <div className="flex items-start gap-2.5">
                      <div className="p-2 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shrink-0">
                        <MarketplaceLogo platform={assoc.platform} size={22} />
                      </div>
                      <div className="space-y-0.5">
                        <div className="flex items-center gap-2">
                          <span className="font-bold text-xs text-neutral-900 dark:text-white">
                            {assoc.storeName}
                          </span>
                          {assoc.storeNickname && (
                            <span className="text-[10px] px-1.5 py-0.2 rounded bg-neutral-200 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 font-mono">
                              {assoc.storeNickname}
                            </span>
                          )}
                          <span
                            className={`text-[10px] font-bold px-2 py-0.5 rounded-full ${
                              isSyncEnabled && !isExpired
                                ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'
                                : isExpired
                                ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20'
                                : 'bg-neutral-200 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400'
                            }`}
                          >
                            {isExpired ? 'Auth Expired' : activation?.syncState || 'Disabled'}
                          </span>
                        </div>

                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-neutral-500 dark:text-neutral-400 font-mono pt-0.5">
                          <span>Channel SKU: <strong className="text-neutral-800 dark:text-neutral-200">{assoc.externalSku}</strong></span>
                          {assoc.externalItemId && <span>Item ID: {assoc.externalItemId}</span>}
                          {assoc.channelWarehouseLocation && (
                            <span className="flex items-center gap-1 text-neutral-600 dark:text-neutral-400">
                              <MapPin className="w-3 h-3 text-neutral-400" />
                              {assoc.channelWarehouseLocation}
                            </span>
                          )}
                        </div>
                      </div>
                    </div>

                    {/* Single Push Button */}
                    <div className="shrink-0 text-right">
                      <button
                        type="button"
                        disabled={!canWrite || isLoading || isBroadcastingAll}
                        onClick={() => handlePushSingle(assoc.connectionId)}
                        className="px-3 py-1.5 bg-neutral-900 dark:bg-white hover:bg-neutral-800 dark:hover:bg-neutral-100 disabled:opacity-30 disabled:cursor-not-allowed text-white dark:text-neutral-900 text-xs font-bold rounded-xl shadow-xs transition-all flex items-center gap-1.5 cursor-pointer"
                        title={blockReason}
                      >
                        <RefreshCw className={`w-3 h-3 ${isLoading ? 'animate-spin' : ''}`} />
                        <span>Push Stock</span>
                      </button>
                    </div>
                  </div>

                  {/* Stock Snapshot & Last Pushed Info */}
                  <div className="pt-2 border-t border-neutral-200 dark:border-neutral-800 flex flex-wrap items-center justify-between text-[11px] text-neutral-500">
                    <div className="flex items-center gap-3">
                      <span>Channel Catalog Read: <strong className="text-neutral-700 dark:text-neutral-300">{assoc.channelStockSnapshot ?? 'None'}</strong></span>
                      <span>Last Confirmed Pushed: <strong className="text-emerald-600 dark:text-emerald-400">{assoc.lastConfirmedPushedQuantity ?? 'None'}</strong></span>
                    </div>
                    {assoc.lastConfirmedPushedAt && (
                      <span className="text-[10px] text-neutral-400">
                        {new Date(assoc.lastConfirmedPushedAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                      </span>
                    )}
                  </div>

                  {/* Block explanation warning */}
                  {!canWrite && (
                    <div className="p-2 rounded-lg bg-neutral-100 dark:bg-neutral-900 text-neutral-600 dark:text-neutral-400 text-[11px] flex items-center gap-1.5">
                      <AlertTriangle className="w-3.5 h-3.5 text-amber-500 shrink-0" />
                      <span>{blockReason}</span>
                    </div>
                  )}

                  {/* Live Result feedback banner */}
                  {result && (
                    <div
                      className={`p-2.5 rounded-lg text-xs flex items-center justify-between gap-2 ${
                        result.status === 'confirmed'
                          ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/20'
                          : 'bg-rose-500/10 text-rose-700 dark:text-rose-300 border border-rose-500/20'
                      }`}
                    >
                      <div className="flex items-center gap-2">
                        {result.status === 'confirmed' ? (
                          <CheckCircle2 className="w-4 h-4 text-emerald-500 shrink-0" />
                        ) : (
                          <XCircle className="w-4 h-4 text-rose-500 shrink-0" />
                        )}
                        <span className="font-medium text-[11px]">
                          {result.status === 'confirmed'
                            ? `Pushed ${result.pushedQuantity} units confirmed (${result.latencyMs ?? 140}ms)`
                            : result.errorMessage}
                        </span>
                      </div>
                      {result.confirmedAt && (
                        <span className="text-[10px] opacity-75 font-mono">
                          {new Date(result.confirmedAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
                        </span>
                      )}
                    </div>
                  )}
                </div>
              );
            })
          )}
          </div>
        </div>

        {/* Footer with Mass Push - Pinned */}
        <div className="p-3.5 sm:px-6 sm:py-3.5 border-t border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950/60 flex items-center justify-between shrink-0">
          <button
            type="button"
            onClick={onClose}
            className="px-4 py-2 text-xs font-semibold text-neutral-600 dark:text-neutral-400 hover:bg-neutral-200 dark:hover:bg-neutral-800 rounded-xl cursor-pointer transition-colors"
          >
            Close
          </button>

          <button
            type="button"
            disabled={isUnknownPhysical || isBroadcastingAll || associations.length === 0}
            onClick={handlePushAll}
            className="px-5 py-2.5 bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 disabled:opacity-30 disabled:cursor-not-allowed hover:bg-neutral-800 dark:hover:bg-neutral-100 text-xs font-bold rounded-xl shadow-xs flex items-center gap-2 cursor-pointer transition-all"
          >
            <RefreshCw className={`w-3.5 h-3.5 ${isBroadcastingAll ? 'animate-spin' : ''}`} />
            <span>Broadcast to All Eligible Stores</span>
          </button>
        </div>

      </div>
    </div>
  );
};
