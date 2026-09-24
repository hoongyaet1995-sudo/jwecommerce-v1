import React, { useState } from 'react';
import {
  CheckCircle2,
  AlertTriangle,
  Clock,
  ShieldAlert,
  Link2,
  RotateCcw,
  RefreshCw,
  ExternalLink,
  Store,
  Layers,
  Check,
  Eye,
  FileText,
  Boxes,
  ArrowRight
} from 'lucide-react';
import { MarketplaceLogo } from '../MarketplaceLogo';

export const StockSyncReviewFrames: React.FC = () => {
  const [selectedFrame, setSelectedFrame] = useState<number>(1);

  const frames = [
    { id: 1, title: '1. Inventory Overview with Clickable Store States', badge: 'Overview' },
    { id: 2, title: '2. Stock Sync Details — Synced', badge: 'Operational' },
    { id: 3, title: '3. Stock Sync Details — Stock Mismatch with Resync Stock', badge: 'Mismatch' },
    { id: 4, title: '4. Resync Confirmation State', badge: 'Confirm' },
    { id: 5, title: '5. Sending & Verifying State', badge: 'In Flight' },
    { id: 6, title: '6. Awaiting Confirmation State with Check Status', badge: 'Pending' },
    { id: 7, title: '7. Sync Failed State (Clear Error & Safe Ledger)', badge: 'Failure' },
    { id: 8, title: '8. Authorization Expired State', badge: 'Auth' },
    { id: 9, title: '9. SKU Not Mapped State', badge: 'Mapping' },
    { id: 10, title: '10. Listing Not Eligible State', badge: 'Seller Center' },
    { id: 11, title: '11. Replaced by Newer Stock State', badge: 'Superseded Replacement' },
    { id: 12, title: '12. Successful Provider-Confirmed State', badge: 'Confirmed' },
    { id: 13, title: '13. Updated Allocate Stock Success Modal (Truthful Statuses)', badge: 'Allocation' },
    { id: 14, title: '14. Activity History with User-Facing Status Labels', badge: 'Audit History' }
  ];

  return (
    <div className="space-y-6 animate-in fade-in duration-150">
      {/* Design Review Selector */}
      <div className="p-4 sm:p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs space-y-3">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
          <div>
            <h3 className="text-sm font-bold text-neutral-900 dark:text-white flex items-center gap-2">
              <Eye className="w-4 h-4 text-neutral-400" />
              <span>Stock Sync Details & Recovery — Design Review Frames</span>
            </h3>
            <p className="text-xs text-neutral-500 dark:text-neutral-400">
              Source-faithful design states specified for the JW Studio dark neutral admin interface.
            </p>
          </div>
          <div className="text-xs font-mono text-neutral-400">
            Screen {selectedFrame} of {frames.length}
          </div>
        </div>

        {/* Frame Pills */}
        <div className="flex flex-wrap gap-1.5 pt-1">
          {frames.map(f => (
            <button
              key={f.id}
              type="button"
              onClick={() => setSelectedFrame(f.id)}
              className={`px-3 py-1.5 rounded-lg text-xs font-medium transition cursor-pointer border ${
                selectedFrame === f.id
                  ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-950 border-transparent shadow-xs font-bold'
                  : 'bg-neutral-50 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 border-neutral-200 dark:border-neutral-700 hover:bg-neutral-100 dark:hover:bg-neutral-700'
              }`}
            >
              <span className="opacity-60 mr-1.5">#{f.id}</span>
              {f.badge}
            </button>
          ))}
        </div>
      </div>

      {/* Frame Container */}
      <div className="p-6 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
        <div className="mb-4 pb-3 border-b border-neutral-100 dark:border-neutral-800 flex items-center justify-between">
          <h4 className="text-sm font-bold text-neutral-900 dark:text-white">
            {frames.find(f => f.id === selectedFrame)?.title}
          </h4>
          <span className="text-[11px] font-mono text-neutral-400">
            JW Studio Dark Neutral Specification
          </span>
        </div>

        {/* SCREEN 1: Inventory Overview with Clickable Store States */}
        {selectedFrame === 1 && (
          <div className="space-y-3">
            <p className="text-xs text-neutral-500 dark:text-neutral-400">
              Each store chip under "CONNECTED STORES & SYNC STATES" is a clickable interactive element with hover states and truthful operational statuses.
            </p>
            <div className="p-4 rounded-xl bg-neutral-950 border border-neutral-800 max-w-xl space-y-2">
              <div className="text-[10px] font-bold uppercase tracking-wider text-neutral-400 mb-2">
                CONNECTED STORES & SYNC STATES (CLICKABLE)
              </div>
              
              <div className="flex items-center justify-between p-2 rounded-lg bg-neutral-900 border border-neutral-800 hover:border-neutral-700 cursor-pointer transition">
                <div className="flex items-center gap-2">
                  <MarketplaceLogo platform="webstore" size="xs" />
                  <span className="text-xs font-medium text-white">JW Storefront (MY)</span>
                </div>
                <span className="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                  JW stock: 19
                </span>
              </div>

              <div className="flex items-center justify-between p-2 rounded-lg bg-neutral-900 border border-neutral-800 hover:border-neutral-700 cursor-pointer transition">
                <div className="flex items-center gap-2">
                  <MarketplaceLogo platform="shopee" size="xs" />
                  <span className="text-xs font-medium text-white">Shopee Official MY</span>
                </div>
                <span className="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center gap-1">
                  <Check className="w-3 h-3" />
                  <span>Confirmed: 19</span>
                </span>
              </div>

              <div className="flex items-center justify-between p-2 rounded-lg bg-neutral-900 border border-neutral-800 hover:border-neutral-700 cursor-pointer transition">
                <div className="flex items-center gap-2">
                  <MarketplaceLogo platform="lazada" size="xs" />
                  <span className="text-xs font-medium text-white">Lazada LazMall MY</span>
                </div>
                <span className="text-[10px] font-bold px-2 py-0.5 rounded bg-amber-500/10 text-amber-400 border border-amber-500/20 flex items-center gap-1">
                  <AlertTriangle className="w-3 h-3" />
                  <span>Not Synced</span>
                </span>
              </div>

              <div className="flex items-center justify-between p-2 rounded-lg bg-neutral-900 border border-neutral-800 hover:border-neutral-700 cursor-pointer transition">
                <div className="flex items-center gap-2">
                  <MarketplaceLogo platform="shopee" size="xs" />
                  <span className="text-xs font-medium text-white">Shopee Secondary Outlet</span>
                </div>
                <span className="text-[10px] font-bold px-2 py-0.5 rounded bg-rose-500/10 text-rose-400 border border-rose-500/20 flex items-center gap-1">
                  <ShieldAlert className="w-3 h-3" />
                  <span>Authorization Expired</span>
                </span>
              </div>

              <div className="flex items-center justify-between p-2 rounded-lg bg-neutral-900 border border-neutral-800 hover:border-neutral-700 cursor-pointer transition">
                <div className="flex items-center gap-2">
                  <MarketplaceLogo platform="lazada" size="xs" />
                  <span className="text-xs font-medium text-white">Lazada Test MY</span>
                </div>
                <span className="text-[10px] font-bold px-2 py-0.5 rounded bg-amber-500/10 text-amber-400 border border-amber-500/20 flex items-center gap-1">
                  <Clock className="w-3 h-3 animate-pulse" />
                  <span>Awaiting Confirmation</span>
                </span>
              </div>
            </div>
          </div>
        )}

        {/* SCREEN 2: Stock Sync Details — Synced */}
        {selectedFrame === 2 && (
          <div className="p-5 rounded-2xl bg-neutral-950 border border-neutral-800 max-w-lg mx-auto space-y-4 text-white">
            <div className="flex items-center justify-between border-b border-neutral-800 pb-3">
              <span className="font-bold text-sm">Stock Sync Details</span>
              <span className="text-xs px-2.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center gap-1 font-semibold">
                <Check className="w-3 h-3" /> Synced
              </span>
            </div>

            <div className="p-3.5 rounded-xl bg-neutral-900 border border-neutral-800 flex items-center justify-between">
              <div>
                <span className="font-mono text-xs font-bold block text-white">MCC-Blue-L</span>
                <span className="text-xs text-neutral-300">MUSE CASUAL COLLECTION — Blue, L</span>
                <div className="flex items-center gap-1.5 mt-1 text-[11px] text-neutral-400">
                  <MarketplaceLogo platform="shopee" size="xs" />
                  <span>OpenSANDBOX107385ec227fd6fa7da (Shopee)</span>
                </div>
              </div>
            </div>

            <div className="grid grid-cols-3 gap-2 text-center text-xs">
              <div className="p-3 rounded-lg bg-neutral-900 border border-neutral-800">
                <span className="text-[10px] text-neutral-400 block">JW Available</span>
                <span className="font-mono font-bold text-base">2 units</span>
              </div>
              <div className="p-3 rounded-lg bg-neutral-900 border border-neutral-800">
                <span className="text-[10px] text-neutral-400 block">Marketplace Stock</span>
                <span className="font-mono font-bold text-base">2 units</span>
              </div>
              <div className="p-3 rounded-lg bg-neutral-900 border border-neutral-800">
                <span className="text-[10px] text-neutral-400 block">Difference</span>
                <span className="font-mono font-bold text-base text-emerald-400">0 units</span>
              </div>
            </div>

            <div className="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-xs text-emerald-300 space-y-1">
              <span className="font-bold block">Stock is synchronized</span>
              <p className="text-emerald-300/80">
                Shopee confirmed 2 units for MCC-Blue-L at Sep 21, 6:42 PM via provider readback.
              </p>
            </div>

            <div className="flex justify-end gap-2 pt-2 border-t border-neutral-800 text-xs">
              <button className="px-3 py-1.5 rounded-xl border border-neutral-700 text-neutral-300">Close</button>
              <button className="px-4 py-1.5 rounded-xl bg-white text-neutral-950 font-bold flex items-center gap-1.5">
                <RefreshCw className="w-3.5 h-3.5" />
                <span>Check Status</span>
              </button>
            </div>
          </div>
        )}

        {/* SCREEN 3: Stock Sync Details — Stock Mismatch */}
        {selectedFrame === 3 && (
          <div className="p-5 rounded-2xl bg-neutral-950 border border-neutral-800 max-w-lg mx-auto space-y-4 text-white">
            <div className="flex items-center justify-between border-b border-neutral-800 pb-3">
              <span className="font-bold text-sm">Stock Sync Details</span>
              <span className="text-xs px-2.5 py-0.5 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20 flex items-center gap-1 font-semibold">
                <AlertTriangle className="w-3 h-3" /> Not Synced
              </span>
            </div>

            <div className="grid grid-cols-3 gap-2 text-center text-xs">
              <div className="p-3 rounded-lg bg-neutral-900 border border-neutral-800">
                <span className="text-[10px] text-neutral-400 block">JW Available Stock</span>
                <span className="font-mono font-bold text-base">2 units</span>
              </div>
              <div className="p-3 rounded-lg bg-neutral-900 border border-neutral-800">
                <span className="text-[10px] text-neutral-400 block">Marketplace Stock</span>
                <span className="font-mono font-bold text-base">0 units</span>
              </div>
              <div className="p-3 rounded-lg bg-neutral-900 border border-neutral-800">
                <span className="text-[10px] text-neutral-400 block">Difference</span>
                <span className="font-mono font-bold text-base text-amber-400">-2 units</span>
              </div>
            </div>

            <div className="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/20 text-xs text-amber-300 space-y-1">
              <span className="font-bold block">Marketplace stock does not match JW inventory</span>
              <p className="text-amber-300/80">
                Send the latest JW Available Stock (2 units) to this store.
              </p>
            </div>

            <div className="flex justify-end gap-2 pt-2 border-t border-neutral-800 text-xs">
              <button className="px-3 py-1.5 rounded-xl border border-neutral-700 text-neutral-300">Close</button>
              <button className="px-4 py-1.5 rounded-xl bg-white text-neutral-950 font-bold flex items-center gap-1.5">
                <RefreshCw className="w-3.5 h-3.5" />
                <span>Resync Stock</span>
              </button>
            </div>
          </div>
        )}

        {/* SCREEN 4: Resync Confirmation State */}
        {selectedFrame === 4 && (
          <div className="p-5 rounded-2xl bg-neutral-950 border border-neutral-800 max-w-lg mx-auto space-y-4 text-white">
            <div className="border-b border-neutral-800 pb-3">
              <span className="font-bold text-sm">Resync Stock Confirmation</span>
            </div>

            <div className="p-4 rounded-xl bg-neutral-900 border border-neutral-800 space-y-3 text-xs">
              <p className="font-semibold text-white">
                Send the latest JW Available Stock to this marketplace store?
              </p>
              <p className="text-neutral-400">
                JW Ecom will send an absolute stock quantity of 2 units. This does not change the internal JW inventory ledger.
              </p>

              <div className="grid grid-cols-2 gap-2 text-xs">
                <div className="p-2.5 rounded bg-neutral-950 border border-neutral-800">
                  <span className="text-[10px] text-neutral-400 block">Store Account</span>
                  <span className="font-semibold">Shopee Official MY</span>
                </div>
                <div className="p-2.5 rounded bg-neutral-950 border border-neutral-800">
                  <span className="text-[10px] text-neutral-400 block">SKU</span>
                  <span className="font-mono font-bold">MCC-Blue-L</span>
                </div>
              </div>

              <div className="p-2.5 rounded bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex justify-between font-bold">
                <span>Quantity that will be sent:</span>
                <span className="font-mono">2 units (Absolute)</span>
              </div>
            </div>

            <div className="flex justify-end gap-2 pt-2 border-t border-neutral-800 text-xs">
              <button className="px-3.5 py-1.5 rounded-xl border border-neutral-700 text-neutral-300">Cancel</button>
              <button className="px-5 py-1.5 rounded-xl bg-white text-neutral-950 font-bold flex items-center gap-1.5">
                <span>Resync 2 Units</span>
                <ArrowRight className="w-3.5 h-3.5" />
              </button>
            </div>
          </div>
        )}

        {/* SCREEN 5: Sending and Verifying State */}
        {selectedFrame === 5 && (
          <div className="p-6 rounded-2xl bg-neutral-950 border border-neutral-800 max-w-lg mx-auto space-y-6 text-center text-white">
            <div className="w-12 h-12 rounded-2xl bg-neutral-900 border border-neutral-800 text-white mx-auto flex items-center justify-center">
              <RefreshCw className="w-6 h-6 animate-spin" />
            </div>

            <div className="space-y-1">
              <h4 className="text-sm font-bold">Verifying marketplace stock…</h4>
              <p className="text-xs text-neutral-400 max-w-sm mx-auto">
                Provider accepted request. Performing independent readback to confirm stock quantity.
              </p>
            </div>

            <div className="max-w-xs mx-auto space-y-2 text-xs text-left">
              <div className="p-2.5 rounded-lg bg-neutral-900 border border-neutral-800 flex justify-between">
                <span className="text-neutral-300">1. Internal Ledger</span>
                <span className="text-emerald-400 font-bold">Preserved</span>
              </div>
              <div className="p-2.5 rounded-lg bg-neutral-900 border border-neutral-800 flex justify-between">
                <span className="text-neutral-300">2. Marketplace API Write</span>
                <span className="text-emerald-400 font-bold">Accepted (2 Units)</span>
              </div>
              <div className="p-2.5 rounded-lg bg-neutral-900 border border-neutral-800 flex justify-between">
                <span className="text-neutral-300">3. Provider Readback</span>
                <span className="text-neutral-400 font-mono animate-pulse">Verifying...</span>
              </div>
            </div>
          </div>
        )}

        {/* SCREEN 6: Awaiting Confirmation State */}
        {selectedFrame === 6 && (
          <div className="p-5 rounded-2xl bg-neutral-950 border border-neutral-800 max-w-lg mx-auto space-y-4 text-white">
            <div className="flex items-center justify-between border-b border-neutral-800 pb-3">
              <span className="font-bold text-sm">Stock Sync Details</span>
              <span className="text-xs px-2.5 py-0.5 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20 flex items-center gap-1 font-semibold">
                <Clock className="w-3 h-3 animate-spin" /> Awaiting Confirmation
              </span>
            </div>

            <div className="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/20 space-y-2 text-xs text-amber-300">
              <span className="font-bold block">Update sent. Waiting for marketplace confirmation.</span>
              <p className="text-amber-300/80">
                JW requested <strong>2 units</strong>. Last observed marketplace stock is <strong>0 units</strong>. Request sent: 18 seconds ago (6:42 PM).
              </p>
              <div className="p-2 rounded bg-neutral-900/80 border border-neutral-800 text-[11px] text-neutral-400">
                Shopee may temporarily return the previous quantity for several seconds after accepting an update. Checking the marketplace will not send another stock update.
              </div>
            </div>

            <div className="flex justify-end gap-2 pt-2 border-t border-neutral-800 text-xs">
              <button className="px-3 py-1.5 rounded-xl border border-neutral-700 text-neutral-300">Close</button>
              <button className="px-4 py-1.5 rounded-xl bg-white text-neutral-950 font-bold flex items-center gap-1.5">
                <RefreshCw className="w-3.5 h-3.5" />
                <span>Check Status</span>
              </button>
            </div>
          </div>
        )}

        {/* SCREEN 7: Sync Failed State */}
        {selectedFrame === 7 && (
          <div className="p-5 rounded-2xl bg-neutral-950 border border-neutral-800 max-w-lg mx-auto space-y-4 text-white">
            <div className="flex items-center justify-between border-b border-neutral-800 pb-3">
              <span className="font-bold text-sm">Stock Sync Details</span>
              <span className="text-xs px-2.5 py-0.5 rounded-full bg-rose-500/10 text-rose-400 border border-rose-500/20 flex items-center gap-1 font-semibold">
                <ShieldAlert className="w-3 h-3" /> Sync Failed
              </span>
            </div>

            <div className="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 space-y-2.5 text-xs text-rose-300">
              <span className="font-bold block">The marketplace service is temporarily unavailable.</span>
              <p className="text-rose-300/90">
                Shopee Open Platform returned a temporary gateway response. The stock update could not be confirmed.
              </p>
              <div className="p-2.5 rounded bg-neutral-900 border border-neutral-800 text-neutral-300 text-[11px]">
                <strong>Ledger Status:</strong> JW inventory remains safe and saved at 2 units. External provider failures do not affect internal inventory.
              </div>
            </div>

            <div className="flex justify-end gap-2 pt-2 border-t border-neutral-800 text-xs">
              <button className="px-3 py-1.5 rounded-xl border border-neutral-700 text-neutral-300">Close</button>
              <button className="px-4 py-1.5 rounded-xl bg-white text-neutral-950 font-bold flex items-center gap-1.5">
                <RotateCcw className="w-3.5 h-3.5" />
                <span>Resync Stock</span>
              </button>
            </div>
          </div>
        )}

        {/* SCREEN 8: Authorization Expired State */}
        {selectedFrame === 8 && (
          <div className="p-5 rounded-2xl bg-neutral-950 border border-neutral-800 max-w-lg mx-auto space-y-4 text-white">
            <div className="flex items-center justify-between border-b border-neutral-800 pb-3">
              <span className="font-bold text-sm">Stock Sync Details</span>
              <span className="text-xs px-2.5 py-0.5 rounded-full bg-rose-500/10 text-rose-400 border border-rose-500/20 flex items-center gap-1 font-semibold">
                <ShieldAlert className="w-3 h-3" /> Authorization Expired
              </span>
            </div>

            <div className="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 space-y-2.5 text-xs text-rose-300">
              <span className="font-bold block">This store must be reconnected before inventory can be synchronized.</span>
              <p className="text-rose-300/80">
                The OAuth access token for Shopee Secondary Outlet has expired. Automated stock pushes are paused.
              </p>
              <div className="p-2.5 rounded bg-neutral-900 border border-neutral-800 text-neutral-300 text-[11px]">
                <strong>Ledger Integrity:</strong> JW inventory remains safe at 2 units. Reconnect the store in Store Settings to resume synchronization.
              </div>
            </div>

            <div className="flex justify-between items-center pt-2 border-t border-neutral-800 text-xs">
              <button className="px-3 py-1.5 rounded-xl border border-neutral-700 text-neutral-300">Close</button>
              <button className="px-4 py-1.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold flex items-center gap-1.5">
                <ShieldAlert className="w-3.5 h-3.5" />
                <span>Reconnect Store</span>
              </button>
            </div>
          </div>
        )}

        {/* SCREEN 9: SKU Not Mapped State */}
        {selectedFrame === 9 && (
          <div className="p-5 rounded-2xl bg-neutral-950 border border-neutral-800 max-w-lg mx-auto space-y-4 text-white">
            <div className="flex items-center justify-between border-b border-neutral-800 pb-3">
              <span className="font-bold text-sm">Stock Sync Details</span>
              <span className="text-xs px-2.5 py-0.5 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20 flex items-center gap-1 font-semibold">
                <Link2 className="w-3 h-3" /> SKU Not Mapped
              </span>
            </div>

            <div className="p-4 rounded-xl bg-amber-500/10 border border-amber-500/20 space-y-2.5 text-xs text-amber-300">
              <span className="font-bold block">This JW SKU is not linked to a listing in this store.</span>
              <p className="text-amber-300/80">
                Stock cannot be sent to this store because no corresponding marketplace item ID has been associated.
              </p>
              <div className="p-2.5 rounded bg-neutral-900 border border-neutral-800 text-neutral-300 text-[11px]">
                <strong>Ledger Integrity:</strong> JW inventory remains safely preserved at 2 units.
              </div>
            </div>

            <div className="flex justify-between items-center pt-2 border-t border-neutral-800 text-xs">
              <button className="px-3 py-1.5 rounded-xl border border-neutral-700 text-neutral-300">Close</button>
              <button className="px-4 py-1.5 rounded-xl bg-white text-neutral-950 font-bold flex items-center gap-1.5">
                <Link2 className="w-3.5 h-3.5" />
                <span>Go to SKU Mapping</span>
              </button>
            </div>
          </div>
        )}

        {/* SCREEN 10: Listing Not Eligible State */}
        {selectedFrame === 10 && (
          <div className="p-5 rounded-2xl bg-neutral-950 border border-neutral-800 max-w-lg mx-auto space-y-4 text-white">
            <div className="flex items-center justify-between border-b border-neutral-800 pb-3">
              <span className="font-bold text-sm">Stock Sync Details</span>
              <span className="text-xs px-2.5 py-0.5 rounded-full bg-neutral-800 text-neutral-300 border border-neutral-700 flex items-center gap-1 font-semibold">
                <AlertTriangle className="w-3 h-3 text-neutral-400" /> Listing Not Eligible
              </span>
            </div>

            <div className="p-4 rounded-xl bg-neutral-900 border border-neutral-800 space-y-2.5 text-xs text-neutral-300">
              <span className="font-bold text-white block">This marketplace listing is not currently accepting stock updates.</span>
              <p className="text-neutral-400">
                The listing is currently inactive or deleted on the channel. Manage the listing status in Shopee or Lazada Seller Center, then check again.
              </p>
              <div className="p-2.5 rounded bg-neutral-950 border border-neutral-800 text-[11px] text-neutral-400">
                JW Ecom only manages inventory synchronization. Listing activation is handled in Seller Center.
              </div>
            </div>

            <div className="flex justify-between items-center pt-2 border-t border-neutral-800 text-xs">
              <a
                href="https://seller.shopee.com.my"
                target="_blank"
                rel="noopener noreferrer"
                className="px-3 py-1.5 rounded-xl border border-neutral-700 text-neutral-300 font-semibold flex items-center gap-1"
              >
                <span>Open Seller Center</span>
                <ExternalLink className="w-3 h-3" />
              </a>
              <div className="flex items-center gap-2">
                <button className="px-3 py-1.5 rounded-xl border border-neutral-700 text-neutral-300">Close</button>
                <button className="px-4 py-1.5 rounded-xl bg-white text-neutral-950 font-bold flex items-center gap-1.5">
                  <RefreshCw className="w-3.5 h-3.5" />
                  <span>Check Status</span>
                </button>
              </div>
            </div>
          </div>
        )}

        {/* SCREEN 11: Replaced by Newer Stock State */}
        {selectedFrame === 11 && (
          <div className="p-5 rounded-2xl bg-neutral-950 border border-neutral-800 max-w-lg mx-auto space-y-4 text-white">
            <div className="flex items-center justify-between border-b border-neutral-800 pb-3">
              <span className="font-bold text-sm">Stock Sync Details</span>
              <span className="text-xs px-2.5 py-0.5 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20 flex items-center gap-1 font-semibold">
                <RotateCcw className="w-3 h-3" /> Replaced by Newer Stock
              </span>
            </div>

            <div className="p-4 rounded-xl bg-amber-500/10 border border-amber-500/20 space-y-2 text-xs text-amber-300">
              <span className="font-bold block">Replaced by Newer Stock</span>
              <p className="text-amber-300/80">
                This sync attempt used an older JW stock quantity (18 units). A newer inventory change (22 units) replaced it before the marketplace update was sent.
              </p>
            </div>

            <div className="flex justify-end gap-2 pt-2 border-t border-neutral-800 text-xs">
              <button className="px-3 py-1.5 rounded-xl border border-neutral-700 text-neutral-300">Close</button>
              <button className="px-4 py-1.5 rounded-xl bg-white text-neutral-950 font-bold flex items-center gap-1.5">
                <RefreshCw className="w-3.5 h-3.5" />
                <span>Sync Latest Stock</span>
              </button>
            </div>
          </div>
        )}

        {/* SCREEN 12: Successful Provider-Confirmed State */}
        {selectedFrame === 12 && (
          <div className="p-5 rounded-2xl bg-neutral-950 border border-neutral-800 max-w-lg mx-auto space-y-4 text-white">
            <div className="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-center space-y-2 text-xs">
              <div className="w-10 h-10 rounded-full bg-emerald-500/20 text-emerald-400 mx-auto flex items-center justify-center">
                <Check className="w-5 h-5" />
              </div>
              <h4 className="text-sm font-bold text-emerald-400">Stock synchronized</h4>
              <p className="text-emerald-300/90">
                Shopee Official MY confirmed 2 units for MCC-Blue-L.
              </p>
            </div>

            <div className="p-3.5 rounded-xl bg-neutral-900 border border-neutral-800 space-y-2 text-xs">
              <div className="flex justify-between py-1 border-b border-neutral-800">
                <span className="text-neutral-400">JW Available Stock</span>
                <span className="font-mono font-bold">2 units</span>
              </div>
              <div className="flex justify-between py-1 border-b border-neutral-800">
                <span className="text-neutral-400">Marketplace Confirmed Stock</span>
                <span className="font-mono font-bold text-emerald-400">2 units</span>
              </div>
              <div className="flex justify-between py-1 border-b border-neutral-800">
                <span className="text-neutral-400">Confirmed Time</span>
                <span className="font-mono">Just now (6:42 PM)</span>
              </div>
              <div className="flex justify-between py-1">
                <span className="text-neutral-400">Confirmation Method</span>
                <span className="font-semibold text-neutral-200">Marketplace readback</span>
              </div>
            </div>

            <div className="flex justify-end gap-2 pt-2 border-t border-neutral-800 text-xs">
              <button className="px-3.5 py-1.5 rounded-xl border border-neutral-700 text-neutral-300 flex items-center gap-1.5">
                <FileText className="w-3.5 h-3.5" />
                <span>View Activity History</span>
              </button>
              <button className="px-5 py-1.5 rounded-xl bg-white text-neutral-950 font-bold">Done</button>
            </div>
          </div>
        )}

        {/* SCREEN 13: Updated Allocate Stock Success Modal */}
        {selectedFrame === 13 && (
          <div className="p-5 rounded-2xl bg-neutral-950 border border-neutral-800 max-w-xl mx-auto space-y-4 text-white">
            <div className="text-center space-y-1">
              <div className="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 mx-auto flex items-center justify-center">
                <Check className="w-5 h-5" />
              </div>
              <h4 className="text-sm font-bold">Stock Allocation Completed</h4>
              <p className="text-xs text-neutral-400">
                Master SKU LEG-AUD-EARPAD-VEL verified at 22 units.
              </p>
            </div>

            <div className="p-3.5 rounded-xl bg-neutral-900 border border-neutral-800 space-y-2.5 text-xs">
              <div className="flex items-center justify-between">
                <span className="font-bold text-white">Marketplace ATP Broadcast Status</span>
                <span className="text-[10px] text-neutral-400">Click any row for Stock Sync Details</span>
              </div>

              <div className="space-y-1.5">
                <div className="p-2 rounded bg-neutral-950 border border-neutral-800 flex justify-between items-center cursor-pointer hover:border-neutral-700">
                  <div className="flex items-center gap-2">
                    <MarketplaceLogo platform="webstore" size="xs" />
                    <span>JW Storefront</span>
                  </div>
                  <span className="text-emerald-400 font-semibold flex items-center gap-1">
                    <Check className="w-3.5 h-3.5" /> Saved
                  </span>
                </div>

                <div className="p-2 rounded bg-neutral-950 border border-neutral-800 flex justify-between items-center cursor-pointer hover:border-neutral-700">
                  <div className="flex items-center gap-2">
                    <MarketplaceLogo platform="shopee" size="xs" />
                    <span>Shopee Flagship Mall</span>
                  </div>
                  <span className="text-amber-400 font-semibold flex items-center gap-1">
                    <Clock className="w-3.5 h-3.5" /> Not Sent
                  </span>
                </div>

                <div className="p-2 rounded bg-neutral-950 border border-neutral-800 flex justify-between items-center cursor-pointer hover:border-neutral-700">
                  <div className="flex items-center gap-2">
                    <MarketplaceLogo platform="shopee" size="xs" />
                    <span>Shopee Outlet</span>
                  </div>
                  <span className="text-amber-400 font-semibold flex items-center gap-1">
                    <Clock className="w-3.5 h-3.5 animate-spin" /> Awaiting Confirmation
                  </span>
                </div>

                <div className="p-2 rounded bg-neutral-950 border border-neutral-800 flex justify-between items-center cursor-pointer hover:border-neutral-700">
                  <div className="flex items-center gap-2">
                    <MarketplaceLogo platform="lazada" size="xs" />
                    <span>Lazada LazMall MY</span>
                  </div>
                  <span className="text-emerald-400 font-semibold flex items-center gap-1">
                    <Check className="w-3.5 h-3.5" /> Confirmed
                  </span>
                </div>

                <div className="p-2 rounded bg-neutral-950 border border-neutral-800 flex justify-between items-center cursor-pointer hover:border-neutral-700">
                  <div className="flex items-center gap-2">
                    <MarketplaceLogo platform="lazada" size="xs" />
                    <span>Lazada Test MY</span>
                  </div>
                  <span className="text-amber-400 font-semibold flex items-center gap-1">
                    <Link2 className="w-3.5 h-3.5" /> SKU Not Mapped · Excluded
                  </span>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* SCREEN 14: Activity History with User-Facing Labels */}
        {selectedFrame === 14 && (
          <div className="p-4 rounded-xl bg-neutral-950 border border-neutral-800 max-w-2xl mx-auto space-y-3 text-white text-xs">
            <div className="text-[11px] font-bold uppercase tracking-wider text-neutral-400">
              Activity History — User-Facing Sync Translations
            </div>

            <table className="w-full text-left text-xs">
              <thead>
                <tr className="border-b border-neutral-800 text-[10px] text-neutral-400 uppercase">
                  <th className="py-2">Raw Status</th>
                  <th className="py-2">User-Facing Label</th>
                  <th className="py-2">Badge Styling</th>
                  <th className="py-2">User Meaning</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-800">
                <tr>
                  <td className="py-2 font-mono text-neutral-400">confirmed</td>
                  <td className="py-2 font-semibold text-emerald-400">Confirmed</td>
                  <td className="py-2"><span className="px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 text-[10px]">Confirmed</span></td>
                  <td className="py-2 text-neutral-300">Provider readback matches JW stock</td>
                </tr>
                <tr>
                  <td className="py-2 font-mono text-neutral-400">queued</td>
                  <td className="py-2 font-semibold text-neutral-300">Waiting to Sync</td>
                  <td className="py-2"><span className="px-2 py-0.5 rounded bg-neutral-800 text-neutral-300 text-[10px]">Waiting</span></td>
                  <td className="py-2 text-neutral-300">Stock update prepared for dispatch</td>
                </tr>
                <tr>
                  <td className="py-2 font-mono text-neutral-400">uncertain</td>
                  <td className="py-2 font-semibold text-amber-400">Awaiting Confirmation</td>
                  <td className="py-2"><span className="px-2 py-0.5 rounded bg-amber-500/10 text-amber-400 text-[10px]">Awaiting</span></td>
                  <td className="py-2 text-neutral-300">Update sent, provider cache refreshing</td>
                </tr>
                <tr>
                  <td className="py-2 font-mono text-neutral-400">failed</td>
                  <td className="py-2 font-semibold text-rose-400">Sync Failed</td>
                  <td className="py-2"><span className="px-2 py-0.5 rounded bg-rose-500/10 text-rose-400 text-[10px]">Failed</span></td>
                  <td className="py-2 text-neutral-300">Provider rejected or timed out</td>
                </tr>
                <tr>
                  <td className="py-2 font-mono text-neutral-400">auth_expired</td>
                  <td className="py-2 font-semibold text-rose-400">Authorization Expired</td>
                  <td className="py-2"><span className="px-2 py-0.5 rounded bg-rose-500/10 text-rose-400 text-[10px]">Expired</span></td>
                  <td className="py-2 text-neutral-300">OAuth token expired, reconnect needed</td>
                </tr>
                <tr>
                  <td className="py-2 font-mono text-neutral-400">superseded</td>
                  <td className="py-2 font-semibold text-amber-400">Replaced by Newer Stock</td>
                  <td className="py-2"><span className="px-2 py-0.5 rounded bg-amber-500/10 text-amber-400 text-[10px]">Replaced</span></td>
                  <td className="py-2 text-neutral-300">Superseded by newer inventory change</td>
                </tr>
                <tr>
                  <td className="py-2 font-mono text-neutral-400">unmapped</td>
                  <td className="py-2 font-semibold text-amber-400">SKU Not Mapped</td>
                  <td className="py-2"><span className="px-2 py-0.5 rounded bg-amber-500/10 text-amber-400 text-[10px]">Unmapped</span></td>
                  <td className="py-2 text-neutral-300">SKU not linked to store item ID</td>
                </tr>
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
};
