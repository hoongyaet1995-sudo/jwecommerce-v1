import React, { useState } from 'react';
import { useAdmin } from '../../context/AdminContext';
import { MarketplacePlatform, MarketplaceConnection } from '../../types';
import { SubNavHeader } from '../common/SubNavHeader';
import { KeyRound, ShieldCheck, CheckCircle2, RefreshCw, Plus, Trash2, Copy, Check, Sliders, Store, Radio, Clock, Globe2, Activity, Zap, Lock } from 'lucide-react';

export const MarketplaceAuthPage: React.FC = () => {
  const {
    connections = [],
    marketplaceOrders = [],
    marketplaceInventory = [],
    marketplaceStoreProducts = [],
    syncLogs = [],
    saveConnection,
    deleteConnection,
    toggleConnectionSync,
    refreshToken,
    syncAllMarketplaces,
    addToast
  } = useAdmin();

  const safeConns = connections || [];
  const safeMpOrders = marketplaceOrders || [];
  const safeMpInv = marketplaceInventory || [];
  const safeStoreProds = marketplaceStoreProducts || [];
  const safeLogs = syncLogs || [];

  const [isConfigModalOpen, setIsConfigModalOpen] = useState(false);
  const [selectedConnection, setSelectedConnection] = useState<MarketplaceConnection | null>(null);
  const [copiedKey, setCopiedKey] = useState<string | null>(null);

  // Form states for creating/editing connection
  const [platform, setPlatform] = useState<MarketplacePlatform>('tiktok');
  const [shopName, setShopName] = useState('');
  const [shopId, setShopId] = useState('');
  const [region, setRegion] = useState('MY');
  const [appKey, setAppKey] = useState('');
  const [appSecret, setAppSecret] = useState('');
  const [autoSyncStock, setAutoSyncStock] = useState(true);
  const [autoPullOrders, setAutoPullOrders] = useState(true);
  const [syncIntervalMinutes, setSyncIntervalMinutes] = useState(5);
  const [selectedScopes, setSelectedScopes] = useState<string[]>([
    'orders.read',
    'orders.write',
    'inventory.read',
    'inventory.write',
    'logistics.awb'
  ]);

  const handleOpenNew = (plat: MarketplacePlatform = 'tiktok') => {
    setSelectedConnection(null);
    setPlatform(plat);
    setShopName(
      plat === 'tiktok' ? 'Aurora TikTok Flagship (MY)' :
      plat === 'shopee' ? 'Aurora Official Shopee Mall' :
      plat === 'lazada' ? 'Aurora LazMall Malaysia' :
      'Aurora Shopify Global'
    );
    setShopId(
      plat === 'tiktok' ? 'TTS_MY_7489201' :
      plat === 'shopee' ? '289410492' :
      plat === 'lazada' ? 'MY10948271' :
      'aurora-audio-global.myshopify.com'
    );
    setRegion(plat === 'shopify' ? 'GLOBAL' : 'MY');
    setAppKey(`app_${plat}_${Math.random().toString(36).substring(2, 10)}`);
    setAppSecret(`sec_${Math.random().toString(36).substring(2, 18)}`);
    setAutoSyncStock(true);
    setAutoPullOrders(true);
    setSyncIntervalMinutes(5);
    setIsConfigModalOpen(true);
  };

  const handleOpenEdit = (conn: MarketplaceConnection) => {
    setSelectedConnection(conn);
    setPlatform(conn.platform);
    setShopName(conn.shopName);
    setShopId(conn.shopId);
    setRegion(conn.region);
    setAppKey(conn.appKey);
    setAppSecret(conn.appSecret);
    setAutoSyncStock(conn.autoSyncStock);
    setAutoPullOrders(conn.autoPullOrders);
    setSyncIntervalMinutes(conn.syncIntervalMinutes);
    setSelectedScopes(conn.scopes || ['orders.read', 'inventory.write']);
    setIsConfigModalOpen(true);
  };

  const handleSaveConnection = (e: React.FormEvent) => {
    e.preventDefault();
    if (!shopName.trim() || !shopId.trim()) {
      addToast({ type: 'error', title: 'Missing Information', message: 'Please provide both Store Name and Shop Identifier.' });
      return;
    }

    const newConnection: MarketplaceConnection = {
      id: selectedConnection ? selectedConnection.id : `mp-${platform}-${Date.now()}`,
      platform,
      shopName,
      shopId,
      region,
      status: 'connected',
      appKey: appKey || `app_${platform}_demo`,
      appSecret: appSecret || `sec_demo_${Date.now()}`,
      accessToken: selectedConnection?.accessToken || `token_act_${Date.now().toString(36)}_${Math.random().toString(36).slice(-8)}`,
      refreshToken: selectedConnection?.refreshToken || `token_rfr_${Date.now().toString(36)}_${Math.random().toString(36).slice(-8)}`,
      tokenExpiresAt: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString(),
      webhookSecret: selectedConnection?.webhookSecret || `whsec_${Math.random().toString(36).slice(-12)}`,
      scopes: selectedScopes,
      autoSyncStock,
      autoPullOrders,
      syncIntervalMinutes,
      lastSyncAt: new Date().toISOString(),
      apiRateLimit: selectedConnection?.apiRateLimit || {
        limit: platform === 'shopify' ? 40 : 50000,
        remaining: platform === 'shopify' ? 38 : 46520,
        resetTime: new Date(Date.now() + 3600000).toISOString()
      }
    };

    saveConnection(newConnection);
    setIsConfigModalOpen(false);
  };

  const copyToClipboard = (text: string, keyName: string) => {
    navigator.clipboard.writeText(text);
    setCopiedKey(keyName);
    addToast({ type: 'info', title: 'Copied to Clipboard', message: `${keyName} copied.` });
    setTimeout(() => setCopiedKey(null), 2000);
  };

  const getPlatformDetails = (p: MarketplacePlatform) => {
    switch (p) {
      case 'tiktok':
        return {
          title: 'TikTok Shop',
          subtitle: 'TikTok Open Platform Partner API v2',
          badgeBg: 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900',
          accentColor: 'border-neutral-900 dark:border-neutral-700',
          gradient: 'from-neutral-900 via-neutral-800 to-neutral-900 dark:from-neutral-900 dark:to-neutral-950',
          iconText: 'TTS',
          docUrl: 'https://partner.tiktokshop.com/doc/page/262744'
        };
      case 'shopee':
        return {
          title: 'Shopee Shop',
          subtitle: 'Shopee Open Platform API v2.0',
          badgeBg: 'bg-amber-600 text-white',
          accentColor: 'border-amber-500',
          gradient: 'from-amber-600 to-amber-700',
          iconText: 'SHP',
          docUrl: 'https://open.shopee.com/documents'
        };
      case 'lazada':
        return {
          title: 'Lazada Shop',
          subtitle: 'Lazada Open Platform API (Alibaba Group)',
          badgeBg: 'bg-blue-600 text-white',
          accentColor: 'border-blue-600',
          gradient: 'from-blue-600 to-indigo-700',
          iconText: 'LAZ',
          docUrl: 'https://open.lazada.com/doc/api.htm'
        };
      case 'shopify':
      default:
        return {
          title: 'Shopify Shop',
          subtitle: 'Shopify Admin GraphQL & REST Open API',
          badgeBg: 'bg-emerald-600 text-white',
          accentColor: 'border-emerald-600',
          gradient: 'from-emerald-600 to-teal-700',
          iconText: 'SPF',
          docUrl: 'https://shopify.dev/docs/api/admin-rest'
        };
    }
  };

  const availableScopesList = [
    { key: 'orders.read', label: 'Read Orders & Status (Pull Incoming Stream)' },
    { key: 'orders.write', label: 'Write Order Fulfillment & Mark Shipped' },
    { key: 'inventory.read', label: 'Read Channel Warehouse Levels' },
    { key: 'inventory.write', label: 'Broadcast Stock ATP & Safe Reserve' },
    { key: 'logistics.awb', label: 'Generate AWB Shipping Labels & Courier Dispatch' },
    { key: 'webhooks.receive', label: 'Real-time Webhook Ingestion Receiver' },
    { key: 'products.read', label: 'Catalog SKU & Variant Metadata Sync' }
  ];

  const totalConnected = safeConns.filter(c => c.status === 'connected').length;
  const pendingOrdersCount = safeMpOrders.filter(o => o.channelStatus === 'AWAITING_COLLECTION' || o.channelStatus === 'UNPAID' || !o.airwayBillGenerated).length;
  const unmappedCount = safeStoreProds.reduce((acc, p) => acc + p.variants.filter(v => v.mappingStatus === 'unmapped').length, 0);

  return (
    <div className="space-y-6">
      <SubNavHeader
        hubTitle="Marketplace"
        hubSection="marketplace"
        currentPageTitle="Connect Store"
        tabs={[
          { id: 'marketplace-auth', label: 'Connect Store', badge: `${totalConnected}/4 Active`, badgeVariant: 'success' },
          { id: 'marketplace-mapping', label: 'Product & SKU Mapping', badge: unmappedCount > 0 ? `${unmappedCount} Unmapped` : 'All Mapped', badgeVariant: unmappedCount > 0 ? 'warning' : 'success' },
          { id: 'marketplace-oms', label: 'Orders (OMS)', badge: pendingOrdersCount > 0 ? `${pendingOrdersCount} Pending` : undefined, badgeVariant: 'warning' },
          { id: 'marketplace-inventory', label: 'Inventory & WMS', badge: `${safeMpInv.length} SKUs`, badgeVariant: 'info' },
          { id: 'marketplace-sync', label: 'Webhooks & Logs', badge: `${safeLogs.length}`, badgeVariant: 'default' },
        ]}
      />

      {/* Top Banner & Omnichannel Summary */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gradient-to-r from-neutral-900 via-indigo-950 to-neutral-900 text-white p-6 sm:p-8 rounded-2xl shadow-xl border border-neutral-800 relative overflow-hidden">
        <div className="absolute right-0 top-0 w-96 h-96 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none" />
        <div className="space-y-2 max-w-2xl relative z-10">
          <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/20 border border-indigo-500/30 text-indigo-300 text-xs font-semibold">
            <Zap className="w-3.5 h-3.5" />
            <span>Connect & Authorize Marketplace Stores</span>
          </div>
          <h1 className="text-2xl sm:text-3xl font-black tracking-tight">
            Connect Store & Marketplace Authorization
          </h1>
          <p className="text-sm text-neutral-300 leading-relaxed">
            Authorize and manage Open API connections for <strong className="text-white">TikTok Shop, Shopee, Lazada, and Shopify</strong>. Auto-fetch orders, sync multi-channel inventory in real-time, and generate carrier airway bills.
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-3 relative z-10">
          <button
            onClick={syncAllMarketplaces}
            className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white border border-white/15 text-xs font-semibold transition-colors cursor-pointer"
          >
            <RefreshCw className="w-4 h-4" />
            Sync All Channels
          </button>
          <button
            onClick={() => handleOpenNew('tiktok')}
            className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-600 hover:from-indigo-600 hover:to-violet-700 text-white text-xs font-bold shadow-lg shadow-indigo-500/25 transition-all cursor-pointer"
          >
            <Plus className="w-4 h-4" />
            Connect New Store
          </button>
        </div>
      </div>

      {/* Metrics Row */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between text-neutral-500 mb-2">
            <span className="text-xs font-bold uppercase tracking-wider">Connected Stores</span>
            <Store className="w-4 h-4 text-indigo-500" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-black text-neutral-900 dark:text-white">{totalConnected}</span>
            <span className="text-xs text-neutral-400">/ {connections.length} Platforms Active</span>
          </div>
          <div className="mt-3 flex items-center gap-1.5 text-[11px] text-emerald-600 dark:text-emerald-400 font-medium">
            <CheckCircle2 className="w-3.5 h-3.5" />
            <span>OAuth 2.0 Handshake Healthy</span>
          </div>
        </div>

        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between text-neutral-500 mb-2">
            <span className="text-xs font-bold uppercase tracking-wider">Token Security</span>
            <KeyRound className="w-4 h-4 text-emerald-500" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-black text-emerald-600 dark:text-emerald-400">100%</span>
            <span className="text-xs text-neutral-400">Encrypted AES-256</span>
          </div>
          <div className="mt-3 flex items-center gap-1.5 text-[11px] text-neutral-500 font-medium">
            <Clock className="w-3.5 h-3.5" />
            <span>Auto-refresh 7 days prior to expiry</span>
          </div>
        </div>

        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between text-neutral-500 mb-2">
            <span className="text-xs font-bold uppercase tracking-wider">Webhook Ingestion</span>
            <Radio className="w-4 h-4 text-violet-500" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-black text-neutral-900 dark:text-white">Active</span>
            <span className="text-xs text-emerald-500 font-bold">200 OK (0.12s latency)</span>
          </div>
          <div className="mt-3 flex items-center gap-1.5 text-[11px] text-neutral-500 font-medium">
            <Activity className="w-3.5 h-3.5 text-violet-400" />
            <span>Subscribed to Order & Stock events</span>
          </div>
        </div>

        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between text-neutral-500 mb-2">
            <span className="text-xs font-bold uppercase tracking-wider">API Quota Pool</span>
            <Globe2 className="w-4 h-4 text-cyan-500" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-black text-neutral-900 dark:text-white">93.4%</span>
            <span className="text-xs text-neutral-400">Quota Remaining</span>
          </div>
          <div className="mt-3 flex items-center gap-1.5 text-[11px] text-neutral-500 font-medium">
            <span>Rate-limit safe across 4 marketplaces</span>
          </div>
        </div>
      </div>

      {/* Connected Channel Cards List */}
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <div>
            <h2 className="text-lg font-bold text-neutral-900 dark:text-white">
              Configured Marketplace Open Channels
            </h2>
            <p className="text-xs text-neutral-500">
              Manage App Keys, Access Tokens, Webhook Handlers, and Synchronization Rules.
            </p>
          </div>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {connections.map((conn) => {
            const details = getPlatformDetails(conn.platform);
            const isConnected = conn.status === 'connected';
            const expiresDate = new Date(conn.tokenExpiresAt);
            const daysLeft = Math.max(0, Math.ceil((expiresDate.getTime() - Date.now()) / (1000 * 60 * 60 * 24)));

            return (
              <div
                key={conn.id}
                className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs hover:shadow-md transition-all overflow-hidden flex flex-col justify-between"
              >
                {/* Channel Header */}
                <div className="p-6 border-b border-neutral-100 dark:border-neutral-800/80">
                  <div className="flex items-start justify-between gap-4">
                    <div className="flex items-center gap-3.5">
                      <div className={`w-12 h-12 rounded-xl flex items-center justify-center font-black text-sm shadow-md ${details.badgeBg}`}>
                        {details.iconText}
                      </div>
                      <div>
                        <div className="flex items-center gap-2">
                          <h3 className="text-base font-bold text-neutral-900 dark:text-white">
                            {conn.shopName}
                          </h3>
                          <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 border border-neutral-200 dark:border-neutral-700">
                            {conn.region}
                          </span>
                        </div>
                        <p className="text-xs text-neutral-500 font-mono mt-0.5">
                          Shop ID: {conn.shopId}
                        </p>
                      </div>
                    </div>

                    <div className="flex items-center gap-2">
                      <span
                        className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold ${
                          isConnected
                            ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800'
                            : 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800'
                        }`}
                      >
                        <span className={`w-2 h-2 rounded-full ${isConnected ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500'}`} />
                        {isConnected ? 'Authorized' : 'Action Required'}
                      </span>
                    </div>
                  </div>

                  {/* Scopes Chips */}
                  <div className="mt-4 flex flex-wrap gap-1.5">
                    {(conn.scopes || ['orders.read_write', 'fulfillment.write', 'inventory.sync']).map((scope, idx) => (
                      <span
                        key={idx}
                        className="text-[10px] font-mono px-2 py-0.5 rounded bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-300 border border-neutral-200 dark:border-neutral-700/60"
                      >
                        {scope}
                      </span>
                    ))}
                  </div>
                </div>

                {/* Body Details */}
                <div className="p-6 space-y-4 text-xs bg-neutral-50/50 dark:bg-neutral-950/30 flex-1">
                  {/* Token & Expiry */}
                  <div className="grid grid-cols-2 gap-3 p-3 bg-white dark:bg-neutral-900 rounded-xl border border-neutral-200 dark:border-neutral-800">
                    <div>
                      <span className="text-[11px] text-neutral-500 font-medium">OAuth Token Validity</span>
                      <p className="font-semibold text-neutral-800 dark:text-neutral-200 mt-0.5">
                        {daysLeft} days remaining ({expiresDate.toLocaleDateString()})
                      </p>
                    </div>
                    <div>
                      <span className="text-[11px] text-neutral-500 font-medium">Last Sync Cycle</span>
                      <p className="font-semibold text-neutral-800 dark:text-neutral-200 mt-0.5">
                        {new Date(conn.lastSyncAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
                      </p>
                    </div>
                  </div>

                  {/* Sync Switches */}
                  <div className="grid grid-cols-2 gap-3">
                    <div className="flex items-center justify-between p-3 bg-white dark:bg-neutral-900 rounded-xl border border-neutral-200 dark:border-neutral-800">
                      <div>
                        <p className="font-semibold text-neutral-900 dark:text-white">Auto Stock Push</p>
                        <p className="text-[10px] text-neutral-500">Live delta sync</p>
                      </div>
                      <button
                        onClick={() => toggleConnectionSync(conn.id, 'autoSyncStock')}
                        className={`w-9 h-5 rounded-full transition-colors relative cursor-pointer ${
                          conn.autoSyncStock ? 'bg-indigo-600' : 'bg-neutral-300 dark:bg-neutral-700'
                        }`}
                      >
                        <span
                          className={`absolute top-0.5 left-0.5 w-4 h-4 rounded-full bg-white transition-transform ${
                            conn.autoSyncStock ? 'translate-x-4' : 'translate-x-0'
                          }`}
                        />
                      </button>
                    </div>

                    <div className="flex items-center justify-between p-3 bg-white dark:bg-neutral-900 rounded-xl border border-neutral-200 dark:border-neutral-800">
                      <div>
                        <p className="font-semibold text-neutral-900 dark:text-white">Auto Order Pull</p>
                        <p className="text-[10px] text-neutral-500">Every {conn.syncIntervalMinutes || 5} mins</p>
                      </div>
                      <button
                        onClick={() => toggleConnectionSync(conn.id, 'autoPullOrders')}
                        className={`w-9 h-5 rounded-full transition-colors relative cursor-pointer ${
                          conn.autoPullOrders ? 'bg-indigo-600' : 'bg-neutral-300 dark:bg-neutral-700'
                        }`}
                      >
                        <span
                          className={`absolute top-0.5 left-0.5 w-4 h-4 rounded-full bg-white transition-transform ${
                            conn.autoPullOrders ? 'translate-x-4' : 'translate-x-0'
                          }`}
                        />
                      </button>
                    </div>
                  </div>

                  {/* Webhook Secret Quick Copy */}
                  <div className="flex items-center justify-between p-2.5 bg-neutral-100 dark:bg-neutral-800/60 rounded-xl font-mono text-[11px]">
                    <div className="flex items-center gap-2 truncate">
                      <Lock className="w-3.5 h-3.5 text-neutral-400" />
                      <span className="text-neutral-500">Webhook Secret:</span>
                      <span className="text-neutral-800 dark:text-neutral-200 truncate">
                        {conn.webhookSecret ? `${conn.webhookSecret.substring(0, 10)}••••••••` : 'whsec_auto_generated'}
                      </span>
                    </div>
                    <button
                      onClick={() => copyToClipboard(conn.webhookSecret || 'whsec_demo', `${conn.shopName} Webhook Secret`)}
                      className="p-1 text-neutral-500 hover:text-indigo-600 cursor-pointer"
                      title="Copy Secret"
                    >
                      {copiedKey === `${conn.shopName} Webhook Secret` ? <Check className="w-3.5 h-3.5 text-emerald-500" /> : <Copy className="w-3.5 h-3.5" />}
                    </button>
                  </div>
                </div>

                {/* Footer Toolbar */}
                <div className="px-6 py-3.5 border-t border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <button
                      onClick={() => refreshToken(conn.id)}
                      className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 transition-colors cursor-pointer"
                    >
                      <RefreshCw className="w-3.5 h-3.5 text-indigo-500" />
                      Refresh Token
                    </button>
                    <button
                      onClick={() => handleOpenEdit(conn)}
                      className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 transition-colors cursor-pointer"
                    >
                      <Sliders className="w-3.5 h-3.5 text-neutral-500" />
                      Configure
                    </button>
                  </div>

                  <button
                    onClick={() => deleteConnection(conn.id)}
                    className="p-1.5 text-neutral-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30 rounded-lg transition-colors cursor-pointer"
                    title="Disconnect Store"
                  >
                    <Trash2 className="w-4 h-4" />
                  </button>
                </div>
              </div>
            );
          })}
        </div>
      </div>

      {/* Developer Webhook & Callback Specifications Card */}
      <div className="p-6 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs space-y-4">
        <div className="flex items-center gap-2">
          <ShieldCheck className="w-5 h-5 text-indigo-500" />
          <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
            Open Platform Webhook & OAuth Endpoint Specifications
          </h3>
        </div>
        <p className="text-xs text-neutral-500">
          Provide these endpoints in your TikTok Shop Partner Center, Shopee Open Platform Console, Lazada Developer Center, or Shopify App Configuration.
        </p>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div className="p-4 bg-neutral-50 dark:bg-neutral-950 rounded-xl border border-neutral-200 dark:border-neutral-800 space-y-2">
            <div className="flex items-center justify-between">
              <span className="text-xs font-bold text-neutral-700 dark:text-neutral-300">
                Universal OAuth Callback Redirect URL
              </span>
              <button
                onClick={() => copyToClipboard('https://api.auroracommerce.io/v1/auth/marketplace/callback', 'OAuth Callback URL')}
                className="text-xs text-indigo-600 hover:underline flex items-center gap-1 font-semibold cursor-pointer"
              >
                <Copy className="w-3 h-3" />
                Copy
              </button>
            </div>
            <p className="font-mono text-xs text-indigo-600 dark:text-indigo-400 bg-white dark:bg-neutral-900 p-2.5 rounded-lg border border-neutral-200 dark:border-neutral-800 break-all select-all">
              https://api.auroracommerce.io/v1/auth/marketplace/callback
            </p>
          </div>

          <div className="p-4 bg-neutral-50 dark:bg-neutral-950 rounded-xl border border-neutral-200 dark:border-neutral-800 space-y-2">
            <div className="flex items-center justify-between">
              <span className="text-xs font-bold text-neutral-700 dark:text-neutral-300">
                Inbound Webhook Receiver URL (HMAC Verified)
              </span>
              <button
                onClick={() => copyToClipboard('https://api.auroracommerce.io/v1/webhooks/marketplace/inbound', 'Webhook URL')}
                className="text-xs text-indigo-600 hover:underline flex items-center gap-1 font-semibold cursor-pointer"
              >
                <Copy className="w-3 h-3" />
                Copy
              </button>
            </div>
            <p className="font-mono text-xs text-indigo-600 dark:text-indigo-400 bg-white dark:bg-neutral-900 p-2.5 rounded-lg border border-neutral-200 dark:border-neutral-800 break-all select-all">
              https://api.auroracommerce.io/v1/webhooks/marketplace/inbound
            </p>
          </div>
        </div>
      </div>

      {/* Configure / Connect Modal */}
      {isConfigModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs overflow-y-auto">
          <div className="relative w-full max-w-2xl bg-white dark:bg-neutral-900 rounded-2xl shadow-2xl border border-neutral-200 dark:border-neutral-800 overflow-hidden my-8">
            <form onSubmit={handleSaveConnection}>
              {/* Modal Header */}
              <div className="flex items-center justify-between px-6 py-4 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950/50">
                <div className="flex items-center gap-2.5">
                  <div className="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">
                    <KeyRound className="w-5 h-5" />
                  </div>
                  <div>
                    <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                      {selectedConnection ? 'Edit Open API Connection' : 'Authorize New Marketplace Store'}
                    </h3>
                    <p className="text-xs text-neutral-500">
                      Configure App Key, App Secret, and Open API Permissions.
                    </p>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={() => setIsConfigModalOpen(false)}
                  className="text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 p-1.5 rounded-lg cursor-pointer"
                >
                  ✕
                </button>
              </div>

              {/* Form Content */}
              <div className="p-6 space-y-4 text-xs max-h-[70vh] overflow-y-auto">
                {/* Platform Selector */}
                <div>
                  <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-2">
                    Target Marketplace Platform
                  </label>
                  <div className="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                    {(['tiktok', 'shopee', 'lazada', 'shopify'] as MarketplacePlatform[]).map(p => (
                      <button
                        type="button"
                        key={p}
                        onClick={() => {
                          setPlatform(p);
                          if (!selectedConnection) {
                            setShopName(
                              p === 'tiktok' ? 'Aurora TikTok Flagship (MY)' :
                              p === 'shopee' ? 'Aurora Official Shopee Mall' :
                              p === 'lazada' ? 'Aurora LazMall Malaysia' :
                              'Aurora Shopify Global'
                            );
                            setRegion(p === 'shopify' ? 'GLOBAL' : 'MY');
                          }
                        }}
                        className={`p-3 rounded-xl border text-center transition-all cursor-pointer ${
                          platform === p
                            ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/30 text-indigo-700 dark:text-indigo-300 font-bold shadow-xs'
                            : 'border-neutral-200 dark:border-neutral-800 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-800'
                        }`}
                      >
                        <span className="block font-bold text-sm uppercase tracking-tight">{p}</span>
                        <span className="text-[10px] text-neutral-500">Open API</span>
                      </button>
                    ))}
                  </div>
                </div>

                {/* Shop Name & Shop ID */}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                  <div>
                    <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                      Store Display Name
                    </label>
                    <input
                      type="text"
                      value={shopName}
                      onChange={e => setShopName(e.target.value)}
                      placeholder="e.g. Aurora Official Store"
                      className="w-full px-3 py-2 rounded-xl bg-white dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 text-neutral-900 dark:text-white font-medium focus:outline-hidden focus:ring-2 focus:ring-indigo-500"
                      required
                    />
                  </div>

                  <div>
                    <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                      Platform Shop ID / Seller Handle
                    </label>
                    <input
                      type="text"
                      value={shopId}
                      onChange={e => setShopId(e.target.value)}
                      placeholder="e.g. 74892019482 or store.myshopify.com"
                      className="w-full px-3 py-2 rounded-xl bg-white dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 font-mono text-neutral-900 dark:text-white focus:outline-hidden focus:ring-2 focus:ring-indigo-500"
                      required
                    />
                  </div>
                </div>

                {/* Region & Sync Interval */}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                      Marketplace Operating Region
                    </label>
                    <select
                      value={region}
                      onChange={e => setRegion(e.target.value)}
                      className="w-full px-3 py-2 rounded-xl bg-white dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 text-neutral-900 dark:text-white font-semibold focus:outline-hidden focus:ring-2 focus:ring-indigo-500"
                    >
                      <option value="MY">Malaysia (MYR)</option>
                      <option value="SG">Singapore (SGD)</option>
                      <option value="ID">Indonesia (IDR)</option>
                      <option value="TH">Thailand (THB)</option>
                      <option value="VN">Vietnam (VND)</option>
                      <option value="PH">Philippines (PHP)</option>
                      <option value="GLOBAL">Global / US (USD)</option>
                    </select>
                  </div>

                  <div>
                    <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                      Background Order Ingestion Interval
                    </label>
                    <select
                      value={syncIntervalMinutes}
                      onChange={e => setSyncIntervalMinutes(parseInt(e.target.value))}
                      className="w-full px-3 py-2 rounded-xl bg-white dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 text-neutral-900 dark:text-white font-semibold focus:outline-hidden focus:ring-2 focus:ring-indigo-500"
                    >
                      <option value={1}>Every 1 Minute (Ultra Real-time)</option>
                      <option value={5}>Every 5 Minutes (Recommended)</option>
                      <option value={15}>Every 15 Minutes</option>
                      <option value={30}>Every 30 Minutes</option>
                    </select>
                  </div>
                </div>

                {/* App Key & App Secret */}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                  <div>
                    <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                      Open API App Key / Client ID
                    </label>
                    <input
                      type="text"
                      value={appKey}
                      onChange={e => setAppKey(e.target.value)}
                      placeholder="app_key_..."
                      className="w-full px-3 py-2 rounded-xl bg-white dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 font-mono text-neutral-900 dark:text-white focus:outline-hidden focus:ring-2 focus:ring-indigo-500"
                    />
                  </div>

                  <div>
                    <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                      App Secret Key (AES-256)
                    </label>
                    <input
                      type="password"
                      value={appSecret}
                      onChange={e => setAppSecret(e.target.value)}
                      placeholder="••••••••••••••••"
                      className="w-full px-3 py-2 rounded-xl bg-white dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 font-mono text-neutral-900 dark:text-white focus:outline-hidden focus:ring-2 focus:ring-indigo-500"
                    />
                  </div>
                </div>

                {/* Scopes checkboxes */}
                <div className="pt-2">
                  <label className="block font-semibold text-neutral-700 dark:text-neutral-300 mb-2">
                    Authorized API Scopes
                  </label>
                  <div className="grid grid-cols-1 gap-2 p-3 bg-neutral-50 dark:bg-neutral-950 rounded-xl border border-neutral-200 dark:border-neutral-800">
                    {availableScopesList.map(scope => {
                      const isChecked = selectedScopes.includes(scope.key);
                      return (
                        <label
                          key={scope.key}
                          className="flex items-center gap-2.5 cursor-pointer hover:text-indigo-600 select-none text-neutral-700 dark:text-neutral-300"
                        >
                          <input
                            type="checkbox"
                            checked={isChecked}
                            onChange={() => {
                              if (isChecked) {
                                setSelectedScopes(selectedScopes.filter(s => s !== scope.key));
                              } else {
                                setSelectedScopes([...selectedScopes, scope.key]);
                              }
                            }}
                            className="rounded border-neutral-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4 cursor-pointer"
                          />
                          <span className="font-mono font-semibold text-[11px] text-indigo-600 dark:text-indigo-400">
                            {scope.key}
                          </span>
                          <span className="text-neutral-500 text-[11px]">— {scope.label}</span>
                        </label>
                      );
                    })}
                  </div>
                </div>
              </div>

              {/* Modal Footer */}
              <div className="flex items-center justify-between px-6 py-4 border-t border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950/50">
                <button
                  type="button"
                  onClick={() => setIsConfigModalOpen(false)}
                  className="px-4 py-2 text-xs font-semibold text-neutral-600 dark:text-neutral-400 hover:bg-neutral-200 dark:hover:bg-neutral-800 rounded-xl transition-colors cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-xs transition-colors cursor-pointer"
                >
                  <ShieldCheck className="w-4 h-4" />
                  Save & Authorize API Connection
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

