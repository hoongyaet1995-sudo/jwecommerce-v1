export type AdminRole = 'super_admin' | 'catalog_manager' | 'order_specialist' | 'developer';

export interface AdminUser {
  id: string;
  name: string;
  email: string;
  avatar?: string;
  role: AdminRole;
  twoFactorEnabled: boolean;
}

export interface LaravelAdminBootstrap {
  user: {
    id?: string;
    name: string;
    email: string;
    avatar: string | null;
    isSuperAdmin?: boolean;
    reviewPermissions?: string[];
  };
  reviewPermissions?: string[];
  csrfToken: string;
  logoutUrl: string;
  dashboardUrl?: string;
  customersUrl?: string;
  reviewsUrl?: string;
  reviewBulkModerateUrl?: string;
  staffReviewPermissionsUrl?: string;
  pagesUrl?: string;
  mediaAssetsUrl?: string;
  catalogUrl?: string;
  menusUrl?: string;
  ordersUrl?: string;
  couponsUrl?: string;
  commerceSettingsUrl?: string;
}

declare global {
  interface Window {
    __JW_ADMIN__?: LaravelAdminBootstrap;
  }
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
  featuredTitle?: string;
  featuredDescription?: string;
  featuredPrice?: string;
  featuredImageUrl?: string;
  featuredCtaLabel?: string;
  featuredCtaUrl?: string;
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

export interface ProductOption {
  id: string;
  name: string; // e.g. "Color", "Size", "Material"
  values: string[]; // e.g. ["Midnight Black", "Arctic White", "Sage Green"]
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
}

export type ReviewModerationStatus = 'pending' | 'published' | 'rejected';
export type ReviewModerationAction = 'publish' | 'reject';

export interface AdminProductReviewPhoto {
  id: string;
  url: string;
  mimeType: string;
}

export interface AdminProductReview {
  id: string;
  productId: string;
  productTitle: string;
  productThumbnail?: string | null;
  productHandle?: string | null;
  customerId: string;
  buyerName: string;
  customerEmail: string;
  orderNumber: string;
  variantTitle?: string | null;
  rating: number;
  title: string;
  comment: string;
  status: ReviewModerationStatus;
  moderationNote?: string | null;
  moderatedBy?: string | null;
  submittedAt: string;
  publishedAt?: string | null;
  photos: AdminProductReviewPhoto[];
}

export interface AdminProductReviewAuditEvent {
  id: string;
  eventType: string;
  fromStatus: ReviewModerationStatus | null;
  toStatus: ReviewModerationStatus | null;
  batchId: string | null;
  metadata: Record<string, unknown>;
  actorName: string | null;
  occurredAt: string | null;
}

export interface ReviewModerationResult {
  id: string;
  status: ReviewModerationStatus;
}

export interface ReviewBulkModerationItemResult {
  reviewId: string;
  ok: boolean;
  status?: ReviewModerationStatus;
  reason?: string;
}

export interface ReviewBulkModerationResult {
  results: ReviewBulkModerationItemResult[];
}

export interface Product {
  id: string;
  title: string;
  slug: string;
  brand: string;
  categoryId: string;
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
  type: 'created' | 'paid' | 'fulfillment_updated' | 'tracking_added' | 'refunded' | 'note_added';
  title: string;
  description: string;
  user?: string;
}

export interface Order {
  id: string;
  orderNumber: string; // e.g. "ORD-9842"
  createdAt: string;
  customerId: string;
  customerName: string;
  customerEmail: string;
  customerPhone?: string;
  paymentGateway: 'Stripe' | 'PayPal' | 'Apple Pay' | 'Credit Card';
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
  | 'newsletter_signup';

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
  eyebrowColor?: string;
  titleColor?: string;
  contentColor?: string;
  primaryBtnBg?: string;
  primaryBtnText?: string;
  secondaryBtnBg?: string;
  secondaryBtnText?: string;
  customBgColor?: string;
}

export interface PageCollectionTab {
  id: string;
  label: string;
  sourceType: 'category' | 'collection';
  sourceId: string;
  productLimit: 3 | 4 | 6 | 8;
}

export interface InquiryCategory {
  id: string;
  label: string;
}

export interface PageSection {
  id: string;
  type: PageSectionType;
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
  contactPhone?: string;
  contactAddress?: string;
  contactHours?: string;
  contactButtonText?: string;
  tickerSpeed?: 'slow' | 'normal' | 'fast';
  tabCategories?: string[];
  tabs?: PageCollectionTab[];
  newsletterCouponId?: string;
  subjectPlaceholder?: string;
  categoryPlaceholder?: string;
  messagePlaceholder?: string;
  inquiryCategories?: InquiryCategory[];
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
}

export interface CmsPage {
  id: string;
  title: string;
  slug: string; // e.g. 'brand-story', 'about-us', 'summer-lookbook'
  description?: string;
  status: 'published' | 'draft' | 'archived';
  template: 'standard' | 'fullwidth' | 'landing';
  heroImage?: string | null;
  seoTitle?: string;
  seoDescription?: string;
  sections: PageSection[];
  lockVersion?: number;
  schemaVersion?: number;
  contentHash?: string;
  hasPublishedSnapshot?: boolean;
  hasUnpublishedChanges?: boolean;
  updatedAt: string;
  createdAt: string;
}

export type PageDocument = Pick<
  CmsPage,
  | 'title'
  | 'slug'
  | 'description'
  | 'template'
  | 'heroImage'
  | 'seoTitle'
  | 'seoDescription'
  | 'sections'
>;

export interface PageDraftPayload {
  pageId: string;
  status: CmsPage['status'];
  schemaVersion: number;
  lockVersion: number;
  contentHash: string;
  hasPublishedSnapshot: boolean;
  hasUnpublishedChanges: boolean;
  document: PageDocument;
  updatedAt: string;
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
  shopId: string;
  sellerId?: string;
  region: MarketplaceRegion;
  appKey: string;
  appSecret: string;
  partnerId?: string;
  accessToken: string;
  refreshToken: string;
  tokenExpiresAt: string; // ISO String
  status: 'connected' | 'expired' | 'pending_auth' | 'error';
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

export interface MarketplaceInventoryItem {
  id: string;
  masterSku: string;
  barcode: string;
  productName: string;
  variantName: string;
  imageUrl: string;
  warehouseLocation: string; // e.g. Zone A - Rack 03 - Bin B2
  totalPhysicalStock: number;
  reservedStock: number;
  safetyStock: number;
  availableStock: number; // ATP: Physical - Reserved - Safety
  reorderPoint: number;
  costPrice: number;
  sellingPrice: number;
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
    resultingStock: number;
    user: string;
    reason: string;
  }[];
}

export interface MarketplaceSyncLog {
  id: string;
  timestamp: string;
  platform: MarketplacePlatform;
  shopName: string;
  eventType: 'order_created' | 'order_status_update' | 'inventory_sync' | 'price_push' | 'token_refresh' | 'awb_generated' | 'webhook_ping';
  status: 'success' | 'failed' | 'processing' | 'warning';
  referenceId: string;
  message: string;
  payloadJson: string;
  latencyMs: number;
}

// ---------------------------
// App Navigation State
// ---------------------------

export type NavSection = 
  | 'dashboard'
  | 'support'
  | 'shipping-malaysia'
  | 'marketplace'
  | 'marketplace-auth'
  | 'marketplace-oms'
  | 'marketplace-inventory'
  | 'marketplace-sync'
  | 'storefront'
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
