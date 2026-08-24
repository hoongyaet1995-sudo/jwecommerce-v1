import React, { useState } from 'react';
import { 
  Plus, 
  Scale, 
  Layers, 
  ShoppingBag, 
  Calculator, 
  CheckCircle2, 
  Copy, 
  Edit3, 
  Trash2, 
  Truck, 
  Sparkles, 
  AlertCircle,
  HelpCircle,
  ArrowRight,
  RefreshCw,
  Search,
  Filter,
  DollarSign,
  ChevronRight
} from 'lucide-react';
import { useAdmin } from '../../context/AdminContext';
import { 
  GeneralShippingRate, 
  PostageCalculationMode, 
  CourierCode 
} from '../../types/shipping';
import { DeleteConfirmationModal } from '../common/DeleteConfirmationModal';

interface ShippingRatesTabProps {
  onOpenInsertModal: () => void;
  onOpenEditModal: (rate: GeneralShippingRate) => void;
}

export const ShippingRatesTab: React.FC<ShippingRatesTabProps> = ({
  onOpenInsertModal,
  onOpenEditModal
}) => {
  const {
    shippingRates = [],
    deleteShippingRate,
    toggleShippingRateStatus,
    duplicateShippingRate,
    calculateShippingRateBreakdown
  } = useAdmin();

  // Filters
  const [selectedModeFilter, setSelectedModeFilter] = useState<'all' | PostageCalculationMode>('all');
  const [selectedRegionFilter, setSelectedRegionFilter] = useState<'all' | 'peninsular' | 'east_malaysia'>('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [rateToDelete, setRateToDelete] = useState<GeneralShippingRate | null>(null);

  // Interactive Live Calculator State
  const [testRegion, setTestRegion] = useState<'peninsular' | 'east_malaysia'>('peninsular');
  const [testWeight, setTestWeight] = useState<number>(3.5);
  const [testQuantity, setTestQuantity] = useState<number>(4);
  const [testOrderAmount, setTestOrderAmount] = useState<number>(85.0);
  const [testCourier, setTestCourier] = useState<'all' | CourierCode>('all');

  // Filtered rate rules
  const filteredRates = shippingRates.filter(rate => {
    if (selectedModeFilter !== 'all' && rate.calculationMode !== selectedModeFilter) return false;
    if (selectedRegionFilter !== 'all') {
      if (selectedRegionFilter === 'peninsular' && rate.zoneRegion !== 'peninsular' && rate.zoneRegion !== 'all') return false;
      if (selectedRegionFilter === 'east_malaysia' && rate.zoneRegion !== 'east_malaysia' && rate.zoneRegion !== 'sabah' && rate.zoneRegion !== 'sarawak' && rate.zoneRegion !== 'all') return false;
    }
    if (searchQuery.trim()) {
      const q = searchQuery.toLowerCase();
      const matchName = rate.name.toLowerCase().includes(q);
      const matchNotes = (rate.notes || '').toLowerCase().includes(q);
      if (!matchName && !matchNotes) return false;
    }
    return true;
  });

  // Calculate live test result
  const testCalculation = calculateShippingRateBreakdown({
    region: testRegion,
    weightKg: testWeight,
    quantity: testQuantity,
    orderAmount: testOrderAmount,
    courierCode: testCourier
  });

  const weightRatesCount = shippingRates.filter(r => r.calculationMode === 'weight').length;
  const qtyRatesCount = shippingRates.filter(r => r.calculationMode === 'quantity').length;
  const orderRatesCount = shippingRates.filter(r => r.calculationMode === 'per_order').length;

  return (
    <div className="space-y-6">
      
      {/* Top Banner & Action Header */}
      <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-5 sm:p-6 shadow-xs">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div>
            <div className="flex items-center gap-2 text-indigo-600 dark:text-indigo-400 text-xs font-bold uppercase tracking-wider">
              <Scale className="w-3.5 h-3.5" />
              <span>Postage Matrix & Tier Engine</span>
            </div>
            <h2 className="text-xl font-bold text-neutral-900 dark:text-white mt-1">
              General Shipping Rates Configuration
            </h2>
            <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-1 max-w-2xl">
              Create and manage region shipping calculation rules by <strong>Weight (kg)</strong>, <strong>Quantity (pcs)</strong>, or <strong>Per Order</strong>. Set tier matrices (<code className="text-indigo-600 dark:text-indigo-400 font-mono text-[11px]">&gt; min to max = cost</code>), subsequent rate increments, and free shipping spend thresholds.
            </p>
          </div>

          <div className="flex items-center gap-2.5 flex-wrap">
            <button
              onClick={onOpenInsertModal}
              className="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs transition cursor-pointer shadow-sm"
            >
              <Plus className="w-4 h-4" />
              Insert Shipping Rate
            </button>
          </div>
        </div>

        {/* Quick Stats Chips */}
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-5 pt-5 border-t border-neutral-100 dark:border-neutral-800">
          <div className="p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700/60">
            <div className="text-[11px] text-neutral-500 font-medium">Total Rate Rules</div>
            <div className="text-lg font-black text-neutral-900 dark:text-white mt-0.5">{shippingRates.length}</div>
          </div>
          <div className="p-3 rounded-xl bg-indigo-50/60 dark:bg-indigo-950/30 border border-indigo-100 dark:border-indigo-900/50">
            <div className="text-[11px] text-indigo-700 dark:text-indigo-300 font-medium flex items-center gap-1">
              <Scale className="w-3 h-3" /> By Weight (kg)
            </div>
            <div className="text-lg font-black text-indigo-900 dark:text-indigo-200 mt-0.5">{weightRatesCount} Rules</div>
          </div>
          <div className="p-3 rounded-xl bg-emerald-50/60 dark:bg-emerald-950/30 border border-emerald-100 dark:border-emerald-900/50">
            <div className="text-[11px] text-emerald-700 dark:text-emerald-300 font-medium flex items-center gap-1">
              <Layers className="w-3 h-3" /> By Quantity (pcs)
            </div>
            <div className="text-lg font-black text-emerald-900 dark:text-emerald-200 mt-0.5">{qtyRatesCount} Rules</div>
          </div>
          <div className="p-3 rounded-xl bg-amber-50/60 dark:bg-amber-950/30 border border-amber-100 dark:border-amber-900/50">
            <div className="text-[11px] text-amber-700 dark:text-amber-300 font-medium flex items-center gap-1">
              <ShoppingBag className="w-3 h-3" /> Per Order Flat
            </div>
            <div className="text-lg font-black text-amber-900 dark:text-amber-200 mt-0.5">{orderRatesCount} Rules</div>
          </div>
        </div>
      </div>

      {/* Interactive Postage Fee Tester & Live Simulator */}
      <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-5 sm:p-6 shadow-xs">
        <div className="flex items-center justify-between gap-3 mb-4">
          <div className="flex items-center gap-2">
            <div className="w-8 h-8 rounded-xl bg-indigo-100 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
              <Calculator className="w-4 h-4" />
            </div>
            <div>
              <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                Live Postage Calculation Simulator & Rate Tester
              </h3>
              <p className="text-[11px] text-neutral-500">
                Simulate customer cart checkouts and test how your Weight/Quantity/Order rules calculate postage fees.
              </p>
            </div>
          </div>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-12 gap-5">
          {/* Simulator Inputs */}
          <div className="lg:col-span-7 grid grid-cols-2 sm:grid-cols-4 gap-3 bg-neutral-50 dark:bg-neutral-800/40 p-4 rounded-xl border border-neutral-200 dark:border-neutral-700/60">
            <div className="col-span-2 space-y-1">
              <label className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300">Destination Region</label>
              <select
                value={testRegion}
                onChange={(e) => setTestRegion(e.target.value as any)}
                className="w-full px-2.5 py-1.5 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-medium cursor-pointer"
              >
                <option value="peninsular">Peninsular Malaysia (West MY)</option>
                <option value="east_malaysia">East Malaysia (Sabah & Sarawak)</option>
              </select>
            </div>

            <div className="col-span-2 space-y-1">
              <label className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300">Courier Gateway</label>
              <select
                value={testCourier}
                onChange={(e) => setTestCourier(e.target.value as any)}
                className="w-full px-2.5 py-1.5 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-medium cursor-pointer"
              >
                <option value="all">All Couriers (Auto-Best)</option>
                <option value="posmalaysia">Pos Malaysia</option>
                <option value="jnt">J&T Express</option>
                <option value="ninjavan">Ninja Van</option>
                <option value="flashexpress">Flash Express</option>
                <option value="dhlecommerce">DHL eCommerce</option>
                <option value="easyparcel">EasyParcel</option>
              </select>
            </div>

            <div className="space-y-1">
              <label className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300">Order Weight</label>
              <div className="relative">
                <input
                  type="number"
                  step="0.1"
                  min="0.1"
                  value={testWeight}
                  onChange={(e) => setTestWeight(parseFloat(e.target.value) || 0)}
                  className="w-full pl-2.5 pr-7 py-1.5 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 font-mono text-xs font-bold"
                />
                <span className="absolute right-2 top-1.5 text-[10px] text-neutral-400 font-mono">kg</span>
              </div>
            </div>

            <div className="space-y-1">
              <label className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300">Total Items</label>
              <div className="relative">
                <input
                  type="number"
                  step="1"
                  min="1"
                  value={testQuantity}
                  onChange={(e) => setTestQuantity(parseInt(e.target.value) || 1)}
                  className="w-full pl-2.5 pr-7 py-1.5 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 font-mono text-xs font-bold"
                />
                <span className="absolute right-2 top-1.5 text-[10px] text-neutral-400 font-mono">pcs</span>
              </div>
            </div>

            <div className="col-span-2 space-y-1">
              <label className="text-[11px] font-semibold text-neutral-700 dark:text-neutral-300">Cart Total (Subtotal)</label>
              <div className="relative">
                <span className="absolute left-2.5 top-1.5 text-[11px] text-neutral-400 font-bold">RM</span>
                <input
                  type="number"
                  step="1"
                  min="0"
                  value={testOrderAmount}
                  onChange={(e) => setTestOrderAmount(parseFloat(e.target.value) || 0)}
                  className="w-full pl-9 pr-3 py-1.5 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 font-mono text-xs font-bold"
                />
              </div>
            </div>
          </div>

          {/* Simulator Result Output */}
          <div className="lg:col-span-5 flex flex-col justify-between p-4 rounded-xl bg-gradient-to-br from-indigo-900 via-indigo-950 to-neutral-900 text-white shadow-sm border border-indigo-800/40">
            <div>
              <div className="flex items-center justify-between text-[11px] text-indigo-300">
                <span>Calculated Shipping Fee</span>
                <span className="font-semibold px-2 py-0.5 rounded-full bg-indigo-500/20 text-indigo-200 border border-indigo-400/30">
                  {testCalculation.appliedRate?.calculationMode.toUpperCase() || 'STANDARD'} MODE
                </span>
              </div>

              <div className="mt-2 flex items-baseline gap-2">
                {testCalculation.isFreeShipping ? (
                  <div className="flex items-center gap-2">
                    <span className="text-3xl font-black text-emerald-400">FREE</span>
                    <span className="line-through text-neutral-400 text-sm">RM {(testCalculation.baseCost + testCalculation.subsequentCost).toFixed(2)}</span>
                  </div>
                ) : (
                  <div className="text-3xl font-black text-white">
                    RM {testCalculation.totalShippingFee.toFixed(2)}
                  </div>
                )}
              </div>

              <div className="mt-2 text-xs text-indigo-200 font-medium">
                Applied Rule: <strong className="text-white">{testCalculation.appliedRate?.name || 'Default System Rate'}</strong>
              </div>

              <div className="mt-2 p-2.5 rounded-lg bg-white/10 text-[11px] text-indigo-100 font-mono leading-relaxed">
                {testCalculation.breakdownSummary}
              </div>
            </div>

            <div className="mt-3 pt-3 border-t border-indigo-800/60 flex items-center justify-between text-[10px] text-indigo-300">
              <span>Transit: {testCalculation.appliedRate?.estimatedDays || '1-3 Days'}</span>
              <span>Free threshold: {testCalculation.appliedRate?.freeShippingThreshold ? `≥ RM ${testCalculation.appliedRate.freeShippingThreshold}` : 'None'}</span>
            </div>
          </div>
        </div>
      </div>

      {/* Filter Tabs & Search */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        {/* Mode Filter Pills */}
        <div className="flex items-center gap-1.5 overflow-x-auto p-1 bg-white dark:bg-neutral-900 rounded-xl border border-neutral-200 dark:border-neutral-800">
          <button
            onClick={() => setSelectedModeFilter('all')}
            className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer whitespace-nowrap ${
              selectedModeFilter === 'all'
                ? 'bg-indigo-600 text-white shadow-2xs'
                : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'
            }`}
          >
            All Modes ({shippingRates.length})
          </button>
          <button
            onClick={() => setSelectedModeFilter('weight')}
            className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer whitespace-nowrap flex items-center gap-1.5 ${
              selectedModeFilter === 'weight'
                ? 'bg-indigo-600 text-white shadow-2xs'
                : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'
            }`}
          >
            <Scale className="w-3.5 h-3.5" />
            Weight (kg) ({weightRatesCount})
          </button>
          <button
            onClick={() => setSelectedModeFilter('quantity')}
            className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer whitespace-nowrap flex items-center gap-1.5 ${
              selectedModeFilter === 'quantity'
                ? 'bg-indigo-600 text-white shadow-2xs'
                : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'
            }`}
          >
            <Layers className="w-3.5 h-3.5" />
            Quantity (pcs) ({qtyRatesCount})
          </button>
          <button
            onClick={() => setSelectedModeFilter('per_order')}
            className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer whitespace-nowrap flex items-center gap-1.5 ${
              selectedModeFilter === 'per_order'
                ? 'bg-indigo-600 text-white shadow-2xs'
                : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'
            }`}
          >
            <ShoppingBag className="w-3.5 h-3.5" />
            Per Order ({orderRatesCount})
          </button>
        </div>

        {/* Region & Search */}
        <div className="flex items-center gap-2">
          <select
            value={selectedRegionFilter}
            onChange={(e) => setSelectedRegionFilter(e.target.value as any)}
            className="px-3 py-1.5 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 text-xs font-medium text-neutral-700 dark:text-neutral-300 cursor-pointer"
          >
            <option value="all">All Regions</option>
            <option value="peninsular">West Malaysia Only</option>
            <option value="east_malaysia">East Malaysia Only</option>
          </select>

          <div className="relative">
            <Search className="w-3.5 h-3.5 absolute left-3 top-2.5 text-neutral-400" />
            <input
              type="text"
              placeholder="Search rates..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="pl-8 pr-3 py-1.5 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 text-xs text-neutral-900 dark:text-white"
            />
          </div>
        </div>
      </div>

      {/* Shipping Rate Cards Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {filteredRates.map((rate) => {
          const unit = rate.calculationMode === 'weight' ? 'kg' : rate.calculationMode === 'quantity' ? 'pcs' : 'order';
          const isWeight = rate.calculationMode === 'weight';
          const isQty = rate.calculationMode === 'quantity';

          return (
            <div
              key={rate.id}
              className={`bg-white dark:bg-neutral-900 rounded-2xl border p-5 transition shadow-xs flex flex-col justify-between ${
                rate.isActive 
                  ? 'border-neutral-200 dark:border-neutral-800 hover:border-indigo-300 dark:hover:border-indigo-700' 
                  : 'border-neutral-200 dark:border-neutral-800 opacity-60 bg-neutral-50/60 dark:bg-neutral-900/60'
              }`}
            >
              <div>
                {/* Header: Title + Mode Pill + Region */}
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <div className="flex items-center gap-2 flex-wrap">
                      <span className={`px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider ${
                        isWeight 
                          ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-300' 
                          : isQty 
                          ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300'
                          : 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300'
                      }`}>
                        {isWeight ? '⚖️ WEIGHT' : isQty ? '📦 QUANTITY' : '📋 PER ORDER'}
                      </span>

                      <span className="px-2 py-0.5 rounded-md bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 text-[10px] font-semibold">
                        {rate.zoneRegion === 'peninsular' ? 'West MY (Peninsular)' : rate.zoneRegion === 'east_malaysia' ? 'East MY (Sabah & Sarawak)' : rate.zoneRegion === 'sabah' ? 'Sabah Only' : rate.zoneRegion === 'sarawak' ? 'Sarawak Only' : 'All Malaysia'}
                      </span>

                      <span className="px-2 py-0.5 rounded-md bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 text-[10px] uppercase font-mono">
                        {rate.logisticType === 'all' ? 'All Couriers' : rate.logisticType.toUpperCase()}
                      </span>
                    </div>

                    <h3 className="text-base font-bold text-neutral-900 dark:text-white mt-1.5">
                      {rate.name}
                    </h3>
                  </div>

                  {/* Active Switch */}
                  <label className="relative inline-flex items-center cursor-pointer shrink-0">
                    <input
                      type="checkbox"
                      checked={rate.isActive}
                      onChange={() => toggleShippingRateStatus(rate.id)}
                      className="sr-only peer"
                    />
                    <div className="w-9 h-5 bg-neutral-300 peer-focus:outline-hidden rounded-full peer dark:bg-neutral-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-neutral-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-neutral-600 peer-checked:bg-indigo-600"></div>
                  </label>
                </div>

                {/* Tier Matrix Table */}
                {rate.calculationMode !== 'per_order' && rate.tiers && rate.tiers.length > 0 ? (
                  <div className="mt-4 bg-neutral-50 dark:bg-neutral-800/60 rounded-xl p-3 border border-neutral-200 dark:border-neutral-700/70">
                    <div className="text-[11px] font-bold text-neutral-700 dark:text-neutral-300 mb-2 flex items-center justify-between">
                      <span>Rate & Cost Tiers ({unit})</span>
                      <span className="text-neutral-400 font-normal">Step Matrix</span>
                    </div>

                    <div className="space-y-1.5">
                      {rate.tiers.map((t, idx) => (
                        <div key={t.id || idx} className="flex items-center justify-between text-xs py-1 px-2 rounded-lg bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700/60 font-mono">
                          <span className="text-neutral-600 dark:text-neutral-300 font-medium">
                            &gt; {t.min} to {t.max} {unit}
                          </span>
                          <span className="font-bold text-neutral-900 dark:text-white">
                            = RM {Number(t.cost).toFixed(2)}
                          </span>
                        </div>
                      ))}
                    </div>

                    {/* Subsequent charge info */}
                    {rate.subsequentRate !== undefined && rate.subsequentCost !== undefined && (
                      <div className="mt-2.5 pt-2 border-t border-neutral-200 dark:border-neutral-700 flex items-center justify-between text-[11px]">
                        <span className="text-neutral-500">Subsequent Rate:</span>
                        <span className="font-mono font-semibold text-indigo-600 dark:text-indigo-400">
                          +RM {Number(rate.subsequentCost).toFixed(2)} / every {rate.subsequentRate} {unit}
                        </span>
                      </div>
                    )}
                  </div>
                ) : (
                  <div className="mt-4 p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700 text-center">
                    <div className="text-xs font-semibold text-neutral-500">Flat Rate Per Order</div>
                    <div className="text-2xl font-black text-neutral-900 dark:text-white mt-1">
                      RM {Number(rate.flatRateCost || 0).toFixed(2)}
                    </div>
                  </div>
                )}

                {/* Additional Info Badges */}
                <div className="mt-3 flex items-center gap-2 flex-wrap text-[11px] text-neutral-500">
                  {rate.freeShippingThreshold && (
                    <span className="px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 font-medium">
                      ✓ Free over RM {rate.freeShippingThreshold}
                    </span>
                  )}
                  <span className="px-2 py-0.5 rounded-md bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400">
                    ⏱ {rate.estimatedDays || '1-3 Days'}
                  </span>
                </div>

                {rate.notes && (
                  <p className="text-[11px] text-neutral-500 mt-2 line-clamp-1 italic">
                    "{rate.notes}"
                  </p>
                )}
              </div>

              {/* Action Buttons */}
              <div className="mt-5 pt-3 border-t border-neutral-100 dark:border-neutral-800 flex items-center justify-between">
                <span className="text-[10px] text-neutral-400 font-mono">
                  ID: {rate.id}
                </span>

                <div className="flex items-center gap-1.5">
                  <button
                    onClick={() => duplicateShippingRate(rate.id)}
                    className="p-1.5 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 hover:bg-neutral-50 dark:hover:bg-neutral-700 text-neutral-600 dark:text-neutral-300 transition cursor-pointer"
                    title="Duplicate rate rule"
                  >
                    <Copy className="w-3.5 h-3.5" />
                  </button>

                  <button
                    onClick={() => onOpenEditModal(rate)}
                    className="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 hover:bg-neutral-50 dark:hover:bg-neutral-700 text-neutral-800 dark:text-neutral-200 text-xs font-semibold transition cursor-pointer"
                  >
                    <Edit3 className="w-3.5 h-3.5" />
                    Edit
                  </button>

                  <button
                    onClick={() => setRateToDelete(rate)}
                    className="p-1.5 rounded-lg text-neutral-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition cursor-pointer"
                    title="Delete rate rule"
                  >
                    <Trash2 className="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>
            </div>
          );
        })}
      </div>

      {filteredRates.length === 0 && (
        <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-12 text-center space-y-3">
          <div className="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center mx-auto">
            <Scale className="w-6 h-6" />
          </div>
          <h3 className="text-base font-bold text-neutral-900 dark:text-white">No shipping rates found</h3>
          <p className="text-xs text-neutral-500 max-w-sm mx-auto">
            No postage rate rules match your selected filters. Create a new Weight, Quantity, or Per Order rate rule to get started.
          </p>
          <button
            onClick={onOpenInsertModal}
            className="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition cursor-pointer"
          >
            <Plus className="w-4 h-4" />
            Insert Shipping Rate
          </button>
        </div>
      )}

      <DeleteConfirmationModal isOpen={!!rateToDelete} itemType="Shipping Rate Rule" itemName={rateToDelete?.name} itemDetails={rateToDelete ? `Mode: ${rateToDelete.calculationMode.toUpperCase()} • Region: ${rateToDelete.zoneRegion} • Base: RM ${(rateToDelete.flatRateCost ?? rateToDelete.tiers[0]?.cost ?? 0).toFixed(2)}` : undefined} warningMessage={`Deleting "${rateToDelete?.name}" removes this postage calculation tier.`} impactItems={['Checkout will no longer calculate shipping from this rate.', 'Existing order shipping snapshots remain unchanged.']} confirmButtonText="Delete Shipping Rate" onConfirm={() => { if (rateToDelete) { deleteShippingRate(rateToDelete.id); setRateToDelete(null); } }} onClose={() => setRateToDelete(null)} />
    </div>
  );
};
