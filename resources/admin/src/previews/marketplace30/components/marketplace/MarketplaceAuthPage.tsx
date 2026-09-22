import React, { useState } from 'react';
import { useAdmin } from '../../context/ContextBridge';
import {marketplaceOperations, requestMarketplaceAuthorization, OAuthResult} from '../../../../integration/marketplaceOperations';
import { MarketplacePlatform, MarketplaceConnection } from '../../types';
import { MarketplaceLogo, getPlatformMeta } from './MarketplaceLogo';
import { SubNavHeader } from '../common/SubNavHeader';
import { ConnectStoreModal } from './ConnectStoreModal';
import { DisconnectStoreModal } from './DisconnectStoreModal';
import { KeyRound, ShieldCheck, CheckCircle2, AlertTriangle, RefreshCw, Plus, Trash2, Copy, Check, Store, Radio, Clock, Globe2, Activity, Lock, Sparkles, PowerOff, RotateCcw } from 'lucide-react';

export const MarketplaceAuthPage: React.FC<{operational?: boolean; oauthAvailability?: Partial<Record<MarketplacePlatform,boolean>>; authorizationResult?: OAuthResult|null; onRetryOAuthBatch?:(id:string)=>Promise<void>; canManagePublishing?:boolean; onSetProductPublishing?:(storeId:string,enabled:boolean,reason:string)=>Promise<void>}> = ({operational=false,oauthAvailability={},authorizationResult=null,onRetryOAuthBatch,canManagePublishing=false,onSetProductPublishing}) => {
  const {
    connections = [],
    marketplaceOrders = [],
    marketplaceInventory = [],
    marketplaceStoreProducts = [],
    syncLogs = [],
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

  // Modals state
  const [isConnectModalOpen, setIsConnectModalOpen] = useState(false);
  const [visibleResult,setVisibleResult]=useState<OAuthResult|null>(null);
  React.useEffect(()=>{
    if(operational && authorizationResult){setVisibleResult(authorizationResult);setIsConnectModalOpen(true);}
  },[operational,authorizationResult]);
  const [connectModalMode, setConnectModalMode] = useState<'connect' | 'reauthorize'>('connect');
  const [selectedConnection, setSelectedConnection] = useState<MarketplaceConnection | null>(null);
  const [testAccessTarget, setTestAccessTarget] = useState<MarketplaceConnection | null>(null);
  const [testAccessToken, setTestAccessToken] = useState('');
  const [testAccessError, setTestAccessError] = useState<string | null>(null);
  const [isRenewingTestAccess, setIsRenewingTestAccess] = useState(false);
  const [testAccessRenewed, setTestAccessRenewed] = useState(false);
  const [publishingTarget,setPublishingTarget]=useState<MarketplaceConnection|null>(null);
  const [publishingReason,setPublishingReason]=useState('');
  const [publishingBusy,setPublishingBusy]=useState(false);
  const [publishingError,setPublishingError]=useState<string|null>(null);

  const [isDisconnectModalOpen, setIsDisconnectModalOpen] = useState(false);
  const [disconnectTarget, setDisconnectTarget] = useState<MarketplaceConnection | null>(null);

  const [copiedKey, setCopiedKey] = useState<string | null>(null);

  const submitPublishingCapability=async(event:React.FormEvent)=>{
    event.preventDefault();
    const reason=String(new FormData(event.currentTarget as HTMLFormElement).get('reason')||'').trim();
    if(!publishingTarget||!onSetProductPublishing||!reason)return;
    const enabled=!publishingTarget.capabilities?.productPublish;
    setPublishingBusy(true);setPublishingError(null);
    try{
      await onSetProductPublishing(publishingTarget.id,enabled,reason);
      setPublishingTarget(null);setPublishingReason('');
    }catch(error){setPublishingError(error instanceof Error?error.message:'The publishing setting could not be updated.');}
    finally{setPublishingBusy(false);}
  };

  // Open modal in "connect" mode
  const handleOpenConnect = (plat?: MarketplacePlatform) => {
    setVisibleResult(null);
    setSelectedConnection(null);
    setConnectModalMode('connect');
    setIsConnectModalOpen(true);
  };

  // Open modal in "reauthorize" mode
  const handleOpenReauthorize = (conn: MarketplaceConnection) => {
    if (operational && conn.credentialSource === 'official_test') {
      setTestAccessTarget(conn);
      setTestAccessToken('');
      setTestAccessError(null);
      setTestAccessRenewed(false);
      return;
    }
    setVisibleResult(null);
    setSelectedConnection(conn);
    setConnectModalMode('reauthorize');
    setIsConnectModalOpen(true);
  };

  const renewOfficialTestAccess = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!testAccessTarget || !testAccessToken.trim()) return;
    setIsRenewingTestAccess(true);
    setTestAccessError(null);
    try {
      await marketplaceOperations.renewLazadaTestAccess(testAccessTarget.id, testAccessToken.trim());
      setTestAccessToken('');
      setTestAccessRenewed(true);
      addToast({type:'success', title:'Test access renewed', message:'The official Lazada test store is connected and ready for controlled sync.'});
    } catch (error) {
      setTestAccessError(error instanceof Error ? error.message : 'Test access could not be verified. Generate a fresh token and try again.');
    } finally {
      setIsRenewingTestAccess(false);
    }
  };

  // Open modal in "disconnect" mode
  const handleOpenDisconnect = (conn: MarketplaceConnection) => {
    setDisconnectTarget(conn);
    setIsDisconnectModalOpen(true);
  };

  const copyToClipboard = (text: string, keyName: string) => {
    navigator.clipboard.writeText(text);
    setCopiedKey(keyName);
    addToast({ type: 'info', title: 'Copied to Clipboard', message: `${keyName} copied.` });
    setTimeout(() => setCopiedKey(null), 2000);
  };

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
          { id: 'marketplace-auth', label: 'Connect Store', badge: operational ? `${totalConnected} Connected` : `${totalConnected}/4 Active`, badgeVariant: 'success' },
          { id: 'marketplace-mapping', label: 'Product & SKU Mapping', badge: operational ? undefined : unmappedCount > 0 ? `${unmappedCount} Unmapped` : 'All Mapped', badgeVariant: unmappedCount > 0 ? 'warning' : 'success' },
          { id: 'marketplace-oms', label: 'Orders (OMS)', badge: pendingOrdersCount > 0 ? `${pendingOrdersCount} Pending` : undefined, badgeVariant: 'warning' },
          { id: 'marketplace-inventory', label: 'Inventory & WMS', badge: operational ? undefined : `${safeMpInv.length} SKUs`, badgeVariant: 'info' },
          { id: 'marketplace-sync', label: 'Webhooks & Logs', badge: operational ? undefined : `${safeLogs.length}`, badgeVariant: 'default' },
        ]}
      />

      {/* Top Banner & Omnichannel Summary */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gradient-to-r from-neutral-900 via-indigo-950 to-neutral-900 text-white p-6 sm:p-8 rounded-3xl shadow-xl border border-neutral-800 relative overflow-hidden">
        <div className="absolute right-0 top-0 w-96 h-96 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none" />
        <div className="space-y-2 max-w-2xl relative z-10">
          <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/20 border border-indigo-500/30 text-indigo-300 text-xs font-semibold">
            <ShieldCheck className="w-3.5 h-3.5" />
            <span>Official Merchant OAuth 2.0 Gateway</span>
          </div>
          <h1 className="text-2xl sm:text-3xl font-black tracking-tight">
            Marketplace Store Authorization
          </h1>
          <p className="text-sm text-neutral-300 leading-relaxed">
            {operational ? 'Connect your stores through official seller authorization. Connecting a store does not automatically enable inventory or fulfillment writes.' : 'Connect and authorize your marketplace stores via official OAuth 2.0 seller portals. Enjoy automatic inventory broadcasting (ATP), real-time order ingestion, and multi-carrier AWB label generation without managing developer keys.'}
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-3 relative z-10">
          <button
            type="button"
            onClick={syncAllMarketplaces}
            disabled={operational}
            className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white border border-white/15 text-xs font-semibold transition-colors cursor-pointer"
          >
            <RefreshCw className="w-4 h-4" />
            Sync All Channels
          </button>

          <button
            type="button"
            onClick={() => handleOpenConnect()}
            className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-lg shadow-indigo-600/25 transition-all cursor-pointer"
          >
            <Plus className="w-4 h-4" />
            Connect Store
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
            <span>{operational ? (totalConnected ? 'Authorized stores available' : 'No authorized stores') : 'OAuth 2.0 Handshake Active'}</span>
          </div>
        </div>

        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between text-neutral-500 mb-2">
            <span className="text-xs font-bold uppercase tracking-wider">Token Security</span>
            <KeyRound className="w-4 h-4 text-emerald-500" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-black text-emerald-600 dark:text-emerald-400">{operational ? 'Encrypted' : '100%'}</span>
            <span className="text-xs text-neutral-400">Encrypted AES-256</span>
          </div>
          <div className="mt-3 flex items-center gap-1.5 text-[11px] text-neutral-500 font-medium">
            <Clock className="w-3.5 h-3.5" />
            <span>{operational ? 'Credentials remain on the server' : 'Auto-refresh enabled via Laravel'}</span>
          </div>
        </div>

        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between text-neutral-500 mb-2">
            <span className="text-xs font-bold uppercase tracking-wider">Webhook Ingestion</span>
            <Radio className="w-4 h-4 text-violet-500" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-black text-neutral-900 dark:text-white">{operational ? 'Not enabled' : 'Active'}</span>
            <span className="text-xs text-emerald-500 font-bold">{operational ? 'Pending integration' : '200 OK (0.12s)'}</span>
          </div>
          <div className="mt-3 flex items-center gap-1.5 text-[11px] text-neutral-500 font-medium">
            <Activity className="w-3.5 h-3.5 text-violet-400" />
            <span>{operational ? 'No webhook health result available' : 'Listening for Order & Stock webhooks'}</span>
          </div>
        </div>

        <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
          <div className="flex items-center justify-between text-neutral-500 mb-2">
            <span className="text-xs font-bold uppercase tracking-wider">API Quota Pool</span>
            <Globe2 className="w-4 h-4 text-cyan-500" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-black text-neutral-900 dark:text-white">{operational ? 'Unavailable' : '96.8%'}</span>
            <span className="text-xs text-neutral-400">Quota Remaining</span>
          </div>
          <div className="mt-3 flex items-center gap-1.5 text-[11px] text-neutral-500 font-medium">
            <span>{operational ? 'No platform quota report available' : 'Rate-limit safe across all channels'}</span>
          </div>
        </div>
      </div>

      {/* Connected Channel Cards List */}
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <div>
            <h2 className="text-lg font-bold text-neutral-900 dark:text-white">
              Marketplace Store Connections
            </h2>
            <p className="text-xs text-neutral-500">
              Manage authorized merchant channels, re-authorize expiring tokens, and configure synchronization rules.
            </p>
          </div>

          <button
            type="button"
            onClick={() => handleOpenConnect()}
            className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-neutral-100 dark:bg-neutral-800 hover:bg-neutral-200 dark:hover:bg-neutral-700 text-neutral-800 dark:text-neutral-200 text-xs font-bold transition-colors cursor-pointer"
          >
            <Plus className="w-4 h-4 text-indigo-500" />
            Connect Another Store
          </button>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {connections.map((conn) => {
            const platformMeta = getPlatformMeta(conn.platform);
            const isConnected = conn.status === 'connected';
            const isOfficialTest = operational && conn.credentialSource === 'official_test';
            const accountMode = conn.accountMode === 'test' ? 'Test account' : 'Production account';
            const publishingLabel = conn.capabilities?.productPublish ? 'Publishing enabled' : 'Publishing disabled';
            const expiresDate = new Date(isOfficialTest ? conn.testLeaseExpiresAt || '' : conn.tokenExpiresAt);
            const daysLeft = Math.max(0, Math.ceil((expiresDate.getTime() - Date.now()) / (1000 * 60 * 60 * 24)));

            return (
              <div
                key={conn.id}
                className={`bg-white dark:bg-neutral-900 rounded-3xl border shadow-xs transition-all overflow-hidden flex flex-col justify-between ${
                  isConnected
                    ? 'border-neutral-200 dark:border-neutral-800 hover:shadow-md'
                    : 'border-rose-200/80 dark:border-rose-900/60 bg-rose-50/10'
                }`}
              >
                {/* Channel Header */}
                <div className="p-6 border-b border-neutral-100 dark:border-neutral-800/80">
                  <div className="flex items-start justify-between gap-4">
                    <div className="flex items-center gap-3.5">
                      <MarketplaceLogo platform={conn.platform} size={48} variant="avatar" />
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
                          {platformMeta.name} • Shop ID: {conn.shopId}
                        </p>
                      </div>
                    </div>

                    <div className="flex items-center gap-2">
                      <span
                        className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold ${
                          isConnected
                            ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800'
                            : 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800'
                        }`}
                      >
                        <span className={`w-2 h-2 rounded-full ${isConnected ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500'}`} />
                        {isConnected ? (isOfficialTest ? 'Test Connection Active' : 'Authorized & Active') : 'Disconnected'}
                      </span>
                    </div>
                  </div>

                  {/* Scopes Chips */}
                  <div className="mt-4 flex flex-wrap gap-1.5">
                    {(operational ? [accountMode, publishingLabel, isOfficialTest ? 'Official test token · Seller OAuth not verified' : 'Official authorization'] : conn.authorizedScopesSummary || conn.scopes || [
                      'Catalog & Listings',
                      'Inventory ATP Sync',
                      'Orders & Fulfillments',
                      'Logistics AWB'
                    ]).map((scope, idx) => (
                      <span
                        key={idx}
                        className="text-[10px] font-medium px-2.5 py-0.5 rounded-lg bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-300 border border-neutral-200 dark:border-neutral-700/60"
                      >
                        {scope}
                      </span>
                    ))}
                  </div>
                </div>

                {/* Body Details */}
                <div className="p-6 space-y-4 text-xs bg-neutral-50/50 dark:bg-neutral-950/30 flex-1">
                  {/* Disconnected Notification Banner if applicable */}
                  {!isConnected && (
                    <div className="p-3.5 rounded-2xl bg-rose-50/80 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/60 space-y-1 text-xs">
                      <div className="flex items-center gap-1.5 font-bold text-rose-800 dark:text-rose-200">
                        <AlertTriangle className="w-4 h-4 text-rose-600 dark:text-rose-400 flex-shrink-0" />
                        <span>Synchronization Suspended</span>
                      </div>
                      <p className="text-rose-700/90 dark:text-rose-300/90 text-[11px] leading-relaxed">
                        This store was disconnected ({conn.disconnectReason || 'Manual pause'}). Order ingestion and inventory broadcasting are on hold. Existing SKU mappings remain intact.
                      </p>
                    </div>
                  )}

                  {/* Token & Expiry */}
                  <div className="grid grid-cols-2 gap-3 p-3.5 bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800">
                    <div>
                      <span className="text-[11px] text-neutral-500 font-medium">{isOfficialTest ? 'Local Test Access Validity' : 'OAuth Token Validity'}</span>
                      <p className={`font-semibold mt-0.5 ${isConnected ? 'text-neutral-800 dark:text-neutral-200' : 'text-neutral-400 line-through'}`}>
                        {isConnected ? (Number.isFinite(daysLeft) ? (operational ? expiresDate.toLocaleString() : `${daysLeft} days remaining`) : 'Unavailable') : 'Revoked / Paused'}
                      </p>
                    </div>
                    <div>
                      <span className="text-[11px] text-neutral-500 font-medium">Last Sync Cycle</span>
                      <p className="font-semibold text-neutral-800 dark:text-neutral-200 mt-0.5">
                        {conn.lastSyncAt && Number.isFinite(Date.parse(conn.lastSyncAt)) ? new Date(conn.lastSyncAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }) : 'Unavailable'}
                      </p>
                    </div>
                  </div>

                  {/* Sync Switches (only relevant if connected) */}
                  {isConnected && (
                    <div className={`grid gap-3 ${operational&&canManagePublishing&&onSetProductPublishing&&conn.accountMode==='production'?'grid-cols-3':'grid-cols-2'}`}>
                      <div className="flex items-center justify-between p-3 bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800">
                        <div>
                          <p className="font-semibold text-neutral-900 dark:text-white">Auto Stock Push</p>
                          <p className="text-[10px] text-neutral-500">Live delta sync</p>
                        </div>
                        <button
                          type="button"
                          onClick={() => toggleConnectionSync(conn.id, 'autoSyncStock')}
                          disabled={operational}
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

                      <div className="flex items-center justify-between p-3 bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800">
                        <div>
                          <p className="font-semibold text-neutral-900 dark:text-white">Auto Order Pull</p>
                          <p className="text-[10px] text-neutral-500">Every {conn.syncIntervalMinutes || 5} mins</p>
                        </div>
                        <button
                          type="button"
                          onClick={() => toggleConnectionSync(conn.id, 'autoPullOrders')}
                          disabled={operational}
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

                      {operational&&canManagePublishing&&onSetProductPublishing&&conn.accountMode==='production'&&(
                        <div className="flex items-center justify-between p-3 bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800">
                          <div>
                            <p className="font-semibold text-neutral-900 dark:text-white">Product Publishing</p>
                            <p className="text-[10px] text-neutral-500">Create marketplace listings</p>
                          </div>
                          <button type="button" aria-label={`${conn.capabilities?.productPublish?'Disable':'Enable'} product publishing for ${conn.shopName}`}
                            onClick={()=>{setPublishingTarget(conn);setPublishingReason('');setPublishingError(null);}}
                            className={`w-9 h-5 rounded-full transition-colors relative cursor-pointer ${conn.capabilities?.productPublish?'bg-indigo-600':'bg-neutral-300 dark:bg-neutral-700'}`}>
                            <span className={`absolute top-0.5 left-0.5 w-4 h-4 rounded-full bg-white transition-transform ${conn.capabilities?.productPublish?'translate-x-4':'translate-x-0'}`} />
                          </button>
                        </div>
                      )}
                    </div>
                  )}

                  {/* Webhook Secret Quick Copy */}
                  {!operational && (
                  <div className="flex items-center justify-between p-2.5 bg-neutral-100 dark:bg-neutral-800/60 rounded-xl font-mono text-[11px]">
                    <div className="flex items-center gap-2 truncate">
                      <Lock className="w-3.5 h-3.5 text-neutral-400" />
                      <span className="text-neutral-500">Webhook Secret:</span>
                      <span className="text-neutral-800 dark:text-neutral-200 truncate">
                        {conn.webhookSecret ? `${conn.webhookSecret.substring(0, 10)}••••••••` : 'whsec_auto_generated'}
                      </span>
                    </div>
                    <button
                      type="button"
                      onClick={() => copyToClipboard(conn.webhookSecret || 'whsec_demo', `${conn.shopName} Webhook Secret`)}
                      className="p-1 text-neutral-500 hover:text-indigo-600 cursor-pointer"
                      title="Copy Secret"
                    >
                      {copiedKey === `${conn.shopName} Webhook Secret` ? <Check className="w-3.5 h-3.5 text-emerald-500" /> : <Copy className="w-3.5 h-3.5" />}
                    </button>
                  </div>
                  )}
                </div>

                {/* Footer Toolbar */}
                <div className="px-6 py-4 border-t border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 flex items-center justify-between">
                  {isConnected ? (
                    <>
                      <div className="flex items-center gap-2">
                        <button
                          type="button"
                          onClick={() => handleOpenReauthorize(conn)}
                          className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-950/50 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 border border-indigo-200 dark:border-indigo-800 transition-colors cursor-pointer"
                        >
                          <Sparkles className="w-3.5 h-3.5 text-indigo-500" />
                          {isOfficialTest ? 'Renew Test Access' : 'Re-authorize Store'}
                        </button>
                        <button
                          type="button"
                          onClick={() => refreshToken(conn.id)}
                          disabled={operational}
                          className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 transition-colors cursor-pointer"
                        >
                          <RefreshCw className="w-3.5 h-3.5 text-neutral-500" />
                          Refresh Token
                        </button>
                      </div>

                      <button
                        type="button"
                        onClick={() => handleOpenDisconnect(conn)}
                        className="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30 rounded-xl transition-colors cursor-pointer"
                        title="Disconnect Store"
                      >
                        <PowerOff className="w-3.5 h-3.5" />
                        Disconnect
                      </button>
                    </>
                  ) : (
                    <>
                      <button
                        type="button"
                        onClick={() => handleOpenReauthorize(conn)}
                        className="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-xs transition-colors cursor-pointer"
                      >
                        <RotateCcw className="w-3.5 h-3.5" />
                        {isOfficialTest ? 'Renew Test Access' : 'Re-authorize & Resume Sync'}
                      </button>

                      <button
                        type="button"
                        onClick={() => deleteConnection(conn.id)}
                        className="p-2 text-neutral-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30 rounded-xl transition-colors cursor-pointer"
                        title="Remove Store Permanently"
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </>
                  )}
                </div>
              </div>
            );
          })}
        </div>
      </div>

      {/* Developer Webhook & Backend Reference Card */}
      {!operational && (
      <div className="p-6 rounded-3xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs space-y-4">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2.5">
            <div className="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400">
              <ShieldCheck className="w-5 h-5" />
            </div>
            <div>
              <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                Laravel Backend & ISV Gateway Configuration
              </h3>
              <p className="text-xs text-neutral-500">
                Official Open Platform ISV endpoints and server-to-server webhook ingestion contract.
              </p>
            </div>
          </div>
          <span className="text-[11px] font-mono text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950 px-2.5 py-1 rounded-full border border-emerald-200 dark:border-emerald-800">
            TLS 1.3 Strict Verification
          </span>
        </div>

        <div className="p-4 rounded-2xl bg-indigo-50/50 dark:bg-indigo-950/30 border border-indigo-200/60 dark:border-indigo-900/50 text-xs text-neutral-600 dark:text-neutral-400 space-y-1.5">
          <p className="font-bold text-neutral-900 dark:text-white">
            Architecture Separation of Concerns:
          </p>
          <p className="leading-relaxed">
            Marketplace master App Keys, Partner IDs, and App Secrets are stored strictly on the Laravel backend server inside <code>.env</code> (e.g. <code>SHOPEE_PARTNER_KEY</code>, <code>LAZADA_APP_SECRET</code>). Merchants authorize store connections purely via the OAuth 2.0 interface above without ever handling developer keys.
          </p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs font-mono">
          <div className="p-3.5 rounded-2xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 space-y-1.5">
            <span className="text-neutral-500 block font-sans font-bold text-[11px]">
              OAuth Redirect URI (Registered with ISV Portals)
            </span>
            <div className="flex items-center justify-between text-indigo-600 dark:text-indigo-400">
              <span className="truncate">https://api.yourdomain.com/api/v1/marketplaces/oauth/callback</span>
              <button
                type="button"
                onClick={() => copyToClipboard('https://api.yourdomain.com/api/v1/marketplaces/oauth/callback', 'OAuth Redirect URI')}
                className="p-1 hover:text-indigo-800 cursor-pointer"
              >
                {copiedKey === 'OAuth Redirect URI' ? <Check className="w-3.5 h-3.5 text-emerald-500" /> : <Copy className="w-3.5 h-3.5" />}
              </button>
            </div>
          </div>

          <div className="p-3.5 rounded-2xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 space-y-1.5">
            <span className="text-neutral-500 block font-sans font-bold text-[11px]">
              Webhook Ingestion Endpoint (Orders & Inventory)
            </span>
            <div className="flex items-center justify-between text-indigo-600 dark:text-indigo-400">
              <span className="truncate">https://api.yourdomain.com/api/v1/marketplaces/webhook/&#123;platform&#125;</span>
              <button
                type="button"
                onClick={() => copyToClipboard('https://api.yourdomain.com/api/v1/marketplaces/webhook/{platform}', 'Webhook URL')}
                className="p-1 hover:text-indigo-800 cursor-pointer"
              >
                {copiedKey === 'Webhook URL' ? <Check className="w-3.5 h-3.5 text-emerald-500" /> : <Copy className="w-3.5 h-3.5" />}
              </button>
            </div>
          </div>
        </div>
      </div>

      )}
      {/* Connect & Re-authorize Store Modal */}
      <ConnectStoreModal
        authorizationResult={visibleResult}
        retryBatch={onRetryOAuthBatch}
        requestAuthorization={operational ? requestMarketplaceAuthorization : undefined}
        oauthAvailability={operational ? oauthAvailability : undefined}
        isOpen={isConnectModalOpen}
        onClose={() => setIsConnectModalOpen(false)}
        mode={connectModalMode}
        existingConnection={selectedConnection}
      />

      {publishingTarget&&(
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-neutral-950/70 backdrop-blur-sm">
          <form onSubmit={submitPublishingCapability} className="w-full max-w-lg rounded-3xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-2xl overflow-hidden">
            <div className="flex items-start justify-between gap-4 p-6 border-b border-neutral-200 dark:border-neutral-800">
              <div><p className="text-xs font-bold text-indigo-600 dark:text-indigo-300">SUPER ADMIN STORE CONTROL</p><h2 className="mt-2 text-lg font-black text-neutral-900 dark:text-white">{publishingTarget.capabilities?.productPublish?'Disable':'Enable'} Product Publishing</h2><p className="mt-1 text-xs text-neutral-500">{publishingTarget.shopName} · Shop ID {publishingTarget.shopId}</p></div>
              <button type="button" onClick={()=>setPublishingTarget(null)} className="p-2 rounded-xl text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800" aria-label="Close">×</button>
            </div>
            <div className="p-6 space-y-4">
              <p className="text-sm leading-relaxed text-neutral-700 dark:text-neutral-300">{publishingTarget.capabilities?.productPublish?'New product publishing will stop immediately. Existing marketplace listings are not deleted.':'This store will be allowed to create real marketplace listings after the existing review and provider checks pass.'}</p>
              <label className="block space-y-2"><span className="text-xs font-bold text-neutral-700 dark:text-neutral-200">Reason for this change</span><textarea name="reason" required maxLength={255} value={publishingReason} onInput={event=>setPublishingReason(event.currentTarget.value)} placeholder="e.g. Merchant approved production publishing" className="w-full min-h-24 px-3.5 py-2.5 rounded-xl bg-white dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 text-sm text-neutral-900 dark:text-white outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20" /></label>
              {publishingError&&<p role="alert" className="text-xs font-semibold text-rose-600 dark:text-rose-300">{publishingError}</p>}
            </div>
            <div className="flex justify-end gap-3 p-5 border-t border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950/40"><button type="button" onClick={()=>setPublishingTarget(null)} className="px-4 py-2 rounded-xl text-xs font-bold text-neutral-700 dark:text-neutral-200 hover:bg-neutral-200 dark:hover:bg-neutral-800">Cancel</button><button type="submit" disabled={publishingBusy||!publishingReason.trim()} className="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-xs font-bold">{publishingBusy?'Saving…':`Confirm ${publishingTarget.capabilities?.productPublish?'Disable':'Enable'} Publishing`}</button></div>
          </form>
        </div>
      )}

      {testAccessTarget && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-neutral-950/70 backdrop-blur-sm">
          <form onSubmit={renewOfficialTestAccess} className="w-full max-w-lg rounded-3xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-2xl overflow-hidden">
            <div className="flex items-start justify-between gap-4 p-6 border-b border-neutral-200 dark:border-neutral-800">
              <div>
                <div className="inline-flex items-center gap-2 text-xs font-bold text-indigo-600 dark:text-indigo-300"><KeyRound className="w-4 h-4" /> Official Lazada Test Store</div>
                <h2 className="mt-2 text-lg font-black text-neutral-900 dark:text-white">Renew Test Access</h2>
                <p className="mt-1 text-xs text-neutral-500">{testAccessTarget.shopName} uses a Lazada API Explorer token, not Seller OAuth.</p>
              </div>
              <button type="button" onClick={() => setTestAccessTarget(null)} className="p-2 rounded-xl text-neutral-400 hover:text-neutral-700 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800" aria-label="Close">×</button>
            </div>
            <div className="p-6 space-y-4">
              {testAccessRenewed ? (
                <div className="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-sm text-emerald-800 dark:text-emerald-200"><div className="flex gap-2 font-bold"><CheckCircle2 className="w-5 h-5" /> Test access renewed</div><p className="mt-1 text-xs">The token was verified server-side and stored encrypted. Reload to see the active connection.</p></div>
              ) : (
                <>
                  <div className="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 text-xs text-amber-900 dark:text-amber-100 leading-relaxed"><strong>Use a fresh official test token.</strong> Generate it in Lazada API Explorer for App Key <code className="font-bold">139041</code>. JW ECOM validates the seller identity before restoring sync.</div>
                  <a href="https://isvconsole.lazada.com/apps/console/test_api" target="_blank" rel="noreferrer" className="inline-flex items-center gap-1.5 text-xs font-bold text-indigo-600 dark:text-indigo-300 hover:underline">Open Lazada API Explorer <span aria-hidden="true">↗</span></a>
                  <label className="block space-y-2"><span className="text-xs font-bold text-neutral-700 dark:text-neutral-200">Access token</span><input autoFocus type="password" autoComplete="off" value={testAccessToken} onChange={(event) => setTestAccessToken(event.target.value)} required minLength={16} placeholder="Paste the current API Explorer token" className="w-full px-3.5 py-2.5 rounded-xl bg-white dark:bg-neutral-950 border border-neutral-300 dark:border-neutral-700 text-sm text-neutral-900 dark:text-white outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20" /></label>
                  {testAccessError && <p role="alert" className="text-xs font-semibold text-rose-600 dark:text-rose-300">{testAccessError}</p>}
                </>
              )}
            </div>
            <div className="flex justify-end gap-3 p-5 border-t border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950/40">
              <button type="button" onClick={() => { setTestAccessTarget(null); if (testAccessRenewed) window.location.reload(); }} className="px-4 py-2 rounded-xl text-xs font-bold text-neutral-700 dark:text-neutral-200 hover:bg-neutral-200 dark:hover:bg-neutral-800">{testAccessRenewed ? 'Close & Reload' : 'Cancel'}</button>
              {!testAccessRenewed && <button type="submit" disabled={isRenewingTestAccess || !testAccessToken.trim()} className="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-xs font-bold shadow-lg shadow-indigo-600/20"><RefreshCw className={`w-3.5 h-3.5 ${isRenewingTestAccess ? 'animate-spin' : ''}`} />{isRenewingTestAccess ? 'Verifying…' : 'Verify & Resume Sync'}</button>}
            </div>
          </form>
        </div>
      )}

      {/* Disconnect Store Confirmation Modal */}
      <DisconnectStoreModal
        isOpen={isDisconnectModalOpen}
        onClose={() => setIsDisconnectModalOpen(false)}
        connection={disconnectTarget}
      />
    </div>
  );
};
