import React, { useState } from 'react';
import { useAdmin } from '../../context/AdminContext';
import {
  Truck,
  Plus,
  ExternalLink,
  ShieldCheck,
  Zap,
  CheckCircle2,
  XCircle,
  Sliders,
  DollarSign,
  Package,
  Layers,
  Sparkles,
  Activity
} from 'lucide-react';

export const CouriersSettingAdmin: React.FC = () => {
  const { courierConfigs, toggleCourierStatus, setCurrentSection, pingCourierAPI, addToast } = useAdmin();
  const [filterCategory, setFilterCategory] = useState<'all' | 'direct_courier' | 'aggregator' | 'on_demand'>('all');
  const [pingingId, setPingingId] = useState<string | null>(null);

  const couriersList = courierConfigs || [];

  const filteredCouriers = couriersList.filter(c => {
    if (filterCategory === 'all') return true;
    return c.category === filterCategory;
  });

  const handlePing = async (id: string, name: string) => {
    setPingingId(id);
    try {
      const latency = await pingCourierAPI(id);
      addToast({
        type: 'success',
        title: 'API Health OK',
        message: `${name} Open API responded in ${latency}ms.`
      });
    } catch {
      addToast({
        type: 'error',
        title: 'Connection Issue',
        message: `Failed to reach ${name} logistics gateway.`
      });
    } finally {
      setPingingId(null);
    }
  };

  return (
    <div className="space-y-6">
      {/* Header Banner */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
        <div>
          <div className="flex items-center gap-2">
            <span className="px-2.5 py-0.5 text-[10px] font-bold uppercase rounded-full bg-blue-500/20 text-blue-700 dark:text-blue-300 border border-blue-500/30">
              osCommerce Shipping Modules
            </span>
            <span className="text-xs text-neutral-500">Malaysia Carrier API & Rates Engine</span>
          </div>
          <h2 className="text-lg sm:text-xl font-bold text-neutral-900 dark:text-white tracking-tight mt-1">
            Couriers Setting
          </h2>
          <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5 max-w-3xl">
            Configure courier delivery modules (osCommerce shipping modules). Controls live rate calculation, API tracking webhooks, COD cash collection handling, and thermal waybill generation.
          </p>
        </div>

        <button
          onClick={() => setCurrentSection('shipping-malaysia')}
          className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs transition cursor-pointer self-start sm:self-auto"
        >
          <ExternalLink className="w-4 h-4" />
          Open Full Logistics Hub
        </button>
      </div>

      {/* Overview Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
        <div className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 space-y-1.5 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500">Integrated Carriers</span>
            <Truck className="w-4 h-4 text-indigo-500" />
          </div>
          <div className="text-2xl font-bold text-neutral-900 dark:text-white">{couriersList.length}</div>
          <p className="text-[11px] text-neutral-500">Active shipping carrier modules</p>
        </div>

        <div className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 space-y-1.5 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500">Active for Checkout</span>
            <CheckCircle2 className="w-4 h-4 text-emerald-500" />
          </div>
          <div className="text-2xl font-bold text-neutral-900 dark:text-white">
            {couriersList.filter(c => c.isEnabled).length} <span className="text-xs font-normal text-neutral-400">enabled</span>
          </div>
          <p className="text-[11px] text-neutral-500">Available on Storefront cart</p>
        </div>

        <div className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 space-y-1.5 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500">COD Cash Supported</span>
            <DollarSign className="w-4 h-4 text-amber-500" />
          </div>
          <div className="text-2xl font-bold text-neutral-900 dark:text-white">
            {couriersList.filter(c => c.supportsCOD).length} <span className="text-xs font-normal text-neutral-400">couriers</span>
          </div>
          <p className="text-[11px] text-neutral-500">Cash-on-Delivery collection</p>
        </div>
      </div>

      {/* Filter Tabs */}
      <div className="flex items-center gap-2 border-b border-neutral-200 dark:border-neutral-800 pb-3">
        {(['all', 'direct_courier', 'aggregator', 'on_demand'] as const).map(cat => (
          <button
            key={cat}
            onClick={() => setFilterCategory(cat)}
            className={`px-3 py-1.5 rounded-lg text-xs font-semibold capitalize transition ${
              filterCategory === cat
                ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300'
                : 'text-neutral-500 hover:text-neutral-900 dark:hover:text-white'
            }`}
          >
            {cat === 'all' ? 'All Carriers' : cat.replace('_', ' ')}
          </button>
        ))}
      </div>

      {/* Couriers Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {filteredCouriers.map((courier) => {
          const primaryService = courier.supportedServices?.[0];
          return (
            <div
              key={courier.id}
              className={`p-5 rounded-2xl border transition-all ${
                courier.isEnabled
                  ? 'bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-800 shadow-xs'
                  : 'bg-neutral-50/70 dark:bg-neutral-900/40 border-neutral-200 dark:border-neutral-800/60 opacity-75'
              }`}
            >
              <div className="flex items-start justify-between">
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 rounded-xl bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center font-bold text-neutral-800 dark:text-neutral-200 text-sm overflow-hidden">
                    {courier.logo ? (
                      <img src={courier.logo} alt={courier.name} className="w-full h-full object-cover" />
                    ) : (
                      (courier.displayName || courier.name || 'CR').slice(0, 2).toUpperCase()
                    )}
                  </div>
                  <div>
                    <h3 className="font-bold text-sm text-neutral-900 dark:text-white">{courier.displayName}</h3>
                    <span className="text-[11px] text-neutral-500 capitalize">{courier.category.replace('_', ' ')}</span>
                  </div>
                </div>

                <button
                  onClick={() => toggleCourierStatus(courier.id)}
                  className={`px-2 py-0.5 rounded-full text-[10px] font-bold border transition cursor-pointer ${
                    courier.isEnabled
                      ? 'bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-950 dark:text-emerald-300 dark:border-emerald-800'
                      : 'bg-neutral-200 text-neutral-600 border-neutral-300 dark:bg-neutral-800 dark:text-neutral-400 dark:border-neutral-700'
                  }`}
                >
                  {courier.isEnabled ? 'Active' : 'Disabled'}
                </button>
              </div>

              <div className="mt-4 pt-3 border-t border-neutral-100 dark:border-neutral-800 grid grid-cols-2 gap-2 text-xs">
                <div>
                  <span className="text-[10px] text-neutral-400 block">Base Rate:</span>
                  <span className="font-mono font-bold text-neutral-900 dark:text-white">
                    RM {primaryService ? primaryService.baseRateRM.toFixed(2) : '6.50'}
                  </span>
                </div>
                <div>
                  <span className="text-[10px] text-neutral-400 block">Estimated SLA:</span>
                  <span className="font-semibold text-neutral-700 dark:text-neutral-300">
                    {primaryService ? primaryService.estDays : '1-3 Days'}
                  </span>
                </div>
              </div>

              <div className="mt-3 flex items-center justify-between pt-2">
                <div className="flex items-center gap-1.5 flex-wrap">
                  {courier.supportsCOD && (
                    <span className="px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                      COD ({courier.codFeePercent}%)
                    </span>
                  )}
                  {courier.supportsEastMalaysia && (
                    <span className="px-1.5 py-0.5 rounded text-[9px] font-bold bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300">
                      Sabah/Sarawak
                    </span>
                  )}
                </div>

                <div className="flex items-center gap-2">
                  <button
                    onClick={() => handlePing(courier.id, courier.displayName)}
                    disabled={pingingId === courier.id}
                    title="Test Open API endpoint"
                    className="p-1.5 rounded-lg text-neutral-400 hover:text-indigo-600 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
                  >
                    <Activity className={`w-3.5 h-3.5 ${pingingId === courier.id ? 'animate-spin text-indigo-500' : ''}`} />
                  </button>

                  <button
                    onClick={() => setCurrentSection('shipping-malaysia')}
                    className="text-xs text-indigo-600 dark:text-indigo-400 font-semibold hover:underline flex items-center gap-1 cursor-pointer"
                  >
                    <span>Config</span>
                    <Sliders className="w-3 h-3" />
                  </button>
                </div>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
};

