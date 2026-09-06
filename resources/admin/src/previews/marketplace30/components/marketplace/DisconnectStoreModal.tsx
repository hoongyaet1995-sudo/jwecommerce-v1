import React, { useState } from 'react';
import { useAdmin } from '../../context/AdminContext';
import { MarketplaceConnection } from '../../types';
import { MarketplaceLogo, getPlatformMeta } from './MarketplaceLogo';
import { X, AlertTriangle, ShieldCheck, PowerOff, RotateCcw } from 'lucide-react';

interface DisconnectStoreModalProps {
  isOpen: boolean;
  onClose: () => void;
  connection: MarketplaceConnection | null;
}

export const DisconnectStoreModal: React.FC<DisconnectStoreModalProps> = ({
  isOpen,
  onClose,
  connection
}) => {
  const { disconnectConnection } = useAdmin();
  const [reason, setReason] = useState<string>('Temporary Store Maintenance / Pause Sync');
  const [isProcessing, setIsProcessing] = useState(false);

  if (!isOpen || !connection) return null;

  const platformMeta = getPlatformMeta(connection.platform);

  const handleConfirmDisconnect = () => {
    setIsProcessing(true);
    setTimeout(() => {
      disconnectConnection(connection.id, reason);
      setIsProcessing(false);
      onClose();
    }, 400);
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-xs overflow-y-auto">
      <div className="relative w-full max-w-lg bg-white dark:bg-neutral-900 rounded-3xl shadow-2xl border border-neutral-200 dark:border-neutral-800 overflow-hidden my-8">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50/80 dark:bg-neutral-950/80">
          <div className="flex items-center gap-2.5">
            <div className="p-2 rounded-xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800">
              <PowerOff className="w-5 h-5" />
            </div>
            <div>
              <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                Disconnect Marketplace Store
              </h3>
              <p className="text-xs text-neutral-500">
                Suspend automated synchronization
              </p>
            </div>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="p-1.5 rounded-xl text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors cursor-pointer"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Content */}
        <div className="p-6 space-y-5">
          {/* Target Store Profile */}
          <div className="p-4 rounded-2xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
            <div className="flex items-center gap-3">
              <MarketplaceLogo platform={connection.platform} size={36} />
              <div>
                <h4 className="text-sm font-bold text-neutral-900 dark:text-white">
                  {connection.shopName}
                </h4>
                <p className="text-xs text-neutral-500 font-mono">
                  {platformMeta.name} • Shop ID: {connection.shopId} ({connection.region})
                </p>
              </div>
            </div>
            <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300">
              Currently Active
            </span>
          </div>

          {/* Impact Warning */}
          <div className="p-4 rounded-2xl bg-amber-50/70 dark:bg-amber-950/30 border border-amber-200/80 dark:border-amber-900/60 space-y-2 text-xs">
            <div className="flex items-center gap-2 font-bold text-amber-900 dark:text-amber-200">
              <AlertTriangle className="w-4 h-4 text-amber-600 dark:text-amber-400" />
              <span>What happens when you disconnect:</span>
            </div>
            <ul className="list-disc list-inside space-y-1 text-amber-800/90 dark:text-amber-300/90 leading-relaxed pl-1">
              <li>Real-time Available-to-Promise (ATP) inventory stock broadcasts will be halted.</li>
              <li>New inbound customer orders from this channel will not be ingested into JW OMS.</li>
              <li>Webhooks for tracking writebacks and fulfillment will be paused.</li>
            </ul>
          </div>

          {/* Data Safety Guarantee */}
          <div className="p-4 rounded-2xl bg-indigo-50/60 dark:bg-indigo-950/30 border border-indigo-200/70 dark:border-indigo-900/60 flex items-start gap-3 text-xs">
            <ShieldCheck className="w-5 h-5 text-indigo-600 dark:text-indigo-400 flex-shrink-0 mt-0.5" />
            <div className="space-y-1">
              <p className="font-bold text-indigo-900 dark:text-indigo-200">
                Your Data Remains 100% Safe
              </p>
              <p className="text-indigo-700/80 dark:text-indigo-300/80 leading-relaxed">
                All existing order history, SKU mappings, and historic sync logs are safely preserved. You can re-authorize this store at any time via 1-click OAuth to resume automated synchronization.
              </p>
            </div>
          </div>

          {/* Disconnection Reason */}
          <div>
            <label className="block text-xs font-bold text-neutral-700 dark:text-neutral-300 mb-1.5">
              Reason for Disconnection (Optional)
            </label>
            <select
              value={reason}
              onChange={e => setReason(e.target.value)}
              className="w-full px-3.5 py-2.5 rounded-xl bg-white dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 text-neutral-900 dark:text-white font-medium text-xs focus:ring-2 focus:ring-rose-500 focus:outline-hidden cursor-pointer"
            >
              <option value="Temporary Store Maintenance / Pause Sync">Temporary Store Maintenance / Pause Sync</option>
              <option value="Rotating OAuth Credentials on Platform">Rotating OAuth Credentials on Platform</option>
              <option value="Store Closed or Account Transferred">Store Closed or Account Transferred</option>
              <option value="Testing & Reconfiguration">Testing & Reconfiguration</option>
              <option value="Other">Other Reason</option>
            </select>
          </div>
        </div>

        {/* Footer Actions */}
        <div className="flex items-center justify-between px-6 py-4 border-t border-neutral-200 dark:border-neutral-800 bg-neutral-50/80 dark:bg-neutral-950/80">
          <button
            type="button"
            onClick={onClose}
            className="px-4 py-2 text-xs font-semibold text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-xl transition-colors cursor-pointer"
          >
            Keep Store Connected
          </button>
          <button
            type="button"
            disabled={isProcessing}
            onClick={handleConfirmDisconnect}
            className="inline-flex items-center gap-2 px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl shadow-xs transition-colors cursor-pointer disabled:opacity-50"
          >
            {isProcessing ? (
              <>
                <RotateCcw className="w-4 h-4 animate-spin" />
                Disconnecting...
              </>
            ) : (
              <>
                <PowerOff className="w-4 h-4" />
                Confirm Disconnection
              </>
            )}
          </button>
        </div>
      </div>
    </div>
  );
};

