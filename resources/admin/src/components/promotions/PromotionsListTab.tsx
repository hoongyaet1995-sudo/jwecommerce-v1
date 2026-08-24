import React, { useState } from 'react';
import { 
  Plus, 
  Search, 
  Filter, 
  Trash2, 
  Edit3, 
  Copy, 
  Check, 
  AlertCircle, 
  Tag, 
  Sparkles, 
  TrendingUp, 
  Layers, 
  Flame, 
  Coins, 
  Percent, 
  ShoppingBag, 
  ShieldCheck, 
  Zap, 
  Eye, 
  Calendar,
  CheckCircle2,
  XCircle,
  HelpCircle,
  Code2
} from 'lucide-react';
import { Promotion, PromotionType, StackingGroup } from '../../types/promotions';
import { DeleteConfirmationModal } from '../common/DeleteConfirmationModal';

interface Props {
  promotions: Promotion[];
  onAddPromotion: (promo: Promotion) => void;
  onUpdatePromotion: (id: string, updates: Partial<Promotion>) => void;
  onDeletePromotion: (id: string) => void;
  onOpenCreateModal: () => void;
  onOpenTestSandbox: () => void;
}

export const PromotionsListTab: React.FC<Props> = ({
  promotions,
  onAddPromotion,
  onUpdatePromotion,
  onDeletePromotion,
  onOpenCreateModal,
  onOpenTestSandbox
}) => {
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedType, setSelectedType] = useState<string>('all');
  const [selectedStatus, setSelectedStatus] = useState<string>('all');
  const [copiedCode, setCopiedCode] = useState<string | null>(null);
  const [promoToDelete, setPromoToDelete] = useState<Promotion | null>(null);

  const handleCopy = (code: string) => {
    navigator.clipboard.writeText(code);
    setCopiedCode(code);
    setTimeout(() => setCopiedCode(null), 2000);
  };

  const filteredPromotions = promotions.filter(p => {
    const matchesSearch = p.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      p.description.toLowerCase().includes(searchQuery.toLowerCase()) ||
      (p.coupons && p.coupons.some(c => c.code.toLowerCase().includes(searchQuery.toLowerCase())));
    const matchesType = selectedType === 'all' || p.promotionType === selectedType;
    const matchesStatus = selectedStatus === 'all' || 
      (selectedStatus === 'active' && p.isActive) ||
      (selectedStatus === 'inactive' && !p.isActive);
    return matchesSearch && matchesType && matchesStatus;
  });

  // KPI Calculations
  const activeCount = promotions.filter(p => p.isActive).length;
  const totalBudget = promotions.reduce((acc, p) => acc + (p.budgetLimitMyr || 0), 0);
  const totalSpent = promotions.reduce((acc, p) => acc + p.currentSpentMyr, 0);
  const totalRedemptions = promotions.reduce((acc, p) => acc + p.totalUsageCount, 0);

  const getPromoTypeBadge = (type: PromotionType) => {
    switch (type) {
      case 'coupon_code':
        return { label: 'Voucher / Coupon', icon: Tag, color: 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800' };
      case 'mix_and_match':
        return { label: 'Mix & Match (Pick 3)', icon: Layers, color: 'bg-purple-50 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300 border-purple-200 dark:border-purple-800' };
      case 'bundle_addon':
        return { label: 'Add-on Deal', icon: Plus, color: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800' };
      case 'bogo':
        return { label: 'BOGO (Buy 1 Free 1)', icon: Flame, color: 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 border-rose-200 dark:border-rose-800' };
      case 'quantity_tier':
        return { label: 'Tiered Discount', icon: Percent, color: 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border-amber-200 dark:border-amber-800' };
      case 'cashback_coins':
        return { label: 'Coins Cashback', icon: Coins, color: 'bg-cyan-50 text-cyan-700 dark:bg-cyan-950/60 dark:text-cyan-300 border-cyan-200 dark:border-cyan-800' };
      default:
        return { label: 'Promotion', icon: Tag, color: 'bg-neutral-100 text-neutral-700 border-neutral-200' };
    }
  };

  return (
    <div className="space-y-6">
      {/* Top Action & KPI Strip */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs flex items-center justify-between">
          <div>
            <div className="text-xs font-semibold text-neutral-500 dark:text-neutral-400">Active Campaigns</div>
            <div className="text-2xl font-bold text-neutral-900 dark:text-white mt-1">
              {activeCount} <span className="text-xs font-normal text-neutral-400">/ {promotions.length} Total</span>
            </div>
          </div>
          <div className="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 flex items-center justify-center">
            <Zap className="w-5 h-5" />
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs flex items-center justify-between">
          <div>
            <div className="text-xs font-semibold text-neutral-500 dark:text-neutral-400">Total Redemptions</div>
            <div className="text-2xl font-bold text-neutral-900 dark:text-white mt-1">
              {totalRedemptions.toLocaleString()} <span className="text-xs font-normal text-emerald-600">+18% vs LW</span>
            </div>
          </div>
          <div className="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 flex items-center justify-center">
            <ShoppingBag className="w-5 h-5" />
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs flex items-center justify-between">
          <div>
            <div className="text-xs font-semibold text-neutral-500 dark:text-neutral-400">Discount Budget Utilized</div>
            <div className="text-2xl font-bold text-neutral-900 dark:text-white mt-1">
              RM {totalSpent.toLocaleString()}
            </div>
            <div className="text-[11px] text-neutral-400">Cap: RM {totalBudget.toLocaleString()}</div>
          </div>
          <div className="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 flex items-center justify-center">
            <TrendingUp className="w-5 h-5" />
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-gradient-to-br from-indigo-900 to-indigo-950 text-white border border-indigo-800 shadow-xs flex flex-col justify-between">
          <div className="flex items-center justify-between">
            <span className="text-xs font-medium text-indigo-200">Rule Engine v2.4</span>
            <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">
              Anti-Double Dip Active
            </span>
          </div>
          <div className="mt-2 flex items-center gap-2">
            <button
              onClick={onOpenTestSandbox}
              className="flex-1 py-1.5 px-3 bg-white/10 hover:bg-white/20 text-xs font-semibold rounded-lg transition-colors flex items-center justify-center gap-1.5"
            >
              <Sparkles className="w-3.5 h-3.5 text-amber-300" />
              <span>Checkout Simulator</span>
            </button>
          </div>
        </div>
      </div>

      {/* Filter and Search Bar */}
      <div className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs flex flex-col md:flex-row items-center justify-between gap-3">
        <div className="flex items-center gap-2 w-full md:w-auto flex-1">
          <div className="relative flex-1 max-w-md">
            <Search className="w-4 h-4 text-neutral-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input
              type="text"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              placeholder="Search campaigns, voucher codes (e.g. RAYA2026), rules..."
              className="w-full pl-9.5 pr-4 py-2 rounded-xl bg-neutral-50 dark:bg-neutral-800/80 border border-neutral-200 dark:border-neutral-700 text-xs text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
            />
          </div>

          {/* Type Filter */}
          <select
            value={selectedType}
            onChange={(e) => setSelectedType(e.target.value)}
            className="px-3 py-2 rounded-xl bg-neutral-50 dark:bg-neutral-800/80 border border-neutral-200 dark:border-neutral-700 text-xs text-neutral-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
          >
            <option value="all">All Promo Types</option>
            <option value="coupon_code">Voucher / Coupon Codes</option>
            <option value="mix_and_match">Mix & Match (Pick X for $Y)</option>
            <option value="bundle_addon">Add-on Deals</option>
            <option value="bogo">BOGO (Buy 1 Free 1)</option>
            <option value="quantity_tier">Tiered Discounts</option>
            <option value="cashback_coins">Coins Cashback</option>
          </select>

          {/* Status Filter */}
          <select
            value={selectedStatus}
            onChange={(e) => setSelectedStatus(e.target.value)}
            className="px-3 py-2 rounded-xl bg-neutral-50 dark:bg-neutral-800/80 border border-neutral-200 dark:border-neutral-700 text-xs text-neutral-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
          >
            <option value="all">All Status</option>
            <option value="active">Active Only</option>
            <option value="inactive">Paused / Inactive</option>
          </select>
        </div>

        <button
          onClick={onOpenCreateModal}
          className="w-full md:w-auto px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold flex items-center justify-center gap-1.5 shadow-sm transition-colors cursor-pointer"
        >
          <Plus className="w-4 h-4" />
          <span>Create Promotion Rule</span>
        </button>
      </div>

      {/* Promotions Cards Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        {filteredPromotions.map((promo) => {
          const badge = getPromoTypeBadge(promo.promotionType);
          const Icon = badge.icon;
          const usagePercent = promo.totalUsageLimit 
            ? Math.min(100, Math.round((promo.totalUsageCount / promo.totalUsageLimit) * 100))
            : null;
          const budgetPercent = promo.budgetLimitMyr 
            ? Math.min(100, Math.round((promo.currentSpentMyr / promo.budgetLimitMyr) * 100))
            : null;

          return (
            <div
              key={promo.id}
              className={`p-5 rounded-2xl bg-white dark:bg-neutral-900 border transition-all flex flex-col justify-between ${
                promo.isActive 
                  ? 'border-neutral-200 dark:border-neutral-800 hover:border-indigo-300 dark:hover:border-indigo-700 shadow-xs' 
                  : 'border-neutral-200 dark:border-neutral-800 opacity-60 bg-neutral-50/50 dark:bg-neutral-900/40'
              }`}
            >
              {/* Card Top Meta */}
              <div className="space-y-3">
                <div className="flex items-start justify-between gap-2">
                  <span className={`px-2.5 py-1 rounded-lg text-[11px] font-bold border flex items-center gap-1.5 ${badge.color}`}>
                    <Icon className="w-3.5 h-3.5" />
                    <span>{badge.label}</span>
                  </span>

                  <div className="flex items-center gap-2">
                    {/* Active Toggle */}
                    <button
                      onClick={() => onUpdatePromotion(promo.id, { isActive: !promo.isActive })}
                      className={`relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none ${
                        promo.isActive ? 'bg-emerald-500' : 'bg-neutral-300 dark:bg-neutral-700'
                      }`}
                    >
                      <span
                        className={`pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${
                          promo.isActive ? 'translate-x-4' : 'translate-x-0'
                        }`}
                      />
                    </button>
                  </div>
                </div>

                <div>
                  <h3 className="font-bold text-sm text-neutral-900 dark:text-white line-clamp-1">
                    {promo.name}
                  </h3>
                  <p className="text-xs text-neutral-500 dark:text-neutral-400 line-clamp-2 mt-1">
                    {promo.description}
                  </p>
                </div>

                {/* Coupon Code Pill if Applicable */}
                {promo.coupons && promo.coupons.length > 0 && (
                  <div className="flex items-center gap-2 p-2 rounded-xl bg-neutral-50 dark:bg-neutral-800/80 border border-neutral-200 dark:border-neutral-700">
                    <Tag className="w-3.5 h-3.5 text-indigo-500 flex-shrink-0" />
                    <span className="font-mono font-bold text-xs text-neutral-800 dark:text-neutral-200 flex-1">
                      {promo.coupons[0].code}
                    </span>
                    <button
                      onClick={() => handleCopy(promo.coupons![0].code)}
                      className="px-2 py-0.5 rounded bg-white dark:bg-neutral-700 hover:bg-neutral-100 text-[10px] font-semibold text-neutral-600 dark:text-neutral-300 border border-neutral-200 dark:border-neutral-600 flex items-center gap-1 transition-colors"
                    >
                      {copiedCode === promo.coupons[0].code ? (
                        <>
                          <Check className="w-3 h-3 text-emerald-500" />
                          <span className="text-emerald-600">Copied</span>
                        </>
                      ) : (
                        <>
                          <Copy className="w-3 h-3" />
                          <span>Copy</span>
                        </>
                      )}
                    </button>
                  </div>
                )}

                {/* Condition Rules Overview */}
                <div className="space-y-1.5 pt-1">
                  <div className="text-[10px] font-bold text-neutral-400 uppercase tracking-wider">Applied Conditions</div>
                  <div className="space-y-1">
                    {promo.rules.map((rule) => (
                      <div key={rule.id} className="text-[11px] text-neutral-600 dark:text-neutral-300 flex items-center gap-1.5">
                        <CheckCircle2 className="w-3.5 h-3.5 text-emerald-500 flex-shrink-0" />
                        <span className="truncate">{rule.description}</span>
                      </div>
                    ))}
                  </div>
                </div>

                {/* Quota & Budget Progress Bar */}
                <div className="pt-2 border-t border-neutral-100 dark:border-neutral-800 space-y-2">
                  <div className="flex justify-between text-[11px]">
                    <span className="text-neutral-500">Usage Quota</span>
                    <span className="font-medium text-neutral-800 dark:text-neutral-200">
                      {promo.totalUsageCount} {promo.totalUsageLimit ? `/ ${promo.totalUsageLimit}` : '(Unlimited)'}
                    </span>
                  </div>
                  {usagePercent !== null && (
                    <div className="w-full h-1.5 bg-neutral-100 dark:bg-neutral-800 rounded-full overflow-hidden">
                      <div 
                        className={`h-full rounded-full ${usagePercent > 85 ? 'bg-rose-500' : 'bg-indigo-500'}`} 
                        style={{ width: `${usagePercent}%` }}
                      />
                    </div>
                  )}

                  <div className="flex justify-between text-[11px] pt-1">
                    <span className="text-neutral-500">Spent Budget</span>
                    <span className="font-medium text-neutral-800 dark:text-neutral-200">
                      RM {promo.currentSpentMyr.toLocaleString()} {promo.budgetLimitMyr ? `/ RM ${promo.budgetLimitMyr.toLocaleString()}` : ''}
                    </span>
                  </div>
                </div>
              </div>

              {/* Card Footer Stack & Exclusivity Badges */}
              <div className="pt-4 mt-3 border-t border-neutral-100 dark:border-neutral-800 flex items-center justify-between">
                <div className="flex items-center gap-1.5">
                  <span className={`text-[10px] px-2 py-0.5 rounded-md font-medium ${
                    promo.isStackable 
                      ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300' 
                      : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400'
                  }`}>
                    {promo.isStackable ? 'Stackable' : 'Exclusive Group'}
                  </span>
                  <span className="text-[10px] text-neutral-400">P{promo.priority}</span>
                </div>

                <div className="flex items-center gap-1">
                  <button
                    onClick={() => setPromoToDelete(promo)}
                    className="p-1.5 rounded-lg text-neutral-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors"
                    title="Delete promotion"
                  >
                    <Trash2 className="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>
          );
        })}
      </div>

      {/* Delete Promotion Confirmation Modal */}
      <DeleteConfirmationModal
        isOpen={!!promoToDelete}
        itemType="Promotion Campaign"
        itemName={promoToDelete?.name}
        itemDetails={`Promo Code: ${promoToDelete?.coupons?.[0]?.code || 'Automatic Rule'} • Type: ${promoToDelete?.promotionType} • Total Redemptions: ${promoToDelete?.totalUsageCount || 0}/${promoToDelete?.totalUsageLimit || '∞'}`}
        warningMessage={`Deleting "${promoToDelete?.name}" immediately removes its checkout discount eligibility.`}
        impactItems={[
          'Active checkout discounts will no longer use this promotion.',
          'Existing order discount snapshots remain unchanged.',
          'This promotion and its configured rules cannot be restored automatically.',
        ]}
        confirmButtonText="Delete Promotion Rule"
        onConfirm={() => {
          if (promoToDelete) {
            onDeletePromotion(promoToDelete.id);
            setPromoToDelete(null);
          }
        }}
        onClose={() => setPromoToDelete(null)}
      />

      {filteredPromotions.length === 0 && (
        <div className="text-center py-12 bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800">
          <Tag className="w-12 h-12 text-neutral-300 dark:text-neutral-600 mx-auto mb-3" />
          <h3 className="font-bold text-neutral-700 dark:text-neutral-300 text-sm">No promotion campaigns found</h3>
          <p className="text-xs text-neutral-400 mt-1 max-w-sm mx-auto">
            Try adjusting your search criteria or create a new campaign rule.
          </p>
        </div>
      )}
    </div>
  );
};
