import React, { useState, useEffect } from 'react';
import type {OAuthResult} from '../../../../integration/marketplaceOperations';
import { useOptionalAdmin } from '../../context/ContextBridge';
import { MarketplaceConnection, MarketplacePlatform, MarketplaceRegion } from '../../types';
import { MarketplaceLogo, getPlatformMeta } from './MarketplaceLogo';
import { X, ShieldCheck, CheckCircle2, AlertCircle, RotateCcw, Store, ArrowRight, Lock, Building2, AlertTriangle } from 'lucide-react';

interface ConnectStoreModalProps {
  isOpen: boolean;
  onClose: () => void;
  mode?: 'connect' | 'reauthorize';
  existingConnection?: MarketplaceConnection | null;
  requestAuthorization?: (platform: MarketplacePlatform, reconnectId?: string) => Promise<void>;
  oauthAvailability?: Partial<Record<MarketplacePlatform, boolean>>;
  authorizationResult?: OAuthResult | null;
}

type AuthStep = 'select' | 'authorizing' | 'success' | 'cancelled' | 'failed';

export const ConnectStoreModal: React.FC<ConnectStoreModalProps> = ({
  isOpen,
  onClose,
  mode = 'connect',
  existingConnection = null,
  requestAuthorization,
  oauthAvailability,
  authorizationResult
}) => {
  const previewAdmin = useOptionalAdmin();

  // Selected parameters
  const [platform, setPlatform] = useState<MarketplacePlatform>(
    existingConnection?.platform || 'shopee'
  );
  const [region, setRegion] = useState<MarketplaceRegion>(
    existingConnection?.region || 'MY'
  );
  const [shopName, setShopName] = useState<string>(
    existingConnection?.shopName || (requestAuthorization ? '' : 'Aurora Official Shopee Mall')
  );
  const [sellerHandle, setSellerHandle] = useState<string>(
    existingConnection?.shopId || (requestAuthorization ? '' : 'aurora_official_my')
  );

  // Workflow state
  const [step, setStep] = useState<AuthStep>('select');
  const [isHandshakeLoading, setIsHandshakeLoading] = useState(false);
  const [errorDetails, setErrorDetails] = useState<string>('');

  // Auto-fill sensible defaults when platform changes in 'connect' mode
  useEffect(() => {
    if (mode === 'connect') {
      if (requestAuthorization) {
        setShopName('');
        setSellerHandle('');
        setRegion('MY');
        return;
      }
      if (platform === 'shopee') {
        setShopName('Aurora Official Shopee Mall');
        setSellerHandle('aurora_official_my');
        setRegion('MY');
      } else if (platform === 'lazada') {
        setShopName('Aurora LazMall Malaysia');
        setSellerHandle('aurora_lazmall_my');
        setRegion('MY');
      } else if (platform === 'tiktok') {
        setShopName('Aurora TikTok Shop Flagship');
        setSellerHandle('aurora_tiktok_my');
        setRegion('MY');
      } else if (platform === 'shopify') {
        setShopName('Aurora Global Flagship');
        setSellerHandle('aurora-global.myshopify.com');
        setRegion('GLOBAL');
      }
    } else if (existingConnection) {
      setPlatform(existingConnection.platform);
      setRegion(existingConnection.region);
      setShopName(existingConnection.shopName);
      setSellerHandle(existingConnection.shopId);
    }
  }, [platform, mode, existingConnection, requestAuthorization]);

  // Reset or initialize on open
  useEffect(() => {
    if (isOpen) {
      if (mode === 'reauthorize' && existingConnection) {
        setPlatform(existingConnection.platform);
        setRegion(existingConnection.region);
        setShopName(existingConnection.shopName);
        setSellerHandle(existingConnection.shopId);
        setStep('select');
      } else {
        setStep('select');
      }
      setIsHandshakeLoading(false);
      setErrorDetails('');
    }
  }, [isOpen, mode, existingConnection]);

  useEffect(() => {
    if (!isOpen || !requestAuthorization || !authorizationResult) return;
    const verified = authorizationResult.status === 'success' && authorizationResult.store;
    setStep(authorizationResult.status === 'success' && !verified ? 'failed' : authorizationResult.status);
    if (verified) {
      setPlatform(verified.platform);
      setShopName(verified.shopName);
      setSellerHandle(verified.shopId);
    }
  }, [isOpen, requestAuthorization, authorizationResult]);

  if (!isOpen) return null;

  const currentPlatformMeta = getPlatformMeta(platform);

  // Trigger simulated redirect to official OAuth
  const handleProceedToOAuth = async () => {
    if (isHandshakeLoading || (requestAuthorization && oauthAvailability?.[platform] !== true)) return;
    setIsHandshakeLoading(true);
    if (requestAuthorization) {
      try {
        await requestAuthorization(platform, mode === 'reauthorize' ? existingConnection?.id : undefined);
      } catch (error) {
        setErrorDetails(error instanceof Error ? error.message : 'Authorization could not be started.');
        setStep('failed');
      } finally {
        setIsHandshakeLoading(false);
      }
      return;
    }
    setTimeout(() => {
      setIsHandshakeLoading(false);
      setStep('authorizing');
    }, 600);
  };

  // Merchant grants authorization
  const handleGrantAccess = () => {
    if (requestAuthorization || !previewAdmin) return;
    setIsHandshakeLoading(true);
    setTimeout(() => {
      setIsHandshakeLoading(false);

      if (mode === 'reauthorize' && existingConnection) {
        previewAdmin.reconnectConnection(existingConnection.id);
      } else {
        const newConnId = `mp-${platform}-${Date.now().toString(36)}`;
        const newConnection: MarketplaceConnection = {
          id: newConnId,
          platform,
          shopName: shopName.trim() || `${currentPlatformMeta.name} Store`,
          shopId: sellerHandle.trim() || `SHOP_${Date.now().toString(36).toUpperCase()}`,
          region,
          status: 'connected',
          appKey: `isv_${platform}_gateway_prod`,
          appSecret: `enc_sec_${Date.now()}`,
          accessToken: `oauth_tok_${platform}_${Date.now().toString(36)}_${Math.random().toString(36).slice(-6)}`,
          refreshToken: `oauth_rfr_${platform}_${Date.now().toString(36)}_${Math.random().toString(36).slice(-6)}`,
          tokenExpiresAt: new Date(Date.now() + 365 * 24 * 60 * 60 * 1000).toISOString(),
          webhookSecret: `whsec_${Math.random().toString(36).slice(-10)}`,
          scopes: [
            'orders.read_write',
            'inventory.sync',
            'catalog.read_write',
            'logistics.awb'
          ],
          authorizedScopesSummary: [
            'Catalog & Listing Synchronization',
            'Live Inventory Stock Broadcast (ATP)',
            'Inbound Order Pull & Status Update',
            'Logistics Airway Bill (AWB) Generation'
          ],
          autoSyncStock: true,
          autoPullOrders: true,
          syncIntervalMinutes: 5,
          lastSyncAt: new Date().toISOString(),
          apiRateLimit: {
            limit: platform === 'shopify' ? 40 : 50000,
            remaining: platform === 'shopify' ? 39 : 48920,
            resetTime: new Date(Date.now() + 3600000).toISOString()
          }
        };

        previewAdmin.saveConnection(newConnection);
      }

      setStep('success');
    }, 850);
  };

  // Merchant denies authorization
  const handleDenyAccess = () => {
    setErrorDetails('Merchant explicitly declined OAuth access permissions during seller portal confirmation.');
    setStep('cancelled');
  };

  // Simulate an API Handshake error (e.g. invalid signature, timeout)
  const handleSimulateFailure = () => {
    setErrorDetails('The platform authorization server returned error code [OAUTH_HANDSHAKE_TIMEOUT]: Token exchange signature could not be verified by the marketplace gateway.');
    setStep('failed');
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-xs overflow-y-auto">
      <div className="relative w-full max-w-2xl max-h-[calc(100dvh-5rem)] bg-white dark:bg-neutral-900 rounded-3xl shadow-2xl border border-neutral-200 dark:border-neutral-800 overflow-y-auto my-6 transition-all">
        
        {/* Top Header Bar */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50/80 dark:bg-neutral-950/80 backdrop-blur-xs">
          <div className="flex items-center gap-3">
            <div className="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">
              <Store className="w-5 h-5" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                  {mode === 'reauthorize' ? 'Re-authorize Marketplace Store' : 'Connect Marketplace Store'}
                </h3>
                <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                  OAuth 2.0 ISV
                </span>
              </div>
              <p className="text-xs text-neutral-500">
                Official Marketplace Merchant Authorization Gateway
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

        {/* STEP 1: Platform & Store Selection */}
        {step === 'select' && (
          <div className="p-6 space-y-6">
            {/* Merchant OAuth Explainer Banner */}
            <div className="p-4 rounded-2xl bg-indigo-50/60 dark:bg-indigo-950/30 border border-indigo-200/80 dark:border-indigo-900/60 flex items-start gap-3.5">
              <ShieldCheck className="w-5 h-5 text-indigo-600 dark:text-indigo-400 flex-shrink-0 mt-0.5" />
              <div className="text-xs space-y-1">
                <p className="font-bold text-indigo-900 dark:text-indigo-200">
                  Merchant-Grade OAuth 2.0 Security
                </p>
                <p className="text-indigo-700/80 dark:text-indigo-300/80 leading-relaxed">
                  No developer App Key or App Secret is required. Authorization is conducted securely via the official marketplace seller portal. Your credentials and store tokens are safely managed by the JW Laravel API gateway.
                </p>
              </div>
            </div>

            {/* Platform Selection */}
            <div>
              <label className="block text-xs font-bold text-neutral-700 dark:text-neutral-300 mb-2.5">
                Select Target Marketplace Platform
              </label>
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                {(['shopee', 'lazada', 'tiktok', 'shopify'] as MarketplacePlatform[]).map(p => {
                  const meta = getPlatformMeta(p);
                  const isSelected = platform === p;
                  const isRelease1Active = p === 'shopee' || p === 'lazada';

                  return (
                    <button
                      type="button"
                      key={p}
                      disabled={mode === 'reauthorize' || (!!requestAuthorization && oauthAvailability?.[p] !== true)}
                      onClick={() => setPlatform(p)}
                      className={`relative p-3.5 rounded-2xl border text-center flex flex-col items-center justify-center gap-2 transition-all cursor-pointer ${
                        isSelected
                          ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/40 text-indigo-900 dark:text-white shadow-xs ring-2 ring-indigo-500/20'
                          : 'border-neutral-200 dark:border-neutral-800 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-800/60'
                      } ${mode === 'reauthorize' && !isSelected ? 'opacity-40 cursor-not-allowed' : ''}`}
                    >
                      {(requestAuthorization || isRelease1Active) && (
                        <span className="absolute top-2 right-2 text-[9px] font-bold px-1.5 py-0.5 rounded-md bg-emerald-100 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300">
                          {requestAuthorization ? (oauthAvailability?.[p] === true ? 'Available' : 'Unavailable') : 'Live R1'}
                        </span>
                      )}
                      <MarketplaceLogo platform={p} size={32} />
                      <div>
                        <span className="block font-bold text-xs">{meta.name}</span>
                        <span className="text-[10px] text-neutral-400 font-normal">
                          {p === 'shopee' ? 'Open API v2' : p === 'lazada' ? 'Open OAuth' : 'Open API'}
                        </span>
                      </div>
                    </button>
                  );
                })}
              </div>
            </div>

            {/* Store Information Form */}
            <div className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-neutral-700 dark:text-neutral-300 mb-1.5">
                    Store Display Name
                  </label>
                  <input
                    type="text"
                    value={shopName}
                    readOnly={!!requestAuthorization}
                    onChange={e => setShopName(e.target.value)}
                    placeholder={requestAuthorization ? 'Retrieved after official authorization' : 'e.g. Aurora Shopee Mall'}
                    className="w-full px-3.5 py-2.5 rounded-xl bg-white dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 text-neutral-900 dark:text-white font-medium text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-hidden"
                  />
                  <span className="text-[10px] text-neutral-400 mt-1 block">
                    {requestAuthorization ? 'Shop name is retrieved from the authorized marketplace account.' : 'Friendly name shown across ERP orders and inventory.'}
                  </span>
                </div>

                <div>
                  <label className="block text-xs font-bold text-neutral-700 dark:text-neutral-300 mb-1.5">
                    Platform Region / Country
                  </label>
                  <select
                    value={region}
                    disabled={!!requestAuthorization}
                    onChange={e => setRegion(e.target.value as MarketplaceRegion)}
                    className="w-full px-3.5 py-2.5 rounded-xl bg-white dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 text-neutral-900 dark:text-white font-semibold text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-hidden cursor-pointer"
                  >
                    <option value="MY">Malaysia (MYR) - Southeast Asia</option>
                    <option value="SG">Singapore (SGD)</option>
                    <option value="ID">Indonesia (IDR)</option>
                    <option value="TH">Thailand (THB)</option>
                    <option value="PH">Philippines (PHP)</option>
                    <option value="VN">Vietnam (VND)</option>
                    <option value="GLOBAL">Global / North America (USD)</option>
                  </select>
                  <span className="text-[10px] text-neutral-400 mt-1 block">
                    Determines currency, tax, and regional Open Platform endpoints.
                  </span>
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-neutral-700 dark:text-neutral-300 mb-1.5">
                  {requestAuthorization ? 'Verified Marketplace Shop ID' : 'Platform Seller Account / Store Handle'}
                </label>
                <div className="relative">
                  <Building2 className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400" />
                  <input
                    type="text"
                    value={sellerHandle}
                    readOnly={!!requestAuthorization}
                    onChange={e => setSellerHandle(e.target.value)}
                    placeholder={requestAuthorization ? 'Retrieved after official authorization' : 'e.g. aurora_official_my'}
                    className="w-full pl-10 pr-3.5 py-2.5 rounded-xl bg-white dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 text-neutral-900 dark:text-white font-mono text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-hidden"
                  />
                </div>
                <span className="text-[10px] text-neutral-400 mt-1 block">
                  {requestAuthorization ? 'The platform confirms your shop identity. No manual Shop ID is required.' : 'The primary seller handle or store username registered with the marketplace.'}
                </span>
              </div>
            </div>

            {/* Bottom Actions */}
            <div className="flex items-center justify-between pt-4 border-t border-neutral-100 dark:border-neutral-800">
              <button
                type="button"
                onClick={onClose}
                className="px-4 py-2 text-xs font-semibold text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-xl transition-colors cursor-pointer"
              >
                Cancel
              </button>
              <button
                type="button"
                disabled={isHandshakeLoading || (requestAuthorization ? oauthAvailability?.[platform] !== true : !shopName.trim())}
                onClick={handleProceedToOAuth}
                className="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-xs transition-colors cursor-pointer disabled:opacity-50"
              >
                {isHandshakeLoading ? (
                  <>
                    <RotateCcw className="w-4 h-4 animate-spin" />
                    Preparing OAuth Handshake...
                  </>
                ) : (
                  <>
                    Proceed to Official Authorization
                    <ArrowRight className="w-4 h-4" />
                  </>
                )}
              </button>
            </div>
          </div>
        )}

        {/* STEP 2: Simulated Official Platform OAuth Handshake / Consent Screen */}
        {!requestAuthorization && step === 'authorizing' && (
          <div className="p-6 space-y-6">
            {/* Browser / SSL Mock Header */}
            <div className="p-3 rounded-2xl bg-neutral-100 dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 flex items-center justify-between text-xs">
              <div className="flex items-center gap-2 text-neutral-600 dark:text-neutral-400 truncate">
                <Lock className="w-3.5 h-3.5 text-emerald-500 flex-shrink-0" />
                <span className="font-mono text-[11px] truncate">
                  {platform === 'shopee'
                    ? 'https://partner.shopeemobile.com/api/v2/shop/auth_partner?partner_id=88390'
                    : platform === 'lazada'
                    ? 'https://auth.lazada.com/oauth/authorize?response_type=code&client_id=102948'
                    : platform === 'tiktok'
                    ? 'https://services.tiktokshop.com/open/authorize?app_key=tts_isv_jw'
                    : 'https://admin.shopify.com/oauth/authorize'}
                </span>
              </div>
              <span className="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950 px-2 py-0.5 rounded-full border border-emerald-200 dark:border-emerald-800">
                SSL 256-Bit
              </span>
            </div>

            {/* Official Consent Card */}
            <div className="p-6 rounded-2xl bg-neutral-50 dark:bg-neutral-950/60 border border-neutral-200 dark:border-neutral-800 space-y-5">
              <div className="flex items-center justify-between pb-4 border-b border-neutral-200 dark:border-neutral-800">
                <div className="flex items-center gap-3">
                  <MarketplaceLogo platform={platform} size={36} />
                  <div>
                    <h4 className="text-sm font-bold text-neutral-900 dark:text-white">
                      {currentPlatformMeta.name} Open Platform Authorization
                    </h4>
                    <p className="text-[11px] text-neutral-500">
                      Seller Account: <strong className="text-neutral-800 dark:text-neutral-200">{sellerHandle}</strong> ({region})
                    </p>
                  </div>
                </div>
                <span className="text-xs font-bold text-neutral-500 uppercase">
                  Step 2 of 3
                </span>
              </div>

              {/* ISV App Profile */}
              <div className="p-3.5 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
                <div>
                  <div className="flex items-center gap-1.5">
                    <span className="font-bold text-xs text-neutral-900 dark:text-white">
                      JW Omnichannel Commerce ERP
                    </span>
                    <span className="text-[10px] font-bold px-1.5 py-0.2 rounded bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-400">
                      Verified ISV Partner
                    </span>
                  </div>
                  <p className="text-[11px] text-neutral-500 mt-0.5">
                    Enterprise ERP & Warehouse Management System for Southeast Asia
                  </p>
                </div>
              </div>

              {/* Scopes Requested */}
              <div className="space-y-2">
                <label className="block text-xs font-bold text-neutral-700 dark:text-neutral-300">
                  Permissions Requested by JW ERP:
                </label>
                <div className="space-y-2 text-xs">
                  <div className="flex items-start gap-2.5 text-neutral-700 dark:text-neutral-300">
                    <CheckCircle2 className="w-4 h-4 text-emerald-500 flex-shrink-0 mt-0.5" />
                    <div>
                      <span className="font-bold">Catalog & Product Listings (Read / Write):</span>
                      <p className="text-neutral-500 text-[11px]">Sync master products, variants, channel prices, and multi-tier SKU specifications.</p>
                    </div>
                  </div>
                  <div className="flex items-start gap-2.5 text-neutral-700 dark:text-neutral-300">
                    <CheckCircle2 className="w-4 h-4 text-emerald-500 flex-shrink-0 mt-0.5" />
                    <div>
                      <span className="font-bold">Real-time Inventory ATP (Read / Write):</span>
                      <p className="text-neutral-500 text-[11px]">Push warehouse available-to-promise stocks and maintain safe reserves.</p>
                    </div>
                  </div>
                  <div className="flex items-start gap-2.5 text-neutral-700 dark:text-neutral-300">
                    <CheckCircle2 className="w-4 h-4 text-emerald-500 flex-shrink-0 mt-0.5" />
                    <div>
                      <span className="font-bold">Orders & Fulfillment Pipeline (Read / Write):</span>
                      <p className="text-neutral-500 text-[11px]">Ingest paid orders, update shipping tracking numbers, and print official AWBs.</p>
                    </div>
                  </div>
                </div>
              </div>

              <div className="text-[11px] text-neutral-500 border-t border-neutral-200 dark:border-neutral-800 pt-3">
                By clicking <strong>Authorize & Connect</strong>, you agree to grant JW ERP access to manage the resources above for a duration of 365 days. You can revoke access at any time from your platform seller console or this admin panel.
              </div>
            </div>

            {/* Test Helper Trigger */}
            <div className="flex items-center justify-between text-[11px] text-neutral-500 px-1">
              <span>Developer Handshake Simulation:</span>
              <button
                type="button"
                onClick={handleSimulateFailure}
                className="text-neutral-400 hover:text-rose-600 underline cursor-pointer"
              >
                Simulate Handshake Timeout / Error
              </button>
            </div>

            {/* Decision Actions */}
            <div className="flex items-center justify-between pt-2">
              <button
                type="button"
                onClick={handleDenyAccess}
                className="px-4 py-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 text-xs font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors cursor-pointer"
              >
                Deny & Cancel
              </button>

              <button
                type="button"
                disabled={isHandshakeLoading}
                onClick={handleGrantAccess}
                className="inline-flex items-center gap-2 px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-xs transition-colors cursor-pointer"
              >
                {isHandshakeLoading ? (
                  <>
                    <RotateCcw className="w-4 h-4 animate-spin" />
                    Authorizing with Platform...
                  </>
                ) : (
                  <>
                    <ShieldCheck className="w-4 h-4" />
                    Authorize & Connect Store
                  </>
                )}
              </button>
            </div>
          </div>
        )}

        {/* STEP 3A: Authorization Success */}
        {step === 'success' && (!requestAuthorization || authorizationResult?.store) && (
          <div className="p-8 text-center space-y-6">
            <div className="w-16 h-16 rounded-3xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-emerald-600 dark:text-emerald-400 mx-auto flex items-center justify-center shadow-xs">
              <CheckCircle2 className="w-8 h-8" />
            </div>

            <div className="space-y-1.5">
              <h4 className="text-lg font-bold text-neutral-900 dark:text-white">
                Store Connected Successfully!
              </h4>
              <p className="text-xs text-neutral-500 max-w-md mx-auto">
                {requestAuthorization ? 'Authorization confirmed. Automatic synchronization remains off for ' : 'OAuth access token granted. Real-time omnichannel synchronization is now active for '}<strong className="text-neutral-800 dark:text-neutral-200">{authorizationResult?.store?.shopName ?? shopName}</strong>.
              </p>
            </div>

            {/* Connection Details Summary */}
            <div className="p-4 rounded-2xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 max-w-md mx-auto text-left space-y-2.5 text-xs">
              <div className="flex items-center justify-between">
                <span className="text-neutral-500">Marketplace Channel:</span>
                <div className="flex items-center gap-1.5 font-bold text-neutral-900 dark:text-white">
                  <MarketplaceLogo platform={platform} size={16} />
                  <span>{currentPlatformMeta.name} ({region})</span>
                </div>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-neutral-500">Shop ID:</span>
                <span className="font-mono text-neutral-800 dark:text-neutral-200">{authorizationResult?.store?.shopId ?? sellerHandle}</span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-neutral-500">{requestAuthorization ? 'Credentials:' : 'Token Validity:'}</span>
                <span className="text-emerald-600 dark:text-emerald-400 font-semibold">
                  {requestAuthorization ? 'Stored securely on the server' : '365 Days (Auto-renew enabled)'}
                </span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-neutral-500">Initial Tasks Queued:</span>
                <span className="text-neutral-800 dark:text-neutral-200 font-medium">
                  {requestAuthorization ? 'None — authorization only' : 'Catalog pull & webhook listeners'}
                </span>
              </div>
            </div>

            <div>
              <button
                type="button"
                onClick={onClose}
                className="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-xs transition-colors cursor-pointer"
              >
                Complete & Return to Stores
              </button>
            </div>
          </div>
        )}

        {/* STEP 3B: Authorization Cancelled */}
        {step === 'cancelled' && (
          <div className="p-8 text-center space-y-6">
            <div className="w-16 h-16 rounded-3xl bg-amber-50 dark:bg-amber-950/60 border border-amber-200 dark:border-amber-800 text-amber-600 dark:text-amber-400 mx-auto flex items-center justify-center shadow-xs">
              <AlertCircle className="w-8 h-8" />
            </div>

            <div className="space-y-1.5">
              <h4 className="text-lg font-bold text-neutral-900 dark:text-white">
                Authorization Cancelled
              </h4>
              <p className="text-xs text-neutral-500 max-w-md mx-auto">
                {errorDetails || 'The OAuth request was cancelled before permissions could be granted.'}
              </p>
            </div>

            <div className="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 max-w-md mx-auto text-xs text-neutral-600 dark:text-neutral-400">
              🛡️ <strong>Safe Assurance:</strong> No data was modified, and your store was not linked. You can restart the authorization anytime.
            </div>

            <div className="flex items-center justify-center gap-3">
              <button
                type="button"
                onClick={onClose}
                className="px-4 py-2 text-xs font-semibold text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-xl transition-colors cursor-pointer"
              >
                Close
              </button>
              <button
                type="button"
                onClick={requestAuthorization ? handleProceedToOAuth : () => setStep('authorizing')}
                disabled={isHandshakeLoading}
                className="inline-flex items-center gap-1.5 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-xs transition-colors cursor-pointer"
              >
                <RotateCcw className="w-3.5 h-3.5" />
                Retry Authorization
              </button>
            </div>
          </div>
        )}

        {/* STEP 3C: Authorization Failed */}
        {step === 'failed' && (
          <div className="p-8 text-center space-y-6">
            <div className="w-16 h-16 rounded-3xl bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-800 text-rose-600 dark:text-rose-400 mx-auto flex items-center justify-center shadow-xs">
              <AlertTriangle className="w-8 h-8" />
            </div>

            <div className="space-y-1.5">
              <h4 className="text-lg font-bold text-neutral-900 dark:text-white">
                OAuth Handshake Failed
              </h4>
              <p className="text-xs text-rose-600 dark:text-rose-400 font-mono max-w-md mx-auto bg-rose-50 dark:bg-rose-950/40 p-3 rounded-xl border border-rose-200 dark:border-rose-900/60 text-left">
                {errorDetails || 'Failed to exchange authorization code for access token.'}
              </p>
            </div>

            <div className="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 max-w-md mx-auto text-xs text-neutral-600 dark:text-neutral-400 text-left space-y-1">
              <p className="font-bold text-neutral-800 dark:text-neutral-200">Recommended Resolution:</p>
              <ul className="list-disc list-inside space-y-0.5 text-neutral-500">
                <li>Verify your seller account is active and in good standing on {currentPlatformMeta.name}.</li>
                <li>Check that cross-border / regional permissions match the selected region ({region}).</li>
                <li>{requestAuthorization ? 'Contact your system administrator if authorization remains unavailable.' : <>Verify the Laravel backend has loaded valid ISV client credentials in <code>.env</code>.</>}</li>
              </ul>
            </div>

            <div className="flex items-center justify-center gap-3">
              <button
                type="button"
                onClick={() => setStep('select')}
                className="px-4 py-2 text-xs font-semibold text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-xl transition-colors cursor-pointer"
              >
                Change Platform / Region
              </button>
              <button
                type="button"
                onClick={requestAuthorization ? handleProceedToOAuth : () => setStep('authorizing')}
                disabled={isHandshakeLoading}
                className="inline-flex items-center gap-1.5 px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl shadow-xs transition-colors cursor-pointer"
              >
                <RotateCcw className="w-3.5 h-3.5" />
                Retry Handshake
              </button>
            </div>
          </div>
        )}

      </div>
    </div>
  );
};
