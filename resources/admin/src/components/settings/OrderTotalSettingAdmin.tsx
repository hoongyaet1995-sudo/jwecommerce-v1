import React, { useState } from 'react';
import { useAdmin } from '../../context/AdminContext';
import { OrderTotalModule, OrderTotalModuleCode } from '../../types/oscommerceSettings';
import {
  Calculator,
  Plus,
  Edit2,
  CheckCircle2,
  XCircle,
  ArrowUp,
  ArrowDown,
  Layers,
  Sparkles,
  Sliders,
  DollarSign,
  Info,
  Check,
  AlertCircle
} from 'lucide-react';

export const OrderTotalSettingAdmin: React.FC = () => {
  const {
    orderTotalModules,
    saveOrderTotalModule,
    reorderOrderTotalModules,
    toggleOrderTotalModule,
    addToast
  } = useAdmin();

  const [editingModule, setEditingModule] = useState<OrderTotalModule | null>(null);

  // Live Simulator state
  const [simSubtotal, setSimSubtotal] = useState<number>(200);
  const [simDiscount, setSimDiscount] = useState<number>(30);
  const [simShipping, setSimShipping] = useState<number>(12);
  const [simRedeemPoints, setSimRedeemPoints] = useState<number>(500); // 500 pts = RM 5.00
  const [simIsCod, setSimIsCod] = useState<boolean>(true);

  const sortedModules = [...orderTotalModules].sort((a, b) => a.sortOrder - b.sortOrder);

  const handleMove = (index: number, direction: 'up' | 'down') => {
    const targetIndex = direction === 'up' ? index - 1 : index + 1;
    if (targetIndex < 0 || targetIndex >= sortedModules.length) return;

    const updated = [...sortedModules];
    const current = updated[index];
    const target = updated[targetIndex];

    const tempSort = current.sortOrder;
    current.sortOrder = target.sortOrder;
    target.sortOrder = tempSort;

    reorderOrderTotalModules(updated);
    addToast({
      type: 'info',
      title: 'Calculation Pipeline Reordered',
      message: `Updated execution sequence for "${current.title}"`
    });
  };

  const handleSaveModal = (e: React.FormEvent) => {
    e.preventDefault();
    if (!editingModule) return;
    saveOrderTotalModule(editingModule);
    setEditingModule(null);
  };

  // Math calculation engine mirroring osCommerce order total waterfall
  const calculatePipeline = () => {
    let currentTotal = 0;
    const breakdown: { code: OrderTotalModuleCode; title: string; amount: number; runningTotal: number; note?: string }[] = [];

    sortedModules.forEach(mod => {
      if (!mod.isEnabled) return;

      if (mod.code === 'ot_subtotal') {
        const val = simSubtotal;
        currentTotal = val;
        breakdown.push({ code: mod.code, title: mod.title, amount: val, runningTotal: currentTotal });
      } else if (mod.code === 'ot_discount') {
        const val = -Math.min(simDiscount, currentTotal);
        currentTotal += val;
        breakdown.push({ code: mod.code, title: mod.title, amount: val, runningTotal: currentTotal });
      } else if (mod.code === 'ot_shipping') {
        const isFree = mod.config.freeShippingThreshold && simSubtotal >= mod.config.freeShippingThreshold;
        const val = isFree ? 0 : simShipping;
        currentTotal += val;
        breakdown.push({
          code: mod.code,
          title: mod.title + (isFree ? ' (Free Shipping Qualified)' : ''),
          amount: val,
          runningTotal: currentTotal,
          note: isFree ? `Free Shipping over RM ${mod.config.freeShippingThreshold}` : undefined
        });
      } else if (mod.code === 'ot_tax') {
        // SST 8% on taxable balance
        const taxBase = Math.max(0, currentTotal);
        const val = taxBase * 0.08;
        currentTotal += val;
        breakdown.push({ code: mod.code, title: mod.title, amount: val, runningTotal: currentTotal, note: '8% SST on calculated balance' });
      } else if (mod.code === 'ot_reward_points') {
        const pointVal = -Math.min((simRedeemPoints / (mod.config.rewardRedemptionRate || 100)), currentTotal);
        currentTotal += pointVal;
        breakdown.push({ code: mod.code, title: `${mod.title} (${simRedeemPoints} pts)`, amount: pointVal, runningTotal: currentTotal });
      } else if (mod.code === 'ot_cod_fee') {
        if (simIsCod) {
          const val = mod.config.codHandlingFee || 5.00;
          currentTotal += val;
          breakdown.push({ code: mod.code, title: mod.title, amount: val, runningTotal: currentTotal });
        }
      } else if (mod.code === 'ot_rounding') {
        // Malaysian BNM 5-Cent Rounding
        const rounded = Math.round(currentTotal * 20) / 20;
        const diff = rounded - currentTotal;
        currentTotal = rounded;
        if (Math.abs(diff) > 0.001) {
          breakdown.push({ code: mod.code, title: mod.title, amount: diff, runningTotal: currentTotal, note: 'Rounded to nearest RM 0.05' });
        }
      } else if (mod.code === 'ot_total') {
        breakdown.push({ code: mod.code, title: mod.title, amount: currentTotal, runningTotal: currentTotal });
      }
    });

    return { breakdown, finalTotal: currentTotal };
  };

  const { breakdown, finalTotal } = calculatePipeline();

  return (
    <div className="space-y-6">
      {/* Header Banner */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
        <div>
          <div className="flex items-center gap-2">
            <span className="px-2.5 py-0.5 text-[10px] font-bold uppercase rounded-full bg-amber-500/20 text-amber-700 dark:text-amber-300 border border-amber-500/30">
              osCommerce Core Architecture
            </span>
            <span className="text-xs text-neutral-500">Order Total Calculation Modules</span>
          </div>
          <h2 className="text-lg sm:text-xl font-bold text-neutral-900 dark:text-white tracking-tight mt-1">
            Order Total Modules (<code className="font-mono text-indigo-600 dark:text-indigo-400">order_total</code>)
          </h2>
          <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5 max-w-3xl">
            In osCommerce architecture, checkout calculations and invoice breakdowns are processed in a sequential <code className="font-mono text-indigo-600 dark:text-indigo-400">sort_order</code> pipeline. Modules execute sequentially (Subtotal → Discounts → Shipping → SST Tax → Loyalty Points → COD Surcharge → BNM Rounding → Grand Total).
          </p>
        </div>
      </div>

      {/* Interactive Live Calculation Pipeline Simulator */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-5">
        {/* Simulator Controls */}
        <div className="lg:col-span-5 p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 space-y-4 shadow-xs">
          <div className="flex items-center gap-2 border-b border-neutral-200 dark:border-neutral-800 pb-3">
            <Calculator className="w-4 h-4 text-indigo-600" />
            <h3 className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider">
              Live Order Total Simulator
            </h3>
          </div>

          <div className="space-y-3 text-xs">
            <div className="space-y-1">
              <div className="flex items-center justify-between text-neutral-600 dark:text-neutral-400 font-semibold">
                <span>Merchandise Subtotal:</span>
                <span className="font-mono font-bold text-neutral-900 dark:text-white">RM {simSubtotal.toFixed(2)}</span>
              </div>
              <input
                type="range"
                min={20}
                max={500}
                step={5}
                value={simSubtotal}
                onChange={e => setSimSubtotal(Number(e.target.value))}
                className="w-full accent-indigo-600 cursor-pointer"
              />
            </div>

            <div className="space-y-1">
              <div className="flex items-center justify-between text-neutral-600 dark:text-neutral-400 font-semibold">
                <span>Voucher / Coupon Discount:</span>
                <span className="font-mono font-bold text-rose-600">-RM {simDiscount.toFixed(2)}</span>
              </div>
              <input
                type="range"
                min={0}
                max={100}
                step={5}
                value={simDiscount}
                onChange={e => setSimDiscount(Number(e.target.value))}
                className="w-full accent-indigo-600 cursor-pointer"
              />
            </div>

            <div className="space-y-1">
              <div className="flex items-center justify-between text-neutral-600 dark:text-neutral-400 font-semibold">
                <span>Courier Postage:</span>
                <span className="font-mono font-bold text-neutral-900 dark:text-white">RM {simShipping.toFixed(2)}</span>
              </div>
              <input
                type="range"
                min={0}
                max={30}
                step={1}
                value={simShipping}
                onChange={e => setSimShipping(Number(e.target.value))}
                className="w-full accent-indigo-600 cursor-pointer"
              />
            </div>

            <div className="space-y-1">
              <div className="flex items-center justify-between text-neutral-600 dark:text-neutral-400 font-semibold">
                <span>Redeem Loyalty Points:</span>
                <span className="font-mono font-bold text-emerald-600">{simRedeemPoints} pts (-RM {(simRedeemPoints / 100).toFixed(2)})</span>
              </div>
              <input
                type="range"
                min={0}
                max={1000}
                step={100}
                value={simRedeemPoints}
                onChange={e => setSimRedeemPoints(Number(e.target.value))}
                className="w-full accent-indigo-600 cursor-pointer"
              />
            </div>

            <div className="pt-2 flex items-center justify-between border-t border-neutral-200 dark:border-neutral-800">
              <span className="font-semibold text-neutral-700 dark:text-neutral-300">Enable Doorstep COD Collection</span>
              <input
                type="checkbox"
                checked={simIsCod}
                onChange={e => setSimIsCod(e.target.checked)}
                className="w-4 h-4 rounded text-indigo-600 accent-indigo-600 cursor-pointer"
              />
            </div>
          </div>
        </div>

        {/* Live Waterfall Output */}
        <div className="lg:col-span-7 p-5 rounded-2xl bg-neutral-50 dark:bg-neutral-950/60 border border-neutral-200 dark:border-neutral-800 space-y-4 shadow-xs">
          <div className="flex items-center justify-between border-b border-neutral-200 dark:border-neutral-800 pb-3">
            <h3 className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider">
              Computed Invoice Waterfall Summary
            </h3>
            <span className="text-[10px] font-mono bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 px-2 py-0.5 rounded">
              osCommerce Engine Executed
            </span>
          </div>

          <div className="space-y-2">
            {breakdown.map((item, idx) => {
              const isGrand = item.code === 'ot_total';
              return (
                <div
                  key={idx}
                  className={`flex items-center justify-between p-2.5 rounded-xl transition ${
                    isGrand
                      ? 'bg-indigo-600 text-white font-bold text-sm shadow-sm'
                      : 'bg-white dark:bg-neutral-900 text-xs border border-neutral-200/70 dark:border-neutral-800'
                  }`}
                >
                  <div>
                    <span className={isGrand ? 'text-white' : 'font-semibold text-neutral-800 dark:text-neutral-200'}>
                      {item.title}
                    </span>
                    {item.note && (
                      <span className="block text-[10px] text-neutral-500 dark:text-neutral-400 mt-0.5">
                        {item.note}
                      </span>
                    )}
                  </div>
                  <div className="text-right">
                    <span className={`font-mono font-bold ${
                      isGrand
                        ? 'text-white text-base'
                        : item.amount < 0
                        ? 'text-emerald-600 dark:text-emerald-400'
                        : 'text-neutral-900 dark:text-white'
                    }`}>
                      {item.amount < 0 ? `-RM ${Math.abs(item.amount).toFixed(2)}` : `RM ${item.amount.toFixed(2)}`}
                    </span>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      </div>

      {/* Order Total Modules Configuration Table */}
      <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 overflow-hidden shadow-xs">
        <div className="p-4 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
          <h3 className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider">
            Installed Order Total Modules & Pipeline Sort Sequence
          </h3>
          <span className="text-xs text-neutral-500">Lower sort_order runs first</span>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead>
              <tr className="bg-neutral-50 dark:bg-neutral-800/60 text-neutral-500 dark:text-neutral-400 font-semibold border-b border-neutral-200 dark:border-neutral-800">
                <th className="py-3 px-4 w-12 text-center">Sort</th>
                <th className="py-3 px-4">Module Code</th>
                <th className="py-3 px-4">Display Title</th>
                <th className="py-3 px-4">Mathematical Behavior</th>
                <th className="py-3 px-4">Active Parameters</th>
                <th className="py-3 px-4 text-center">Pipeline Active</th>
                <th className="py-3 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800">
              {sortedModules.map((mod, idx) => (
                <tr key={mod.id} className="hover:bg-neutral-50/70 dark:hover:bg-neutral-800/40 transition-colors">
                  <td className="py-3.5 px-4 text-center font-mono font-bold text-indigo-600 dark:text-indigo-400">
                    {mod.sortOrder}
                  </td>
                  <td className="py-3.5 px-4 font-mono font-semibold text-neutral-700 dark:text-neutral-300">
                    {mod.code}
                  </td>
                  <td className="py-3.5 px-4">
                    <div className="font-bold text-neutral-900 dark:text-white">{mod.title}</div>
                    <div className="text-[11px] text-neutral-500 line-clamp-1 max-w-sm">{mod.description}</div>
                  </td>
                  <td className="py-3.5 px-4">
                    <span className="px-2 py-0.5 rounded-md text-[10px] font-bold bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 uppercase">
                      {mod.calculationType}
                    </span>
                  </td>
                  <td className="py-3.5 px-4 text-[11px] text-neutral-600 dark:text-neutral-400">
                    {mod.code === 'ot_shipping' && mod.config.freeShippingThreshold && (
                      <span>Free Ship Threshold: RM {mod.config.freeShippingThreshold}</span>
                    )}
                    {mod.code === 'ot_cod_fee' && mod.config.codHandlingFee && (
                      <span>COD Surcharge: RM {mod.config.codHandlingFee.toFixed(2)}</span>
                    )}
                    {mod.code === 'ot_reward_points' && (
                      <span>Conversion: {mod.config.rewardRedemptionRate || 100} pts = RM 1.00</span>
                    )}
                    {mod.code === 'ot_rounding' && (
                      <span>BNM 5-Cent Standard</span>
                    )}
                    {!['ot_shipping', 'ot_cod_fee', 'ot_reward_points', 'ot_rounding'].includes(mod.code) && (
                      <span>Standard osC Rule</span>
                    )}
                  </td>
                  <td className="py-3.5 px-4 text-center">
                    <button
                      onClick={() => toggleOrderTotalModule(mod.id)}
                      disabled={mod.code === 'ot_subtotal' || mod.code === 'ot_total'}
                      className={`inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold border transition ${
                        mod.isEnabled
                          ? 'bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-950 dark:text-emerald-300 dark:border-emerald-800'
                          : 'bg-neutral-100 text-neutral-500 border-neutral-300 dark:bg-neutral-800 dark:text-neutral-400 dark:border-neutral-700'
                      }`}
                    >
                      {mod.isEnabled ? 'Enabled' : 'Disabled'}
                    </button>
                  </td>
                  <td className="py-3.5 px-4 text-right">
                    <div className="flex items-center justify-end gap-1">
                      <button
                        onClick={() => handleMove(idx, 'up')}
                        disabled={idx === 0}
                        className="p-1.5 text-neutral-500 hover:text-indigo-600 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-lg disabled:opacity-30"
                        title="Move Up in sequence"
                      >
                        <ArrowUp className="w-3.5 h-3.5" />
                      </button>
                      <button
                        onClick={() => handleMove(idx, 'down')}
                        disabled={idx === sortedModules.length - 1}
                        className="p-1.5 text-neutral-500 hover:text-indigo-600 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-lg disabled:opacity-30"
                        title="Move Down in sequence"
                      >
                        <ArrowDown className="w-3.5 h-3.5" />
                      </button>
                      <button
                        onClick={() => setEditingModule({ ...mod })}
                        className="p-1.5 text-neutral-500 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 rounded-lg transition"
                        title="Edit Module Properties"
                      >
                        <Edit2 className="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Edit Module Modal */}
      {editingModule && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
          <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-3xl p-6 max-w-lg w-full shadow-2xl space-y-5 animate-in fade-in zoom-in-95 duration-150 my-8">
            <div className="flex items-center justify-between border-b border-neutral-200 dark:border-neutral-800 pb-3">
              <div>
                <h3 className="text-base font-bold text-neutral-900 dark:text-white">
                  Configure {editingModule.title}
                </h3>
                <p className="text-xs text-neutral-500">Module identifier: <code className="font-mono text-indigo-600">{editingModule.code}</code></p>
              </div>
              <button
                onClick={() => setEditingModule(null)}
                className="p-2 rounded-xl text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-xs font-bold"
              >
                ✕
              </button>
            </div>

            <form onSubmit={handleSaveModal} className="space-y-4">
              <div className="space-y-1">
                <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Invoice Display Label</label>
                <input
                  type="text"
                  required
                  value={editingModule.title}
                  onChange={e => setEditingModule({ ...editingModule, title: e.target.value })}
                  className="w-full px-3 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs text-neutral-900 dark:text-white font-semibold"
                />
              </div>

              <div className="space-y-1">
                <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Pipeline Sort Order</label>
                <input
                  type="number"
                  required
                  value={editingModule.sortOrder}
                  onChange={e => setEditingModule({ ...editingModule, sortOrder: Number(e.target.value) })}
                  className="w-full px-3 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-mono"
                />
              </div>

              {editingModule.code === 'ot_shipping' && (
                <div className="space-y-1 p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700">
                  <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Free Shipping Threshold (RM)</label>
                  <input
                    type="number"
                    value={editingModule.config.freeShippingThreshold || 0}
                    onChange={e => setEditingModule({
                      ...editingModule,
                      config: { ...editingModule.config, freeShippingThreshold: Number(e.target.value) }
                    })}
                    className="w-full px-3 py-1.5 rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-xs font-bold"
                  />
                </div>
              )}

              {editingModule.code === 'ot_cod_fee' && (
                <div className="space-y-1 p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700">
                  <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">COD Handling Surcharge (RM)</label>
                  <input
                    type="number"
                    value={editingModule.config.codHandlingFee || 5.0}
                    onChange={e => setEditingModule({
                      ...editingModule,
                      config: { ...editingModule.config, codHandlingFee: Number(e.target.value) }
                    })}
                    className="w-full px-3 py-1.5 rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-xs font-bold"
                  />
                </div>
              )}

              {editingModule.code === 'ot_reward_points' && (
                <div className="space-y-1 p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700">
                  <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Points per 1.00 Currency Unit</label>
                  <input
                    type="number"
                    value={editingModule.config.rewardRedemptionRate || 100}
                    onChange={e => setEditingModule({
                      ...editingModule,
                      config: { ...editingModule.config, rewardRedemptionRate: Number(e.target.value) }
                    })}
                    className="w-full px-3 py-1.5 rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-xs font-bold"
                  />
                </div>
              )}

              <div className="flex items-center justify-end gap-2 pt-3 border-t border-neutral-200 dark:border-neutral-800">
                <button
                  type="button"
                  onClick={() => setEditingModule(null)}
                  className="px-4 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 text-xs font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs"
                >
                  Save Module Settings
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
