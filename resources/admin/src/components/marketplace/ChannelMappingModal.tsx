import React, { useState } from 'react';
import { MarketplaceInventoryItem, MarketplacePlatform, ChannelSkuMapping } from '../../types';
import { X, Link2, Check, AlertCircle, RefreshCw, Layers } from 'lucide-react';

interface ChannelMappingModalProps {
  item: MarketplaceInventoryItem | null;
  isOpen: boolean;
  onClose: () => void;
  onSave: (inventoryId: string, platform: MarketplacePlatform, mapping: ChannelSkuMapping) => void;
}

export const ChannelMappingModal: React.FC<ChannelMappingModalProps> = ({
  item,
  isOpen,
  onClose,
  onSave
}) => {
  if (!isOpen || !item) return null;

  const [activePlatform, setActivePlatform] = useState<MarketplacePlatform>('tiktok');
  const existingMapping = item.channelMappings[activePlatform];

  const [channelSku, setChannelSku] = useState(existingMapping?.channelSku || existingMapping?.channelSellerSku || `${item.masterSku}-${(activePlatform || 'TIKTOK').toUpperCase()}`);
  const [channelItemId, setChannelItemId] = useState(existingMapping?.channelItemId || `MP-ITEM-${Date.now().toString().slice(-6)}`);
  const [channelPrice, setChannelPrice] = useState<number>(existingMapping?.channelPrice ?? existingMapping?.price ?? item.sellingPrice);
  const [priceMultiplier, setPriceMultiplier] = useState<number>(existingMapping?.priceMultiplier || 1.0);
  const [isLinked, setIsLinked] = useState<boolean>(existingMapping ? existingMapping.isLinked : true);

  // Sync state when activePlatform changes
  const handlePlatformSwitch = (platform: MarketplacePlatform) => {
    setActivePlatform(platform);
    const m = item.channelMappings[platform];
    if (m) {
      setChannelSku(m.channelSku || m.channelSellerSku || `${item.masterSku}-${(platform || 'TIKTOK').toUpperCase()}`);
      setChannelItemId(m.channelItemId || `MP-ITEM-${Date.now().toString().slice(-6)}`);
      setChannelPrice(m.channelPrice ?? m.price ?? item.sellingPrice);
      setPriceMultiplier(m.priceMultiplier || 1.0);
      setIsLinked(m.isLinked ?? true);
    } else {
      setChannelSku(`${item.masterSku}-${(platform || 'TIKTOK').toUpperCase()}`);
      setChannelItemId(`MP-ITEM-${Date.now().toString().slice(-6)}`);
      setChannelPrice(item.sellingPrice);
      setPriceMultiplier(1.0);
      setIsLinked(true);
    }
  };

  const handleMultiplierChange = (mult: number) => {
    setPriceMultiplier(mult);
    setChannelPrice(Number((item.sellingPrice * mult).toFixed(2)));
  };

  const handleSave = () => {
    const updatedMapping: ChannelSkuMapping = {
      channelSku,
      channelItemId,
      channelPrice,
      channelStock: item.availableStock,
      priceMultiplier,
      isLinked,
      lastSyncedAt: new Date().toISOString(),
      syncStatus: isLinked ? 'synced' : 'unlinked'
    };
    onSave(item.id, activePlatform, updatedMapping);
    onClose();
  };

  const platforms: { id: MarketplacePlatform; name: string; color: string }[] = [
    { id: 'tiktok', name: 'TikTok Shop', color: 'border-neutral-900 text-neutral-900 dark:text-white dark:border-neutral-700' },
    { id: 'shopee', name: 'Shopee Shop', color: 'border-amber-500 text-amber-600 dark:text-amber-400' },
    { id: 'lazada', name: 'Lazada Shop', color: 'border-blue-600 text-blue-600 dark:text-blue-400' },
    { id: 'shopify', name: 'Shopify Shop', color: 'border-emerald-600 text-emerald-600 dark:text-emerald-400' },
  ];

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
      <div className="relative w-full max-w-xl bg-white dark:bg-neutral-900 rounded-2xl shadow-2xl border border-neutral-200 dark:border-neutral-800 overflow-hidden">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950/60">
          <div className="flex items-center gap-2.5">
            <div className="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">
              <Link2 className="w-5 h-5" />
            </div>
            <div>
              <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                Channel SKU Mapping Matrix
              </h3>
              <p className="text-xs text-neutral-500">
                Master SKU: <span className="font-mono font-semibold text-neutral-800 dark:text-neutral-200">{item.masterSku}</span> ({item.productName})
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors cursor-pointer"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Platform Selector Tabs */}
        <div className="px-6 pt-4 border-b border-neutral-200 dark:border-neutral-800 flex gap-2">
          {platforms.map(p => {
            const isSelected = activePlatform === p.id;
            const mapping = item.channelMappings[p.id];
            return (
              <button
                key={p.id}
                onClick={() => handlePlatformSwitch(p.id)}
                className={`flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-t-lg border-b-2 transition-all cursor-pointer ${
                  isSelected
                    ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 bg-indigo-50/50 dark:bg-indigo-950/30'
                    : 'border-transparent text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200'
                }`}
              >
                <span>{p.name}</span>
                {mapping?.isLinked ? (
                  <span className="w-2 h-2 rounded-full bg-emerald-500" title="Linked" />
                ) : (
                  <span className="w-2 h-2 rounded-full bg-neutral-300 dark:bg-neutral-700" title="Unlinked" />
                )}
              </button>
            );
          })}
        </div>

        {/* Mapping Form Body */}
        <div className="p-6 space-y-4">
          <div className="flex items-center justify-between p-3.5 bg-neutral-50 dark:bg-neutral-950/40 rounded-xl border border-neutral-200 dark:border-neutral-800">
            <div>
              <p className="text-xs font-bold text-neutral-900 dark:text-white">
                Channel Sync Status
              </p>
              <p className="text-[11px] text-neutral-500">
                When enabled, WMS inventory deltas auto-broadcast to this marketplace.
              </p>
            </div>
            <label className="relative inline-flex items-center cursor-pointer">
              <input
                type="checkbox"
                checked={isLinked}
                onChange={e => setIsLinked(e.target.checked)}
                className="sr-only peer"
              />
              <div className="w-11 h-6 bg-neutral-200 peer-focus:outline-hidden rounded-full peer dark:bg-neutral-800 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-neutral-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
            </label>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                Channel Product / Item ID
              </label>
              <input
                type="text"
                value={channelItemId}
                onChange={e => setChannelItemId(e.target.value)}
                placeholder="e.g. 1729481928"
                className="w-full px-3 py-2 rounded-xl bg-white dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 text-xs font-mono text-neutral-900 dark:text-white focus:outline-hidden focus:ring-2 focus:ring-indigo-500"
              />
              <p className="text-[10px] text-neutral-400 mt-1">Unique item ID assigned by {(activePlatform || 'MARKETPLACE').toUpperCase()} catalog.</p>
            </div>

            <div>
              <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                Marketplace Seller SKU
              </label>
              <input
                type="text"
                value={channelSku}
                onChange={e => setChannelSku(e.target.value)}
                placeholder="e.g. TT-SKU-990"
                className="w-full px-3 py-2 rounded-xl bg-white dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 text-xs font-mono text-neutral-900 dark:text-white focus:outline-hidden focus:ring-2 focus:ring-indigo-500"
              />
              <p className="text-[10px] text-neutral-400 mt-1">Seller SKU entered in channel backend.</p>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3 pt-2">
            <div>
              <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                Pricing Multiplier (Markup / Margin)
              </label>
              <div className="flex items-center gap-2">
                <input
                  type="number"
                  step="0.05"
                  min="0.5"
                  max="3.0"
                  value={priceMultiplier}
                  onChange={e => handleMultiplierChange(parseFloat(e.target.value) || 1.0)}
                  className="w-24 px-3 py-2 rounded-xl bg-white dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 text-xs font-semibold text-neutral-900 dark:text-white"
                />
                <span className="text-xs text-neutral-500">
                  {priceMultiplier === 1 ? '(1:1 Standard)' : priceMultiplier > 1 ? `(+${Math.round((priceMultiplier - 1) * 100)}% Channel Fee)` : '(-Discount)'}
                </span>
              </div>
            </div>

            <div>
              <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                Channel Listed Price
              </label>
              <div className="relative">
                <input
                  type="number"
                  step="0.01"
                  value={channelPrice}
                  onChange={e => setChannelPrice(parseFloat(e.target.value) || 0)}
                  className="w-full px-3 py-2 rounded-xl bg-white dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 text-xs font-bold text-neutral-900 dark:text-white"
                />
              </div>
              <p className="text-[10px] text-neutral-400 mt-1">Base Master Price: RM {item.sellingPrice.toFixed(2)}</p>
            </div>
          </div>

          {/* Real-time sync preview pill */}
          <div className="p-3 bg-indigo-50/50 dark:bg-indigo-950/30 rounded-xl border border-indigo-100 dark:border-indigo-900/50 flex items-start gap-2.5 text-xs text-indigo-900 dark:text-indigo-200">
            <RefreshCw className="w-4 h-4 text-indigo-600 dark:text-indigo-400 flex-shrink-0 mt-0.5" />
            <div>
              <p className="font-semibold">Auto-Allocation Formula</p>
              <p className="text-[11px] text-indigo-700 dark:text-indigo-300 mt-0.5">
                Physical Stock ({item.totalPhysicalStock}) − Reserved ({item.reservedStock}) − Safety Stock ({item.safetyStock}) = <span className="font-bold">{item.availableStock} ATP Units</span> pushed to {(activePlatform || 'MARKETPLACE').toUpperCase()}.
              </p>
            </div>
          </div>
        </div>

        {/* Footer */}
        <div className="flex items-center justify-between px-6 py-4 border-t border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950/60">
          <button
            onClick={onClose}
            className="px-4 py-2 text-xs font-semibold text-neutral-600 dark:text-neutral-400 hover:bg-neutral-200 dark:hover:bg-neutral-800 rounded-xl transition-colors cursor-pointer"
          >
            Cancel
          </button>
          <button
            onClick={handleSave}
            className="inline-flex items-center gap-1.5 px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow-xs transition-colors cursor-pointer"
          >
            <Check className="w-4 h-4" />
            Save & Update Mapping
          </button>
        </div>
      </div>
    </div>
  );
};
