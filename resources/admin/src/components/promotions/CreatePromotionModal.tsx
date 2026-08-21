import React, { useState } from 'react';
import { 
  X, 
  Sparkles, 
  Layers, 
  Tag, 
  Flame, 
  Plus, 
  Percent, 
  Coins, 
  ShieldCheck, 
  Calendar,
  AlertCircle,
  HelpCircle
} from 'lucide-react';
import { Promotion, PromotionType, StackingGroup, DiscountTarget } from '../../types/promotions';

interface Props {
  isOpen: boolean;
  onClose: () => void;
  onSave: (promotion: Promotion) => void;
}

export const CreatePromotionModal: React.FC<Props> = ({ isOpen, onClose, onSave }) => {
  const [promoType, setPromoType] = useState<PromotionType>('mix_and_match');
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [voucherCode, setVoucherCode] = useState('');
  const [priority, setPriority] = useState<number>(5);
  const [stackingGroup, setStackingGroup] = useState<StackingGroup>('shop_voucher');
  const [isStackable, setIsStackable] = useState(false);
  const [totalUsageLimit, setTotalUsageLimit] = useState<string>('500');
  const [budgetLimitMyr, setBudgetLimitMyr] = useState<string>('10000');
  const [perUserLimit, setPerUserLimit] = useState<number>(1);
  const [startsAt, setStartsAt] = useState('2026-08-20T00:00');
  const [endsAt, setEndsAt] = useState('2026-09-30T23:59');

  // Action specifics
  const [fixedDiscount, setFixedDiscount] = useState<number>(20);
  const [percentDiscount, setPercentDiscount] = useState<number>(15);
  const [maxDiscountCap, setMaxDiscountCap] = useState<number>(50);
  const [bundleSize, setBundleSize] = useState<number>(3);
  const [bundleFixedPrice, setBundleFixedPrice] = useState<number>(99);
  const [addonPrice, setAddonPrice] = useState<number>(1);
  const [cashbackPercent, setCashbackPercent] = useState<number>(5);
  const [minOrderSubtotal, setMinOrderSubtotal] = useState<number>(100);

  if (!isOpen) return null;

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    let actionConfig: any = {};
    let rulesConfig: any[] = [];
    let target: DiscountTarget = 'order_subtotal';

    if (promoType === 'mix_and_match') {
      target = 'matched_items';
      actionConfig = {
        actionType: 'bundle_fixed_price',
        bundleSize: bundleSize,
        bundleFixedPrice: bundleFixedPrice,
        overflowBehavior: 'charge_standard_unit_price'
      };
      rulesConfig = [
        {
          id: `r-${Date.now()}-1`,
          type: 'collection_membership',
          operator: 'IN',
          value: ['col-streetwear', 'col-summer'],
          description: `Qualifying items from Selected Collections`
        },
        {
          id: `r-${Date.now()}-2`,
          type: 'min_quantity',
          operator: '>=',
          value: bundleSize,
          description: `Requires at least ${bundleSize} qualifying items in cart`
        }
      ];
    } else if (promoType === 'coupon_code') {
      target = 'order_subtotal';
      actionConfig = {
        actionType: 'fixed_discount',
        discountAmount: fixedDiscount
      };
      rulesConfig = [
        {
          id: `r-${Date.now()}-1`,
          type: 'min_order_subtotal',
          operator: '>=',
          value: minOrderSubtotal,
          description: `Min basket subtotal of RM ${minOrderSubtotal.toFixed(2)}`
        }
      ];
    } else if (promoType === 'bundle_addon') {
      target = 'custom_sku';
      actionConfig = {
        actionType: 'addon_fixed_price',
        addonSku: 'ACC-ADDON-01',
        addonPrice: addonPrice
      };
      rulesConfig = [
        {
          id: `r-${Date.now()}-1`,
          type: 'min_quantity',
          operator: '>=',
          value: 1,
          description: 'Requires purchasing qualified Primary SKU'
        }
      ];
    } else if (promoType === 'bogo') {
      target = 'cheapest_item';
      actionConfig = {
        actionType: 'free_item',
        freeSku: 'PROMO-GIFT-01',
        freeSkuName: 'Free Matching Item'
      };
      rulesConfig = [
        {
          id: `r-${Date.now()}-1`,
          type: 'min_quantity',
          operator: '>=',
          value: 2,
          description: 'Buy 1, get 2nd matching item 100% Free'
        }
      ];
    } else if (promoType === 'quantity_tier') {
      target = 'order_subtotal';
      actionConfig = {
        actionType: 'percentage_discount',
        discountPercentage: percentDiscount,
        maxDiscountCap: maxDiscountCap
      };
      rulesConfig = [
        {
          id: `r-${Date.now()}-1`,
          type: 'min_order_subtotal',
          operator: '>=',
          value: minOrderSubtotal,
          description: `Min order spend RM ${minOrderSubtotal.toFixed(2)}`
        }
      ];
    } else if (promoType === 'cashback_coins') {
      target = 'order_subtotal';
      actionConfig = {
        actionType: 'cashback_points',
        cashbackPercentage: cashbackPercent,
        maxCoins: 500
      };
      rulesConfig = [
        {
          id: `r-${Date.now()}-1`,
          type: 'payment_method',
          operator: 'IN',
          value: ['FPX', 'GrabPay'],
          description: 'Valid for FPX & E-Wallets'
        }
      ];
    }

    const newPromo: Promotion = {
      id: `promo-${Date.now()}`,
      name: name || `${promoType.toUpperCase()} Campaign`,
      description: description || 'High-performance discount promotion engine campaign.',
      promotionType: promoType,
      priority: Number(priority),
      isStackable: isStackable,
      stackingGroup: stackingGroup,
      applyExclusively: false,
      startsAt: new Date(startsAt).toISOString(),
      endsAt: new Date(endsAt).toISOString(),
      isActive: true,
      totalUsageLimit: totalUsageLimit ? Number(totalUsageLimit) : null,
      totalUsageCount: 0,
      perUserLimit: Number(perUserLimit),
      budgetLimitMyr: budgetLimitMyr ? Number(budgetLimitMyr) : null,
      currentSpentMyr: 0,
      coupons: promoType === 'coupon_code' && voucherCode ? [
        {
          id: `cp-${Date.now()}`,
          code: voucherCode.toUpperCase(),
          usageLimit: totalUsageLimit ? Number(totalUsageLimit) : null,
          usageCount: 0,
          isSingleUse: false,
          createdAt: new Date().toISOString()
        }
      ] : undefined,
      rules: rulesConfig,
      action: actionConfig,
      target: target,
      createdAt: new Date().toISOString(),
      updatedAt: new Date().toISOString()
    };

    onSave(newPromo);
    onClose();
  };

  return (
    <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
      <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-3xl max-w-2xl w-full shadow-2xl overflow-hidden my-8">
        {/* Modal Header */}
        <div className="px-6 py-4 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
          <div className="flex items-center gap-2.5">
            <div className="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/80 text-indigo-600 flex items-center justify-center">
              <Sparkles className="w-4 h-4" />
            </div>
            <div>
              <h2 className="text-sm font-bold text-neutral-900 dark:text-white">Create Promotion Rule</h2>
              <p className="text-[11px] text-neutral-400">PostgreSQL / MySQL Schema-Backed Engine</p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
          >
            <X className="w-4 h-4" />
          </button>
        </div>

        {/* Modal Form */}
        <form onSubmit={handleSubmit} className="p-6 space-y-5 max-h-[80vh] overflow-y-auto">
          {/* Promo Type Selector */}
          <div>
            <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-2">
              Select Promotion Logic Paradigm
            </label>
            <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
              {[
                { id: 'mix_and_match', label: 'Mix & Match', sub: 'Any 3 for $99', icon: Layers },
                { id: 'coupon_code', label: 'Voucher Code', sub: 'Fixed / % with Cap', icon: Tag },
                { id: 'bundle_addon', label: 'Add-on Deal', sub: 'Buy A, Add B for $1', icon: Plus },
                { id: 'bogo', label: 'BOGO Offer', sub: 'Buy 1 Free 1', icon: Flame },
                { id: 'quantity_tier', label: 'Tiered Discount', sub: 'Spend $X Get $Y', icon: Percent },
                { id: 'cashback_coins', label: 'Coins Cashback', sub: '5% Coins Rebate', icon: Coins }
              ].map((item) => {
                const Icon = item.icon;
                const isSelected = promoType === item.id;
                return (
                  <button
                    key={item.id}
                    type="button"
                    onClick={() => setPromoType(item.id as PromotionType)}
                    className={`p-3 rounded-xl border text-left transition-all flex flex-col justify-between cursor-pointer ${
                      isSelected
                        ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 ring-2 ring-indigo-500/20'
                        : 'border-neutral-200 dark:border-neutral-800 hover:border-neutral-300 dark:hover:border-neutral-700'
                    }`}
                  >
                    <div className="flex items-center justify-between mb-1.5">
                      <Icon className="w-4 h-4" />
                      {isSelected && <div className="w-1.5 h-1.5 rounded-full bg-indigo-600" />}
                    </div>
                    <div className="text-xs font-bold text-neutral-900 dark:text-white">{item.label}</div>
                    <div className="text-[10px] text-neutral-400">{item.sub}</div>
                  </button>
                );
              })}
            </div>
          </div>

          {/* Basic Campaign Info */}
          <div className="space-y-3">
            <div>
              <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                Campaign Name
              </label>
              <input
                type="text"
                required
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="e.g. Streetwear Graphic Tees: Any 3 for RM 99"
                className="w-full px-3.5 py-2 rounded-xl bg-neutral-50 dark:bg-neutral-800/80 border border-neutral-200 dark:border-neutral-700 text-xs text-neutral-900 dark:text-white focus:ring-2 focus:ring-indigo-500/30"
              />
            </div>

            <div>
              <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                Public Customer Description
              </label>
              <input
                type="text"
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                placeholder="e.g. Choose any 3 selected heavyweight tees and pay only RM 99 at checkout."
                className="w-full px-3.5 py-2 rounded-xl bg-neutral-50 dark:bg-neutral-800/80 border border-neutral-200 dark:border-neutral-700 text-xs text-neutral-900 dark:text-white focus:ring-2 focus:ring-indigo-500/30"
              />
            </div>
          </div>

          {/* Dynamic Configuration per Promo Type */}
          <div className="p-4 rounded-2xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700 space-y-3">
            <div className="text-xs font-bold text-neutral-800 dark:text-neutral-200 flex items-center gap-1.5">
              <Sparkles className="w-3.5 h-3.5 text-indigo-500" />
              <span>Discount Execution Parameters</span>
            </div>

            {promoType === 'mix_and_match' && (
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-[11px] font-medium text-neutral-500 mb-1">Required Bundle Size (X Items)</label>
                  <input
                    type="number"
                    min="2"
                    value={bundleSize}
                    onChange={(e) => setBundleSize(Number(e.target.value))}
                    className="w-full px-3 py-1.5 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 text-xs"
                  />
                </div>
                <div>
                  <label className="block text-[11px] font-medium text-neutral-500 mb-1">Bundle Fixed Price (RM)</label>
                  <input
                    type="number"
                    value={bundleFixedPrice}
                    onChange={(e) => setBundleFixedPrice(Number(e.target.value))}
                    className="w-full px-3 py-1.5 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 text-xs"
                  />
                </div>
              </div>
            )}

            {promoType === 'coupon_code' && (
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                  <label className="block text-[11px] font-medium text-neutral-500 mb-1">Voucher Code</label>
                  <input
                    type="text"
                    required
                    value={voucherCode}
                    onChange={(e) => setVoucherCode(e.target.value.toUpperCase())}
                    placeholder="RAYA2026"
                    className="w-full px-3 py-1.5 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 text-xs font-mono font-bold"
                  />
                </div>
                <div>
                  <label className="block text-[11px] font-medium text-neutral-500 mb-1">Discount Amount (RM)</label>
                  <input
                    type="number"
                    value={fixedDiscount}
                    onChange={(e) => setFixedDiscount(Number(e.target.value))}
                    className="w-full px-3 py-1.5 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 text-xs"
                  />
                </div>
                <div>
                  <label className="block text-[11px] font-medium text-neutral-500 mb-1">Min. Subtotal (RM)</label>
                  <input
                    type="number"
                    value={minOrderSubtotal}
                    onChange={(e) => setMinOrderSubtotal(Number(e.target.value))}
                    className="w-full px-3 py-1.5 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 text-xs"
                  />
                </div>
              </div>
            )}

            {promoType === 'bundle_addon' && (
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-[11px] font-medium text-neutral-500 mb-1">Add-on Special Price (RM)</label>
                  <input
                    type="number"
                    value={addonPrice}
                    onChange={(e) => setAddonPrice(Number(e.target.value))}
                    className="w-full px-3 py-1.5 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 text-xs"
                  />
                </div>
                <div>
                  <label className="block text-[11px] font-medium text-neutral-500 mb-1">Target Add-on Accessory</label>
                  <input
                    type="text"
                    disabled
                    value="Premium Ribbed Socks (ACC-01)"
                    className="w-full px-3 py-1.5 rounded-lg bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 text-xs text-neutral-500"
                  />
                </div>
              </div>
            )}

            {promoType === 'quantity_tier' && (
              <div className="grid grid-cols-3 gap-3">
                <div>
                  <label className="block text-[11px] font-medium text-neutral-500 mb-1">Discount %</label>
                  <input
                    type="number"
                    value={percentDiscount}
                    onChange={(e) => setPercentDiscount(Number(e.target.value))}
                    className="w-full px-3 py-1.5 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 text-xs"
                  />
                </div>
                <div>
                  <label className="block text-[11px] font-medium text-neutral-500 mb-1">Max Cap (RM)</label>
                  <input
                    type="number"
                    value={maxDiscountCap}
                    onChange={(e) => setMaxDiscountCap(Number(e.target.value))}
                    className="w-full px-3 py-1.5 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 text-xs"
                  />
                </div>
                <div>
                  <label className="block text-[11px] font-medium text-neutral-500 mb-1">Min Spend (RM)</label>
                  <input
                    type="number"
                    value={minOrderSubtotal}
                    onChange={(e) => setMinOrderSubtotal(Number(e.target.value))}
                    className="w-full px-3 py-1.5 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 text-xs"
                  />
                </div>
              </div>
            )}

            {promoType === 'cashback_coins' && (
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-[11px] font-medium text-neutral-500 mb-1">Cashback %</label>
                  <input
                    type="number"
                    value={cashbackPercent}
                    onChange={(e) => setCashbackPercent(Number(e.target.value))}
                    className="w-full px-3 py-1.5 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 text-xs"
                  />
                </div>
                <div>
                  <label className="block text-[11px] font-medium text-neutral-500 mb-1">Max Coins Issued</label>
                  <input
                    type="number"
                    value={500}
                    disabled
                    className="w-full px-3 py-1.5 rounded-lg bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 text-xs text-neutral-500"
                  />
                </div>
              </div>
            )}
          </div>

          {/* Stacking Group & Concurrency Control */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                Stacking Partition Group
              </label>
              <select
                value={stackingGroup}
                onChange={(e) => setStackingGroup(e.target.value as StackingGroup)}
                className="w-full px-3 py-2 rounded-xl bg-neutral-50 dark:bg-neutral-800/80 border border-neutral-200 dark:border-neutral-700 text-xs text-neutral-900 dark:text-white"
              >
                <option value="shop_voucher">Shop Voucher Group (Max 1 per Order)</option>
                <option value="platform_voucher">Platform Voucher Group</option>
                <option value="payment_promo">Payment / E-Wallet Rebate</option>
                <option value="shipping_waiver">Shipping Fee Waiver</option>
              </select>
            </div>

            <div>
              <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                Priority Index (Evaluation Order)
              </label>
              <input
                type="number"
                min="1"
                max="20"
                value={priority}
                onChange={(e) => setPriority(Number(e.target.value))}
                className="w-full px-3 py-2 rounded-xl bg-neutral-50 dark:bg-neutral-800/80 border border-neutral-200 dark:border-neutral-700 text-xs text-neutral-900 dark:text-white"
              />
            </div>
          </div>

          {/* Quota and Financial Cap */}
          <div className="grid grid-cols-3 gap-3">
            <div>
              <label className="block text-[11px] font-medium text-neutral-500 mb-1">Total Usage Cap</label>
              <input
                type="number"
                value={totalUsageLimit}
                onChange={(e) => setTotalUsageLimit(e.target.value)}
                placeholder="500"
                className="w-full px-3 py-1.5 rounded-lg bg-neutral-50 dark:bg-neutral-800/80 border border-neutral-200 dark:border-neutral-700 text-xs"
              />
            </div>
            <div>
              <label className="block text-[11px] font-medium text-neutral-500 mb-1">Budget Limit (RM)</label>
              <input
                type="number"
                value={budgetLimitMyr}
                onChange={(e) => setBudgetLimitMyr(e.target.value)}
                placeholder="10000"
                className="w-full px-3 py-1.5 rounded-lg bg-neutral-50 dark:bg-neutral-800/80 border border-neutral-200 dark:border-neutral-700 text-xs"
              />
            </div>
            <div>
              <label className="block text-[11px] font-medium text-neutral-500 mb-1">Limit Per Customer</label>
              <input
                type="number"
                value={perUserLimit}
                onChange={(e) => setPerUserLimit(Number(e.target.value))}
                className="w-full px-3 py-1.5 rounded-lg bg-neutral-50 dark:bg-neutral-800/80 border border-neutral-200 dark:border-neutral-700 text-xs"
              />
            </div>
          </div>

          {/* Modal Actions */}
          <div className="pt-4 border-t border-neutral-200 dark:border-neutral-800 flex items-center justify-end gap-2.5">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 rounded-xl text-xs font-semibold text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
            >
              Cancel
            </button>
            <button
              type="submit"
              className="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-sm transition"
            >
              Deploy Campaign Rule
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};
