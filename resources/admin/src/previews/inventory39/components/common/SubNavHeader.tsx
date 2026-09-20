import React from 'react';
import { useAdmin } from '../../context/AdminContext';
import { NavSection } from '../../types';
import { ChevronLeft, ChevronRight, LayoutGrid } from 'lucide-react';

interface SubNavHeaderProps {
  hubTitle?: string;
  hubSection?: NavSection;
  currentPageTitle?: string;
  tabs?: {
    id: NavSection;
    label: string;
    badge?: string | number;
    badgeVariant?: 'default' | 'success' | 'warning' | 'info';
  }[];
  parentSection?: NavSection;
  parentLabel?: string;
  currentLabel?: string;
  subtitle?: string;
  actions?: React.ReactNode;
}

export const SubNavHeader: React.FC<SubNavHeaderProps> = ({
  hubTitle,
  hubSection,
  currentPageTitle,
  tabs,
  parentSection,
  parentLabel,
  currentLabel,
  subtitle,
  actions,
}) => {
  const { currentSection, setCurrentSection } = useAdmin();

  const effectiveHubTitle = hubTitle || parentLabel || 'Marketplace';
  const effectiveHubSection: NavSection = hubSection || parentSection || 'marketplace';
  const effectivePageTitle = currentPageTitle || currentLabel || 'Overview';

  // Default fallback tabs if none passed and within marketplace
  const defaultMarketplaceTabs: SubNavHeaderProps['tabs'] = [
    { id: 'marketplace-auth' as NavSection, label: 'Connect Store' },
    { id: 'marketplace-mapping' as NavSection, label: 'Product & SKU Mapping' },
    { id: 'marketplace-oms' as NavSection, label: 'Orders (OMS)' },
    { id: 'marketplace-inventory' as NavSection, label: 'Inventory' },
    { id: 'marketplace-sync' as NavSection, label: 'Webhooks & Logs' },
  ];

  const safeTabs = (tabs && tabs.length > 0)
    ? tabs
    : (effectiveHubSection === 'marketplace' ? defaultMarketplaceTabs : []);

  return (
    <div className="mb-6 space-y-3">
      {/* Breadcrumb Bar */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400">
          <button
            onClick={() => setCurrentSection(effectiveHubSection)}
            className="flex items-center gap-1.5 font-semibold text-neutral-700 dark:text-neutral-300 hover:text-indigo-600 dark:hover:text-indigo-400 transition cursor-pointer"
          >
            <ChevronLeft className="w-3.5 h-3.5" />
            <span>{effectiveHubTitle} Hub</span>
          </button>
          <ChevronRight className="w-3.5 h-3.5 text-neutral-400" />
          <span className="font-bold text-neutral-900 dark:text-white">{effectivePageTitle}</span>
        </div>

        <div className="flex items-center gap-2">
          {actions}
          <button
            onClick={() => setCurrentSection(effectiveHubSection)}
            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-neutral-900 text-neutral-700 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white border border-neutral-200 dark:border-neutral-800 hover:border-neutral-300 dark:hover:border-neutral-700 transition shadow-2xs cursor-pointer"
          >
            <LayoutGrid className="w-3.5 h-3.5 text-neutral-500" />
            <span>All {effectiveHubTitle} Features</span>
          </button>
        </div>
      </div>

      {subtitle && (
        <p className="text-xs text-neutral-500 dark:text-neutral-400 max-w-3xl">
          {subtitle}
        </p>
      )}

      {/* Quick Tab Switcher */}
      {safeTabs.length > 0 && (
        <div className="flex items-center gap-1.5 p-1 bg-white dark:bg-neutral-900 rounded-xl border border-neutral-200 dark:border-neutral-800 overflow-x-auto scrollbar-thin shadow-2xs">
          <button
            onClick={() => setCurrentSection(effectiveHubSection)}
            className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer shrink-0 ${
              currentSection === effectiveHubSection
                ? 'bg-indigo-600 text-white shadow-xs'
                : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800'
            }`}
          >
            <LayoutGrid className="w-3.5 h-3.5" />
            <span>Overview</span>
          </button>

          {safeTabs.map((tab) => {
            const isActive = currentSection === tab.id;
            return (
              <button
                key={tab.id}
                onClick={() => setCurrentSection(tab.id)}
                className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer shrink-0 ${
                  isActive
                    ? 'bg-indigo-600 text-white shadow-xs'
                    : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800'
                }`}
              >
                <span>{tab.label}</span>
                {tab.badge !== undefined && (
                  <span
                    className={`text-[10px] px-1.5 py-0.2 rounded-full font-bold ${
                      isActive
                        ? 'bg-white/20 text-white'
                        : tab.badgeVariant === 'warning'
                        ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30'
                        : tab.badgeVariant === 'success'
                        ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30'
                        : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400'
                    }`}
                  >
                    {tab.badge}
                  </span>
                )}
              </button>
            );
          })}
        </div>
      )}
    </div>
  );
};
