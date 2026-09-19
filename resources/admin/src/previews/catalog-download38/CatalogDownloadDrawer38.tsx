import React, { useState } from 'react';
import {
  X,
  Minus,
  Maximize2,
  CloudDownload,
  CheckCircle2,
  AlertCircle,
  AlertTriangle,
  RefreshCw,
  Clock,
  Server,
  Database,
  Layers,
  ArrowRight,
  ExternalLink,
  ChevronRight,
  ChevronDown,
  Play,
  Info
} from 'lucide-react';
import { MarketplaceLogo } from '../marketplace30/components/marketplace/MarketplaceLogo';
import type { CatalogDownloadTask38 as CatalogDownloadTask } from './catalogDownload38';

interface CatalogDownloadDrawer38Props {
  open:boolean; minimized:boolean; task:CatalogDownloadTask|null;
  onClose:()=>void; onMinimize:()=>void; onOpen:()=>void; onResume:()=>void; onCheckConnection:()=>void;
  onNavigateToMapping?: () => void;
  onNavigateToUnmapped?: () => void;
}

export const CatalogDownloadDrawer38: React.FC<CatalogDownloadDrawer38Props> = ({
  open,minimized:minimized,task:activeDrawerTask,onClose:closeDrawer,onMinimize:minimizeDrawer,onOpen:openDrawer,onResume:resumeDownload,onCheckConnection:checkStoreConnection,
  onNavigateToMapping,
  onNavigateToUnmapped
}) => {

  const [showTechnicalDetails, setShowTechnicalDetails] = useState(false);

  if (!open || !activeDrawerTask) return null;

  const task = activeDrawerTask;
  const isIndeterminate = task.totalProducts === null || task.status === 'preparing' || task.status === 'queued';
  const progressPercent = (!isIndeterminate && task.totalProducts && task.totalProducts > 0)
    ? Math.min(100, Math.round((task.downloadedProducts / task.totalProducts) * 100))
    : 0;

  const platformDisplayName = task.platform === 'lazada'
    ? 'Lazada'
    : task.platform === 'shopee'
    ? 'Shopee'
    : task.platform === 'tiktok'
    ? 'TikTok Shop'
    : task.platform === 'shopify'
    ? 'Shopify'
    : String(task.platform).toUpperCase();

  const formatElapsed = (seconds: number) => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    if (mins === 0) return `${secs}s`;
    return `${mins}m ${secs}s`;
  };

  const handleResumeClick = () => {
    resumeDownload();
  };

  const handleGoToMapping = () => {
    closeDrawer();
    if (onNavigateToMapping) {
      onNavigateToMapping();
    } else {
      onNavigateToMapping?.();
    }
  };

  const handleGoToUnmapped = () => {
    closeDrawer();
    if (onNavigateToUnmapped) {
      onNavigateToUnmapped();
    } else {
      onNavigateToUnmapped?.();
    }
  };

  // Minimized Floating Pill View
  if (minimized) {
    return (
      <aside
        aria-label="Minimized catalog download task"
        className="fixed bottom-5 right-5 z-50 animate-slideUp"
      >
        <button
          type="button"
          onClick={openDrawer}
          className="flex items-center gap-3 px-4 py-2.5 rounded-2xl bg-neutral-900/95 hover:bg-neutral-850 border border-neutral-700/80 shadow-2xl backdrop-blur-md text-white transition-all hover:scale-102 cursor-pointer group"
        >
          <div className="relative flex items-center justify-center">
            <MarketplaceLogo platform={task.platform} size="xs" />
            {(task.status === 'downloading' || task.status === 'preparing') && (
              <span className="absolute -top-1 -right-1 w-2 h-2 rounded-full bg-emerald-400 animate-ping" />
            )}
            {task.status === 'paused' && (
              <span className="absolute -top-1 -right-1 w-2 h-2 rounded-full bg-amber-400" />
            )}
          </div>

          <div className="text-left">
            <div className="flex items-center gap-2">
              <span className="text-xs font-bold text-neutral-100">
                {task.status === 'downloading' && (isIndeterminate ? 'Catalog download initializing…' : `Catalog download: ${progressPercent}%`)}
                {task.status === 'preparing' && 'Confirming catalog size…'}
                {task.status === 'paused' && 'Catalog task paused'}
                {task.status === 'completed' && 'Catalog download complete'}
              </span>
              <span className="text-[10px] px-1.5 py-0.2 rounded bg-neutral-800 text-neutral-400 font-mono">
                {task.shopName}
              </span>
            </div>
            <div className="text-[10px] text-neutral-400">
              {isIndeterminate ? 'Confirming size with Open API' : `${task.downloadedProducts} / ${task.totalProducts} products`}
            </div>
          </div>

          <Maximize2 className="w-3.5 h-3.5 text-neutral-400 group-hover:text-white ml-1 transition-colors" />
        </button>
      </aside>
    );
  }

  return (
    <div
      id="catalog-download-drawer"
      className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/80 backdrop-blur-sm animate-fadeIn"
      role="dialog"
      aria-modal="true"
      aria-labelledby="drawer-heading"
    >
      <div className="relative w-full max-w-2xl bg-neutral-900 border border-neutral-800 text-neutral-100 rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[92vh]">
        {/* Top Accent Line */}
        <div
          className={`h-1 w-full transition-colors ${
            task.status === 'completed'
              ? 'bg-emerald-500'
              : task.status === 'paused' || task.status === 'failed'
              ? 'bg-amber-500'
              : 'bg-indigo-600'
          }`}
        />

        {/* Header Bar */}
        <div className="px-5 py-3.5 border-b border-neutral-800 flex items-center justify-between bg-neutral-900/95 shrink-0">
          <div className="flex items-center gap-3">
            <div className="w-8 h-8 rounded-lg bg-indigo-500/10 border border-indigo-500/30 flex items-center justify-center text-indigo-400">
              <CloudDownload className="w-4 h-4" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h3 id="drawer-heading" className="text-sm font-bold text-white tracking-tight">
                  Background Catalog Download
                </h3>
                {/* Status Badge */}
                {task.status === 'queued' && (
                  <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-neutral-800 text-neutral-300 border border-neutral-700">
                    Queued
                  </span>
                )}
                {task.status === 'preparing' && (
                  <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-500/15 text-indigo-300 border border-indigo-500/30 flex items-center gap-1">
                    <RefreshCw className="w-2.5 h-2.5 animate-spin" />
                    Preparing
                  </span>
                )}
                {task.status === 'downloading' && (
                  <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-500/15 text-indigo-300 border border-indigo-500/30 flex items-center gap-1">
                    <span className="w-1.5 h-1.5 rounded-full bg-indigo-400 animate-pulse" />
                    Downloading
                  </span>
                )}
                {task.status === 'paused' && (
                  <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/15 text-amber-300 border border-amber-500/30 flex items-center gap-1">
                    <AlertTriangle className="w-2.5 h-2.5" />
                    Paused / Requires Attention
                  </span>
                )}
                {task.status === 'failed' && (
                  <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/15 text-rose-300 border border-rose-500/30 flex items-center gap-1">
                    <AlertCircle className="w-2.5 h-2.5" />
                    Failed
                  </span>
                )}
                {task.status === 'completed' && (
                  <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/15 text-emerald-300 border border-emerald-500/30 flex items-center gap-1">
                    <CheckCircle2 className="w-2.5 h-2.5" />
                    Completed
                  </span>
                )}
              </div>
              <p className="text-[11px] text-neutral-400">
                Server Worker Task #{task.id} • {task.workerId}
              </p>
            </div>
          </div>

          <div className="flex items-center gap-1">
            <button
              onClick={minimizeDrawer}
              className="p-1.5 rounded-lg text-neutral-400 hover:text-white hover:bg-neutral-800 transition-colors cursor-pointer"
              title="Minimize to background pill"
              aria-label="Minimize"
            >
              <Minus className="w-4 h-4" />
            </button>
            <button
              onClick={closeDrawer}
              className="p-1.5 rounded-lg text-neutral-400 hover:text-white hover:bg-neutral-800 transition-colors cursor-pointer"
              title="Close window"
              aria-label="Close"
            >
              <X className="w-4 h-4" />
            </button>
          </div>
        </div>


        {/* Drawer Body - Scrollable */}
        <div className="p-5 overflow-y-auto space-y-4 text-xs">
          {/* Store Identity Summary */}
          <div className="p-3.5 rounded-xl bg-neutral-950/60 border border-neutral-800 flex items-center justify-between gap-3">
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-xl bg-neutral-900 border border-neutral-700 shrink-0">
                <MarketplaceLogo platform={task.platform} size="sm" />
              </div>
              <div>
                <div className="flex items-center gap-2 flex-wrap">
                  <span className="text-sm font-bold text-white">{task.shopName}</span>
                  <span className="px-2 py-0.2 rounded-full text-[10px] font-semibold bg-neutral-800 text-neutral-300 border border-neutral-700">
                    {task.accountType}
                  </span>
                </div>
                <div className="text-[11px] text-neutral-400 mt-0.5 flex items-center gap-2 flex-wrap">
                  <span className="font-mono">ID: {task.shopId}</span>
                  <span>•</span>
                  <span>{task.region}</span>
                  <span>•</span>
                  <span>Target: JW Studio Master Catalog Cache</span>
                </div>
              </div>
            </div>
          </div>

          {/* STATE B: Restored Session Banner if user returned later */}
          {task.isRestoredFromSavedProgress && (
            <div className="p-3 rounded-xl bg-indigo-950/40 border border-indigo-800/60 flex items-start gap-2.5 text-indigo-200">
              <RefreshCw className="w-4 h-4 text-indigo-400 shrink-0 mt-0.5" />
              <div className="space-y-0.5 text-[11px]">
                <div className="font-bold text-xs text-indigo-100">
                  Download resumed from saved server progress.
                </div>
                <p className="text-indigo-300/80">
                  Your browser did not need to stay open. The download continued on our cloud cluster and restored your session state automatically.
                </p>
              </div>
            </div>
          )}

          {/* STATE C: Non-Alarming Paused / Error Banner with Preserve Notice */}
          {(task.status === 'paused' || task.status === 'failed') && (
            <div className="p-3.5 rounded-xl bg-amber-950/30 border border-amber-800/60 space-y-3">
              <div className="flex items-start gap-2.5">
                <AlertTriangle className="w-4 h-4 text-amber-400 shrink-0 mt-0.5" />
                <div className="space-y-1">
                  <div className="text-xs font-bold text-amber-200">
                    Catalog download paused
                  </div>
                  <p className="text-[11px] text-amber-300/90 leading-relaxed">
                    {task.errorDetails?.reason || task.statusMessage}
                  </p>
                  <p className="text-[11px] text-neutral-400">
                    <strong>{task.downloadedProducts.toLocaleString()} products were already saved.</strong> Resume will continue from the last completed page (Page {task.currentPage}) without repeating prior calls.
                  </p>
                </div>
              </div>

              {/* Recovery Action Buttons */}
              <div className="flex items-center gap-2.5 pt-1 border-t border-amber-900/40 flex-wrap">
                <button
                  type="button"
                  onClick={handleResumeClick}
                  className="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold transition-colors cursor-pointer shadow-xs disabled:opacity-50"
                >
                  <Play className="w-3.5 h-3.5" />
                  <span>Resume Download</span>
                </button>

                <button
                  type="button"
                  onClick={checkStoreConnection}
                  className="px-3 py-1.5 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-neutral-300 text-xs font-semibold border border-neutral-700 transition-colors cursor-pointer"
                >
                  Check Connection
                </button>

                <button
                  type="button"
                  onClick={() => setShowTechnicalDetails(!showTechnicalDetails)}
                  className="px-2.5 py-1.5 rounded-lg text-neutral-400 hover:text-neutral-200 text-[11px] font-medium transition-colors cursor-pointer ml-auto flex items-center gap-1"
                >
                  <span>{showTechnicalDetails ? 'Hide details' : 'View technical details'}</span>
                  <ChevronDown className={`w-3 h-3 transition-transform ${showTechnicalDetails ? 'rotate-180' : ''}`} />
                </button>
              </div>

              {/* Expandable Technical Log Box */}
              {showTechnicalDetails && task.errorDetails && (
                <div className="p-3 rounded-lg bg-neutral-950 border border-neutral-800 font-mono text-[10px] space-y-1 text-neutral-400">
                  <div className="text-neutral-300 font-bold">API Diagnostic Trace:</div>
                  <div>HTTP Code: {task.errorDetails.httpStatus || 504}</div>
                  <div>Error Symbol: {task.errorDetails.technicalCode || 'GATEWAY_TIMEOUT'}</div>
                  <div>Cursor Checkpoint: {task.savedPageCursor || 'page_cursor_idx'}</div>
                  {task.errorDetails.rawResponseSample && (
                    <div className="p-1.5 bg-neutral-900 rounded text-neutral-300 whitespace-pre-wrap overflow-x-auto mt-1">
                      {task.errorDetails.rawResponseSample}
                    </div>
                  )}
                </div>
              )}
            </div>
          )}

          {/* STATE D: Completed Success State */}
          {task.status === 'completed' && (
            <div className="p-3.5 rounded-xl bg-emerald-950/30 border border-emerald-800/60 space-y-3">
              <div className="flex items-start gap-2.5">
                <CheckCircle2 className="w-5 h-5 text-emerald-400 shrink-0 mt-0.5" />
                <div className="space-y-1">
                  <div className="text-xs font-bold text-emerald-200">
                    Catalog Download Complete
                  </div>
                  <p className="text-[11px] text-emerald-300/90 leading-relaxed">
                    All pages retrieved and saved to local cache. Images and seller SKU structures are ready for product and SKU mapping.
                  </p>
                </div>
              </div>

              <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center pt-1">
                <div className="p-2 rounded-lg bg-neutral-950/50 border border-neutral-800">
                  <div className="text-sm font-black text-white">{task.downloadedProducts.toLocaleString()}</div>
                  <div className="text-[10px] text-neutral-400">Products Downloaded</div>
                </div>
                <div className="p-2 rounded-lg bg-neutral-950/50 border border-neutral-800">
                  <div className="text-sm font-black text-white">{task.variantsCaptured.toLocaleString()}</div>
                  <div className="text-[10px] text-neutral-400">Variants Captured</div>
                </div>
                <div className="p-2 rounded-lg bg-neutral-950/50 border border-neutral-800">
                  <div className="text-sm font-black text-white">{task.imagesCaptured.toLocaleString()}</div>
                  <div className="text-[10px] text-neutral-400">Images Available</div>
                </div>
                <div className="p-2 rounded-lg bg-neutral-950/50 border border-amber-900/40 bg-amber-950/20">
                  <div className="text-sm font-black text-amber-300">{task.unmappedVariantsCount.toLocaleString()}</div>
                  <div className="text-[10px] text-amber-400">Unmapped Variants</div>
                </div>
              </div>

              <div className="flex items-center gap-1 text-[11px] text-neutral-400 pt-1">
                <ShieldCheck className="w-3.5 h-3.5 text-neutral-500 shrink-0" />
                <span>No JW products were created or changed automatically.</span>
              </div>
            </div>
          )}

          {/* LARGE OPERATIONAL PROGRESS BAR SECTION */}
          {task.status !== 'completed' && (
            <div className="p-4 rounded-xl bg-neutral-950/70 border border-neutral-800 space-y-3">
              <div className="flex items-center justify-between text-xs">
                <span className="font-bold text-white flex items-center gap-2">
                  <Database className="w-4 h-4 text-indigo-400" />
                  <span>
                    {isIndeterminate
                      ? `Confirming catalog size with ${platformDisplayName}…`
                      : `${task.downloadedProducts.toLocaleString()} / ${task.totalProducts?.toLocaleString()} products`}
                  </span>
                </span>
                <span className="font-mono text-neutral-300 font-bold">
                  {isIndeterminate ? (
                    <span className="text-indigo-400 animate-pulse">Calculating size…</span>
                  ) : (
                    `${progressPercent}%`
                  )}
                </span>
              </div>

              {/* The Progress Bar Container */}
              <div className="h-3 w-full bg-neutral-800 rounded-full overflow-hidden relative">
                {isIndeterminate ? (
                  // Indeterminate animated gradient bar
                  <div className="h-full w-2/5 bg-gradient-to-r from-transparent via-indigo-500 to-transparent animate-[shimmer_1.5s_infinite] rounded-full absolute" />
                ) : (
                  // Determinate percentage bar
                  <div
                    className={`h-full transition-all duration-500 rounded-full ${
                      task.status === 'paused'
                        ? 'bg-amber-500'
                        : 'bg-gradient-to-r from-indigo-600 via-indigo-500 to-violet-500'
                    }`}
                    style={{ width: `${progressPercent}%` }}
                  />
                )}
              </div>

              {/* Progress Counters Grid */}
              <div className="grid grid-cols-3 gap-2 pt-1 text-[11px]">
                <div className="p-2 rounded-lg bg-neutral-900 border border-neutral-800/80">
                  <span className="text-neutral-500 block text-[10px]">Variants Captured</span>
                  <span className="text-neutral-200 font-mono font-bold">
                    {task.variantsCaptured.toLocaleString()}
                  </span>
                </div>
                <div className="p-2 rounded-lg bg-neutral-900 border border-neutral-800/80">
                  <span className="text-neutral-500 block text-[10px]">Page Cursor</span>
                  <span className="text-neutral-200 font-mono font-bold">
                    {task.totalPages ? `Page ${task.currentPage} of ${task.totalPages}` : `Page ${task.currentPage}`}
                  </span>
                </div>
                <div className="p-2 rounded-lg bg-neutral-900 border border-neutral-800/80">
                  <span className="text-neutral-500 block text-[10px]">Images Cached</span>
                  <span className="text-neutral-200 font-mono font-bold">
                    {task.imagesCaptured.toLocaleString()}
                  </span>
                </div>
              </div>
            </div>
          )}

          {/* Timers & Real-Time Operational Context */}
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 px-1 text-[11px] text-neutral-400">
            <div className="flex items-center gap-3">
              <span className="flex items-center gap-1.5">
                <Clock className="w-3.5 h-3.5 text-neutral-500" />
                Elapsed: <strong>{formatElapsed(task.elapsedSeconds)}</strong>
              </span>
              <span>•</span>
              <span>
                Last API batch: <strong>Just now</strong>
              </span>
            </div>

            <div className="flex items-center gap-1.5 text-neutral-400">
              <Server className="w-3.5 h-3.5 text-emerald-500" />
              <span>Safe to leave page (server-side job)</span>
            </div>
          </div>

          {/* Rate-Limit & Image Fetching Operational Explanation */}
          <div className="p-3 rounded-xl bg-neutral-950/40 border border-neutral-800/80 text-[11px] text-neutral-400 flex items-start gap-2.5">
            <Info className="w-4 h-4 text-indigo-400 shrink-0 mt-0.5" />
            <p className="leading-relaxed">
              Product images and variant details are retrieved page by page in accordance with {platformDisplayName} Open API rate limits. This may take additional time for stores with high variant counts.
            </p>
          </div>
        </div>

        {/* Footer Actions */}
        <div className="px-5 py-3.5 border-t border-neutral-800 bg-neutral-900/95 flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-between gap-3 shrink-0">
          <button
            type="button"
            onClick={minimizeDrawer}
            className="px-4 py-2 rounded-xl text-xs font-semibold text-neutral-300 hover:text-white hover:bg-neutral-800 transition-colors cursor-pointer text-center"
          >
            Continue working
          </button>

          <div className="flex items-center gap-2">
            {task.status === 'completed' ? (
              <button
                type="button"
                onClick={handleGoToUnmapped}
                className="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition-all shadow-lg shadow-emerald-600/20 cursor-pointer"
              >
                <Layers className="w-4 h-4" />
                <span>Review Unmapped Products ({task.unmappedVariantsCount})</span>
              </button>
            ) : (
              <button
                type="button"
                onClick={handleGoToMapping}
                disabled={task.downloadedProducts === 0}
                className="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 disabled:bg-neutral-800 disabled:text-neutral-500 disabled:cursor-not-allowed text-white text-xs font-bold transition-all shadow-lg shadow-indigo-600/20 cursor-pointer"
                title={task.downloadedProducts === 0 ? 'Catalog download in preparation' : 'Catalog may still be downloading in background'}
              >
                <span>View Product & SKU Mapping</span>
                <ArrowRight className="w-4 h-4" />
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};
