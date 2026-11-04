import React, { useEffect, useState } from 'react';
import { useAdmin } from '../../context/AdminContext';
import {
  PaymentGatewayConfig,
  PaymentGatewayId,
  BankAccountDetails,
  PaymentMethodCategory
} from '../../types/payment';
import { PaymentGatewayModal } from './PaymentGatewayModal';
import { BankAccountModal } from './BankAccountModal';
import { PaymentLogo } from '../common/PaymentLogo';
import {
  CreditCard,
  Building,
  Truck,
  Sliders,
  Play,
  Calculator,
  ShieldCheck,
  Zap,
  CheckCircle2,
  AlertTriangle,
  RefreshCw,
  Plus,
  Edit2,
  Trash2,
  QrCode,
  Lock,
  ExternalLink,
  ChevronRight,
  Sparkles,
  Info,
  DollarSign,
  Copy,
  Check,
  Smartphone,
  Layers,
  ArrowRight
} from 'lucide-react';

export const PaymentSettingAdmin: React.FC = () => {
  const {
    paymentGateways,
    savePaymentGateway,
    togglePaymentGateway,
    pingPaymentGateway,
    bankTransferConfig,
    saveBankTransferConfig,
    saveBankAccount,
    deleteBankAccount,
    setPrimaryBankAccount,
    codConfig,
    saveCodConfig,
    globalPaymentSettings,
    saveGlobalPaymentSettings,
    addToast
  } = useAdmin();

  // Active sub-tab in Payment Setting
  const [activeTab, setActiveTab] = useState<'gateways' | 'bank_transfer' | 'cod' | 'rules' | 'simulator' | 'fee_calculator'>('gateways');
  const [categoryFilter, setCategoryFilter] = useState<'all' | PaymentMethodCategory>('all');
  const [searchQuery, setSearchQuery] = useState('');

  // Modals state
  const [selectedGateway, setSelectedGateway] = useState<PaymentGatewayConfig | null>(null);
  const [isGatewayModalOpen, setIsGatewayModalOpen] = useState(false);
  const [selectedBankAccount, setSelectedBankAccount] = useState<BankAccountDetails | null>(null);
  const [isBankModalOpen, setIsBankModalOpen] = useState(false);

  // Ping test tracking
  const [pingingIds, setPingingIds] = useState<{ [key: string]: boolean }>({});

  // Local state for COD & Rules forms
  const [codForm, setCodForm] = useState({ ...codConfig });
  const [bankForm, setBankForm] = useState({ ...bankTransferConfig });
  const [rulesForm, setRulesForm] = useState({ ...globalPaymentSettings });

  useEffect(() => {
    setBankForm({ ...bankTransferConfig });
  }, [bankTransferConfig]);

  useEffect(() => {
    setCodForm({ ...codConfig });
  }, [codConfig]);

  useEffect(() => {
    setRulesForm({ ...globalPaymentSettings });
  }, [globalPaymentSettings]);

  // Simulator state
  const [simCartTotal, setSimCartTotal] = useState<number>(185.00);
  const [simPaymentMethod, setSimPaymentMethod] = useState<string>('billplz');
  const [simReceiptUploaded, setSimReceiptUploaded] = useState<boolean>(false);
  const [simPaymentSuccess, setSimPaymentSuccess] = useState<boolean>(false);

  // Fee calculator test order amount
  const [calcOrderAmount, setCalcOrderAmount] = useState<number>(200);

  const handleEditGateway = (gw: PaymentGatewayConfig) => {
    setSelectedGateway(gw);
    setIsGatewayModalOpen(true);
  };

  const handlePing = async (id: PaymentGatewayId) => {
    setPingingIds(prev => ({ ...prev, [id]: true }));
    try {
      await pingPaymentGateway(id);
    } finally {
      setPingingIds(prev => ({ ...prev, [id]: false }));
    }
  };

  const handleSaveBankTransferSettings = (e: React.FormEvent) => {
    e.preventDefault();
    saveBankTransferConfig(bankForm);
  };

  const handleSaveCodSettings = (e: React.FormEvent) => {
    e.preventDefault();
    saveCodConfig(codForm);
  };

  const handleSaveRulesSettings = (e: React.FormEvent) => {
    e.preventDefault();
    saveGlobalPaymentSettings(rulesForm);
  };

  const filteredGateways = paymentGateways.filter(gw => {
    const matchCat = categoryFilter === 'all' || gw.category === categoryFilter;
    const matchSearch = gw.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      gw.description.toLowerCase().includes(searchQuery.toLowerCase());
    return matchCat && matchSearch;
  });

  const activeGatewaysCount = paymentGateways.filter(g => g.isEnabled).length;
  const activeBankCount = bankTransferConfig.bankAccounts.filter(b => b.isActive).length;

  return (
    <div className="space-y-6">
      {/* Sub-tabs bar */}
      <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-2 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div className="flex items-center gap-1.5 overflow-x-auto scrollbar-thin">
          <button
            onClick={() => setActiveTab('gateways')}
            className={`flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold transition cursor-pointer shrink-0 ${
              activeTab === 'gateways'
                ? 'bg-indigo-600 text-white shadow-xs'
                : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'
            }`}
          >
            <CreditCard className="w-4 h-4" />
            <span>Online Gateways & E-Wallets</span>
            <span className={`text-[10px] px-1.5 py-0.2 rounded-full font-bold ${
              activeTab === 'gateways' ? 'bg-indigo-700 text-white' : 'bg-neutral-200 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300'
            }`}>
              {activeGatewaysCount}/{paymentGateways.length}
            </span>
          </button>

          <button
            onClick={() => setActiveTab('bank_transfer')}
            className={`flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold transition cursor-pointer shrink-0 ${
              activeTab === 'bank_transfer'
                ? 'bg-indigo-600 text-white shadow-xs'
                : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'
            }`}
          >
            <Building className="w-4 h-4" />
            <span>Direct Bank Transfer / DuitNow</span>
            {bankTransferConfig.isEnabled && (
              <span className="w-2 h-2 rounded-full bg-emerald-500" />
            )}
          </button>

          <button
            onClick={() => setActiveTab('cod')}
            className={`flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold transition cursor-pointer shrink-0 ${
              activeTab === 'cod'
                ? 'bg-indigo-600 text-white shadow-xs'
                : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'
            }`}
          >
            <Truck className="w-4 h-4" />
            <span>Cash on Delivery (COD)</span>
            {codConfig.isEnabled && (
              <span className="w-2 h-2 rounded-full bg-emerald-500" />
            )}
          </button>

          <button
            onClick={() => setActiveTab('rules')}
            className={`flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold transition cursor-pointer shrink-0 ${
              activeTab === 'rules'
                ? 'bg-indigo-600 text-white shadow-xs'
                : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'
            }`}
          >
            <Sliders className="w-4 h-4" />
            <span>Global Rules & Expiry</span>
          </button>

          <button
            onClick={() => setActiveTab('simulator')}
            className={`flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold transition cursor-pointer shrink-0 ${
              activeTab === 'simulator'
                ? 'bg-violet-600 text-white shadow-xs'
                : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'
            }`}
          >
            <Play className="w-4 h-4" />
            <span>Live Checkout Simulator</span>
          </button>

          <button
            onClick={() => setActiveTab('fee_calculator')}
            className={`flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold transition cursor-pointer shrink-0 ${
              activeTab === 'fee_calculator'
                ? 'bg-indigo-600 text-white shadow-xs'
                : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'
            }`}
          >
            <Calculator className="w-4 h-4" />
            <span>MDR Fee Analyzer</span>
          </button>
        </div>

        {activeTab === 'bank_transfer' && (
          <button
            onClick={() => {
              setSelectedBankAccount(null);
              setIsBankModalOpen(true);
            }}
            className="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition cursor-pointer shrink-0"
          >
            <Plus className="w-3.5 h-3.5" />
            <span>Add Bank Account</span>
          </button>
        )}
      </div>

      {/* TAB 1: ONLINE GATEWAYS & E-WALLETS */}
      {activeTab === 'gateways' && (
        <div className="space-y-6">
          {/* Header Banner */}
          <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-6 shadow-sm">
            <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
              <div>
                <h2 className="text-xl font-bold text-neutral-900 dark:text-white flex items-center gap-2.5">
                  <CreditCard className="w-6 h-6 text-indigo-600" />
                  Payment Gateways & Digital Wallets
                </h2>
                <p className="text-xs text-neutral-500 mt-1">
                  Connect Malaysian FPX Online Banking, International Credit/Debit Cards, E-Wallets, and Buy Now Pay Later (BNPL) providers.
                </p>
              </div>

              {/* Quick Stats */}
              <div className="flex items-center gap-3">
                <div className="px-3.5 py-2 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700/60 text-right">
                  <div className="text-[10px] text-neutral-400 font-semibold uppercase">Active Channels</div>
                  <div className="text-sm font-bold text-neutral-900 dark:text-white">{activeGatewaysCount} Providers</div>
                </div>
                <div className="px-3.5 py-2 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/60 text-right">
                  <div className="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold uppercase">FPX Retail Banks</div>
                  <div className="text-sm font-bold text-emerald-700 dark:text-emerald-300">23 Connected</div>
                </div>
              </div>
            </div>

            {/* Filter Pills */}
            <div className="flex flex-wrap items-center justify-between gap-3 mt-6 pt-4 border-t border-neutral-100 dark:border-neutral-800">
              <div className="flex flex-wrap items-center gap-1.5">
                {[
                  { id: 'all', label: 'All Channels' },
                  { id: 'online_banking_fpx', label: 'Malaysian FPX' },
                  { id: 'card', label: 'Cards & Global (Stripe/PayPal)' },
                  { id: 'ewallet', label: 'E-Wallets (TNG/Grab)' },
                  { id: 'bnpl', label: 'BNPL 3x (Atome)' }
                ].map(tab => (
                  <button
                    key={tab.id}
                    onClick={() => setCategoryFilter(tab.id as any)}
                    className={`text-xs font-semibold px-3 py-1.5 rounded-lg transition cursor-pointer ${
                      categoryFilter === tab.id
                        ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800'
                        : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 border border-transparent'
                    }`}
                  >
                    {tab.label}
                  </button>
                ))}
              </div>

              <input
                type="text"
                placeholder="Search gateway..."
                value={searchQuery}
                onChange={e => setSearchQuery(e.target.value)}
                className="px-3 py-1.5 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200 w-48"
              />
            </div>
          </div>

          {/* Gateway Cards Grid */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {filteredGateways.map(gw => {
              const isPinging = pingingIds[gw.id] || false;
              return (
                <div
                  key={gw.id}
                  className={`bg-white dark:bg-neutral-900 rounded-2xl border transition-all duration-200 p-5 space-y-4 ${
                    gw.isEnabled
                      ? 'border-neutral-200 dark:border-neutral-800 shadow-xs'
                      : 'border-neutral-200/60 dark:border-neutral-800/50 opacity-75 bg-neutral-50/50 dark:bg-neutral-900/40'
                  }`}
                >
                  {/* Top Bar */}
                  <div className="flex items-start justify-between gap-3">
                    <div className="flex items-center gap-3">
                      <PaymentLogo type={gw.id} size="md" />
                      <div>
                        <div className="flex items-center gap-2">
                          <h3 className="text-sm font-bold text-neutral-900 dark:text-white">{gw.name}</h3>
                          {gw.isSandbox && (
                            <span className="text-[9px] uppercase font-bold px-1.5 py-0.2 rounded bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30">
                              Sandbox
                            </span>
                          )}
                        </div>
                        <span className="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400">
                          {gw.category === 'online_banking_fpx' ? 'FPX Online Banking' :
                           gw.category === 'card' ? 'Credit / Debit Card' :
                           gw.category === 'ewallet' ? 'Mobile E-Wallet' : 'Buy Now Pay Later'}
                        </span>
                      </div>
                    </div>

                    {/* Enable Toggle */}
                    <div className="flex items-center gap-2">
                      <button
                        type="button"
                        onClick={() => togglePaymentGateway(gw.id, !gw.isEnabled)}
                        className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors cursor-pointer ${
                          gw.isEnabled ? 'bg-indigo-600' : 'bg-neutral-300 dark:bg-neutral-700'
                        }`}
                      >
                        <span
                          className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                            gw.isEnabled ? 'translate-x-6' : 'translate-x-1'
                          }`}
                        />
                      </button>
                    </div>
                  </div>

                  <p className="text-xs text-neutral-500 leading-relaxed">{gw.description}</p>

                  {/* Specs & Fee Pills */}
                  <div className="grid grid-cols-3 gap-2 pt-2 border-t border-neutral-100 dark:border-neutral-800 text-[11px]">
                    <div className="p-2 rounded-lg bg-neutral-50 dark:bg-neutral-800/50">
                      <span className="text-[10px] text-neutral-400 block">MDR Fee</span>
                      <span className="font-bold text-neutral-800 dark:text-neutral-200">
                        {gw.transactionFee.type === 'fixed'
                          ? `RM ${(gw.transactionFee.fixedAmount || 0).toFixed(2)}`
                          : gw.transactionFee.type === 'percentage'
                          ? `${gw.transactionFee.percentageRate}%`
                          : `${gw.transactionFee.percentageRate}% + RM ${(gw.transactionFee.fixedAmount || 0).toFixed(2)}`}
                      </span>
                    </div>

                    <div className="p-2 rounded-lg bg-neutral-50 dark:bg-neutral-800/50">
                      <span className="text-[10px] text-neutral-400 block">Fee Absorption</span>
                      <span className="font-semibold text-neutral-700 dark:text-neutral-300 capitalize">
                        {gw.transactionFee.feePayer === 'merchant' ? 'Store Absorbs' : 'Buyer Surcharge'}
                      </span>
                    </div>

                    <div className="p-2 rounded-lg bg-neutral-50 dark:bg-neutral-800/50">
                      <span className="text-[10px] text-neutral-400 block">Order Range</span>
                      <span className="font-semibold text-neutral-700 dark:text-neutral-300">
                        RM {gw.limits.minAmount || 1} - {gw.limits.maxAmount ? `RM ${(gw.limits.maxAmount / 1000).toFixed(0)}k` : 'No Max'}
                      </span>
                    </div>
                  </div>

                  {/* Test & Actions Bar */}
                  <div className="flex items-center justify-between pt-2 border-t border-neutral-100 dark:border-neutral-800">
                    <div className="flex items-center gap-2">
                      <div className={`w-2 h-2 rounded-full ${
                        gw.testStatus === 'success' ? 'bg-emerald-500' : 'bg-neutral-400'
                      }`} />
                      <span className="text-[10px] text-neutral-400">
                        {gw.lastTestedAt ? `Verified: ${gw.lastTestedAt.split(' ')[0]}` : 'Untested'}
                      </span>
                    </div>

                    <div className="flex items-center gap-2">
                      <button
                        type="button"
                        onClick={() => handlePing(gw.id)}
                        disabled={isPinging}
                        className="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-800 text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 transition cursor-pointer"
                      >
                        <RefreshCw className={`w-3 h-3 ${isPinging ? 'animate-spin' : ''}`} />
                        <span>{isPinging ? 'Pinging...' : 'Ping Test'}</span>
                      </button>

                      <button
                        type="button"
                        onClick={() => handleEditGateway(gw)}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 text-xs font-semibold transition cursor-pointer"
                      >
                        <Lock className="w-3.5 h-3.5" />
                        <span>Configure Keys</span>
                      </button>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* TAB 2: DIRECT BANK TRANSFER / DUITNOW */}
      {activeTab === 'bank_transfer' && (
        <div className="space-y-6">
          <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-6 shadow-sm">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-neutral-100 dark:border-neutral-800">
              <div>
                <h2 className="text-xl font-bold text-neutral-900 dark:text-white flex items-center gap-2.5">
                  <Building className="w-6 h-6 text-indigo-600" />
                  Direct Bank Transfer & DuitNow QR
                </h2>
                <p className="text-xs text-neutral-500 mt-1">
                  Allow customers to pay via instant bank transfer or by scanning DuitNow QR. Orders remain pending until your finance team verifies payment.
                </p>
              </div>

              <div className="flex items-center gap-3">
                <div className="flex items-center gap-2 p-2 px-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700">
                  <span className="text-xs font-bold text-neutral-900 dark:text-white">Enable Manual Bank Transfer</span>
                  <input
                    type="checkbox"
                    checked={bankForm.isEnabled}
                    onChange={e => setBankForm({ ...bankForm, isEnabled: e.target.checked })}
                    className="w-5 h-5 rounded text-indigo-600 accent-indigo-600 cursor-pointer"
                  />
                </div>
              </div>
            </div>

            {/* Bank Accounts List */}
            <div className="mt-6 space-y-4">
              <div className="flex items-center justify-between">
                <h3 className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider">
                  Company Bank Accounts ({bankTransferConfig.bankAccounts.length})
                </h3>
                <span className="text-[11px] text-neutral-400">
                  Primary account is highlighted on customer checkout receipt
                </span>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                {bankTransferConfig.bankAccounts.map(account => (
                  <div
                    key={account.id}
                    className={`rounded-2xl border p-5 space-y-4 relative transition-all ${
                      account.isPrimary
                        ? 'border-indigo-300 dark:border-indigo-700 bg-indigo-50/20 dark:bg-indigo-950/20 shadow-xs'
                        : 'border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900'
                    }`}
                  >
                    {account.isPrimary && (
                      <div className="absolute top-3 right-3 text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-600 text-white">
                        PRIMARY
                      </div>
                    )}

                    <div className="flex items-center gap-3">
                      <PaymentLogo
                        type={
                          account.bankName.toLowerCase().includes('maybank') ? 'maybank' :
                          account.bankName.toLowerCase().includes('cimb') ? 'cimb' :
                          account.bankName.toLowerCase().includes('public') ? 'public_bank' :
                          account.bankName.toLowerCase().includes('rhb') ? 'rhb' :
                          account.bankName.toLowerCase().includes('hong leong') ? 'hong_leong' :
                          'bank_transfer'
                        }
                        size="sm"
                      />
                      <div>
                        <h4 className="text-xs font-bold text-neutral-900 dark:text-white">{account.bankName}</h4>
                        <span className="text-[10px] text-neutral-400 font-mono">SWIFT: {account.swiftCode || 'N/A'}</span>
                      </div>
                    </div>

                    <div className="space-y-1.5 p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 text-xs">
                      <div>
                        <span className="text-[10px] text-neutral-400 block">Account Number</span>
                        <span className="font-mono font-bold text-neutral-900 dark:text-white text-sm">
                          {account.accountNumber}
                        </span>
                      </div>
                      <div>
                        <span className="text-[10px] text-neutral-400 block">Account Holder</span>
                        <span className="font-semibold text-neutral-800 dark:text-neutral-200 truncate block">
                          {account.accountHolderName}
                        </span>
                      </div>
                      {account.duitNowId && (
                        <div>
                          <span className="text-[10px] text-neutral-400 block">DuitNow ID</span>
                          <span className="font-mono text-neutral-700 dark:text-neutral-300">{account.duitNowId}</span>
                        </div>
                      )}
                    </div>

                    {/* DuitNow QR Thumbnail */}
                    {account.duitNowQrUrl && (
                      <div className="flex items-center gap-3 p-2 rounded-xl bg-pink-50/50 dark:bg-pink-950/20 border border-pink-200 dark:border-pink-900/40">
                        <img
                          src={account.duitNowQrUrl}
                          alt="DuitNow QR"
                          referrerPolicy="no-referrer"
                          className="w-12 h-12 rounded-lg bg-white p-1 border border-pink-200"
                        />
                        <div className="text-[10px] text-pink-900 dark:text-pink-300">
                          <span className="font-bold block">DuitNow QR Active</span>
                          <span>MAE / TNG / GrabPay supported</span>
                        </div>
                      </div>
                    )}

                    {/* Card Actions */}
                    <div className="flex items-center justify-between pt-2 border-t border-neutral-100 dark:border-neutral-800">
                      {!account.isPrimary ? (
                        <button
                          type="button"
                          onClick={() => setPrimaryBankAccount(account.id)}
                          className="text-[11px] font-semibold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 cursor-pointer"
                        >
                          Set Primary
                        </button>
                      ) : (
                        <span className="text-[11px] text-emerald-600 font-semibold flex items-center gap-1">
                          <Check className="w-3 h-3" /> Default
                        </span>
                      )}

                      <div className="flex items-center gap-1">
                        <button
                          type="button"
                          onClick={() => {
                            setSelectedBankAccount(account);
                            setIsBankModalOpen(true);
                          }}
                          className="p-1.5 rounded-lg text-neutral-500 hover:text-indigo-600 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition cursor-pointer"
                        >
                          <Edit2 className="w-3.5 h-3.5" />
                        </button>
                        <button
                          type="button"
                          onClick={() => deleteBankAccount(account.id)}
                          className="p-1.5 rounded-lg text-neutral-500 hover:text-rose-600 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition cursor-pointer"
                        >
                          <Trash2 className="w-3.5 h-3.5" />
                        </button>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>

            {/* Bank Transfer Rules Form */}
            <form onSubmit={handleSaveBankTransferSettings} className="mt-8 pt-6 border-t border-neutral-100 dark:border-neutral-800 space-y-5">
              <h3 className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider">
                Transfer Instructions & Verification Rules
              </h3>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="flex items-center justify-between p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-700/60">
                  <div>
                    <label className="text-xs font-bold text-neutral-900 dark:text-white">Require Payment Receipt Upload</label>
                    <p className="text-[11px] text-neutral-500">Require shoppers to upload bank slip / screenshot on checkout</p>
                  </div>
                  <input
                    type="checkbox"
                    checked={bankForm.requireProofUpload}
                    onChange={e => setBankForm({ ...bankForm, requireProofUpload: e.target.checked })}
                    className="w-5 h-5 rounded text-indigo-600 accent-indigo-600 cursor-pointer"
                  />
                </div>

                <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-700/60 space-y-1">
                  <label className="text-xs font-bold text-neutral-900 dark:text-white">Payment Expiry Window (Hours)</label>
                  <p className="text-[11px] text-neutral-500">Auto-cancel order if transfer is not verified within window</p>
                  <input
                    type="number"
                    min="1"
                    max="72"
                    value={Math.round(bankForm.paymentWindowMinutes / 60)}
                    onChange={e => setBankForm({ ...bankForm, paymentWindowMinutes: (parseInt(e.target.value) || 24) * 60 })}
                    className="w-full mt-1 px-3 py-1.5 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-semibold"
                  />
                </div>
              </div>

              <div>
                <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                  Customer Transfer Instructions (Displayed on Checkout & Invoice)
                </label>
                <textarea
                  rows={4}
                  value={bankForm.verificationInstructions}
                  onChange={e => setBankForm({ ...bankForm, verificationInstructions: e.target.value })}
                  className="w-full mt-1 p-3.5 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs leading-relaxed"
                />
              </div>

              <button
                type="submit"
                className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition cursor-pointer shadow-sm"
              >
                <Check className="w-4 h-4" />
                <span>Save Bank Transfer Settings</span>
              </button>
            </form>
          </div>
        </div>
      )}

      {/* TAB 3: CASH ON DELIVERY (COD) */}
      {activeTab === 'cod' && (
        <div className="space-y-6">
          <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-6 sm:p-8 shadow-sm">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-neutral-100 dark:border-neutral-800">
              <div>
                <h2 className="text-xl font-bold text-neutral-900 dark:text-white flex items-center gap-2.5">
                  <Truck className="w-6 h-6 text-indigo-600" />
                  Cash on Delivery (COD) Settings
                </h2>
                <p className="text-xs text-neutral-500 mt-1">
                  Configure post-paid parcel collection fees, order limits, and geographic eligibility for door-to-door courier cash collection.
                </p>
              </div>

              <div className="flex items-center gap-2 p-2 px-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700">
                <span className="text-xs font-bold text-neutral-900 dark:text-white">Enable COD Payment</span>
                <input
                  type="checkbox"
                  checked={codForm.isEnabled}
                  onChange={e => setCodForm({ ...codForm, isEnabled: e.target.checked })}
                  className="w-5 h-5 rounded text-indigo-600 accent-indigo-600 cursor-pointer"
                />
              </div>
            </div>

            <form onSubmit={handleSaveCodSettings} className="mt-6 space-y-6 max-w-3xl">
              {/* Surcharge Configuration */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 space-y-2">
                  <label className="text-xs font-bold text-neutral-900 dark:text-white">COD Handling Surcharge Type</label>
                  <select
                    value={codForm.surchargeType}
                    onChange={e => setCodForm({ ...codForm, surchargeType: e.target.value as 'fixed' | 'percentage' })}
                    className="w-full px-3 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-semibold"
                  >
                    <option value="fixed">Fixed Surcharge (e.g. RM 5.00)</option>
                    <option value="percentage">Percentage of Cart Total (%)</option>
                  </select>
                </div>

                <div className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 space-y-2">
                  <label className="text-xs font-bold text-neutral-900 dark:text-white">
                    Surcharge Amount ({codForm.surchargeType === 'fixed' ? 'RM' : '%'})
                  </label>
                  <input
                    type="number"
                    step="0.1"
                    min="0"
                    value={codForm.surchargeAmount}
                    onChange={e => setCodForm({ ...codForm, surchargeAmount: parseFloat(e.target.value) || 0 })}
                    className="w-full px-3 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-bold font-mono"
                  />
                </div>
              </div>

              {/* Order Min/Max Limits */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 space-y-2">
                  <label className="text-xs font-bold text-neutral-900 dark:text-white">Minimum Order Amount (RM)</label>
                  <p className="text-[11px] text-neutral-500">Disallow COD for small low-margin orders</p>
                  <input
                    type="number"
                    min="0"
                    value={codForm.minOrderAmount}
                    onChange={e => setCodForm({ ...codForm, minOrderAmount: parseFloat(e.target.value) || 0 })}
                    className="w-full px-3 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-bold"
                  />
                </div>

                <div className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 space-y-2">
                  <label className="text-xs font-bold text-neutral-900 dark:text-white">Maximum Order Amount (RM)</label>
                  <p className="text-[11px] text-neutral-500">Cap max COD value to prevent fraud and carrier risk</p>
                  <input
                    type="number"
                    min="0"
                    value={codForm.maxOrderAmount}
                    onChange={e => setCodForm({ ...codForm, maxOrderAmount: parseFloat(e.target.value) || 0 })}
                    className="w-full px-3 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-bold"
                  />
                </div>
              </div>

              {/* Regional Coverage */}
              <div className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 space-y-3">
                <label className="text-xs font-bold text-neutral-900 dark:text-white">Eligible Delivery Region</label>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                  {[
                    { id: 'all', title: 'Whole Malaysia', desc: 'Peninsular + Sabah + Sarawak' },
                    { id: 'peninsular_only', title: 'Peninsular Only', desc: 'West Malaysia postcodes' },
                    { id: 'east_malaysia_only', title: 'East Malaysia Only', desc: 'Sabah and Sarawak' }
                  ].map(region => (
                    <label
                      key={region.id}
                      className={`p-3 rounded-xl border flex flex-col justify-between cursor-pointer transition ${
                        codForm.allowedRegions === region.id
                          ? 'border-indigo-600 bg-indigo-50/30 dark:bg-indigo-950/30'
                          : 'border-neutral-200 dark:border-neutral-700'
                      }`}
                    >
                      <div className="flex items-center justify-between">
                        <span className="text-xs font-bold text-neutral-900 dark:text-white">{region.title}</span>
                        <input
                          type="radio"
                          name="allowedRegions"
                          checked={codForm.allowedRegions === region.id}
                          onChange={() => setCodForm({ ...codForm, allowedRegions: region.id as any })}
                          className="w-4 h-4 text-indigo-600 accent-indigo-600"
                        />
                      </div>
                      <span className="text-[11px] text-neutral-500 mt-1">{region.desc}</span>
                    </label>
                  ))}
                </div>
              </div>

              {/* Customer Instruction Notes */}
              <div>
                <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                  Customer COD Delivery Instructions (Shown at Checkout)
                </label>
                <textarea
                  rows={3}
                  value={codForm.instructions}
                  onChange={e => setCodForm({ ...codForm, instructions: e.target.value })}
                  className="w-full mt-1 p-3.5 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs"
                />
              </div>

              <button
                type="submit"
                className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition cursor-pointer shadow-sm"
              >
                <Check className="w-4 h-4" />
                <span>Save COD Configuration</span>
              </button>
            </form>
          </div>
        </div>
      )}

      {/* TAB 4: GLOBAL RULES & EXPIRY */}
      {activeTab === 'rules' && (
        <div className="space-y-6">
          <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-6 sm:p-8 shadow-sm">
            <h2 className="text-xl font-bold text-neutral-900 dark:text-white flex items-center gap-2.5">
              <Sliders className="w-6 h-6 text-indigo-600" />
              Global Checkout Payment Rules & Expirations
            </h2>
            <p className="text-xs text-neutral-500 mt-1">
              Configure default gateway ordering, unpaid order automatic inventory release, reminder notices, .
            </p>

            <form onSubmit={handleSaveRulesSettings} className="mt-8 space-y-6 max-w-3xl">
              {/* Default Gateway Selection */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 space-y-2">
                  <label className="text-xs font-bold text-neutral-900 dark:text-white">Default Payment Gateway at Checkout</label>
                  <p className="text-[11px] text-neutral-500">Pre-selected option when customers open checkout</p>
                  <select
                    value={rulesForm.defaultGateway}
                    onChange={e => setRulesForm({ ...rulesForm, defaultGateway: e.target.value })}
                    className="w-full px-3 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-semibold"
                  >
                    <option value="billplz">Billplz (Malaysian FPX)</option>
                    <option value="stripe">Stripe (Credit / Debit Cards)</option>
                    <option value="tng_ewallet">Touch 'n Go eWallet</option>
                    <option value="toyyibpay">ToyyibPay FPX</option>
                    <option value="manual_transfer">Direct Bank Transfer</option>
                    <option value="atome">Atome BNPL (3x)</option>
                  </select>
                </div>

                <div className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 space-y-2">
                  <label className="text-xs font-bold text-neutral-900 dark:text-white">Unpaid FPX Auto-Cancel Timer (Minutes)</label>
                  <p className="text-[11px] text-neutral-500">Auto-release inventory reservations if buyer abandons payment</p>
                  <input
                    type="number"
                    min="15"
                    max="1440"
                    value={rulesForm.unpaidOrderAutoCancelMinutes}
                    onChange={e => setRulesForm({ ...rulesForm, unpaidOrderAutoCancelMinutes: parseInt(e.target.value) || 120 })}
                    className="w-full px-3 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-bold font-mono"
                  />
                </div>
              </div>

              {/* Toggles */}
              <div className="space-y-3">
                <div className="flex items-center justify-between p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-700/60">
                  <div>
                    <label className="text-xs font-bold text-neutral-900 dark:text-white">Send SMS Payment Reminders</label>
                    <p className="text-[11px] text-neutral-500">Send an automated SMS payment link 30 minutes before pending order expiration</p>
                  </div>
                  <input
                    type="checkbox"
                    checked={rulesForm.sendPaymentReminderSms}
                    onChange={e => setRulesForm({ ...rulesForm, sendPaymentReminderSms: e.target.checked })}
                    className="w-5 h-5 rounded text-indigo-600 accent-indigo-600 cursor-pointer"
                  />
                </div>

                <div className="flex items-center justify-between p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-700/60">
                  <div>
                    <label className="text-xs font-bold text-neutral-900 dark:text-white">Generate Official Tax Invoice at Checkout</label>
                    <p className="text-[11px] text-neutral-500">Include SST registration and itemized tax breakdown on order confirmation receipts</p>
                  </div>
                  <input
                    type="checkbox"
                    checked={rulesForm.displayTaxInvoiceAtCheckout}
                    onChange={e => setRulesForm({ ...rulesForm, displayTaxInvoiceAtCheckout: e.target.checked })}
                    className="w-5 h-5 rounded text-indigo-600 accent-indigo-600 cursor-pointer"
                  />
                </div>
              </div>

              <button
                type="submit"
                className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition cursor-pointer shadow-sm"
              >
                <Check className="w-4 h-4" />
                <span>Save Global Payment Policies</span>
              </button>
            </form>
          </div>
        </div>
      )}

      {/* TAB 5: LIVE CHECKOUT SIMULATOR */}
      {activeTab === 'simulator' && (
        <div className="space-y-6">
          <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-6 shadow-sm">
            <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-neutral-100 dark:border-neutral-800">
              <div>
                <h2 className="text-xl font-bold text-neutral-900 dark:text-white flex items-center gap-2.5">
                  <Play className="w-6 h-6 text-violet-600" />
                  Storefront Checkout Experience Simulator
                </h2>
                <p className="text-xs text-neutral-500 mt-1">
                  Preview exactly what your customers see and experience at payment stage based on your active configuration.
                </p>
              </div>

              <div className="flex items-center gap-3">
                <span className="text-xs font-semibold text-neutral-500">Simulate Cart Total:</span>
                <div className="flex items-center gap-1.5">
                  <span className="text-xs font-bold text-neutral-400">RM</span>
                  <input
                    type="number"
                    value={simCartTotal}
                    onChange={e => setSimCartTotal(parseFloat(e.target.value) || 0)}
                    className="w-24 px-2.5 py-1 text-xs font-bold rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white"
                  />
                </div>
              </div>
            </div>

            {/* Mobile/Tablet Preview Container */}
            <div className="mt-6 max-w-xl mx-auto border border-neutral-200 dark:border-neutral-800 rounded-3xl p-6 bg-neutral-50 dark:bg-neutral-950 shadow-xl space-y-5">
              <div className="flex items-center justify-between pb-3 border-b border-neutral-200 dark:border-neutral-800">
                <div className="flex items-center gap-2">
                  <div className="w-6 h-6 rounded-lg bg-indigo-600 text-white flex items-center justify-center text-xs font-bold">
                    A
                  </div>
                  <span className="text-xs font-bold text-neutral-900 dark:text-white">Aurora Checkout</span>
                </div>
                <span className="text-xs font-bold text-neutral-900 dark:text-white">
                  Total: RM {simCartTotal.toFixed(2)}
                </span>
              </div>

              {/* Payment Methods Accordion */}
              <div className="space-y-2.5">
                <span className="text-xs font-bold text-neutral-700 dark:text-neutral-300 uppercase tracking-wider block">
                  Select Payment Method
                </span>

                {/* Option 1: FPX Billplz */}
                <label
                  onClick={() => setSimPaymentMethod('billplz')}
                  className={`p-3.5 rounded-xl border flex items-center justify-between cursor-pointer transition ${
                    simPaymentMethod === 'billplz'
                      ? 'border-indigo-600 bg-indigo-50/40 dark:bg-indigo-950/40 ring-1 ring-indigo-500'
                      : 'border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900'
                  }`}
                >
                  <div className="flex items-center gap-3">
                    <PaymentLogo type="billplz" size="sm" />
                    <div>
                      <div className="text-xs font-bold text-neutral-900 dark:text-white flex items-center gap-1.5">
                        <span>Online Banking (FPX)</span>
                        <span className="text-[9px] px-1 py-0.2 rounded bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 font-bold">FPX</span>
                      </div>
                      <div className="text-[11px] text-neutral-500">Maybank2u, CIMB Clicks, Public Bank & 20+ more</div>
                    </div>
                  </div>
                  <input
                    type="radio"
                    name="simPay"
                    checked={simPaymentMethod === 'billplz'}
                    onChange={() => setSimPaymentMethod('billplz')}
                    className="w-4 h-4 text-indigo-600 accent-indigo-600"
                  />
                </label>

                {/* Option 2: Credit Card */}
                <label
                  onClick={() => setSimPaymentMethod('stripe')}
                  className={`p-3.5 rounded-xl border flex items-center justify-between cursor-pointer transition ${
                    simPaymentMethod === 'stripe'
                      ? 'border-indigo-600 bg-indigo-50/40 dark:bg-indigo-950/40 ring-1 ring-indigo-500'
                      : 'border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900'
                  }`}
                >
                  <div className="flex items-center gap-3">
                    <PaymentLogo type="stripe" size="sm" />
                    <div>
                      <div className="text-xs font-bold text-neutral-900 dark:text-white flex items-center gap-1.5">
                        <span>Credit / Debit Card (Stripe)</span>
                        <div className="flex items-center gap-1">
                          <PaymentLogo type="visa" size="xs" className="w-5 h-3.5" />
                          <PaymentLogo type="mastercard" size="xs" className="w-5 h-3.5" />
                        </div>
                      </div>
                      <div className="text-[11px] text-neutral-500">Visa, Mastercard, Apple Pay, Google Pay</div>
                    </div>
                  </div>
                  <input
                    type="radio"
                    name="simPay"
                    checked={simPaymentMethod === 'stripe'}
                    onChange={() => setSimPaymentMethod('stripe')}
                    className="w-4 h-4 text-indigo-600 accent-indigo-600"
                  />
                </label>

                {/* Option 3: TNG / GrabPay */}
                <label
                  onClick={() => setSimPaymentMethod('tng_ewallet')}
                  className={`p-3.5 rounded-xl border flex items-center justify-between cursor-pointer transition ${
                    simPaymentMethod === 'tng_ewallet'
                      ? 'border-indigo-600 bg-indigo-50/40 dark:bg-indigo-950/40 ring-1 ring-indigo-500'
                      : 'border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900'
                  }`}
                >
                  <div className="flex items-center gap-3">
                    <PaymentLogo type="tng_ewallet" size="sm" />
                    <div>
                      <div className="text-xs font-bold text-neutral-900 dark:text-white flex items-center gap-1.5">
                        <span>Touch 'n Go eWallet / GrabPay</span>
                      </div>
                      <div className="text-[11px] text-neutral-500">Instant app redirect & QR code scan</div>
                    </div>
                  </div>
                  <input
                    type="radio"
                    name="simPay"
                    checked={simPaymentMethod === 'tng_ewallet'}
                    onChange={() => setSimPaymentMethod('tng_ewallet')}
                    className="w-4 h-4 text-indigo-600 accent-indigo-600"
                  />
                </label>

                {/* Option 4: Atome BNPL */}
                <label
                  onClick={() => setSimPaymentMethod('atome')}
                  className={`p-3.5 rounded-xl border flex items-center justify-between cursor-pointer transition ${
                    simPaymentMethod === 'atome'
                      ? 'border-indigo-600 bg-indigo-50/40 dark:bg-indigo-950/40 ring-1 ring-indigo-500'
                      : 'border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900'
                  }`}
                >
                  <div className="flex items-center gap-3">
                    <PaymentLogo type="atome" size="sm" />
                    <div>
                      <div className="text-xs font-bold text-neutral-900 dark:text-white">Atome: 3x 0% Interest</div>
                      <div className="text-[11px] text-neutral-500">
                        Pay RM {(simCartTotal / 3).toFixed(2)} / month today
                      </div>
                    </div>
                  </div>
                  <input
                    type="radio"
                    name="simPay"
                    checked={simPaymentMethod === 'atome'}
                    onChange={() => setSimPaymentMethod('atome')}
                    className="w-4 h-4 text-indigo-600 accent-indigo-600"
                  />
                </label>

                {/* Option 5: Direct Bank Transfer */}
                <label
                  onClick={() => setSimPaymentMethod('manual_transfer')}
                  className={`p-3.5 rounded-xl border flex items-center justify-between cursor-pointer transition ${
                    simPaymentMethod === 'manual_transfer'
                      ? 'border-indigo-600 bg-indigo-50/40 dark:bg-indigo-950/40 ring-1 ring-indigo-500'
                      : 'border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900'
                  }`}
                >
                  <div className="flex items-center gap-3">
                    <PaymentLogo type="maybank" size="sm" />
                    <div>
                      <div className="text-xs font-bold text-neutral-900 dark:text-white">Manual Bank Transfer / DuitNow</div>
                      <div className="text-[11px] text-neutral-500">Transfer directly to company Maybank / CIMB</div>
                    </div>
                  </div>
                  <input
                    type="radio"
                    name="simPay"
                    checked={simPaymentMethod === 'manual_transfer'}
                    onChange={() => setSimPaymentMethod('manual_transfer')}
                    className="w-4 h-4 text-indigo-600 accent-indigo-600"
                  />
                </label>
              </div>

              {/* Dynamic Step for Bank Transfer (Receipt Upload) */}
              {simPaymentMethod === 'manual_transfer' && (
                <div className="p-4 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 space-y-3 animate-in fade-in">
                  <div className="text-xs font-bold text-neutral-900 dark:text-white flex items-center gap-1.5">
                    <QrCode className="w-4 h-4 text-indigo-600" />
                    Scan Maybank DuitNow QR or Transfer
                  </div>

                  <div className="flex items-center gap-3 p-2 rounded-lg bg-neutral-50 dark:bg-neutral-800">
                    <img
                      src={bankTransferConfig.bankAccounts[0]?.duitNowQrUrl || 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=DuitNowQR_AURORA'}
                      alt="QR"
                      referrerPolicy="no-referrer"
                      className="w-14 h-14 rounded bg-white p-0.5"
                    />
                    <div className="text-[11px] text-neutral-600 dark:text-neutral-400 space-y-0.5">
                      <div className="font-bold text-neutral-900 dark:text-white">{bankTransferConfig.bankAccounts[0]?.bankName}</div>
                      <div className="font-mono">{bankTransferConfig.bankAccounts[0]?.accountNumber}</div>
                      <div>{bankTransferConfig.bankAccounts[0]?.accountHolderName}</div>
                    </div>
                  </div>

                  <div className="p-3 rounded-lg border border-dashed border-neutral-300 dark:border-neutral-700 text-center">
                    <span className="text-[11px] text-neutral-500 block mb-1">
                      {simReceiptUploaded ? '✅ Receipt "maybank_transfer.jpg" attached' : 'Upload Bank Transfer Slip / Screenshot'}
                    </span>
                    <button
                      type="button"
                      onClick={() => setSimReceiptUploaded(true)}
                      className="text-xs font-semibold px-3 py-1 rounded-lg bg-neutral-100 dark:bg-neutral-800 hover:bg-neutral-200 text-neutral-800 dark:text-neutral-200 cursor-pointer"
                    >
                      {simReceiptUploaded ? 'Change File' : 'Browse / Upload Slip'}
                    </button>
                  </div>
                </div>
              )}

              {/* Pay Now Button */}
              <button
                type="button"
                onClick={() => {
                  setSimPaymentSuccess(true);
                  addToast({
                    type: 'success',
                    title: 'Simulator Payment Successful',
                    message: `Order simulated with ${simPaymentMethod} for RM ${simCartTotal.toFixed(2)}!`
                  });
                }}
                className="w-full py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md transition cursor-pointer flex items-center justify-center gap-2"
              >
                <span>Place Order & Pay RM {simCartTotal.toFixed(2)}</span>
                <ArrowRight className="w-4 h-4" />
              </button>

              {simPaymentSuccess && (
                <div className="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-300 dark:border-emerald-800 text-center space-y-1 animate-in zoom-in-95">
                  <div className="text-emerald-700 dark:text-emerald-300 font-bold text-xs flex items-center justify-center gap-1.5">
                    <CheckCircle2 className="w-4 h-4" />
                    Payment Authorized & Order Created
                  </div>
                  <p className="text-[11px] text-emerald-600 dark:text-emerald-400">
                    Webhook ping acknowledged. Customer notified via email and WhatsApp.
                  </p>
                </div>
              )}
            </div>
          </div>
        </div>
      )}

      {/* TAB 6: MDR FEE ANALYZER */}
      {activeTab === 'fee_calculator' && (
        <div className="space-y-6">
          <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-6 sm:p-8 shadow-sm space-y-6">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
              <div>
                <h2 className="text-xl font-bold text-neutral-900 dark:text-white flex items-center gap-2.5">
                  <Calculator className="w-6 h-6 text-indigo-600" />
                  Merchant Discount Rate (MDR) & Margin Analyzer
                </h2>
                <p className="text-xs text-neutral-500 mt-1">
                  Compare gateway commission fees and net settlement amounts per order to optimize store profitability.
                </p>
              </div>

              <div className="flex items-center gap-2">
                <span className="text-xs font-semibold text-neutral-500">Test Order Amount:</span>
                <div className="flex items-center gap-1">
                  <span className="text-xs font-bold text-neutral-400">RM</span>
                  <input
                    type="number"
                    value={calcOrderAmount}
                    onChange={e => setCalcOrderAmount(parseFloat(e.target.value) || 0)}
                    className="w-24 px-3 py-1.5 text-xs font-bold rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 font-mono"
                  />
                </div>
              </div>
            </div>

            {/* Comparison Table */}
            <div className="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-800">
              <table className="w-full text-left text-xs">
                <thead className="bg-neutral-50 dark:bg-neutral-800/60 text-neutral-500 uppercase text-[10px] font-bold">
                  <tr>
                    <th className="px-4 py-3">Payment Channel</th>
                    <th className="px-4 py-3">Fee Structure</th>
                    <th className="px-4 py-3">Total MDR Fee (RM)</th>
                    <th className="px-4 py-3">Net Merchant Payout (RM)</th>
                    <th className="px-4 py-3">Effective Margin (%)</th>
                    <th className="px-4 py-3">Settlement SLA</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800">
                  {/* Manual Transfer */}
                  <tr className="hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                    <td className="px-4 py-3.5 font-bold text-neutral-900 dark:text-white flex items-center gap-2.5">
                      <PaymentLogo type="duitnow" size="xs" />
                      <div>
                        <div>Direct Bank Transfer / DuitNow QR</div>
                        <span className="text-[10px] text-neutral-400 font-normal">Maybank, CIMB, Public Bank instant</span>
                      </div>
                    </td>
                    <td className="px-4 py-3.5 text-neutral-600 dark:text-neutral-400">RM 0.00 (0%)</td>
                    <td className="px-4 py-3.5 font-mono font-bold text-emerald-600">RM 0.00</td>
                    <td className="px-4 py-3.5 font-mono font-bold text-neutral-900 dark:text-white">
                      RM {calcOrderAmount.toFixed(2)}
                    </td>
                    <td className="px-4 py-3.5 text-emerald-600 font-bold">100.0%</td>
                    <td className="px-4 py-3.5 text-neutral-500">Instant / Direct</td>
                  </tr>

                  {/* Billplz */}
                  <tr className="hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                    <td className="px-4 py-3.5 font-bold text-neutral-900 dark:text-white flex items-center gap-2.5">
                      <PaymentLogo type="billplz" size="xs" />
                      <div>
                        <div>Billplz (FPX Direct Debit)</div>
                        <span className="text-[10px] text-neutral-400 font-normal">Malaysian FPX 23 Retail Banks</span>
                      </div>
                    </td>
                    <td className="px-4 py-3.5 text-neutral-600 dark:text-neutral-400">Fixed RM 1.50 / txn</td>
                    <td className="px-4 py-3.5 font-mono font-bold text-rose-600">RM 1.50</td>
                    <td className="px-4 py-3.5 font-mono font-bold text-neutral-900 dark:text-white">
                      RM {Math.max(0, calcOrderAmount - 1.50).toFixed(2)}
                    </td>
                    <td className="px-4 py-3.5 font-bold text-neutral-800 dark:text-neutral-200">
                      {(((calcOrderAmount - 1.50) / (calcOrderAmount || 1)) * 100).toFixed(1)}%
                    </td>
                    <td className="px-4 py-3.5 text-neutral-500">T+1 Business Day</td>
                  </tr>

                  {/* ToyyibPay */}
                  <tr className="hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                    <td className="px-4 py-3.5 font-bold text-neutral-900 dark:text-white flex items-center gap-2.5">
                      <PaymentLogo type="toyyibpay" size="xs" />
                      <div>
                        <div>ToyyibPay FPX Gateway</div>
                        <span className="text-[10px] text-neutral-400 font-normal">SME & Social Commerce</span>
                      </div>
                    </td>
                    <td className="px-4 py-3.5 text-neutral-600 dark:text-neutral-400">Fixed RM 1.00 / txn</td>
                    <td className="px-4 py-3.5 font-mono font-bold text-rose-600">RM 1.00</td>
                    <td className="px-4 py-3.5 font-mono font-bold text-neutral-900 dark:text-white">
                      RM {Math.max(0, calcOrderAmount - 1.00).toFixed(2)}
                    </td>
                    <td className="px-4 py-3.5 font-bold text-neutral-800 dark:text-neutral-200">
                      {(((calcOrderAmount - 1.00) / (calcOrderAmount || 1)) * 100).toFixed(1)}%
                    </td>
                    <td className="px-4 py-3.5 text-neutral-500">T+2 Business Days</td>
                  </tr>

                  {/* Curlec */}
                  <tr className="hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                    <td className="px-4 py-3.5 font-bold text-neutral-900 dark:text-white flex items-center gap-2.5">
                      <PaymentLogo type="curlec" size="xs" />
                      <div>
                        <div>Curlec by Razorpay</div>
                        <span className="text-[10px] text-neutral-400 font-normal">FPX & Mandate Subscriptions</span>
                      </div>
                    </td>
                    <td className="px-4 py-3.5 text-neutral-600 dark:text-neutral-400">Fixed RM 1.00 / txn</td>
                    <td className="px-4 py-3.5 font-mono font-bold text-rose-600">RM 1.00</td>
                    <td className="px-4 py-3.5 font-mono font-bold text-neutral-900 dark:text-white">
                      RM {Math.max(0, calcOrderAmount - 1.00).toFixed(2)}
                    </td>
                    <td className="px-4 py-3.5 font-bold text-neutral-800 dark:text-neutral-200">
                      {(((calcOrderAmount - 1.00) / (calcOrderAmount || 1)) * 100).toFixed(1)}%
                    </td>
                    <td className="px-4 py-3.5 text-neutral-500">T+1 Business Day</td>
                  </tr>

                  {/* SenangPay */}
                  <tr className="hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                    <td className="px-4 py-3.5 font-bold text-neutral-900 dark:text-white flex items-center gap-2.5">
                      <PaymentLogo type="senangpay" size="xs" />
                      <div>
                        <div>SenangPay (FPX + Cards)</div>
                        <span className="text-[10px] text-neutral-400 font-normal">Hybrid MDR Tier</span>
                      </div>
                    </td>
                    <td className="px-4 py-3.5 text-neutral-600 dark:text-neutral-400">1.50% + RM 0.65</td>
                    <td className="px-4 py-3.5 font-mono font-bold text-rose-600">
                      RM {(calcOrderAmount * 0.015 + 0.65).toFixed(2)}
                    </td>
                    <td className="px-4 py-3.5 font-mono font-bold text-neutral-900 dark:text-white">
                      RM {Math.max(0, calcOrderAmount - (calcOrderAmount * 0.015 + 0.65)).toFixed(2)}
                    </td>
                    <td className="px-4 py-3.5 font-bold text-neutral-800 dark:text-neutral-200">
                      {(((calcOrderAmount - (calcOrderAmount * 0.015 + 0.65)) / (calcOrderAmount || 1)) * 100).toFixed(1)}%
                    </td>
                    <td className="px-4 py-3.5 text-neutral-500">Weekly Wednesday</td>
                  </tr>

                  {/* Razer Merchant Services */}
                  <tr className="hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                    <td className="px-4 py-3.5 font-bold text-neutral-900 dark:text-white flex items-center gap-2.5">
                      <PaymentLogo type="razer_merchant" size="xs" />
                      <div>
                        <div>Razer Merchant Services (RMS)</div>
                        <span className="text-[10px] text-neutral-400 font-normal">Enterprise SEA Multi-channel</span>
                      </div>
                    </td>
                    <td className="px-4 py-3.5 text-neutral-600 dark:text-neutral-400">2.20%</td>
                    <td className="px-4 py-3.5 font-mono font-bold text-rose-600">
                      RM {(calcOrderAmount * 0.022).toFixed(2)}
                    </td>
                    <td className="px-4 py-3.5 font-mono font-bold text-neutral-900 dark:text-white">
                      RM {(calcOrderAmount * (1 - 0.022)).toFixed(2)}
                    </td>
                    <td className="px-4 py-3.5 font-bold text-neutral-800 dark:text-neutral-200">97.8%</td>
                    <td className="px-4 py-3.5 text-neutral-500">T+2 Business Days</td>
                  </tr>

                  {/* TNG eWallet */}
                  <tr className="hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                    <td className="px-4 py-3.5 font-bold text-neutral-900 dark:text-white flex items-center gap-2.5">
                      <PaymentLogo type="tng_ewallet" size="xs" />
                      <div>
                        <div>Touch 'n Go eWallet</div>
                        <span className="text-[10px] text-neutral-400 font-normal">Mobile In-App & QR Scan</span>
                      </div>
                    </td>
                    <td className="px-4 py-3.5 text-neutral-600 dark:text-neutral-400">1.50%</td>
                    <td className="px-4 py-3.5 font-mono font-bold text-rose-600">
                      RM {(calcOrderAmount * 0.015).toFixed(2)}
                    </td>
                    <td className="px-4 py-3.5 font-mono font-bold text-neutral-900 dark:text-white">
                      RM {(calcOrderAmount * (1 - 0.015)).toFixed(2)}
                    </td>
                    <td className="px-4 py-3.5 font-bold text-neutral-800 dark:text-neutral-200">98.5%</td>
                    <td className="px-4 py-3.5 text-neutral-500">T+1 Business Day</td>
                  </tr>

                  {/* GrabPay */}
                  <tr className="hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                    <td className="px-4 py-3.5 font-bold text-neutral-900 dark:text-white flex items-center gap-2.5">
                      <PaymentLogo type="grabpay" size="xs" />
                      <div>
                        <div>GrabPay & PayLater</div>
                        <span className="text-[10px] text-neutral-400 font-normal">Grab Rewards & Wallet</span>
                      </div>
                    </td>
                    <td className="px-4 py-3.5 text-neutral-600 dark:text-neutral-400">1.80%</td>
                    <td className="px-4 py-3.5 font-mono font-bold text-rose-600">
                      RM {(calcOrderAmount * 0.018).toFixed(2)}
                    </td>
                    <td className="px-4 py-3.5 font-mono font-bold text-neutral-900 dark:text-white">
                      RM {(calcOrderAmount * (1 - 0.018)).toFixed(2)}
                    </td>
                    <td className="px-4 py-3.5 font-bold text-neutral-800 dark:text-neutral-200">98.2%</td>
                    <td className="px-4 py-3.5 text-neutral-500">T+1 Business Day</td>
                  </tr>

                  {/* Stripe Card */}
                  <tr className="hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                    <td className="px-4 py-3.5 font-bold text-neutral-900 dark:text-white flex items-center gap-2.5">
                      <PaymentLogo type="stripe" size="xs" />
                      <div>
                        <div>Stripe (Cards & Apple/Google Pay)</div>
                        <span className="text-[10px] text-neutral-400 font-normal">Visa, Mastercard, 3DS2 Radar</span>
                      </div>
                    </td>
                    <td className="px-4 py-3.5 text-neutral-600 dark:text-neutral-400">3.00% + RM 1.00</td>
                    <td className="px-4 py-3.5 font-mono font-bold text-rose-600">
                      RM {(calcOrderAmount * 0.03 + 1.00).toFixed(2)}
                    </td>
                    <td className="px-4 py-3.5 font-mono font-bold text-neutral-900 dark:text-white">
                      RM {Math.max(0, calcOrderAmount - (calcOrderAmount * 0.03 + 1.00)).toFixed(2)}
                    </td>
                    <td className="px-4 py-3.5 font-bold text-neutral-800 dark:text-neutral-200">
                      {(((calcOrderAmount - (calcOrderAmount * 0.03 + 1.00)) / (calcOrderAmount || 1)) * 100).toFixed(1)}%
                    </td>
                    <td className="px-4 py-3.5 text-neutral-500">Rolling T+3 Days</td>
                  </tr>

                  {/* PayPal */}
                  <tr className="hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                    <td className="px-4 py-3.5 font-bold text-neutral-900 dark:text-white flex items-center gap-2.5">
                      <PaymentLogo type="paypal" size="xs" />
                      <div>
                        <div>PayPal Commerce Platform</div>
                        <span className="text-[10px] text-neutral-400 font-normal">Global Multi-currency Checkout</span>
                      </div>
                    </td>
                    <td className="px-4 py-3.5 text-neutral-600 dark:text-neutral-400">3.90% + RM 2.00</td>
                    <td className="px-4 py-3.5 font-mono font-bold text-rose-600">
                      RM {(calcOrderAmount * 0.039 + 2.00).toFixed(2)}
                    </td>
                    <td className="px-4 py-3.5 font-mono font-bold text-neutral-900 dark:text-white">
                      RM {Math.max(0, calcOrderAmount - (calcOrderAmount * 0.039 + 2.00)).toFixed(2)}
                    </td>
                    <td className="px-4 py-3.5 font-bold text-neutral-800 dark:text-neutral-200">
                      {(((calcOrderAmount - (calcOrderAmount * 0.039 + 2.00)) / (calcOrderAmount || 1)) * 100).toFixed(1)}%
                    </td>
                    <td className="px-4 py-3.5 text-neutral-500">Instant to Balance</td>
                  </tr>

                  {/* Atome BNPL */}
                  <tr className="hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                    <td className="px-4 py-3.5 font-bold text-neutral-900 dark:text-white flex items-center gap-2.5">
                      <PaymentLogo type="atome" size="xs" />
                      <div>
                        <div>Atome Buy Now Pay Later (BNPL 3x)</div>
                        <span className="text-[10px] text-neutral-400 font-normal">0% Interest 3-month split</span>
                      </div>
                    </td>
                    <td className="px-4 py-3.5 text-neutral-600 dark:text-neutral-400">4.50%</td>
                    <td className="px-4 py-3.5 font-mono font-bold text-rose-600">
                      RM {(calcOrderAmount * 0.045).toFixed(2)}
                    </td>
                    <td className="px-4 py-3.5 font-mono font-bold text-neutral-900 dark:text-white">
                      RM {(calcOrderAmount * (1 - 0.045)).toFixed(2)}
                    </td>
                    <td className="px-4 py-3.5 font-bold text-neutral-800 dark:text-neutral-200">95.5%</td>
                    <td className="px-4 py-3.5 text-neutral-500">Weekly Payout</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* Modals */}
      <PaymentGatewayModal
        isOpen={isGatewayModalOpen}
        onClose={() => setIsGatewayModalOpen(false)}
        gateway={selectedGateway}
        onSave={savePaymentGateway}
        onPing={pingPaymentGateway}
      />

      <BankAccountModal
        isOpen={isBankModalOpen}
        onClose={() => setIsBankModalOpen(false)}
        account={selectedBankAccount}
        onSave={saveBankAccount}
      />
    </div>
  );
};
