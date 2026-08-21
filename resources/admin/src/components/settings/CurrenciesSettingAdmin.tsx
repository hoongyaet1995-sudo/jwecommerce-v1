import React, { useState } from 'react';
import { useAdmin } from '../../context/AdminContext';
import { CurrencySetting } from '../../types/oscommerceSettings';
import {
  DollarSign,
  Plus,
  Edit2,
  CheckCircle2,
  RefreshCw,
  Star,
  Layers,
  Calculator,
  ArrowRight,
  TrendingUp,
  Sliders,
  Check,
  AlertCircle
} from 'lucide-react';

export const CurrenciesSettingAdmin: React.FC = () => {
  const {
    currenciesSetting,
    saveCurrencySetting,
    setDefaultCurrency,
    toggleCurrencyStatus,
    updateCurrencyRates,
    addToast
  } = useAdmin();

  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingCurrency, setEditingCurrency] = useState<CurrencySetting | null>(null);
  const [isUpdatingRates, setIsUpdatingRates] = useState(false);

  // FX Calculator Simulator state
  const [simBaseAmount, setSimBaseAmount] = useState<number>(100);

  // Form state
  const [formData, setFormData] = useState<Partial<CurrencySetting>>({
    code: '',
    title: '',
    symbolLeft: '',
    symbolRight: '',
    decimalPlaces: 2,
    exchangeRate: 1.0,
    isDefault: false,
    autoUpdate: true,
    isEnabled: true
  });

  const baseCurrency = currenciesSetting.find(c => c.isDefault) || currenciesSetting[0];

  const handleOpenAdd = () => {
    setEditingCurrency(null);
    setFormData({
      id: '',
      code: '',
      title: '',
      symbolLeft: '',
      symbolRight: '',
      decimalPlaces: 2,
      exchangeRate: 1.0,
      isDefault: false,
      autoUpdate: true,
      isEnabled: true
    });
    setIsModalOpen(true);
  };

  const handleOpenEdit = (curr: CurrencySetting) => {
    setEditingCurrency(curr);
    setFormData({ ...curr });
    setIsModalOpen(true);
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.code?.trim() || !formData.title?.trim()) {
      addToast({ type: 'error', title: 'Fields Required', message: 'Please enter Currency Code and Title.' });
      return;
    }

    const code = formData.code.trim().toUpperCase();
    const finalCurrency: CurrencySetting = {
      id: code,
      code,
      title: formData.title.trim(),
      symbolLeft: formData.symbolLeft || '',
      symbolRight: formData.symbolRight || '',
      decimalPlaces: Number(formData.decimalPlaces) ?? 2,
      exchangeRate: Number(formData.exchangeRate) || 1.0,
      isDefault: Boolean(formData.isDefault),
      autoUpdate: Boolean(formData.autoUpdate),
      lastUpdated: new Date().toISOString().replace('T', ' ').slice(0, 19) + ' (Manual)',
      isEnabled: Boolean(formData.isEnabled)
    };

    saveCurrencySetting(finalCurrency);
    setIsModalOpen(false);
  };

  const handleFetchLiveRates = () => {
    setIsUpdatingRates(true);
    setTimeout(() => {
      updateCurrencyRates();
      setIsUpdatingRates(false);
      addToast({
        type: 'success',
        title: 'Forex Feeds Synchronized',
        message: 'Successfully refreshed foreign exchange rates against base currency ' + baseCurrency.code
      });
    }, 800);
  };

  const formatCurrencyValue = (val: number, curr: CurrencySetting) => {
    const converted = val * curr.exchangeRate;
    const formatted = converted.toLocaleString(undefined, {
      minimumFractionDigits: curr.decimalPlaces,
      maximumFractionDigits: curr.decimalPlaces
    });
    return `${curr.symbolLeft}${formatted}${curr.symbolRight}`;
  };

  return (
    <div className="space-y-6">
      {/* Header Banner */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
        <div>
          <div className="flex items-center gap-2">
            <span className="px-2.5 py-0.5 text-[10px] font-bold uppercase rounded-full bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30">
              osCommerce Core Architecture
            </span>
            <span className="text-xs text-neutral-500">Multi-Currency & Forex Rate Engine</span>
          </div>
          <h2 className="text-lg sm:text-xl font-bold text-neutral-900 dark:text-white tracking-tight mt-1">
            Currencies Setting
          </h2>
          <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5 max-w-3xl">
            Configure multi-currency rates (osCommerce <code className="font-mono text-indigo-600 dark:text-indigo-400">currencies</code> table). Base currency is indexed to 1.00000000, while exchange multipliers govern real-time Storefront currency switching, invoice generation, and gateway settlement.
          </p>
        </div>

        <div className="flex items-center gap-2 self-start sm:self-auto">
          <button
            onClick={handleFetchLiveRates}
            disabled={isUpdatingRates}
            className="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 hover:bg-neutral-50 dark:hover:bg-neutral-700 text-neutral-800 dark:text-neutral-200 text-xs font-semibold shadow-xs transition cursor-pointer"
          >
            <RefreshCw className={`w-3.5 h-3.5 ${isUpdatingRates ? 'animate-spin text-indigo-600' : ''}`} />
            <span>Fetch Live FX Rates</span>
          </button>

          <button
            onClick={handleOpenAdd}
            className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs transition cursor-pointer"
          >
            <Plus className="w-4 h-4" />
            Add Currency
          </button>
        </div>
      </div>

      {/* Base Currency Callout & Live FX Simulator */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div className="p-5 rounded-2xl bg-indigo-50/70 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-900/60 space-y-3">
          <div className="flex items-center justify-between">
            <span className="text-[11px] font-bold uppercase tracking-wider text-indigo-700 dark:text-indigo-300 flex items-center gap-1.5">
              <Star className="w-4 h-4 text-amber-500 fill-amber-500" />
              Primary Store Base Currency
            </span>
            <span className="px-2 py-0.5 rounded-md bg-indigo-600 text-white text-[10px] font-bold">
              Base 1.00000000
            </span>
          </div>
          <div>
            <div className="text-2xl font-black text-neutral-900 dark:text-white">
              {baseCurrency.title} ({baseCurrency.code})
            </div>
            <div className="text-xs text-neutral-600 dark:text-neutral-400 mt-1 font-mono">
              Symbol: "{baseCurrency.symbolLeft}" • Decimals: {baseCurrency.decimalPlaces}
            </div>
          </div>
          <p className="text-[11px] text-neutral-500 dark:text-neutral-400 leading-relaxed">
            All catalog product prices, discount rules, and ledger calculations are stored natively in this base currency.
          </p>
        </div>

        {/* Live FX Simulator Playground */}
        <div className="lg:col-span-2 p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 space-y-3 shadow-xs">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <Calculator className="w-4 h-4 text-emerald-600" />
              <h3 className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider">
                Live Storefront Multi-Currency Simulator
              </h3>
            </div>
            <div className="flex items-center gap-1.5 text-xs">
              <span className="text-neutral-500">Test Product Base Price:</span>
              <div className="flex items-center gap-1 bg-neutral-100 dark:bg-neutral-800 px-2.5 py-1 rounded-lg border border-neutral-300 dark:border-neutral-700">
                <span className="font-bold text-neutral-700 dark:text-neutral-300">{baseCurrency.symbolLeft}</span>
                <input
                  type="number"
                  min={1}
                  value={simBaseAmount}
                  onChange={e => setSimBaseAmount(Number(e.target.value) || 0)}
                  className="w-16 bg-transparent text-xs font-bold text-neutral-900 dark:text-white outline-hidden font-mono"
                />
              </div>
            </div>
          </div>

          <div className="grid grid-cols-2 sm:grid-cols-4 gap-2.5 pt-1">
            {currenciesSetting.filter(c => c.isEnabled).slice(0, 8).map(curr => (
              <div
                key={curr.id}
                className={`p-2.5 rounded-xl border transition ${
                  curr.isDefault
                    ? 'bg-indigo-50/50 dark:bg-indigo-950/20 border-indigo-300 dark:border-indigo-800'
                    : 'bg-neutral-50 dark:bg-neutral-800/40 border-neutral-200 dark:border-neutral-700/60'
                }`}
              >
                <div className="flex items-center justify-between text-[10px] text-neutral-500 font-semibold">
                  <span>{curr.code}</span>
                  <span className="font-mono">x{curr.exchangeRate.toFixed(4)}</span>
                </div>
                <div className="text-sm font-bold text-neutral-900 dark:text-white mt-1 font-mono">
                  {formatCurrencyValue(simBaseAmount, curr)}
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Currencies Table */}
      <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 overflow-hidden shadow-xs">
        <div className="p-4 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
          <h3 className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider">
            Active Currency Matrix & FX Multipliers
          </h3>
          <span className="text-xs text-neutral-500">osCommerce 8-digit precision standard</span>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead>
              <tr className="bg-neutral-50 dark:bg-neutral-800/60 text-neutral-500 dark:text-neutral-400 font-semibold border-b border-neutral-200 dark:border-neutral-800">
                <th className="py-3 px-4">Currency Title</th>
                <th className="py-3 px-4">Code</th>
                <th className="py-3 px-4">Left Symbol</th>
                <th className="py-3 px-4">Right Symbol</th>
                <th className="py-3 px-4">Decimals</th>
                <th className="py-3 px-4">Exchange Rate Value</th>
                <th className="py-3 px-4">Last Rate Sync</th>
                <th className="py-3 px-4 text-center">Default</th>
                <th className="py-3 px-4 text-center">Status</th>
                <th className="py-3 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800">
              {currenciesSetting.map((curr) => (
                <tr key={curr.id} className="hover:bg-neutral-50/70 dark:hover:bg-neutral-800/40 transition-colors">
                  <td className="py-3.5 px-4 font-bold text-neutral-900 dark:text-white">
                    <div className="flex items-center gap-2">
                      <div className="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-xs font-mono">
                        {curr.code.slice(0, 2)}
                      </div>
                      <span>{curr.title}</span>
                    </div>
                  </td>
                  <td className="py-3.5 px-4 font-mono font-bold text-neutral-700 dark:text-neutral-300">
                    {curr.code}
                  </td>
                  <td className="py-3.5 px-4 font-mono text-neutral-700 dark:text-neutral-300">
                    {curr.symbolLeft || '—'}
                  </td>
                  <td className="py-3.5 px-4 font-mono text-neutral-700 dark:text-neutral-300">
                    {curr.symbolRight || '—'}
                  </td>
                  <td className="py-3.5 px-4 font-mono text-center text-neutral-700 dark:text-neutral-300">
                    {curr.decimalPlaces}
                  </td>
                  <td className="py-3.5 px-4 font-mono font-bold text-indigo-600 dark:text-indigo-400">
                    {curr.exchangeRate.toFixed(8)}
                  </td>
                  <td className="py-3.5 px-4 text-[11px] text-neutral-500">
                    {curr.lastUpdated}
                  </td>
                  <td className="py-3.5 px-4 text-center">
                    {curr.isDefault ? (
                      <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 border border-amber-300 dark:border-amber-800">
                        <Star className="w-2.5 h-2.5 fill-amber-500" /> Default Base
                      </span>
                    ) : (
                      <button
                        onClick={() => setDefaultCurrency(curr.id)}
                        className="text-[10px] font-semibold text-neutral-500 hover:text-indigo-600 dark:hover:text-indigo-400 underline cursor-pointer"
                      >
                        Set as Base
                      </button>
                    )}
                  </td>
                  <td className="py-3.5 px-4 text-center">
                    <button
                      onClick={() => toggleCurrencyStatus(curr.id)}
                      disabled={curr.isDefault}
                      className={`inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold border transition ${
                        curr.isEnabled
                          ? 'bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-950 dark:text-emerald-300 dark:border-emerald-800'
                          : 'bg-neutral-100 text-neutral-500 border-neutral-300 dark:bg-neutral-800 dark:text-neutral-400 dark:border-neutral-700'
                      }`}
                    >
                      {curr.isEnabled ? 'Enabled' : 'Disabled'}
                    </button>
                  </td>
                  <td className="py-3.5 px-4 text-right">
                    <button
                      onClick={() => handleOpenEdit(curr)}
                      className="p-1.5 text-neutral-500 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 rounded-lg transition"
                      title="Edit Currency Parameters"
                    >
                      <Edit2 className="w-3.5 h-3.5" />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Edit / Add Currency Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
          <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-3xl p-6 max-w-xl w-full shadow-2xl space-y-5 animate-in fade-in zoom-in-95 duration-150 my-8">
            <div className="flex items-center justify-between border-b border-neutral-200 dark:border-neutral-800 pb-3">
              <div>
                <h3 className="text-base font-bold text-neutral-900 dark:text-white">
                  {editingCurrency ? 'Edit Currency Parameters' : 'Add New Multi-Currency'}
                </h3>
                <p className="text-xs text-neutral-500">osCommerce currency configuration & precision format</p>
              </div>
              <button
                onClick={() => setIsModalOpen(false)}
                className="p-2 rounded-xl text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-xs font-bold"
              >
                ✕
              </button>
            </div>

            <form onSubmit={handleSubmit} className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <div className="space-y-1">
                  <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Currency Title *</label>
                  <input
                    type="text"
                    required
                    placeholder="e.g. Singapore Dollar"
                    value={formData.title || ''}
                    onChange={e => setFormData({ ...formData, title: e.target.value })}
                    className="w-full px-3 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs text-neutral-900 dark:text-white"
                  />
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">ISO Code (3-Letter) *</label>
                  <input
                    type="text"
                    maxLength={3}
                    required
                    placeholder="SGD"
                    value={formData.code || ''}
                    onChange={e => setFormData({ ...formData, code: e.target.value.toUpperCase() })}
                    className="w-full px-3 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-mono uppercase text-neutral-900 dark:text-white"
                  />
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div className="space-y-1">
                  <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Symbol Left</label>
                  <input
                    type="text"
                    placeholder="S$ or RM "
                    value={formData.symbolLeft || ''}
                    onChange={e => setFormData({ ...formData, symbolLeft: e.target.value })}
                    className="w-full px-3 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-mono text-neutral-900 dark:text-white"
                  />
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Symbol Right</label>
                  <input
                    type="text"
                    placeholder="e.g. € or CHF"
                    value={formData.symbolRight || ''}
                    onChange={e => setFormData({ ...formData, symbolRight: e.target.value })}
                    className="w-full px-3 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-mono text-neutral-900 dark:text-white"
                  />
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Decimal Places</label>
                  <input
                    type="number"
                    min={0}
                    max={4}
                    value={formData.decimalPlaces ?? 2}
                    onChange={e => setFormData({ ...formData, decimalPlaces: Number(e.target.value) })}
                    className="w-full px-3 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-mono text-neutral-900 dark:text-white"
                  />
                </div>
              </div>

              <div className="space-y-1.5 p-3.5 rounded-2xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700">
                <div className="flex items-center justify-between">
                  <label className="text-xs font-bold text-neutral-900 dark:text-white">Exchange Rate Value (vs Base Currency)</label>
                  <span className="text-[10px] text-neutral-500">8 Decimal Places Precision</span>
                </div>
                <input
                  type="number"
                  step="0.00000001"
                  required
                  value={formData.exchangeRate || 1.0}
                  onChange={e => setFormData({ ...formData, exchangeRate: Number(e.target.value) })}
                  className="w-full px-3 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-mono text-neutral-900 dark:text-white font-bold"
                />
              </div>

              <div className="flex flex-wrap gap-4 pt-1">
                <label className="flex items-center gap-2 text-xs text-neutral-700 dark:text-neutral-300 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={Boolean(formData.isEnabled)}
                    onChange={e => setFormData({ ...formData, isEnabled: e.target.checked })}
                    className="w-4 h-4 rounded text-indigo-600 accent-indigo-600 cursor-pointer"
                  />
                  <span>Active in Storefront Switcher</span>
                </label>

                <label className="flex items-center gap-2 text-xs text-neutral-700 dark:text-neutral-300 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={Boolean(formData.autoUpdate)}
                    onChange={e => setFormData({ ...formData, autoUpdate: e.target.checked })}
                    className="w-4 h-4 rounded text-indigo-600 accent-indigo-600 cursor-pointer"
                  />
                  <span>Auto-Update via Live Forex Webhook</span>
                </label>
              </div>

              <div className="flex items-center justify-end gap-2 pt-3 border-t border-neutral-200 dark:border-neutral-800">
                <button
                  type="button"
                  onClick={() => setIsModalOpen(false)}
                  className="px-4 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 text-xs font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs"
                >
                  Save Currency
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
