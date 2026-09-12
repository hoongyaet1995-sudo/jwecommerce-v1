import { ReturnRequest } from './rma';
export * from './rma';

export type AdminRole = 'super_admin' | 'catalog_manager' | 'order_specialist' | 'developer';

export interface AdminUser {
  id: string;
  name: string;
  email: string;
  avatar: string;
  role: AdminRole;
  twoFactorEnabled: boolean;
}

// ---------------------------
// Phase 1 / Menu Management
// ---------------------------

export type MenuLinkType = 'category' | 'collection' | 'product' | 'page' | 'custom_url';

export interface HeaderMenuL3 {
  id: string;
  title: string;
  url: string;
  linkType: MenuLinkType;
  targetId?: string; // categoryId or collectionId
  badge?: string;
  isExternal?: boolean;
  isActive: boolean;
  sortOrder: number;
}

export interface HeaderMenuL2 {
  id: string;
  title: string;
  url: string;
  linkType: MenuLinkType;
  targetId?: string;
  icon?: string;
  description?: string;
  badge?: string;
  isActive: boolean;
  sortOrder: number;
  featuredImage?: string;
  items: HeaderMenuL3[]; // Level 3 leaf items
}

export interface HeaderMenuL1 {
  id: string;
  title: string;
  url: string;
  linkType: MenuLinkType;
  targetId?: string;
  icon?: string;
  badge?: string;
  isHighlighted?: boolean;
  displayStyle: 'dropdown' | 'megamenu' | 'simple_link';
  isActive: boolean;
  sortOrder: number;
  columns: HeaderMenuL2[]; // Level 2 Sub-categories or columns
}

export interface FooterMenuL2 {
  id: string;
  title: string;
  url: string;
  linkType: MenuLinkType;
  targetId?: string;
  badge?: string;
  isExternal?: boolean;
  isActive: boolean;
  sortOrder: number;
}

export interface FooterMenuL1 {
  id: string;
  sectionTitle: string;
  isActive: boolean;
  sortOrder: number;
  links: FooterMenuL2[]; // Level 2 Links
}

// ---------------------------
// Catalog Management
// ---------------------------

export interface Category {
  id: string;
  name: string;
  slug: string;
  parentId: string | null; // For hierarchical subcategories
  icon: string;
  image?: string;
  description?: string;
  seoTitle?: string;
  seoDescription?: string;
  seoKeywords?: string;
  status: 'active' | 'draft';
  sortOrder: number;
  productCount: number;
  createdAt: string;
}

export type CollectionRuleField = 'price' | 'category' | 'tag' | 'vendor' | 'inventory';
export type CollectionRuleOperator = 'equals' | 'not_equals' | 'greater_than' | 'less_than' | 'contains';

export interface DynamicRule {
  id: string;
  field: CollectionRuleField;
  operator: CollectionRuleOperator;
  value: string;
}

export interface Collection {
  id: string;
  title: string;
  slug: string;
  description?: string;
  coverImage?: string;
  type: 'manual' | 'dynamic';
  matchType?: 'all' | 'any';
  dynamicRules?: DynamicRule[];
  manualProductIds?: string[];
  status: 'active' | 'scheduled' | 'archived';
  productCount: number;
  createdAt: string;
}

export interface ProductOptionValueVisual {
  value: string;
  visualType?: 'color' | 'image' | 'none';
  colorCode?: string;
  optionImage?: string;
}

export interface ProductOption {
  id: string;
  name: string; // e.g. "Color", "Size", "Material"
  values: string[]; // e.g. ["Midnight Black", "Arctic White", "Sage Green"]
  displayType?: 'color_palette' | 'option_image' | 'pills' | 'dropdown';
  valueVisuals?: Record<string, { visualType?: 'color' | 'image' | 'none'; colorCode?: string; optionImage?: string }>;
}

export interface ProductVariant {
  id: string;
  sku: string;
  title: string; // e.g. "Midnight Black / M"
  options: Record<string, string>; // { "Color": "Midnight Black", "Size": "M" }
  price: number;
  compareAtPrice?: number;
  costPrice?: number;
  inventoryQuantity: number;
  weightGrams?: number;
  barcode?: string;
  image?: string;
  enabled: boolean;
  // 2-choose-1 Visual Representation (Color Code vs Option Image)
  visualType?: 'color' | 'image' | 'none';
  colorCode?: string; // Hex color code e.g. #1E293B
  optionImage?: string; // Swatch image thumbnail URL or base64
}

// ---------------------------
// Product PDP Extra Information Types
// ---------------------------

export interface ProductHighlightItem {
  id: string;
  text: string;
  icon?: string; // Lucide icon name or preset (CheckCircle2, Sparkles, Shield, Zap, etc.)
  badge?: string; // e.g. "Exclusive", "Patented"
}

export interface ProductSpecItem {
  id: string;
  group?: string; // Optional grouping e.g. "Dimensions", "Acoustics", "Materials"
  label: string; // e.g. "Frame Material", "Frequency Response"
  value: string; // e.g. "Japanese Beta-Titanium", "5Hz - 48kHz"
}

export interface ProductShippingPolicy {
  useStoreDefault: boolean;
  leadTimeText?: string; // e.g. "Order in the next 3 hrs 12 mins for dispatch today. Estimated arrival: 3–5 business days."
  shippingBadgeText?: string; // e.g. "Complimentary Express Shipping Included"
  policyTitle?: string; // e.g. "Shipping & 30-Day Return Guarantee"
  policyContentHtml?: string; // Detailed shipping/returns markdown/HTML
  freeShippingThreshold?: number; // e.g. 150
}

export interface ProductCustomAccordion {
  id: string;
  title: string; // e.g. "What's In The Box", "Care & Maintenance", "Size & Fit Guide", "FAQ"
  icon?: string;
  content: string; // Markdown or rich text
  defaultOpen?: boolean;
}

export type ReviewModerationStatus = 'pending' | 'published' | 'rejected';

export interface ProductReview {
  id: string;
  productId: string;
  productTitle: string;
  productImage: string;
  productSlug?: string;
  customerId?: string;
  customerName: string;
  customerEmail: string;
  customerAvatar?: string;
  orderId: string;
  orderNumber: string;
  variantPurchased: string; // e.g. "Gunmetal Titanium / Forest Green"
  rating: number; // 1 to 5
  title: string;
  comment: string;
  mediaUrls?: string[];
  verifiedBuyer: boolean; // Display-only system evidence linked to real completed order
  submittedAt: string;
  status: ReviewModerationStatus;
  publishedAt?: string;
  rejectedAt?: string;
  moderationNote?: string; // Internal admin note required on rejection
  moderatedBy?: string;
  helpfulCount?: number;
}

export interface ProductSeedReview {
  id: string;
  author: string;
  rating: number; // 1 to 5
  date: string;
  title: string;
  comment: string;
  verifiedBuyer: boolean;
  variantPurchased?: string; // e.g. "Gunmetal Titanium / Forest Green"
  helpfulCount?: number;
}

export interface Product {
  id: string;
  title: string;
  slug: string;
  brand: string;
  categoryId: string;
  taxClassId?: string;
  collectionIds: string[];
  descriptionHtml: string;
  summary: string;
  images: string[];
  status: 'active' | 'draft' | 'archived';
  hasVariants: boolean;
  basePrice: number;
  baseCompareAtPrice?: number;
  options: ProductOption[];
  variants: ProductVariant[];
  tags: string[];
  totalInventory: number;
  featured: boolean;
  seoTitle?: string;
  seoDescription?: string;
  createdAt: string;
  updatedAt: string;

  // --- Rich PDP Extra Information Fields ---
  highlights?: ProductHighlightItem[]; // Signature Highlights (bullet points with checkmarks/icons)
  specifications?: ProductSpecItem[]; // Technical Specifications Key-Value Matrix
  shippingPolicy?: ProductShippingPolicy; // Shipping & Guarantee overrides
  customAccordions?: ProductCustomAccordion[]; // Custom extensible sections (Care, Size Guide, Box Contents, FAQ)
  crossSellProductIds?: string[]; // Frequently Paired With / Modular Synergy recommendations
  crossSellDiscountPercent?: number; // e.g. 10% bundle discount
  reviews?: ProductSeedReview[]; // Verified Reviews list
  ratingScore?: number; // e.g. 4.9
  reviewCount?: number; // e.g. 142
  configuratorLabel?: string; // e.g. "CONFIGURATION" / "FINISH / OPTION" custom headline
}

// ---------------------------
// Customer Management
// ---------------------------

export interface CustomerAddress {
  id: string;
  isDefault: boolean;
  type: 'shipping' | 'billing';
  firstName: string;
  lastName: string;
  company?: string;
  address1: string;
  address2?: string;
  city: string;
  province: string;
  country: string;
  postalCode: string;
  phone: string;
}

export interface Customer {
  id: string;
  name: string;
  email: string;
  phone: string;
  avatar: string;
  totalOrders: number;
  totalSpent: number;
  currency: string;
  registeredAt: string;
  lastOrderAt?: string;
  status: 'active' | 'vip' | 'blocked';
  tags: string[];
  notes?: string;
  addresses: CustomerAddress[];
}

// ---------------------------
// Order Management
// ---------------------------

export type PaymentStatus = 'paid' | 'pending' | 'refunded' | 'partially_refunded' | 'failed';
export type PaymentProofStatus = 'none' | 'uploaded' | 'verified' | 'rejected';
export type FulfillmentStatus = 'unfulfilled' | 'processing' | 'shipped' | 'delivered' | 'cancelled';

export interface OrderItem {
  id: string;
  productId: string;
  variantId?: string;
  title: string;
  variantTitle?: string;
  sku: string;
  price: number;
  quantity: number;
  image: string;
  weightGrams?: number;
}

export interface OrderEvent {
  id: string;
  timestamp: string;
  type: 'created' | 'paid' | 'fulfillment_updated' | 'tracking_added' | 'refunded' | 'note_added' | 'payment_proof_uploaded' | 'payment_proof_viewed' | 'payment_proof_downloaded' | 'payment_verified' | 'payment_proof_rejected' | 'status_updated' | string;
  title: string;
  description: string;
  user?: string;
  actor?: string;
}

export * from './rma';

export interface Order {
  id: string;
  orderNumber: string; // e.g. "ORD-9842"
  createdAt: string;
  customerId: string;
  customerName: string;
  customerEmail: string;
  customerPhone?: string;
  paymentGateway: 'Stripe' | 'PayPal' | 'Apple Pay' | 'Credit Card' | 'bank_transfer' | string;
  paymentGatewayStatus: PaymentStatus;
  fulfillmentStatus: FulfillmentStatus;
  currency: string;
  items: OrderItem[];
  subtotal: number;
  taxAmount: number;
  shippingAmount: number;
  discountAmount: number;
  totalAmount: number;
  trackingNumber?: string;
  trackingCarrier?: string;
  shippingAddress: CustomerAddress;
  billingAddress: CustomerAddress;
  customerNote?: string;
  internalNote?: string;
  customStatusId?: string;
  customStatusName?: string;
  timeline: OrderEvent[];
  // Bank In / DuitNow payment proof review fields
  paymentMethodTitle?: string;
  paymentGatewayCode?: string;
  paymentProvider?: string;
  paymentReference?: string;
  paymentProofStatus?: 'none' | 'uploaded' | 'verified' | 'rejected';
  paymentProofUrl?: string;
  paymentProofFileName?: string;
  paymentProofUploadedAt?: string;
  paymentProofVerifiedAt?: string;
  paymentProofVerifiedBy?: string;
  paymentProofRejectedAt?: string;
  paymentProofRejectedBy?: string;
  paymentProofRejectionReason?: string;
  paymentProofDownloadName?: string;
  // Shipping & Fulfilment Source fields
  checkoutShippingRateLabel?: string;
  checkoutShippingSnapshot?: string;
  fulfilmentModule?: 'none' | 'easyparcel' | 'manual';
  easyParcelQuoteStatus?: 'none' | 'available' | 'booked';
  easyParcelCourier?: string;
  easyParcelService?: string;
  easyParcelBookingRef?: string;
  easyParcelAWB?: string;
  // Employee-facing Return / RMA request workflow
  returnRequest?: ReturnRequest;
}

// ---------------------------
// Page & Layout Builder (CMS Sections)
// ---------------------------

export type PageSectionType = 
  | 'hero_banner'
  | 'split_hero'
  | 'hero_slider'
  | 'announcement_bar'
  | 'rich_text'
  | 'media_text_split'
  | 'timeline_story'
  | 'stats_counter'
  | 'product_grid'
  | 'product_listing'
  | 'collection_tabs'
  | 'featured_deal'
  | 'pricing_table'
  | 'media_gallery'
  | 'video_embed'
  | 'before_after'
  | 'instagram_feed'
  | 'feature_cards'
  | 'icon_list'
  | 'comparison_matrix'
  | 'faq_accordion'
  | 'contact_form'
  | 'store_locator'
  | 'testimonials_slider'
  | 'logo_cloud'
  | 'cta_strip'
  | 'newsletter_signup'
  | 'custom_code_block';

export interface SectionItem {
  id: string;
  title: string;
  subtitle?: string;
  description?: string;
  icon?: string;
  imageUrl?: string;
  videoUrl?: string;
  linkUrl?: string;
  badge?: string;
  price?: number;
  compareAtPrice?: number;
  rating?: number; // e.g. 5
  author?: string;
  authorRole?: string;
  authorAvatar?: string;
  date?: string;
  category?: string;
  value?: string; // for stats e.g. "99.8%", "150k+"
  features?: string[]; // for pricing or matrix
  isHighlighted?: boolean;
  handle?: string; // for instagram/social
  metricLabel?: string;
  buttonText?: string;
  secondaryButtonText?: string;
  secondaryButtonLink?: string;
}

export interface SectionColorConfig {
  eyebrowColor?: string;       // Subtitle / Eyebrow Tag color (e.g. #818cf8, #38bdf8, #f59e0b)
  titleColor?: string;         // Primary Headline Title color (e.g. #ffffff, #111827)
  contentColor?: string;       // Editorial Description color (e.g. #d4d4d8, #4b5563)
  primaryBtnBg?: string;       // Primary Button background color (e.g. #4f46e5, #000000)
  primaryBtnText?: string;     // Primary Button text color (e.g. #ffffff)
  secondaryBtnBg?: string;     // Secondary Button background color (e.g. rgba(255,255,255,0.1), #f3f4f6)
  secondaryBtnText?: string;   // Secondary Button text color (e.g. #ffffff, #111827)
  customBgColor?: string;      // Custom Background Color (e.g. Hex or CSS Gradient)
}

export interface CollectionTabMapping {
  id: string;
  label: string;
  sourceType: 'category' | 'collection';
  sourceId: string;
  productLimit: 3 | 4 | 6 | 8;
}

export interface ContactInquiryCategory {
  id: string;
  label: string;
}

export type SectionMotionPreset = 'subtle-lift' | 'fade-in' | 'smooth-zoom' | 'dynamic-pop' | 'none';

export interface PageSection {
  id: string;
  type: PageSectionType;
  motionPreset?: SectionMotionPreset;
  title: string;
  subtitle?: string;
  content?: string; // Rich editorial paragraph / markdown
  quote?: string;
  authorName?: string;
  authorRole?: string;
  authorAvatar?: string;
  mediaUrl?: string; // Image or Video asset URL
  mediaType?: 'image' | 'video';
  mediaPosition?: 'left' | 'right';
  mediaAspectRatio?: '16:9' | '4:3' | '1:1' | 'auto';
  buttonText?: string;
  buttonLink?: string;
  secondaryButtonText?: string;
  secondaryButtonLink?: string;
  textAlignment?: 'left' | 'center' | 'right';
  backgroundColor?: string;
  customColors?: SectionColorConfig;
  textColor?: 'light' | 'dark';
  paddingY?: 'compact' | 'normal' | 'spacious';
  overlayOpacity?: number; // 0 to 100 for hero banner
  videoEmbedUrl?: string;
  isVisible: boolean;
  items?: SectionItem[];
  productIds?: string[];
  columnsCount?: 2 | 3 | 4 | 5 | 6;
  customCssClass?: string;
  customHtml?: string;
  sourceFileName?: string;

  // Specific Feature Props
  beforeImageUrl?: string;
  afterImageUrl?: string;
  beforeLabel?: string;
  afterLabel?: string;
  dealEndDate?: string;
  discountPercent?: number;
  dealBadge?: string;
  formEmail?: string;
  formSuccessMessage?: string;
  newsletterCouponId?: string;
  contactPhone?: string;
  contactAddress?: string;
  contactHours?: string;
  contactButtonText?: string;
  subjectPlaceholder?: string;
  categoryPlaceholder?: string;
  inquiryCategories?: ContactInquiryCategory[];
  messagePlaceholder?: string;
  tickerSpeed?: 'slow' | 'normal' | 'fast';
  tabCategories?: string[];
  tabs?: CollectionTabMapping[];
  comparisonDimensionName?: string;
  comparisonCompetitorName?: string;
  comparisonOurBrandName?: string;

  // Split Hero floating product card props
  cardBadge?: string;
  cardTitle?: string;
  cardSubtitle?: string;
  cardPrice?: string | number;
  cardExtra?: string;

  // Hero Slider props
  sliderInterval?: number; // seconds e.g. 5
  sliderAutoplay?: boolean;
  sliderHeight?: 'compact' | 'normal' | 'spacious' | 'fullscreen';

  // Product Listing Props (Auto Sliding Carousel & Catalog Filtering)
  sourceType?: 'category' | 'collection';
  sourceId?: string;
  productLimit?: number; // e.g. 3 | 4 | 6 | 8 | 12 | 16
  autoSlide?: boolean; // whether to automatically advance slides
  autoSlideInterval?: number; // seconds between auto-advance e.g. 3.5, 4, 5
  showNavArrows?: boolean; // whether to show prev/next buttons
}

export interface CmsPage {
  id: string;
  title: string;
  slug: string; // e.g. 'brand-story', 'about-us', 'summer-lookbook'
  description?: string;
  status: 'published' | 'draft' | 'archived';
  template: 'standard' | 'fullwidth' | 'landing';
  heroImage?: string;
  seoTitle?: string;
  seoDescription?: string;
  sections: PageSection[];
  updatedAt: string;
  createdAt: string;
  isHomepage?: boolean; // Set as active Storefront Home Page (index.php)
}

// ---------------------------
// Settings & Staff RBAC Types
// ---------------------------

export type StaffPermissionKey = 
  | 'configuration'
  | 'customers'
  | 'orders'
  | 'category'
  | 'products'
  | 'promotions'
  | 'Rewards'
  | 'Layout'
  | 'dashboard'
  | 'Sale Report'
  | 'Module Integration'
  | 'Tax'
  | 'Marketplace'
  | 'lhdn';

export interface StaffPermissionOption {
  key: StaffPermissionKey;
  label: string;
  category: 'core' | 'catalog' | 'sales' | 'marketing' | 'system';
  description: string;
}

export interface StaffUser {
  id: string;
  username: string;
  fullName?: string;
  email?: string;
  phone?: string;
  position: string; // e.g. "Airene sale", "Admin", "sales team", "ACCOUNT"
  password?: string;
  permissions: StaffPermissionKey[];
  isActive: boolean;
  lastLoginAt?: string;
  createdAt: string;
  avatarColor?: string;
  notes?: string;
}

export interface SocialMediaLinks {
  facebook: string;
  instagram: string;
  tiktok: string;
  youtube: string;
  linkedin: string;
  twitter: string; // X (Twitter)
  whatsapp: string;
  xiaohongshu: string; // Xiaohongshu
}

export interface ShopGeneralSettings {
  shopName: string;
  shopLogo: string;
  tagline: string;
  businessRegNo: string;
  sstNumber: string;
  email: string;
  phone: string;
  whatsapp: string;
  address: string;
  city: string;
  state: string;
  country: string;
  postcode: string;
  currency: string;
  timezone: string;
  maintenanceMode: boolean;
  orderPrefix: string;
  enablePoints: boolean;
  pointsRate: number; // e.g. RM 1 = 1 Point
  pointsRedeemRate: number; // 100 Points = RM 1
  socialLinks?: SocialMediaLinks;
}

// ---------------------------
// Marketplace Ecosystem Types (OMS & WMS)
// ---------------------------

export type MarketplacePlatform = 'tiktok' | 'shopee' | 'lazada' | 'shopify' | 'direct';
export type MarketplaceRegion = 'MY' | 'SG' | 'PH' | 'TH' | 'ID' | 'VN' | 'US' | 'GLOBAL';

export type ChannelOrderStatus = 
  | 'UNPAID'
  | 'AWAITING_COLLECTION'
  | 'READY_TO_SHIP'
  | 'IN_TRANSIT'
  | 'DELIVERED'
  | 'COMPLETED'
  | 'CANCELLED'
  | 'RETURNED'
  | (string & {});

export interface MarketplaceConnection {
  id: string;
  platform: MarketplacePlatform;
  shopName: string;
  shopNickname?: string;
  shopId: string;
  sellerId?: string;
  region: MarketplaceRegion;
  appKey: string;
  appSecret: string;
  partnerId?: string;
  accessToken: string;
  refreshToken: string;
  tokenExpiresAt: string; // ISO String
  status: 'connected' | 'expired' | 'pending_auth' | 'error' | 'disconnected';
  connectedAt?: string;
  disconnectedAt?: string;
  disconnectReason?: string;
  lastFetchedAt?: string;
  autoSyncStock: boolean;
  autoPullOrders: boolean;
  webhookRegistered?: boolean;
  webhookSecret?: string;
  lastSyncAt: string;
  orderCount?: number;
  productCount?: number;
  priceMarkupPercent?: number; // e.g. 0% or 5%
  stockBufferPercent?: number; // Safety reserve
  defaultCourier?: string;
  environment?: 'production' | 'sandbox';
  shopAvatar?: string;
  syncIntervalMinutes?: number;
  scopes?: string[];
  authorizedScopesSummary?: string[];
  apiRateLimit?: {
    limit: number;
    remaining: number;
    resetTime: string;
  };
}

export interface MarketplaceOrder extends Order {
  channel: MarketplacePlatform;
  channelStoreId: string;
  channelStoreName: string;
  channelOrderId: string; // e.g. Shopee Order SN, TikTok Order ID
  marketplaceBuyerUsername: string;
  channelStatus: ChannelOrderStatus; // e.g. READY_TO_SHIP, UNPAID, PROCESSED
  slaShipDeadline: string; // ISO String for late dispatch penalty countdown
  isSlaBreachRisk: boolean;
  airwayBillGenerated: boolean;
  airwayBillUrl?: string;
  pickupType: 'dropoff' | 'pickup';
  allocatedWarehouse: string;
  packedBy?: string;
  scannedItemsCount?: number;
}

export interface ChannelSkuMapping {
  isLinked: boolean;
  channelProductId?: string;
  channelSkuId?: string;
  channelItemId?: string;
  channelModelId?: string;
  channelSellerSku?: string;
  channelSku?: string;
  channelVariantId?: string;
  channelStock: number;
  price?: number;
  channelPrice?: number;
  priceMultiplier?: number;
  lastSyncedAt: string;
  syncStatus: 'synced' | 'pending' | 'error' | 'unlinked';
  errorMessage?: string;
}

export type MappingStatus = 'unmapped' | 'mapped' | 'conflict' | 'invalid';

export interface SuggestedJwMatch {
  productId: string;
  variantId: string;
  sku: string;
  productName: string;
  variantTitle: string;
  imageUrl?: string;
  stock: number;
  price: number;
  confidenceScore: number; // 0 - 100
  matchReason: string;
}

export interface MarketplaceStoreVariant {
  id: string;
  channelSkuId: string;
  channelSellerSku: string;
  title: string;
  specOptions?: Record<string, string>;
  price: number;
  currency: string;
  stock: number;
  imageUrl?: string;

  // Mapping to JW product variant
  mappingStatus: MappingStatus;
  mappedJwProductId?: string;
  mappedJwVariantId?: string;
  mappedJwSku?: string;
  mappedJwProductName?: string;
  mappedJwVariantTitle?: string;
  mappedJwVariantImage?: string;
  mappedJwStock?: number;
  mappedJwPrice?: number;
  mappedAt?: string;
  mappedBy?: string;

  // Conflict / Invalid detail
  conflictReason?: string;

  // Suggested Smart Match (if unmapped or suggested)
  suggestedMatch?: SuggestedJwMatch;
}

export interface MarketplaceStoreProduct {
  id: string;
  connectionId: string;
  platform: MarketplacePlatform;
  shopId: string;
  shopName: string;
  channelProductId: string;
  title: string;
  imageUrl: string;
  categoryName: string;
  status: 'active' | 'inactive' | 'delisted';
  updatedAt: string;
  variants: MarketplaceStoreVariant[];
}

export type StoreInventorySyncState = 
  | 'disabled'                  // Initial state after OAuth connection; connecting store alone never activates stock writes
  | 'awaiting_reconciliation'   // Requested activation, pending stock audit & order review
  | 'enabled'                   // Actively participating in real-time ATP broadcast
  | 'paused'                    // Merchant paused stock writes
  | 'auth_expired'              // OAuth token expired
  | 'failed';                   // Gateway rejected or configuration error

export interface StoreSkuAssociation {
  connectionId: string;
  storeName: string;
  storeNickname?: string;
  platform: 'shopee' | 'lazada'; // Release 1 constraint
  externalSku: string;           // e.g. SP_MOD_84920194, or 'UNMAPPED'
  externalItemId?: string;
  externalModelId?: string;
  channelWarehouseLocation?: string; // e.g. Cyberjaya Central Hub [SPX-MY-01]
  connectionState: 'connected' | 'expired' | 'error' | 'disconnected';
  syncState: StoreInventorySyncState;
  reconciledAt?: string;
  reconciledBy?: string;
  channelStockSnapshot?: number; // Reported by channel during catalog read, distinct from physical stock
  channelStockFetchedAt?: string | null;
  lastConfirmedPushedQuantity?: number | null; // Last pushed quantity confirmed by channel
  lastConfirmedPushedAt?: string;
  lastFailureReason?: string;
  lastSyncStatus?: 'confirmed' | 'queued' | 'processing' | 'failed' | 'uncertain';
}

export interface StoreInventoryActivation {
  connectionId: string;
  storeName: string;
  platform: 'shopee' | 'lazada';
  syncState: StoreInventorySyncState;
  reconciledAt?: string;
  reconciledBy?: string;
  channelWarehouseLocationId: string;
  channelWarehouseLocationName: string;
  mappedSkuCount: number;
  unmappedSkuCount: number;
  openOrdersCount: number;
  historicalOrdersExcludedCount: number;
  safetyBufferPercent: number;
  lastSyncAt?: string;
  lastFailureMessage?: string;
}

export interface InventoryOrderException {
  id: string;
  orderNumber: string;
  platform: 'shopee' | 'lazada';
  storeName: string;
  placedAt: string;
  type: 'unmapped_sku' | 'insufficient_stock' | 'predates_activation';
  severity: 'critical' | 'warning' | 'info';
  channelSku: string;
  channelItemTitle: string;
  requestedQuantity: number;
  availableAtp: number | null;
  status: 'open' | 'resolved' | 'ignored';
  explanation: string;
  isHistoricalExcluded?: boolean;
}

export interface StoreBroadcastResult {
  connectionId: string;
  storeName: string;
  platform: 'shopee' | 'lazada';
  status: 'queued' | 'processing' | 'confirmed' | 'failed' | 'uncertain';
  pushedQuantity: number;
  latencyMs?: number;
  confirmedAt?: string;
  errorMessage?: string;
  httpStatusCode?: number;
  isRetryable: boolean;
}

// Dual Inventory Mode
export type InventoryOperatingMode = 'shared_pool' | 'independent_warehouses' | 'unconfigured';

// Reconcile Inventory Server Contract Types
export interface ReconcileInventoryParams {
  masterSku: string;
  warehouseId: string;
  newSafetyStock: number;
  reason: string;
  expectedVersion?: number | string;
  operatorName: string;
}

export interface ReconcileInventoryReceipt {
  success: boolean;
  receiptId?: string;
  timestamp?: string;
  masterSku?: string;
  productName?: string;
  warehouseId?: string;
  warehouseCode?: string;
  warehouseName?: string;
  physicalStock?: number;
  physicalStockStatus?: 'verified' | 'pending_reconciliation' | 'unknown';
  previousReserved?: number;
  newReserved?: number;
  previousSafety?: number;
  newSafety?: number;
  previousAtp?: number;
  newAtp?: number;
  affectedStores?: {
    connectionId: string;
    storeName: string;
    platform: 'shopee' | 'lazada' | 'webstore';
    boundWarehouseAtp: number;
    syncState: string;
  }[];
  operator?: string;
  status?: 'RECONCILED_IN_WMS_LEDGER' | 'BASIC_SAFETY_CONFIGURED' | 'VERSION_CONFLICT' | 'BLOCKED' | 'FAILED';
  error?: string;
  versionConflict?: boolean;
  notes?: string;
}

export interface ModeSwitchServerCheck {
  canSwitch: boolean;
  blockingOrders: {
    orderNumber: string;
    platform: string;
    buyer: string;
    sku: string;
    reason: string;
  }[];
  inTransitTransfers: {
    id: string;
    sku: string;
    productName: string;
    quantity: number;
    sourceWarehouseName: string;
    destinationWarehouseName: string;
  }[];
}

// Entity Warehouse Facility
export interface EntityWarehouse {
  dataClassification?: string;
  id: string; // e.g. 'wh-cyberjaya'
  code: string; // e.g. 'WH-CYB'
  name: string; // e.g. 'Cyberjaya Central Hub'
  address: string;
  state: string;
  isDefaultMaster: boolean;
  isActive: boolean;
  status?: 'active' | 'inactive' | 'maintenance';
  contactPerson: string;
  contactPhone: string;
  supportedCarriers: string[];
  notes?: string;
}

// Per-Warehouse Stock Breakdown (used in Independent Mode)
export interface WarehouseStockBalance {
  poolKind?: 'shared' | 'warehouse';
  safetyStockKnown?: boolean;
  basicWms?: boolean;
  basicAvailable?: number | null;
  basicOwnedReserved?: number | null;
  balanceId?: number;
  poolId?: number;
  warehouseId: string;
  warehouseCode: string;
  warehouseName: string;
  binLocation?: string; // e.g. 'Zone A - Shelf 01'
  locationBin?: string; // alias for binLocation
  physicalStock: number;
  physicalStockStatus: 'verified' | 'pending_reconciliation' | 'unknown';
  reservedStock: number;
  safetyStock: number;
  availableAtp: number; // physical - reserved - safety
  lastAuditAt?: string;
  lastAuditedBy?: string;
  lastAuditBy?: string;
  version?: number; // optimistic concurrency version
}

// Store & Platform Warehouse Routing Binding
export interface StoreFulfillmentBinding {
  dataClassification?: string;
  id: string;
  storeIdentifier: string; // 'webstore' or connectionId e.g. 'conn-shopee-01'
  platform: 'webstore' | 'shopee' | 'lazada';
  storeName: string;
  storeNickname?: string;
  boundWarehouseId: string; // EntityWarehouse id (e.g. 'wh-cyberjaya')
  boundWarehouseName: string;
  boundWarehouseCode?: string;
  channelWarehouseLocationId?: string; // Platform location id e.g. 'SPX-MY-CENTRAL-01'
  channelWarehouseLocationName?: string; // Platform location name
  platformLocationId?: string;
  platformLocationName?: string;
  priorityOrder: number;
  fulfillmentPriority?: number;
  isSharedPoolActive: boolean;
  lastSyncAt?: string;
  notes?: string;
}

// Inter-Warehouse Transfer (Stock Relocation)
export interface InterWarehouseTransfer {
  id: string; // e.g. 'TRF-2026-001'
  sourceWarehouseId: string;
  sourceWarehouseName: string;
  destinationWarehouseId: string;
  destinationWarehouseName: string;
  masterSku: string;
  productName: string;
  variantName: string;
  quantity: number;
  status: 'draft' | 'in_transit' | 'completed' | 'cancelled';
  requestedBy: string;
  approvedBy?: string;
  receivedBy?: string;
  createdAt: string;
  dispatchedAt?: string;
  completedAt?: string;
  notes?: string;
  trackingNumber?: string;
}

// Mode Switch Audit Ledger
export interface InventoryModeSwitchAudit {
  id: string;
  timestamp: string;
  fromMode: InventoryOperatingMode;
  toMode: InventoryOperatingMode;
  staffName: string;
  reason: string;
  affectedSkuCount: number;
  summaryNote: string;
}

export interface MarketplaceInventoryItem {
  legacyJwStock?: number | null;
  jwProductId?: number;
  dataClassification?: string;
  id: string;
  masterSku: string;
  barcode: string;
  productName: string;
  variantName: string;
  imageUrl: string;
  warehouseLocation: string; // Primary bin location (or shared summary)
  warehouseName?: string;
  
  // Stock separation (Master / Aggregate or Shared Pool)
  physicalStockStatus?: 'verified' | 'pending_reconciliation' | 'unknown';
  totalPhysicalStock: number | null; // null if unknown legacy stock
  reservedStock: number;
  safetyStock: number;
  availableStock: number | null; // ATP: Physical - Reserved - Safety (null if physical is unknown)
  reorderPoint: number;
  costPrice: number;
  sellingPrice: number;

  // Per-Warehouse breakdown in Independent Mode
  warehouseBalances?: WarehouseStockBalance[];

  // Multi-store associations per connected store
  storeAssociations?: StoreSkuAssociation[];

  channelMappings: {
    tiktok?: ChannelSkuMapping;
    shopee?: ChannelSkuMapping;
    lazada?: ChannelSkuMapping;
    shopify?: ChannelSkuMapping;
    [key: string]: ChannelSkuMapping | undefined;
  };
  autoSyncEnabled: boolean;
  lastBroadcastAt?: string;
  historyLog: {
    id: string;
    timestamp: string;
    action: string;
    change: number;
    resultingStock: number | null;
    user: string;
    userRole?: string;
    reason: string;
    documentRef?: string;
  }[];
}

export interface MarketplaceSyncLog {
  id: string;
  timestamp: string;
  platform: MarketplacePlatform;
  shopName: string;
  eventType: 'order_created' | 'order_status_update' | 'inventory_sync' | 'price_push' | 'token_refresh' | 'awb_generated' | 'webhook_ping' | 'product_publish' | 'store_disconnect' | 'store_connect';
  status: 'success' | 'failed' | 'processing' | 'warning';
  referenceId: string;
  message: string;
  payloadJson: string;
  latencyMs: number;
}

export interface ImportProductOptions {
  createDraftStatus?: 'draft' | 'active'; // Default 'draft'
  zeroPhysicalStock?: boolean; // Default true (zero warehouse stock)
  skuOverrides?: Record<string, string>; // variantId -> desired master SKU
  linkExistingVariantIds?: Record<string, { productId: string; variantId: string }>; // variantId -> link to existing instead of creating new
  targetProductId?: string; // If explicitly appending variant to an existing JW master product
}

export interface PublishListingOptions {
  priceMultiplier?: number;
  categoryName?: string;
  marketplaceCategoryId?: string;
  brand?: string;
  warrantyType?: string;
  warrantyPeriod?: string;
  packageWeightKg?: number;
  packageLengthCm?: number;
  packageWidthCm?: number;
  packageHeightCm?: number;
  condition?: 'NEW' | 'REFURBISHED';
  initialStockMode?: 'warehouse_stock' | 'zero_stock';
  enableOngoingSync?: boolean;
}

export type PublishAsyncStatus = 'idle' | 'queued' | 'processing' | 'success' | 'rejected' | 'uncertain';

// ---------------------------
// App Navigation State
// ---------------------------

export type NavSection = 
  | 'dashboard'
  | 'support'
  | 'shipping-malaysia'
  | 'marketplace'
  | 'marketplace-auth'
  | 'marketplace-mapping'
  | 'marketplace-oms'
  | 'marketplace-inventory'
  | 'marketplace-sync'
  | 'storefront'
  | 'storefront-header-announcement'
  | 'menu-header'
  | 'menu-footer'
  | 'pages-builder'
  | 'catalog'
  | 'catalog-categories'
  | 'catalog-collections'
  | 'catalog-products'
  | 'catalog-reviews'
  | 'commerce'
  | 'customers'
  | 'orders'
  | 'settings'
  | 'settings-users'
  | 'settings-shop'
  | 'settings-payment'
  | 'settings-points'
  | 'settings-invoice'
  | 'settings-email'
  | 'settings-social'
  | 'settings-general'
  | 'settings-courier'
  | 'settings-agent-group'
  | 'settings-orders-status'
  | 'settings-countries'
  | 'settings-zones'
  | 'settings-currencies'
  | 'settings-order-total'
  | 'settings-tax-classes'
  | 'settings-tax-zones'
  | 'reports'
  | 'reports-sales'
  | 'reports-orders'
  | 'reports-products'
  | 'reports-customers'
  | 'reports-shipping'
  | 'reports-tax'
  | 'ai-auto-reply'
  | 'promotions'
  | 'settings-ai-agent'
  | 'architecture-spec';

export * from './payment';
export * from './oscommerceSettings';
export * from './support';
export * from './reports';
export * from './aiAutoReply';
export * from './promotions';
export * from './storefrontHeader';
export * from './notifications';
