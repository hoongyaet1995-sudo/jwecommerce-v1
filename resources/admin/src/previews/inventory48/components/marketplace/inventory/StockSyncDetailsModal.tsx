import React, { useState, useEffect } from 'react';
import {
  X,
  CheckCircle2,
  AlertTriangle,
  RefreshCw,
  Clock,
  ShieldAlert,
  ArrowRight,
  ExternalLink,
  Info,
  RotateCcw,
  Check,
  Building2,
  Store,
  Layers,
  Link2,
  FileText
} from 'lucide-react';
import { MarketplaceInventoryItem } from '../../../types';
import { InventoryStoreChannel } from './inventoryTypes';
import { OperationalSyncStatus, StoreSyncItemState } from './stockSyncTypes';
import { MarketplaceLogo } from '../MarketplaceLogo';

interface StockSyncDetailsModalProps {
  isOpen: boolean;
  onClose: () => void;
  item: MarketplaceInventoryItem | null;
  store: InventoryStoreChannel | null;
  syncInfo: StoreSyncItemState | null;
  onUpdateSyncInfo?: (updated: StoreSyncItemState) => void;
  onCheckStatus?: () => Promise<StoreSyncItemState>;
  onResync?: () => Promise<StoreSyncItemState>;
  onNavigateToSkuMapping?: (sku: string, storeId: string) => void;
  onNavigateToStoreSettings?: (storeId: string) => void;
  onNavigateToHistory?: (sku: string) => void;
  facilities?: Array<{id:number;name:string;providerId:string}>;
  selectedFacilityId?: number | null;
  onSelectFacility?: (id:number) => Promise<void>;
}

type ModalViewMode = 'detail' | 'confirm_resync' | 'executing' | 'success';

export const StockSyncDetailsModal: React.FC<StockSyncDetailsModalProps> = ({
  isOpen,
  onClose,
  item,
  store,
  syncInfo: initialSyncInfo,
  onUpdateSyncInfo,
  onCheckStatus,
  onResync,
  onNavigateToSkuMapping,
  onNavigateToStoreSettings,
  onNavigateToHistory,
  facilities = [],
  selectedFacilityId = null,
  onSelectFacility
}) => {
  const [currentSync, setCurrentSync] = useState<StoreSyncItemState | null>(null);
  const [viewMode, setViewMode] = useState<ModalViewMode>('detail');
  const [executingStep, setExecutingStep] = useState<'sending' | 'verifying'>('sending');
  const [isCheckingStatus, setIsCheckingStatus] = useState<boolean>(false);
  const [statusCheckFeedback, setStatusCheckFeedback] = useState<string | null>(null);
  const [selectingFacility, setSelectingFacility] = useState(false);

  // Sync state when props change
  useEffect(() => {
    if (isOpen && initialSyncInfo) {
      setCurrentSync({ ...initialSyncInfo });
      setViewMode('detail');
      setExecutingStep('sending');
      setIsCheckingStatus(false);
      setStatusCheckFeedback(null);
    }
  }, [isOpen, initialSyncInfo]);

  if (!isOpen || !item || !store || !currentSync) {
    return null;
  }

  const isWebstore = store.platform === 'webstore' || store.id === 'webstore';
  const effectiveStatus = isWebstore ? 'jw_internal' : currentSync.status;

  // Handle "Check Status" (performs readback only without resending)
  const handleCheckStatus = async () => {
    setIsCheckingStatus(true);
    setStatusCheckFeedback(null);
    try {
      if(!onCheckStatus) throw new Error('Provider status check is unavailable.');
      const updated=await onCheckStatus();
      setCurrentSync(updated);onUpdateSyncInfo?.(updated);
      setStatusCheckFeedback(updated.status==='synced'
        ? 'Provider readback confirmed this stock balance.'
        : 'Provider readback completed. Review the reported quantity and status.');
      if(updated.status==='synced') setViewMode('success');
    } catch(error:any) {
      setStatusCheckFeedback(error?.message||'Provider stock could not be checked.');
    } finally {setIsCheckingStatus(false)}
  };

  // Handle Resync Confirmation Trigger
  const handleStartResync = () => {
    setViewMode('confirm_resync');
  };

  // Handle Resync Execution
  const handleConfirmAndExecuteResync = async () => {
    setViewMode('executing');
    setExecutingStep('sending');
    try {
      if(!onResync) throw new Error('Provider resync is unavailable.');
      const updated=await onResync();
      setCurrentSync(updated);onUpdateSyncInfo?.(updated);
      if(updated.status==='synced')setViewMode('success');
      else {setViewMode('detail');setStatusCheckFeedback('Stock update queued. Await provider readback before treating it as synchronized.');}
    } catch(error:any) {
      setViewMode('detail');setStatusCheckFeedback(error?.message||'Provider resync failed.');
    }
  };

  // Status Badge Helper
  const getStatusBadge = (status: OperationalSyncStatus) => {
    switch (status) {
      case 'synced':
        return (
          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 text-xs font-semibold">
            <CheckCircle2 className="w-3.5 h-3.5" />
            <span>Synced</span>
          </span>
        );
      case 'not_synced':
        return (
          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 text-xs font-semibold">
            <AlertTriangle className="w-3.5 h-3.5" />
            <span>Not Synced</span>
          </span>
        );
      case 'awaiting_confirmation':
        return (
          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 text-xs font-semibold">
            <Clock className="w-3.5 h-3.5 animate-pulse" />
            <span>Awaiting Confirmation</span>
          </span>
        );
      case 'sync_failed':
        return (
          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20 text-xs font-semibold">
            <ShieldAlert className="w-3.5 h-3.5" />
            <span>Sync Failed</span>
          </span>
        );
      case 'auth_expired':
        return (
          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20 text-xs font-semibold">
            <ShieldAlert className="w-3.5 h-3.5" />
            <span>Authorization Expired</span>
          </span>
        );
      case 'sku_not_mapped':
        return (
          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 text-xs font-semibold">
            <Link2 className="w-3.5 h-3.5" />
            <span>SKU Not Mapped</span>
          </span>
        );
      case 'listing_not_eligible':
        return (
          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-neutral-200 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 border border-neutral-300 dark:border-neutral-700 text-xs font-semibold">
            <AlertTriangle className="w-3.5 h-3.5 text-neutral-500" />
            <span>Listing Not Eligible</span>
          </span>
        );
      case 'sync_replaced':
        return (
          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 text-xs font-semibold">
            <RotateCcw className="w-3.5 h-3.5" />
            <span>Replaced by Newer Stock</span>
          </span>
        );
      case 'verification_pending':
      case 'select_facility':
        return (
          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 text-xs font-semibold">
            <Clock className="w-3.5 h-3.5" />
            <span>{status === 'select_facility' ? 'Select Warehouse' : 'Verification Pending'}</span>
          </span>
        );
      case 'jw_internal':
      default:
        return (
          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 text-xs font-semibold">
            <CheckCircle2 className="w-3.5 h-3.5" />
            <span>JW Direct Ledger</span>
          </span>
        );
    }
  };

  return (
    <div
      id="stock-sync-details-modal-overlay"
      className="fixed inset-0 z-50 overflow-y-auto bg-neutral-950/75 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 animate-in fade-in duration-150"
    >
      <div
        id="stock-sync-details-modal-dialog"
        className="bg-white dark:bg-neutral-900 w-full max-w-xl rounded-2xl shadow-2xl border border-neutral-200 dark:border-neutral-800 flex flex-col overflow-hidden text-neutral-900 dark:text-white my-auto"
      >
        {/* ================= MODAL TOP HEADER ================= */}
        <div className="p-4 sm:p-5 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between gap-3">
          <div className="flex items-center gap-2.5">
            <div className="p-2 rounded-xl bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300">
              <RefreshCw className="w-4 h-4" />
            </div>
            <div>
              <h3 className="text-sm sm:text-base font-bold text-neutral-900 dark:text-white">
                {isWebstore ? 'Storefront Stock Ledger' : 'Stock Sync Details'}
              </h3>
              <p className="text-[11px] text-neutral-500 dark:text-neutral-400">
                {isWebstore
                  ? 'Internal direct storefront inventory status'
                  : 'Independent marketplace channel synchronization state'}
              </p>
            </div>
          </div>

          <button
            type="button"
            onClick={onClose}
            aria-label="Close modal"
            className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition cursor-pointer"
          >
            <X className="w-4 h-4" />
          </button>
        </div>

        {/* ================= PRODUCT & STORE BANNER ================= */}
        <div className="p-4 sm:p-5 bg-neutral-50 dark:bg-neutral-800/50 border-b border-neutral-200 dark:border-neutral-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
          <div className="flex items-center gap-3 min-w-0">
            <div className="w-12 h-12 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 overflow-hidden flex items-center justify-center shrink-0">
              {item.imageUrl ? (
                <img
                  src={item.imageUrl}
                  alt={item.productName}
                  className="w-full h-full object-cover"
                  referrerPolicy="no-referrer"
                />
              ) : (
                <Store className="w-5 h-5 text-neutral-400" />
              )}
            </div>
            <div className="min-w-0">
              <div className="font-mono font-bold text-neutral-900 dark:text-white text-xs">
                {item.masterSku}
              </div>
              <div className="font-semibold text-neutral-800 dark:text-neutral-200 truncate max-w-xs sm:max-w-sm mt-0.5">
                {item.productName}
              </div>
              <div className="text-[11px] text-neutral-500 dark:text-neutral-400 truncate">
                {item.variantName || 'Default Variant'}
              </div>
            </div>
          </div>

          <div className="flex flex-row sm:flex-col sm:items-end justify-between items-center gap-1.5 shrink-0 pt-2 sm:pt-0 border-t sm:border-t-0 border-neutral-200 dark:border-neutral-700/50">
            <div className="flex items-center gap-1.5">
              <MarketplaceLogo platform={store.platform as any} size="xs" />
              <span className="font-semibold text-neutral-900 dark:text-white text-xs">
                {store.nickname || store.name}
              </span>
            </div>
            <div>{getStatusBadge(effectiveStatus)}</div>
          </div>
        </div>

        {/* ================= MODAL BODY ================= */}
        <div className="p-5 overflow-y-auto space-y-4 max-h-[70vh]">
          {/* VIEW MODE 1: DETAIL / STATUS DISPLAY */}
          {viewMode === 'detail' && (
            <div className="space-y-4">
              {facilities.length > 0 && (
                <div className="p-4 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 space-y-2 text-xs">
                  <label htmlFor="sync-target-facility" className="font-bold text-neutral-700 dark:text-neutral-200">Target Warehouse / Location</label>
                  <select id="sync-target-facility" value={selectedFacilityId ?? ''} disabled={selectingFacility}
                    onChange={async event=>{
                      const id=Number(event.target.value);
                      if(!id||!onSelectFacility)return;
                      setSelectingFacility(true);setStatusCheckFeedback(null);
                      try {await onSelectFacility(id)}
                      catch(error:any){setStatusCheckFeedback(error?.message||'Warehouse stock status could not be read.')}
                      finally {setSelectingFacility(false)}
                    }}
                    className="w-full p-2 rounded-lg bg-neutral-50 dark:bg-neutral-800 border border-neutral-300 dark:border-neutral-700">
                    <option value="">Select a warehouse to inspect exact stock</option>
                    {facilities.map(facility=><option key={facility.id} value={facility.id}>{facility.name} ({facility.providerId})</option>)}
                  </select>
                </div>
              )}
              {(effectiveStatus==='verification_pending'||effectiveStatus==='select_facility') && (
                <div className="p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 text-xs text-amber-700 dark:text-amber-300">
                  {effectiveStatus==='verification_pending'
                    ? (currentSync.failureReason || 'Warehouse verification is pending. Stock check and resync are paused.')
                    : 'Select a verified warehouse before checking or resynchronizing stock.'}
                </div>
              )}
              {/* STOCK COMPARISON TABLE / CARDS */}
              {!isWebstore ? (
                <div className="p-4 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 space-y-3">
                  <div className="flex items-center justify-between text-[11px] font-bold uppercase tracking-wider text-neutral-400">
                    <span>Stock Comparison</span>
                    <span className="font-normal lowercase font-mono">absolute stock reconciliation</span>
                  </div>

                  <div className="grid grid-cols-3 gap-2.5 text-center">
                    <div className="p-3 rounded-lg bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700/60">
                      <span className="block text-[11px] text-neutral-500 dark:text-neutral-400 font-medium">
                        JW Available Stock
                      </span>
                      <span className="font-mono font-bold text-lg text-neutral-900 dark:text-white">
                        {currentSync.jwAvailableStock === null ? 'Not counted' : <>{currentSync.jwAvailableStock} <span className="text-xs font-normal text-neutral-400">units</span></>}
                      </span>
                    </div>

                    <div className="p-3 rounded-lg bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700/60">
                      <span className="block text-[11px] text-neutral-500 dark:text-neutral-400 font-medium">
                        Marketplace Stock
                      </span>
                      <span className="font-mono font-bold text-lg text-neutral-900 dark:text-white">
                        {currentSync.marketplaceReportedStock !== null ? (
                          <>
                            {currentSync.marketplaceReportedStock}{' '}
                            <span className="text-xs font-normal text-neutral-400">units</span>
                          </>
                        ) : (
                          <span className="text-sm font-semibold text-neutral-400 italic">Unknown</span>
                        )}
                      </span>
                    </div>

                    <div className="p-3 rounded-lg bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700/60">
                      <span className="block text-[11px] text-neutral-500 dark:text-neutral-400 font-medium">
                        Difference
                      </span>
                      <span
                        className={`font-mono font-bold text-lg ${
                          currentSync.difference === null
                            ? 'text-neutral-400'
                            : currentSync.difference === 0
                            ? 'text-emerald-600 dark:text-emerald-400'
                            : 'text-amber-600 dark:text-amber-400'
                        }`}
                      >
                        {currentSync.difference !== null ? (
                          `${currentSync.difference > 0 ? `+${currentSync.difference}` : currentSync.difference} units`
                        ) : (
                          <span className="text-sm font-normal">—</span>
                        )}
                      </span>
                    </div>
                  </div>

                  {/* Timestamps */}
                  <div className="pt-2 border-t border-neutral-100 dark:border-neutral-800 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 text-xs text-neutral-500 dark:text-neutral-400">
                    <div className="flex items-center gap-1.5">
                      <Clock className="w-3.5 h-3.5 text-neutral-400" />
                      <span>
                        Last Confirmed Sync:{' '}
                        <strong className="text-neutral-700 dark:text-neutral-300">
                          {currentSync.lastConfirmedSyncAt || 'Never'}
                        </strong>
                      </span>
                    </div>
                    <div>
                      <span>
                        Last Checked:{' '}
                        <strong className="text-neutral-700 dark:text-neutral-300">
                          {currentSync.lastCheckedAt}
                        </strong>
                      </span>
                    </div>
                  </div>

                  {currentSync.marketplaceReportedStock === null && (
                    <div className="text-[11px] text-neutral-500 dark:text-neutral-400 italic">
                      Notice: Marketplace stock is currently unknown. It has never been read from the channel API and is not assumed to be zero.
                    </div>
                  )}
                </div>
              ) : (
                /* JW Direct Storefront Summary */
                <div className="p-4 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 space-y-3 text-xs">
                  <div className="flex items-center justify-between text-[11px] font-bold uppercase tracking-wider text-neutral-400">
                    <span>Direct Webstore Ledger Status</span>
                    <span className="font-normal lowercase font-mono">internal real-time ledger</span>
                  </div>

                  <div className="grid grid-cols-3 gap-2.5 text-center">
                    <div className="p-3 rounded-lg bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700/60">
                      <span className="block text-[11px] text-neutral-400">Physical Stock</span>
                      <span className="font-mono font-bold text-base text-neutral-900 dark:text-white">
                        {item.totalPhysicalStock ?? '—'} units
                      </span>
                    </div>
                    <div className="p-3 rounded-lg bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700/60">
                      <span className="block text-[11px] text-amber-500">Reserved (Locked)</span>
                      <span className="font-mono font-bold text-base text-amber-600 dark:text-amber-400">
                        {item.reservedStock ?? 0} units
                      </span>
                    </div>
                    <div className="p-3 rounded-lg bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700/60">
                      <span className="block text-[11px] text-emerald-500">Available to Sell</span>
                      <span className="font-mono font-bold text-base text-emerald-600 dark:text-emerald-400">
                        {currentSync.jwAvailableStock} ATP
                      </span>
                    </div>
                  </div>

                  <div className="p-3 rounded-lg bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-800 text-neutral-600 dark:text-neutral-300 space-y-1">
                    <p className="font-medium text-neutral-900 dark:text-white">Direct Ledger Architecture</p>
                    <p>
                      JW Storefront is Aurora Audio's official direct webstore. Customer orders reserve and deduct stock directly from the central JW inventory ledger in real-time. No external marketplace synchronization or third-party API dispatch is required.
                    </p>
                  </div>
                </div>
              )}

              {/* SPECIFIC OPERATIONAL STATUS PANELS */}

              {/* 1. SYNCED */}
              {effectiveStatus === 'synced' && (
                <div className="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 space-y-2 text-xs">
                  <div className="flex items-center gap-2 text-emerald-700 dark:text-emerald-300 font-bold">
                    <CheckCircle2 className="w-4 h-4" />
                    <span>Stock is synchronized</span>
                  </div>
                  <p className="text-emerald-800/90 dark:text-emerald-200/90">
                    The external marketplace reported stock matches the latest JW Available Stock ({currentSync.jwAvailableStock} units).
                    {currentSync.lastConfirmedSyncAt && ` Confirmed at ${currentSync.lastConfirmedSyncAt}.`}
                  </p>
                </div>
              )}

              {/* 2. NOT SYNCED */}
              {effectiveStatus === 'not_synced' && (
                <div className="p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 space-y-2 text-xs">
                  <div className="flex items-center gap-2 text-amber-700 dark:text-amber-300 font-bold">
                    <AlertTriangle className="w-4 h-4" />
                    <span>Marketplace stock does not match JW inventory</span>
                  </div>
                  <p className="text-amber-800/90 dark:text-amber-200/90">
                    JW inventory has <strong>{currentSync.jwAvailableStock ?? 'an uncounted quantity'}</strong> available to sell, while {store.nickname || store.name} currently reports <strong>{currentSync.marketplaceReportedStock ?? 'Unknown'}</strong>.
                  </p>
                  <p className="text-amber-700 dark:text-amber-300/80 text-[11px]">
                    {currentSync.canResync === true
                      ? 'Send the latest JW Available Stock to this store to reconcile channel availability.'
                      : (currentSync.failureReason || 'Stock synchronization is unavailable until this warehouse is counted and verified.')}
                  </p>
                </div>
              )}

              {/* 3. SYNC FAILED */}
              {effectiveStatus === 'sync_failed' && (
                <div className="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 space-y-2.5 text-xs">
                  <div className="flex items-center gap-2 text-rose-700 dark:text-rose-300 font-bold">
                    <ShieldAlert className="w-4 h-4" />
                    <span>Marketplace sync failed</span>
                  </div>
                  <div className="p-2.5 rounded-lg bg-white/60 dark:bg-neutral-900/60 border border-rose-200 dark:border-rose-900/40 text-neutral-800 dark:text-neutral-200 font-medium">
                    {currentSync.failureReason || 'The marketplace service is temporarily unavailable.'}
                  </div>
                  <div className="p-2 rounded bg-neutral-100 dark:bg-neutral-800/80 border border-neutral-200 dark:border-neutral-700 text-[11px] text-neutral-600 dark:text-neutral-400">
                    <strong>Ledger Integrity:</strong> JW inventory remains safe; {currentSync.jwAvailableStock === null ? 'this warehouse has not been counted yet' : `${currentSync.jwAvailableStock} units are recorded`}. External provider failures do not affect internal stock counts.
                  </div>
                  <p className="text-rose-700 dark:text-rose-300/80 text-[11px]">
                    What to do next: Verify connection status or retry sending the latest JW stock.
                  </p>
                </div>
              )}

              {/* 4. AWAITING CONFIRMATION */}
              {effectiveStatus === 'awaiting_confirmation' && (
                <div className="p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 space-y-2.5 text-xs">
                  <div className="flex items-center gap-2 text-amber-700 dark:text-amber-300 font-bold">
                    <Clock className="w-4 h-4 animate-spin" />
                    <span>Update sent. Waiting for marketplace confirmation.</span>
                  </div>
                  <div className="space-y-1 text-amber-800/90 dark:text-amber-200/90">
                    <p>
                      JW requested <strong>{currentSync.requestedQuantity ?? currentSync.jwAvailableStock} units</strong>. Last observed marketplace stock is <strong>{currentSync.marketplaceReportedStock ?? 'Unknown'}</strong>.
                    </p>
                    {currentSync.requestSentAt && (
                      <p className="text-[11px] text-neutral-500 dark:text-neutral-400">
                        Request sent: {currentSync.requestSentAt}
                      </p>
                    )}
                  </div>
                  <div className="p-2.5 rounded-lg bg-neutral-100 dark:bg-neutral-800/80 border border-neutral-200 dark:border-neutral-700 text-[11px] text-neutral-600 dark:text-neutral-400">
                    <strong>Notice:</strong> Shopee and Lazada may take several seconds to update downstream cached inventory. Checking the marketplace will perform readback only and will not send another duplicate stock update.
                  </div>
                </div>
              )}

              {/* 5. AUTHORIZATION EXPIRED */}
              {effectiveStatus === 'auth_expired' && (
                <div className="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 space-y-2.5 text-xs">
                  <div className="flex items-center gap-2 text-rose-700 dark:text-rose-300 font-bold">
                    <ShieldAlert className="w-4 h-4" />
                    <span>Store authorization expired</span>
                  </div>
                  <p className="text-rose-800/90 dark:text-rose-200/90">
                    This store must be connected or reauthorized before inventory can be synchronized.
                  </p>
                  <div className="p-2 rounded bg-neutral-100 dark:bg-neutral-800/80 border border-neutral-200 dark:border-neutral-700 text-[11px] text-neutral-600 dark:text-neutral-400">
                    <strong>Ledger Integrity:</strong> JW inventory remains safe. {currentSync.jwAvailableStock === null ? 'The count is not yet verified.' : `${currentSync.jwAvailableStock} units are recorded.`} Reconnect this store in Connect Store to resume synchronization.
                  </div>
                </div>
              )}

              {/* 6. SKU NOT MAPPED */}
              {effectiveStatus === 'sku_not_mapped' && (
                <div className="p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 space-y-2.5 text-xs">
                  <div className="flex items-center gap-2 text-amber-700 dark:text-amber-300 font-bold">
                    <Link2 className="w-4 h-4" />
                    <span>SKU not mapped in this store</span>
                  </div>
                  <p className="text-amber-800/90 dark:text-amber-200/90">
                    This JW SKU is not linked to a listing in this store. Stock cannot be synchronized until a channel item ID is mapped.
                  </p>
                  <div className="p-2 rounded bg-neutral-100 dark:bg-neutral-800/80 border border-neutral-200 dark:border-neutral-700 text-[11px] text-neutral-600 dark:text-neutral-400">
                    <strong>Ledger Integrity:</strong> JW inventory remains unchanged. {currentSync.jwAvailableStock === null ? 'The count is not yet verified.' : `${currentSync.jwAvailableStock} units are recorded.`} Map this SKU to start channel synchronization.
                  </div>
                </div>
              )}

              {/* 7. LISTING NOT ELIGIBLE FOR STOCK UPDATE */}
              {effectiveStatus === 'listing_not_eligible' && (
                <div className="p-4 rounded-xl bg-neutral-100 dark:bg-neutral-800/80 border border-neutral-200 dark:border-neutral-700 space-y-2.5 text-xs">
                  <div className="flex items-center gap-2 text-neutral-800 dark:text-neutral-200 font-bold">
                    <AlertTriangle className="w-4 h-4 text-amber-500" />
                    <span>Listing not eligible for stock update</span>
                  </div>
                  <p className="text-neutral-600 dark:text-neutral-300">
                    This marketplace listing is not currently accepting stock updates. The listing may be deleted, inactive, archived, or awaiting platform approval.
                  </p>
                  <p className="text-[11px] text-neutral-500 dark:text-neutral-400">
                    Manage the listing status in Shopee or Lazada Seller Center, then check again. JW Ecom does not manage product listing activation.
                  </p>
                  <div className="p-2 rounded bg-white/60 dark:bg-neutral-900/60 border border-neutral-200 dark:border-neutral-700/80 text-[11px] text-neutral-600 dark:text-neutral-400">
                    <strong>Ledger Integrity:</strong> JW inventory remains safely saved at {currentSync.jwAvailableStock} units.
                  </div>
                </div>
              )}

              {/* 8. SYNC REPLACED */}
              {effectiveStatus === 'sync_replaced' && (
                <div className="p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 space-y-2.5 text-xs">
                  <div className="flex items-center gap-2 text-amber-700 dark:text-amber-300 font-bold">
                    <RotateCcw className="w-4 h-4" />
                    <span>Replaced by Newer Stock</span>
                  </div>
                  <p className="text-amber-800/90 dark:text-amber-200/90">
                    This sync attempt used an older JW stock quantity. A newer inventory change replaced it before the marketplace update was sent.
                  </p>
                  <p className="text-amber-700 dark:text-amber-300/80 text-[11px]">
                    Current JW Available Stock is <strong>{currentSync.jwAvailableStock} units</strong>. Synchronize the latest stock to update this store.
                  </p>
                </div>
              )}

              {/* Status Check Feedback Toast/Message */}
              {statusCheckFeedback && (
                <div className="p-3 rounded-lg bg-neutral-100 dark:bg-neutral-800 text-xs text-neutral-700 dark:text-neutral-300 flex items-center gap-2 border border-neutral-200 dark:border-neutral-700 animate-in fade-in">
                  <Info className="w-4 h-4 text-neutral-400 shrink-0" />
                  <span>{statusCheckFeedback}</span>
                </div>
              )}
            </div>
          )}

          {/* VIEW MODE 2: CONFIRM RESYNC */}
          {viewMode === 'confirm_resync' && (
            <div className="space-y-4 text-xs animate-in fade-in duration-150">
              <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700 space-y-3">
                <div className="flex items-center gap-2 text-neutral-900 dark:text-white font-bold text-sm">
                  <RefreshCw className="w-4 h-4 text-neutral-500" />
                  <span>Send the latest JW Available Stock to this marketplace store?</span>
                </div>

                <p className="text-neutral-600 dark:text-neutral-300">
                  JW Ecom will send an absolute stock quantity of <strong>{currentSync.jwAvailableStock} units</strong>. This does not change the internal JW inventory ledger.
                </p>

                {/* Verification Summary Matrix */}
                <div className="grid grid-cols-2 gap-2 text-xs">
                  <div className="p-2.5 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700">
                    <span className="block text-[10px] text-neutral-400">Store Account</span>
                    <span className="font-semibold text-neutral-800 dark:text-neutral-200 truncate block">
                      {store.nickname || store.name}
                    </span>
                  </div>
                  <div className="p-2.5 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700">
                    <span className="block text-[10px] text-neutral-400">SKU</span>
                    <span className="font-mono font-bold text-neutral-800 dark:text-neutral-200 truncate block">
                      {item.masterSku}
                    </span>
                  </div>
                  <div className="p-2.5 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700">
                    <span className="block text-[10px] text-neutral-400">Current JW Available Stock</span>
                    <span className="font-mono font-bold text-neutral-900 dark:text-white">
                      {currentSync.jwAvailableStock} units
                    </span>
                  </div>
                  <div className="p-2.5 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700">
                    <span className="block text-[10px] text-neutral-400">Last Marketplace Quantity</span>
                    <span className="font-mono font-bold text-neutral-500">
                      {currentSync.marketplaceReportedStock !== null ? `${currentSync.marketplaceReportedStock} units` : 'Unknown'}
                    </span>
                  </div>
                </div>

                <div className="p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-[11px] text-emerald-800 dark:text-emerald-300 flex items-center justify-between">
                  <span>Quantity that will be sent:</span>
                  <span className="font-mono font-bold text-sm text-emerald-600 dark:text-emerald-400">
                    {currentSync.jwAvailableStock} units (Absolute)
                  </span>
                </div>
              </div>
            </div>
          )}

          {/* VIEW MODE 3: EXECUTING PROGRESS */}
          {viewMode === 'executing' && (
            <div className="py-8 space-y-6 text-center animate-in fade-in duration-150">
              <div className="w-12 h-12 rounded-2xl bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white mx-auto flex items-center justify-center shadow-xs">
                <RefreshCw className="w-6 h-6 animate-spin" />
              </div>

              <div className="space-y-1.5">
                <h4 className="text-sm font-bold text-neutral-900 dark:text-white">
                  {executingStep === 'sending' ? 'Sending stock update…' : 'Verifying marketplace stock…'}
                </h4>
                <p className="text-xs text-neutral-500 dark:text-neutral-400 max-w-sm mx-auto">
                  {executingStep === 'sending'
                    ? `Dispatching absolute quantity of ${currentSync.jwAvailableStock} units to ${store.nickname || store.name} via channel API.`
                    : 'Awaiting provider confirmation and performing independent stock readback.'}
                </p>
              </div>

              {/* Progress Steps Indicator */}
              <div className="max-w-xs mx-auto space-y-2 text-xs">
                <div className="flex items-center justify-between p-2.5 rounded-lg bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700">
                  <div className="flex items-center gap-2">
                    <CheckCircle2 className="w-4 h-4 text-emerald-500" />
                    <span className="font-medium text-neutral-800 dark:text-neutral-200">1. Internal Ledger</span>
                  </div>
                  <span className="text-[11px] font-bold text-emerald-600 dark:text-emerald-400">Preserved</span>
                </div>

                <div className="flex items-center justify-between p-2.5 rounded-lg bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700">
                  <div className="flex items-center gap-2">
                    {executingStep === 'sending' ? (
                      <RefreshCw className="w-4 h-4 animate-spin text-neutral-400" />
                    ) : (
                      <CheckCircle2 className="w-4 h-4 text-emerald-500" />
                    )}
                    <span className="font-medium text-neutral-800 dark:text-neutral-200">2. Marketplace API Call</span>
                  </div>
                  <span className="text-[11px] font-mono text-neutral-400">
                    {executingStep === 'sending' ? 'In flight' : 'Accepted'}
                  </span>
                </div>

                <div className="flex items-center justify-between p-2.5 rounded-lg bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700">
                  <div className="flex items-center gap-2">
                    {executingStep === 'verifying' ? (
                      <RefreshCw className="w-4 h-4 animate-spin text-neutral-400" />
                    ) : (
                      <Clock className="w-4 h-4 text-neutral-400" />
                    )}
                    <span className="font-medium text-neutral-800 dark:text-neutral-200">3. Provider Readback</span>
                  </div>
                  <span className="text-[11px] font-mono text-neutral-400">
                    {executingStep === 'verifying' ? 'Verifying...' : 'Pending'}
                  </span>
                </div>
              </div>
            </div>
          )}

          {/* VIEW MODE 4: SUCCESS */}
          {viewMode === 'success' && (
            <div className="space-y-4 text-xs animate-in fade-in duration-150">
              <div className="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-center space-y-2">
                <div className="w-10 h-10 rounded-full bg-emerald-500/20 text-emerald-500 mx-auto flex items-center justify-center">
                  <CheckCircle2 className="w-6 h-6" />
                </div>
                <h4 className="text-sm font-bold text-emerald-700 dark:text-emerald-300">
                  Stock synchronized
                </h4>
                <p className="text-emerald-800/90 dark:text-emerald-200/90 max-w-sm mx-auto">
                  {store.nickname || store.name} confirmed {currentSync.jwAvailableStock} units for {item.masterSku}.
                </p>
              </div>

              {/* Confirmation Details Card */}
              <div className="p-3.5 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 space-y-2 text-xs">
                <div className="flex justify-between items-center py-1 border-b border-neutral-100 dark:border-neutral-800">
                  <span className="text-neutral-500 dark:text-neutral-400">JW Available Stock</span>
                  <span className="font-mono font-bold text-neutral-900 dark:text-white">
                    {currentSync.jwAvailableStock} units
                  </span>
                </div>
                <div className="flex justify-between items-center py-1 border-b border-neutral-100 dark:border-neutral-800">
                  <span className="text-neutral-500 dark:text-neutral-400">Marketplace Confirmed Stock</span>
                  <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400">
                    {currentSync.jwAvailableStock} units
                  </span>
                </div>
                <div className="flex justify-between items-center py-1 border-b border-neutral-100 dark:border-neutral-800">
                  <span className="text-neutral-500 dark:text-neutral-400">Confirmed Time</span>
                  <span className="font-mono text-neutral-800 dark:text-neutral-200">
                    {currentSync.lastConfirmedSyncAt || 'Just now'}
                  </span>
                </div>
                <div className="flex justify-between items-center py-1">
                  <span className="text-neutral-500 dark:text-neutral-400">Confirmation Method</span>
                  <span className="font-semibold text-neutral-700 dark:text-neutral-300">
                    Marketplace readback
                  </span>
                </div>
              </div>
            </div>
          )}
        </div>

        {/* ================= MODAL FOOTER ACTIONS ================= */}
        <div className="p-4 sm:p-5 border-t border-neutral-200 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-800/40 flex flex-wrap items-center justify-between gap-3 text-xs">
          {/* VIEW MODE 1: DETAIL BUTTONS */}
          {viewMode === 'detail' && (
            <>
              <div>
                {/* Secondary Action on the left if applicable */}
                {effectiveStatus === 'listing_not_eligible' && (
                  <a
                    href="https://seller.shopee.com.my"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-700 dark:text-neutral-300 font-semibold inline-flex items-center gap-1.5 transition-colors cursor-pointer"
                  >
                    <span>Open Seller Center</span>
                    <ExternalLink className="w-3.5 h-3.5" />
                  </a>
                )}
                {effectiveStatus === 'auth_expired' && (
                  <button
                    type="button"
                    onClick={() => onNavigateToStoreSettings?.(store.id)}
                    className="px-3 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-semibold flex items-center gap-1.5 transition-colors cursor-pointer"
                  >
                    <ShieldAlert className="w-3.5 h-3.5" />
                    <span>Reconnect Store</span>
                  </button>
                )}
                {effectiveStatus === 'sku_not_mapped' && (
                  <button
                    type="button"
                    onClick={() => onNavigateToSkuMapping?.(item.masterSku, store.id)}
                    className="px-3 py-2 rounded-xl bg-neutral-900 hover:bg-neutral-800 dark:bg-white dark:hover:bg-neutral-100 text-white dark:text-neutral-950 font-bold flex items-center gap-1.5 transition-colors cursor-pointer"
                  >
                    <Link2 className="w-3.5 h-3.5" />
                    <span>Go to SKU Mapping</span>
                  </button>
                )}
              </div>

              <div className="flex items-center gap-2 ml-auto">
                <button
                  type="button"
                  onClick={onClose}
                  className="px-3.5 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-700 dark:text-neutral-300 font-semibold transition-colors cursor-pointer"
                >
                  Close
                </button>

                {/* Primary Action Button based on Status */}
                {effectiveStatus === 'synced' && (
                  <button
                    type="button"
                    disabled={isCheckingStatus || currentSync.canCheck !== true}
                    onClick={handleCheckStatus}
                    className="px-4 py-2 rounded-xl bg-neutral-900 hover:bg-neutral-800 dark:bg-white dark:hover:bg-neutral-100 text-white dark:text-neutral-950 font-bold transition-all shadow-xs flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
                  >
                    {isCheckingStatus ? (
                      <RefreshCw className="w-3.5 h-3.5 animate-spin" />
                    ) : (
                      <RefreshCw className="w-3.5 h-3.5" />
                    )}
                    <span>Check Status</span>
                  </button>
                )}

                {effectiveStatus === 'not_synced' && (
                  <button
                    type="button"
                    disabled={currentSync.canResync !== true}
                    onClick={handleStartResync}
                    className="px-4 py-2 rounded-xl bg-neutral-900 hover:bg-neutral-800 dark:bg-white dark:hover:bg-neutral-100 text-white dark:text-neutral-950 font-bold transition-all shadow-xs flex items-center gap-1.5 cursor-pointer disabled:opacity-40"
                  >
                    <RefreshCw className="w-3.5 h-3.5" />
                    <span>Resync Stock</span>
                  </button>
                )}

                {effectiveStatus === 'sync_failed' && (
                  <button
                    type="button"
                    disabled={currentSync.canResync !== true}
                    onClick={handleStartResync}
                    className="px-4 py-2 rounded-xl bg-neutral-900 hover:bg-neutral-800 dark:bg-white dark:hover:bg-neutral-100 text-white dark:text-neutral-950 font-bold transition-all shadow-xs flex items-center gap-1.5 cursor-pointer"
                  >
                    <RotateCcw className="w-3.5 h-3.5" />
                    <span>Resync Stock</span>
                  </button>
                )}

                {effectiveStatus === 'awaiting_confirmation' && (
                  <button
                    type="button"
                    disabled={isCheckingStatus || currentSync.canCheck !== true}
                    onClick={handleCheckStatus}
                    className="px-4 py-2 rounded-xl bg-neutral-900 hover:bg-neutral-800 dark:bg-white dark:hover:bg-neutral-100 text-white dark:text-neutral-950 font-bold transition-all shadow-xs flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
                  >
                    {isCheckingStatus ? (
                      <RefreshCw className="w-3.5 h-3.5 animate-spin" />
                    ) : (
                      <RefreshCw className="w-3.5 h-3.5" />
                    )}
                    <span>Check Status</span>
                  </button>
                )}

                {effectiveStatus === 'listing_not_eligible' && (
                  <button
                    type="button"
                    disabled={isCheckingStatus || currentSync.canCheck !== true}
                    onClick={handleCheckStatus}
                    className="px-4 py-2 rounded-xl bg-neutral-900 hover:bg-neutral-800 dark:bg-white dark:hover:bg-neutral-100 text-white dark:text-neutral-950 font-bold transition-all shadow-xs flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
                  >
                    {isCheckingStatus ? (
                      <RefreshCw className="w-3.5 h-3.5 animate-spin" />
                    ) : (
                      <RefreshCw className="w-3.5 h-3.5" />
                    )}
                    <span>Check Status</span>
                  </button>
                )}

                {effectiveStatus === 'sync_replaced' && (
                  <button
                    type="button"
                    disabled={currentSync.canResync !== true}
                    onClick={handleStartResync}
                    className="px-4 py-2 rounded-xl bg-neutral-900 hover:bg-neutral-800 dark:bg-white dark:hover:bg-neutral-100 text-white dark:text-neutral-950 font-bold transition-all shadow-xs flex items-center gap-1.5 cursor-pointer"
                  >
                    <RefreshCw className="w-3.5 h-3.5" />
                    <span>Sync Latest Stock</span>
                  </button>
                )}

                {effectiveStatus === 'jw_internal' && (
                  <button
                    type="button"
                    onClick={() => {
                      onClose();
                      onNavigateToHistory?.(item.masterSku);
                    }}
                    className="px-4 py-2 rounded-xl bg-neutral-900 hover:bg-neutral-800 dark:bg-white dark:hover:bg-neutral-100 text-white dark:text-neutral-950 font-bold transition-all shadow-xs flex items-center gap-1.5 cursor-pointer"
                  >
                    <FileText className="w-3.5 h-3.5" />
                    <span>View Activity History</span>
                  </button>
                )}
              </div>
            </>
          )}

          {/* VIEW MODE 2: CONFIRM RESYNC BUTTONS */}
          {viewMode === 'confirm_resync' && (
            <div className="flex items-center justify-end gap-2 w-full">
              <button
                type="button"
                onClick={() => setViewMode('detail')}
                className="px-4 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-700 dark:text-neutral-300 font-semibold transition-colors cursor-pointer"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={handleConfirmAndExecuteResync}
                className="px-5 py-2 rounded-xl bg-neutral-900 hover:bg-neutral-800 dark:bg-white dark:hover:bg-neutral-100 text-white dark:text-neutral-950 font-bold transition-all shadow-xs flex items-center gap-1.5 cursor-pointer"
              >
                <span>Resync {currentSync.jwAvailableStock} Units</span>
                <ArrowRight className="w-4 h-4" />
              </button>
            </div>
          )}

          {/* VIEW MODE 3: EXECUTING (Action disabled) */}
          {viewMode === 'executing' && (
            <div className="flex items-center justify-center w-full text-xs text-neutral-400 italic">
              Synchronizing with marketplace API... Please do not close this window.
            </div>
          )}

          {/* VIEW MODE 4: SUCCESS BUTTONS */}
          {viewMode === 'success' && (
            <div className="flex items-center justify-end gap-2 w-full">
              <button
                type="button"
                onClick={() => {
                  onClose();
                  onNavigateToHistory?.(item.masterSku);
                }}
                className="px-4 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-700 dark:text-neutral-300 font-semibold transition-colors flex items-center gap-1.5 cursor-pointer"
              >
                <FileText className="w-3.5 h-3.5" />
                <span>View Activity History</span>
              </button>
              <button
                type="button"
                onClick={onClose}
                className="px-5 py-2 rounded-xl bg-neutral-900 hover:bg-neutral-800 dark:bg-white dark:hover:bg-neutral-100 text-white dark:text-neutral-950 font-bold transition-all shadow-xs cursor-pointer"
              >
                Done
              </button>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};
