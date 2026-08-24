import React, { useEffect, useState } from 'react';
import type { HeaderMenuL1, StorefrontShellLayout, StorefrontShellSettings } from '../../types';
import {
  Search,
  Sun,
  Moon,
  Globe,
  User,
  ShoppingBag,
  ChevronDown,
  Sparkles,
  Layers,
  SlidersHorizontal,
  LogIn,
  UserPlus,
  HelpCircle,
  Package,
  ArrowRight,
  Menu,
  X,
  Zap,
  Tag,
  Check,
  Compass,
  Laptop,
  Megaphone,
  Truck,
  Phone
} from 'lucide-react';

type HeaderLayoutTemplate =
  | 'modern_minimalist'
  | 'centered_luxury'
  | 'floating_pill'
  | 'mega_catalog'
  | 'editorial_drawer';

export type { HeaderLayoutTemplate };

export interface StorefrontHeaderPreviewUtilityState {
  showSearch: boolean;
  showThemeToggle: boolean;
  showCurrency: boolean;
  showUserAccount: boolean;
  showCartBadge: boolean;
  selectedCurrency: string;
  cartCount: number;
}

interface PreviewAnnouncementBarConfig {
  enabled: boolean;
  leftEnabled: boolean;
  leftText: string;
  leftBadge: string;
  centerEnabled: boolean;
  centerText: string;
  centerHighlightText: string;
  rightEnabled: boolean;
  rightText: string;
  showCurrencySelector: boolean;
  showLogisticsTracker: boolean;
  showWhatsAppHotline: boolean;
  whatsAppNumber: string;
  theme: 'solid_dark' | 'brand_indigo' | 'warm_amber' | 'emerald_deals' | 'crimson_sale' | 'custom';
  customBgColor?: string;
  customTextColor?: string;
}

const SHELL_TO_SOURCE_LAYOUT: Record<StorefrontShellLayout, HeaderLayoutTemplate> = {
  atelier: 'modern_minimalist',
  'centered-luxury': 'centered_luxury',
  'floating-pill': 'floating_pill',
  'mega-catalog': 'mega_catalog',
  'editorial-drawer': 'editorial_drawer',
};

const SOURCE_TO_SHELL_LAYOUT: Record<HeaderLayoutTemplate, StorefrontShellLayout> = {
  modern_minimalist: 'atelier',
  centered_luxury: 'centered-luxury',
  floating_pill: 'floating-pill',
  mega_catalog: 'mega-catalog',
  editorial_drawer: 'editorial-drawer',
};

const shellThemeToSource = (theme: StorefrontShellSettings['theme']): PreviewAnnouncementBarConfig['theme'] => {
  switch (theme) {
    case 'aurora-indigo': return 'brand_indigo';
    case 'emerald-logistics': return 'emerald_deals';
    case 'amber-flash': return 'warm_amber';
    case 'crimson-campaign': return 'crimson_sale';
    case 'custom': return 'custom';
    case 'midnight-obsidian':
    default: return 'solid_dark';
  }
};

const TEMPLATE_DESCRIPTIONS: Record<
  HeaderLayoutTemplate,
  { name: string; tag: string; desc: string; icon: string }
> = {
  modern_minimalist: {
    name: 'Modern Atelier (D2C)',
    tag: 'Recommended',
    desc: 'Logo on left, fluid center nav, search + currency + member studio popover on right.',
    icon: '✨'
  },
  centered_luxury: {
    name: 'Haute Couture (Centered)',
    tag: 'Luxury',
    desc: 'Balanced dual-tier layout: top utility bar with grand centered brand logo & centered nav.',
    icon: '👑'
  },
  floating_pill: {
    name: 'Cyber Glassmorphic Pill',
    tag: 'Tech',
    desc: 'Floating capsule nav bar with backdrop blur, rounded borders, and ambient glow.',
    icon: '⚡'
  },
  mega_catalog: {
    name: 'Mega Catalog Superstore',
    tag: 'High SKU',
    desc: 'Multi-row header with prominent search bar, customer care direct line, and category tabs.',
    icon: '🏢'
  },
  editorial_drawer: {
    name: 'Editorial Minimal (Drawer)',
    tag: 'Lookbook',
    desc: 'Clean off-canvas hamburger drawer for lookbook imagery with centered minimal mark.',
    icon: '📖'
  }
};

const CURRENCIES = [
  { code: 'EUR', symbol: '€', label: 'EUR (€)' },
  { code: 'USD', symbol: '$', label: 'USD ($)' },
  { code: 'MYR', symbol: 'RM', label: 'MYR (RM)' },
  { code: 'GBP', symbol: '£', label: 'GBP (£)' },
  { code: 'SGD', symbol: 'S$', label: 'SGD (S$)' },
  { code: 'JPY', symbol: '¥', label: 'JPY (¥)' }
];

interface StorefrontHeaderPreviewProps {
  settings: StorefrontShellSettings;
  utilityState: StorefrontHeaderPreviewUtilityState;
  headerMenus: HeaderMenuL1[];
  onSelectLayout: (layout: StorefrontShellLayout) => void;
  onUtilityChange: (updates: Partial<StorefrontHeaderPreviewUtilityState>) => void;
  onAnnouncementEnabledChange: (enabled: boolean) => void;
  onEditAnnouncement: () => void;
}

export const StorefrontHeaderPreview: React.FC<StorefrontHeaderPreviewProps> = ({
  settings,
  utilityState,
  headerMenus,
  onSelectLayout,
  onUtilityChange,
  onAnnouncementEnabledChange,
  onEditAnnouncement,
}) => {
  const [activeL1Id, setActiveL1Id] = useState<string | null>(headerMenus[0]?.id ?? null);
  const onSelectL1 = (id: string) => setActiveL1Id(id);
  const announcementBar: PreviewAnnouncementBarConfig = {
    enabled: settings.enabled,
    leftEnabled: settings.zones.left.enabled,
    leftText: settings.zones.left.text,
    leftBadge: settings.zones.left.badge,
    centerEnabled: settings.zones.center.enabled,
    centerText: settings.zones.center.text,
    centerHighlightText: settings.zones.center.couponCode,
    rightEnabled: settings.zones.right.enabled,
    rightText: settings.zones.right.helperText,
    showCurrencySelector: utilityState.showCurrency,
    showLogisticsTracker: settings.zones.right.trackingEnabled,
    showWhatsAppHotline: settings.zones.right.whatsappEnabled,
    whatsAppNumber: settings.zones.right.whatsappLabel,
    theme: shellThemeToSource(settings.theme),
    customBgColor: settings.customPalette.background ?? undefined,
    customTextColor: settings.customPalette.text ?? undefined,
  };
  const config = {
    template: SHELL_TO_SOURCE_LAYOUT[settings.layout],
    colorTheme: 'dark' as 'dark' | 'light' | 'glass' | 'titanium',
    showSearch: utilityState.showSearch,
    showThemeToggle: utilityState.showThemeToggle,
    showCurrency: utilityState.showCurrency,
    showUserAccount: utilityState.showUserAccount,
    showCartBadge: utilityState.showCartBadge,
    selectedCurrency: utilityState.selectedCurrency,
    cartCount: utilityState.cartCount,
    announcementBar,
  };
  const saveStorefrontHeaderConfig = (updates: Partial<{
    template: HeaderLayoutTemplate;
    showSearch: boolean;
    showCurrency: boolean;
    showUserAccount: boolean;
    showCartBadge: boolean;
    selectedCurrency: string;
    announcementBar: PreviewAnnouncementBarConfig;
  }>) => {
    if (updates.template) onSelectLayout(SOURCE_TO_SHELL_LAYOUT[updates.template]);
    if (updates.showSearch !== undefined) onUtilityChange({ showSearch: updates.showSearch });
    if (updates.showCurrency !== undefined) onUtilityChange({ showCurrency: updates.showCurrency });
    if (updates.showUserAccount !== undefined) onUtilityChange({ showUserAccount: updates.showUserAccount });
    if (updates.showCartBadge !== undefined) onUtilityChange({ showCartBadge: updates.showCartBadge });
    if (updates.selectedCurrency !== undefined) onUtilityChange({ selectedCurrency: updates.selectedCurrency });
    if (updates.announcementBar?.enabled !== undefined) onAnnouncementEnabledChange(updates.announcementBar.enabled);
  };
  const setCurrentSection = (_section: string) => onEditAnnouncement();

  // UI Interactive Popovers
  const [isUserPopoverOpen, setIsUserPopoverOpen] = useState(false);
  const [isCurrencyDropdownOpen, setIsCurrencyDropdownOpen] = useState(false);
  const [isSearchOpen, setIsSearchOpen] = useState(false);
  const [isCartSlideoverOpen, setIsCartSlideoverOpen] = useState(false);
  const [isDrawerMenuOpen, setIsDrawerMenuOpen] = useState(false);
  const [isCustomizing, setIsCustomizing] = useState(false);
  const [themeMode, setThemeMode] = useState<'dark' | 'light'>('dark');

  // Search input state
  const [searchQuery, setSearchQuery] = useState('');

  useEffect(() => {
    if (!headerMenus.some(menu => menu.id === activeL1Id)) {
      setActiveL1Id(headerMenus[0]?.id ?? null);
    }
  }, [activeL1Id, headerMenus]);

  const activeMenu = headerMenus.find(m => m.id === activeL1Id);

  // Background style helper
  const getHeaderBgStyle = () => {
    switch (config.colorTheme) {
      case 'light':
        return 'bg-white text-neutral-900 border-neutral-200';
      case 'glass':
        return 'bg-neutral-900/70 text-white border-neutral-800/80 backdrop-blur-xl shadow-2xl';
      case 'titanium':
        return 'bg-[#0f172a] text-white border-slate-800 shadow-xl';
      case 'dark':
      default:
        return 'bg-neutral-950 text-white border-neutral-800/90';
    }
  };

  // Helper for Announcement Bar theme styling
  const getAnnouncementBarStyle = () => {
    switch (announcementBar.theme) {
      case 'emerald_deals':
        return { backgroundColor: '#064e3b', color: '#a7f3d0' };
      case 'warm_amber':
        return { backgroundColor: '#78350f', color: '#fde68a' };
      case 'crimson_sale':
        return { backgroundColor: '#881337', color: '#ffe4e6' };
      case 'brand_indigo':
        return { backgroundColor: '#312e81', color: '#e0e7ff' };
      case 'custom':
        return {
          backgroundColor: announcementBar.customBgColor || '#0f172a',
          color: announcementBar.customTextColor || '#ffffff',
        };
      case 'solid_dark':
      default:
        return { backgroundColor: '#0f172a', color: '#f8fafc' };
    }
  };

  const isLight = config.colorTheme === 'light';

  return (
    <div className="space-y-4">
      {/* Template Switcher & Quick Customizer Toolbar */}
      <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-3 p-3.5 rounded-2xl bg-neutral-900 border border-neutral-800">
        <div className="flex items-center gap-2 overflow-x-auto pb-1 lg:pb-0 scrollbar-none">
          <span className="text-[11px] font-bold uppercase tracking-wider text-neutral-400 shrink-0 flex items-center gap-1 mr-1">
            <Compass className="w-3.5 h-3.5 text-indigo-400" /> Header Templates:
          </span>

          {(Object.keys(TEMPLATE_DESCRIPTIONS) as HeaderLayoutTemplate[]).map(tpl => {
            const info = TEMPLATE_DESCRIPTIONS[tpl];
            const isSelected = config.template === tpl;
            return (
              <button
                key={tpl}
                type="button"
                onClick={() => saveStorefrontHeaderConfig({ template: tpl })}
                className={`px-3 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-all flex items-center gap-1.5 cursor-pointer shrink-0 ${
                  isSelected
                    ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30 ring-1 ring-indigo-400'
                    : 'bg-neutral-800/80 text-neutral-300 hover:bg-neutral-800 hover:text-white border border-neutral-700/60'
                }`}
              >
                <span>{info.icon}</span>
                <span>{info.name}</span>
                {isSelected && (
                  <span className="text-[9px] px-1 py-0.2 rounded bg-white/20 text-white font-mono">
                    Active
                  </span>
                )}
              </button>
            );
          })}
        </div>

        <div className="flex items-center gap-2 shrink-0 self-end lg:self-center">
          <button
            type="button"
            onClick={() => setCurrentSection('storefront-header-announcement')}
            className="px-3 py-1.5 rounded-xl text-xs font-semibold bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/40 transition-colors flex items-center gap-1.5 cursor-pointer"
          >
            <Megaphone className="w-3.5 h-3.5 text-amber-400" />
            <span>Edit Announcement Bar</span>
          </button>

          <button
            type="button"
            onClick={() => setIsCustomizing(!isCustomizing)}
            className={`px-3 py-1.5 rounded-xl text-xs font-semibold border transition-all flex items-center gap-1.5 cursor-pointer ${
              isCustomizing
                ? 'bg-indigo-500/20 text-indigo-300 border-indigo-500/40'
                : 'bg-neutral-800 text-neutral-300 border-neutral-700 hover:text-white'
            }`}
          >
            <SlidersHorizontal className="w-3.5 h-3.5" />
            <span>Customize Features</span>
          </button>
        </div>
      </div>

      {/* Feature Customization Drawer */}
      {isCustomizing && (
        <div className="p-4 rounded-2xl bg-neutral-900/90 border border-neutral-800 grid grid-cols-2 sm:grid-cols-5 gap-3 animate-in fade-in duration-150">
          <label className="flex items-center gap-2 text-xs text-neutral-300 cursor-pointer">
            <input
              type="checkbox"
              checked={config.showSearch}
              onChange={e => saveStorefrontHeaderConfig({ showSearch: e.target.checked })}
              className="rounded accent-indigo-600"
            />
            <span>Search Icon</span>
          </label>

          <label className="flex items-center gap-2 text-xs text-neutral-300 cursor-pointer">
            <input
              type="checkbox"
              checked={config.showCurrency}
              onChange={e => saveStorefrontHeaderConfig({ showCurrency: e.target.checked })}
              className="rounded accent-indigo-600"
            />
            <span>Currency Switcher</span>
          </label>

          <label className="flex items-center gap-2 text-xs text-neutral-300 cursor-pointer">
            <input
              type="checkbox"
              checked={config.showUserAccount}
              onChange={e => saveStorefrontHeaderConfig({ showUserAccount: e.target.checked })}
              className="rounded accent-indigo-600"
            />
            <span>Member Studio (User)</span>
          </label>

          <label className="flex items-center gap-2 text-xs text-neutral-300 cursor-pointer">
            <input
              type="checkbox"
              checked={config.showCartBadge}
              onChange={e => saveStorefrontHeaderConfig({ showCartBadge: e.target.checked })}
              className="rounded accent-indigo-600"
            />
            <span>Cart Bag Icon</span>
          </label>

          <label className="flex items-center gap-2 text-xs text-neutral-300 cursor-pointer">
            <input
              type="checkbox"
              checked={announcementBar.enabled}
              onChange={e => saveStorefrontHeaderConfig({
                announcementBar: { ...announcementBar, enabled: e.target.checked }
              })}
              className="rounded accent-indigo-600"
            />
            <span>Announcement Bar</span>
          </label>
        </div>
      )}

      {/* Main Interactive Customer Viewport Container */}
      <div className="rounded-3xl bg-neutral-950 border border-neutral-800 text-white shadow-2xl overflow-hidden relative">
        {/* Top Announcement Bar Render (Directly bound to Global Config!) */}
        {announcementBar.enabled && (
          <div
            className="py-2.5 px-4 sm:px-6 text-xs transition-all border-b border-white/10"
            style={getAnnouncementBarStyle()}
          >
            <div className="max-w-7xl mx-auto flex items-center justify-between gap-4">
              {/* Left notice / badge */}
              {announcementBar.leftEnabled && (
                <div className="hidden md:flex items-center gap-2 text-[11px]">
                  {announcementBar.leftBadge && (
                    <span className="px-1.5 py-0.2 rounded-full bg-white/20 text-[9px] font-bold tracking-wider">
                      {announcementBar.leftBadge}
                    </span>
                  )}
                  <span className="font-medium opacity-90">{announcementBar.leftText}</span>
                </div>
              )}

              {/* Center Core Promo */}
              {announcementBar.centerEnabled && (
                <div className="flex-1 text-center truncate flex items-center justify-center gap-2">
                  <span className="font-semibold">{announcementBar.centerText}</span>
                  {announcementBar.centerHighlightText && (
                    <span className="px-2 py-0.5 rounded bg-amber-400 text-neutral-950 text-[10px] font-mono font-black tracking-wide shadow-xs">
                      {announcementBar.centerHighlightText}
                    </span>
                  )}
                </div>
              )}

              {/* Right Hub */}
              {announcementBar.rightEnabled && (
                <div className="hidden lg:flex items-center gap-3 text-[11px]">
                  {announcementBar.showWhatsAppHotline && announcementBar.whatsAppNumber && (
                    <span className="font-mono flex items-center gap-1 opacity-90">
                      <Phone className="w-3 h-3 text-emerald-400" />
                      {announcementBar.whatsAppNumber}
                    </span>
                  )}
                  {announcementBar.showLogisticsTracker && (
                    <span className="flex items-center gap-1 hover:underline cursor-pointer opacity-90">
                      <Truck className="w-3 h-3 text-cyan-400" />
                      {announcementBar.rightText}
                    </span>
                  )}
                </div>
              )}
            </div>
          </div>
        )}

        {/* Viewport Meta Bar */}
        <div className="flex items-center justify-between px-5 py-2.5 bg-neutral-950 border-b border-neutral-850 text-xs">
          <div className="flex items-center gap-2.5">
            <span className="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
            <span className="font-bold text-neutral-200">
              Interactive Storefront Preview (Customer Viewport)
            </span>
            <span className="px-2 py-0.5 rounded-full text-[10px] font-mono bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
              {TEMPLATE_DESCRIPTIONS[config.template].name}
            </span>
          </div>

          <div className="flex items-center gap-3 text-[11px] text-neutral-400">
            <span>Hover menu tabs to trigger Mega Menu • Click Member Icon to preview Auth Popover</span>
          </div>
        </div>

        {/* ======================================================== */}
        {/* HEADER BAR VARIANTS                                      */}
        {/* ======================================================== */}
        <div className={`transition-all duration-200 relative ${getHeaderBgStyle()}`}>
          {/* ---------------------------------------------------- */}
          {/* TEMPLATE A: MODERN MINIMALIST (ATELIER D2C) - SCREENSHOT 2 */}
          {/* ---------------------------------------------------- */}
          {config.template === 'modern_minimalist' && (
            <header className="px-5 sm:px-8 h-18 flex items-center justify-between border-b border-neutral-800/70">
              {/* Brand Logo & Left Navigation */}
              <div className="flex items-center gap-8">
                <div className="flex items-center gap-2.5 cursor-pointer group">
                  <div className="w-8 h-8 rounded-xl bg-indigo-600 flex items-center justify-center shadow-lg shadow-indigo-600/30 text-white font-black text-sm group-hover:scale-105 transition-transform">
                    <Zap className="w-4 h-4 text-white fill-white" />
                  </div>
                  <span className="font-black tracking-widest text-base text-white">AURORA</span>
                </div>

                {/* Level 1 Nav Items */}
                <nav className="hidden md:flex items-center gap-1">
                  {headerMenus.map(l1 => {
                    const isActive = activeL1Id === l1.id;
                    return (
                      <button
                        key={l1.id}
                        onClick={() => onSelectL1(l1.id)}
                        onMouseEnter={() => onSelectL1(l1.id)}
                        className={`px-3 py-2 rounded-xl text-xs font-semibold transition-all flex items-center gap-1.5 cursor-pointer ${
                          isActive
                            ? 'bg-neutral-800/90 text-white shadow-xs'
                            : 'text-neutral-300 hover:text-white hover:bg-neutral-800/50'
                        }`}
                      >
                        <span>{l1.title}</span>
                        {l1.badge && (
                          <span className="text-[9px] font-bold px-1.5 py-0.2 rounded-full bg-indigo-500/30 text-indigo-300 border border-indigo-500/40">
                            {l1.badge}
                          </span>
                        )}
                        {l1.columns.length > 0 && (
                          <ChevronDown
                            className={`w-3.5 h-3.5 transition-transform duration-200 ${
                              isActive ? 'rotate-180 text-indigo-400' : 'opacity-50'
                            }`}
                          />
                        )}
                      </button>
                    );
                  })}
                </nav>
              </div>

              {/* Right Utility Cluster (Screenshot 2 Match!) */}
              <div className="flex items-center gap-2 sm:gap-3">
                {/* Search Button */}
                {config.showSearch && (
                  <button
                    type="button"
                    onClick={() => setIsSearchOpen(!isSearchOpen)}
                    className="p-2 rounded-xl text-neutral-300 hover:text-white hover:bg-neutral-800/80 transition-colors cursor-pointer"
                    title="Search Catalog"
                  >
                    <Search className="w-4 h-4" />
                  </button>
                )}

                {/* Theme Toggle Icon */}
                {config.showThemeToggle && (
                  <button
                    type="button"
                    onClick={() => setThemeMode(themeMode === 'dark' ? 'light' : 'dark')}
                    className="p-2 rounded-xl text-neutral-300 hover:text-white hover:bg-neutral-800/80 transition-colors cursor-pointer"
                    title="Toggle Theme"
                  >
                    {themeMode === 'dark' ? (
                      <Sun className="w-4 h-4 text-amber-400" />
                    ) : (
                      <Moon className="w-4 h-4 text-indigo-400" />
                    )}
                  </button>
                )}

                {/* Currency Switcher */}
                {config.showCurrency && (
                  <div className="relative">
                    <button
                      type="button"
                      onClick={() => setIsCurrencyDropdownOpen(!isCurrencyDropdownOpen)}
                      className="px-2.5 py-1.5 rounded-xl text-xs font-semibold text-neutral-300 hover:text-white hover:bg-neutral-800/80 transition-colors flex items-center gap-1.5 cursor-pointer border border-neutral-800"
                    >
                      <Globe className="w-3.5 h-3.5 text-neutral-400" />
                      <span>{config.selectedCurrency}</span>
                      <ChevronDown className="w-3 h-3 opacity-60" />
                    </button>

                    {/* Currency Dropdown Menu */}
                    {isCurrencyDropdownOpen && (
                      <div className="absolute right-0 top-full mt-2 w-36 bg-neutral-900 border border-neutral-800 rounded-2xl shadow-2xl p-1 z-50 animate-in fade-in zoom-in-95">
                        {CURRENCIES.map(curr => (
                          <button
                            key={curr.code}
                            type="button"
                            onClick={() => {
                              saveStorefrontHeaderConfig({ selectedCurrency: curr.label });
                              setIsCurrencyDropdownOpen(false);
                            }}
                            className={`w-full px-3 py-1.5 rounded-xl text-xs text-left font-medium transition-colors flex items-center justify-between ${
                              config.selectedCurrency === curr.label
                                ? 'bg-indigo-600 text-white'
                                : 'text-neutral-300 hover:bg-neutral-800 hover:text-white'
                            }`}
                          >
                            <span>{curr.label}</span>
                            {config.selectedCurrency === curr.label && (
                              <Check className="w-3.5 h-3.5" />
                            )}
                          </button>
                        ))}
                      </div>
                    )}
                  </div>
                )}

                {/* User Account Popover Trigger (Screenshot 2 Match!) */}
                {config.showUserAccount && (
                  <div className="relative">
                    <button
                      type="button"
                      onClick={() => setIsUserPopoverOpen(!isUserPopoverOpen)}
                      className={`p-2 rounded-xl transition-all cursor-pointer border ${
                        isUserPopoverOpen
                          ? 'bg-indigo-600 text-white border-indigo-500 shadow-md shadow-indigo-600/30'
                          : 'text-neutral-300 hover:text-white hover:bg-neutral-800/80 border-neutral-800'
                      }`}
                      title="Member Studio"
                    >
                      <div className="flex items-center gap-1">
                        <User className="w-4 h-4" />
                        <ChevronDown className="w-2.5 h-2.5 opacity-60" />
                      </div>
                    </button>

                    {/* EXACT SCREENSHOT 2 MEMBER STUDIO POPOVER */}
                    {isUserPopoverOpen && (
                      <div className="absolute right-0 top-full mt-3 w-80 bg-neutral-900/95 backdrop-blur-2xl border border-neutral-700/80 rounded-3xl shadow-2xl p-4 z-50 animate-in fade-in slide-in-from-top-2 duration-150">
                        {/* Studio Header */}
                        <div className="pb-3 border-b border-neutral-800 mb-3">
                          <div className="flex items-center justify-between">
                            <h4 className="text-sm font-bold text-white tracking-tight">
                              Atelier Member Studio
                            </h4>
                            <span className="text-[10px] px-2 py-0.5 rounded-full bg-indigo-500/20 text-indigo-300 font-bold border border-indigo-500/30">
                              VIP Club
                            </span>
                          </div>
                          <p className="text-xs text-neutral-400 mt-1 leading-relaxed">
                            Sign in to access your orders, track deliveries & earn rewards.
                          </p>
                        </div>

                        {/* Action Buttons */}
                        <div className="space-y-2">
                          <button
                            type="button"
                            onClick={() => alert('Simulator: Sign In flow triggered')}
                            className="w-full py-2.5 px-4 rounded-2xl bg-white text-neutral-950 hover:bg-neutral-200 font-bold text-xs flex items-center justify-between transition-colors shadow-sm cursor-pointer"
                          >
                            <span className="flex items-center gap-2">
                              <LogIn className="w-3.5 h-3.5 text-neutral-900" />
                              <span>Sign In</span>
                            </span>
                            <span className="text-[10px] font-mono text-neutral-500">Login</span>
                          </button>

                          <button
                            type="button"
                            onClick={() => alert('Simulator: Register flow triggered')}
                            className="w-full py-2.5 px-4 rounded-2xl bg-neutral-800/90 hover:bg-neutral-800 text-white font-bold text-xs flex items-center justify-between border border-neutral-700 transition-colors cursor-pointer"
                          >
                            <span className="flex items-center gap-2">
                              <UserPlus className="w-3.5 h-3.5 text-indigo-400" />
                              <span>Register</span>
                            </span>
                            <span className="text-[10px] font-bold px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-300 border border-amber-500/40">
                              +10% OFF
                            </span>
                          </button>
                        </div>

                        {/* Footer Help Link */}
                        <div className="mt-3 pt-2.5 border-t border-neutral-800 flex items-center justify-between text-[11px] text-neutral-400">
                          <button
                            type="button"
                            onClick={() => alert('Simulator: Password reset requested')}
                            className="hover:text-neutral-200 transition-colors flex items-center gap-1 cursor-pointer"
                          >
                            <HelpCircle className="w-3 h-3" /> Forgot Password?
                          </button>
                          <span className="text-neutral-500">Track Order</span>
                        </div>
                      </div>
                    )}
                  </div>
                )}

                {/* Shopping Bag Icon with Live Badge (Screenshot 2 Match!) */}
                {config.showCartBadge && (
                  <button
                    type="button"
                    onClick={() => setIsCartSlideoverOpen(!isCartSlideoverOpen)}
                    className="p-2 rounded-xl text-neutral-300 hover:text-white hover:bg-neutral-800/80 transition-colors relative cursor-pointer border border-neutral-800"
                    title="View Shopping Bag"
                  >
                    <ShoppingBag className="w-4 h-4" />
                    {config.cartCount > 0 && (
                      <span className="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-indigo-600 text-white font-bold text-[9px] flex items-center justify-center shadow-md">
                        {config.cartCount}
                      </span>
                    )}
                  </button>
                )}

                {/* Mobile Hamburger Drawer */}
                <button
                  type="button"
                  onClick={() => setIsDrawerMenuOpen(!isDrawerMenuOpen)}
                  className="md:hidden p-2 rounded-xl text-neutral-300 hover:text-white hover:bg-neutral-800/80"
                >
                  <Menu className="w-5 h-5" />
                </button>
              </div>
            </header>
          )}

          {/* ---------------------------------------------------- */}
          {/* TEMPLATE B: CENTERED LUXURY (HAUTE COUTURE)          */}
          {/* ---------------------------------------------------- */}
          {config.template === 'centered_luxury' && (
            <header className="border-b border-neutral-800/70">
              {/* Row 1: Utility Bar & Centered Brand */}
              <div className="px-6 sm:px-10 h-16 flex items-center justify-between border-b border-neutral-900">
                {/* Left Utilities */}
                <div className="flex items-center gap-3 text-xs text-neutral-400">
                  <span className="hidden sm:inline">Concierge: +1 (800) 555-0199</span>
                  <div className="flex items-center gap-1.5 cursor-pointer hover:text-white">
                    <Globe className="w-3.5 h-3.5" />
                    <span>{config.selectedCurrency}</span>
                  </div>
                </div>

                {/* Centered Grand Logo */}
                <div className="flex items-center gap-2 cursor-pointer">
                  <span className="font-serif tracking-widest text-lg sm:text-xl font-bold text-white uppercase">
                    A U R O R A
                  </span>
                </div>

                {/* Right Utilities */}
                <div className="flex items-center gap-3">
                  <button
                    onClick={() => setIsSearchOpen(!isSearchOpen)}
                    className="p-1.5 text-neutral-400 hover:text-white cursor-pointer"
                  >
                    <Search className="w-4 h-4" />
                  </button>
                  <button
                    onClick={() => setIsUserPopoverOpen(!isUserPopoverOpen)}
                    className="p-1.5 text-neutral-400 hover:text-white cursor-pointer"
                  >
                    <User className="w-4 h-4" />
                  </button>
                  <button
                    onClick={() => setIsCartSlideoverOpen(!isCartSlideoverOpen)}
                    className="p-1.5 text-neutral-400 hover:text-white flex items-center gap-1 cursor-pointer"
                  >
                    <ShoppingBag className="w-4 h-4" />
                    <span className="text-xs font-mono">({config.cartCount})</span>
                  </button>
                </div>
              </div>

              {/* Row 2: Centered Navigation Tabs */}
              <nav className="h-12 flex items-center justify-center gap-6 px-6">
                {headerMenus.map(l1 => (
                  <button
                    key={l1.id}
                    onClick={() => onSelectL1(l1.id)}
                    onMouseEnter={() => onSelectL1(l1.id)}
                    className={`text-xs uppercase tracking-wider font-semibold transition-all flex items-center gap-1 cursor-pointer ${
                      activeL1Id === l1.id
                        ? 'text-white border-b-2 border-indigo-500 pb-1'
                        : 'text-neutral-400 hover:text-white'
                    }`}
                  >
                    <span>{l1.title}</span>
                    {l1.badge && (
                      <span className="text-[8px] px-1 py-0.2 rounded bg-amber-500/20 text-amber-300 border border-amber-500/40">
                        {l1.badge}
                      </span>
                    )}
                  </button>
                ))}
              </nav>
            </header>
          )}

          {/* ---------------------------------------------------- */}
          {/* TEMPLATE C: FLOATING GLASSMORPHIC PILL               */}
          {/* ---------------------------------------------------- */}
          {config.template === 'floating_pill' && (
            <div className="p-4 sm:p-6 bg-gradient-to-b from-neutral-900/60 to-neutral-950">
              <header className="max-w-4xl mx-auto h-14 px-5 rounded-full bg-neutral-900/80 border border-neutral-700/80 backdrop-blur-xl shadow-2xl flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <div className="w-7 h-7 rounded-lg bg-indigo-600 flex items-center justify-center font-bold text-xs text-white">
                    A
                  </div>
                  <span className="font-bold tracking-wider text-xs text-white">AURORA</span>
                </div>

                <nav className="hidden md:flex items-center gap-1">
                  {headerMenus.map(l1 => (
                    <button
                      key={l1.id}
                      onClick={() => onSelectL1(l1.id)}
                      onMouseEnter={() => onSelectL1(l1.id)}
                      className={`px-3 py-1.5 rounded-full text-xs font-semibold transition-all cursor-pointer ${
                        activeL1Id === l1.id
                          ? 'bg-white text-neutral-950 font-bold'
                          : 'text-neutral-300 hover:text-white hover:bg-neutral-800'
                      }`}
                    >
                      {l1.title}
                    </button>
                  ))}
                </nav>

                <div className="flex items-center gap-2">
                  <button
                    onClick={() => setIsSearchOpen(!isSearchOpen)}
                    className="p-2 rounded-full text-neutral-300 hover:text-white hover:bg-neutral-800"
                  >
                    <Search className="w-3.5 h-3.5" />
                  </button>
                  <button
                    onClick={() => setIsUserPopoverOpen(!isUserPopoverOpen)}
                    className="p-2 rounded-full text-neutral-300 hover:text-white hover:bg-neutral-800"
                  >
                    <User className="w-3.5 h-3.5" />
                  </button>
                  <button
                    onClick={() => setIsCartSlideoverOpen(!isCartSlideoverOpen)}
                    className="px-3 py-1.5 rounded-full bg-indigo-600 text-white text-xs font-bold flex items-center gap-1.5 shadow-md"
                  >
                    <ShoppingBag className="w-3.5 h-3.5" />
                    <span>{config.cartCount}</span>
                  </button>
                </div>
              </header>
            </div>
          )}

          {/* ---------------------------------------------------- */}
          {/* TEMPLATE D: MEGA CATALOG SUPERSTORE                  */}
          {/* ---------------------------------------------------- */}
          {config.template === 'mega_catalog' && (
            <header className="border-b border-neutral-800">
              {/* Row 1: Brand + Wide Search Bar + Action Hub */}
              <div className="px-6 py-3.5 flex items-center justify-between gap-6 border-b border-neutral-900">
                <div className="flex items-center gap-2 cursor-pointer shrink-0">
                  <div className="w-8 h-8 rounded-xl bg-indigo-600 flex items-center justify-center font-black text-sm text-white">
                    <Zap className="w-4 h-4" />
                  </div>
                  <div>
                    <div className="font-black text-sm tracking-wider text-white">AURORA</div>
                    <div className="text-[9px] text-neutral-500 font-mono">ELECTRONICS HUB</div>
                  </div>
                </div>

                {/* Wide Prominent Search Field */}
                <div className="flex-1 max-w-xl hidden md:flex items-center relative">
                  <input
                    type="text"
                    placeholder="Search over 12,000+ audio gear, ANC headphones & cables..."
                    className="w-full pl-10 pr-24 py-2 text-xs rounded-xl bg-neutral-900 border border-neutral-700 text-white placeholder-neutral-500 focus:ring-2 focus:ring-indigo-500"
                  />
                  <Search className="w-4 h-4 text-neutral-500 absolute left-3" />
                  <button className="absolute right-1.5 px-3 py-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold">
                    Search
                  </button>
                </div>

                <div className="flex items-center gap-4 shrink-0">
                  <div
                    onClick={() => setIsUserPopoverOpen(!isUserPopoverOpen)}
                    className="flex items-center gap-2 cursor-pointer text-left"
                  >
                    <div className="w-8 h-8 rounded-xl bg-neutral-800 flex items-center justify-center text-neutral-300">
                      <User className="w-4 h-4" />
                    </div>
                    <div className="hidden lg:block text-xs">
                      <div className="text-[10px] text-neutral-500">Welcome Back</div>
                      <div className="font-bold text-white">My Account ▾</div>
                    </div>
                  </div>

                  <div
                    onClick={() => setIsCartSlideoverOpen(!isCartSlideoverOpen)}
                    className="flex items-center gap-2 cursor-pointer text-left"
                  >
                    <div className="w-8 h-8 rounded-xl bg-indigo-600 flex items-center justify-center text-white relative">
                      <ShoppingBag className="w-4 h-4" />
                      <span className="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-rose-500 text-white text-[9px] font-bold flex items-center justify-center">
                        {config.cartCount}
                      </span>
                    </div>
                    <div className="hidden lg:block text-xs">
                      <div className="text-[10px] text-neutral-500">Cart Total</div>
                      <div className="font-bold text-emerald-400">$389.00</div>
                    </div>
                  </div>
                </div>
              </div>

              {/* Row 2: Departments & Menu List */}
              <div className="px-6 h-11 flex items-center justify-between bg-neutral-900/90 text-xs">
                <div className="flex items-center gap-2">
                  <button className="px-3.5 py-1.5 rounded-lg bg-indigo-600 text-white font-bold flex items-center gap-2">
                    <Menu className="w-3.5 h-3.5" />
                    <span>All Departments</span>
                  </button>

                  <nav className="hidden sm:flex items-center gap-1 ml-2">
                    {headerMenus.map(l1 => (
                      <button
                        key={l1.id}
                        onClick={() => onSelectL1(l1.id)}
                        onMouseEnter={() => onSelectL1(l1.id)}
                        className={`px-3 py-1.5 rounded-lg font-semibold transition-colors ${
                          activeL1Id === l1.id
                            ? 'bg-neutral-800 text-white'
                            : 'text-neutral-400 hover:text-white'
                        }`}
                      >
                        {l1.title}
                      </button>
                    ))}
                  </nav>
                </div>

                <div className="hidden md:flex items-center gap-4 text-neutral-400 font-medium">
                  <span className="text-amber-400 font-semibold">⚡ Flash Deals (Up to 40% Off)</span>
                  <span>Store Locator</span>
                </div>
              </div>
            </header>
          )}

          {/* ---------------------------------------------------- */}
          {/* TEMPLATE E: EDITORIAL DRAWER                         */}
          {/* ---------------------------------------------------- */}
          {config.template === 'editorial_drawer' && (
            <header className="px-6 h-18 flex items-center justify-between border-b border-neutral-800/70">
              <button
                onClick={() => setIsDrawerMenuOpen(!isDrawerMenuOpen)}
                className="px-3 py-1.5 rounded-xl bg-neutral-900 border border-neutral-800 text-neutral-300 hover:text-white flex items-center gap-2 text-xs font-semibold cursor-pointer"
              >
                <Menu className="w-4 h-4" />
                <span>Menu</span>
              </button>

              <div className="font-serif tracking-widest text-base sm:text-lg font-bold text-white">
                A U R O R A   S T U D I O
              </div>

              <div className="flex items-center gap-3">
                <button
                  onClick={() => setIsSearchOpen(!isSearchOpen)}
                  className="p-2 text-neutral-400 hover:text-white"
                >
                  <Search className="w-4 h-4" />
                </button>
                <button
                  onClick={() => setIsCartSlideoverOpen(!isCartSlideoverOpen)}
                  className="p-2 text-neutral-400 hover:text-white relative"
                >
                  <ShoppingBag className="w-4 h-4" />
                  <span className="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-indigo-600 text-white text-[9px] font-bold flex items-center justify-center">
                    {config.cartCount}
                  </span>
                </button>
              </div>
            </header>
          )}

          {/* ==================================================== */}
          {/* 3. ACTIVE MEGA MENU DROPDOWN PANEL                   */}
          {/* ==================================================== */}
          {(() => {
            if (!activeMenu || activeMenu.columns.length === 0) {
              return (
                <div className="p-4 bg-neutral-950/80 border-t border-neutral-850 text-center text-xs text-neutral-500">
                  Direct page link or empty sub-columns for &quot;{activeMenu?.title || 'Selected Menu'}&quot;
                </div>
              );
            }

            return (
              <div className="p-6 bg-neutral-950 border-t border-neutral-800 animate-in fade-in slide-in-from-top-1 duration-150">
                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-8 max-w-7xl mx-auto">
                  {activeMenu.columns.map(col => (
                    <div key={col.id} className="space-y-3">
                      {/* Column Level 2 Title */}
                      <div className="flex items-center justify-between pb-2 border-b border-neutral-800">
                        <div className="flex items-center gap-2 text-xs font-bold text-white tracking-wide">
                          <span>{col.title}</span>
                          {col.badge && (
                            <span className="text-[9px] font-bold px-1.5 py-0.2 rounded bg-cyan-500/20 text-cyan-300 border border-cyan-500/30">
                              {col.badge}
                            </span>
                          )}
                        </div>
                      </div>

                      {/* Level 3 Leaf Links */}
                      <ul className="space-y-2">
                        {col.items.map(leaf => (
                          <li key={leaf.id}>
                            <a
                              href="#preview"
                              onClick={e => e.preventDefault()}
                              className="group flex items-center justify-between text-xs text-neutral-400 hover:text-white transition-colors"
                            >
                              <span className="group-hover:translate-x-0.5 transition-transform">
                                {leaf.title}
                              </span>
                              {leaf.badge && (
                                <span className="text-[9px] font-bold px-1.5 py-0.2 rounded bg-rose-500/20 text-rose-400 border border-rose-500/30">
                                  {leaf.badge}
                                </span>
                              )}
                            </a>
                          </li>
                        ))}
                        {col.items.length === 0 && (
                          <li className="text-[11px] text-neutral-600 italic">
                            No Level 3 sub-items
                          </li>
                        )}
                      </ul>
                    </div>
                  ))}

                  {/* Curated Promo Card Inside Mega Menu */}
                  <div className="hidden lg:block p-4 rounded-2xl bg-gradient-to-br from-indigo-950/60 to-neutral-900 border border-indigo-500/30 space-y-2">
                    <div className="text-[10px] font-bold uppercase tracking-wider text-indigo-400">
                      Featured Release
                    </div>
                    <div className="text-xs font-bold text-white">
                      Planar Magnetic Studio ANC
                    </div>
                    <p className="text-[11px] text-neutral-400 leading-snug">
                      50mm custom beryllium drivers with active magnetic damping.
                    </p>
                    <div className="pt-2 flex items-center justify-between">
                      <span className="text-xs font-bold text-emerald-400">$389.00</span>
                      <span className="text-[10px] font-bold text-indigo-300 flex items-center gap-1 hover:underline cursor-pointer">
                        Explore <ArrowRight className="w-3 h-3" />
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            );
          })()}
        </div>

        {/* ======================================================== */}
        {/* 4. SEARCH DRAWER SIMULATOR POPUP                         */}
        {/* ======================================================== */}
        {isSearchOpen && (
          <div className="p-5 bg-neutral-900 border-t border-neutral-800 animate-in fade-in duration-150">
            <div className="max-w-2xl mx-auto space-y-3">
              <div className="flex items-center gap-3">
                <div className="relative flex-1">
                  <Search className="w-4 h-4 text-neutral-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                  <input
                    type="text"
                    value={searchQuery}
                    onChange={e => setSearchQuery(e.target.value)}
                    placeholder="Search audiophile headphones, titanium chassis, cables..."
                    className="w-full pl-10 pr-4 py-2.5 bg-neutral-950 rounded-2xl border border-neutral-700 text-sm text-white placeholder-neutral-500 focus:ring-2 focus:ring-indigo-500 focus:outline-hidden"
                    autoFocus
                  />
                </div>
                <button
                  type="button"
                  onClick={() => setIsSearchOpen(false)}
                  className="p-2 text-neutral-400 hover:text-white rounded-xl bg-neutral-800"
                >
                  <X className="w-4 h-4" />
                </button>
              </div>

              {/* Quick Suggestion Tags */}
              <div className="flex items-center gap-2 text-xs text-neutral-400">
                <span className="font-medium">Trending:</span>
                {['Planar Magnetic ANC', 'Titanium Headband', '280GSM Tee', 'Lossless DAC'].map(tag => (
                  <button
                    key={tag}
                    onClick={() => setSearchQuery(tag)}
                    className="px-2 py-0.5 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-neutral-300 text-[11px] transition-colors"
                  >
                    {tag}
                  </button>
                ))}
              </div>
            </div>
          </div>
        )}

        {/* ======================================================== */}
        {/* 5. MINI CART SLIDE-OVER SIMULATOR                        */}
        {/* ======================================================== */}
        {isCartSlideoverOpen && (
          <div className="absolute right-0 top-0 bottom-0 w-80 bg-neutral-900 border-l border-neutral-700 shadow-2xl p-4 z-50 flex flex-col animate-in slide-in-from-right duration-200">
            <div className="flex items-center justify-between pb-3 border-b border-neutral-800">
              <div className="flex items-center gap-2">
                <ShoppingBag className="w-4 h-4 text-indigo-400" />
                <span className="font-bold text-sm text-white">Your Shopping Bag ({config.cartCount})</span>
              </div>
              <button
                onClick={() => setIsCartSlideoverOpen(false)}
                className="p-1 rounded-lg text-neutral-400 hover:text-white hover:bg-neutral-800"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <div className="flex-1 py-3 space-y-3 overflow-y-auto">
              <div className="p-3 rounded-2xl bg-neutral-950 border border-neutral-800 flex gap-3">
                <div className="w-12 h-12 rounded-xl bg-neutral-800 flex items-center justify-center text-xl shrink-0">
                  🎧
                </div>
                <div className="flex-1 text-xs">
                  <div className="font-bold text-white">Aether Studio ANC Wireless</div>
                  <div className="text-[10px] text-neutral-400">Finish: Obsidian Black</div>
                  <div className="mt-1 flex items-center justify-between">
                    <span className="font-mono text-emerald-400 font-bold">$389.00</span>
                    <span className="text-[10px] text-neutral-500">Qty: 1</span>
                  </div>
                </div>
              </div>
            </div>

            <div className="pt-3 border-t border-neutral-800 space-y-2">
              <div className="flex items-center justify-between text-xs">
                <span className="text-neutral-400">Subtotal:</span>
                <span className="font-mono font-bold text-white">$389.00</span>
              </div>
              <div className="flex items-center justify-between text-xs text-emerald-400 font-medium">
                <span>Worldwide Shipping:</span>
                <span>FREE</span>
              </div>
              <button
                onClick={() => alert('Simulator: Proceed to checkout')}
                className="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs transition-colors shadow-md cursor-pointer"
              >
                Checkout Now ($389.00)
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};
