import React, { useEffect, useState } from 'react';
import { useAdmin } from '../../context/AdminContext';
import {
  DEFAULT_STOREFRONT_SHELL_SETTINGS,
  type StorefrontShellLayout,
  type StorefrontShellSettings,
  type StorefrontShellTheme,
} from '../../types';
import {
  Sparkles,
  SlidersHorizontal,
  Eye,
  Megaphone,
  Palette,
  Truck,
  RotateCcw,
  Save,
  CheckCircle2,
  AlertCircle,
  ExternalLink,
  ChevronRight,
  Zap,
  Globe,
  DollarSign,
  Phone,
  Layout,
  Layers,
  ArrowRight,
  Sun,
  Moon,
  Search,
  User,
  ShoppingBag,
  Info,
  Check,
  Compass,
  Monitor
} from 'lucide-react';

type HeaderLayoutTemplate =
  | 'modern_minimalist'
  | 'centered_luxury'
  | 'floating_pill'
  | 'mega_catalog'
  | 'editorial_drawer';

type AnnouncementBarTheme =
  | 'solid_dark'
  | 'brand_indigo'
  | 'warm_amber'
  | 'emerald_deals'
  | 'crimson_sale'
  | 'custom';

interface AnnouncementBarConfig {
  enabled: boolean;
  leftEnabled: boolean;
  leftText: string;
  leftLink?: string;
  leftBadge?: string;
  centerEnabled: boolean;
  centerText: string;
  centerHighlightText?: string;
  centerLink?: string;
  rightEnabled: boolean;
  rightText: string;
  rightLink?: string;
  showCurrencySelector: boolean;
  showLogisticsTracker: boolean;
  showWhatsAppHotline: boolean;
  whatsAppNumber?: string;
  theme: AnnouncementBarTheme;
  customBgColor?: string;
  customTextColor?: string;
  isDismissible: boolean;
}

const LEGACY_LAYOUT_TO_SHELL: Record<HeaderLayoutTemplate, StorefrontShellLayout> = {
  modern_minimalist: 'atelier',
  centered_luxury: 'centered-luxury',
  floating_pill: 'floating-pill',
  mega_catalog: 'mega-catalog',
  editorial_drawer: 'editorial-drawer',
};

const SHELL_LAYOUT_TO_LEGACY: Record<StorefrontShellLayout, HeaderLayoutTemplate> = {
  atelier: 'modern_minimalist',
  'centered-luxury': 'centered_luxury',
  'floating-pill': 'floating_pill',
  'mega-catalog': 'mega_catalog',
  'editorial-drawer': 'editorial_drawer',
};

const LEGACY_THEME_TO_SHELL: Record<AnnouncementBarTheme, StorefrontShellTheme> = {
  solid_dark: 'midnight-obsidian',
  brand_indigo: 'aurora-indigo',
  emerald_deals: 'emerald-logistics',
  warm_amber: 'amber-flash',
  crimson_sale: 'crimson-campaign',
  custom: 'custom',
};

const SHELL_THEME_TO_LEGACY: Record<StorefrontShellTheme, AnnouncementBarTheme> = {
  'midnight-obsidian': 'solid_dark',
  'aurora-indigo': 'brand_indigo',
  'emerald-logistics': 'emerald_deals',
  'amber-flash': 'warm_amber',
  'crimson-campaign': 'crimson_sale',
  custom: 'custom',
};

const nullable = (value: string | undefined): string | null => {
  const trimmed = value?.trim() ?? '';
  return trimmed === '' ? null : trimmed;
};

const whatsAppUrl = (number: string | undefined, current: string | null): string | null => {
  const digits = number?.replace(/\D/g, '') ?? '';
  return digits === '' ? current : `https://wa.me/${digits}`;
};

export const announcementDraftFromSettings = (settings: StorefrontShellSettings): AnnouncementBarConfig => ({
  enabled: settings.enabled,
  leftEnabled: settings.zones.left.enabled,
  leftText: settings.zones.left.text,
  leftBadge: settings.zones.left.badge,
  leftLink: settings.zones.left.linkUrl ?? '',
  centerEnabled: settings.zones.center.enabled,
  centerText: settings.zones.center.text,
  centerHighlightText: settings.zones.center.couponCode,
  centerLink: settings.zones.center.linkUrl ?? '',
  rightEnabled: settings.zones.right.enabled,
  rightText: settings.zones.right.helperText,
  rightLink: settings.zones.right.trackingUrl ?? '',
  showCurrencySelector: true,
  showLogisticsTracker: settings.zones.right.trackingEnabled,
  showWhatsAppHotline: settings.zones.right.whatsappEnabled,
  whatsAppNumber: settings.zones.right.whatsappLabel,
  theme: SHELL_THEME_TO_LEGACY[settings.theme],
  customBgColor: settings.customPalette.background ?? '#0f172a',
  customTextColor: settings.customPalette.text ?? '#ffffff',
  isDismissible: true,
});

export const storefrontShellSettingsFromDraft = (
  persisted: StorefrontShellSettings,
  formData: AnnouncementBarConfig,
  layout: StorefrontShellLayout,
): StorefrontShellSettings => ({
  enabled: formData.enabled,
  layout,
  theme: LEGACY_THEME_TO_SHELL[formData.theme],
  customPalette: {
    background: formData.theme === 'custom' ? nullable(formData.customBgColor) : persisted.customPalette.background,
    text: formData.theme === 'custom' ? nullable(formData.customTextColor) : persisted.customPalette.text,
    accent: persisted.customPalette.accent,
  },
  zones: {
    left: {
      enabled: nullable(formData.leftText) !== null,
      text: formData.leftText,
      badge: formData.leftBadge ?? '',
      linkUrl: nullable(formData.leftLink),
    },
    center: {
      enabled: nullable(formData.centerText) !== null,
      text: formData.centerText,
      couponCode: formData.centerHighlightText ?? '',
      linkUrl: nullable(formData.centerLink),
    },
    right: {
      enabled: nullable(formData.rightText) !== null,
      helperText: formData.rightText,
      trackingEnabled: formData.showLogisticsTracker,
      trackingLabel: persisted.zones.right.trackingLabel || 'Track order',
      trackingUrl: formData.showLogisticsTracker ? nullable(formData.rightLink) : persisted.zones.right.trackingUrl,
      whatsappEnabled: formData.showWhatsAppHotline,
      whatsappLabel: formData.whatsAppNumber ?? '',
      whatsappUrl: formData.showWhatsAppHotline
        ? whatsAppUrl(formData.whatsAppNumber, persisted.zones.right.whatsappUrl)
        : persisted.zones.right.whatsappUrl,
    },
  },
});

export const canSaveStorefrontShellLayout = (layout: StorefrontShellLayout): boolean => layout === 'atelier';

export const persistStorefrontShellDraft = async (
  settings: StorefrontShellSettings,
  save: (settings: StorefrontShellSettings) => Promise<StorefrontShellSettings | null>,
): Promise<StorefrontShellSettings | null> => {
  if (!canSaveStorefrontShellLayout(settings.layout)) return null;
  return save(settings);
};

const PRESET_THEMES: {
  id: AnnouncementBarTheme;
  name: string;
  desc: string;
  bgClass: string;
  textClass: string;
  borderClass: string;
  previewBg: string;
  previewText: string;
}[] = [
  {
    id: 'solid_dark',
    name: 'Midnight Obsidian',
    desc: 'Deep slate dark canvas for luxury, tech & modern minimalist stores',
    bgClass: 'bg-neutral-900',
    textClass: 'text-neutral-200',
    borderClass: 'border-neutral-800',
    previewBg: '#0f172a',
    previewText: '#f8fafc',
  },
  {
    id: 'brand_indigo',
    name: 'Aurora Indigo',
    desc: 'Brand signature deep indigo gradient with energetic violet glow',
    bgClass: 'bg-gradient-to-r from-indigo-950 via-indigo-900 to-violet-950',
    textClass: 'text-indigo-100',
    borderClass: 'border-indigo-800/60',
    previewBg: '#312e81',
    previewText: '#e0e7ff',
  },
  {
    id: 'emerald_deals',
    name: 'Emerald Logistics & Trust',
    desc: 'Trust-building deep forest green for free shipping and guaranteed authentic badges',
    bgClass: 'bg-emerald-950',
    textClass: 'text-emerald-200',
    borderClass: 'border-emerald-800/60',
    previewBg: '#064e3b',
    previewText: '#a7f3d0',
  },
  {
    id: 'warm_amber',
    name: 'Amber Flash Deals',
    desc: 'High-contrast warm amber notice for limited-time offers and coupon codes',
    bgClass: 'bg-amber-950',
    textClass: 'text-amber-200',
    borderClass: 'border-amber-800/60',
    previewBg: '#78350f',
    previewText: '#fde68a',
  },
  {
    id: 'crimson_sale',
    name: 'Crimson Campaign',
    desc: 'Urgent red notice bar for flash sales, clearance, and seasonal promotions',
    bgClass: 'bg-rose-950',
    textClass: 'text-rose-100',
    borderClass: 'border-rose-800/60',
    previewBg: '#881337',
    previewText: '#ffe4e6',
  },
  {
    id: 'custom',
    name: 'Custom Hex Palette',
    desc: 'Specify exact background and font color codes matching your brand guide',
    bgClass: 'bg-neutral-900',
    textClass: 'text-white',
    borderClass: 'border-neutral-700',
    previewBg: '#1e293b',
    previewText: '#ffffff',
  },
];

const TEMPLATE_PREVIEWS: Record<
  HeaderLayoutTemplate,
  { name: string; tag: string; desc: string; icon: string }
> = {
  modern_minimalist: {
    name: 'Modern Atelier (D2C)',
    tag: 'Recommended',
    desc: 'Logo left, centered navigation, utility hub right with member studio popover.',
    icon: '✨',
  },
  centered_luxury: {
    name: 'Haute Couture (Centered)',
    tag: 'Luxury',
    desc: 'Balanced dual-tier layout with grand centered brand typography & centered nav.',
    icon: '👑',
  },
  floating_pill: {
    name: 'Glassmorphic Floating Pill',
    tag: 'Tech',
    desc: 'Floating capsule nav bar with backdrop blur, rounded borders, and ambient glow.',
    icon: '⚡',
  },
  mega_catalog: {
    name: 'Mega Catalog Superstore',
    tag: 'High SKU',
    desc: 'Multi-row header with prominent search field, customer line, and category tabs.',
    icon: '🏢',
  },
  editorial_drawer: {
    name: 'Editorial Minimal (Drawer)',
    tag: 'Lookbook',
    desc: 'Off-canvas hamburger drawer for lookbook imagery with minimal brand mark.',
    icon: '📖',
  },
};

interface StorefrontHeaderAnnouncementEditorProps {
  initialTab?: 'announcement' | 'header_layout' | 'preview';
  initialPreviewViewport?: 'desktop' | 'mobile';
}

export const StorefrontHeaderAnnouncementEditor: React.FC<StorefrontHeaderAnnouncementEditorProps> = ({
  initialTab = 'announcement',
  initialPreviewViewport = 'desktop',
}) => {
  const {
    storefrontShellSettings,
    saveStorefrontShellSettings,
    setCurrentSection,
    headerMenus = []
  } = useAdmin();

  // Local draft state for smooth editing and batch save
  const [formData, setFormData] = useState<AnnouncementBarConfig>(() => announcementDraftFromSettings(storefrontShellSettings));

  const [headerSettings, setHeaderSettings] = useState<{
    template: HeaderLayoutTemplate;
    colorTheme: 'dark' | 'light' | 'glass' | 'titanium';
    showSearch: boolean;
    showThemeToggle: boolean;
    showCurrency: boolean;
    showUserAccount: boolean;
    showCartBadge: boolean;
    selectedCurrency: string;
    stickyHeader: boolean;
  }>(() => ({
    template: SHELL_LAYOUT_TO_LEGACY[storefrontShellSettings.layout],
    colorTheme: 'dark',
    showSearch: true,
    showThemeToggle: true,
    showCurrency: true,
    showUserAccount: true,
    showCartBadge: true,
    selectedCurrency: 'MYR (RM)',
    stickyHeader: true,
  }));

  const [activeTab, setActiveTab] = useState<'announcement' | 'header_layout' | 'preview'>(initialTab);
  const [isSavedRecently, setIsSavedRecently] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [previewViewport, setPreviewViewport] = useState<'desktop' | 'mobile'>(initialPreviewViewport);

  useEffect(() => {
    setFormData(announcementDraftFromSettings(storefrontShellSettings));
    setHeaderSettings(prev => ({
      ...prev,
      template: SHELL_LAYOUT_TO_LEGACY[storefrontShellSettings.layout],
    }));
  }, [storefrontShellSettings]);

  const selectedLayout = LEGACY_LAYOUT_TO_SHELL[headerSettings.template];
  const canSaveLive = canSaveStorefrontShellLayout(selectedLayout);
  const draftSettings = storefrontShellSettingsFromDraft(storefrontShellSettings, formData, selectedLayout);
  // Quick Preset Handlers
  const applyPromoTemplate = (type: 'freeship' | 'authenticity' | 'flash_sale' | 'support_hotline') => {
    if (type === 'freeship') {
      setFormData(prev => ({
        ...prev,
        enabled: true,
        leftEnabled: true,
        centerEnabled: true,
        rightEnabled: true,
        leftText: '⚡ Official Flagship Store • 100% Authentic Guaranteed',
        leftBadge: 'OFFICIAL',
        leftLink: '/about',
        centerText: 'Free Expedited Courier Across Malaysia On Orders Over RM150',
        centerHighlightText: 'CODE: FREESHIP',
        centerLink: '/promotions',
        rightText: '📦 Track Order & Real-Time Logistics',
        rightLink: '/orders/track',
        theme: 'emerald_deals',
        showCurrencySelector: true,
        showLogisticsTracker: true,
        showWhatsAppHotline: false
      }));
    } else if (type === 'flash_sale') {
      setFormData(prev => ({
        ...prev,
        enabled: true,
        leftEnabled: true,
        centerEnabled: true,
        rightEnabled: true,
        leftText: '🔥 8.8 Mega Super Sale is Live Now!',
        leftBadge: 'FLASH SALE',
        leftLink: '/collections/sale',
        centerText: 'Up to 50% Off Top Audiophile Brands + Extra RM30 Voucher',
        centerHighlightText: 'CODE: MEGA88',
        centerLink: '/collections/clearance',
        rightText: '💬 24/7 Live Concierge Help',
        rightLink: '/support',
        theme: 'crimson_sale',
        showCurrencySelector: true,
        showLogisticsTracker: true,
        showWhatsAppHotline: true
      }));
    } else if (type === 'authenticity') {
      setFormData(prev => ({
        ...prev,
        enabled: true,
        leftEnabled: true,
        centerEnabled: true,
        rightEnabled: true,
        leftText: '🛡️ 100% Direct Official Import • 2-Year Authorized Local Warranty',
        leftBadge: 'CERTIFIED',
        leftLink: '/warranty',
        centerText: 'Same-Day Dispatch for Klang Valley & Fast Delivery Nationwide',
        centerHighlightText: 'FAST DELIVERY',
        centerLink: '/shipping',
        rightText: '📍 Visit Our Kuala Lumpur Experience Studio',
        rightLink: '/stores',
        theme: 'solid_dark',
        showCurrencySelector: true,
        showLogisticsTracker: true,
        showWhatsAppHotline: true
      }));
    } else if (type === 'support_hotline') {
      setFormData(prev => ({
        ...prev,
        enabled: true,
        leftEnabled: true,
        centerEnabled: true,
        rightEnabled: true,
        leftText: '💬 Customer Care & WhatsApp Hotline: +60 14-224 4200 (9am - 10pm)',
        leftBadge: 'HOTLINE',
        leftLink: 'https://wa.me/60142244200',
        centerText: 'Complimentary Audio Calibration & Free Ear-Tip Fitting Kit With Orders',
        centerHighlightText: 'FREE GIFT',
        centerLink: '/collections/accessories',
        rightText: '🚚 Express Courier Coverage (Peninsular & East MY)',
        rightLink: '/shipping',
        theme: 'brand_indigo',
        showCurrencySelector: true,
        showLogisticsTracker: true,
        showWhatsAppHotline: true,
        whatsAppNumber: '+60 14-224 4200'
      }));
    }
  };

  const handleSaveAll = async () => {
    if (!canSaveLive || isSaving) return;

    setIsSaving(true);
    const saved = await persistStorefrontShellDraft(draftSettings, saveStorefrontShellSettings);
    setIsSaving(false);

    if (saved) {
      setIsSavedRecently(true);
      window.setTimeout(() => setIsSavedRecently(false), 3000);
    }
  };

  const handleResetToDefaults = () => {
    if (window.confirm('Reset Storefront Header & Announcement Bar to factory presets?')) {
      setFormData(announcementDraftFromSettings(DEFAULT_STOREFRONT_SHELL_SETTINGS));
      setHeaderSettings({
        template: 'modern_minimalist',
        colorTheme: 'dark',
        showSearch: true,
        showThemeToggle: true,
        showCurrency: true,
        showUserAccount: true,
        showCartBadge: true,
        selectedCurrency: 'MYR (RM)',
        stickyHeader: true,
      });
    }
  };

  // Get style properties for announcement bar preview
  const getBarThemeStyles = () => {
    const matched = PRESET_THEMES.find(t => t.id === formData.theme);
    if (formData.theme === 'custom' && formData.customBgColor) {
      return {
        backgroundColor: formData.customBgColor,
        color: formData.customTextColor || '#ffffff',
        borderColor: 'rgba(255,255,255,0.1)'
      };
    }
    return matched ? {
      backgroundColor: matched.previewBg,
      color: matched.previewText,
      borderColor: 'rgba(255,255,255,0.1)'
    } : {
      backgroundColor: '#0f172a',
      color: '#f8fafc',
      borderColor: 'rgba(255,255,255,0.1)'
    };
  };

  return (
    <div className="space-y-6">
      {/* Top Header Breadcrumb & Actions */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-neutral-200 dark:border-neutral-800">
        <div>
          <div className="flex items-center gap-2 text-xs font-semibold text-neutral-500 dark:text-neutral-400 mb-1">
            <button
              onClick={() => setCurrentSection('storefront')}
              className="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
            >
              Storefront CMS
            </button>
            <ChevronRight className="w-3.5 h-3.5" />
            <span className="text-neutral-900 dark:text-white font-bold">
              Header & Announcement Bar
            </span>
          </div>
          <h1 className="text-2xl sm:text-3xl font-extrabold text-neutral-900 dark:text-white tracking-tight flex items-center gap-2.5">
            <Megaphone className="w-7 h-7 text-indigo-600 dark:text-indigo-400" />
            Header & Announcement Bar
          </h1>
          <p className="text-xs sm:text-sm text-neutral-600 dark:text-neutral-400 mt-1 max-w-2xl">
            Configure the top storefront notice bar (slogans, promotional campaigns, logistics tracking & customer service hotline) and header navigation template layout.
          </p>
        </div>

        <div className="flex items-center gap-2.5 shrink-0">
          <button
            type="button"
            onClick={handleResetToDefaults}
            className="px-3.5 py-2 rounded-xl text-xs font-semibold bg-neutral-200/80 hover:bg-neutral-300 dark:bg-neutral-800 dark:hover:bg-neutral-700 text-neutral-700 dark:text-neutral-300 transition-colors flex items-center gap-1.5 cursor-pointer"
          >
            <RotateCcw className="w-3.5 h-3.5" />
            <span>Reset</span>
          </button>

          <button
            type="button"
            onClick={handleSaveAll}
            disabled={!canSaveLive || isSaving}
            title={canSaveLive ? 'Save live changes' : 'Only Modern Atelier is currently available for live storefront publishing.'}
            className={`px-5 py-2 rounded-xl text-xs font-bold text-white shadow-md transition-all flex items-center gap-2 active:scale-98 ${
              canSaveLive && !isSaving
                ? 'bg-indigo-600 hover:bg-indigo-700 shadow-indigo-600/30 cursor-pointer'
                : 'bg-neutral-500 shadow-none cursor-not-allowed opacity-60'
            }`}
          >
            {isSavedRecently ? (
              <>
                <CheckCircle2 className="w-4 h-4 text-emerald-300" />
                <span>Saved Live!</span>
              </>
            ) : (
              <>
                <Save className="w-4 h-4" />
                <span>{isSaving ? 'Saving...' : 'Save Live Changes'}</span>
              </>
            )}
          </button>
        </div>
      </div>

      {!canSaveLive && (
        <div className="p-3.5 rounded-2xl border border-amber-500/30 bg-amber-500/10 text-amber-800 dark:text-amber-200 flex items-start gap-2.5">
          <AlertCircle className="w-4 h-4 mt-0.5 shrink-0" />
          <div>
            <p className="text-xs font-bold">Preview-only layout</p>
            <p className="text-[11px] mt-0.5 opacity-80">
              All five ZIP layouts remain available in the simulator. Only Modern Atelier can be published to the live storefront right now.
            </p>
          </div>
        </div>
      )}

      {/* Tabs Switcher */}
      <div className="flex items-center gap-2 border-b border-neutral-200 dark:border-neutral-800 pb-2">
        <button
          onClick={() => setActiveTab('announcement')}
          className={`px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 cursor-pointer ${
            activeTab === 'announcement'
              ? 'bg-indigo-600 text-white shadow-sm'
              : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-200/60 dark:hover:bg-neutral-800'
          }`}
        >
          <Megaphone className="w-3.5 h-3.5" />
          <span>Announcement Bar Content</span>
        </button>

        <button
          onClick={() => setActiveTab('header_layout')}
          className={`px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 cursor-pointer ${
            activeTab === 'header_layout'
              ? 'bg-indigo-600 text-white shadow-sm'
              : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-200/60 dark:hover:bg-neutral-800'
          }`}
        >
          <Layout className="w-3.5 h-3.5" />
          <span>Header Layout & Utilities</span>
        </button>

        <button
          onClick={() => setActiveTab('preview')}
          className={`px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 cursor-pointer ${
            activeTab === 'preview'
              ? 'bg-indigo-600 text-white shadow-sm'
              : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-200/60 dark:hover:bg-neutral-800'
          }`}
        >
          <Eye className="w-3.5 h-3.5" />
          <span>Interactive Live Preview</span>
        </button>
      </div>

      {/* ========================================================================= */}
      {/* TAB 1: ANNOUNCEMENT BAR CONTENT & THEME CONFIGURATION                     */}
      {/* ========================================================================= */}
      {activeTab === 'announcement' && (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Main Editing Column (2 Cols) */}
          <div className="lg:col-span-2 space-y-6">
            {/* 1. Master Switch & Quick Preset Buttons */}
            <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs space-y-4">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <div className={`w-10 h-10 rounded-xl flex items-center justify-center font-bold text-white shadow-xs ${
                    formData.enabled ? 'bg-indigo-600 shadow-indigo-600/30' : 'bg-neutral-400 dark:bg-neutral-700'
                  }`}>
                    <Megaphone className="w-5 h-5" />
                  </div>
                  <div>
                    <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                      Announcement Bar Master Switch
                    </h3>
                    <p className="text-xs text-neutral-500 dark:text-neutral-400">
                      Display top promotional bar above storefront navigation
                    </p>
                  </div>
                </div>

                <label className="relative inline-flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    checked={formData.enabled}
                    onChange={(e) => setFormData(prev => ({ ...prev, enabled: e.target.checked }))}
                    className="sr-only peer"
                  />
                  <div className="w-11 h-6 bg-neutral-300 peer-focus:outline-hidden rounded-full peer dark:bg-neutral-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-neutral-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-neutral-600 peer-checked:bg-indigo-600"></div>
                </label>
              </div>

              {/* Quick Template Presets */}
              <div className="pt-3 border-t border-neutral-100 dark:border-neutral-800/80">
                <div className="flex items-center justify-between mb-2">
                  <span className="text-xs font-semibold text-neutral-700 dark:text-neutral-300 flex items-center gap-1.5">
                    <Sparkles className="w-3.5 h-3.5 text-amber-500" />
                    Quick Marketing Presets:
                  </span>
                  <span className="text-[11px] text-neutral-400">Click to autofill wording & theme</span>
                </div>

                <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                  <button
                    type="button"
                    onClick={() => applyPromoTemplate('freeship')}
                    className="p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950/60 hover:border-emerald-500 text-left transition-all group cursor-pointer"
                  >
                    <div className="text-xs font-bold text-neutral-900 dark:text-white group-hover:text-emerald-500 flex items-center gap-1">
                      <span>🚚 Free Shipping</span>
                    </div>
                    <div className="text-[10px] text-neutral-500 truncate mt-0.5">Over RM150 Nationwide</div>
                  </button>

                  <button
                    type="button"
                    onClick={() => applyPromoTemplate('flash_sale')}
                    className="p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950/60 hover:border-rose-500 text-left transition-all group cursor-pointer"
                  >
                    <div className="text-xs font-bold text-neutral-900 dark:text-white group-hover:text-rose-500 flex items-center gap-1">
                      <span>🔥 Flash Sale</span>
                    </div>
                    <div className="text-[10px] text-neutral-500 truncate mt-0.5">Mega 8.8 50% Off</div>
                  </button>

                  <button
                    type="button"
                    onClick={() => applyPromoTemplate('authenticity')}
                    className="p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950/60 hover:border-indigo-500 text-left transition-all group cursor-pointer"
                  >
                    <div className="text-xs font-bold text-neutral-900 dark:text-white group-hover:text-indigo-500 flex items-center gap-1">
                      <span>🛡️ 100% Authentic</span>
                    </div>
                    <div className="text-[10px] text-neutral-500 truncate mt-0.5">Official Warranty Guarantee</div>
                  </button>

                  <button
                    type="button"
                    onClick={() => applyPromoTemplate('support_hotline')}
                    className="p-2.5 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950/60 hover:border-violet-500 text-left transition-all group cursor-pointer"
                  >
                    <div className="text-xs font-bold text-neutral-900 dark:text-white group-hover:text-violet-500 flex items-center gap-1">
                      <span>💬 VIP Care Line</span>
                    </div>
                    <div className="text-[10px] text-neutral-500 truncate mt-0.5">WhatsApp +6014-2244200</div>
                  </button>
                </div>
              </div>
            </div>

            {/* 2. Three-Zone Content Fields (Left / Center / Right) */}
            <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs space-y-5">
              <h3 className="text-sm font-bold text-neutral-900 dark:text-white flex items-center gap-2 pb-2 border-b border-neutral-100 dark:border-neutral-800">
                <Layout className="w-4 h-4 text-indigo-500" />
                Storefront Notice Content Zones (Left, Center & Right)
              </h3>

              {/* Zone 1: Left (Slogan / Badge / Trust Marker) */}
              <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-950/60 border border-neutral-200/80 dark:border-neutral-800/80 space-y-3">
                <div className="flex items-center justify-between">
                  <span className="text-xs font-bold text-neutral-800 dark:text-neutral-200 flex items-center gap-1.5">
                    <span className="w-2 h-2 rounded-full bg-blue-500"></span>
                    Zone A: Left Notice (Store Slogan / Guarantee)
                  </span>
                  <span className="text-[11px] text-neutral-500">Visible on Desktop & Tablet</span>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                  <div className="sm:col-span-2">
                    <label className="block text-[11px] font-semibold text-neutral-600 dark:text-neutral-400 mb-1">
                      Left Text / Slogan
                    </label>
                    <input
                      type="text"
                      value={formData.leftText}
                      onChange={e => setFormData(prev => ({ ...prev, leftText: e.target.value }))}
                      placeholder="e.g. ⚡ Official Flagship Store • 100% Authentic"
                      className="w-full px-3 py-2 text-xs rounded-xl bg-white dark:bg-neutral-900 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                    />
                  </div>

                  <div>
                    <label className="block text-[11px] font-semibold text-neutral-600 dark:text-neutral-400 mb-1">
                      Badge Pill (Optional)
                    </label>
                    <input
                      type="text"
                      value={formData.leftBadge || ''}
                      onChange={e => setFormData(prev => ({ ...prev, leftBadge: e.target.value }))}
                      placeholder="e.g. OFFICIAL"
                      className="w-full px-3 py-2 text-xs rounded-xl bg-white dark:bg-neutral-900 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                    />
                  </div>
                </div>

                <div>
                  <label className="block text-[11px] font-semibold text-neutral-600 dark:text-neutral-400 mb-1">
                    Left Link URL (Optional click target)
                  </label>
                  <input
                    type="text"
                    value={formData.leftLink || ''}
                    onChange={e => setFormData(prev => ({ ...prev, leftLink: e.target.value }))}
                    placeholder="e.g. /about or /guarantee"
                    className="w-full px-3 py-2 text-xs rounded-xl bg-white dark:bg-neutral-900 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                  />
                </div>
              </div>

              {/* Zone 2: Center (Hero Promo Banner & Coupon Code) */}
              <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-950/60 border border-neutral-200/80 dark:border-neutral-800/80 space-y-3">
                <div className="flex items-center justify-between">
                  <span className="text-xs font-bold text-neutral-800 dark:text-neutral-200 flex items-center gap-1.5">
                    <span className="w-2 h-2 rounded-full bg-amber-500"></span>
                    Zone B: Center Core Promo (Campaign & Voucher)
                  </span>
                  <span className="text-[11px] text-amber-500 font-semibold">Primary Centerpiece</span>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                  <div className="sm:col-span-2">
                    <label className="block text-[11px] font-semibold text-neutral-600 dark:text-neutral-400 mb-1">
                      Promo Headline / Notice Text
                    </label>
                    <input
                      type="text"
                      value={formData.centerText}
                      onChange={e => setFormData(prev => ({ ...prev, centerText: e.target.value }))}
                      placeholder="e.g. Free Expedited Courier Across Malaysia On Orders Over RM150"
                      className="w-full px-3 py-2 text-xs rounded-xl bg-white dark:bg-neutral-900 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                    />
                  </div>

                  <div>
                    <label className="block text-[11px] font-semibold text-neutral-600 dark:text-neutral-400 mb-1">
                      Highlight Tag / Code
                    </label>
                    <input
                      type="text"
                      value={formData.centerHighlightText || ''}
                      onChange={e => setFormData(prev => ({ ...prev, centerHighlightText: e.target.value }))}
                      placeholder="e.g. CODE: FREESHIP"
                      className="w-full px-3 py-2 text-xs font-mono font-bold rounded-xl bg-white dark:bg-neutral-900 border border-neutral-300 dark:border-neutral-700 text-amber-600 dark:text-amber-400 focus:ring-2 focus:ring-indigo-500"
                    />
                  </div>
                </div>

                <div>
                  <label className="block text-[11px] font-semibold text-neutral-600 dark:text-neutral-400 mb-1">
                    Promo Destination URL
                  </label>
                  <input
                    type="text"
                    value={formData.centerLink || ''}
                    onChange={e => setFormData(prev => ({ ...prev, centerLink: e.target.value }))}
                    placeholder="e.g. /collections/deals or /promotions"
                    className="w-full px-3 py-2 text-xs rounded-xl bg-white dark:bg-neutral-900 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                  />
                </div>
              </div>

              {/* Zone 3: Right (Logistics, Hotline & Quick Utilities) */}
              <div className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-950/60 border border-neutral-200/80 dark:border-neutral-800/80 space-y-3">
                <div className="flex items-center justify-between">
                  <span className="text-xs font-bold text-neutral-800 dark:text-neutral-200 flex items-center gap-1.5">
                    <span className="w-2 h-2 rounded-full bg-emerald-500"></span>
                    Zone C: Right Utility Hub (Logistics Tracking & WhatsApp Hotline)
                  </span>
                  <span className="text-[11px] text-neutral-500">Service & Help Direct</span>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label className="block text-[11px] font-semibold text-neutral-600 dark:text-neutral-400 mb-1">
                      Right Notice Text / Status
                    </label>
                    <input
                      type="text"
                      value={formData.rightText}
                      onChange={e => setFormData(prev => ({ ...prev, rightText: e.target.value }))}
                      placeholder="e.g. 📦 Track Order & Real-Time Logistics"
                      className="w-full px-3 py-2 text-xs rounded-xl bg-white dark:bg-neutral-900 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                    />
                  </div>

                  <div>
                    <label className="block text-[11px] font-semibold text-neutral-600 dark:text-neutral-400 mb-1">
                      Tracking Link URL
                    </label>
                    <input
                      type="text"
                      value={formData.rightLink || ''}
                      onChange={e => setFormData(prev => ({ ...prev, rightLink: e.target.value }))}
                      placeholder="e.g. /orders/track or /support"
                      className="w-full px-3 py-2 text-xs rounded-xl bg-white dark:bg-neutral-900 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                    />
                  </div>
                </div>

                {/* WhatsApp & Quick Utilities Switches */}
                <div className="pt-2 grid grid-cols-1 sm:grid-cols-3 gap-3">
                  <label className="flex items-center gap-2 p-2 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 cursor-pointer text-xs">
                    <input
                      type="checkbox"
                      checked={formData.showWhatsAppHotline}
                      onChange={e => setFormData(prev => ({ ...prev, showWhatsAppHotline: e.target.checked }))}
                      className="rounded accent-indigo-600"
                    />
                    <span className="text-neutral-700 dark:text-neutral-300 font-medium">WhatsApp Hotline</span>
                  </label>

                  <label className="flex items-center gap-2 p-2 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 cursor-pointer text-xs">
                    <input
                      type="checkbox"
                      checked={formData.showLogisticsTracker}
                      onChange={e => setFormData(prev => ({ ...prev, showLogisticsTracker: e.target.checked }))}
                      className="rounded accent-indigo-600"
                    />
                    <span className="text-neutral-700 dark:text-neutral-300 font-medium">Order Tracker Link</span>
                  </label>

                  <label className="flex items-center gap-2 p-2 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 cursor-pointer text-xs">
                    <input
                      type="checkbox"
                      checked={formData.showCurrencySelector}
                      onChange={e => setFormData(prev => ({ ...prev, showCurrencySelector: e.target.checked }))}
                      className="rounded accent-indigo-600"
                    />
                    <span className="text-neutral-700 dark:text-neutral-300 font-medium">Currency Dropdown</span>
                  </label>
                </div>

                {formData.showWhatsAppHotline && (
                  <div>
                    <label className="block text-[11px] font-semibold text-neutral-600 dark:text-neutral-400 mb-1">
                      WhatsApp Hotline Phone Number
                    </label>
                    <input
                      type="text"
                      value={formData.whatsAppNumber || ''}
                      onChange={e => setFormData(prev => ({ ...prev, whatsAppNumber: e.target.value }))}
                      placeholder="+60 14-224 4200"
                      className="w-full px-3 py-2 text-xs rounded-xl bg-white dark:bg-neutral-900 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                    />
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* Right Column: Theme & Visual Styling (1 Col) */}
          <div className="space-y-6">
            <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs space-y-4">
              <h3 className="text-sm font-bold text-neutral-900 dark:text-white flex items-center gap-2 pb-2 border-b border-neutral-100 dark:border-neutral-800">
                <Palette className="w-4 h-4 text-purple-500" />
                Color Theme & Presets
              </h3>

              <div className="space-y-2.5">
                {PRESET_THEMES.map(theme => {
                  const isSelected = formData.theme === theme.id;
                  return (
                    <div
                      key={theme.id}
                      onClick={() => setFormData(prev => ({ ...prev, theme: theme.id }))}
                      className={`p-3 rounded-xl border transition-all cursor-pointer flex items-center gap-3 ${
                        isSelected
                          ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/30 ring-1 ring-indigo-600'
                          : 'border-neutral-200 dark:border-neutral-800 hover:border-neutral-300 dark:hover:border-neutral-700 bg-neutral-50/60 dark:bg-neutral-950/40'
                      }`}
                    >
                      <div
                        className="w-7 h-7 rounded-lg shrink-0 border border-white/20 shadow-xs flex items-center justify-center font-bold text-xs"
                        style={{ backgroundColor: theme.previewBg, color: theme.previewText }}
                      >
                        {isSelected ? <Check className="w-4 h-4" /> : '•'}
                      </div>
                      <div className="flex-1 min-w-0">
                        <div className="text-xs font-bold text-neutral-900 dark:text-white flex items-center justify-between">
                          <span>{theme.name}</span>
                          {isSelected && (
                            <span className="text-[10px] px-1.5 py-0.2 rounded bg-indigo-500/20 text-indigo-600 dark:text-indigo-300 font-bold">
                              Active
                            </span>
                          )}
                        </div>
                        <div className="text-[10px] text-neutral-500 truncate">{theme.desc}</div>
                      </div>
                    </div>
                  );
                })}
              </div>

              {/* Custom Color Overrides if Custom selected */}
              {formData.theme === 'custom' && (
                <div className="p-3 rounded-xl bg-neutral-100 dark:bg-neutral-950 border border-neutral-200 dark:border-neutral-800 space-y-3 animate-in fade-in">
                  <div>
                    <label className="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                      Background Color Hex
                    </label>
                    <div className="flex items-center gap-2">
                      <input
                        type="color"
                        value={formData.customBgColor || '#0f172a'}
                        onChange={e => setFormData(prev => ({ ...prev, customBgColor: e.target.value }))}
                        className="w-8 h-8 rounded-lg cursor-pointer border border-neutral-300"
                      />
                      <input
                        type="text"
                        value={formData.customBgColor || '#0f172a'}
                        onChange={e => setFormData(prev => ({ ...prev, customBgColor: e.target.value }))}
                        className="flex-1 px-3 py-1.5 text-xs rounded-xl bg-white dark:bg-neutral-900 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white font-mono"
                      />
                    </div>
                  </div>

                  <div>
                    <label className="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                      Text & Link Color Hex
                    </label>
                    <div className="flex items-center gap-2">
                      <input
                        type="color"
                        value={formData.customTextColor || '#ffffff'}
                        onChange={e => setFormData(prev => ({ ...prev, customTextColor: e.target.value }))}
                        className="w-8 h-8 rounded-lg cursor-pointer border border-neutral-300"
                      />
                      <input
                        type="text"
                        value={formData.customTextColor || '#ffffff'}
                        onChange={e => setFormData(prev => ({ ...prev, customTextColor: e.target.value }))}
                        className="flex-1 px-3 py-1.5 text-xs rounded-xl bg-white dark:bg-neutral-900 border border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-white font-mono"
                      />
                    </div>
                  </div>
                </div>
              )}

              {/* Behavior Settings */}
              <div className="pt-3 border-t border-neutral-100 dark:border-neutral-800 space-y-2">
                <label className="flex items-center justify-between text-xs text-neutral-700 dark:text-neutral-300 cursor-pointer">
                  <span>Allow Buyer to Dismiss (Close button)</span>
                  <input
                    type="checkbox"
                    checked={formData.isDismissible}
                    onChange={e => setFormData(prev => ({ ...prev, isDismissible: e.target.checked }))}
                    className="rounded accent-indigo-600"
                  />
                </label>
              </div>
            </div>

            {/* Quick Live Card Preview */}
            <div className="p-4 rounded-2xl bg-neutral-950 border border-neutral-800 text-white space-y-2.5">
              <div className="text-[11px] font-bold text-neutral-400 uppercase tracking-wider flex items-center gap-1.5">
                <Eye className="w-3.5 h-3.5 text-indigo-400" />
                Real-Time Bar Preview:
              </div>

              <div
                className="py-2 px-3 rounded-xl border text-xs transition-all flex items-center justify-between gap-2 overflow-hidden"
                style={getBarThemeStyles()}
              >
                <div className="truncate text-[11px] font-medium">
                  {formData.leftBadge && (
                    <span className="mr-1.5 px-1.5 py-0.2 rounded bg-white/20 text-[9px] font-bold">
                      {formData.leftBadge}
                    </span>
                  )}
                  <span>{formData.centerText || 'Announcement Bar Notice'}</span>
                </div>
                {formData.centerHighlightText && (
                  <span className="shrink-0 px-2 py-0.5 rounded bg-amber-400/20 text-amber-300 text-[10px] font-mono font-bold">
                    {formData.centerHighlightText}
                  </span>
                )}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* TAB 2: HEADER NAVIGATION TEMPLATES & COMPONENT TOGGLES                     */}
      {/* ========================================================================= */}
      {activeTab === 'header_layout' && (
        <div className="space-y-6">
          {/* Header Layout Templates */}
          <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <h3 className="text-sm font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                  <Compass className="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                  Header Layout Architectural Template
                </h3>
                <p className="text-xs text-neutral-500 dark:text-neutral-400">
                  Select structural framework for logo alignment, navigation menu density, and utility clusters
                </p>
              </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
              {(Object.keys(TEMPLATE_PREVIEWS) as HeaderLayoutTemplate[]).map(key => {
                const tpl = TEMPLATE_PREVIEWS[key];
                const isSelected = headerSettings.template === key;
                return (
                  <div
                    key={key}
                    onClick={() => setHeaderSettings(prev => ({ ...prev, template: key }))}
                    className={`p-4 rounded-2xl border transition-all cursor-pointer flex flex-col justify-between ${
                      isSelected
                        ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/30 ring-2 ring-indigo-600 shadow-sm'
                        : 'border-neutral-200 dark:border-neutral-800 hover:border-neutral-300 dark:hover:border-neutral-700 bg-neutral-50/50 dark:bg-neutral-950/40'
                    }`}
                  >
                    <div>
                      <div className="flex items-center justify-between mb-2">
                        <span className="text-2xl">{tpl.icon}</span>
                        <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full ${
                          isSelected
                            ? 'bg-indigo-600 text-white'
                            : 'bg-neutral-200 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400'
                        }`}>
                          {tpl.tag}
                        </span>
                      </div>
                      <h4 className="text-xs font-bold text-neutral-900 dark:text-white">
                        {tpl.name}
                      </h4>
                      <p className="text-[11px] text-neutral-500 dark:text-neutral-400 mt-1 leading-relaxed">
                        {tpl.desc}
                      </p>
                    </div>

                    <div className="mt-4 pt-3 border-t border-neutral-200/60 dark:border-neutral-800/80 flex items-center justify-between text-[11px]">
                      <span className="font-semibold text-neutral-600 dark:text-neutral-400">
                        {isSelected ? 'Active Preset' : 'Click to Apply'}
                      </span>
                      {isSelected && <Check className="w-4 h-4 text-indigo-600 dark:text-indigo-400" />}
                    </div>
                  </div>
                );
              })}
            </div>
          </div>

          {/* Header Utilities & Popover Toggles */}
          <div className="p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs space-y-4">
            <h3 className="text-sm font-bold text-neutral-900 dark:text-white flex items-center gap-2 pb-2 border-b border-neutral-100 dark:border-neutral-800">
              <SlidersHorizontal className="w-4 h-4 text-indigo-500" />
              Header Utility Features & Component Controls
            </h3>

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              <div className="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-950/60 border border-neutral-200/80 dark:border-neutral-800/80 flex items-center justify-between">
                <div>
                  <div className="text-xs font-bold text-neutral-900 dark:text-white flex items-center gap-1.5">
                    <Search className="w-3.5 h-3.5 text-indigo-500" />
                    <span>Catalog Search Drawer</span>
                  </div>
                  <div className="text-[10px] text-neutral-500">Live search popover trigger</div>
                </div>
                <input
                  type="checkbox"
                  checked={headerSettings.showSearch}
                  onChange={e => setHeaderSettings(prev => ({ ...prev, showSearch: e.target.checked }))}
                  className="rounded accent-indigo-600"
                />
              </div>

              <div className="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-950/60 border border-neutral-200/80 dark:border-neutral-800/80 flex items-center justify-between">
                <div>
                  <div className="text-xs font-bold text-neutral-900 dark:text-white flex items-center gap-1.5">
                    <Globe className="w-3.5 h-3.5 text-indigo-500" />
                    <span>Currency Selector</span>
                  </div>
                  <div className="text-[10px] text-neutral-500">Multi-currency switch popup</div>
                </div>
                <input
                  type="checkbox"
                  checked={headerSettings.showCurrency}
                  onChange={e => setHeaderSettings(prev => ({ ...prev, showCurrency: e.target.checked }))}
                  className="rounded accent-indigo-600"
                />
              </div>

              <div className="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-950/60 border border-neutral-200/80 dark:border-neutral-800/80 flex items-center justify-between">
                <div>
                  <div className="text-xs font-bold text-neutral-900 dark:text-white flex items-center gap-1.5">
                    <User className="w-3.5 h-3.5 text-indigo-500" />
                    <span>Member Studio (User)</span>
                  </div>
                  <div className="text-[10px] text-neutral-500">Login/Register popup drawer</div>
                </div>
                <input
                  type="checkbox"
                  checked={headerSettings.showUserAccount}
                  onChange={e => setHeaderSettings(prev => ({ ...prev, showUserAccount: e.target.checked }))}
                  className="rounded accent-indigo-600"
                />
              </div>

              <div className="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-950/60 border border-neutral-200/80 dark:border-neutral-800/80 flex items-center justify-between">
                <div>
                  <div className="text-xs font-bold text-neutral-900 dark:text-white flex items-center gap-1.5">
                    <ShoppingBag className="w-3.5 h-3.5 text-indigo-500" />
                    <span>Shopping Bag Slideover</span>
                  </div>
                  <div className="text-[10px] text-neutral-500">Cart counter badge & slideout</div>
                </div>
                <input
                  type="checkbox"
                  checked={headerSettings.showCartBadge}
                  onChange={e => setHeaderSettings(prev => ({ ...prev, showCartBadge: e.target.checked }))}
                  className="rounded accent-indigo-600"
                />
              </div>
            </div>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* TAB 3: COMPLETE REAL-TIME STOREFRONT SIMULATOR                             */}
      {/* ========================================================================= */}
      {activeTab === 'preview' && (
        <div className="space-y-4">
          <div className="flex items-center justify-between p-3 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800">
            <div className="flex items-center gap-2">
              <span className="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
              <span className="text-xs font-bold text-neutral-900 dark:text-white">
                Live Storefront Customer Simulation Viewport
              </span>
            </div>

            <div className="flex items-center gap-2">
              <button
                onClick={() => setPreviewViewport('desktop')}
                className={`px-3 py-1.5 rounded-xl text-xs font-semibold flex items-center gap-1.5 cursor-pointer ${
                  previewViewport === 'desktop'
                    ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900 shadow-xs'
                    : 'text-neutral-500 hover:text-neutral-900 dark:hover:text-white'
                }`}
              >
                <Monitor className="w-3.5 h-3.5" />
                <span>Desktop View</span>
              </button>

              <button
                onClick={() => setPreviewViewport('mobile')}
                className={`px-3 py-1.5 rounded-xl text-xs font-semibold flex items-center gap-1.5 cursor-pointer ${
                  previewViewport === 'mobile'
                    ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900 shadow-xs'
                    : 'text-neutral-500 hover:text-neutral-900 dark:hover:text-white'
                }`}
              >
                <span>📱 Mobile View</span>
              </button>
            </div>
          </div>

          {/* Viewport Frame */}
          <div className={`mx-auto rounded-3xl bg-neutral-950 border border-neutral-800 shadow-2xl overflow-hidden transition-all duration-300 ${
            previewViewport === 'mobile' ? 'max-w-md' : 'w-full'
          }`}>
            {/* Top Announcement Bar Render */}
            {formData.enabled && (
              <div
                className="py-2.5 px-4 sm:px-6 text-xs transition-all border-b"
                style={getBarThemeStyles()}
              >
                <div className="max-w-7xl mx-auto flex items-center justify-between gap-4">
                  {/* Left area */}
                  {formData.leftEnabled && (
                    <div className="hidden md:flex items-center gap-2 text-[11px]">
                      {formData.leftBadge && (
                        <span className="px-1.5 py-0.2 rounded-full bg-white/20 text-[9px] font-bold tracking-wider">
                          {formData.leftBadge}
                        </span>
                      )}
                      <span className="font-medium opacity-90">{formData.leftText}</span>
                    </div>
                  )}

                  {/* Center promo */}
                  {formData.centerEnabled && (
                    <div className="flex-1 text-center truncate flex items-center justify-center gap-2">
                      <span className="font-semibold">{formData.centerText}</span>
                      {formData.centerHighlightText && (
                        <span className="px-2 py-0.5 rounded bg-amber-400 text-neutral-950 text-[10px] font-mono font-black tracking-wide shadow-xs">
                          {formData.centerHighlightText}
                        </span>
                      )}
                    </div>
                  )}

                  {/* Right area */}
                  {formData.rightEnabled && (
                    <div className="hidden lg:flex items-center gap-3 text-[11px]">
                      {formData.showWhatsAppHotline && formData.whatsAppNumber && (
                        <span className="font-mono flex items-center gap-1 opacity-90">
                          <Phone className="w-3 h-3 text-emerald-400" />
                          {formData.whatsAppNumber}
                        </span>
                      )}
                      {formData.showLogisticsTracker && (
                        <span className="flex items-center gap-1 hover:underline cursor-pointer opacity-90">
                          <Truck className="w-3 h-3 text-cyan-400" />
                          {formData.rightText}
                        </span>
                      )}
                    </div>
                  )}
                </div>
              </div>
            )}

            {/* Header Main Bar */}
            <header className="px-6 h-18 flex items-center justify-between border-b border-neutral-800 bg-neutral-950 text-white">
              <div className="flex items-center gap-8">
                <div className="flex items-center gap-2.5 cursor-pointer">
                  <div className="w-8 h-8 rounded-xl bg-indigo-600 flex items-center justify-center font-black text-sm text-white shadow-md shadow-indigo-600/30">
                    <Zap className="w-4 h-4" />
                  </div>
                  <span className="font-black tracking-widest text-base text-white">AURORA</span>
                </div>

                <nav className="hidden md:flex items-center gap-1">
                  {headerMenus.map(l1 => (
                    <span
                      key={l1.id}
                      className="px-3 py-2 text-xs font-semibold text-neutral-300 hover:text-white rounded-xl cursor-pointer"
                    >
                      {l1.title}
                    </span>
                  ))}
                </nav>
              </div>

              <div className="flex items-center gap-3">
                {headerSettings.showSearch && (
                  <div className="p-2 text-neutral-300 hover:text-white cursor-pointer">
                    <Search className="w-4 h-4" />
                  </div>
                )}
                {headerSettings.showCurrency && (
                  <div className="hidden sm:flex items-center gap-1 px-2.5 py-1 rounded-xl bg-neutral-900 border border-neutral-800 text-xs text-neutral-300">
                    <Globe className="w-3.5 h-3.5" />
                    <span>{headerSettings.selectedCurrency}</span>
                  </div>
                )}
                {headerSettings.showUserAccount && (
                  <div className="p-2 text-neutral-300 hover:text-white cursor-pointer">
                    <User className="w-4 h-4" />
                  </div>
                )}
                {headerSettings.showCartBadge && (
                  <div className="p-2 bg-indigo-600 text-white rounded-xl flex items-center gap-1 text-xs font-bold shadow-md shadow-indigo-600/30 cursor-pointer">
                    <ShoppingBag className="w-4 h-4" />
                    <span>2</span>
                  </div>
                )}
              </div>
            </header>

            {/* Mock Hero Content to represent storefront page context */}
            <div className="p-8 sm:p-12 text-center bg-gradient-to-b from-neutral-900 to-neutral-950 text-white space-y-4">
              <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/20 text-indigo-300 text-xs font-semibold border border-indigo-500/30">
                <Sparkles className="w-3.5 h-3.5" />
                Storefront Canvas Simulation
              </div>
              <h2 className="text-2xl sm:text-4xl font-extrabold tracking-tight">
                Experience Acoustic Purity & Precision
              </h2>
              <p className="text-xs sm:text-sm text-neutral-400 max-w-lg mx-auto">
                Explore hand-crafted audiophile acoustics, lossless planar drivers & bespoke accessories.
              </p>
              <div className="pt-2 flex justify-center gap-3">
                <span className="px-5 py-2.5 rounded-2xl bg-white text-neutral-950 font-bold text-xs">
                  Shop Collection
                </span>
                <span className="px-5 py-2.5 rounded-2xl bg-neutral-800 text-white font-bold text-xs border border-neutral-700">
                  Explore Stories
                </span>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
