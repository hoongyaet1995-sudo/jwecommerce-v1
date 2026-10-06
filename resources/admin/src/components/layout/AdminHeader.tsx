import React, { useState, useRef, useEffect } from 'react';
import { useAdmin } from '../../context/AdminContext';
import {
  Search,
  Moon,
  Sun,
  Bell,
  Menu,
  ShieldCheck,
  RotateCcw,
  LogOut,
  Command,
  ChevronDown,
  ExternalLink,
  Laptop,
  PanelLeftOpen,
  PanelLeftClose
} from 'lucide-react';

export const AdminHeader: React.FC<{ onToggleMobileSidebar: () => void }> = ({
  onToggleMobileSidebar
}) => {
  const {
    currentSection,
    setCurrentSection,
    theme,
    toggleTheme,
    isSidebarCollapsed,
    toggleSidebarCollapse,
    user,
    logout,
    setIsCommandPaletteOpen,
    supportAlertUnread,
    isNotificationOpen,
    setIsNotificationOpen,
    setIsLoginModalOpen,
    login,
    resetToDefaults
  } = useAdmin();

  const [isProfileMenuOpen, setIsProfileMenuOpen] = useState(false);
  const profileMenuRef = useRef<HTMLDivElement>(null);
  const profileTriggerRef = useRef<HTMLButtonElement>(null);

  // Close profile dropdown on outside click
  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (profileMenuRef.current && !profileMenuRef.current.contains(e.target as Node)) {
        setIsProfileMenuOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  useEffect(() => {
    if (!isProfileMenuOpen) return;

    const handleEscape = (e: KeyboardEvent) => {
      if (e.key !== 'Escape') return;
      e.preventDefault();
      setIsProfileMenuOpen(false);
      window.requestAnimationFrame(() => profileTriggerRef.current?.focus());
    };

    document.addEventListener('keydown', handleEscape);
    return () => document.removeEventListener('keydown', handleEscape);
  }, [isProfileMenuOpen]);

  const getSectionTitle = () => {
    if (currentSection === 'shipping-malaysia') {
      return { category: 'Logistics & Couriers', title: 'Shipping & Delivery Management' };
    }
    if (currentSection === 'settings-payment') {
      return { category: 'Finance & Payment', title: 'Payment Gateways & Methods' };
    }
    if (currentSection.startsWith('settings-') || currentSection === 'settings') {
      return { category: 'Preferences', title: 'Store Settings' };
    }

    switch (currentSection) {
      case 'integration': return { category: 'Connections', title: 'Integration' };
      case 'integration-ai-connect': return { category: 'Integration', title: 'AI Connect' };
      case 'dashboard': return { category: 'Executive', title: 'Dashboard Overview' };
      case 'marketplace': return { category: 'Marketplace Ecosystem', title: 'Overview Hub' };
      case 'marketplace-auth': return { category: 'Marketplace Ecosystem', title: 'Connect Store' };
      case 'marketplace-mapping': return { category: 'Marketplace Ecosystem', title: 'Product & SKU Mapping' };
      case 'marketplace-oms': return { category: 'Marketplace Ecosystem', title: 'Marketplace Orders (OMS)' };
      case 'marketplace-inventory': return { category: 'Marketplace Ecosystem', title: 'Inventory & WMS Allocation' };
      case 'marketplace-sync': return { category: 'Marketplace Ecosystem', title: 'Webhooks & Sync Stream' };
      case 'storefront': return { category: 'Storefront CMS', title: 'Storefront Overview Hub' };
      case 'pages-builder': return { category: 'Storefront CMS', title: 'Design Layout Pages (CMS)' };
      case 'menu-header': return { category: 'Storefront CMS', title: '3-Level Header Menu Builder' };
      case 'menu-footer': return { category: 'Storefront CMS', title: '2-Level Footer Menu Builder' };
      case 'catalog': return { category: 'Catalog Management', title: 'Catalog Overview Hub' };
      case 'catalog-categories': return { category: 'Catalog Management', title: 'Category Taxonomy' };
      case 'catalog-collections': return { category: 'Catalog Management', title: 'Collections & Dynamic Rules' };
      case 'catalog-products': return { category: 'Catalog Management', title: 'Products & SKU Variant Matrix' };
      case 'support-overview': return { category: 'Customer Care & AI', title: 'Customer Support Overview' };
      case 'support-tiktok-prototype': return { category: 'Customer Care & AI', title: 'TikTok Review Prototype' };
      case 'support-tiktok': return { category: 'Customer Care & AI', title: 'TikTok Account Management' };
      case 'support-whatsapp': return { category: 'Customer Care & AI', title: 'WhatsApp Account Management' };
      case 'support-instagram': return { category: 'Customer Care & AI', title: 'Instagram Account Management' };
      case 'support-facebook': return { category: 'Customer Care & AI', title: 'Facebook Account Management' };
      case 'commerce': return { category: 'Orders & Customers', title: 'Commerce Overview Hub' };
      case 'customers': return { category: 'Orders & Customers', title: 'Customer Directory' };
      case 'orders': return { category: 'Orders & Customers', title: 'Store Orders & Fulfillment' };
      case 'architecture-spec': return { category: 'Engineering Specs', title: 'Route Specs & JSON Schemas' };
      default: return { category: 'Admin', title: 'Dashboard' };
    }
  };

  const { category, title } = getSectionTitle();

  return (
    <header
      id="admin-header"
      className="sticky top-0 z-30 h-16 w-full flex items-center justify-between px-4 sm:px-6 border-b transition-colors
        bg-white/90 dark:bg-neutral-900/90 backdrop-blur-md
        border-neutral-200 dark:border-neutral-800
      "
    >
      {/* Left side: Hamburger (mobile) / Sidebar toggle (desktop) + Breadcrumb */}
      <div className="flex items-center gap-3">
        {/* Mobile Drawer Trigger */}
        <button
          id="btn-sidebar-toggle"
          onClick={onToggleMobileSidebar}
          className="lg:hidden p-2 rounded-xl text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
          aria-label="Toggle Navigation"
        >
          <Menu className="w-5 h-5" />
        </button>

        {/* Desktop Sidebar Collapse / Expand Toggle */}
        <button
          id="btn-sidebar-toggle-desktop"
          onClick={toggleSidebarCollapse}
          className={`hidden lg:flex items-center justify-center p-2 rounded-xl border transition-all ${
            isSidebarCollapsed
              ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border-indigo-200 dark:border-indigo-800 hover:bg-indigo-100 dark:hover:bg-indigo-900 shadow-xs'
              : 'text-neutral-500 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 border-neutral-200/60 dark:border-neutral-800'
          }`}
          title={isSidebarCollapsed ? 'Expand Main Sidebar (Show Menu)' : 'Collapse Main Sidebar (Zen / Wide View)'}
          aria-label="Toggle Desktop Sidebar"
        >
          {isSidebarCollapsed ? <PanelLeftOpen className="w-4 h-4" /> : <PanelLeftClose className="w-4 h-4" />}
        </button>

        <div className="flex flex-col">
          <span className="text-[10px] font-semibold text-neutral-600 dark:text-neutral-400 uppercase tracking-wider">
            {category}
          </span>
          <h1 className="text-sm sm:text-base font-bold text-neutral-900 dark:text-white tracking-tight leading-none mt-0.5">
            {title}
          </h1>
        </div>
      </div>

      {/* Right side: Global Search, Quick Specs, Theme, Notifications, User */}
      <div className="flex items-center gap-2 sm:gap-3">
        {/* Quick Command Palette Button */}
        <button
          id="btn-command-palette-trigger"
          onClick={() => setIsCommandPaletteOpen(true)}
          className="hidden md:flex items-center gap-3 px-3 py-1.5 rounded-xl border text-xs text-neutral-500 dark:text-neutral-400 bg-neutral-100/80 dark:bg-neutral-800/80 border-neutral-200 dark:border-neutral-700/80 hover:border-neutral-300 dark:hover:border-neutral-600 transition-all"
        >
          <Search className="w-3.5 h-3.5" />
          <span className="text-neutral-600 dark:text-neutral-400">Search products, menus, orders...</span>
          <kbd className="px-1.5 py-0.5 text-[10px] font-mono bg-white dark:bg-neutral-900 border border-neutral-300 dark:border-neutral-700 rounded text-neutral-600 dark:text-neutral-400 shadow-xs">
            ⌘K
          </kbd>
        </button>

        {/* Theme Switcher Toggle Pill */}
        <div className="flex items-center p-1 rounded-xl bg-neutral-100 dark:bg-neutral-800/80 border border-neutral-200 dark:border-neutral-700/80 shadow-2xs">
          <button
            id="btn-theme-light"
            onClick={() => {
              if (theme !== 'light') toggleTheme();
            }}
            className={`p-1.5 px-2 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all cursor-pointer ${
              theme === 'light'
                ? 'bg-white text-amber-600 shadow-xs ring-1 ring-neutral-200/80'
                : 'text-neutral-500 hover:text-neutral-800'
            }`}
            title="Switch to Light Mode"
            aria-label="Light Mode"
          >
            <Sun className={`w-3.5 h-3.5 ${theme === 'light' ? 'text-amber-500 fill-amber-500/20' : 'text-neutral-400'}`} />
            <span className="hidden xl:inline text-[11px]">Light</span>
          </button>
          <button
            id="btn-theme-dark"
            onClick={() => {
              if (theme !== 'dark') toggleTheme();
            }}
            className={`p-1.5 px-2 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all cursor-pointer ${
              theme === 'dark'
                ? 'bg-neutral-900 text-indigo-300 shadow-xs ring-1 ring-neutral-700'
                : 'text-neutral-500 dark:text-neutral-400 hover:text-white'
            }`}
            title="Switch to Dark Mode"
            aria-label="Dark Mode"
          >
            <Moon className={`w-3.5 h-3.5 ${theme === 'dark' ? 'text-indigo-400 fill-indigo-400/20' : 'text-neutral-400'}`} />
            <span className="hidden xl:inline text-[11px]">Dark</span>
          </button>
        </div>

        {/* Notification Bell */}
        <div className="relative">
          <button
            id="btn-notifications-toggle"
            onClick={() => setIsNotificationOpen(!isNotificationOpen)}
            className="p-2 rounded-xl text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 border border-neutral-200 dark:border-neutral-800 transition-colors relative"
            aria-label="Notifications"
          >
            <Bell className="w-4 h-4" />
            {supportAlertUnread > 0 && <span aria-label={`${supportAlertUnread} unread notifications`} className="absolute -top-1 -right-1 min-w-4 h-4 px-1 rounded-full bg-rose-500 text-white text-[10px] ring-2 ring-white dark:ring-neutral-900">{supportAlertUnread > 99 ? "99+" : supportAlertUnread}</span>}
          </button>
        </div>

        {/* Admin Profile Dropdown */}
        <div className="relative" ref={profileMenuRef}>
          {user ? (
            <button
              id="btn-user-profile"
              ref={profileTriggerRef}
              onClick={() => setIsProfileMenuOpen(!isProfileMenuOpen)}
              className="flex items-center gap-2 p-1 pl-1.5 pr-2.5 rounded-xl border border-neutral-200 dark:border-neutral-800 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-all text-left"
            >
              <img
                src={user.avatar}
                alt={user.name}
                className="w-7 h-7 rounded-lg object-cover ring-1 ring-neutral-300 dark:ring-neutral-700"
                referrerPolicy="no-referrer"
              />
              <div className="hidden sm:block text-left">
                <div className="text-xs font-bold text-neutral-900 dark:text-white leading-tight">
                  {user.name.split(' ')[0]}
                </div>
                <div className="text-[10px] text-neutral-500 dark:text-neutral-400 font-medium">
                  {user.role === 'super_admin' ? 'Super Admin' : user.role}
                </div>
              </div>
              <ChevronDown className="w-3.5 h-3.5 text-neutral-400 ml-0.5" />
            </button>
          ) : (
            <button
              onClick={() => setIsLoginModalOpen(true)}
              className="px-3 py-1.5 text-xs font-semibold rounded-xl bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:opacity-90 transition-opacity"
            >
              Login
            </button>
          )}

          {/* Profile Dropdown Menu */}
          {isProfileMenuOpen && user && (
            <div
              id="profile-dropdown-menu"
              className="absolute right-0 mt-2 w-64 rounded-2xl border bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-800 shadow-2xl py-2 z-50 animate-in fade-in slide-in-from-top-2"
            >
              <div className="px-4 py-3 border-b border-neutral-100 dark:border-neutral-800">
                <p className="text-xs font-bold text-neutral-900 dark:text-white">{user.name}</p>
                <p className="text-[11px] text-neutral-500 dark:text-neutral-400 truncate">{user.email}</p>
                <div className="mt-2 flex items-center justify-between">
                  <span className="text-[10px] font-semibold px-2 py-0.5 rounded-md bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300">
                    Role: {user.role}
                  </span>
                  <span className="flex items-center gap-1 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400">
                    <ShieldCheck className="w-3 h-3" />
                    2FA Verified
                  </span>
                </div>
              </div>

              {/* Role switcher simulation */}
              <div className="px-4 py-2 border-b border-neutral-100 dark:border-neutral-800">
                <p className="text-[10px] uppercase font-bold text-neutral-400 mb-1.5">Switch Test Role</p>
                <div className="grid grid-cols-2 gap-1.5">
                  <button
                    onClick={() => { login(user.email, 'super_admin'); setIsProfileMenuOpen(false); }}
                    className={`text-[11px] px-2 py-1 rounded-lg text-left font-medium ${user.role === 'super_admin' ? 'bg-neutral-100 dark:bg-neutral-800 text-neutral-900 dark:text-white font-bold' : 'text-neutral-500 hover:bg-neutral-50 dark:hover:bg-neutral-800/50'}`}
                  >
                    Super Admin
                  </button>
                  <button
                    onClick={() => { login(user.email, 'catalog_manager'); setIsProfileMenuOpen(false); }}
                    className={`text-[11px] px-2 py-1 rounded-lg text-left font-medium ${user.role === 'catalog_manager' ? 'bg-neutral-100 dark:bg-neutral-800 text-neutral-900 dark:text-white font-bold' : 'text-neutral-500 hover:bg-neutral-50 dark:hover:bg-neutral-800/50'}`}
                  >
                    Catalog Mgr
                  </button>
                  <button
                    onClick={() => { login(user.email, 'order_specialist'); setIsProfileMenuOpen(false); }}
                    className={`text-[11px] px-2 py-1 rounded-lg text-left font-medium ${user.role === 'order_specialist' ? 'bg-neutral-100 dark:bg-neutral-800 text-neutral-900 dark:text-white font-bold' : 'text-neutral-500 hover:bg-neutral-50 dark:hover:bg-neutral-800/50'}`}
                  >
                    Order Ops
                  </button>
                  <button
                    onClick={() => { login(user.email, 'developer'); setIsProfileMenuOpen(false); }}
                    className={`text-[11px] px-2 py-1 rounded-lg text-left font-medium ${user.role === 'developer' ? 'bg-neutral-100 dark:bg-neutral-800 text-neutral-900 dark:text-white font-bold' : 'text-neutral-500 hover:bg-neutral-50 dark:hover:bg-neutral-800/50'}`}
                  >
                    Developer
                  </button>
                </div>
              </div>

              {/* Actions */}
              <div className="py-1">
                <button
                  onClick={() => { resetToDefaults(); setIsProfileMenuOpen(false); }}
                  className="w-full flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/30 text-left transition-colors"
                >
                  <RotateCcw className="w-3.5 h-3.5" />
                  Reset Seed Demo Data
                </button>
                <button
                  onClick={() => { logout(); setIsProfileMenuOpen(false); }}
                  className="w-full flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/30 text-left transition-colors"
                >
                  <LogOut className="w-3.5 h-3.5" />
                  Log Out
                </button>
              </div>
            </div>
          )}
        </div>
      </div>
    </header>
  );
};

