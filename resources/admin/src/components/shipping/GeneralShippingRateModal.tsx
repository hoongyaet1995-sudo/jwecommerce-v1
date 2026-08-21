import React, { useState, useEffect } from 'react';
import { 
  X, 
  Plus, 
  Trash2, 
  Scale, 
  Layers, 
  ShoppingBag, 
  HelpCircle, 
  ArrowLeft,
  Truck,
  Sparkles,
  CheckCircle2,
  DollarSign
} from 'lucide-react';
import { 
  GeneralShippingRate, 
  ShippingRateTier, 
  PostageCalculationMode, 
  RateType, 
  CourierCode 
} from '../../types/shipping';

interface GeneralShippingRateModalProps {
  isOpen: boolean;
  onClose: () => void;
  rate?: GeneralShippingRate | null;
  onSave: (rate: GeneralShippingRate) => void;
}

export const GeneralShippingRateModal: React.FC<GeneralShippingRateModalProps> = ({
  isOpen,
  onClose,
  rate,
  onSave,
}) => {
  const isEditing = Boolean(rate);

  // Form State
  const [name, setName] = useState('West Malaysia Standard Ground Rate');
  const [zoneRegion, setZoneRegion] = useState<GeneralShippingRate['zoneRegion']>('peninsular');
  const [logisticType, setLogisticType] = useState<'all' | CourierCode>('all');
  const [calculationMode, setCalculationMode] = useState<PostageCalculationMode>('weight');
  const [rateType, setRateType] = useState<RateType>('custom');
  
  // Tier rows (> min to max = cost)
  const [tiers, setTiers] = useState<ShippingRateTier[]>([
    { id: 'tier_1', min: 0, max: 2, cost: 8 },
    { id: 'tier_2', min: 2, max: 8, cost: 12 },
    { id: 'tier_3', min: 8, max: 20, cost: 20 },
  ]);

  // Subsequent Unit & Cost
  const [subsequentRate, setSubsequentRate] = useState<string>('1.0');
  const [subsequentCost, setSubsequentCost] = useState<string>('2.0');

  // Flat rate (when mode is per_order or flat)
  const [flatRateCost, setFlatRateCost] = useState<string>('8.0');

  // Free shipping threshold
  const [freeShippingThreshold, setFreeShippingThreshold] = useState<string>('120');

  // Est Days & Notes
  const [estimatedDays, setEstimatedDays] = useState<string>('1 - 3 Working Days');
  const [notes, setNotes] = useState<string>('');
  const [isActive, setIsActive] = useState<boolean>(true);

  // Errors state
  const [errorMsg, setErrorMsg] = useState<string>('');

  useEffect(() => {
    if (rate) {
      setName(rate.name || '');
      setZoneRegion(rate.zoneRegion || 'peninsular');
      setLogisticType(rate.logisticType || 'all');
      setCalculationMode(rate.calculationMode || 'weight');
      setRateType(rate.rateType || 'custom');
      setTiers(rate.tiers && rate.tiers.length > 0 ? rate.tiers : [
        { id: 'tier_1', min: 0, max: 2, cost: 8 },
        { id: 'tier_2', min: 2, max: 8, cost: 12 },
      ]);
      setSubsequentRate(rate.subsequentRate !== undefined ? String(rate.subsequentRate) : '');
      setSubsequentCost(rate.subsequentCost !== undefined ? String(rate.subsequentCost) : '');
      setFlatRateCost(rate.flatRateCost !== undefined ? String(rate.flatRateCost) : '8.0');
      setFreeShippingThreshold(rate.freeShippingThreshold !== undefined ? String(rate.freeShippingThreshold) : '');
      setEstimatedDays(rate.estimatedDays || '1 - 3 Working Days');
      setNotes(rate.notes || '');
      setIsActive(rate.isActive ?? true);
    } else {
      // Default reset
      setName('General Regional Shipping Rate');
      setZoneRegion('peninsular');
      setLogisticType('all');
      setCalculationMode('weight');
      setRateType('custom');
      setTiers([
        { id: 'tier_1', min: 0, max: 2, cost: 8 },
        { id: 'tier_2', min: 2, max: 8, cost: 12 },
        { id: 'tier_3', min: 8, max: 20, cost: 20 },
      ]);
      setSubsequentRate('1.0');
      setSubsequentCost('2.0');
      setFlatRateCost('8.0');
      setFreeShippingThreshold('120');
      setEstimatedDays('1 - 3 Working Days');
      setNotes('');
      setIsActive(true);
    }
    setErrorMsg('');
  }, [rate, isOpen]);

  if (!isOpen) return null;

  // Add a new tier
  const handleAddTier = () => {
    const lastTier = tiers[tiers.length - 1];
    const newMin = lastTier ? lastTier.max : 0;
    const step = calculationMode === 'weight' ? 5 : 5;
    const newMax = newMin + step;
    const newCost = lastTier ? lastTier.cost + 5 : 10;

    const newTier: ShippingRateTier = {
      id: `tier_${Date.now()}_${Math.random().toString(36).substr(2, 4)}`,
      min: newMin,
      max: newMax,
      cost: newCost
    };
    setTiers([...tiers, newTier]);
  };

  // Remove tier
  const handleRemoveTier = (index: number) => {
    if (tiers.length <= 1) {
      setErrorMsg('At least one rate tier is required for tiered calculation.');
      return;
    }
    const updated = tiers.filter((_, i) => i !== index);
    setTiers(updated);
  };

  // Update tier fields
  const handleTierChange = (index: number, field: 'min' | 'max' | 'cost', value: number) => {
    const updated = [...tiers];
    updated[index] = {
      ...updated[index],
      [field]: isNaN(value) ? 0 : value
    };
    setTiers(updated);
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim()) {
      setErrorMsg('Please enter a descriptive rate title.');
      return;
    }

    if (calculationMode !== 'per_order' && tiers.length === 0) {
      setErrorMsg('Please add at least one rate tier.');
      return;
    }

    const payload: GeneralShippingRate = {
      id: rate?.id || `rate_${Date.now()}`,
      name: name.trim(),
      zoneRegion,
      logisticType,
      calculationMode,
      rateType,
      tiers: calculationMode === 'per_order' ? [] : tiers.map(t => ({
        id: t.id,
        min: Number(t.min) || 0,
        max: Number(t.max) || 0,
        cost: Number(t.cost) || 0
      })),
      subsequentRate: subsequentRate ? parseFloat(subsequentRate) : undefined,
      subsequentCost: subsequentCost ? parseFloat(subsequentCost) : undefined,
      flatRateCost: flatRateCost ? parseFloat(flatRateCost) : undefined,
      freeShippingThreshold: freeShippingThreshold ? parseFloat(freeShippingThreshold) : undefined,
      estimatedDays: estimatedDays.trim() || '1 - 3 Working Days',
      isActive,
      notes: notes.trim(),
      createdAt: rate?.createdAt || new Date().toISOString().replace('T', ' ').slice(0, 19),
      updatedAt: new Date().toISOString().replace('T', ' ').slice(0, 19),
    };

    onSave(payload);
  };

  const unitLabel = calculationMode === 'weight' ? 'kg' : calculationMode === 'quantity' ? 'pcs' : 'order';

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 animate-in fade-in duration-150">
      <div className="bg-white dark:bg-neutral-900 rounded-2xl max-w-2xl w-full shadow-2xl border border-neutral-200 dark:border-neutral-800 overflow-hidden flex flex-col max-h-[92vh]">
        
        {/* Header (Matching Uploaded Screenshot Header) */}
        <div className="px-6 pt-6 pb-4 border-b border-neutral-100 dark:border-neutral-800">
          <div className="flex items-center justify-between gap-3">
            <button
              type="button"
              onClick={onClose}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 hover:bg-neutral-50 dark:hover:bg-neutral-700 text-xs font-semibold text-neutral-700 dark:text-neutral-200 transition cursor-pointer"
            >
              <ArrowLeft className="w-3.5 h-3.5" />
              Back
            </button>
            <button
              onClick={onClose}
              className="p-1 rounded-lg text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition cursor-pointer"
            >
              <X className="w-5 h-5" />
            </button>
          </div>

          <div className="mt-3">
            <h2 className="text-xl font-bold text-neutral-900 dark:text-white">
              {isEditing ? 'Edit General Shipping Rate' : 'General Shipping Shipping Rate Insert'}
            </h2>
            <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
              Fill in below fields to create your new region shipping rate.
            </p>
          </div>
        </div>

        {/* Scrollable Form Body */}
        <form id="shipping-rate-form" onSubmit={handleSubmit} className="p-6 overflow-y-auto space-y-5 flex-1 text-xs">
          {errorMsg && (
            <div className="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-xs flex items-center gap-2">
              <span className="font-bold">Notice:</span> {errorMsg}
            </div>
          )}

          {/* Rate Title / Name */}
          <div className="space-y-1.5">
            <label className="font-semibold text-neutral-800 dark:text-neutral-200 block">
              Shipping Rate Title / Description
            </label>
            <input
              type="text"
              required
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="e.g. West Malaysia Heavy Freight / Apparel Rate"
              className="w-full px-3.5 py-2.5 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white focus:ring-2 focus:ring-indigo-500 text-xs"
            />
          </div>

          {/* Region / Target Zone */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <label className="font-semibold text-neutral-800 dark:text-neutral-200 block">
                Destination Region / Zone
              </label>
              <select
                value={zoneRegion}
                onChange={(e) => setZoneRegion(e.target.value as any)}
                className="w-full px-3.5 py-2.5 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white focus:ring-2 focus:ring-indigo-500 text-xs cursor-pointer"
              >
                <option value="peninsular">Peninsular Malaysia (West MY)</option>
                <option value="east_malaysia">East Malaysia (Sabah & Sarawak)</option>
                <option value="sabah">Sabah Only</option>
                <option value="sarawak">Sarawak Only</option>
                <option value="all">All Malaysia Postcodes</option>
                <option value="international">International Air Zone</option>
              </select>
            </div>

            {/* Logistic Type (1:1 with Screenshot) */}
            <div className="space-y-1.5">
              <label className="font-semibold text-neutral-800 dark:text-neutral-200 block">
                Logistic Type
              </label>
              <select
                value={logisticType}
                onChange={(e) => setLogisticType(e.target.value as any)}
                className="w-full px-3.5 py-2.5 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white focus:ring-2 focus:ring-indigo-500 text-xs cursor-pointer"
              >
                <option value="all">All Couriers (Default Standard)</option>
                <option value="posmalaysia">Pos Malaysia</option>
                <option value="jnt">J&T Express Malaysia</option>
                <option value="ninjavan">Ninja Van Malaysia</option>
                <option value="flashexpress">Flash Express</option>
                <option value="dhlecommerce">DHL eCommerce</option>
                <option value="easyparcel">EasyParcel Multi-Carrier</option>
                <option value="delyva">Delyva Aggregator</option>
              </select>
            </div>
          </div>

          {/* Postage Calculation Mode & Rate Type (1:1 with Screenshot) */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <label className="font-semibold text-neutral-800 dark:text-neutral-200 flex items-center justify-between">
                <span>Postage Calculation Mode</span>
                <span className="text-[10px] text-indigo-600 dark:text-indigo-400 font-normal">
                  {calculationMode === 'weight' ? '⚖️ By Weight (kg)' : calculationMode === 'quantity' ? '📦 By Item Count (pcs)' : '📋 Flat Per Order'}
                </span>
              </label>
              <select
                value={calculationMode}
                onChange={(e) => setCalculationMode(e.target.value as PostageCalculationMode)}
                className="w-full px-3.5 py-2.5 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white font-medium focus:ring-2 focus:ring-indigo-500 text-xs cursor-pointer"
              >
                <option value="weight">Weight (kg) — 按重量计费</option>
                <option value="quantity">Quantity (pcs) — 按件数/数量计费</option>
                <option value="per_order">Per order — 按每笔订单固定计费</option>
              </select>
            </div>

            <div className="space-y-1.5">
              <label className="font-semibold text-neutral-800 dark:text-neutral-200 block">
                Rate Type
              </label>
              <select
                value={rateType}
                onChange={(e) => setRateType(e.target.value as RateType)}
                className="w-full px-3.5 py-2.5 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white focus:ring-2 focus:ring-indigo-500 text-xs cursor-pointer"
              >
                <option value="custom">Custom (Tiered Range + Subsequent)</option>
                <option value="tiered">Tiered Range Only</option>
                <option value="flat">Flat Rate</option>
              </select>
            </div>
          </div>

          {/* Conditional Per-Order Flat Rate */}
          {calculationMode === 'per_order' ? (
            <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700 space-y-3">
              <div className="flex items-center gap-2 text-indigo-600 dark:text-indigo-400 font-bold text-xs">
                <DollarSign className="w-4 h-4" />
                <span>Flat Rate Fee Per Order</span>
              </div>
              <div className="flex items-center gap-3">
                <span className="text-neutral-600 dark:text-neutral-300 font-bold">Shipping Fee: RM</span>
                <input
                  type="number"
                  step="0.1"
                  min="0"
                  value={flatRateCost}
                  onChange={(e) => setFlatRateCost(e.target.value)}
                  className="w-32 px-3 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white text-xs font-bold"
                  placeholder="8.00"
                />
                <span className="text-neutral-500 text-[11px]">flat per order regardless of weight/quantity</span>
              </div>
            </div>
          ) : (
            /* Rate & Cost Section (Exact reproduction of screenshot) */
            <div className="space-y-3 pt-2">
              <div className="flex items-center justify-between">
                <label className="font-bold text-neutral-900 dark:text-white text-xs flex items-center gap-1.5">
                  <span>Rate & Cost</span>
                  <span className="text-neutral-400 font-normal">({calculationMode === 'weight' ? 'Weight in kg' : 'Item quantity in pieces'})</span>
                </label>
                <button
                  type="button"
                  onClick={handleAddTier}
                  className="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-[11px] font-semibold transition cursor-pointer shadow-2xs"
                >
                  <Plus className="w-3.5 h-3.5" />
                  New Rate +
                </button>
              </div>

              {/* Tier Column Headers */}
              <div className="grid grid-cols-12 gap-2 text-[11px] font-semibold text-neutral-500 dark:text-neutral-400 px-1">
                <div className="col-span-7">Rate ({unitLabel} Range)</div>
                <div className="col-span-4">Cost (RM)</div>
                <div className="col-span-1 text-center"></div>
              </div>

              {/* Dynamic Rate Tier Rows */}
              <div className="space-y-2.5">
                {tiers.map((tier, index) => (
                  <div 
                    key={tier.id}
                    className="flex items-center gap-2 bg-neutral-50 dark:bg-neutral-800/70 p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700/80"
                  >
                    {/* Range: > [min] to [max] */}
                    <div className="flex items-center gap-1.5 flex-1 min-w-0">
                      <span className="text-neutral-400 font-bold text-xs">&gt;</span>
                      <input
                        type="number"
                        step={calculationMode === 'weight' ? '0.1' : '1'}
                        min="0"
                        value={tier.min}
                        onChange={(e) => handleTierChange(index, 'min', parseFloat(e.target.value))}
                        className="w-16 sm:w-20 px-2.5 py-1.5 rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-center font-mono text-xs text-neutral-900 dark:text-white"
                      />
                      <span className="text-neutral-500 font-medium text-xs">to</span>
                      <input
                        type="number"
                        step={calculationMode === 'weight' ? '0.1' : '1'}
                        min="0"
                        value={tier.max}
                        onChange={(e) => handleTierChange(index, 'max', parseFloat(e.target.value))}
                        className="w-16 sm:w-20 px-2.5 py-1.5 rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-center font-mono text-xs text-neutral-900 dark:text-white font-bold text-indigo-600 dark:text-indigo-400"
                      />
                      <span className="text-neutral-400 text-[11px] font-mono hidden sm:inline">{unitLabel}</span>
                    </div>

                    {/* Equals Sign */}
                    <span className="text-neutral-400 font-bold text-xs">=</span>

                    {/* Cost Input */}
                    <div className="w-24 sm:w-28 flex items-center">
                      <input
                        type="number"
                        step="0.1"
                        min="0"
                        value={tier.cost}
                        onChange={(e) => handleTierChange(index, 'cost', parseFloat(e.target.value))}
                        className="w-full px-2.5 py-1.5 rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 font-mono text-xs font-bold text-neutral-900 dark:text-white text-right"
                        placeholder="8.00"
                      />
                    </div>

                    {/* Delete Tier Button */}
                    <button
                      type="button"
                      onClick={() => handleRemoveTier(index)}
                      className="p-1.5 text-neutral-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg transition cursor-pointer"
                      title="Delete rate tier"
                    >
                      <Trash2 className="w-4 h-4" />
                    </button>
                  </div>
                ))}
              </div>

              {/* Bottom New Rate + Button (as shown in screenshot) */}
              <div className="pt-1">
                <button
                  type="button"
                  onClick={handleAddTier}
                  className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition cursor-pointer shadow-xs"
                >
                  <Plus className="w-4 h-4" />
                  New Rate +
                </button>
              </div>
            </div>
          )}

          {/* Subsequent Rate & Cost (1:1 with Screenshot) */}
          {calculationMode !== 'per_order' && (
            <div className="space-y-4 pt-2 border-t border-neutral-100 dark:border-neutral-800">
              <div className="space-y-1.5">
                <label className="font-semibold text-neutral-800 dark:text-neutral-200 flex items-center justify-between">
                  <span>Subsequent Rate</span>
                  <span className="text-neutral-400 text-[11px] font-normal">
                    Increment step (e.g. Every 1.0 {unitLabel})
                  </span>
                </label>
                <input
                  type="number"
                  step={calculationMode === 'weight' ? '0.1' : '1'}
                  min="0"
                  value={subsequentRate}
                  onChange={(e) => setSubsequentRate(e.target.value)}
                  placeholder={`e.g. 1.0 (${unitLabel})`}
                  className="w-full px-3.5 py-2.5 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white text-xs"
                />
              </div>

              <div className="space-y-1.5">
                <label className="font-semibold text-neutral-800 dark:text-neutral-200 flex items-center justify-between">
                  <span>Subsequent Cost</span>
                  <span className="text-neutral-400 text-[11px] font-normal">
                    Additional fee (e.g. +RM 2.00 per step)
                  </span>
                </label>
                <input
                  type="number"
                  step="0.1"
                  min="0"
                  value={subsequentCost}
                  onChange={(e) => setSubsequentCost(e.target.value)}
                  placeholder="e.g. 2.00 (RM)"
                  className="w-full px-3.5 py-2.5 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white text-xs font-medium"
                />
              </div>
            </div>
          )}

          {/* Optional Extra Settings: Free Shipping & Days */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-neutral-100 dark:border-neutral-800">
            <div className="space-y-1.5">
              <label className="font-semibold text-neutral-800 dark:text-neutral-200 flex items-center justify-between">
                <span>Free Shipping Threshold (RM)</span>
                <span className="text-emerald-600 dark:text-emerald-400 text-[10px]">Optional</span>
              </label>
              <input
                type="number"
                step="1"
                min="0"
                value={freeShippingThreshold}
                onChange={(e) => setFreeShippingThreshold(e.target.value)}
                placeholder="e.g. 120 (Free for orders ≥ RM 120)"
                className="w-full px-3.5 py-2.5 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white text-xs"
              />
            </div>

            <div className="space-y-1.5">
              <label className="font-semibold text-neutral-800 dark:text-neutral-200 block">
                Estimated Transit Duration
              </label>
              <input
                type="text"
                value={estimatedDays}
                onChange={(e) => setEstimatedDays(e.target.value)}
                placeholder="e.g. 1 - 3 Working Days"
                className="w-full px-3.5 py-2.5 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white text-xs"
              />
            </div>
          </div>

          {/* Active Status Switch */}
          <div className="flex items-center justify-between p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700">
            <div>
              <div className="font-semibold text-neutral-900 dark:text-white text-xs">Enable This Rate Rule</div>
              <div className="text-[11px] text-neutral-500">When enabled, store checkout and rate quote APIs will apply this calculation.</div>
            </div>
            <label className="relative inline-flex items-center cursor-pointer">
              <input
                type="checkbox"
                checked={isActive}
                onChange={(e) => setIsActive(e.target.checked)}
                className="sr-only peer"
              />
              <div className="w-10 h-5 bg-neutral-300 peer-focus:outline-hidden rounded-full peer dark:bg-neutral-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-neutral-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-neutral-600 peer-checked:bg-indigo-600"></div>
            </label>
          </div>
        </form>

        {/* Action Buttons (1:1 with Screenshot: [Insert Shipping Rate] [Back]) */}
        <div className="px-6 py-4 border-t border-neutral-100 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-900 flex items-center gap-3">
          <button
            type="submit"
            form="shipping-rate-form"
            className="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs transition cursor-pointer shadow-sm flex items-center gap-1.5"
          >
            <CheckCircle2 className="w-4 h-4" />
            {isEditing ? 'Save Shipping Rate' : 'Insert Shipping Rate'}
          </button>
          <button
            type="button"
            onClick={onClose}
            className="px-4 py-2.5 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 hover:bg-neutral-100 dark:hover:bg-neutral-700 text-neutral-700 dark:text-neutral-200 text-xs font-semibold transition cursor-pointer"
          >
            Back
          </button>
        </div>

      </div>
    </div>
  );
};
