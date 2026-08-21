import React, { useState, useEffect } from 'react';
import { PaymentGatewayConfig } from '../../types/payment';
import { PaymentLogo } from '../common/PaymentLogo';
import {
  X,
  Key,
  ShieldCheck,
  Zap,
  Globe,
  DollarSign,
  Copy,
  Check,
  AlertCircle,
  ExternalLink,
  Lock,
  RefreshCw
} from 'lucide-react';

interface PaymentGatewayModalProps {
  isOpen: boolean;
  onClose: () => void;
  gateway: PaymentGatewayConfig | null;
  onSave: (config: PaymentGatewayConfig) => void;
  onPing: (id: PaymentGatewayConfig['id']) => Promise<number>;
}

export const PaymentGatewayModal: React.FC<PaymentGatewayModalProps> = ({
  isOpen,
  onClose,
  gateway,
  onSave,
  onPing
}) => {
  const [formData, setFormData] = useState<PaymentGatewayConfig | null>(null);
  const [copiedWebhook, setCopiedWebhook] = useState(false);
  const [isPinging, setIsPinging] = useState(false);
  const [pingLatency, setPingLatency] = useState<number | null>(null);

  useEffect(() => {
    if (gateway) {
      setFormData(JSON.parse(JSON.stringify(gateway)));
      setPingLatency(null);
    }
  }, [gateway]);

  if (!isOpen || !formData) return null;

  const handleCopyWebhook = () => {
    navigator.clipboard.writeText(formData.webhookUrl);
    setCopiedWebhook(true);
    setTimeout(() => setCopiedWebhook(false), 2000);
  };

  const handlePingTest = async () => {
    setIsPinging(true);
    try {
      const ms = await onPing(formData.id);
      setPingLatency(ms);
    } catch {
      // ignore
    } finally {
      setIsPinging(false);
    }
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (formData) {
      onSave(formData);
      onClose();
    }
  };

  const updateCredential = (key: string, value: string) => {
    setFormData({
      ...formData,
      credentials: {
        ...formData.credentials,
        [key]: value
      }
    });
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs overflow-y-auto">
      <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-2xl w-full max-w-2xl shadow-2xl overflow-hidden my-8 animate-in fade-in zoom-in-95 duration-150">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-950/50">
          <div className="flex items-center gap-3">
            <PaymentLogo type={formData.id} size="md" />
            <div>
              <h2 className="text-base font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                Configure {formData.name}
                {formData.isSandbox && (
                  <span className="text-[10px] uppercase font-bold px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                    Sandbox Mode
                  </span>
                )}
              </h2>
              <p className="text-xs text-neutral-500">{formData.description}</p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-2 rounded-lg text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition cursor-pointer"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Form Body */}
        <form onSubmit={handleSubmit} className="p-6 space-y-6 max-h-[75vh] overflow-y-auto">
          {/* Status & Environment Toggles */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-700/60 flex items-center justify-between">
              <div>
                <label className="text-xs font-bold text-neutral-900 dark:text-white">Enable Gateway</label>
                <p className="text-[11px] text-neutral-500">Show this payment option to shoppers at checkout</p>
              </div>
              <input
                type="checkbox"
                checked={formData.isEnabled}
                onChange={e => setFormData({ ...formData, isEnabled: e.target.checked })}
                className="w-5 h-5 rounded text-indigo-600 accent-indigo-600 cursor-pointer"
              />
            </div>

            <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-700/60 flex items-center justify-between">
              <div>
                <label className="text-xs font-bold text-neutral-900 dark:text-white">Sandbox / Test Mode</label>
                <p className="text-[11px] text-neutral-500">Simulate transactions without real funds</p>
              </div>
              <input
                type="checkbox"
                checked={formData.isSandbox}
                onChange={e => setFormData({ ...formData, isSandbox: e.target.checked })}
                className="w-5 h-5 rounded text-amber-600 accent-amber-600 cursor-pointer"
              />
            </div>
          </div>

          {/* Credentials Section */}
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5">
                <Lock className="w-3.5 h-3.5 text-indigo-600" />
                API Credentials & Authentication
              </h3>
              <span className="text-[11px] text-neutral-400 flex items-center gap-1">
                <ShieldCheck className="w-3.5 h-3.5 text-emerald-600" />
                AES-256 Vault Encrypted
              </span>
            </div>

            {/* Billplz Fields */}
            {formData.id === 'billplz' && (
              <div className="space-y-3">
                <div>
                  <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">API Secret Key</label>
                  <input
                    type="password"
                    value={formData.credentials.apiKey || ''}
                    onChange={e => updateCredential('apiKey', e.target.value)}
                    placeholder="sec_live_..."
                    className="w-full mt-1 px-3.5 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-mono"
                  />
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Collection ID</label>
                    <input
                      type="text"
                      value={formData.credentials.collectionId || ''}
                      onChange={e => updateCredential('collectionId', e.target.value)}
                      placeholder="e.g. in0912ab"
                      className="w-full mt-1 px-3.5 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-mono"
                    />
                  </div>
                  <div>
                    <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">X-Signature Key</label>
                    <input
                      type="password"
                      value={formData.credentials.signatureKey || ''}
                      onChange={e => updateCredential('signatureKey', e.target.value)}
                      placeholder="x_sig_..."
                      className="w-full mt-1 px-3.5 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-mono"
                    />
                  </div>
                </div>
              </div>
            )}

            {/* ToyyibPay Fields */}
            {formData.id === 'toyyibpay' && (
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">User Secret Key</label>
                  <input
                    type="password"
                    value={formData.credentials.secretKey || ''}
                    onChange={e => updateCredential('secretKey', e.target.value)}
                    placeholder="toyyib_user_sec_..."
                    className="w-full mt-1 px-3.5 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-mono"
                  />
                </div>
                <div>
                  <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Category Code</label>
                  <input
                    type="text"
                    value={formData.credentials.categoryCode || ''}
                    onChange={e => updateCredential('categoryCode', e.target.value)}
                    placeholder="e.g. aurora_cat_01"
                    className="w-full mt-1 px-3.5 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-mono"
                  />
                </div>
              </div>
            )}

            {/* SenangPay Fields */}
            {formData.id === 'senangpay' && (
              <div className="space-y-3">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Merchant ID</label>
                    <input
                      type="text"
                      value={formData.credentials.merchantId || ''}
                      onChange={e => updateCredential('merchantId', e.target.value)}
                      placeholder="e.g. 682910482910"
                      className="w-full mt-1 px-3.5 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-mono"
                    />
                  </div>
                  <div>
                    <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Hash Algorithm</label>
                    <select
                      value={formData.credentials.hashType || 'sha256'}
                      onChange={e => updateCredential('hashType', e.target.value)}
                      className="w-full mt-1 px-3.5 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-semibold"
                    >
                      <option value="sha256">SHA256 (Recommended)</option>
                      <option value="md5">MD5 (Legacy)</option>
                    </select>
                  </div>
                </div>
                <div>
                  <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Secret Key</label>
                  <input
                    type="password"
                    value={formData.credentials.secretKey || ''}
                    onChange={e => updateCredential('secretKey', e.target.value)}
                    placeholder="sec_senang_..."
                    className="w-full mt-1 px-3.5 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-mono"
                  />
                </div>
              </div>
            )}

            {/* Stripe Fields */}
            {formData.id === 'stripe' && (
              <div className="space-y-3">
                <div>
                  <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Publishable Key</label>
                  <input
                    type="text"
                    value={formData.credentials.publishableKey || ''}
                    onChange={e => updateCredential('publishableKey', e.target.value)}
                    placeholder="pk_live_..."
                    className="w-full mt-1 px-3.5 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-mono"
                  />
                </div>
                <div>
                  <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Secret Key</label>
                  <input
                    type="password"
                    value={formData.credentials.secretKey || ''}
                    onChange={e => updateCredential('secretKey', e.target.value)}
                    placeholder="sk_live_..."
                    className="w-full mt-1 px-3.5 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-mono"
                  />
                </div>
                <div>
                  <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Webhook Signing Secret</label>
                  <input
                    type="password"
                    value={formData.credentials.webhookSecret || ''}
                    onChange={e => updateCredential('webhookSecret', e.target.value)}
                    placeholder="whsec_..."
                    className="w-full mt-1 px-3.5 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-mono"
                  />
                </div>
              </div>
            )}

            {/* Generic / Other Gateways */}
            {!['billplz', 'toyyibpay', 'senangpay', 'stripe'].includes(formData.id) && (
              <div className="space-y-3">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Merchant / Client ID</label>
                    <input
                      type="text"
                      value={formData.credentials.merchantId || formData.credentials.clientId || formData.credentials.partnerId || ''}
                      onChange={e => updateCredential(formData.credentials.clientId ? 'clientId' : (formData.credentials.partnerId ? 'partnerId' : 'merchantId'), e.target.value)}
                      placeholder="Merchant identifier"
                      className="w-full mt-1 px-3.5 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-mono"
                    />
                  </div>
                  <div>
                    <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Secret / Signature Key</label>
                    <input
                      type="password"
                      value={formData.credentials.secretKey || formData.credentials.clientSecret || formData.credentials.apiKey || ''}
                      onChange={e => updateCredential(formData.credentials.clientSecret ? 'clientSecret' : (formData.credentials.apiKey ? 'apiKey' : 'secretKey'), e.target.value)}
                      placeholder="Private key token"
                      className="w-full mt-1 px-3.5 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-mono"
                    />
                  </div>
                </div>
              </div>
            )}
          </div>

          {/* Webhook Endpoint */}
          <div className="space-y-1.5 p-4 rounded-xl bg-indigo-50/50 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-900/60">
            <div className="flex items-center justify-between">
              <label className="text-xs font-bold text-indigo-950 dark:text-indigo-200">Merchant Webhook Notification URL</label>
              <span className="text-[10px] text-indigo-600 dark:text-indigo-400 font-semibold">Copy into gateway portal</span>
            </div>
            <div className="flex items-center gap-2">
              <input
                type="text"
                readOnly
                value={formData.webhookUrl}
                className="flex-1 px-3 py-1.5 text-xs font-mono rounded-lg border border-indigo-300 dark:border-indigo-800 bg-white dark:bg-neutral-900 text-neutral-700 dark:text-neutral-300"
              />
              <button
                type="button"
                onClick={handleCopyWebhook}
                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition cursor-pointer"
              >
                {copiedWebhook ? <Check className="w-3.5 h-3.5" /> : <Copy className="w-3.5 h-3.5" />}
                <span>{copiedWebhook ? 'Copied' : 'Copy'}</span>
              </button>
            </div>
          </div>

          {/* Transaction Fees & Payment Limits */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="space-y-3 p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-800/30">
              <h4 className="text-xs font-bold text-neutral-900 dark:text-white flex items-center gap-1.5">
                <DollarSign className="w-3.5 h-3.5 text-indigo-600" />
                MDR Fee & Surcharge Rules
              </h4>

              <div className="space-y-2">
                <div>
                  <label className="text-[11px] font-semibold text-neutral-600 dark:text-neutral-400">Fee Calculation</label>
                  <div className="grid grid-cols-2 gap-2 mt-1">
                    <div>
                      <span className="text-[10px] text-neutral-400">Fixed (RM)</span>
                      <input
                        type="number"
                        step="0.01"
                        min="0"
                        value={formData.transactionFee.fixedAmount || 0}
                        onChange={e => setFormData({
                          ...formData,
                          transactionFee: {
                            ...formData.transactionFee,
                            fixedAmount: parseFloat(e.target.value) || 0
                          }
                        })}
                        className="w-full px-2.5 py-1.5 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs"
                      />
                    </div>
                    <div>
                      <span className="text-[10px] text-neutral-400">Percentage (%)</span>
                      <input
                        type="number"
                        step="0.1"
                        min="0"
                        value={formData.transactionFee.percentageRate || 0}
                        onChange={e => setFormData({
                          ...formData,
                          transactionFee: {
                            ...formData.transactionFee,
                            percentageRate: parseFloat(e.target.value) || 0
                          }
                        })}
                        className="w-full px-2.5 py-1.5 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs"
                      />
                    </div>
                  </div>
                </div>

                <div>
                  <label className="text-[11px] font-semibold text-neutral-600 dark:text-neutral-400">Fee Absorption</label>
                  <select
                    value={formData.transactionFee.feePayer}
                    onChange={e => setFormData({
                      ...formData,
                      transactionFee: {
                        ...formData.transactionFee,
                        feePayer: e.target.value as 'merchant' | 'customer'
                      }
                    })}
                    className="w-full mt-1 px-3 py-1.5 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-semibold"
                  >
                    <option value="merchant">Merchant Absorbs Fee (Free for shopper)</option>
                    <option value="customer">Pass Surcharge to Shopper at Checkout</option>
                  </select>
                </div>
              </div>
            </div>

            <div className="space-y-3 p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-800/30">
              <h4 className="text-xs font-bold text-neutral-900 dark:text-white flex items-center gap-1.5">
                <Globe className="w-3.5 h-3.5 text-indigo-600" />
                Limits & Accepted Currencies
              </h4>

              <div className="grid grid-cols-2 gap-2">
                <div>
                  <label className="text-[11px] font-semibold text-neutral-600 dark:text-neutral-400">Min Order (RM)</label>
                  <input
                    type="number"
                    min="1"
                    value={formData.limits.minAmount || 1}
                    onChange={e => setFormData({
                      ...formData,
                      limits: {
                        ...formData.limits,
                        minAmount: parseFloat(e.target.value) || 1
                      }
                    })}
                    className="w-full mt-1 px-2.5 py-1.5 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs"
                  />
                </div>
                <div>
                  <label className="text-[11px] font-semibold text-neutral-600 dark:text-neutral-400">Max Order (RM)</label>
                  <input
                    type="number"
                    min="10"
                    value={formData.limits.maxAmount || 50000}
                    onChange={e => setFormData({
                      ...formData,
                      limits: {
                        ...formData.limits,
                        maxAmount: parseFloat(e.target.value) || 50000
                      }
                    })}
                    className="w-full mt-1 px-2.5 py-1.5 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs"
                  />
                </div>
              </div>

              <div>
                <label className="text-[11px] font-semibold text-neutral-600 dark:text-neutral-400">Supported Currencies</label>
                <div className="flex flex-wrap gap-1.5 mt-1.5">
                  {['MYR', 'SGD', 'USD', 'EUR', 'GBP'].map(curr => {
                    const isSelected = formData.supportedCurrencies.includes(curr);
                    return (
                      <button
                        key={curr}
                        type="button"
                        onClick={() => {
                          const list = isSelected
                            ? formData.supportedCurrencies.filter(c => c !== curr)
                            : [...formData.supportedCurrencies, curr];
                          setFormData({ ...formData, supportedCurrencies: list });
                        }}
                        className={`text-[10px] font-bold px-2 py-0.5 rounded-md border transition cursor-pointer ${
                          isSelected
                            ? 'bg-indigo-600 text-white border-indigo-600'
                            : 'bg-white dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 border-neutral-300 dark:border-neutral-700'
                        }`}
                      >
                        {curr}
                      </button>
                    );
                  })}
                </div>
              </div>
            </div>
          </div>

          {/* Connection Test & Health Status */}
          <div className="flex items-center justify-between p-3.5 rounded-xl bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700">
            <div className="flex items-center gap-2.5">
              <div className={`w-2.5 h-2.5 rounded-full ${
                formData.testStatus === 'success' ? 'bg-emerald-500 animate-pulse' : 'bg-neutral-400'
              }`} />
              <div>
                <div className="text-xs font-semibold text-neutral-900 dark:text-white">
                  {formData.testStatus === 'success' ? 'Connection Active & Verified' : 'Gateway Untested'}
                  {pingLatency && ` • ${pingLatency}ms`}
                </div>
                <div className="text-[11px] text-neutral-500">
                  {formData.lastTestedAt ? `Last ping: ${formData.lastTestedAt}` : 'Click Test to verify merchant API handshake'}
                </div>
              </div>
            </div>

            <button
              type="button"
              onClick={handlePingTest}
              disabled={isPinging}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-neutral-200 dark:bg-neutral-700 hover:bg-neutral-300 dark:hover:bg-neutral-600 text-neutral-800 dark:text-neutral-200 text-xs font-semibold transition cursor-pointer disabled:opacity-50"
            >
              <RefreshCw className={`w-3.5 h-3.5 ${isPinging ? 'animate-spin' : ''}`} />
              <span>{isPinging ? 'Testing...' : 'Test Connection'}</span>
            </button>
          </div>

          {/* Footer Actions */}
          <div className="flex items-center justify-end gap-3 pt-4 border-t border-neutral-200 dark:border-neutral-800">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 rounded-xl text-xs font-semibold text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition cursor-pointer"
            >
              Cancel
            </button>
            <button
              type="submit"
              className="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition cursor-pointer shadow-sm"
            >
              Save Configuration
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};
