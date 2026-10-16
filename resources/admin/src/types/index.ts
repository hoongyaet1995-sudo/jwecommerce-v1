import type {PageStyle,BrandPalette} from '../cms/pageStyle';
import type {SectionTypography} from '../cms/inspectorCapabilities';
import type {
  NotificationDispatchChannel,
  NotificationDispatchSource,
  NotificationDispatchState,
  NotificationEventKey,
  NotificationPermission,
} from './notifications';

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
  productEnquiriesEnabled?: boolean;
  basicWmsUiEnabled?: boolean;
  basicWmsUiPreview?: boolean;
  user: {
    id?: string;
    name: string;
    email: string;
    avatar: string | null;
    isSuperAdmin?: boolean;
    reviewPermissions?: string[];
    notificationPermissions?: NotificationPermission[];
  };
  reviewPermissions?: string[];
  notificationPermissions?: NotificationPermission[];
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
  notificationTemplatesUrl?: string;
  orderNotificationsUrl?: string;
  shipmentsUrl?: string;
  shippingOrdersUrl?: string;
  easyParcelWebhookReceiptsUrl?: string;
  easyParcelWebhookCallbackUrl?: string;
  easyParcelWebhookConfigured?: boolean;
  easyParcelProviderUrl?: string;
  easyParcelProviderAuthorizeUrl?: string;
  easyParcelConnection?: { state: 'connected' | 'failed' | 'cancelled'; message: string } | null;
  easyParcelOrdersUrl?: string;
  couponsUrl?: string;
  commerceSettingsUrl?: string;
  taxSettingsUrl?: string;
  taxClassesUrl?: string;
  storefrontShellSettingsUrl?: string;
  catalogListingSettingsUrl?: string;
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
  badgeColor?: string;
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
  badgeColor?: string;
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
  badgeColor?: string;
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
  badgeColor?: string;
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
  subtitle?: string;
  badge?: string;
  coverImage?: string;
  type: 'manual' | 'dynamic';
  matchType?: 'all' | 'any';
  dynamicRules?: DynamicRule[];
  manualProductIds?: string[];
  status: 'active' | 'scheduled' | 'archived';
  productCount: number;
  createdAt: string;
}

export type VariantVisualType = 'color' | 'image' | 'none';
export type ProductOptionDisplayType = 'color_palette' | 'option_image' | 'pills' | 'dropdown';

export interface VariantVisual {
  visualType: VariantVisualType;
  colorCode?: string;
  optionImage?: string;
}

export interface ProductOption {
  id: string;
  name: string; // e.g. "Color", "Size", "Material"
  values: string[]; // e.g. ["Midnight Black", "Arctic White", "Sage Green"]
  displayType?: ProductOptionDisplayType;
  valueVisuals?: Record<string, VariantVisual>;
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
  stockVersion?: number;
  inventoryInitialized?: boolean;
  inventoryVerified?: boolean;
  inventoryEditable?: boolean;
  inventoryRevision?: string;
  inventoryAdjustmentUrl?: string;
  inventoryRepairRequired?: boolean;
  weightGrams?: number;
  packageLengthCm?: number;
  packageWidthCm?: number;
  packageHeightCm?: number;
  barcode?: string;
  image?: string;
  visualType?: VariantVisualType;
  colorCode?: string;
  optionImage?: string;
  enabled: boolean;
}

export interface ProductMedia {
  id?: string;
  path: string;
  alt: string;
  position?: number;
}

export interface ProductHighlightItem {
  id: string;
  text: string;
  badge?: string;
  icon?: string;
}

export interface ProductSpecItem {
  id: string;
  label: string;
  value: string;
  group?: string;
}

export interface ProductShippingPolicy {
  useStoreDefault: boolean;
  shippingBadgeText?: string;
  leadTimeText?: string;
  policyTitle?: string;
  policyContentHtml?: string;
  freeShippingThreshold?: number;
}

export interface ProductCustomAccordion {
  id: string;
  title: string;
  icon?: string;
  content: string;
  defaultOpen?: boolean;
}

export interface ProductSeedReview {
  id: string;
  author: string;
  rating: number;
  date?: string | null;
  title: string;
  comment: string;
  verifiedBuyer?: boolean;
  variantPurchased?: string;
  helpfulCount?: number;
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
  images: Array<ProductMedia | string>;
  status: 'active' | 'draft' | 'archived';
  purchaseMode?: 'buy' | 'quote' | 'display';
  priceDisplay?: 'exact' | 'from' | 'hide';
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
  pdpContent?: {
    highlights: ProductHighlightItem[];
    specifications: ProductSpecItem[];
    useStoreDefaultShipping: boolean;
    shippingPolicy?: Omit<ProductShippingPolicy, 'useStoreDefault'> | null;
    customAccordions: ProductCustomAccordion[];
    faqs?: { id: string; question: string; answer: string }[];
    ratingScore?: number | null;
    reviewCount: number;
    configuratorLabel?: string | null;
    story?: { label?: string; heading?: string; description?: string; image?: string; alt?: string; focus?: 'left'|'center'|'right'; focusY?: 'top'|'middle'|'bottom' } | null;
  };
  crossSellProductIds?: string[];
  reviews?: ProductSeedReview[];
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
  platforms?: string[];
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
export type PaymentProofStatus = 'not_required' | 'missing' | 'none' | 'uploaded' | 'verified' | 'rejected';

export interface PaymentProofAuditEvent {
  id: string;
  eventType: string;
  reason?: string | null;
  at?: string | null;
  actor?: string | null;
}

export interface PaymentProof {
  required?: boolean;
  paymentId?: string;
  status: PaymentProofStatus;
  uploadedAt?: string | null;
  reviewedAt?: string | null;
  originalFileName?: string | null;
  mimeType?: string | null;
  previewUrl?: string | null;
  downloadUrl?: string | null;
  canReview?: boolean;
  audit?: PaymentProofAuditEvent[];
}

export interface OrderShippingSource {
  checkoutMethod?: string | null;
  checkoutService?: string | null;
  checkoutRateType?: string | null;
  checkoutCalculationMode?: string | null;
  fulfillmentModule?: string | null;
  provider?: string | null;
  service?: string | null;
  quoteState?: string | null;
  providerReference?: string | null;
}

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

export interface OrderNotificationSummary {
  publicId: string;
  eventKey: NotificationEventKey;
  channel: NotificationDispatchChannel;
  state: NotificationDispatchState;
  source: NotificationDispatchSource;
  queuedAt: string | null;
}

export type ReturnRequestStatus = 'requested'|'approved'|'received'|'resolved_refund'|'resolved_replacement'|'rejected';
export interface ReturnRequestView {
  id:string; number:string; status:ReturnRequestStatus; resolution:'refund'|'replacement'; quantity:number; reason:string; details?:string|null;
  receivedQuantity?:number|null; condition?:'restockable'|'damaged'|null; warehouseNote?:string|null; rejectionReason?:string|null;
  replacementOrderId?:string|null; replacementOrderNumber?:string|null; refundedAmount?:number|null; externalRefundReference?:string|null; manualRefundNote?:string|null; createdAt:string;
  item:{id:string;title:string;variantTitle?:string|null;sku:string;image?:string|null;unitPrice:number};
  evidence:Array<{id:string;name:string;mimeType:string}>; events:Array<{id:string;type:string;actorType:string;note?:string|null;at:string}>;
}

export interface Order {
  id: string;
  orderNumber: string; // e.g. "ORD-9842"
  createdAt: string;
  customerId: string;
  customerName: string;
  customerEmail: string;
  customerPhone?: string;
  paymentGateway: string;
  paymentGatewayStatus: PaymentStatus;
  paymentProvider?: string | null;
  paymentReference?: string | null;
  paymentEnvironment?: string | null;
  paymentProof?: PaymentProof;
  fulfillmentStatus: FulfillmentStatus;
  shippingSource?: OrderShippingSource;
  currency: string;
  currencyExchangeRate?: number;
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
  quotation?: {url:string;manualFulfilment:boolean}|null;
  notificationContextFingerprint: string;
  notificationSummary: OrderNotificationSummary | null;
  returnRequest?: ReturnRequestView | null;
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
  | 'chapter_block'
  | 'chapter_navigation'
  | 'policy_content'
  | 'delivery_table'
  | 'payment_methods'
  | 'timeline_story'
  | 'stats_counter'
  | 'product_grid'
  | 'product_listing'
  | 'collection_tabs'
  | 'featured_deal'
  | 'pricing_table'
  | 'shop_navigation'
  | 'promotion_highlights'
  | 'media_gallery'
  | 'video_embed'
  | 'before_after'
  | 'instagram_feed'
  | 'feature_cards'
  | 'values_list'
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
  sourceType?: 'category' | 'collection' | 'custom';
  sourceId?: string;
  deliveryEstimate?: string;
  deliveryFee?: string;
  id: string;
  title: string;
  subtitle?: string;
  description?: string;
  caption?: string;
  thirdButtonText?: string;
  thirdButtonLink?: string;
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
  comparisonCells?: {deviceId:string;value:string}[];
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

export interface PageProductTab {
  id: string;
  label: string;
  sourceType: 'all' | 'category' | 'collection' | 'manual';
  sourceId?: string;
  productIds?: string[];
  productLimit?: number;
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

export type SectionMotionPreset = 'subtle-lift' | 'fade-in' | 'smooth-zoom' | 'dynamic-pop' | 'none';

export type SectionImageSlot = Pick<PageSection, 'mediaAlt' | 'imageRatio' | 'imagePosition' | 'imageFit' | 'imageWidth' | 'imageShape' | 'imageOverlay' | 'imageOverlayOpacity' | 'imageForeground' | 'imageForegroundColor' | 'imageForegroundOpacity'> & {target:string};

export interface PageSection {
  promotionSource?: 'automatic'|'manual';
  promotionLayout?: 'side'|'above';
  showOfferBadges?: boolean;
  imageSlots?: SectionImageSlot[];
  typography?: SectionTypography;
  surface?: 'light' | 'contrast';
  brandPalette?: BrandPalette;
  motionPreset?: SectionMotionPreset;
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
  imageRatio?: 'default' | 'auto' | '16:9' | '3:2' | '4:3' | '1:1' | '3:4';
  imagePosition?: 'top-left' | 'top-center' | 'top-right' | 'center-left' | 'center' | 'center-right' | 'bottom-left' | 'bottom-center' | 'bottom-right';
  imageFit?: 'cover' | 'contain';
  imageWidth?: '100' | '75' | '50';
  imageShape?: 'default' | 'rectangle' | 'rounded' | 'circle';
  imageOverlay?: boolean;
  imageOverlayOpacity?: 0 | 10 | 20 | 30 | 40 | 50 | 60 | 70 | 80 | 90 | 100;
  imageForeground?: boolean;
  imageForegroundColor?: string;
  imageForegroundOpacity?: 0 | 10 | 20 | 30 | 40 | 50 | 60 | 70 | 80 | 90 | 100;
  layoutPreset?: 'default' | 'editorial-hero' | 'editorial-story' | 'editorial-cover' | 'editorial-story-cards' | 'editorial-journal-rows' | 'story-with-steps' | 'flush-split' | 'producer-story' | 'tech-feature' | 'kids-discovery' | 'artisan-story';
  paymentLayout?: 'cards' | 'list';
  paymentNote?: string;
  deliverySource?: 'settings' | 'manual';
  deliveryNote?: string;
  deliveryDestinationLabel?: string;
  deliveryEstimateLabel?: string;
  deliveryFeeLabel?: string;
  faqPreset?: 'default' | 'minimal' | 'industrial-split';
  faqInitiallyOpen?: boolean;
  leadText?: string;
  policyPreset?: 'guidelines' | 'choices' | 'steps' | 'document' | 'directory' | 'process-flow' | 'timeline' | 'tabs' | 'columns';
  policyNote?: string;
  contactPreset?: 'enquiry' | 'location';
  mapEmbedUrl?: string;
  mapVisible?: boolean;
  contactAddressIcon?: string;
  chapterPreset?: 'story' | 'perspective' | 'principles' | 'process' | 'delivery' | 'actions' | 'policy';
  mediaContainerMode?: 'page' | 'full-bleed';
  containerMode?: 'page' | 'full-bleed';
  sectionBorder?: 'default' | 'none' | 'top' | 'bottom' | 'both';
  sectionBorderColor?: string;
  ctaPreset?: 'default' | 'image-banner' | 'editorial-band' | 'help-panel';
  ctaBannerHeight?: 'compact' | 'normal' | 'spacious';
  richTextPreset?: 'default' | 'manifesto';
  featureCardsPreset?: 'default' | 'values-list' | 'editorial-columns' | 'numbered-process' | 'service-highlights' | 'industrial-cards' | 'industrial-process';
  heroBannerPreset?: 'default' | 'editorial-gradient' | 'industrial-overlay';
  testimonialPreset?: 'default' | 'editorial-quote';
  marqueeSpeed?: number;
  marqueeDirection?: 'left' | 'right';
  marqueePauseOnHover?: boolean;
  galleryPreset?: 'photo-marquee' | 'default' | 'editorial-montage' | 'editorial-cards' | 'editorial-triptych' | 'portfolio-cards' | 'routine-steps';
  showSectionHeading?: boolean;
  valuesShowHeading?: boolean;
  valuesNumberColor?: string;
  valuesDividerColor?: string;
  valuesShowDividers?: boolean;
  valuesRowSpacing?: 'compact' | 'normal' | 'spacious';
  buttonStyle?: 'button' | 'text-link';
  mediaAlt?: string;
  mediaFocalPoint?: 'center' | 'top' | 'bottom' | 'left' | 'right';
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
  sourceType?: 'category' | 'collection' | 'manual';
  listingPreset?: 'default' | 'editorial-grid' | 'editorial-tabs' | 'fashion-lookbook' | 'beauty-grid' | 'pantry-grid' | 'pantry-tabs' | 'tech-grid' | 'tech-tabs' | 'kids-grid' | 'kids-tabs';
  productTabs?: PageProductTab[];
  sourceId?: string;
  productLimit?: number;
  autoSlide?: boolean;
  autoSlideInterval?: number;
  showNavArrows?: boolean;
  shopNavigationPreset?: 'cards' | 'kids-banners' | 'collection-banners' | 'fashion-banners' | 'market-hero' | 'market-offers' | 'image-overlay' | 'image-caption' | 'catalog-captions' | 'recipe-cards' | 'age-cards';
  bannerProportions?: '50:50' | '60:40' | '40:60';
  bannerHeight?: 'compact' | 'comfortable' | 'tall';
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
  newsletterPreset?: 'default' | 'inline-signup';
  newsletterCouponId?: string;
  subjectPlaceholder?: string;
  categoryPlaceholder?: string;
  messagePlaceholder?: string;
  inquiryCategories?: InquiryCategory[];
  comparisonPreset?: 'default' | 'device-table';
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
  sliderPreset?: 'default' | 'editorial-overlay' | 'fashion-editorial';
  sliderHeight?: 'panoramic' | 'compact' | 'normal' | 'spacious' | 'fullscreen';
}

export interface CmsPage {
  isHomepage?: boolean;
  id: string;
  title: string;
  slug: string; // e.g. 'brand-story', 'about-us', 'summer-lookbook'
  description?: string;
  status: 'published' | 'draft' | 'archived';
  template: 'standard' | 'fullwidth' | 'landing';
  shellDesign?: import('../cms/shellDesign').ShellDesign;
  headerPresentation?: import('../cms/headerPresentation').HeaderPresentation;
  containerWidth?: 'standard' | 'wide' | 'full' | 'site';
  style?: PageStyle;
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

export type StorefrontShellLayout =
  | 'atelier'
  | 'centered-luxury'
  | 'floating-pill'
  | 'mega-catalog'
  | 'editorial-drawer';

export type StorefrontShellTheme =
  | 'midnight-obsidian'
  | 'aurora-indigo'
  | 'emerald-logistics'
  | 'amber-flash'
  | 'crimson-campaign'
  | 'custom';

export interface StorefrontShellPalette {
  background: string | null;
  text: string | null;
  accent: string | null;
}

export interface StorefrontShellLeftZone {
  enabled: boolean;
  text: string;
  badge: string;
  linkUrl: string | null;
}

export interface StorefrontShellCenterZone {
  enabled: boolean;
  text: string;
  couponCode: string;
  linkUrl: string | null;
}

export interface StorefrontShellRightZone {
  enabled: boolean;
  helperText: string;
  trackingEnabled: boolean;
  trackingLabel: string;
  trackingUrl: string | null;
  whatsappEnabled: boolean;
  whatsappLabel: string;
  whatsappUrl: string | null;
}

export interface StorefrontShellSettings {
  contentWidth?: 'standard' | 'wide' | 'full';
  overlayLogos?: {light:string|null;dark:string|null};
  shellDesign?: import('../cms/shellDesign').ShellDesign;
  headerPresentation?: import('../cms/headerPresentation').HeaderPresentation;
  enabled: boolean;
  layout: StorefrontShellLayout;
  theme: StorefrontShellTheme;
  customPalette: StorefrontShellPalette;
  zones: {
    left: StorefrontShellLeftZone;
    center: StorefrontShellCenterZone;
    right: StorefrontShellRightZone;
  };
}

export const DEFAULT_STOREFRONT_SHELL_SETTINGS: StorefrontShellSettings = {
  enabled: false,
  layout: 'atelier',
  theme: 'midnight-obsidian',
  customPalette: {
    background: null,
    text: null,
    accent: null,
  },
  zones: {
    left: {
      enabled: false,
      text: '',
      badge: '',
      linkUrl: null,
    },
    center: {
      enabled: false,
      text: '',
      couponCode: '',
      linkUrl: null,
    },
    right: {
      enabled: false,
      helperText: '',
      trackingEnabled: false,
      trackingLabel: '',
      trackingUrl: null,
      whatsappEnabled: false,
      whatsappLabel: '',
      whatsappUrl: null,
    },
  },
};

export type PageDocument = Pick<
  CmsPage,
  | 'title'
  | 'slug'
  | 'description'
  | 'template'
  | 'headerPresentation'
  | 'shellDesign'
  | 'containerWidth'
  | 'style'
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
  | 'support-overview'
  | 'support-facebook'
  | 'support-tiktok-prototype'
  | 'support-tiktok'
  | 'support-whatsapp'
  | 'support-instagram'
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
  | 'website-design'
  | 'menu-header'
  | 'menu-footer'
  | 'pages-builder'
  | 'catalog'
  | 'catalog-categories'
  | 'catalog-collections'
  | 'catalog-products'
  | 'catalog-reviews'
  | 'commerce'
  | 'enquiries'
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
  | 'settings-tax-classes'
  | 'reports'
  | 'reports-sales'
  | 'reports-orders'
  | 'reports-products'
  | 'reports-customers'
  | 'reports-shipping'
  | 'reports-tax'
  | 'ai-auto-reply'
  | 'ai-personality'
  | 'ai-knowledge'
  | 'ai-guardrails'
  | 'ai-improvement'
  | 'integration'
  | 'integration-ai-connect'
  | 'promotions'
  | 'settings-ai-agent'
  | 'architecture-spec';

export * from './payment';
export * from './oscommerceSettings';
export * from './support';
export * from './reports';
export * from './aiAutoReply';
export * from './promotions';
export * from './notifications';
