import { HeaderMenuL1 } from './index';

export type HeaderLayoutTemplate =
  | 'modern_minimalist'
  | 'centered_luxury'
  | 'floating_pill'
  | 'mega_catalog'
  | 'editorial_drawer';

export type AnnouncementBarTheme = 'solid_dark' | 'brand_indigo' | 'warm_amber' | 'emerald_deals' | 'crimson_sale' | 'custom';

export interface AnnouncementBarConfig {
  enabled: boolean;
  // Left area (e.g. Slogan / Welcome / Service commitment)
  leftText: string;
  leftLink?: string;
  leftBadge?: string;
  // Center area (e.g. Core promo, Free shipping banner, Discount code)
  centerText: string;
  centerHighlightText?: string;
  centerLink?: string;
  // Right area (e.g. Logistics status, Track order, WhatsApp hotline, Currency switcher)
  rightText: string;
  rightLink?: string;
  showCurrencySelector: boolean;
  showLogisticsTracker: boolean;
  showWhatsAppHotline: boolean;
  whatsAppNumber?: string;
  // Style / Visual configuration
  theme: AnnouncementBarTheme;
  customBgColor?: string;
  customTextColor?: string;
  enableMarquee: boolean; // Continuous scrolling for mobile/desktop
  isDismissible: boolean;
}

export interface StorefrontHeaderConfig {
  template: HeaderLayoutTemplate;
  colorTheme: 'dark' | 'light' | 'glass' | 'titanium';
  showSearch: boolean;
  showThemeToggle: boolean;
  showCurrency: boolean;
  showUserAccount: boolean;
  showCartBadge: boolean;
  selectedCurrency: string;
  cartCount: number;
  stickyHeader: boolean;
  announcementBar: AnnouncementBarConfig;
}

export const INITIAL_ANNOUNCEMENT_BAR_CONFIG: AnnouncementBarConfig = {
  enabled: true,
  leftText: '⚡ Official Flagship Store • 100% Authentic Guaranteed',
  leftBadge: 'OFFICIAL',
  leftLink: '/about',
  centerText: 'Free Expedited Courier Across Malaysia On Orders Over RM150',
  centerHighlightText: 'CODE: FREESHIP',
  centerLink: '/collections/clearance',
  rightText: '📦 Track Order & Real-Time Logistics',
  rightLink: '/orders/track',
  showCurrencySelector: true,
  showLogisticsTracker: true,
  showWhatsAppHotline: true,
  whatsAppNumber: '+60 14-224 4200',
  theme: 'solid_dark',
  customBgColor: '#0f172a',
  customTextColor: '#f8fafc',
  enableMarquee: false,
  isDismissible: true,
};

export const INITIAL_STOREFRONT_HEADER_CONFIG: StorefrontHeaderConfig = {
  template: 'modern_minimalist',
  colorTheme: 'dark',
  showSearch: true,
  showThemeToggle: true,
  showCurrency: true,
  showUserAccount: true,
  showCartBadge: true,
  selectedCurrency: 'MYR (RM)',
  cartCount: 2,
  stickyHeader: true,
  announcementBar: INITIAL_ANNOUNCEMENT_BAR_CONFIG,
};
