import React, { useEffect, useState } from 'react';
import { 
  Sparkles, 
  Tag, 
  Layers, 
  History, 
  Plus, 
  Database, 
  ShieldCheck, 
  Zap,
  Code2
} from 'lucide-react';
import { PromotionsListTab } from './PromotionsListTab';
import { PromotionSimulatorTab } from './PromotionSimulatorTab';
import { PromotionLogsTab } from './PromotionLogsTab';
import { CreatePromotionModal } from './CreatePromotionModal';
import { Promotion } from '../../types/promotions';
import { readAdminBootstrap } from '../../integration/adminBootstrap';
import { deleteCouponPromotion, fetchCoupons, saveCouponPromotion } from '../../integration/couponData';
import { useAdmin } from '../../context/AdminContext';

export const PromotionsHub: React.FC = () => {
  const [activeTab, setActiveTab] = useState<'campaigns' | 'simulator' | 'logs'>('campaigns');
  const [promotions, setPromotions] = useState<Promotion[]>([]);
  const [usages, setUsages] = useState<import('../../types/promotions').PromotionUsageLog[]>([]);
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const { addToast } = useAdmin();
  const bootstrap = readAdminBootstrap();

  useEffect(() => {
    let active = true;
    void fetchCoupons(bootstrap).then(data => { if (active) { setPromotions(data.promotions); setUsages(data.usages); } }).catch(error => {
      if (active) addToast({ type: 'error', title: 'Promotions Unavailable', message: error instanceof Error ? error.message : 'Unable to load coupons from MySQL.' });
    });
    return () => { active = false; };
  // Laravel emits one immutable bootstrap document per app load.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const handleAddPromotion = async (newPromo: Promotion) => {
    try {
      const saved = await saveCouponPromotion(bootstrap, newPromo);
      setPromotions(prev => [saved, ...prev]);
      addToast({ type: 'success', title: 'Promotion Saved', message: `Coupon ${saved.coupons?.[0]?.code ?? saved.name} is live in MySQL.` });
    } catch (error) {
      addToast({ type: 'error', title: 'Promotion Save Failed', message: error instanceof Error ? error.message : 'Unable to save promotion.' });
    }
  };

  const handleUpdatePromotion = async (id: string, updates: Partial<Promotion>) => {
    const existing = promotions.find(promotion => promotion.id === id);
    if (!existing) return;
    try {
      const saved = await saveCouponPromotion(bootstrap, { ...existing, ...updates });
      setPromotions(prev => prev.map(promotion => promotion.id === id ? saved : promotion));
    } catch (error) {
      addToast({ type: 'error', title: 'Promotion Update Failed', message: error instanceof Error ? error.message : 'Unable to update promotion.' });
    }
  };

  const handleDeletePromotion = async (id: string) => {
    try {
      await deleteCouponPromotion(bootstrap, id);
      setPromotions(prev => prev.filter(promotion => promotion.id !== id));
    } catch (error) {
      addToast({ type: 'error', title: 'Promotion Delete Failed', message: error instanceof Error ? error.message : 'Unable to delete promotion.' });
    }
  };

  return (
    <div className="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto space-y-6">
      {/* Page Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2">
            <span className="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
              Postgres / MySQL Engine
            </span>
            <span className="text-xs text-neutral-400">Rule-Condition-Action Pattern</span>
          </div>
          <h1 className="text-xl sm:text-2xl font-bold text-neutral-900 dark:text-white mt-1">
            Promotions & Marketing Rules
          </h1>
          <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
            Manage Coupons, Mix & Match Deals, Tiered Discounts, BOGO, Add-on Bundles, and Cashback Coins.
          </p>
        </div>

        {/* Global Action Header Controls */}
        <div className="flex items-center gap-2">
          <button
            onClick={() => setActiveTab('simulator')}
            className={`px-3.5 py-2 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition ${
              activeTab === 'simulator'
                ? 'bg-indigo-600 text-white shadow-xs'
                : 'bg-white dark:bg-neutral-900 text-neutral-700 dark:text-neutral-300 border border-neutral-200 dark:border-neutral-800 hover:bg-neutral-50 dark:hover:bg-neutral-800'
            }`}
          >
            <Sparkles className="w-3.5 h-3.5 text-amber-400" />
            <span>Test Simulator</span>
          </button>

          <button
            onClick={() => setIsCreateModalOpen(true)}
            className="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold flex items-center gap-1.5 shadow-sm transition cursor-pointer"
          >
            <Plus className="w-4 h-4" />
            <span>New Promotion</span>
          </button>
        </div>
      </div>

      {/* Main Tabs Navigation */}
      <div className="flex items-center gap-2 border-b border-neutral-200 dark:border-neutral-800">
        {[
          { id: 'campaigns', label: 'Active Campaigns', icon: Tag, count: promotions.length },
          { id: 'simulator', label: 'Rule Sandbox & Simulator', icon: Sparkles },
          { id: 'logs', label: 'Usage & Audit Ledger', icon: History, count: usages.length }
        ].map((tab) => {
          const Icon = tab.icon;
          const isActive = activeTab === tab.id;
          return (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id as any)}
              className={`pb-3 px-3 text-xs font-bold transition-all border-b-2 flex items-center gap-2 cursor-pointer ${
                isActive
                  ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
                  : 'border-transparent text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200'
              }`}
            >
              <Icon className="w-4 h-4" />
              <span>{tab.label}</span>
              {tab.count !== undefined && (
                <span className={`px-1.5 py-0.2 rounded-full text-[10px] ${
                  isActive 
                    ? 'bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300' 
                    : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-500'
                }`}>
                  {tab.count}
                </span>
              )}
            </button>
          );
        })}
      </div>

      {/* Active Tab View */}
      {activeTab === 'campaigns' && (
        <PromotionsListTab
          promotions={promotions}
          onAddPromotion={handleAddPromotion}
          onUpdatePromotion={handleUpdatePromotion}
          onDeletePromotion={handleDeletePromotion}
          onOpenCreateModal={() => setIsCreateModalOpen(true)}
          onOpenTestSandbox={() => setActiveTab('simulator')}
        />
      )}

      {activeTab === 'simulator' && (
        <PromotionSimulatorTab promotions={promotions} />
      )}

      {activeTab === 'logs' && (
        <PromotionLogsTab usages={usages} />
      )}

      {/* Create Promotion Modal */}
      <CreatePromotionModal
        isOpen={isCreateModalOpen}
        onClose={() => setIsCreateModalOpen(false)}
        onSave={handleAddPromotion}
      />
    </div>
  );
};
