import React from 'react';
import {
  CloudDownload,
  AlertTriangle,
  CheckCircle2,
  RefreshCw,
  ChevronRight,
  Server
} from 'lucide-react';
import type {CatalogDownloadTask38} from './catalogDownload38';
import { MarketplaceLogo } from '../marketplace30/components/marketplace/MarketplaceLogo';

interface PersistentCatalogTaskIndicatorProps {
  task: CatalogDownloadTask38; onOpen: () => void;
  variant?: 'inline' | 'floating' | 'header';
  className?: string;
}

export const PersistentCatalogTaskIndicator38: React.FC<PersistentCatalogTaskIndicatorProps> = ({
  task, onOpen,
  variant = 'inline',
  className = ''
}) => {
  const isIndeterminate = task.totalProducts === null || task.status === 'preparing';
  const progressPercent = (!isIndeterminate && task.totalProducts && task.totalProducts > 0)
    ? Math.min(100, Math.round((task.downloadedProducts / task.totalProducts) * 100))
    : 0;

  // Determine state label
  let labelText = '';
  let statusBadgeStyle = '';

  if (task.status === 'paused' || task.status === 'failed') {
    labelText = 'Catalog task needs attention';
    statusBadgeStyle = 'bg-amber-500/15 text-amber-300 border-amber-500/30';

  } else if (task.status === 'downloading') {
    labelText = isIndeterminate ? 'Catalog download initializing…' : `Catalog download: ${progressPercent}%`;
    statusBadgeStyle = 'bg-indigo-500/15 text-indigo-300 border-indigo-500/30';
  } else if (task.status === 'queued') {
    labelText = 'Catalog download queued';
    statusBadgeStyle = 'bg-indigo-500/15 text-indigo-300 border-indigo-500/30';
  } else if (task.status === 'preparing') {
    labelText = 'Confirming catalog size…';
    statusBadgeStyle = 'bg-indigo-500/15 text-indigo-300 border-indigo-500/30';
  } else if (task.status === 'completed') {
    labelText = 'Catalog download complete';
    statusBadgeStyle = 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30';
  }

  // Header mini button variant
  if (variant === 'header') {
    return (
      <button
        type="button"
        onClick={() => onOpen()}
        className={`inline-flex items-center gap-2 px-2.5 py-1.5 rounded-lg border text-xs font-medium transition-all hover:scale-102 cursor-pointer ${statusBadgeStyle} ${className}`}
        title="Open Background Catalog Task Manager"
      >
        <div className="flex items-center gap-1.5">
          {task.status === 'paused' || task.status === 'failed' ? (
            <AlertTriangle className="w-3.5 h-3.5 text-amber-400 shrink-0" />
          ) : task.status === 'completed' ? (
            <CheckCircle2 className="w-3.5 h-3.5 text-emerald-400 shrink-0" />
          ) : (
            <CloudDownload className="w-3.5 h-3.5 text-indigo-400 animate-pulse shrink-0" />
          )}
          <span className="font-semibold">{labelText}</span>
        </div>
        <ChevronRight className="w-3 h-3 text-neutral-400" />
      </button>
    );
  }

  // Floating Corner Widget (shown when active task exists and drawer is closed)
  if (variant === 'floating') {
    return (
      <aside
        aria-label="Persistent catalog task notification"
        className={`fixed bottom-5 right-5 z-40 animate-slideUp ${className}`}
      >
        <button
          type="button"
          onClick={() => onOpen()}
          className="flex items-center gap-3 px-3.5 py-2 rounded-xl bg-neutral-900/95 hover:bg-neutral-850 border border-neutral-700 shadow-xl backdrop-blur-md text-white transition-all hover:scale-102 cursor-pointer group"
        >
          <div className="relative">
            <MarketplaceLogo platform={task.platform} size="xs" />
            {task.status === 'downloading' && (
              <span className="absolute -top-1 -right-1 w-2 h-2 rounded-full bg-indigo-400 animate-ping" />
            )}
            {task.status === 'paused' && (
              <span className="absolute -top-1 -right-1 w-2 h-2 rounded-full bg-amber-400" />
            )}
          </div>

          <div className="text-left">
            <div className="text-xs font-bold text-neutral-100 flex items-center gap-1.5">
              <span>{labelText}</span>
            </div>
            <div className="text-[10px] text-neutral-400 flex items-center gap-1">
              <span className="truncate max-w-[120px]">{task.shopName}</span>
              <span>…</span>
              <span>{task.downloadedProducts} prods</span>
            </div>
          </div>

          <ChevronRight className="w-3.5 h-3.5 text-neutral-400 group-hover:text-white transition-colors ml-1" />
        </button>
      </aside>
    );
  }

  // Inline Banner / Bar in Mapping Page Toolbar
  return (
    <div
      onClick={() => onOpen()}
      className={`inline-flex items-center gap-2.5 px-3 py-1.5 rounded-xl border transition-all hover:border-neutral-600 bg-neutral-900/80 cursor-pointer group select-none ${statusBadgeStyle} ${className}`}
      role="button"
      tabIndex={0}
      onKeyDown={e => e.key === 'Enter' && onOpen()}
      title="Click to view full background catalog download progress"
    >
      <div className="flex items-center gap-2">
        <MarketplaceLogo platform={task.platform} size="xs" />

        {task.status === 'downloading' || task.status === 'preparing' ? (
          <div className="relative flex items-center justify-center">
            <CloudDownload className="w-3.5 h-3.5 text-indigo-400 shrink-0 animate-pulse" />
          </div>
        ) : task.status === 'paused' || task.status === 'failed' ? (
          <AlertTriangle className="w-3.5 h-3.5 text-amber-400 shrink-0" />
        ) : (
          <CheckCircle2 className="w-3.5 h-3.5 text-emerald-400 shrink-0" />
        )}

        <div className="text-left">
          <div className="text-xs font-bold flex items-center gap-1.5">
            <span>{labelText}</span>
            <span className="text-[10px] text-neutral-400 font-normal">
              ({task.shopName})
            </span>
          </div>
        </div>
      </div>

      {/* Mini Progress Bar when downloading */}
      {task.status === 'downloading' && !isIndeterminate && (
        <div className="w-14 h-1.5 bg-neutral-800 rounded-full overflow-hidden shrink-0 hidden sm:block">
          <div
            className="h-full bg-indigo-500 rounded-full"
            style={{ width: `${progressPercent}%` }}
          />
        </div>
      )}

      <ChevronRight className="w-3.5 h-3.5 text-neutral-400 group-hover:text-white transition-colors" />
    </div>
  );
};
