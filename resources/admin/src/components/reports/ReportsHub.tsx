import React, { useState } from 'react';
import { useAdmin } from '../../context/AdminContext';
import { NavSection } from '../../types';
import { ReportDateRange, ReportChannelFilter } from '../../types/reports';
import { SalesReportView } from './SalesReportView';
import { OrdersReportView } from './OrdersReportView';
import { ProductsReportView } from './ProductsReportView';
import { CustomersReportView } from './CustomersReportView';
import { LogisticsReportView } from './LogisticsReportView';
import { MarketingReportView } from './MarketingReportView';
import { FinancialTaxReportView } from './FinancialTaxReportView';
import {
  BarChart3,
  TrendingUp,
  ShoppingBag,
  Package,
  Users,
  Truck,
  Megaphone,
  Receipt,
  Calendar,
  Filter,
  Download,
  Sparkles,
  FileSpreadsheet,
  CheckCircle2,
  RefreshCw
} from 'lucide-react';

export type ReportTab =
  | 'sales'
  | 'orders'
  | 'products'
  | 'customers'
  | 'logistics'
  | 'marketing'
  | 'tax';

export const ReportsHub: React.FC = () => {
  const { addToast, currentSection, setCurrentSection } = useAdmin();
  const [dateRange, setDateRange] = useState<ReportDateRange>('30d');
  const [channelFilter, setChannelFilter] = useState<ReportChannelFilter>('all');
  const [isRefreshing, setIsRefreshing] = useState(false);

  // Derive initial tab from currentSection
  const getActiveTab = (): ReportTab => {
    switch (currentSection) {
      case 'reports-sales':
        return 'sales';
      case 'reports-orders':
        return 'orders';
      case 'reports-products':
        return 'products';
      case 'reports-customers':
        return 'customers';
      case 'reports-shipping':
        return 'logistics';
      case 'reports-tax':
        return 'tax';
      default:
        return 'sales';
    }
  };

  const [activeTab, setActiveTab] = useState<ReportTab>(getActiveTab());

  const handleTabChange = (tab: ReportTab) => {
    setActiveTab(tab);
    switch (tab) {
      case 'sales':
        setCurrentSection('reports-sales');
        break;
      case 'orders':
        setCurrentSection('reports-orders');
        break;
      case 'products':
        setCurrentSection('reports-products');
        break;
      case 'customers':
        setCurrentSection('reports-customers');
        break;
      case 'logistics':
        setCurrentSection('reports-shipping');
        break;
      case 'tax':
        setCurrentSection('reports-tax');
        break;
      default:
        setCurrentSection('reports');
    }
  };

  const handleExport = (reportName: string) => {
    addToast({
      type: 'success',
      title: 'Report Export Generated',
      message: `Successfully compiled "${reportName} (${dateRange.toUpperCase()})". CSV & Excel download ready.`
    });
  };

  const handleRefresh = () => {
    setIsRefreshing(true);
    setTimeout(() => {
      setIsRefreshing(false);
      addToast({
        type: 'info',
        title: 'Report Data Synchronized',
        message: 'Aggregated latest real-time metrics across all 5 sales channels & POS.'
      });
    }, 600);
  };

  const tabs = [
    { id: 'sales' as ReportTab, label: 'Sales & Revenue', icon: TrendingUp, badge: 'MYR' },
    { id: 'orders' as ReportTab, label: 'Orders & Fulfillment', icon: ShoppingBag },
    { id: 'products' as ReportTab, label: 'Products & Inventory', icon: Package, badge: 'Stock' },
    { id: 'customers' as ReportTab, label: 'Customer Cohorts & RFM', icon: Users },
    { id: 'logistics' as ReportTab, label: 'Courier & Logistics SLA', icon: Truck },
    { id: 'marketing' as ReportTab, label: 'Marketing & Conversion', icon: Megaphone },
    { id: 'tax' as ReportTab, label: 'Tax & Gateway P&L', icon: Receipt, badge: 'SST' }
  ];

  return (
    <div className="space-y-6">
      {/* 1. Top Header Banner */}
      <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4 p-6 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
        <div>
          <div className="flex items-center gap-2.5">
            <div className="p-2 rounded-xl bg-indigo-600 text-white shadow-xs">
              <BarChart3 className="w-5 h-5" />
            </div>
            <div>
              <h1 className="text-xl font-black text-neutral-900 dark:text-neutral-50 tracking-tight">
                Enterprise Analytics & Reports Center
              </h1>
              <p className="text-xs text-neutral-500 dark:text-neutral-400">
                Omnichannel performance intelligence, inventory velocity, RFM customer cohorts, and SST tax schedules.
              </p>
            </div>
          </div>
        </div>

        {/* Global Controls: Date Range, Channel Filter & Actions */}
        <div className="flex items-center gap-2.5 flex-wrap">
          {/* Channel Selector */}
          <div className="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 text-xs">
            <Filter className="w-3.5 h-3.5 text-neutral-400" />
            <select
              id="select-report-channel"
              value={channelFilter}
              onChange={e => setChannelFilter(e.target.value as ReportChannelFilter)}
              className="bg-transparent font-medium text-neutral-800 dark:text-neutral-200 focus:outline-none cursor-pointer"
            >
              <option value="all">All Channels (Omnichannel)</option>
              <option value="web_direct">JW Direct Storefront</option>
              <option value="tiktok_shop">TikTok Shop Malaysia</option>
              <option value="shopee">Shopee Official Mall</option>
              <option value="lazada">Lazada LazMall</option>
              <option value="whatsapp_b2b">WhatsApp B2B</option>
            </select>
          </div>

          {/* Date Range Selector */}
          <div className="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 text-xs">
            <Calendar className="w-3.5 h-3.5 text-neutral-400" />
            <select
              id="select-report-daterange"
              value={dateRange}
              onChange={e => setDateRange(e.target.value as ReportDateRange)}
              className="bg-transparent font-medium text-neutral-800 dark:text-neutral-200 focus:outline-none cursor-pointer"
            >
              <option value="today">Today (Live)</option>
              <option value="yesterday">Yesterday</option>
              <option value="7d">Last 7 Days</option>
              <option value="30d">Last 30 Days (Default)</option>
              <option value="90d">Last 90 Days (Quarter)</option>
              <option value="this_month">This Month (August 2026)</option>
              <option value="ytd">Year to Date (YTD 2026)</option>
            </select>
          </div>

          {/* Refresh Action */}
          <button
            id="btn-refresh-reports"
            onClick={handleRefresh}
            className={`p-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-all ${
              isRefreshing ? 'animate-spin' : ''
            }`}
            title="Sync Data"
          >
            <RefreshCw className="w-3.5 h-3.5" />
          </button>

          {/* Global Master Export Button */}
          <button
            id="btn-export-master-report"
            onClick={() => handleExport('Master-Ecommerce-Executive-Report')}
            className="px-3.5 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs flex items-center gap-1.5 shadow-xs transition-all cursor-pointer"
          >
            <Download className="w-3.5 h-3.5" />
            <span>Export Full Suite</span>
          </button>
        </div>
      </div>

      {/* 2. AI Executive Summary Callout */}
      <div className="p-4.5 rounded-2xl bg-gradient-to-r from-indigo-500/10 via-purple-500/10 to-pink-500/10 border border-indigo-200/60 dark:border-indigo-800/60 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div className="flex items-center gap-3">
          <div className="p-2 rounded-xl bg-indigo-600 text-white shrink-0 shadow-xs">
            <Sparkles className="w-4 h-4" />
          </div>
          <div>
            <div className="flex items-center gap-2">
              <span className="text-xs font-bold text-indigo-900 dark:text-indigo-200">
                JW Studio AI Strategic Brief
              </span>
              <span className="px-1.5 py-0.5 rounded text-[10px] font-mono font-bold bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300">
                Automated
              </span>
            </div>
            <p className="text-xs text-neutral-700 dark:text-neutral-300 mt-0.5">
              Net revenue is up <span className="font-bold text-emerald-600 dark:text-emerald-400">+16.2%</span> driven by TikTok Live conversions. Recommended action: restock <span className="font-semibold">JW 280GSM Luxury Tee (Black)</span> before inventory exhausts in 9 days.
            </p>
          </div>
        </div>
        <button
          id="btn-switch-to-products-ai"
          onClick={() => handleTabChange('products')}
          className="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline shrink-0 flex items-center gap-1"
        >
          <span>View Inventory Restock</span>
          <span>→</span>
        </button>
      </div>

      {/* 3. Navigation Sub-Tabs */}
      <div className="flex items-center gap-1.5 overflow-x-auto pb-1 border-b border-neutral-200 dark:border-neutral-800 no-scrollbar">
        {tabs.map(t => {
          const Icon = t.icon;
          const isActive = activeTab === t.id;
          return (
            <button
              key={t.id}
              id={`tab-report-${t.id}`}
              onClick={() => handleTabChange(t.id)}
              className={`flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all cursor-pointer ${
                isActive
                  ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900 shadow-xs'
                  : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-200/60 dark:hover:bg-neutral-800/60'
              }`}
            >
              <Icon className="w-3.5 h-3.5" />
              <span>{t.label}</span>
              {t.badge && (
                <span
                  className={`px-1.5 py-0.2 rounded text-[10px] font-mono ${
                    isActive
                      ? 'bg-neutral-700 text-neutral-200 dark:bg-neutral-200 dark:text-neutral-800'
                      : 'bg-neutral-200 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300'
                  }`}
                >
                  {t.badge}
                </span>
              )}
            </button>
          );
        })}
      </div>

      {/* 4. Active Report Content Section */}
      <div>
        {activeTab === 'sales' && (
          <SalesReportView channelFilter={channelFilter} onExport={handleExport} />
        )}
        {activeTab === 'orders' && (
          <OrdersReportView onExport={handleExport} />
        )}
        {activeTab === 'products' && (
          <ProductsReportView onExport={handleExport} />
        )}
        {activeTab === 'customers' && (
          <CustomersReportView onExport={handleExport} />
        )}
        {activeTab === 'logistics' && (
          <LogisticsReportView onExport={handleExport} />
        )}
        {activeTab === 'marketing' && (
          <MarketingReportView onExport={handleExport} />
        )}
        {activeTab === 'tax' && (
          <FinancialTaxReportView onExport={handleExport} />
        )}
      </div>
    </div>
  );
};
