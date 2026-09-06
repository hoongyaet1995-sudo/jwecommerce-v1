import React from 'react';
import { MarketplacePlatform } from '../../types';

interface MarketplaceLogoProps {
  platform: MarketplacePlatform | string;
  size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl' | number;
  variant?: 'icon' | 'tile' | 'badge' | 'avatar';
  showLabel?: boolean;
  className?: string;
}

// Pixel-perfect SVG vector representations matching official brand guidelines & uploaded user images

export const ShopeeIcon: React.FC<{ size?: number; className?: string }> = ({ size = 20, className = '' }) => (
  <svg
    width={size}
    height={size}
    viewBox="0 0 100 100"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
    className={className}
    role="img"
    aria-label="Shopee Logo"
  >
    {/* Shopee Bag Body (Orange #EE4D2D) */}
    <rect width="100" height="100" rx="20" fill="#EE4D2D" />
    
    {/* Handle */}
    <path
      d="M36 28 C36 18 64 18 64 28"
      stroke="#FFFFFF"
      strokeWidth="6"
      strokeLinecap="round"
      fill="none"
    />
    
    {/* Inner Bag Outline / Cutout */}
    <path
      d="M20 32 L80 32 L73 83 C72.5 86 69.5 88 66 88 L34 88 C30.5 88 27.5 86 27 83 Z"
      fill="#EE4D2D"
    />

    {/* Shopee Stylized 'S' */}
    <path
      d="M58.5 45 C58 41 54.5 39 49.5 39 C43.5 39 39.5 42.5 39.5 47.5 C39.5 56.5 61.5 54.5 61.5 67 C61.5 75.5 54 78.5 48.5 78.5 C41.5 78.5 36.5 75 36 69 L43 67.5 C43.5 71 46 72.8 49 72.8 C53 72.8 55.2 70.5 55.2 67 C55.2 59 33.2 60.5 33.2 47.5 C33.2 39 40 33.5 49.5 33.5 C57.5 33.5 63.5 37.5 64.5 44 Z"
      fill="#FFFFFF"
    />
  </svg>
);

export const LazadaIcon: React.FC<{ size?: number; className?: string }> = ({ size = 20, className = '' }) => (
  <svg
    width={size}
    height={size}
    viewBox="0 0 100 100"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
    className={className}
    role="img"
    aria-label="Lazada Logo"
  >
    <defs>
      {/* Background Gradient */}
      <linearGradient id="lazadaBg" x1="0" y1="0" x2="100" y2="100" gradientUnits="userSpaceOnUse">
        <stop offset="0%" stopColor="#0E1268" />
        <stop offset="100%" stopColor="#080948" />
      </linearGradient>

      {/* Heart Top Face Gradient */}
      <linearGradient id="lazadaTop" x1="25" y1="25" x2="75" y2="45" gradientUnits="userSpaceOnUse">
        <stop offset="0%" stopColor="#FF7A00" />
        <stop offset="50%" stopColor="#FF1970" />
        <stop offset="100%" stopColor="#FF0D5A" />
      </linearGradient>

      {/* Heart Left Face Gradient */}
      <linearGradient id="lazadaLeft" x1="25" y1="40" x2="50" y2="75" gradientUnits="userSpaceOnUse">
        <stop offset="0%" stopColor="#FF7300" />
        <stop offset="100%" stopColor="#FF2E00" />
      </linearGradient>

      {/* Heart Right Face Gradient */}
      <linearGradient id="lazadaRight" x1="50" y1="40" x2="75" y2="75" gradientUnits="userSpaceOnUse">
        <stop offset="0%" stopColor="#FF0D5A" />
        <stop offset="100%" stopColor="#E0004D" />
      </linearGradient>
    </defs>

    {/* App Tile Background (Navy/Blue) */}
    <rect width="100" height="100" rx="20" fill="url(#lazadaBg)" />

    {/* Lazada 3D Isometric Heart Box */}
    {/* Left Lobe / Face */}
    <path
      d="M50 48.5 L28 35.5 L28 55 L50 68 Z"
      fill="url(#lazadaLeft)"
    />

    {/* Right Lobe / Face */}
    <path
      d="M50 48.5 L72 35.5 L72 55 L50 68 Z"
      fill="url(#lazadaRight)"
    />

    {/* Top Heart Shape 3D Bevel */}
    <path
      d="M50 48.5 L28 35.5 C24 33 24 27 28.5 24 C33 21 39 23 43 27 L50 33 L57 27 C61 23 67 21 71.5 24 C76 27 76 33 72 35.5 Z"
      fill="url(#lazadaTop)"
    />

    {/* Subtle inner heart crease for depth */}
    <path
      d="M50 33 L50 67.5"
      stroke="rgba(255,255,255,0.25)"
      strokeWidth="1.5"
    />

    {/* Lazada Wordmark underneath */}
    <text
      x="50"
      y="84"
      textAnchor="middle"
      fill="#FFFFFF"
      fontFamily="system-ui, -apple-system, sans-serif"
      fontWeight="800"
      fontSize="13"
      letterSpacing="-0.3"
    >
      Lazada
    </text>
  </svg>
);

export const TikTokShopIcon: React.FC<{ size?: number; className?: string }> = ({ size = 20, className = '' }) => (
  <svg
    width={size}
    height={size}
    viewBox="0 0 100 100"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
    className={className}
    role="img"
    aria-label="TikTok Shop Logo"
  >
    {/* Dark rounded tile or bag base */}
    <rect width="100" height="100" rx="20" fill="#010101" />

    {/* Shopping Bag Handle */}
    <path
      d="M37 28 C37 18 63 18 63 28"
      stroke="#FFFFFF"
      strokeWidth="6"
      strokeLinecap="round"
      fill="none"
    />

    {/* Shopping Bag Body */}
    <path
      d="M22 32 L78 32 L72 82 C71.5 85 68.5 87 65 87 L35 87 C31.5 87 28.5 85 28 82 Z"
      fill="#010101"
      stroke="#262626"
      strokeWidth="2"
    />

    {/* TikTok Music Note - Cyan Shadow */}
    <path
      d="M55.5 39 C58 43 62 45 66 45.5 L66 52 C61.5 51.5 57.5 49 55.5 46.5 L55.5 62 C55.5 68.5 50.5 73.5 44 73.5 C37.5 73.5 32.5 68.5 32.5 62 C32.5 55.5 37.5 50.5 44 50.5 C45.5 50.5 47 50.8 48.5 51.5 L48.5 39 Z"
      fill="#00F2FE"
      transform="translate(-1.5, -1)"
    />

    {/* TikTok Music Note - Magenta Shadow */}
    <path
      d="M55.5 39 C58 43 62 45 66 45.5 L66 52 C61.5 51.5 57.5 49 55.5 46.5 L55.5 62 C55.5 68.5 50.5 73.5 44 73.5 C37.5 73.5 32.5 68.5 32.5 62 C32.5 55.5 37.5 50.5 44 50.5 C45.5 50.5 47 50.8 48.5 51.5 L48.5 39 Z"
      fill="#FE2C55"
      transform="translate(1.5, 1)"
    />

    {/* TikTok Music Note - Main White Core */}
    <path
      d="M55.5 39 C58 43 62 45 66 45.5 L66 52 C61.5 51.5 57.5 49 55.5 46.5 L55.5 62 C55.5 68.5 50.5 73.5 44 73.5 C37.5 73.5 32.5 68.5 32.5 62 C32.5 55.5 37.5 50.5 44 50.5 C45.5 50.5 47 50.8 48.5 51.5 L48.5 39 Z"
      fill="#FFFFFF"
    />
  </svg>
);

export const ShopifyIcon: React.FC<{ size?: number; className?: string }> = ({ size = 20, className = '' }) => (
  <svg
    width={size}
    height={size}
    viewBox="0 0 100 100"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
    className={className}
    role="img"
    aria-label="Shopify Logo"
  >
    {/* Rounded tile background */}
    <rect width="100" height="100" rx="20" fill="#FFFFFF" />
    <rect width="100" height="100" rx="20" stroke="#E5E7EB" strokeWidth="2" fill="none" />

    {/* Shopping Bag Handles */}
    <path
      d="M44 26 C44 19 56 19 56 26"
      stroke="#5E8E3E"
      strokeWidth="4"
      strokeLinecap="round"
      fill="none"
    />
    <path
      d="M48 24 C48 20 52 20 52 24"
      stroke="#95BF47"
      strokeWidth="3.5"
      strokeLinecap="round"
      fill="none"
    />

    {/* Green Shopping Bag Body */}
    <path
      d="M32 29 L68 25 L63 68 C62.5 71 60 73 57 73 L41 73 C38 73 35.5 71 35 68 Z"
      fill="#95BF47"
    />
    {/* Darker green side gusset fold */}
    <path
      d="M50 27 L68 25 L63 68 C62.5 71 60 73 57 73 L50 73 Z"
      fill="#7AB528"
    />

    {/* White 'S' Script */}
    <path
      d="M52 40 C52 38 50.5 37 48.5 37 C45.5 37 44 38.5 44 41 C44 45.5 53 45 53 51 C53 55.5 49.5 57 46.5 57 C43 57 41 55 41 52 L44 51.5 C44 53 45 54 46.5 54 C48.5 54 49.5 53 49.5 51 C49.5 46.5 40.5 47 40.5 41 C40.5 37 43.5 34 48.5 34 C52.5 34 55 36.5 55 40 Z"
      fill="#FFFFFF"
    />

    {/* Shopify Wordmark underneath */}
    <text
      x="50"
      y="87"
      textAnchor="middle"
      fill="#212326"
      fontFamily="system-ui, -apple-system, sans-serif"
      fontWeight="700"
      fontSize="12"
      letterSpacing="-0.2"
    >
      shopify
    </text>
  </svg>
);

// Fallback / Direct Web icon
export const DirectWebIcon: React.FC<{ size?: number; className?: string }> = ({ size = 20, className = '' }) => (
  <svg
    width={size}
    height={size}
    viewBox="0 0 100 100"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
    className={className}
    role="img"
    aria-label="Direct Webstore"
  >
    <rect width="100" height="100" rx="20" fill="#4F46E5" />
    <circle cx="50" cy="50" r="26" stroke="#FFFFFF" strokeWidth="6" fill="none" />
    <ellipse cx="50" cy="50" rx="14" ry="26" stroke="#FFFFFF" strokeWidth="5" fill="none" />
    <line x1="24" y1="50" x2="76" y2="50" stroke="#FFFFFF" strokeWidth="5" />
  </svg>
);

const SIZE_MAP = {
  xs: 14,
  sm: 18,
  md: 24,
  lg: 32,
  xl: 44,
};

export const getPlatformMeta = (platform: MarketplacePlatform | string) => {
  const norm = (platform || '').toLowerCase();
  if (norm.includes('shopee') || norm === 'sp' || norm === 'shp') {
    return {
      key: 'shopee' as MarketplacePlatform,
      name: 'Shopee',
      fullName: 'Shopee Mall (MY)',
      tag: 'SHP',
      color: '#EE4D2D',
      bgClass: 'bg-[#EE4D2D] text-white',
      badgeClass: 'bg-orange-50 dark:bg-orange-950/50 text-orange-700 dark:text-orange-300 border-orange-200 dark:border-orange-800',
      pillClass: 'bg-[#EE4D2D] text-white',
      Icon: ShopeeIcon
    };
  }
  if (norm.includes('lazada') || norm === 'laz' || norm === 'lz') {
    return {
      key: 'lazada' as MarketplacePlatform,
      name: 'Lazada',
      fullName: 'Lazada LazMall (MY)',
      tag: 'LAZ',
      color: '#0E1268',
      bgClass: 'bg-[#0E1268] text-white',
      badgeClass: 'bg-blue-50 dark:bg-blue-950/50 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800',
      pillClass: 'bg-[#0E1268] text-white',
      Icon: LazadaIcon
    };
  }
  if (norm.includes('tiktok') || norm === 'tts' || norm === 'tt') {
    return {
      key: 'tiktok' as MarketplacePlatform,
      name: 'TikTok Shop',
      fullName: 'TikTok Shop (MY)',
      tag: 'TTS',
      color: '#000000',
      bgClass: 'bg-black text-white dark:bg-neutral-900',
      badgeClass: 'bg-neutral-100 dark:bg-neutral-800 text-neutral-900 dark:text-white border-neutral-300 dark:border-neutral-700',
      pillClass: 'bg-black text-white dark:bg-neutral-800',
      Icon: TikTokShopIcon
    };
  }
  if (norm.includes('shopify') || norm === 'spf' || norm === 'spfy') {
    return {
      key: 'shopify' as MarketplacePlatform,
      name: 'Shopify',
      fullName: 'Shopify Global Store',
      tag: 'SPF',
      color: '#95BF47',
      bgClass: 'bg-emerald-600 text-white',
      badgeClass: 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
      pillClass: 'bg-emerald-600 text-white',
      Icon: ShopifyIcon
    };
  }

  return {
    key: 'direct' as MarketplacePlatform,
    name: 'Direct Web',
    fullName: 'JW Online Storefront',
    tag: 'DIR',
    color: '#4F46E5',
    bgClass: 'bg-indigo-600 text-white',
    badgeClass: 'bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800',
    pillClass: 'bg-indigo-600 text-white',
    Icon: DirectWebIcon
  };
};

export const MarketplaceLogo: React.FC<MarketplaceLogoProps> = ({
  platform,
  size = 'md',
  variant = 'icon',
  showLabel = false,
  className = '',
}) => {
  const meta = getPlatformMeta(platform);
  const IconComponent = meta.Icon;
  const numSize = typeof size === 'number' ? size : SIZE_MAP[size] || 24;

  if (variant === 'badge') {
    return (
      <span
        className={`inline-flex items-center gap-1.5 px-2 py-0.5 rounded-lg border text-xs font-bold transition-all ${meta.badgeClass} ${className}`}
      >
        <span className="shrink-0 drop-shadow-xs">
          <IconComponent size={Math.max(14, numSize - 4)} />
        </span>
        <span className="font-semibold">{meta.name}</span>
      </span>
    );
  }

  if (variant === 'avatar' || variant === 'tile') {
    return (
      <div
        style={{ width: numSize, height: numSize }}
        className={`rounded-xl shadow-xs shrink-0 flex items-center justify-center overflow-hidden transition-transform hover:scale-105 ${className}`}
        title={meta.fullName}
      >
        <IconComponent size={numSize} />
      </div>
    );
  }

  return (
    <div className={`inline-flex items-center gap-1.5 shrink-0 ${className}`}>
      <div className="shrink-0 drop-shadow-xs">
        <IconComponent size={numSize} />
      </div>
      {showLabel && (
        <span className="font-bold text-xs text-neutral-800 dark:text-neutral-200">
          {meta.name}
        </span>
      )}
    </div>
  );
};

