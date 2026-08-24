import React, { useMemo, useState } from 'react';
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
  SlidersHorizontal,
  ArrowRight,
  Menu,
  X,
  Zap,
  Check,
  Compass,
  Megaphone,
  Truck,
  Phone,
  Monitor,
  Smartphone,
} from 'lucide-react';

export interface StorefrontHeaderPreviewUtilityState {
  showSearch: boolean;
  showThemeToggle: boolean;
  showCurrency: boolean;
  showUserAccount: boolean;
  showCartBadge: boolean;
  selectedCurrency: string;
  cartCount: number;
}

interface StorefrontHeaderPreviewProps {
  settings: StorefrontShellSettings;
  utilityState: StorefrontHeaderPreviewUtilityState;
  headerMenus: HeaderMenuL1[];
  onSelectLayout: (layout: StorefrontShellLayout) => void;
  onUtilityChange: (updates: Partial<StorefrontHeaderPreviewUtilityState>) => void;
  onAnnouncementEnabledChange: (enabled: boolean) => void;
  onEditAnnouncement: () => void;
}

const TEMPLATE_DESCRIPTIONS: Record<
  StorefrontShellLayout,
  { name: string; tag: string; desc: string; icon: string }
> = {
  atelier: {
    name: 'Modern Atelier (D2C)',
    tag: 'Recommended',
    desc: 'Logo on left, fluid center nav, search + currency + member studio popover on right.',
    icon: '✨',
  },
  'centered-luxury': {
    name: 'Haute Couture (Centered)',
    tag: 'Luxury',
    desc: 'Balanced dual-tier layout: top utility bar with grand centered brand logo & centered nav.',
    icon: '👑',
  },
  'floating-pill': {
    name: 'Cyber Glassmorphic Pill',
    tag: 'Tech',
    desc: 'Floating capsule nav bar with backdrop blur, rounded borders, and ambient glow.',
    icon: '⚡',
  },
  'mega-catalog': {
    name: 'Mega Catalog Superstore',
    tag: 'High SKU',
    desc: 'Multi-row header with prominent search bar, customer care direct line, and category tabs.',
    icon: '🏢',
  },
  'editorial-drawer': {
    name: 'Editorial Minimal (Drawer)',
    tag: 'Lookbook',
    desc: 'Clean off-canvas hamburger drawer for lookbook imagery with centered minimal mark.',
    icon: '📖',
  },
};

const CURRENCIES = ['EUR (€)', 'USD ($)', 'MYR (RM)', 'GBP (£)', 'SGD (S$)', 'JPY (¥)'];

const getAnnouncementBarStyle = (settings: StorefrontShellSettings): React.CSSProperties => {
  switch (settings.theme) {
    case 'aurora-indigo':
      return { backgroundColor: '#312e81', color: '#e0e7ff' };
    case 'emerald-logistics':
      return { backgroundColor: '#064e3b', color: '#a7f3d0' };
    case 'amber-flash':
      return { backgroundColor: '#78350f', color: '#fde68a' };
    case 'crimson-campaign':
      return { backgroundColor: '#881337', color: '#ffe4e6' };
    case 'custom':
      return {
        backgroundColor: settings.customPalette.background ?? '#0f172a',
        color: settings.customPalette.text ?? '#ffffff',
      };
    case 'midnight-obsidian':
    default:
      return { backgroundColor: '#0f172a', color: '#f8fafc' };
  }
};

export const StorefrontHeaderPreview: React.FC<StorefrontHeaderPreviewProps> = ({
  settings,
  utilityState,
  headerMenus,
  onSelectLayout,
  onUtilityChange,
  onAnnouncementEnabledChange,
  onEditAnnouncement,
}) => {
  const [isCustomizing, setIsCustomizing] = useState(false);
  const [activeMenuId, setActiveMenuId] = useState<string | null>(headerMenus[0]?.id ?? null);
  const [isSearchOpen, setIsSearchOpen] = useState(false);
  const [isCurrencyDropdownOpen, setIsCurrencyDropdownOpen] = useState(false);
  const [isUserPopoverOpen, setIsUserPopoverOpen] = useState(false);
  const [isCartSlideoverOpen, setIsCartSlideoverOpen] = useState(false);
  const [isDrawerMenuOpen, setIsDrawerMenuOpen] = useState(false);
  const [themeMode, setThemeMode] = useState<'dark' | 'light'>('dark');
  const [viewport, setViewport] = useState<'desktop' | 'mobile'>('desktop');
  const [searchQuery, setSearchQuery] = useState('');

  const activeMenu = useMemo(
    () => headerMenus.find((item) => item.id === activeMenuId) ?? headerMenus[0] ?? null,
    [activeMenuId, headerMenus],
  );

  const announcementStyle = getAnnouncementBarStyle(settings);

  const utilityCluster = (
    <div className="flex items-center gap-2 sm:gap-3">
      {utilityState.showSearch && (
        <button
          type="button"
          onClick={() => setIsSearchOpen((open) => !open)}
          className="p-2 rounded-xl text-neutral-300 hover:text-white hover:bg-neutral-800/80 transition-colors cursor-pointer"
        >
          <Search className="w-4 h-4" />
        </button>
      )}
      {utilityState.showThemeToggle && (
        <button
          type="button"
          onClick={() => setThemeMode((mode) => (mode === 'dark' ? 'light' : 'dark'))}
          className="p-2 rounded-xl text-neutral-300 hover:text-white hover:bg-neutral-800/80 transition-colors cursor-pointer"
        >
          {themeMode === 'dark' ? (
            <Sun className="w-4 h-4 text-amber-400" />
          ) : (
            <Moon className="w-4 h-4 text-indigo-400" />
          )}
        </button>
      )}
      {utilityState.showCurrency && (
        <div className="relative">
          <button
            type="button"
            onClick={() => setIsCurrencyDropdownOpen((open) => !open)}
            className="px-2.5 py-1.5 rounded-xl text-xs font-semibold text-neutral-300 hover:text-white hover:bg-neutral-800/80 transition-colors flex items-center gap-1.5 cursor-pointer border border-neutral-800"
          >
            <Globe className="w-3.5 h-3.5 text-neutral-400" />
            <span>{utilityState.selectedCurrency}</span>
            <ChevronDown className="w-3 h-3 opacity-60" />
          </button>
          {isCurrencyDropdownOpen && (
            <div className="absolute right-0 top-full mt-2 w-36 bg-neutral-900 border border-neutral-800 rounded-2xl shadow-2xl p-1 z-50 animate-in fade-in zoom-in-95">
              {CURRENCIES.map((currency) => (
                <button
                  key={currency}
                  type="button"
                  onClick={() => {
                    onUtilityChange({ selectedCurrency: currency });
                    setIsCurrencyDropdownOpen(false);
                  }}
                  className={`w-full px-3 py-1.5 rounded-xl text-xs text-left font-medium transition-colors flex items-center justify-between ${
                    utilityState.selectedCurrency === currency
                      ? 'bg-indigo-600 text-white'
                      : 'text-neutral-300 hover:bg-neutral-800 hover:text-white'
                  }`}
                >
                  <span>{currency}</span>
                  {utilityState.selectedCurrency === currency && <Check className="w-3.5 h-3.5" />}
                </button>
              ))}
            </div>
          )}
        </div>
      )}
      {utilityState.showUserAccount && (
        <div className="relative">
          <button
            type="button"
            onClick={() => setIsUserPopoverOpen((open) => !open)}
            className="p-2 rounded-xl text-neutral-300 hover:text-white hover:bg-neutral-800/80 transition-colors cursor-pointer"
          >
            <User className="w-4 h-4" />
          </button>
          {isUserPopoverOpen && (
            <div className="absolute right-0 top-full mt-2 w-56 bg-neutral-900 border border-neutral-800 rounded-2xl shadow-2xl p-2 z-50">
              <div className="p-3 rounded-2xl bg-neutral-950 border border-neutral-800 space-y-2">
                <div className="text-xs font-bold text-white">Member Studio</div>
                <div className="text-[11px] text-neutral-400">Login/Register popover simulator</div>
              </div>
            </div>
          )}
        </div>
      )}
      {utilityState.showCartBadge && (
        <button
          type="button"
          onClick={() => setIsCartSlideoverOpen((open) => !open)}
          className="p-2 bg-indigo-600 text-white rounded-xl flex items-center gap-1 text-xs font-bold shadow-md shadow-indigo-600/30 cursor-pointer"
        >
          <ShoppingBag className="w-4 h-4" />
          <span>{utilityState.cartCount}</span>
        </button>
      )}
    </div>
  );

  const renderHeader = () => {
    switch (settings.layout) {
      case 'centered-luxury':
        return (
          <header className="border-b border-neutral-800">
            <div className="px-6 py-3 flex items-center justify-between text-[11px] text-neutral-400 border-b border-neutral-900">
              <span>Worldwide Atelier Dispatch</span>
              <span>Concierge + Warranty Registration</span>
            </div>
            <div className="px-6 py-5 flex flex-col items-center gap-4">
              <div className="font-serif tracking-[0.6em] text-lg text-white">AURORA</div>
              <nav className="hidden md:flex items-center gap-6">
                {headerMenus.map((item) => (
                  <button
                    key={item.id}
                    onMouseEnter={() => setActiveMenuId(item.id)}
                    onClick={() => setActiveMenuId(item.id)}
                    className={`text-xs uppercase tracking-wider font-semibold transition-all flex items-center gap-1 cursor-pointer ${
                      activeMenuId === item.id
                        ? 'text-white border-b-2 border-indigo-500 pb-1'
                        : 'text-neutral-400 hover:text-white'
                    }`}
                  >
                    <span>{item.title}</span>
                    {item.badge && (
                      <span className="text-[8px] px-1 py-0.2 rounded bg-amber-500/20 text-amber-300 border border-amber-500/40">
                        {item.badge}
                      </span>
                    )}
                  </button>
                ))}
              </nav>
              {utilityCluster}
            </div>
          </header>
        );
      case 'floating-pill':
        return (
          <div className="p-4 sm:p-6 bg-gradient-to-b from-neutral-900/60 to-neutral-950">
            <header className="max-w-4xl mx-auto h-14 px-5 rounded-full bg-neutral-900/80 border border-neutral-700/80 backdrop-blur-xl shadow-2xl flex items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="w-7 h-7 rounded-lg bg-indigo-600 flex items-center justify-center font-bold text-xs text-white">A</div>
                <span className="font-bold tracking-wider text-xs text-white">AURORA</span>
              </div>
              <nav className="hidden md:flex items-center gap-1">
                {headerMenus.map((item) => (
                  <button
                    key={item.id}
                    onClick={() => setActiveMenuId(item.id)}
                    onMouseEnter={() => setActiveMenuId(item.id)}
                    className={`px-3 py-1.5 rounded-full text-xs font-semibold transition-all cursor-pointer ${
                      activeMenuId === item.id
                        ? 'bg-white text-neutral-950 font-bold'
                        : 'text-neutral-300 hover:text-white hover:bg-neutral-800'
                    }`}
                  >
                    {item.title}
                  </button>
                ))}
              </nav>
              {utilityCluster}
            </header>
          </div>
        );
      case 'mega-catalog':
        return (
          <header className="border-b border-neutral-800">
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
              {utilityCluster}
            </div>
            <div className="px-6 h-11 flex items-center justify-between bg-neutral-900/90 text-xs">
              <div className="flex items-center gap-2">
                <button className="px-3.5 py-1.5 rounded-lg bg-indigo-600 text-white font-bold flex items-center gap-2">
                  <Menu className="w-3.5 h-3.5" />
                  <span>All Departments</span>
                </button>
                <nav className="hidden sm:flex items-center gap-1 ml-2">
                  {headerMenus.map((item) => (
                    <button
                      key={item.id}
                      onClick={() => setActiveMenuId(item.id)}
                      onMouseEnter={() => setActiveMenuId(item.id)}
                      className={`px-3 py-1.5 rounded-lg font-semibold transition-colors ${
                        activeMenuId === item.id ? 'bg-neutral-800 text-white' : 'text-neutral-400 hover:text-white'
                      }`}
                    >
                      {item.title}
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
        );
      case 'editorial-drawer':
        return (
          <header className="px-6 h-18 flex items-center justify-between border-b border-neutral-800/70">
            <button
              onClick={() => setIsDrawerMenuOpen((open) => !open)}
              className="px-3 py-1.5 rounded-xl bg-neutral-900 border border-neutral-800 text-neutral-300 hover:text-white flex items-center gap-2 text-xs font-semibold cursor-pointer"
            >
              <Menu className="w-4 h-4" />
              <span>Menu</span>
            </button>
            <div className="font-serif tracking-widest text-base sm:text-lg font-bold text-white">
              A U R O R A   S T U D I O
            </div>
            <div className="flex items-center gap-3">
              {utilityCluster}
            </div>
          </header>
        );
      case 'atelier':
      default:
        return (
          <header className="px-5 sm:px-8 h-18 flex items-center justify-between border-b border-neutral-800/70">
            <div className="flex items-center gap-8">
              <div className="flex items-center gap-2.5 cursor-pointer group">
                <div className="w-8 h-8 rounded-xl bg-indigo-600 flex items-center justify-center shadow-lg shadow-indigo-600/30 text-white font-black text-sm group-hover:scale-105 transition-transform">
                  <Zap className="w-4 h-4 text-white fill-white" />
                </div>
                <span className="font-black tracking-widest text-base text-white">AURORA</span>
              </div>
              <nav className="hidden md:flex items-center gap-1">
                {headerMenus.map((item) => {
                  const isActive = activeMenuId === item.id;
                  return (
                    <button
                      key={item.id}
                      onClick={() => setActiveMenuId(item.id)}
                      onMouseEnter={() => setActiveMenuId(item.id)}
                      className={`px-3 py-2 rounded-xl text-xs font-semibold transition-all flex items-center gap-1.5 cursor-pointer ${
                        isActive
                          ? 'bg-neutral-800/90 text-white shadow-xs'
                          : 'text-neutral-300 hover:text-white hover:bg-neutral-800/50'
                      }`}
                    >
                      <span>{item.title}</span>
                      {item.badge && (
                        <span className="text-[9px] font-bold px-1.5 py-0.2 rounded-full bg-indigo-500/30 text-indigo-300 border border-indigo-500/40">
                          {item.badge}
                        </span>
                      )}
                      {item.columns.length > 0 && (
                        <ChevronDown className={`w-3.5 h-3.5 transition-transform duration-200 ${isActive ? 'rotate-180 text-indigo-400' : 'opacity-50'}`} />
                      )}
                    </button>
                  );
                })}
              </nav>
            </div>
            {utilityCluster}
          </header>
        );
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-3 p-3.5 rounded-2xl bg-neutral-900 border border-neutral-800">
        <div className="flex items-center gap-2 overflow-x-auto pb-1 lg:pb-0 scrollbar-none">
          <span className="text-[11px] font-bold uppercase tracking-wider text-neutral-400 shrink-0 flex items-center gap-1 mr-1">
            <Compass className="w-3.5 h-3.5 text-indigo-400" /> Header Templates:
          </span>
          {(Object.keys(TEMPLATE_DESCRIPTIONS) as StorefrontShellLayout[]).map((layout) => {
            const info = TEMPLATE_DESCRIPTIONS[layout];
            const isSelected = settings.layout === layout;
            return (
              <button
                key={layout}
                type="button"
                onClick={() => onSelectLayout(layout)}
                className={`px-3 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-all flex items-center gap-1.5 cursor-pointer shrink-0 ${
                  isSelected
                    ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30 ring-1 ring-indigo-400'
                    : 'bg-neutral-800/80 text-neutral-300 hover:bg-neutral-800 hover:text-white border border-neutral-700/60'
                }`}
              >
                <span>{info.icon}</span>
                <span>{info.name}</span>
                {isSelected && <span className="text-[9px] px-1 py-0.2 rounded bg-white/20 text-white font-mono">Active</span>}
              </button>
            );
          })}
        </div>

        <div className="flex items-center gap-2 shrink-0 self-end lg:self-center">
          <button
            type="button"
            onClick={onEditAnnouncement}
            className="px-3 py-1.5 rounded-xl text-xs font-semibold bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/40 transition-colors flex items-center gap-1.5 cursor-pointer"
          >
            <Megaphone className="w-3.5 h-3.5 text-amber-400" />
            <span>Edit Announcement Bar</span>
          </button>
          <button
            type="button"
            onClick={() => setIsCustomizing((open) => !open)}
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

      {isCustomizing && (
        <div className="p-4 rounded-2xl bg-neutral-900/90 border border-neutral-800 grid grid-cols-2 sm:grid-cols-5 gap-3 animate-in fade-in duration-150">
          <label className="flex items-center gap-2 text-xs text-neutral-300 cursor-pointer">
            <input type="checkbox" checked={utilityState.showSearch} onChange={(e) => onUtilityChange({ showSearch: e.target.checked })} className="rounded accent-indigo-600" />
            <span>Search Icon</span>
          </label>
          <label className="flex items-center gap-2 text-xs text-neutral-300 cursor-pointer">
            <input type="checkbox" checked={utilityState.showCurrency} onChange={(e) => onUtilityChange({ showCurrency: e.target.checked })} className="rounded accent-indigo-600" />
            <span>Currency Switcher</span>
          </label>
          <label className="flex items-center gap-2 text-xs text-neutral-300 cursor-pointer">
            <input type="checkbox" checked={utilityState.showUserAccount} onChange={(e) => onUtilityChange({ showUserAccount: e.target.checked })} className="rounded accent-indigo-600" />
            <span>Member Studio (User)</span>
          </label>
          <label className="flex items-center gap-2 text-xs text-neutral-300 cursor-pointer">
            <input type="checkbox" checked={utilityState.showCartBadge} onChange={(e) => onUtilityChange({ showCartBadge: e.target.checked })} className="rounded accent-indigo-600" />
            <span>Cart Bag Icon</span>
          </label>
          <label className="flex items-center gap-2 text-xs text-neutral-300 cursor-pointer">
            <input type="checkbox" checked={settings.enabled} onChange={(e) => onAnnouncementEnabledChange(e.target.checked)} className="rounded accent-indigo-600" />
            <span>Announcement Bar</span>
          </label>
        </div>
      )}

      <div className="flex items-center justify-between p-3 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800">
        <div className="flex items-center gap-2">
          <span className="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
          <span className="text-xs font-bold text-neutral-900 dark:text-white">Live Storefront Customer Simulation Viewport</span>
        </div>
        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={() => setViewport('desktop')}
            className={`px-3 py-1.5 rounded-xl text-xs font-semibold flex items-center gap-1.5 cursor-pointer ${
              viewport === 'desktop' ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900 shadow-xs' : 'text-neutral-500 hover:text-neutral-900 dark:hover:text-white'
            }`}
          >
            <Monitor className="w-3.5 h-3.5" />
            <span>Desktop View</span>
          </button>
          <button
            type="button"
            onClick={() => setViewport('mobile')}
            className={`px-3 py-1.5 rounded-xl text-xs font-semibold flex items-center gap-1.5 cursor-pointer ${
              viewport === 'mobile' ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900 shadow-xs' : 'text-neutral-500 hover:text-neutral-900 dark:hover:text-white'
            }`}
          >
            <Smartphone className="w-3.5 h-3.5" />
            <span>Mobile View</span>
          </button>
        </div>
      </div>

      <div className={`mx-auto rounded-3xl bg-neutral-950 border border-neutral-800 shadow-2xl overflow-hidden transition-all duration-300 ${viewport === 'mobile' ? 'max-w-md' : 'w-full'}`}>
        {settings.enabled && (
          <div className="py-2.5 px-4 sm:px-6 text-xs transition-all border-b border-white/10" style={announcementStyle}>
            <div className="max-w-7xl mx-auto flex items-center justify-between gap-4">
              <div className="hidden md:flex items-center gap-2 text-[11px]">
                {settings.zones.left.badge && (
                  <span className="px-1.5 py-0.2 rounded-full bg-white/20 text-[9px] font-bold tracking-wider">
                    {settings.zones.left.badge}
                  </span>
                )}
                <span className="font-medium opacity-90">{settings.zones.left.text}</span>
              </div>
              <div className="flex-1 text-center truncate flex items-center justify-center gap-2">
                <span className="font-semibold">{settings.zones.center.text}</span>
                {settings.zones.center.couponCode && (
                  <span className="px-2 py-0.5 rounded bg-amber-400 text-neutral-950 text-[10px] font-mono font-black tracking-wide shadow-xs">
                    {settings.zones.center.couponCode}
                  </span>
                )}
              </div>
              <div className="hidden lg:flex items-center gap-3 text-[11px]">
                {settings.zones.right.whatsappEnabled && settings.zones.right.whatsappLabel && (
                  <span className="font-mono flex items-center gap-1 opacity-90">
                    <Phone className="w-3 h-3 text-emerald-400" />
                    {settings.zones.right.whatsappLabel}
                  </span>
                )}
                {settings.zones.right.trackingEnabled && settings.zones.right.helperText && (
                  <span className="flex items-center gap-1 hover:underline cursor-pointer opacity-90">
                    <Truck className="w-3 h-3 text-cyan-400" />
                    {settings.zones.right.helperText}
                  </span>
                )}
              </div>
            </div>
          </div>
        )}

        <div className="flex items-center justify-between px-5 py-2.5 bg-neutral-950 border-b border-neutral-850 text-xs">
          <div className="flex items-center gap-2.5">
            <span className="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
            <span className="font-bold text-neutral-200">Interactive Storefront Preview (Customer Viewport)</span>
            <span className="px-2 py-0.5 rounded-full text-[10px] font-mono bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
              {TEMPLATE_DESCRIPTIONS[settings.layout].name}
            </span>
          </div>
          <div className="hidden lg:flex items-center gap-3 text-[11px] text-neutral-400">
            <span>Hover menu tabs to trigger Mega Menu • Click Member Icon to preview Auth Popover</span>
          </div>
        </div>

        <div className="transition-all duration-200 relative bg-neutral-950 text-white">
          {renderHeader()}

          {activeMenu && activeMenu.columns.length > 0 && settings.layout !== 'editorial-drawer' && (
            <div className="p-6 bg-neutral-950 border-t border-neutral-800 animate-in fade-in slide-in-from-top-1 duration-150">
              <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-8 max-w-7xl mx-auto">
                {activeMenu.columns.map((column) => (
                  <div key={column.id} className="space-y-3">
                    <div className="flex items-center justify-between pb-2 border-b border-neutral-800">
                      <div className="flex items-center gap-2 text-xs font-bold text-white tracking-wide">
                        <span>{column.title}</span>
                        {column.badge && (
                          <span className="text-[9px] font-bold px-1.5 py-0.2 rounded bg-cyan-500/20 text-cyan-300 border border-cyan-500/30">
                            {column.badge}
                          </span>
                        )}
                      </div>
                    </div>
                    <ul className="space-y-2">
                      {column.items.map((leaf) => (
                        <li key={leaf.id}>
                          <a href="#preview" onClick={(event) => event.preventDefault()} className="group flex items-center justify-between text-xs text-neutral-400 hover:text-white transition-colors">
                            <span className="group-hover:translate-x-0.5 transition-transform">{leaf.title}</span>
                            {leaf.badge && (
                              <span className="text-[9px] font-bold px-1.5 py-0.2 rounded bg-rose-500/20 text-rose-400 border border-rose-500/30">
                                {leaf.badge}
                              </span>
                            )}
                          </a>
                        </li>
                      ))}
                      {column.items.length === 0 && <li className="text-[11px] text-neutral-600 italic">No Level 3 sub-items</li>}
                    </ul>
                  </div>
                ))}
                <div className="hidden lg:block p-4 rounded-2xl bg-gradient-to-br from-indigo-950/60 to-neutral-900 border border-indigo-500/30 space-y-2">
                  <div className="text-[10px] font-bold uppercase tracking-wider text-indigo-400">Featured Release</div>
                  <div className="text-xs font-bold text-white">Planar Magnetic Studio ANC</div>
                  <p className="text-[11px] text-neutral-400 leading-snug">50mm custom beryllium drivers with active magnetic damping.</p>
                  <div className="pt-2 flex items-center justify-between">
                    <span className="text-xs font-bold text-emerald-400">$389.00</span>
                    <span className="text-[10px] font-bold text-indigo-300 flex items-center gap-1 hover:underline cursor-pointer">
                      Explore <ArrowRight className="w-3 h-3" />
                    </span>
                  </div>
                </div>
              </div>
            </div>
          )}
        </div>

        <div className="p-8 sm:p-12 text-center bg-gradient-to-b from-neutral-900 to-neutral-950 text-white space-y-4">
          <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/20 text-indigo-300 text-xs font-semibold border border-indigo-500/30">
            <Sparkles className="w-3.5 h-3.5" />
            Storefront Canvas Simulation
          </div>
          <h2 className="text-2xl sm:text-4xl font-extrabold tracking-tight">Experience Acoustic Purity & Precision</h2>
          <p className="text-xs sm:text-sm text-neutral-400 max-w-lg mx-auto">
            Explore hand-crafted audiophile acoustics, lossless planar drivers & bespoke accessories.
          </p>
          <div className="pt-2 flex justify-center gap-3">
            <span className="px-5 py-2.5 rounded-2xl bg-white text-neutral-950 font-bold text-xs">Shop Collection</span>
            <span className="px-5 py-2.5 rounded-2xl bg-neutral-800 text-white font-bold text-xs border border-neutral-700">Explore Stories</span>
          </div>
        </div>

        {isSearchOpen && (
          <div className="p-5 bg-neutral-900 border-t border-neutral-800 animate-in fade-in duration-150">
            <div className="max-w-2xl mx-auto space-y-3">
              <div className="flex items-center gap-3">
                <div className="relative flex-1">
                  <Search className="w-4 h-4 text-neutral-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                  <input
                    type="text"
                    value={searchQuery}
                    onChange={(event) => setSearchQuery(event.target.value)}
                    placeholder="Search audiophile headphones, titanium chassis, cables..."
                    className="w-full pl-10 pr-4 py-2.5 bg-neutral-950 rounded-2xl border border-neutral-700 text-sm text-white placeholder-neutral-500 focus:ring-2 focus:ring-indigo-500 focus:outline-hidden"
                    autoFocus
                  />
                </div>
                <button type="button" onClick={() => setIsSearchOpen(false)} className="p-2 text-neutral-400 hover:text-white rounded-xl bg-neutral-800">
                  <X className="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>
        )}

        {isCartSlideoverOpen && (
          <div className="absolute right-0 top-0 bottom-0 w-80 bg-neutral-900 border-l border-neutral-700 shadow-2xl p-4 z-50 flex flex-col animate-in slide-in-from-right duration-200">
            <div className="flex items-center justify-between pb-3 border-b border-neutral-800">
              <div className="flex items-center gap-2">
                <ShoppingBag className="w-4 h-4 text-indigo-400" />
                <span className="font-bold text-sm text-white">Your Shopping Bag ({utilityState.cartCount})</span>
              </div>
              <button type="button" onClick={() => setIsCartSlideoverOpen(false)} className="p-1 rounded-lg text-neutral-400 hover:text-white hover:bg-neutral-800">
                <X className="w-4 h-4" />
              </button>
            </div>
            <div className="flex-1 py-3 space-y-3 overflow-y-auto">
              <div className="p-3 rounded-2xl bg-neutral-950 border border-neutral-800 flex gap-3">
                <div className="w-12 h-12 rounded-xl bg-neutral-800 flex items-center justify-center text-xl shrink-0">🎧</div>
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
          </div>
        )}

        {isDrawerMenuOpen && (
          <div className="absolute inset-y-0 left-0 w-72 bg-neutral-900 border-r border-neutral-800 z-40 p-5 animate-in slide-in-from-left duration-200">
            <div className="flex items-center justify-between mb-5">
              <div className="text-sm font-bold text-white">Lookbook Navigation</div>
              <button type="button" onClick={() => setIsDrawerMenuOpen(false)} className="p-2 rounded-xl bg-neutral-800 text-neutral-300 hover:text-white">
                <X className="w-4 h-4" />
              </button>
            </div>
            <div className="space-y-3">
              {headerMenus.map((item) => (
                <button
                  key={item.id}
                  type="button"
                  onClick={() => {
                    setActiveMenuId(item.id);
                    setIsDrawerMenuOpen(false);
                  }}
                  className="w-full text-left p-3 rounded-2xl bg-neutral-950 border border-neutral-800 text-sm font-semibold text-white"
                >
                  {item.title}
                </button>
              ))}
            </div>
          </div>
        )}
      </div>
    </div>
  );
};
