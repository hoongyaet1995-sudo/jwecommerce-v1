import React from 'react';
import { useAdmin } from '../../context/AdminContext';
import { ChannelSkuMapping } from '../../types';
import {
  KeyRound,
  Boxes,
  Warehouse,
  Radio,
  ArrowRight,
  ShieldCheck,
  RefreshCw,
  Clock,
  TrendingUp,
  CheckCircle2,
  AlertTriangle,
  Zap,
  Globe,
  Layers,
  Sparkles
} from 'lucide-react';

export const MarketplaceHub: React.FC = () => {
  const {
    setCurrentSection,
    connections = [],
    marketplaceOrders = [],
    marketplaceInventory = [],
    syncLogs = [],
    addToast,
  } = useAdmin();

  const isSyncingMarketplace = false;
  const triggerChannelSyncAll = () => addToast({type:'info', title:'Unavailable', message:'This operation is not enabled. No changes were made.'});

  const safeConns = connections || [];
  const safeMpOrders = marketplaceOrders || [];
  const safeMpInv = marketplaceInventory || [];
  const safeLogs = syncLogs || [];

  const connectedCount = safeConns.filter((c) => c.status === 'connected').length;
  const pendingOrders = safeMpOrders.filter(
    (o) => o.channelStatus === 'AWAITING_COLLECTION' || o.channelStatus === 'UNPAID' || !o.airwayBillGenerated
  );
  const urgentOrders = safeMpOrders.filter((o) => o.isSlaBreachRisk);
  const totalMappedChannels = safeMpInv.reduce((acc, item) => {
    const mappings = Object.values(item.channelMappings || {}) as (ChannelSkuMapping | undefined)[];
    return acc + mappings.filter((m) => Boolean(m && m.syncStatus !== 'unlinked')).length;
  }, 0);

  const featureCards = [
    {
      id: 'marketplace-auth' as const,
      title: 'Open Platform Auth',
      shortName: 'Store Connections',
      description: 'Manage multi-channel OAuth tokens, API app keys & secrets, shop IDs, and webhook registration across TikTok Shop, Shopee, Lazada, and Shopify.',
      icon: KeyRound,
      iconBg: 'from-amber-500 to-orange-600',
      badge: `${connectedCount}/4 Connected`,
      badgeColor: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/20',
      highlights: [
        `${connectedCount} Active Stores Connected`,
        'OAuth 2.0 Auto Token Refresh',
        'Webhook Secret Verification',
        'Production & Sandbox Switcher',
      ],
      actionLabel: 'Configure Auth & Stores',
    },
    {
      id: 'marketplace-oms' as const,
      title: 'Marketplace Orders (OMS)',
      shortName: 'Order Stream & AWB',
      description: 'Unified multi-channel order stream with live SLA penalty countdowns, batch Airway Bill (AWB) generation, packing verification, and carrier handover.',
      icon: Boxes,
      iconBg: 'from-indigo-500 to-blue-600',
      badge: pendingOrders.length > 0 ? `${pendingOrders.length} Pending Dispatch` : 'All Shipped',
      badgeColor: pendingOrders.length > 0 ? 'bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-500/20' : 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/20',
      highlights: [
        `${safeMpOrders.length} Total Channel Orders`,
        `${urgentOrders.length} Urgent SLA Breach Risk`,
        'Batch 1-Click AWB PDF Generator',
        'Carrier Dispatch Handover Flow',
      ],
      actionLabel: 'Manage Marketplace OMS',
    },
    {
      id: 'marketplace-inventory' as const,
      title: 'Marketplace Inventory & WMS',
      shortName: 'Stock Mapping & ATP',
      description: 'Master SKU to Channel SKU mapping matrix with Available-To-Promise (ATP = Physical - Reserved - Safety) stock formula and multi-channel broadcast.',
      icon: Warehouse,
      iconBg: 'from-cyan-500 to-blue-600',
      badge: `${safeMpInv.length} Master SKUs`,
      badgeColor: 'bg-cyan-500/10 text-cyan-700 dark:text-cyan-300 border-cyan-500/20',
      highlights: [
        `${totalMappedChannels} Active Channel SKU Links`,
        'Real-time ATP Formula Calculation',
        'Custom Price Multiplier Rules',
        'Bin Location & Audit Trail',
      ],
      actionLabel: 'Open WMS & SKU Mapping',
    },
    {
      id: 'marketplace-sync' as const,
      title: 'Webhooks & Sync Stream',
      shortName: 'Webhook Stream & Logs',
      description: 'Real-time webhook event ingress stream, live JSON payload inspector, latency measurements, and automated retry mechanism for failed dispatches.',
      icon: Radio,
      iconBg: 'from-violet-500 to-purple-600',
      badge: `${safeLogs.length} Events Logged`,
      badgeColor: 'bg-violet-500/10 text-violet-700 dark:text-violet-300 border-violet-500/20',
      highlights: [
        'Live Webhook Ping Ingestion',
        'Raw JSON Payload Inspector',
        'Sub-120ms Latency Monitor',
        '1-Click Failed Job Retry Flow',
      ],
      actionLabel: 'Inspect Sync Stream',
    },
  ];

  return (
    <div className="space-y-6">
      {/* Header Banner */}
      <div className="relative overflow-hidden rounded-2xl bg-gradient-to-r from-neutral-900 via-indigo-950 to-neutral-900 border border-neutral-800 p-6 sm:p-8 text-white shadow-lg">
        <div className="absolute top-0 right-0 -mt-12 -mr-12 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none" />
        <div className="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
          <div className="space-y-2 max-w-2xl">
            <div className="flex items-center gap-2">
              <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                <Zap className="w-3.5 h-3.5 text-indigo-400" />
                Omnichannel Central
              </span>
              <span className="text-xs text-neutral-400">Open Platform OMS & WMS</span>
            </div>
            <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-white">
              Marketplace Ecosystem
            </h1>
            <p className="text-sm text-neutral-300 leading-relaxed">
              Connect external channels, synchronize master stock via Available-To-Promise (ATP) calculations, process multi-store orders with Airway Bill (AWB) generation, and track real-time webhook streams.
            </p>
          </div>

          <div className="flex flex-wrap items-center gap-3 shrink-0">
            <button
              onClick={triggerChannelSyncAll}
              disabled={isSyncingMarketplace}
              className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold bg-indigo-600 hover:bg-indigo-500 text-white shadow-md shadow-indigo-600/30 transition cursor-pointer disabled:opacity-50"
            >
              <RefreshCw className={`w-4 h-4 ${isSyncingMarketplace ? 'animate-spin' : ''}`} />
              <span>{isSyncingMarketplace ? 'Broadcasting...' : 'Sync All Channels'}</span>
            </button>
          </div>
        </div>

        {/* Quick KPI Bar */}
        <div className="mt-6 pt-6 border-t border-neutral-800 grid grid-cols-2 sm:grid-cols-4 gap-4">
          <div className="p-3 rounded-xl bg-white/5 border border-white/5 backdrop-blur-xs">
            <p className="text-[11px] font-medium text-neutral-400">Connected Channels</p>
            <p className="text-xl font-bold text-white mt-0.5">{connectedCount} / 4</p>
            <span className="text-[10px] text-emerald-400 flex items-center gap-1 mt-1">
              <CheckCircle2 className="w-3 h-3" /> TikTok, Shopee, Lazada, Shopify
            </span>
          </div>

          <div className="p-3 rounded-xl bg-white/5 border border-white/5 backdrop-blur-xs">
            <p className="text-[11px] font-medium text-neutral-400">Orders Awaiting Dispatch</p>
            <p className="text-xl font-bold text-white mt-0.5">{pendingOrders.length}</p>
            <span className="text-[10px] text-amber-400 flex items-center gap-1 mt-1">
              <Clock className="w-3 h-3" /> {urgentOrders.length} SLA breach alerts
            </span>
          </div>

          <div className="p-3 rounded-xl bg-white/5 border border-white/5 backdrop-blur-xs">
            <p className="text-[11px] font-medium text-neutral-400">Master SKU Links</p>
            <p className="text-xl font-bold text-white mt-0.5">{totalMappedChannels}</p>
            <span className="text-[10px] text-cyan-400 flex items-center gap-1 mt-1">
              <Layers className="w-3 h-3" /> Across {safeMpInv.length} Master SKUs
            </span>
          </div>

          <div className="p-3 rounded-xl bg-white/5 border border-white/5 backdrop-blur-xs">
            <p className="text-[11px] font-medium text-neutral-400">Webhook Health</p>
            <p className="text-xl font-bold text-white mt-0.5">99.8%</p>
            <span className="text-[10px] text-violet-400 flex items-center gap-1 mt-1">
              <TrendingUp className="w-3 h-3" /> {safeLogs.length} events logged
            </span>
          </div>
        </div>
      </div>

      {/* 4 Main Feature Cards Grid (User's specific request) */}
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-bold text-neutral-900 dark:text-white">
            Marketplace Functional Modules
          </h2>
          <span className="text-xs text-neutral-500">Click any card to access detailed workspace</span>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
          {featureCards.map((card) => {
            const Icon = card.icon;
            return (
              <div
                key={card.id}
                onClick={() => setCurrentSection(card.id)}
                className="group relative flex flex-col justify-between p-6 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 hover:border-indigo-300 dark:hover:border-indigo-700/60 transition-all duration-200 shadow-xs hover:shadow-md cursor-pointer"
              >
                <div>
                  <div className="flex items-start justify-between gap-4">
                    <div className="flex items-center gap-3.5">
                      <div
                        className={`w-12 h-12 rounded-xl bg-gradient-to-tr ${card.iconBg} flex items-center justify-center text-white shadow-md group-hover:scale-105 transition-transform`}
                      >
                        <Icon className="w-6 h-6" />
                      </div>
                      <div>
                        <span className="text-[11px] uppercase tracking-wider font-semibold text-neutral-400">
                          {card.shortName}
                        </span>
                        <h3 className="text-base font-bold text-neutral-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                          {card.title}
                        </h3>
                      </div>
                    </div>

                    <span
                      className={`text-xs font-semibold px-2.5 py-1 rounded-full border ${card.badgeColor} shrink-0`}
                    >
                      {card.badge}
                    </span>
                  </div>

                  <p className="mt-4 text-xs text-neutral-600 dark:text-neutral-400 leading-relaxed">
                    {card.description}
                  </p>

                  <div className="mt-4 pt-4 border-t border-neutral-100 dark:border-neutral-800/80 grid grid-cols-2 gap-2">
                    {card.highlights.map((h, i) => (
                      <div key={i} className="flex items-center gap-1.5 text-[11px] text-neutral-600 dark:text-neutral-400">
                        <div className="w-1.5 h-1.5 rounded-full bg-indigo-500 shrink-0" />
                        <span className="truncate">{h}</span>
                      </div>
                    ))}
                  </div>
                </div>

                <div className="mt-6 pt-4 border-t border-neutral-100 dark:border-neutral-800 flex items-center justify-between text-xs font-bold text-indigo-600 dark:text-indigo-400 group-hover:translate-x-0.5 transition-transform">
                  <span>{card.actionLabel}</span>
                  <div className="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center group-hover:bg-indigo-600 group-hover:text-white transition-colors">
                    <ArrowRight className="w-4 h-4" />
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
};
