import React, { useState, useEffect } from 'react';

import {
  Truck,
  CheckCircle2,
  AlertCircle,
  HelpCircle,
  RefreshCw,
  Info,
  Check,
  PackageCheck,
  ShieldCheck
} from 'lucide-react';

export interface ShippingChannelConfig {
  channelId: string;
  name: string;
  isRequiredByPlatform: boolean; // E.g. SPX Express is mandatory for Shopee Mall / Preferred
  requiredReason?: string;
  requiresSizeSelection?: boolean;
  sizeOptions?: { id: string; label: string; maxDimCm: number }[];
  requiresSellerFee?: boolean;
  defaultFeeMyr?: number;
}

export interface SelectedChannelState {
  enabled: boolean;
  selectedSizeTier?: string;
  sellerFeeMyr?: number;
}

interface StoreShippingChannelsSectionProps {
 connection:{shopName:string}; channels:ShippingChannelConfig[];
 selectedChannels:Record<string,SelectedChannelState>;
 onChange:(channels:Record<string,SelectedChannelState>)=>void;
 isLoading:boolean;fetchError:string|null;onRefresh:()=>void;
}
export const StoreShippingChannelsSection:React.FC<StoreShippingChannelsSectionProps>=({connection,channels,selectedChannels,onChange,isLoading,fetchError,onRefresh})=>{
  const handleToggleChannel = (channel: ShippingChannelConfig) => {
    if (channel.isRequiredByPlatform) return; // Cannot toggle platform-mandated channel

    const current = selectedChannels[channel.channelId]?.enabled || false;
    const nextState = {
      ...selectedChannels,
      [channel.channelId]: {
        enabled: !current,
        selectedSizeTier: selectedChannels[channel.channelId]?.selectedSizeTier,
        sellerFeeMyr: selectedChannels[channel.channelId]?.sellerFeeMyr
      }
    };
    onChange(nextState);
  };

  const handleUpdateChannelField = (
    channelId: string,
    field: 'selectedSizeTier' | 'sellerFeeMyr',
    val: any
  ) => {
    const current = selectedChannels[channelId] || { enabled: true };
    onChange({
      ...selectedChannels,
      [channelId]: {
        ...current,
        [field]: val
      }
    });
  };

  const enabledCount = Object.values(selectedChannels).filter((c: SelectedChannelState) => c?.enabled).length;

  return (
    <div id="store-shipping-channels-section" className="space-y-3">
      {/* Section Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-1">
        <div>
          <div className="flex items-center gap-2">
            <label className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5">
              <Truck className="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
              Store Shipping Channels
              <span className="text-rose-500">*</span>
            </label>
            <span className="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 border border-neutral-200 dark:border-neutral-700">
              {enabledCount} active channel{enabledCount !== 1 ? 's' : ''}
            </span>
          </div>
          <p className="text-[11px] text-neutral-500 dark:text-neutral-400 mt-0.5">
            Shipping channels available for <strong>{connection.shopName}</strong>.
          </p>
        </div>

        {/* Reload telemetry action */}
        <button
          type="button"
          onClick={onRefresh}
          disabled={isLoading}
          className="inline-flex items-center gap-1.5 text-[11px] font-medium text-neutral-500 hover:text-neutral-900 dark:hover:text-neutral-200 cursor-pointer disabled:opacity-50 self-start sm:self-auto"
        >
          <RefreshCw className={`w-3 h-3 ${isLoading ? 'animate-spin text-indigo-500' : ''}`} />
          <span>Refresh Channels</span>
        </button>
      </div>

      {/* Loading State */}
      {isLoading && (
        <div className="p-6 rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-900/40 flex flex-col items-center justify-center gap-2 text-neutral-500">
          <RefreshCw className="w-5 h-5 animate-spin text-indigo-600" />
          <span className="text-xs">Fetching active courier routes from Shopee API...</span>
        </div>
      )}

      {/* Error State with Retry */}
      {fetchError && !isLoading && (
        <div className="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 text-xs text-rose-700 dark:text-rose-300 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <AlertCircle className="w-4 h-4 shrink-0" />
            <span>{fetchError}</span>
          </div>
          <button
            type="button"
            onClick={onRefresh}
            className="px-2.5 py-1 bg-rose-600 text-white rounded-lg font-semibold hover:bg-rose-700"
          >
            Retry
          </button>
        </div>
      )}

      {/* Channels Selection Cards (2-column layout on wide screens, 1-column on narrow) */}
      {!isLoading && !fetchError && (
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          {channels.map(channel => {
            const isSelected = selectedChannels[channel.channelId]?.enabled || false;
            const channelState = selectedChannels[channel.channelId];

            return (
              <div
                key={channel.channelId}
                onClick={() => handleToggleChannel(channel)}
                className={`p-3.5 rounded-xl border transition-all flex flex-col justify-between cursor-pointer ${
                  channel.isRequiredByPlatform
                    ? 'border-indigo-500/60 dark:border-indigo-500/60 bg-indigo-50/30 dark:bg-indigo-950/20'
                    : isSelected
                    ? 'border-indigo-600 dark:border-indigo-500 bg-indigo-50/40 dark:bg-indigo-950/30 ring-1 ring-indigo-500/30 shadow-xs'
                    : 'border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 hover:border-neutral-300 dark:hover:border-neutral-700'
                }`}
              >
                {/* Header row: Checkbox + Courier Name + Badges */}
                <div className="flex items-start gap-2.5">
                  <div className="pt-0.5 shrink-0">
                    <input
                      type="checkbox"
                      checked={isSelected}
                      disabled={channel.isRequiredByPlatform}
                      aria-label={channel.name}
                      onClick={e=>e.stopPropagation()}
                      onChange={() => handleToggleChannel(channel)}
                      className={`rounded border-neutral-300 text-indigo-600 focus:ring-indigo-500 ${
                        channel.isRequiredByPlatform ? 'opacity-70 cursor-not-allowed' : 'cursor-pointer'
                      }`}
                    />
                  </div>

                  <div className="min-w-0 flex-1 space-y-1">
                    <div className="flex flex-wrap items-center gap-1.5">
                      <span className="text-xs font-bold text-neutral-900 dark:text-white break-words leading-tight">
                        {channel.name}
                      </span>
                    </div>

                    {/* Platform Requirement Notice */}
                    {channel.isRequiredByPlatform && (
                      <div className="flex items-center gap-1.5 pt-0.5">
                        <span className="inline-flex items-center gap-1 text-[10px] font-bold px-1.5 py-0.2 rounded bg-indigo-100 dark:bg-indigo-950/70 text-indigo-800 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 shrink-0">
                          <ShieldCheck className="w-3 h-3" />
                          Required by Shopee
                        </span>
                      </div>
                    )}

                    {channel.requiredReason && (
                      <p className="text-[10px] text-neutral-500 dark:text-neutral-400 leading-snug">
                        {channel.requiredReason}
                      </p>
                    )}
                  </div>
                </div>

                {/* Inline Expanded Fields (Only when channel is enabled & requires fields) */}
                {isSelected && (channel.requiresSizeSelection || channel.requiresSellerFee) && (
                  <div
                    className="mt-3 pt-2.5 border-t border-neutral-200/80 dark:border-neutral-700/80 space-y-2"
                    onClick={e => e.stopPropagation()} // Prevent card toggle when interacting with inputs
                  >
                    {/* Size Tier Dropdown */}
                    {channel.requiresSizeSelection && channel.sizeOptions && (
                      <div className="space-y-1">
                        <label className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 flex items-center justify-between">
                          <span>Parcel Size Tier:</span>
                          <span className="text-[10px] text-neutral-400">Required by courier</span>
                        </label>
                        <select
                          value={channelState?.selectedSizeTier ?? ''}
                          onChange={e =>
                            handleUpdateChannelField(channel.channelId, 'selectedSizeTier', e.target.value)
                          }
                          className="w-full px-2.5 py-1.5 bg-white dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 rounded-lg text-xs text-neutral-900 dark:text-white focus:ring-1 focus:ring-indigo-500"
                        >
                          <option value="">Select shipping size</option>
                          {channel.sizeOptions.map(opt => (
                            <option key={opt.id} value={opt.id}>
                              {opt.label}
                            </option>
                          ))}
                        </select>
                      </div>
                    )}

                    {/* Custom Seller Shipping Fee Input */}
                    {channel.requiresSellerFee && (
                      <div className="space-y-1">
                        <label className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 flex items-center justify-between">
                          <span>Shipping Fee (MYR):</span>
                          <span className="text-[10px] text-neutral-400">Required by channel</span>
                        </label>
                        <div className="relative">
                          <span className="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs font-mono text-neutral-400">
                            RM
                          </span>
                          <input
                            type="number"
                            min="0"
                            step="0.1"
                            value={channelState?.sellerFeeMyr ?? ''}
                            onChange={e =>
                              handleUpdateChannelField(
                                channel.channelId,
                                'sellerFeeMyr',
                                e.target.value===''?undefined:Number(e.target.value)
                              )
                            }
                            className="w-full pl-9 pr-3 py-1.5 bg-white dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 rounded-lg text-xs font-mono text-neutral-900 dark:text-white focus:ring-1 focus:ring-indigo-500"
                          />
                        </div>
                      </div>
                    )}
                  </div>
                )}
              </div>
            );
          })}
        </div>
      )}

      {/* Validation warning if no channels enabled */}
      {enabledCount === 0 && (
        <div className="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 text-xs text-rose-700 dark:text-rose-300 flex items-center gap-2">
          <AlertCircle className="w-4 h-4 shrink-0" />
          <span>At least one shipping channel must be enabled before publishing.</span>
        </div>
      )}
    </div>
  );
};
