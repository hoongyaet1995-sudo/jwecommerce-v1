import { ReturnRequest } from './rma';
export type AdminRole = 'super_admin' | 'catalog_manager' | 'order_specialist' | 'developer';
export interface AdminUser {
  id: string;
  name: string;
  email: string;
  avatar: string;
  role: AdminRole;
  twoFactorEnabled: boolean;
}
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
  type: 'created' | 'paid' | 'fulfillment_updated' | 'tracking_added' | 'refunded' | 'note_added' | 'payment_proof_uploaded' | 'payment_proof_viewed' | 'payment_proof_downloaded' | 'payment_verified' | 'payment_proof_rejected' | 'status_updated' | string;
  title: string;
  description: string;
  user?: string;
  actor?: string;
}
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
  credentialSource?: string | null;
  testLeaseExpiresAt?: string | null;
  accountMode?: 'production' | 'test' | null;
  capabilities?: {catalogRead:boolean; productPublish:boolean; orderReceive:boolean; inventoryUpdate:boolean; fulfillmentWrite:boolean};
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
  price: number | null;
  currency: string;
  stock: number | null;
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
  status: 'active' | 'inactive' | 'delisted' | 'unknown';
  updatedAt: string;
  variants: MarketplaceStoreVariant[];
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
  eventType: 'order_created' | 'order_status_update' | 'inventory_sync' | 'price_push' | 'token_refresh' | 'awb_generated' | 'webhook_ping' | 'product_publish' | 'store_disconnect' | 'store_connect';
  status: 'success' | 'failed' | 'processing' | 'warning';
  referenceId: string;
  message: string;
  payloadJson: string;
  latencyMs: number;
}
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
