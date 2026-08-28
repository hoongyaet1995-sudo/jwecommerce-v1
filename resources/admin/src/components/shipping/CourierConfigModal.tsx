import React, { useState } from 'react';
import { 
  X, 
  Key, 
  Lock, 
  Globe, 
  Server, 
  Copy, 
  Check, 
  Zap, 
  ShieldCheck, 
  AlertCircle, 
  MapPin, 
  Building,
  DollarSign,
  Plus
} from 'lucide-react';
import { CourierConfig } from '../../types/shipping';

interface CourierConfigModalProps {
  isOpen: boolean;
  config: CourierConfig | null;
  onClose: () => void;
  onSave: (config: CourierConfig) => void;
  onPing: (id: string) => Promise<number>;
  onTopUpWallet?: (amountRM: number) => void;
}

export const CourierConfigModal: React.FC<CourierConfigModalProps> = ({
  isOpen,
  config,
  onClose,
  onSave,
  onPing,
  onTopUpWallet
}) => {
  if (!isOpen || !config) return null;

  const walletAvailable = typeof config.walletBalance === 'number';
  const [environment, setEnvironment] = useState<'sandbox' | 'production'>(config.environment || 'production');
  const [apiKey, setApiKey] = useState(config.apiKey || (config as any).credentials?.apiKey || '');
  const [apiSecret, setApiSecret] = useState(config.apiSecret || (config as any).credentials?.secretKey || (config as any).credentials?.apiSecret || '');
  const [accountNo, setAccountNo] = useState(config.accountNo || (config as any).credentials?.accountNumber || '');
  const [merchantCode, setMerchantCode] = useState(config.merchantCode || '');
  const [isEnabled, setIsEnabled] = useState(config.isEnabled ?? true);
  const [supportsCOD, setSupportsCOD] = useState(config.supportsCOD ?? (config as any).features?.supportsCod ?? true);
  const [codFeePercent, setCodFeePercent] = useState<number>(config.codFeePercent ?? 2.0);
  const [supportsEastMalaysia, setSupportsEastMalaysia] = useState(config.supportsEastMalaysia ?? (config as any).features?.supportsEastMalaysia ?? true);
  const [supportsAirFreightHazmat, setSupportsAirFreightHazmat] = useState(config.supportsAirFreightHazmat ?? false);
  const [autoRetryQueue, setAutoRetryQueue] = useState(config.autoRetryQueue ?? true);

  const [topUpAmount, setTopUpAmount] = useState('100');
  const [isCopied, setIsCopied] = useState(false);
  const [isTestingPing, setIsTestingPing] = useState(false);
  const [pingResult, setPingResult] = useState<number | null>(config.lastPingLatency || null);

  const webhookUrl = config.webhookEndpointUrl || (config as any).webhookUrl || `https://api.aurora-store.my/v1/webhooks/courier/${config.courierCode || 'jnt'}`;

  const handleCopyWebhook = () => {
    if (webhookUrl) {
      navigator.clipboard.writeText(webhookUrl);
      setIsCopied(true);
      setTimeout(() => setIsCopied(false), 2000);
    }
  };

  const handleTestPing = async () => {
    setIsTestingPing(true);
    try {
      const lat = await onPing(config.id);
      setPingResult(lat);
    } finally {
      setIsTestingPing(false);
    }
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const updated: CourierConfig = {
      ...config,
      environment,
      isEnabled,
      apiKey,
      apiSecret,
      accountNo,
      merchantCode,
      supportsCOD,
      codFeePercent,
      supportsEastMalaysia,
      supportsAirFreightHazmat,
      autoRetryQueue,
      webhookEndpointUrl: webhookUrl,
      lastPingLatency: pingResult ?? config.lastPingLatency,
      status: apiKey ? 'connected' : 'pending_keys'
    };
    onSave(updated);
    onClose();
  };

  return (
    <div id="courier-config-modal-overlay" className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 overflow-y-auto backdrop-blur-sm">
      <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-2xl max-w-2xl w-full flex flex-col shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-900/80">
          <div className="flex items-center gap-3">
            <div className="w-11 h-11 rounded-xl bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400 font-black text-sm">
              {(config.name || config.displayName || 'COU').substring(0, 3).toUpperCase()}
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h3 className="text-base font-semibold text-neutral-900 dark:text-neutral-100">
                  {config.displayName} API Integration
                </h3>
                <span className={`px-2 py-0.5 text-[10px] uppercase font-bold rounded ${
                  environment === 'production' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'
                }`}>
                  {environment} Mode
                </span>
              </div>
              <p className="text-xs text-neutral-500 dark:text-neutral-400">
                Official Malaysia Courier Gateway v2.4 • Direct API Protocol
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2">
            <button
              onClick={onClose}
              className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
            >
              <X className="w-5 h-5" />
            </button>
          </div>
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit} className="p-6 space-y-4 overflow-y-auto max-h-[70vh]">
          {/* Environment Switch & Status */}
          <div className="flex items-center justify-between p-3.5 bg-neutral-50 dark:bg-neutral-800/50 rounded-xl border border-neutral-200 dark:border-neutral-800">
            <div>
              <div className="text-xs font-semibold text-neutral-900 dark:text-neutral-100">
                Integration Environment
              </div>
              <div className="text-[11px] text-neutral-500 dark:text-neutral-400">
                Switch between Sandbox testing API endpoint and live Production
              </div>
            </div>
            <div className="flex items-center gap-2">
              <div className="inline-flex p-0.5 bg-neutral-200 dark:bg-neutral-700 rounded-lg text-xs font-medium">
                <button
                  type="button"
                  onClick={() => setEnvironment('sandbox')}
                  className={`px-3 py-1 rounded-md transition-all ${
                    environment === 'sandbox'
                      ? 'bg-white dark:bg-neutral-900 text-neutral-900 dark:text-neutral-100 shadow-sm font-semibold'
                      : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900'
                  }`}
                >
                  Sandbox (Test)
                </button>
                <button
                  type="button"
                  onClick={() => setEnvironment('production')}
                  className={`px-3 py-1 rounded-md transition-all ${
                    environment === 'production'
                      ? 'bg-emerald-600 text-white shadow-sm font-semibold'
                      : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900'
                  }`}
                >
                  Production (Live)
                </button>
              </div>
            </div>
          </div>

          {/* EasyParcel Wallet Box if easyparcel */}
          {config.courierCode === 'easyparcel' && (
            <div className="p-4 bg-pink-50 dark:bg-pink-900/20 border border-pink-200 dark:border-pink-800 rounded-xl flex items-center justify-between">
              <div>
                <div className="text-xs font-semibold text-pink-900 dark:text-pink-200">
                  EasyParcel Prepaid Shipping Wallet Balance
                </div>
                <div className="text-xl font-bold font-mono text-pink-700 dark:text-pink-300 mt-0.5">
                  {walletAvailable ? `RM ${config.walletBalance.toFixed(2)}` : 'Unavailable'}
                </div>
              </div>

              {onTopUpWallet && (
                <div className="flex items-center gap-2">
                  <div className="relative">
                    <span className="absolute left-2.5 top-2 text-xs text-neutral-500 font-semibold">RM</span>
                    <input
                      type="number"
                      value={walletAvailable ? topUpAmount : ''}
                      disabled={!walletAvailable}
                      onChange={(e) => setTopUpAmount(e.target.value)}
                      className="w-24 pl-8 pr-2 py-1.5 text-xs font-mono rounded-lg border border-pink-300 dark:border-pink-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100"
                    />
                  </div>
                  <button
                    type="button"
                    onClick={() => {
                      const val = parseFloat(topUpAmount);
                      if (walletAvailable && val > 0) onTopUpWallet(val);
                    }}
                    disabled={!walletAvailable}
                    title={walletAvailable ? undefined : 'Wallet top-up is not available through this integration'}
                    className="flex items-center gap-1 px-3 py-1.5 text-xs font-semibold text-white bg-pink-600 hover:bg-pink-700 rounded-lg transition-colors cursor-pointer shadow-sm"
                  >
                    <Plus className="w-3.5 h-3.5" />
                    Top-Up
                  </button>
                </div>
              )}
            </div>
          )}

          {/* API Credentials */}
          <div className="space-y-3">
            <h4 className="text-xs font-bold text-neutral-900 dark:text-neutral-100 uppercase tracking-wider flex items-center gap-1.5">
              <Key className="w-3.5 h-3.5 text-blue-600" />
              {environment === 'production' ? 'Production API Credentials' : 'Sandbox (Testing) API Credentials'}
            </h4>

            <div className="space-y-3">
              <div>
                <label className="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                  API Key / Client ID / Merchant Key
                </label>
                <input
                  type="text"
                  value={apiKey}
                  onChange={(e) => setApiKey(e.target.value)}
                  placeholder="e.g. jnt_live_key_..."
                  className="w-full px-3 py-2 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 font-mono"
                />
              </div>

              <div>
                <label className="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                  API Secret Key / Signature Secret Token
                </label>
                <input
                  type="password"
                  value={apiSecret}
                  onChange={(e) => setApiSecret(e.target.value)}
                  placeholder="••••••••••••••••••••••••"
                  className="w-full px-3 py-2 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 font-mono"
                />
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                    Courier Account No. / Billing ID
                  </label>
                  <input
                    type="text"
                    value={accountNo}
                    onChange={(e) => setAccountNo(e.target.value)}
                    placeholder="e.g. JNT-MY-88291039"
                    className="w-full px-3 py-2 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 font-mono"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                    Merchant Customer Code
                  </label>
                  <input
                    type="text"
                    value={merchantCode}
                    onChange={(e) => setMerchantCode(e.target.value)}
                    placeholder="e.g. AURORA_VIP_01"
                    className="w-full px-3 py-2 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 font-mono"
                  />
                </div>
              </div>
            </div>
          </div>

          {/* Webhook Endpoint */}
          <div className="space-y-2 border-t border-neutral-200 dark:border-neutral-800 pt-3">
            <label className="block text-xs font-bold text-neutral-900 dark:text-neutral-100 uppercase tracking-wider">
              Courier Webhook Callback URL
            </label>
            <div className="flex items-center gap-2">
              <input
                type="text"
                readOnly
                value={webhookUrl}
                className="flex-1 px-3 py-2 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-100 dark:bg-neutral-800/80 text-neutral-600 dark:text-neutral-300 font-mono select-all"
              />
              <button
                type="button"
                onClick={handleCopyWebhook}
                className="flex items-center gap-1 px-3 py-2 text-xs font-medium bg-neutral-200 dark:bg-neutral-700 hover:bg-neutral-300 dark:hover:bg-neutral-600 rounded-lg transition-colors cursor-pointer text-neutral-800 dark:text-neutral-200"
              >
                {isCopied ? <Check className="w-3.5 h-3.5 text-emerald-600" /> : <Copy className="w-3.5 h-3.5" />}
                {isCopied ? 'Copied' : 'Copy'}
              </button>
            </div>
            <p className="text-[11px] text-neutral-500 dark:text-neutral-400">
              Paste this URL into your {config.name || config.displayName} merchant portal to receive real-time parcel tracking push updates.
            </p>
          </div>

          {/* Handshake Ping & Features */}
          <div className="border-t border-neutral-200 dark:border-neutral-800 pt-3 space-y-3">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={handleTestPing}
                  disabled={isTestingPing}
                  className="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg hover:bg-blue-100 transition-colors cursor-pointer"
                >
                  <Zap className="w-3.5 h-3.5 text-amber-500" />
                  {isTestingPing ? 'Testing Handshake...' : 'Ping Courier API Endpoint'}
                </button>
                {pingResult && (
                  <span className="text-xs font-mono text-emerald-600 dark:text-emerald-400 font-semibold">
                    ✓ Handshake 200 OK ({pingResult}ms)
                  </span>
                )}
              </div>

              <label className="flex items-center gap-2 text-xs font-semibold text-neutral-800 dark:text-neutral-200 cursor-pointer">
                <input
                  type="checkbox"
                  checked={isEnabled}
                  onChange={(e) => setIsEnabled(e.target.checked)}
                  className="rounded text-blue-600 focus:ring-blue-500"
                />
                Enable this Courier
              </label>
            </div>

            {/* Feature toggles */}
            <div className="grid grid-cols-2 gap-2 text-xs text-neutral-700 dark:text-neutral-300 pt-1">
              <label className="flex items-center gap-2 p-2 rounded-lg border border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-800/30 cursor-pointer">
                <input
                  type="checkbox"
                  checked={autoRetryQueue}
                  onChange={(e) => setAutoRetryQueue(e.target.checked)}
                  className="rounded text-blue-600 focus:ring-blue-500"
                />
                Auto-retry webhook and dispatch queues
              </label>
              <label className="flex items-center gap-2 p-2 rounded-lg border border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-800/30 cursor-pointer">
                <input
                  type="checkbox"
                  checked={supportsAirFreightHazmat}
                  onChange={(e) => setSupportsAirFreightHazmat(e.target.checked)}
                  className="rounded text-blue-600 focus:ring-blue-500"
                />
                Supports Battery / Liquid Cargo (Hazmat)
              </label>
              <label className="flex items-center gap-2 p-2 rounded-lg border border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-800/30 cursor-pointer">
                <input
                  type="checkbox"
                  checked={supportsCOD}
                  onChange={(e) => setSupportsCOD(e.target.checked)}
                  className="rounded text-blue-600 focus:ring-blue-500"
                />
                Supports COD (Cash on Delivery)
              </label>
              <label className="flex items-center gap-2 p-2 rounded-lg border border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-800/30 cursor-pointer">
                <input
                  type="checkbox"
                  checked={supportsEastMalaysia}
                  onChange={(e) => setSupportsEastMalaysia(e.target.checked)}
                  className="rounded text-blue-600 focus:ring-blue-500"
                />
                Supports East Malaysia (Sabah & Sarawak)
              </label>
            </div>
          </div>

          {/* Footer buttons */}
          <div className="pt-3 flex items-center justify-end gap-2 border-t border-neutral-200 dark:border-neutral-800">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 text-xs font-medium text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-lg transition-colors cursor-pointer"
            >
              Cancel
            </button>
            <button
              type="submit"
              className="flex items-center gap-1.5 px-4 py-2 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors shadow-sm cursor-pointer"
            >
              <ShieldCheck className="w-3.5 h-3.5" />
              Save Configuration
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};
