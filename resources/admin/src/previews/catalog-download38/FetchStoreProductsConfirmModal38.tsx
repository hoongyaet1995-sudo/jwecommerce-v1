import React, { useState } from 'react';
import {
  X,
  CloudDownload,
  CheckCircle2,
  ShieldCheck,
  AlertTriangle,
  Clock,
  Database,
  Layers,
  Sparkles,
  Info,
  Server,
  ArrowRight,
  HelpCircle,
  ExternalLink,
  ChevronDown
} from 'lucide-react';
import { MarketplaceLogo } from '../marketplace30/components/marketplace/MarketplaceLogo';
import { StoreConnection as MarketplaceConnection } from '../../integration/marketplaceOperations';

interface FetchStoreProductsConfirmModal38Props {
  open: boolean;
  connections: MarketplaceConnection[];
  existingTask?: {downloadedProducts:number};
  pending?: boolean;
  unavailableReason?: string;
  onViewProgress?: () => void;
  onClose: () => void;
  connection: MarketplaceConnection | null;
  onConfirmStart: (connectionId: string, skipFutureConfirm: boolean) => void;
  onSelectAlternativeConnection?: (connectionId: string) => void;
}

export const FetchStoreProductsConfirmModal38: React.FC<FetchStoreProductsConfirmModal38Props> = ({
  open,
  connections,
  existingTask,
  pending = false,
  unavailableReason,
  onViewProgress,
  onClose,
  connection,
  onConfirmStart,
  onSelectAlternativeConnection
}) => {
  const [doNotShowAgain, setDoNotShowAgain] = useState(false);
  const [isStoreSwitcherOpen, setIsStoreSwitcherOpen] = useState(false);

  if (!open || !connection) return null;

  // Check if this store already has a running task
  const isTaskAlreadyRunning = existingTask && (
    Boolean(existingTask) ||
    Boolean(existingTask) ||
    Boolean(existingTask)
  );

  const platformDisplayName = connection.platform === 'lazada'
    ? 'Lazada'
    : connection.platform === 'shopee'
    ? 'Shopee'
    : connection.platform === 'tiktok'
    ? 'TikTok Shop'
    : connection.platform === 'shopify'
    ? 'Shopify'
    : String(connection.platform).toUpperCase();

  const accountTypeDisplay = connection.accountMode === 'test' ? 'Test account' : 'Production account';

  const regionDisplay = connection.region === 'MY'
    ? 'Malaysia (MYR)'
    : `${connection.region} (Local Currency)`;

  const handleStart = () => {
    onConfirmStart(connection.id, doNotShowAgain);
  };

  return (
    <div
      id="fetch-store-products-confirm-modal"
      className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/80 backdrop-blur-sm animate-fadeIn"
      role="dialog"
      aria-modal="true"
      aria-labelledby="modal-fetch-title"
    >
      <div className="relative w-full max-w-2xl bg-neutral-900 border border-neutral-800 text-neutral-100 rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[92vh]">
        {/* Subtle accent gradient bar on top */}
        <div className="h-1 w-full bg-gradient-to-r from-indigo-600 via-violet-500 to-indigo-600" />

        {/* Modal Header */}
        <div className="px-5 py-4 border-b border-neutral-800/80 flex items-center justify-between bg-neutral-900/90 shrink-0">
          <div className="flex items-center gap-2.5">
            <div className="w-8 h-8 rounded-lg bg-indigo-500/10 border border-indigo-500/30 flex items-center justify-center text-indigo-400">
              <CloudDownload className="w-4 h-4" />
            </div>
            <div>
              <h2 id="modal-fetch-title" className="text-base font-bold text-white tracking-tight">
                Fetch Store Products
              </h2>
              <p className="text-[11px] text-neutral-400">
                Marketplace Catalog Download & SKU Sync Preparation
              </p>
            </div>
          </div>

          <button
            onClick={onClose}
            className="p-1.5 rounded-lg text-neutral-400 hover:text-white hover:bg-neutral-800 transition-colors cursor-pointer"
            aria-label="Close modal"
          >
            <X className="w-4 h-4" />
          </button>
        </div>

        {/* Modal Body - Scrollable */}
        <div className="p-5 overflow-y-auto space-y-4 text-xs">
          {/* Selected Store Summary Card */}
          <div className="p-4 rounded-xl bg-neutral-950/70 border border-neutral-800 relative">
            <div className="flex items-start justify-between gap-3">
              <div className="flex items-center gap-3">
                <div className="p-2 rounded-xl bg-neutral-900 border border-neutral-700/80 shadow-inner shrink-0">
                  <MarketplaceLogo platform={connection.platform} size="md" />
                </div>
                <div>
                  <div className="flex items-center gap-2 flex-wrap">
                    <span className="text-sm font-black text-white tracking-tight">
                      {connection.shopName}
                    </span>
                    <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/15 text-amber-400 border border-amber-500/30">
                      {accountTypeDisplay}
                    </span>
                  </div>

                  <div className="flex items-center gap-3 text-[11px] text-neutral-400 mt-1 flex-wrap">
                    <span className="flex items-center gap-1 font-mono">
                      <span className="text-neutral-500">ID:</span> {connection.shopId}
                    </span>
                    <span className="text-neutral-600">•</span>
                    <span>Region: {regionDisplay}</span>
                    <span className="text-neutral-600">•</span>
                    <span className="capitalize">{platformDisplayName} Open API</span>
                  </div>
                </div>
              </div>

              {/* Store Switcher for easy testing across channels */}
              {onSelectAlternativeConnection && connections.length > 1 && (
                <div className="relative shrink-0">
                  <button
                    type="button"
                    onClick={() => setIsStoreSwitcherOpen(!isStoreSwitcherOpen)}
                    className="flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-neutral-800/80 hover:bg-neutral-800 border border-neutral-700 text-[11px] font-medium text-neutral-300 transition-colors cursor-pointer"
                    title="Switch store connection"
                  >
                    <span>Change Store</span>
                    <ChevronDown className="w-3.5 h-3.5 text-neutral-400" />
                  </button>

                  {isStoreSwitcherOpen && (
                    <div className="absolute right-0 mt-1.5 w-60 rounded-xl bg-neutral-900 border border-neutral-700 shadow-xl z-20 py-1 divide-y divide-neutral-800">
                      <div className="px-3 py-1.5 text-[10px] font-bold text-neutral-400 uppercase tracking-wider">
                        Select Connected Store
                      </div>
                      <div className="max-h-48 overflow-y-auto">
                        {connections.map(c => (
                          <button
                            key={c.id}
                            type="button"
                            onClick={() => {
                              onSelectAlternativeConnection(c.id);
                              setIsStoreSwitcherOpen(false);
                            }}
                            className={`w-full px-3 py-2 text-left flex items-center justify-between text-xs hover:bg-neutral-800 transition-colors cursor-pointer ${
                              c.id === connection.id ? 'bg-indigo-950/40 text-indigo-300 font-bold' : 'text-neutral-300'
                            }`}
                          >
                            <span className="truncate pr-2">{c.shopName}</span>
                            <span className="text-[10px] text-neutral-500 uppercase">{c.platform}</span>
                          </button>
                        ))}
                      </div>
                    </div>
                  )}
                </div>
              )}
            </div>

            {/* Already running alert banner if applicable */}
            {isTaskAlreadyRunning && (
              <div className="mt-3 p-2.5 rounded-lg bg-indigo-950/50 border border-indigo-800/80 flex items-center justify-between text-[11px] text-indigo-200">
                <div className="flex items-center gap-2">
                  <div className="w-2 h-2 rounded-full bg-indigo-400 animate-pulse" />
                  <span>A catalog download is already running for this store ({existingTask?.downloadedProducts} products retrieved).</span>
                </div>
                <button type="button" onClick={onViewProgress} className="font-bold text-indigo-300 underline cursor-pointer">
                  View Progress
                </button>
              </div>
            )}
          </div>

          {/* Core Server-side Explanation Callout */}
          <div className="p-3.5 rounded-xl bg-indigo-950/30 border border-indigo-800/40 flex items-start gap-3">
            <div className="p-1.5 rounded-lg bg-indigo-500/20 text-indigo-300 shrink-0 mt-0.5">
              <Server className="w-4 h-4" />
            </div>
            <div className="space-y-1">
              <p className="font-semibold text-indigo-200 text-xs leading-relaxed">
                This starts a background catalog download. You can continue working or leave this page; JW Studio will keep downloading on the server.
              </p>
              <p className="text-[11px] text-indigo-300/80">
                Tasks run as asynchronous worker jobs on the server. Closing your tab or browser session will not interrupt progress.
              </p>
            </div>
          </div>

          {/* Two-Column Comparison: What will be fetched vs What will NOT happen */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3.5">
            {/* What Will Be Fetched */}
            <div className="p-3.5 rounded-xl bg-neutral-950/50 border border-neutral-800/80 space-y-2.5">
              <div className="flex items-center gap-1.5 text-xs font-bold text-emerald-400">
                <CheckCircle2 className="w-4 h-4 text-emerald-500 shrink-0" />
                <span>What will be fetched</span>
              </div>
              <ul className="space-y-1.5 text-[11px] text-neutral-300 pl-1">
                <li className="flex items-start gap-2">
                  <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 mt-1.5 shrink-0" />
                  <span>Product titles and detailed descriptions</span>
                </li>
                <li className="flex items-start gap-2">
                  <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 mt-1.5 shrink-0" />
                  <span>Primary images and variant-level swatch images</span>
                </li>
                <li className="flex items-start gap-2">
                  <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 mt-1.5 shrink-0" />
                  <span>Seller SKUs and variant attribute values</span>
                </li>
                <li className="flex items-start gap-2">
                  <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 mt-1.5 shrink-0" />
                  <span>Channel listing prices and available stock</span>
                </li>
                <li className="flex items-start gap-2">
                  <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 mt-1.5 shrink-0" />
                  <span>Marketplace product & item IDs for later mapping</span>
                </li>
              </ul>
            </div>

            {/* What Will NOT Happen */}
            <div className="p-3.5 rounded-xl bg-neutral-950/50 border border-neutral-800/80 space-y-2.5">
              <div className="flex items-center gap-1.5 text-xs font-bold text-amber-400">
                <ShieldCheck className="w-4 h-4 text-amber-400 shrink-0" />
                <span>What will NOT happen</span>
              </div>
              <ul className="space-y-1.5 text-[11px] text-neutral-300 pl-1">
                <li className="flex items-start gap-2">
                  <span className="w-1.5 h-1.5 rounded-full bg-amber-400 mt-1.5 shrink-0" />
                  <span>No product will be created in JW Master automatically</span>
                </li>
                <li className="flex items-start gap-2">
                  <span className="w-1.5 h-1.5 rounded-full bg-amber-400 mt-1.5 shrink-0" />
                  <span>No product will be published or changed in {platformDisplayName}</span>
                </li>
                <li className="flex items-start gap-2">
                  <span className="w-1.5 h-1.5 rounded-full bg-amber-400 mt-1.5 shrink-0" />
                  <span>No inventory update will be pushed</span>
                </li>
                <li className="flex items-start gap-2">
                  <span className="w-1.5 h-1.5 rounded-full bg-amber-400 mt-1.5 shrink-0" />
                  <span>No SKU mapping will be made automatically</span>
                </li>
              </ul>
            </div>
          </div>

          {/* Estimated Scope & Safety Policy Box */}
          <div className="p-3 rounded-xl bg-neutral-950/40 border border-neutral-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-[11px] text-neutral-400">
            <div className="flex items-center gap-2">
              <Clock className="w-4 h-4 text-neutral-400 shrink-0" />
              <span>
                The exact product total will be confirmed after {platformDisplayName} returns the first catalog page. For a large store, downloading may take several minutes.
              </span>
            </div>
          </div>

          {/* Compact Safety Policy Note */}
          <div className="flex items-center gap-2 px-1 text-[11px] text-neutral-400">
            <Info className="w-3.5 h-3.5 text-indigo-400 shrink-0" />
            <span>
              <strong>Operational rule:</strong> Only one catalog download can run per store at a time.
            </span>
          </div>


        </div>

        {/* Modal Footer */}
        <div className="px-5 py-3.5 border-t border-neutral-800/80 bg-neutral-900/95 flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-between gap-3 shrink-0">
          <button
            type="button"
            onClick={onClose}
            className="px-4 py-2 rounded-xl text-xs font-semibold text-neutral-300 hover:text-white hover:bg-neutral-800 transition-colors cursor-pointer text-center"
          >
            Cancel
          </button>

          <button
            type="button"
            onClick={isTaskAlreadyRunning ? onViewProgress : handleStart}
            disabled={pending || !!unavailableReason}
            title={unavailableReason}
            className="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-lg shadow-indigo-600/20 transition-all cursor-pointer"
          >
            <CloudDownload className="w-4 h-4" />
            <span>{pending ? 'Checking task…' : unavailableReason || (isTaskAlreadyRunning ? 'View Progress' : 'Start Background Download')}</span>
          </button>
        </div>
      </div>
    </div>
  );
};
