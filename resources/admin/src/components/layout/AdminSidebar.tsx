import React, { useEffect, useState } from 'react';
import { useAdmin } from '../../context/AdminContext';
import { NavSection } from '../../types';
import {
  LayoutDashboard,
  Store,
  LayoutTemplate,
  Package,
  ShoppingBag,
  Settings,
  FileCode2,
  ChevronRight,
  ChevronDown,
  Zap,
  PanelLeftClose,
  Radio,
  Layers,
  Sparkles,
  Truck,
  CreditCard,
  MessageSquareText,
  BarChart3,
  Bot,
  Tag,
  Percent
} from 'lucide-react';

export const AdminSidebar: React.FC<{ isOpen: boolean; onCloseMobile?: () => void }> = ({
  isOpen,
  onCloseMobile
}) => {
  const {
    currentSection,
    setCurrentSection,
    products,
    orders,
    categories = [],
    pages = [],
    staffUsers = [],
    isSidebarCollapsed,
    toggleSidebarCollapse,
    connections = [],
    marketplaceOrders = [],
    marketplaceInventory = [],
    syncLogs = [],
    shipmentParcels = [],
    courierConfigs = [],
    paymentGateways = [],
    supportConversations = [],
    reviews = [],
    shopSettings
  } = useAdmin();

  const safeConns = connections || [];
  const safeMpOrders = marketplaceOrders || [];
  const safeOrders = orders || [];
  const safeShipmentParcels = shipmentParcels || [];
  const pendingAwbCount = safeShipmentParcels.filter(p => p.status === 'pending').length;
  const activeGatewaysCount = paymentGateways.filter(g => g.isEnabled).length;
  const unreadSupportCount = supportConversations.reduce((acc, c) => acc + (c.unreadCount || 0), 0);
  const pendingReviewsCount = reviews.filter((review) => review.status === 'pending').length;

  const [expandedHubs, setExpandedHubs] = useState<{ [key: string]: boolean }>({
    marketplace: false,
    storefront: false,
    catalog: false,
    commerce: false,
    reports: false,
    settings: false,
  });

  useEffect(() => {
    if (!isOpen) return;

    const handleEscape = (e: KeyboardEvent) => {
      if (e.key !== 'Escape') return;
      e.preventDefault();
      onCloseMobile?.();
      window.requestAnimationFrame(() => document.getElementById('btn-sidebar-toggle')?.focus());
    };

    document.addEventListener('keydown', handleEscape);
    return () => document.removeEventListener('keydown', handleEscape);
  }, [isOpen, onCloseMobile]);

  const toggleExpand = (hubKey: string, e: React.MouseEvent) => {
    e.stopPropagation();
    setExpandedHubs((prev) => ({ ...prev, [hubKey]: !prev[hubKey] }));
  };

  const pendingMpOrders = safeMpOrders.filter(
    (o) => o.channelStatus === 'AWAITING_COLLECTION' || o.channelStatus === 'UNPAID' || !o.airwayBillGenerated
  ).length;
  const connectedStoresCount = safeConns.filter((c) => c.status === 'connected').length;
  const pendingStoreOrders = safeOrders.filter(
    (o) => o.fulfillmentStatus === 'processing' || o.fulfillmentStatus === 'unfulfilled'
  ).length;

  const handleSelect = (id: NavSection) => {
    setCurrentSection(id);
    if (onCloseMobile) onCloseMobile();
  };

  // Check which hub is active
  const isMarketplaceActive =
    currentSection === 'marketplace' ||
    currentSection === 'marketplace-auth' ||
    currentSection === 'marketplace-oms' ||
    currentSection === 'marketplace-inventory' ||
    currentSection === 'marketplace-sync';

  const isStorefrontActive =
    currentSection === 'storefront' ||
    currentSection === 'storefront-header-announcement' ||
    currentSection === 'pages-builder' ||
    currentSection === 'menu-header' ||
    currentSection === 'menu-footer';

  const isCatalogActive =
    currentSection === 'catalog' ||
    currentSection === 'catalog-products' ||
    currentSection === 'catalog-reviews' ||
    currentSection === 'catalog-categories' ||
    currentSection === 'catalog-collections';

  const isCommerceActive =
    currentSection === 'commerce' ||
    currentSection === 'orders' ||
    currentSection === 'customers';

  const isShippingActive = currentSection === 'shipping-malaysia';

  const isPaymentActive = currentSection === 'settings-payment';

  const isReportsActive =
    currentSection === 'reports' ||
    currentSection === 'reports-sales' ||
    currentSection === 'reports-orders' ||
    currentSection === 'reports-products' ||
    currentSection === 'reports-customers' ||
    currentSection === 'reports-shipping' ||
    currentSection === 'reports-tax';

  const isSettingsActive = (currentSection === 'settings' || currentSection.startsWith('settings-')) && currentSection !== 'settings-payment';

  // Organized, Prioritized Nav Sections by Operational Workflow
  const coreOperationsNav = [
    {
      id: 'dashboard' as NavSection,
      label: 'Dashboard',
      icon: LayoutDashboard,
      isActive: currentSection === 'dashboard',
    },
    {
      id: 'commerce' as NavSection,
      label: 'Orders & CRM',
      icon: ShoppingBag,
      isActive: isCommerceActive,
      badge: pendingStoreOrders > 0 ? `${pendingStoreOrders} New` : undefined,
      badgeVariant: 'warning',
      subItems: [
        { id: 'commerce' as NavSection, label: 'Overview Hub' },
        { id: 'orders' as NavSection, label: 'Orders & Fulfillment' },
        { id: 'customers' as NavSection, label: 'Customer Directory' },
      ],
    },
    {
      id: 'shipping-malaysia' as NavSection,
      label: 'Shipping & Logistics',
      icon: Truck,
      isActive: isShippingActive,
      badge: pendingAwbCount > 0 ? `${pendingAwbCount} AWB` : 'Manual',
      badgeVariant: pendingAwbCount > 0 ? 'warning' : 'success',
    },
    {
      id: 'catalog' as NavSection,
      label: 'Products & Catalog',
      icon: Package,
      isActive: isCatalogActive,
      badge: pendingReviewsCount > 0 ? `${pendingReviewsCount} Review${pendingReviewsCount > 1 ? 's' : ''}` : `${products.length} Items`,
      badgeVariant: pendingReviewsCount > 0 ? 'warning' : 'default',
      subItems: [
        { id: 'catalog' as NavSection, label: 'Overview Hub' },
        { id: 'catalog-products' as NavSection, label: 'Product Matrix' },
        { id: 'catalog-reviews' as NavSection, label: 'Product Reviews' },
        { id: 'catalog-categories' as NavSection, label: 'Categories' },
        { id: 'catalog-collections' as NavSection, label: 'Collections' },
      ],
    },
    {
      id: 'promotions' as NavSection,
      label: 'Promotions & Discounts',
      icon: Tag,
      isActive: currentSection === 'promotions',
      badge: 'Mix&Match',
      badgeVariant: 'warning',
    },
  ];

  const salesChannelsNav = [
    {
      id: 'marketplace' as NavSection,
      label: 'Marketplace OMS',
      icon: Store,
      isActive: isMarketplaceActive,
      badge: pendingMpOrders > 0 ? `${pendingMpOrders} Pending` : `${connectedStoresCount}/4`,
      badgeVariant: pendingMpOrders > 0 ? 'warning' : 'success',
      subItems: [
        { id: 'marketplace' as NavSection, label: 'Overview Hub' },
        { id: 'marketplace-auth' as NavSection, label: 'Open Platform Auth' },
        { id: 'marketplace-oms' as NavSection, label: 'Marketplace OMS' },
        { id: 'marketplace-inventory' as NavSection, label: 'Marketplace Inventory' },
        { id: 'marketplace-sync' as NavSection, label: 'Webhooks & Sync Stream' },
      ],
    },
    {
      id: 'storefront' as NavSection,
      label: 'Storefront CMS',
      icon: LayoutTemplate,
      isActive: isStorefrontActive,
      badge: `${pages.length} Pages`,
      badgeVariant: 'info',
      subItems: [
        { id: 'storefront' as NavSection, label: 'Overview Hub' },
        { id: 'storefront-header-announcement' as NavSection, label: 'Header & Announcement' },
        { id: 'pages-builder' as NavSection, label: 'Page Builder' },
        { id: 'menu-header' as NavSection, label: 'Header Mega Menu' },
        { id: 'menu-footer' as NavSection, label: 'Footer Menu' },
      ],
    },
  ];

  const customerCareNav = [
    {
      id: 'support' as NavSection,
      label: 'Customer Support',
      icon: MessageSquareText,
      isActive: currentSection === 'support',
      badge: unreadSupportCount > 0 ? `${unreadSupportCount} New` : 'Inbox',
      badgeVariant: unreadSupportCount > 0 ? 'warning' : 'info',
    },
    {
      id: 'ai-auto-reply' as NavSection,
      label: 'AI Auto Reply',
      icon: Bot,
      isActive: currentSection === 'ai-auto-reply' || currentSection === 'settings-ai-agent',
      badge: 'Agent Studio',
      badgeVariant: 'success',
    },
  ];

  const analyticsNav = [
    {
      id: 'reports' as NavSection,
      label: 'Reports & Analytics',
      icon: BarChart3,
      isActive: isReportsActive,
      badge: 'Analytics',
      badgeVariant: 'info',
      subItems: [
        { id: 'reports-sales' as NavSection, label: 'Sales & Revenue' },
        { id: 'reports-orders' as NavSection, label: 'Orders & Funnel' },
        { id: 'reports-products' as NavSection, label: 'Products & Velocity' },
        { id: 'reports-customers' as NavSection, label: 'Customers & RFM' },
        { id: 'reports-shipping' as NavSection, label: 'Logistics SLA' },
        { id: 'reports-tax' as NavSection, label: 'Tax & Gateway P&L' },
      ],
    },
    {
      id: 'settings-payment' as NavSection,
      label: 'Payment Gateways',
      icon: CreditCard,
      isActive: isPaymentActive,
      badge: activeGatewaysCount > 0 ? `${activeGatewaysCount} Active` : `${paymentGateways.length} Gateways`,
      badgeVariant: activeGatewaysCount > 0 ? 'success' : 'default',
    },
  ];

  const navGroups = [
    {
      title: 'Core Operations',
      items: coreOperationsNav,
    },
    {
      title: 'Sales Channels',
      items: salesChannelsNav,
    },
    {
      title: 'Customer Care & AI',
      items: customerCareNav,
    },
    {
      title: 'Analytics & Finance',
      items: analyticsNav,
    },
  ];

  const settingSubItems: { id: NavSection; label: string }[] = [
    { id: 'settings-ai-agent', label: 'AI Auto Reply & Bot' },
    { id: 'settings-users', label: 'User Login & Staff' },
    { id: 'settings-shop', label: 'Shop Profile & SEO' },
    { id: 'settings-points', label: 'Points & Rewards' },
    { id: 'settings-invoice', label: 'Invoice & Taxes' },
    { id: 'settings-email', label: 'Email Templates' },
    { id: 'settings-general', label: 'General Configuration' },
  ];

  return (
    <aside
      id="admin-sidebar"
      className={`fixed inset-y-0 left-0 z-40 w-64 flex flex-col transition-all duration-300 ease-in-out border-r
        ${
          isOpen
            ? 'translate-x-0'
            : isSidebarCollapsed
            ? '-translate-x-full'
            : '-translate-x-full lg:translate-x-0'
        }
        bg-white border-neutral-200 text-neutral-800
        dark:bg-neutral-950 dark:border-neutral-800 dark:text-neutral-200 shadow-2xl lg:shadow-none
      `}
    >
      {/* Brand Header */}
      <div className="h-16 flex items-center justify-between px-4 border-b border-neutral-200 dark:border-neutral-800 bg-white/80 dark:bg-neutral-950/60 backdrop-blur-md">
        <div
          onClick={() => handleSelect('dashboard')}
          className="flex items-center gap-2.5 cursor-pointer group min-w-0"
        >
          {shopSettings?.shopLogo ? (
            <div className="w-8 h-8 rounded-xl overflow-hidden bg-neutral-950 flex items-center justify-center shadow-md shadow-indigo-500/20 ring-1 ring-neutral-700/50 group-hover:scale-105 transition-transform flex-shrink-0">
              <img
                src={shopSettings.shopLogo}
                alt={shopSettings.shopName || 'JW Studio'}
                className="w-full h-full object-cover"
                referrerPolicy="no-referrer"
              />
            </div>
          ) : (
            <div className="w-8 h-8 rounded-xl bg-gradient-to-tr from-indigo-600 via-violet-600 to-indigo-700 flex items-center justify-center shadow-md shadow-indigo-500/20 ring-1 ring-white/20 group-hover:scale-105 transition-transform flex-shrink-0">
              <Zap className="w-4 h-4 text-white" />
            </div>
          )}
          <div className="min-w-0 flex-1">
            <div className="flex items-center gap-1.5">
              <span className="font-bold text-sm tracking-tight text-neutral-900 dark:text-white truncate">
                {shopSettings?.shopName ? shopSettings.shopName.toUpperCase() : 'JW STUDIO'}
              </span>
              <span className="text-[9px] uppercase font-bold px-1.5 py-0.2 rounded bg-indigo-50 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-500/30 flex-shrink-0">
                OS
              </span>
            </div>
            <p className="text-[10px] text-neutral-400 font-medium leading-none truncate">
              {shopSettings?.tagline ? shopSettings.tagline : 'Universal Admin'}
            </p>
          </div>
        </div>

        {/* Collapse Sidebar Button */}
        <button
          onClick={toggleSidebarCollapse}
          className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors cursor-pointer"
          title="Collapse Sidebar"
          aria-label="Collapse Navigation"
        >
          <PanelLeftClose className="w-4 h-4" />
        </button>
      </div>

      {/* Navigation Group Items */}
      <div className="flex-1 overflow-y-auto px-3 py-4 space-y-5 scrollbar-thin scrollbar-thumb-neutral-200 dark:scrollbar-thumb-neutral-800">
        {/* Dynamic Operational Groups */}
        {navGroups.map((group) => (
          <div key={group.title} className="space-y-1">
            <div className="px-3 pb-1 text-[10px] font-bold tracking-wider text-neutral-400 dark:text-neutral-500 uppercase">
              {group.title}
            </div>

            {group.items.map((item) => {
              const Icon = item.icon;
              const hasSub = item.subItems && item.subItems.length > 0;
              const isExpanded = expandedHubs[item.id as string];

              return (
                <div key={item.id} className="space-y-0.5">
                  <div
                    id={`nav-btn-${item.id}`}
                    onClick={() => handleSelect(item.id)}
                    className={`group relative flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold transition-all duration-150 text-left cursor-pointer
                      ${
                        item.isActive
                          ? 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 shadow-xs'
                          : 'text-neutral-700 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800/60 border border-transparent'
                      }
                    `}
                  >
                    <div className="flex items-center gap-2.5 min-w-0">
                      <Icon
                        className={`w-4 h-4 flex-shrink-0 transition-colors ${
                          item.isActive
                            ? 'text-indigo-600 dark:text-indigo-400'
                            : 'text-neutral-400 group-hover:text-neutral-600 dark:group-hover:text-neutral-200'
                        }`}
                      />
                      <span className="truncate">{item.label}</span>
                    </div>

                    <div className="flex items-center gap-1.5 flex-shrink-0">
                      {item.badge && (
                        <span
                          className={`text-[10px] font-bold px-1.5 py-0.2 rounded-full ${
                            item.badgeVariant === 'warning'
                              ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30'
                              : item.badgeVariant === 'success'
                              ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30'
                              : item.badgeVariant === 'info'
                              ? 'bg-cyan-500/15 text-cyan-700 dark:text-cyan-300 border border-cyan-500/30'
                              : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400'
                          }`}
                        >
                          {item.badge}
                        </span>
                      )}

                      {hasSub && (
                        <button
                          type="button"
                          onClick={(e) => toggleExpand(item.id as string, e)}
                          className="p-1 rounded hover:bg-neutral-200 dark:hover:bg-neutral-800 text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 transition-colors"
                        >
                          <ChevronDown
                            className={`w-3.5 h-3.5 transition-transform ${isExpanded ? 'rotate-180' : ''}`}
                          />
                        </button>
                      )}
                    </div>
                  </div>

                  {/* Optional Expandable Sub-items */}
                  {hasSub && isExpanded && (
                    <div className="ml-5 pl-2.5 py-1 space-y-0.5 border-l border-neutral-200 dark:border-neutral-800">
                      {item.subItems?.map((sub) => {
                        const isSubActive = currentSection === sub.id;
                        const isReviewSubItem = sub.id === 'catalog-reviews';
                        return (
                          <button
                            key={sub.id}
                            type="button"
                            onClick={() => handleSelect(sub.id)}
                            className={`w-full text-left px-2.5 py-1.5 rounded-lg text-[11px] font-medium transition cursor-pointer flex items-center justify-between ${
                              isSubActive
                                ? 'bg-indigo-600 text-white font-bold shadow-xs'
                                : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800/40'
                            }`}
                          >
                            <span className="truncate">{sub.label}</span>
                            <div className="flex items-center gap-1.5 flex-shrink-0">
                              {isReviewSubItem && pendingReviewsCount > 0 && (
                                <span
                                  className={`text-[9px] font-bold px-1.5 py-0.2 rounded-full ${
                                    isSubActive
                                      ? 'bg-white/20 text-white'
                                      : 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30'
                                  }`}
                                >
                                  {pendingReviewsCount} Pending
                                </span>
                              )}
                              {isSubActive && <div className="w-1.5 h-1.5 rounded-full bg-white" />}
                            </div>
                          </button>
                        );
                      })}
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        ))}

        {/* System & Configuration Section */}
        <div className="space-y-1">
          <div className="px-3 pb-1.5 text-[10px] font-bold tracking-wider text-neutral-400 uppercase">
            Preferences & Specs
          </div>

          {/* Store Settings Accordion */}
          <div className="space-y-0.5">
            <div
              id="nav-btn-settings"
              onClick={() => handleSelect('settings')}
              className={`group flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-semibold transition-all duration-150 text-left cursor-pointer
                ${
                  isSettingsActive
                    ? 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 shadow-xs'
                    : 'text-neutral-700 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800/60 border border-transparent'
                }
              `}
            >
              <div className="flex items-center gap-2.5 min-w-0">
                <Settings
                  className={`w-4 h-4 flex-shrink-0 ${
                    isSettingsActive ? 'text-indigo-600 dark:text-indigo-400' : 'text-neutral-400 group-hover:text-neutral-600 dark:group-hover:text-neutral-200'
                  }`}
                />
                <span className="truncate">Settings</span>
              </div>

              <div className="flex items-center gap-1.5">
                <span className="text-[10px] font-bold px-1.5 py-0.2 rounded-full bg-indigo-50 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-500/30">
                  {staffUsers.length} Staff
                </span>
                <button
                  type="button"
                  onClick={(e) => toggleExpand('settings', e)}
                  className="p-1 rounded hover:bg-neutral-200 dark:hover:bg-neutral-800 text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 transition-colors"
                >
                  <ChevronDown
                    className={`w-3.5 h-3.5 transition-transform ${expandedHubs.settings ? 'rotate-180' : ''}`}
                  />
                </button>
              </div>
            </div>

            {expandedHubs.settings && (
              <div className="ml-5 pl-2.5 py-1 space-y-0.5 border-l border-neutral-200 dark:border-neutral-800">
                {settingSubItems.map((subItem) => {
                  const isCurrent = currentSection === subItem.id;
                  return (
                    <button
                      key={subItem.id}
                      id={`nav-btn-${subItem.id}`}
                      type="button"
                      onClick={() => handleSelect(subItem.id)}
                      className={`w-full flex items-center justify-between px-2.5 py-1.5 rounded-lg text-[11px] transition cursor-pointer text-left ${
                        isCurrent
                          ? 'bg-indigo-600 text-white font-bold shadow-xs'
                          : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800/40'
                      }`}
                    >
                      <span className="truncate">{subItem.label}</span>
                    </button>
                  );
                })}
              </div>
            )}
          </div>

          {/* Developer Architecture Specs */}
          <button
            id="nav-btn-architecture-spec"
            onClick={() => handleSelect('architecture-spec')}
            className={`w-full group flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-semibold transition-all duration-150 text-left cursor-pointer
              ${
                currentSection === 'architecture-spec'
                  ? 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 shadow-xs'
                  : 'text-neutral-700 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800/60 border border-transparent'
              }
            `}
          >
            <div className="flex items-center gap-2.5 min-w-0">
              <FileCode2
                className={`w-4 h-4 flex-shrink-0 ${
                  currentSection === 'architecture-spec'
                    ? 'text-indigo-600 dark:text-indigo-400'
                    : 'text-neutral-400 group-hover:text-neutral-600 dark:group-hover:text-neutral-200'
                }`}
              />
              <span className="truncate">Developer Specs</span>
            </div>
            <span className="text-[10px] font-semibold px-1.5 py-0.2 rounded-full bg-cyan-500/15 text-cyan-700 dark:text-cyan-300 border border-cyan-500/30">
              API
            </span>
          </button>
        </div>
      </div>

      {/* Footer Storefront Status Banner */}
      <div className="p-3 border-t border-neutral-200 dark:border-neutral-800 bg-neutral-50/80 dark:bg-neutral-950/80">
        <div className="p-2.5 rounded-xl bg-white dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 space-y-1.5 shadow-xs">
          <div className="flex items-center justify-between text-xs font-semibold">
            <span className="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 text-[11px]">
              <span className="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
              Storefront Online
            </span>
            <span className="text-[10px] font-mono text-neutral-400">v2.4</span>
          </div>
        </div>
      </div>
    </aside>
  );
};
