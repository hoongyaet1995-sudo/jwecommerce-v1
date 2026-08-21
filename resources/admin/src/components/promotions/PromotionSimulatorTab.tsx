import React, { useState } from 'react';
import { 
  Sparkles, 
  ShoppingCart, 
  Plus, 
  Minus, 
  Trash2, 
  ArrowRight, 
  CheckCircle2, 
  AlertTriangle, 
  ShieldAlert, 
  Layers, 
  Tag, 
  Flame, 
  Percent, 
  Coins, 
  RefreshCw,
  Code2
} from 'lucide-react';
import { Promotion } from '../../types/promotions';

interface Props {
  promotions: Promotion[];
}

interface CartItem {
  id: string;
  name: string;
  sku: string;
  price: number;
  collection: string;
  quantity: number;
  image: string;
}

const SAMPLE_PRODUCTS: CartItem[] = [
  {
    id: 'prod-1',
    name: 'Vintage Washed Graphic Heavyweight Tee (280GSM)',
    sku: 'TEE-VINTAGE-01',
    price: 49.00,
    collection: 'col-streetwear',
    quantity: 1,
    image: 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=150&auto=format&fit=crop&q=60'
  },
  {
    id: 'prod-2',
    name: 'Cyberpunk Acid-Wash Boxy Tee',
    sku: 'TEE-ACID-02',
    price: 49.00,
    collection: 'col-streetwear',
    quantity: 1,
    image: 'https://images.unsplash.com/photo-1503342217505-b0a15ec3261c?w=150&auto=format&fit=crop&q=60'
  },
  {
    id: 'prod-3',
    name: 'Minimalist Tokyo Typography Tee',
    sku: 'TEE-TOKYO-03',
    price: 49.00,
    collection: 'col-streetwear',
    quantity: 1,
    image: 'https://images.unsplash.com/photo-1583743814966-8936f5b7be1a?w=150&auto=format&fit=crop&q=60'
  },
  {
    id: 'prod-4',
    name: '450GSM French Terry Oversized Hoodie',
    sku: 'HD-450GSM',
    price: 189.00,
    collection: 'col-winter',
    quantity: 0,
    image: 'https://images.unsplash.com/photo-1556905055-8f358a7a47b2?w=150&auto=format&fit=crop&q=60'
  },
  {
    id: 'prod-5',
    name: 'Vintage Washed Corduroy Dad Cap',
    sku: 'ACC-CAP-09',
    price: 39.00,
    collection: 'col-accessories',
    quantity: 0,
    image: 'https://images.unsplash.com/photo-1588850561407-ed78c282e89b?w=150&auto=format&fit=crop&q=60'
  }
];

export const PromotionSimulatorTab: React.FC<Props> = ({ promotions }) => {
  const [cart, setCart] = useState<CartItem[]>(SAMPLE_PRODUCTS.filter(p => p.quantity > 0));
  const [inputVoucherCode, setInputVoucherCode] = useState('RAYA2026');
  const [appliedVoucher, setAppliedVoucher] = useState<string | null>('RAYA2026');
  const [selectedPaymentMethod, setSelectedPaymentMethod] = useState<'FPX' | 'GrabPay' | 'Credit Card'>('FPX');

  // Update item quantity
  const updateQty = (id: string, delta: number) => {
    setCart(prev => {
      const existing = prev.find(p => p.id === id);
      if (existing) {
        const nextQty = existing.quantity + delta;
        if (nextQty <= 0) {
          return prev.filter(p => p.id !== id);
        }
        return prev.map(p => p.id === id ? { ...p, quantity: nextQty } : p);
      } else {
        const product = SAMPLE_PRODUCTS.find(p => p.id === id);
        if (product) {
          return [...prev, { ...product, quantity: 1 }];
        }
      }
      return prev;
    });
  };

  const addProductToCart = (product: CartItem) => {
    updateQty(product.id, 1);
  };

  // ----------------------------------------------------
  // SIMULATION RULE ENGINE EXECUTION PIPELINE
  // ----------------------------------------------------
  const rawSubtotal = cart.reduce((acc, item) => acc + item.price * item.quantity, 0);
  const totalItemCount = cart.reduce((acc, item) => acc + item.quantity, 0);

  // 1. Evaluate Mix & Match (Any 3 Streetwear Tees for RM 99)
  const streetwearItems = cart.filter(item => item.collection === 'col-streetwear');
  const streetwearCount = streetwearItems.reduce((acc, item) => acc + item.quantity, 0);
  const mixMatchPromo = promotions.find(p => p.promotionType === 'mix_and_match' && p.isActive);

  let mixMatchDiscount = 0;
  let mixMatchBundlesApplied = 0;

  if (mixMatchPromo && streetwearCount >= 3) {
    mixMatchBundlesApplied = Math.floor(streetwearCount / 3);
    const bundleStandardPrice = 3 * 49.00; // RM 147
    const bundlePromoPrice = 99.00;
    mixMatchDiscount = mixMatchBundlesApplied * (bundleStandardPrice - bundlePromoPrice);
  }

  // 2. Evaluate Add-on Deal (Heavyweight Hoodie + Socks for RM 1)
  const hasHoodie = cart.some(item => item.sku === 'HD-450GSM');
  const addonPromo = promotions.find(p => p.promotionType === 'bundle_addon' && p.isActive);
  let addonDiscount = 0;
  if (hasHoodie && addonPromo) {
    // Discount saved on accessories
    addonDiscount = 0; // Trigger for add-on unlock
  }

  // 3. Evaluate BOGO (Caps & Beanies)
  const accessories = cart.filter(item => item.collection === 'col-accessories');
  const accessoriesCount = accessories.reduce((acc, item) => acc + item.quantity, 0);
  const bogoPromo = promotions.find(p => p.promotionType === 'bogo' && p.isActive);
  let bogoDiscount = 0;
  if (bogoPromo && accessoriesCount >= 2) {
    const freePairs = Math.floor(accessoriesCount / 2);
    bogoDiscount = freePairs * 39.00;
  }

  const subtotalAfterItemDiscounts = Math.max(0, rawSubtotal - mixMatchDiscount - bogoDiscount);

  // 4. Evaluate Voucher / Coupon Code
  let voucherDiscount = 0;
  let voucherError: string | null = null;
  let activeVoucherObj: Promotion | null = null;

  if (appliedVoucher) {
    const matchedPromo = promotions.find(
      p => p.coupons && p.coupons.some(c => c.code.toUpperCase() === appliedVoucher.toUpperCase()) && p.isActive
    );

    if (!matchedPromo) {
      voucherError = 'Invalid or expired voucher code';
    } else {
      activeVoucherObj = matchedPromo;
      const minSpendRule = matchedPromo.rules.find(r => r.type === 'min_order_subtotal');
      const minSpend = minSpendRule ? Number(minSpendRule.value) : 0;

      if (subtotalAfterItemDiscounts < minSpend) {
        voucherError = `Voucher requires minimum basket spend of RM ${minSpend.toFixed(2)} (Current eligible: RM ${subtotalAfterItemDiscounts.toFixed(2)})`;
      } else {
        if (matchedPromo.action.actionType === 'fixed_discount') {
          voucherDiscount = matchedPromo.action.discountAmount || 0;
        } else if (matchedPromo.action.actionType === 'percentage_discount') {
          const calc = (subtotalAfterItemDiscounts * (matchedPromo.action.discountPercentage || 0)) / 100;
          voucherDiscount = matchedPromo.action.maxDiscountCap ? Math.min(calc, matchedPromo.action.maxDiscountCap) : calc;
        }
      }
    }
  }

  // 5. Evaluate Payment Cashback (FPX / GrabPay 5% Coins)
  let cashbackCoins = 0;
  if (selectedPaymentMethod === 'FPX' || selectedPaymentMethod === 'GrabPay') {
    const finalBasket = Math.max(0, subtotalAfterItemDiscounts - voucherDiscount);
    cashbackCoins = Math.min(500, Math.floor(finalBasket * 0.05 * 100)); // 5% in coins
  }

  const finalPayable = Math.max(0, subtotalAfterItemDiscounts - voucherDiscount);
  const totalSavings = (rawSubtotal - finalPayable);

  return (
    <div className="space-y-6">
      {/* Simulator Intro Banner */}
      <div className="p-4 rounded-2xl bg-indigo-50/70 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800 flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center flex-shrink-0">
            <Sparkles className="w-5 h-5" />
          </div>
          <div>
            <h2 className="text-sm font-bold text-neutral-900 dark:text-white">
              Real-time Checkout Engine & Stacking Simulator
            </h2>
            <p className="text-xs text-neutral-600 dark:text-neutral-300 mt-0.5">
              Live simulation of the PostgreSQL Rule-Condition-Action evaluation pipeline, Anti-Double-Dipping guardrails, and deterministic priority order.
            </p>
          </div>
        </div>

        <button
          onClick={() => setCart(SAMPLE_PRODUCTS.slice(0, 3))}
          className="px-3 py-1.5 rounded-lg bg-white dark:bg-neutral-800 hover:bg-neutral-100 text-neutral-700 dark:text-neutral-300 text-xs font-semibold border border-neutral-200 dark:border-neutral-700 flex items-center gap-1.5 transition"
        >
          <RefreshCw className="w-3.5 h-3.5" />
          <span>Reset Cart</span>
        </button>
      </div>

      {/* Main Sandbox Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {/* Left Column: Interactive Cart & Catalog Selector */}
        <div className="lg:col-span-7 space-y-4">
          <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs space-y-4">
            <div className="flex items-center justify-between border-b border-neutral-100 dark:border-neutral-800 pb-3">
              <div className="flex items-center gap-2">
                <ShoppingCart className="w-4 h-4 text-indigo-500" />
                <h3 className="font-bold text-xs text-neutral-900 dark:text-white uppercase tracking-wider">
                  Live Customer Cart ({totalItemCount} Items)
                </h3>
              </div>
              <span className="text-xs font-semibold text-neutral-500">
                Raw Total: RM {rawSubtotal.toFixed(2)}
              </span>
            </div>

            {/* Cart Items List */}
            {cart.length === 0 ? (
              <div className="text-center py-8 text-neutral-400 text-xs">
                Cart is empty. Add products below to trigger discounts.
              </div>
            ) : (
              <div className="space-y-3">
                {cart.map((item) => (
                  <div
                    key={item.id}
                    className="p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200/80 dark:border-neutral-700/80 flex items-center justify-between gap-3"
                  >
                    <img
                      src={item.image}
                      alt={item.name}
                      referrerPolicy="no-referrer"
                      className="w-12 h-12 rounded-lg object-cover bg-neutral-200 dark:bg-neutral-700"
                    />
                    <div className="flex-1 min-w-0">
                      <h4 className="text-xs font-bold text-neutral-900 dark:text-white truncate">
                        {item.name}
                      </h4>
                      <div className="flex items-center gap-2 mt-0.5">
                        <span className="text-[11px] font-mono text-neutral-400">{item.sku}</span>
                        <span className="text-[10px] px-1.5 py-0.2 rounded bg-neutral-200 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300">
                          {item.collection === 'col-streetwear' ? 'Streetwear (Mix&Match Qualified)' : item.collection}
                        </span>
                      </div>
                      <div className="text-xs font-semibold text-neutral-800 dark:text-neutral-200 mt-1">
                        RM {item.price.toFixed(2)} each
                      </div>
                    </div>

                    <div className="flex items-center gap-2">
                      <div className="flex items-center border border-neutral-200 dark:border-neutral-700 rounded-lg bg-white dark:bg-neutral-900 overflow-hidden">
                        <button
                          onClick={() => updateQty(item.id, -1)}
                          className="px-2 py-1 text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-xs"
                        >
                          <Minus className="w-3 h-3" />
                        </button>
                        <span className="px-2.5 text-xs font-bold">{item.quantity}</span>
                        <button
                          onClick={() => updateQty(item.id, 1)}
                          className="px-2 py-1 text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-xs"
                        >
                          <Plus className="w-3 h-3" />
                        </button>
                      </div>

                      <button
                        onClick={() => updateQty(item.id, -item.quantity)}
                        className="p-1.5 text-neutral-400 hover:text-rose-500 transition"
                      >
                        <Trash2 className="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            )}

            {/* Quick Catalog Add Options */}
            <div className="pt-3 border-t border-neutral-100 dark:border-neutral-800">
              <div className="text-[11px] font-bold text-neutral-400 uppercase tracking-wider mb-2">
                Quick Test Items (Click to Add to Cart)
              </div>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                {SAMPLE_PRODUCTS.map((prod) => (
                  <button
                    key={prod.id}
                    onClick={() => addProductToCart(prod)}
                    className="p-2 rounded-xl bg-white dark:bg-neutral-800 hover:bg-neutral-50 dark:hover:bg-neutral-700/60 border border-neutral-200 dark:border-neutral-700 text-left transition flex items-center justify-between text-xs"
                  >
                    <span className="truncate flex-1 font-medium">{prod.name}</span>
                    <span className="font-bold text-indigo-600 dark:text-indigo-400 ml-2">
                      +RM {prod.price}
                    </span>
                  </button>
                ))}
              </div>
            </div>
          </div>
        </div>

        {/* Right Column: Rule Engine Execution Breakdown */}
        <div className="lg:col-span-5 space-y-4">
          <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs space-y-4">
            <h3 className="font-bold text-xs text-neutral-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
              <Code2 className="w-4 h-4 text-emerald-500" />
              <span>Pipeline Calculation & Order Breakdown</span>
            </h3>

            {/* Voucher Input Simulator */}
            <div className="p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/80 border border-neutral-200 dark:border-neutral-700 space-y-2">
              <label className="block text-[11px] font-bold text-neutral-700 dark:text-neutral-300">
                Apply Coupon / Voucher Code
              </label>
              <div className="flex gap-2">
                <input
                  type="text"
                  value={inputVoucherCode}
                  onChange={(e) => setInputVoucherCode(e.target.value.toUpperCase())}
                  placeholder="e.g. RAYA2026"
                  className="flex-1 px-3 py-1.5 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 text-xs font-mono font-bold"
                />
                <button
                  onClick={() => setAppliedVoucher(inputVoucherCode.trim() || null)}
                  className="px-3 py-1.5 rounded-lg bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 text-xs font-bold"
                >
                  Apply
                </button>
              </div>

              {voucherError && (
                <div className="text-[11px] text-rose-600 dark:text-rose-400 flex items-center gap-1.5 pt-1">
                  <AlertTriangle className="w-3.5 h-3.5 flex-shrink-0" />
                  <span>{voucherError}</span>
                </div>
              )}
            </div>

            {/* Payment Method Selector */}
            <div className="space-y-1.5">
              <label className="block text-[11px] font-bold text-neutral-700 dark:text-neutral-300">
                Checkout Payment Method (Cashback Rule)
              </label>
              <div className="grid grid-cols-3 gap-2">
                {(['FPX', 'GrabPay', 'Credit Card'] as const).map((method) => (
                  <button
                    key={method}
                    onClick={() => setSelectedPaymentMethod(method)}
                    className={`py-1.5 px-2 rounded-lg text-xs font-semibold border transition ${
                      selectedPaymentMethod === method
                        ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border-indigo-400'
                        : 'border-neutral-200 dark:border-neutral-700 text-neutral-600 dark:text-neutral-400'
                    }`}
                  >
                    {method}
                  </button>
                ))}
              </div>
            </div>

            {/* Triggered Rule Inspector */}
            <div className="space-y-2 pt-2 border-t border-neutral-100 dark:border-neutral-800">
              <div className="text-[10px] font-bold text-neutral-400 uppercase tracking-wider">
                Triggered Rule Pipeline Results
              </div>

              {mixMatchBundlesApplied > 0 && (
                <div className="p-2.5 rounded-xl bg-purple-50 dark:bg-purple-950/40 border border-purple-200 dark:border-purple-800 flex items-center justify-between text-xs">
                  <div className="flex items-center gap-2">
                    <Layers className="w-4 h-4 text-purple-600" />
                    <div>
                      <span className="font-bold text-purple-900 dark:text-purple-200">
                        Mix & Match Promo ({mixMatchBundlesApplied}x Bundle)
                      </span>
                      <div className="text-[10px] text-purple-600 dark:text-purple-300">
                        {mixMatchBundlesApplied * 3} items bundled at RM 99 per 3
                      </div>
                    </div>
                  </div>
                  <span className="font-bold text-purple-700 dark:text-purple-300">
                    -RM {mixMatchDiscount.toFixed(2)}
                  </span>
                </div>
              )}

              {bogoDiscount > 0 && (
                <div className="p-2.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 flex items-center justify-between text-xs">
                  <div className="flex items-center gap-2">
                    <Flame className="w-4 h-4 text-rose-600" />
                    <span className="font-bold text-rose-900 dark:text-rose-200">
                      BOGO Free Item Discount
                    </span>
                  </div>
                  <span className="font-bold text-rose-700 dark:text-rose-300">
                    -RM {bogoDiscount.toFixed(2)}
                  </span>
                </div>
              )}

              {voucherDiscount > 0 && (
                <div className="p-2.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800 flex items-center justify-between text-xs">
                  <div className="flex items-center gap-2">
                    <Tag className="w-4 h-4 text-indigo-600" />
                    <span className="font-bold text-indigo-900 dark:text-indigo-200">
                      Voucher Code [{appliedVoucher}]
                    </span>
                  </div>
                  <span className="font-bold text-indigo-700 dark:text-indigo-300">
                    -RM {voucherDiscount.toFixed(2)}
                  </span>
                </div>
              )}

              {cashbackCoins > 0 && (
                <div className="p-2.5 rounded-xl bg-cyan-50 dark:bg-cyan-950/40 border border-cyan-200 dark:border-cyan-800 flex items-center justify-between text-xs">
                  <div className="flex items-center gap-2">
                    <Coins className="w-4 h-4 text-cyan-600" />
                    <span className="font-bold text-cyan-900 dark:text-cyan-200">
                      Loyalty Coins Reward ({selectedPaymentMethod})
                    </span>
                  </div>
                  <span className="font-bold text-cyan-700 dark:text-cyan-300">
                    +{cashbackCoins} Coins (RM {(cashbackCoins / 100).toFixed(2)})
                  </span>
                </div>
              )}
            </div>

            {/* Final Financial Summary */}
            <div className="p-4 rounded-2xl bg-neutral-900 text-white space-y-2">
              <div className="flex justify-between text-xs text-neutral-400">
                <span>Original Subtotal</span>
                <span>RM {rawSubtotal.toFixed(2)}</span>
              </div>
              <div className="flex justify-between text-xs text-emerald-400">
                <span>Total Discount Saved</span>
                <span>-RM {totalSavings.toFixed(2)}</span>
              </div>
              <div className="pt-2 border-t border-neutral-800 flex justify-between items-baseline">
                <span className="text-xs font-bold text-neutral-300">Customer Pays</span>
                <span className="text-xl font-bold text-white">RM {finalPayable.toFixed(2)}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};
