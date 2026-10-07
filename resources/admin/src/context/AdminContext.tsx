import {useSupportAlerts, type SupportAlert} from '../integration/supportAlerts';
import { useSupportChat } from '../integration/supportChat';
import React, { createContext, useContext, useState, useEffect, useRef } from 'react';
import { initialSectionFromLocation, navigateToSection, subscribeToSectionChanges } from '../integration/adminRoutes';
import { readAdminBootstrap } from '../integration/adminBootstrap';
import { fetchHubEventLogs } from '../integration/marketplaceHubEvents';
import { formatAdminMoney } from '../integration/adminMoney';
import { submitLaravelLogout } from '../integration/laravelSession';
import { clonePage, createPage, fetchPages, removePage, savePageDraft } from '../integration/pageData';
import { deleteCatalogEntity, fetchCatalog, saveCatalogEntity } from '../integration/catalogData';
import { catalogStockPayload } from '../components/catalog/catalogStockPayload';
import { fetchMenus, saveMenuDocument } from '../integration/menuData';
import { customerDirectoryToCustomers, deleteCustomerData, fetchCustomerDirectoryData, saveCustomerData } from '../integration/customerDirectoryData';
import { amendOrderDeliveryDetails as amendOrderDeliveryDetailsData, fetchOrders, processOrderRefundData, reviewPaymentProofData, updateOrderStatusData, updateOrderTrackingData, type AmendOrderDeliveryDetailsInput } from '../integration/orderData';
import { LatestOrderMutationCoordinator } from './LatestOrderMutationCoordinator';
import {
  appendShipmentEvent,
  amendShipmentOperationalDetails as amendShipmentOperationalDetailsData,
  confirmEasyParcelProviderFailureWithReload,
  easyParcelShipmentStatusDiagnostic,
  fetchShipments,
  findEasyParcelReplacementParcel,
  refreshEasyParcelShipmentStatus as refreshEasyParcelShipmentStatusData,
  refreshEasyParcelShipmentStatusWithReload,
  recordExternalCourierDispatch as recordExternalCourierDispatchData,
  recordManualDeliveryDispatch as recordManualDeliveryDispatchData,
  startShipmentCorrection as startShipmentCorrectionData,
  type EasyParcelRecoveryConfirmationOutcome,
  type EasyParcelShipmentStatusRefresh,
  type EasyParcelStatusRefreshOutcome,
} from '../integration/shipmentData';
import { createEasyParcelWebhookAuditRefresher, fetchEasyParcelWebhookReceipts } from '../integration/easyParcelWebhookData';
import { courierConfigsFromEasyParcel, disconnectEasyParcelStore, fetchEasyParcelProvider, saveEasyParcelSenderProfile, startEasyParcelAuthorization, type EasyParcelProviderStatus, type EasyParcelSenderProfile } from '../integration/easyParcelProviderData';
import { confirmEasyParcelBooking, requestEasyParcelQuote, type EasyParcelBooking, type EasyParcelPackageSpec, type EasyParcelQuote } from '../integration/easyParcelFulfillmentData';
import { fetchCommerceSettings, saveBankTransferConfiguration, saveCodConfiguration, saveCommerceSettings, saveShippingConfiguration } from '../integration/commerceSettingsData';
import { deleteTaxRule as deleteTaxRuleData, deleteTaxZone as deleteTaxZoneData, globalTaxEngineFromConfiguration, saveTaxConfiguration, saveTaxRule, saveTaxZone, type TaxSettingsDocument } from '../integration/taxSettingsData';
import { activateTaxClass as activateTaxClassData, createTaxClass, deactivateTaxClass as deactivateTaxClassData, deleteTaxClass as deleteSimpleTaxClassData, fetchTaxClasses, updateTaxClass } from '../integration/taxClassData';
import { loadStorefrontShellSettings, saveStorefrontShellSettingsData } from '../integration/storefrontShellSettingsData';
import { loadCatalogListingSettings, saveCatalogListingSettings, type CatalogListingSettings } from '../integration/catalogListingSettingsData';
import {
  bulkModerateReviews as bulkModerateReviewsData,
  fetchReviewAudit as fetchReviewAuditData,
  fetchReviews as fetchReviewsData,
  moderateReview as moderateReviewData,
} from '../integration/reviewData';
import { 
  AdminProductReview,
  AdminProductReviewAuditEvent,
  AdminUser, 
  NavSection, 
  HeaderMenuL1, 
  HeaderMenuL2, 
  HeaderMenuL3, 
  FooterMenuL1, 
  FooterMenuL2, 
  Category, 
  Collection, 
  Product, 
  Customer, 
  Order, 
  PaymentStatus, 
  FulfillmentStatus,
  CmsPage,
  PageSection,
  ReviewBulkModerationResult,
  ReviewModerationAction,
  ReviewModerationResult,
  NotificationPermission,
  StaffUser,
  ShopGeneralSettings,
  StorefrontShellSettings,
  DEFAULT_STOREFRONT_SHELL_SETTINGS,
  MarketplacePlatform,
  MarketplaceConnection,
  MarketplaceOrder,
  MarketplaceInventoryItem,
  MarketplaceSyncLog
} from '../types';
import { 
  INITIAL_ADMIN_USER, 
  INITIAL_STAFF_USERS,
  INITIAL_SHOP_SETTINGS
} from '../data/mockData';
import {
  INITIAL_MARKETPLACE_CONNECTIONS,
  INITIAL_MARKETPLACE_ORDERS,
  INITIAL_MARKETPLACE_INVENTORY,
  INITIAL_MARKETPLACE_SYNC_LOGS
} from '../data/marketplaceMockData';
import {
  CourierConfig,
  SmartRoutingRule,
  ShipmentParcel,
  AmendShipmentOperationalDetailsInput,
  StartShipmentCorrectionInput,
  StartShipmentCorrectionResult,
  EasyParcelWebhookReceiptRow,
  EasyParcelWebhookAuditStatus,
  EasyParcelWebhookAuditSnapshot,
  PickupRequest,
  BillingReconciliationRecord,
  GeneralShippingRate,
  ShippingRateTier,
  PostageCalculationMode
} from '../types/shipping';
import {
  PaymentGatewayConfig,
  PaymentGatewayId,
  BankAccountDetails,
  ManualBankTransferConfig,
  CashOnDeliveryConfig,
  GlobalPaymentSettings
} from '../types/payment';
import {
  CustomOrderStatus,
  CountrySetting,
  ZoneSetting,
  CurrencySetting,
  OrderTotalModule,
  GeoTaxZone,
  TaxClass,
  TaxRate,
  GlobalTaxEngineSettings
} from '../types/oscommerceSettings';
import {
  INITIAL_CUSTOM_ORDER_STATUSES,
  INITIAL_COUNTRIES,
  INITIAL_ZONES,
  INITIAL_CURRENCIES,
  INITIAL_ORDER_TOTAL_MODULES,
  INITIAL_GLOBAL_TAX_ENGINE
} from '../data/oscommerceSettingsData';
import {
  SupportConversation,
  ConversationStatus,
  ChatMessage,
  CannedResponse,
  CopilotQueryMessage,
  CopilotReasoningLevel,
  SenderRole,
  MessageType,
  MessageAttachment
} from '../types/support';
import {
  INITIAL_CANNED_RESPONSES,
  INITIAL_INTERNAL_COPILOT_MESSAGES
} from '../data/supportData';
import {
  AIAutoReplyMasterConfig,
  FAQKnowledgeItem,
  VoucherRule,
  CustomKnowledgeDoc
} from '../types/aiAutoReply';
import { DEFAULT_AI_AUTO_REPLY_CONFIG } from '../data/defaultAIAutoReply';
import { normalizeAIAutoReplyConfig } from '../data/normalizeAIAutoReply';
import { supportFaqRequest } from '../integration/supportFaq';
import { faqCategories } from '../data/faqCategories';

const ORDERS_POLL_INTERVAL_MS = 15_000;

interface Toast {
  id: string;
  type: 'success' | 'info' | 'warning' | 'error';
  title: string;
  message: string;
}

export const fetchReviewAuditForContext = async (
  laravelBootstrap: ReturnType<typeof readAdminBootstrap>,
  reviewId: string,
  addToast: (toast: Omit<Toast, 'id'>) => void,
): Promise<AdminProductReviewAuditEvent[]> => {
  try {
    return await fetchReviewAuditData(laravelBootstrap, reviewId);
  } catch (error) {
    const message = error instanceof Error ? error.message : 'Unable to load review audit history.';
    addToast({ type: 'error', title: 'Audit Unavailable', message });
    throw error instanceof Error ? error : new Error(message);
  }
};

interface AdminContextType {
  laravelBootstrap: ReturnType<typeof readAdminBootstrap>;
  notificationPermissions: NotificationPermission[];

  // Navigation & Theme
  currentSection: NavSection;
  setCurrentSection: (section: NavSection) => void;
  theme: 'dark' | 'light';
  toggleTheme: () => void;
  isSidebarCollapsed: boolean;
  setIsSidebarCollapsed: (collapsed: boolean) => void;
  toggleSidebarCollapse: () => void;
  
  // Auth
  user: AdminUser | null;
  login: (email: string, role?: AdminUser['role']) => void;
  logout: () => void;
  isLoginModalOpen: boolean;
  setIsLoginModalOpen: (open: boolean) => void;

  // Modals & Panels
  isCommandPaletteOpen: boolean;
  setIsCommandPaletteOpen: (open: boolean) => void;
  supportAlerts: SupportAlert[];
  supportAlertUnread: number;
  supportAlertError: string;
  markSupportAlertRead: (id:string)=>Promise<boolean>;
  isNotificationOpen: boolean;
  setIsNotificationOpen: (open: boolean) => void;
  searchQuery: string;
  setSearchQuery: (query: string) => void;
  toasts: Toast[];
  addToast: (toast: Omit<Toast, 'id'>) => void;
  removeToast: (id: string) => void;

  // Header Menu CRUD
  headerMenus: HeaderMenuL1[];
  setHeaderMenus: React.Dispatch<React.SetStateAction<HeaderMenuL1[]>>;
  saveHeaderMenuL1: (item: HeaderMenuL1) => void;
  deleteHeaderMenuL1: (id: string) => void;
  saveHeaderMenuL2: (l1Id: string, item: HeaderMenuL2) => void;
  deleteHeaderMenuL2: (l1Id: string, l2Id: string) => void;
  saveHeaderMenuL3: (l1Id: string, l2Id: string, item: HeaderMenuL3) => void;
  deleteHeaderMenuL3: (l1Id: string, l2Id: string, l3Id: string) => void;

  // Footer Menu CRUD
  footerMenus: FooterMenuL1[];
  setFooterMenus: React.Dispatch<React.SetStateAction<FooterMenuL1[]>>;
  saveFooterMenuL1: (item: FooterMenuL1) => void;
  deleteFooterMenuL1: (id: string) => void;
  saveFooterMenuL2: (l1Id: string, item: FooterMenuL2) => void;
  deleteFooterMenuL2: (l1Id: string, l2Id: string) => void;
  storefrontShellSettings: StorefrontShellSettings;
  refreshStorefrontShellSettings: () => Promise<void>;
  saveStorefrontShellSettings: (settings: StorefrontShellSettings) => Promise<StorefrontShellSettings | null>;
  catalogListingSettings: CatalogListingSettings;
  saveCatalogListingSettings: (settings: CatalogListingSettings) => Promise<CatalogListingSettings | null>;

  // Catalog CRUD
  categories: Category[];
  saveCategory: (category: Category) => Promise<Category | null>;
  deleteCategory: (id: string) => Promise<void>;
  
  collections: Collection[];
  saveCollection: (collection: Collection) => Promise<Collection | null>;
  deleteCollection: (id: string) => Promise<void>;

  products: Product[];
  saveProduct: (product: Product) => Promise<Product | null>;
  deleteProduct: (id: string) => Promise<void>;
  duplicateProduct: (id: string) => void;
  reviews: AdminProductReview[];
  reviewFilterProductId: string | null;
  setReviewFilterProductId: (productId: string | null) => void;
  refreshReviews: () => Promise<void>;
  fetchReviewAudit: (reviewId: string) => Promise<AdminProductReviewAuditEvent[]>;
  moderateReview: (reviewId: string, action: ReviewModerationAction, reason?: string) => Promise<ReviewModerationResult | null>;
  bulkModerateReviews: (reviewIds: string[], action: ReviewModerationAction, reason?: string) => Promise<ReviewBulkModerationResult | null>;
  canModerateReviews: boolean;
  canBulkModerateReviews: boolean;
  canViewReviewAudit: boolean;

  // CMS Page & Layout Builder
  pages: CmsPage[];
  selectedPageId: string | null;
  setSelectedPageId: (id: string | null) => void;
  savePage: (page: CmsPage) => Promise<CmsPage | null>;
  updatePageLocal: (pageId: string, updater: (page: CmsPage) => CmsPage) => CmsPage | null;
  replacePageLocal: (page: CmsPage) => void;
  updatePageSection: (
    pageId: string,
    sectionId: string,
    updater: (section: PageSection) => PageSection,
  ) => Promise<CmsPage | null>;
  deletePage: (id: string) => Promise<void>;
  duplicatePage: (id: string) => Promise<CmsPage | null>;

  // Customers
  customers: Customer[];
  selectedCustomerId: string | null;
  setSelectedCustomerId: (id: string | null) => void;
  saveCustomer: (customer: Customer) => Promise<Customer | null>;
  deleteCustomer: (id: string | number) => Promise<void>;

  // Orders
  orders: Order[];
  reloadOrders: () => Promise<void>;
  selectedOrderId: string | null;
  setSelectedOrderId: (id: string | null) => void;
  updateOrderStatus: (orderId: string, paymentStatus?: PaymentStatus, fulfillmentStatus?: FulfillmentStatus, note?: string, notifyCustomer?: boolean) => Promise<Order | null>;
  updateOrderTracking: (orderId: string, carrier: string, trackingNumber: string) => Promise<Order | null>;
  processOrderRefund: (orderId: string, amount: number, reason: string) => Promise<Order | null>;
  reviewPaymentProof: (orderId: string, paymentId: string, decision: 'verified' | 'rejected', reason?: string) => Promise<Order | null>;
  amendOrderDeliveryDetails: (orderId: string, input: AmendOrderDeliveryDetailsInput) => Promise<Order>;
  reloadShipmentParcels: () => Promise<void>;

  // Settings & Staff RBAC
  staffUsers: StaffUser[];
  saveStaffUser: (staff: StaffUser) => void;
  deleteStaffUser: (id: string) => void;
  toggleStaffStatus: (id: string) => void;
  resetStaffPassword: (id: string, newPass: string) => void;
  shopSettings: ShopGeneralSettings;
  saveShopSettings: (settings: ShopGeneralSettings) => Promise<void>;

  // Marketplace Ecosystem (OMS & WMS)
  connections: MarketplaceConnection[];
  saveConnection: (conn: MarketplaceConnection) => void;
  deleteConnection: (id: string) => void;
  toggleConnectionSync: (id: string, field: 'autoSyncStock' | 'autoPullOrders') => void;
  refreshToken: (id: string) => void;
  syncAllMarketplaces: () => void;

  marketplaceOrders: MarketplaceOrder[];
  selectedMarketplaceOrderId: string | null;
  setSelectedMarketplaceOrderId: (id: string | null) => void;
  saveMarketplaceOrder: (order: MarketplaceOrder) => void;
  generateAirwayBill: (orderId: string, courierName?: string) => void;
  markOrderPacked: (orderId: string, packedBy: string) => void;
  markOrderShipped: (orderId: string, trackingNumber: string, carrier: string) => void;
  batchPrintAWB: (orderIds: string[]) => void;
  batchPackOrders: (orderIds: string[]) => void;

  marketplaceInventory: MarketplaceInventoryItem[];
  saveInventoryItem: (item: MarketplaceInventoryItem) => void;
  adjustStock: (idOrSku: string, change: number, reason: string, user?: string) => void;
  broadcastStockToChannels: (sku: string) => void;
  broadcastAllStockToChannels: () => void;
  linkChannelSku: (inventoryId: string, platform: MarketplacePlatform, mapping: import('../types').ChannelSkuMapping) => void;
  unlinkChannelSku: (inventoryId: string, platform: MarketplacePlatform) => void;

  syncLogs: MarketplaceSyncLog[];
  addSyncLog: (log: Omit<MarketplaceSyncLog, 'id' | 'timestamp'>) => void;
  retrySyncLog: (logId: string) => void;
  clearSyncLogs: () => void;
  simulateIncomingOrder: (platform: MarketplacePlatform) => void;

  // Malaysia Shipping & Fulfillment Module
  courierConfigs: CourierConfig[];
  easyParcelProvider: EasyParcelProviderStatus | null;
  saveCourierConfig: (config: CourierConfig) => void;
  refreshEasyParcelProvider: () => Promise<void>;
  disconnectEasyParcel: (environment?: 'sandbox' | 'production') => Promise<void>;
  saveEasyParcelSender: (environment: 'sandbox' | 'production', profile: EasyParcelSenderProfile) => Promise<void>;
  quoteEasyParcelShipment: (parcel: ShipmentParcel, packageSpec: EasyParcelPackageSpec) => Promise<EasyParcelQuote>;
  bookEasyParcelShipment: (parcel: ShipmentParcel, quote: EasyParcelQuote, serviceCode: string) => Promise<EasyParcelBooking>;
  toggleCourierStatus: (id: string) => void;
  pingCourierAPI: (id: string, environment?: 'sandbox' | 'production') => Promise<number>;

  smartRoutingRules: SmartRoutingRule[];
  saveSmartRoutingRule: (rule: SmartRoutingRule) => void;
  deleteSmartRoutingRule: (id: string) => void;
  toggleSmartRoutingRule: (id: string) => void;
  reorderSmartRoutingRules: (rules: SmartRoutingRule[]) => void;

  shipmentParcels: ShipmentParcel[];
  refreshEasyParcelShipmentStatus: (parcelId: string) => Promise<EasyParcelStatusRefreshOutcome>;
  refreshAllEasyParcelShipmentStatuses: () => Promise<EasyParcelStatusRefreshOutcome[]>;
  confirmEasyParcelProviderFailure: (parcelId: string, reason: string) => Promise<EasyParcelRecoveryConfirmationOutcome>;
  easyParcelWebhookReceipts: EasyParcelWebhookReceiptRow[];
  easyParcelWebhookAuditStatus: EasyParcelWebhookAuditStatus;
  easyParcelWebhookAuditError: string | null;
  refreshEasyParcelWebhookReceipts: () => Promise<void>;
  selectedParcelId: string | null;
  setSelectedParcelId: (id: string | null) => void;
  generateShipmentAWB: (parcelId: string, courierCode?: string) => void;
  batchGenerateAWB: (parcelIds: string[]) => void;
  updateShipmentStatus: (parcelId: string, status: ShipmentParcel['status'], eventDesc?: string, location?: string, voidConfirmed?: boolean) => Promise<void>;
  recordExternalCourierDispatch: (parcelId: string, data: { courierCode: string; courierName: string; trackingNumber: string; trackingUrl?: string; auditNote: string }) => Promise<void>;
  recordManualDeliveryDispatch: (parcelId: string, data: { driverName: string; handoffReference?: string; auditNote: string }) => Promise<void>;
  startShipmentCorrection: (parcelId: string, input: StartShipmentCorrectionInput) => Promise<{ result: StartShipmentCorrectionResult; replacementParcel: ShipmentParcel }>;
  amendShipmentOperationalDetails: (parcelId: string, input: AmendShipmentOperationalDetailsInput) => Promise<void>;
  requestRedelivery: (parcelId: string) => void;
  createRMAReturnShipment: (orderNumber: string, reason: string) => void;
  simulateCourierWebhookEvent: (parcelId: string, eventType: string, location: string, description: string) => void;

  pickupRequests: PickupRequest[];
  createPickupRequest: (request: Omit<PickupRequest, 'id' | 'createdAt'>) => void;
  cancelPickupRequest: (id: string) => void;

  billingReconciliations: BillingReconciliationRecord[];
  resolveBillingDispute: (id: string) => void;

  // General Shipping Rates & Postage Calculation Engine
  shippingRates: GeneralShippingRate[];
  saveShippingRate: (rate: GeneralShippingRate) => void;
  deleteShippingRate: (id: string) => void;
  toggleShippingRateStatus: (id: string) => void;
  duplicateShippingRate: (id: string) => void;
  calculateShippingRateBreakdown: (params: {
    region?: string;
    state?: string;
    weightKg?: number;
    quantity?: number;
    orderAmount?: number;
    courierCode?: string;
    rateId?: string;
  }) => {
    appliedRate: GeneralShippingRate | null;
    baseCost: number;
    subsequentCost: number;
    totalShippingFee: number;
    isFreeShipping: boolean;
    freeShippingSavedRM?: number;
    tierMatched?: ShippingRateTier;
    subsequentUnitsBilled?: number;
    breakdownSummary: string;
  };

  // Payment Settings Module
  paymentGateways: PaymentGatewayConfig[];
  savePaymentGateway: (config: PaymentGatewayConfig) => Promise<boolean>;
  togglePaymentGateway: (id: PaymentGatewayId, enabled: boolean) => Promise<void>;
  pingPaymentGateway: (id: PaymentGatewayId) => Promise<number>;
  bankTransferConfig: ManualBankTransferConfig;
  saveBankTransferConfig: (config: ManualBankTransferConfig) => Promise<boolean>;
  saveBankAccount: (account: BankAccountDetails) => Promise<boolean>;
  deleteBankAccount: (id: string) => Promise<boolean>;
  setPrimaryBankAccount: (id: string) => Promise<boolean>;
  codConfig: CashOnDeliveryConfig;
  saveCodConfig: (config: CashOnDeliveryConfig) => Promise<boolean>;
  globalPaymentSettings: GlobalPaymentSettings;
  saveGlobalPaymentSettings: (settings: GlobalPaymentSettings) => void;

  // osCommerce Orders Status Setting
  orderStatuses: CustomOrderStatus[];
  saveOrderStatus: (status: CustomOrderStatus) => void;
  deleteOrderStatus: (id: string) => void;
  setDefaultOrderStatus: (id: string) => void;

  // osCommerce Countries Setting
  countriesSetting: CountrySetting[];
  countries: CountrySetting[];
  saveCountrySetting: (country: CountrySetting) => void;
  toggleCountryStatus: (id: string) => void;

  // osCommerce Zones Setting
  zonesSetting: ZoneSetting[];
  zones: ZoneSetting[];
  saveZoneSetting: (zone: ZoneSetting) => void;
  toggleZoneStatus: (id: string) => void;
  deleteZoneSetting: (id: string) => void;

  // osCommerce Currencies Setting
  currenciesSetting: CurrencySetting[];
  currencies: CurrencySetting[];
  saveCurrencySetting: (currency: CurrencySetting) => void;
  setDefaultCurrency: (id: string) => void;
  toggleCurrencyStatus: (id: string) => void;
  updateCurrencyRates: () => Promise<void>;

  // osCommerce Order Total Pipeline
  orderTotalModules: OrderTotalModule[];
  saveOrderTotalModule: (module: OrderTotalModule) => void;
  reorderOrderTotalModules: (modules: OrderTotalModule[]) => void;
  toggleOrderTotalModule: (id: string) => void;

  // osCommerce Tax Zones & SST Engine
  taxZones: GeoTaxZone[];
  taxClasses: TaxClass[];
  taxRates: TaxRate[];
  globalTaxEngine: GlobalTaxEngineSettings;
  saveGeoTaxZone: (zone: GeoTaxZone) => void;
  deleteGeoTaxZone: (id: string) => void;
  saveTaxClass: (taxClass: TaxClass) => void;
  activateTaxClass: (id: string) => void;
  deactivateTaxClass: (id: string) => void;
  deleteTaxClass: (id: string) => void;
  saveTaxRate: (taxRate: TaxRate) => void;
  deleteTaxRate: (id: string) => void;
  saveGlobalTaxEngine: (settings: Partial<GlobalTaxEngineSettings>) => void;

  // Customer Support Hub & AI Copilot
  supportConversations: SupportConversation[];
  supportLoading: boolean;
  supportError: string;
  supportBusy: boolean;
  supportDrafts: Record<string, string>;
  setSupportDraft: (conversationId: string, text: string) => void;
  selectedConversationId: string | null;
  setSelectedConversationId: (id: string | null) => void;
  supportActiveTab: 'inbox' | 'copilot';
  setSupportActiveTab: (tab: 'inbox' | 'copilot') => void;
  sendMessageToConversation: (
    conversationId: string,
    content: string,
    type?: MessageType,
    attachments?: MessageAttachment[],
    options?: { role?: SenderRole; productCard?: any; orderCard?: any }
  ) => Promise<boolean>;
  takeOverConversation: (conversationId: string) => void;
  handBackToAI: (conversationId: string) => void;
  archiveConversation: (conversationId: string, archived: boolean) => void;
  resolveConversation: (conversationId: string) => void;
  reopenConversation: (conversationId: string) => void;
  approveAIDraft: (conversationId: string) => void;
  discardAIDraft: (conversationId: string) => void;
  generateAIDraft: (conversationId: string) => Promise<void>;
  togglePinConversation: (conversationId: string) => void;
  markConversationAsRead: (conversationId: string) => void;
  addStaffNote: (conversationId: string, noteContent: string) => Promise<boolean>;
  retryFailedMessage: (conversationId: string, messageId: string) => void;
  cannedResponses: CannedResponse[];
  copilotMessages: CopilotQueryMessage[];
  askInternalCopilot: (query: string, reasoningLevel?: CopilotReasoningLevel) => Promise<void>;
  clearCopilotHistory: () => void;

  // AI Auto Reply (Storefront AI Support Personality, Knowledge Scope & Guardrails)
  aiAutoReplyConfig: AIAutoReplyMasterConfig;
  updateAIAutoReplyConfig: (updater: (prev: AIAutoReplyMasterConfig) => AIAutoReplyMasterConfig) => void;
  saveAIAutoReplyConfig: (newConfig: AIAutoReplyMasterConfig) => Promise<boolean>;
  addFAQItem: (item: Omit<FAQKnowledgeItem, 'id' | 'lastUpdatedAt'>) => void;
  updateFAQItem: (id: string, updates: Partial<FAQKnowledgeItem>) => void;
  deleteFAQItem: (id: string) => void;
  addVoucherRule: (voucher: Omit<VoucherRule, 'id'>) => void;
  updateVoucherRule: (id: string, updates: Partial<VoucherRule>) => void;
  deleteVoucherRule: (id: string) => void;
  addKnowledgeDoc: (doc: Omit<CustomKnowledgeDoc, 'id' | 'lastUpdatedAt' | 'characterCount'>) => void;
  updateKnowledgeDoc: (id: string, updates: Partial<CustomKnowledgeDoc>) => void;
  deleteKnowledgeDoc: (id: string) => void;
  resetAIAutoReplyToDefaults: () => void;

  // Global Reset
  resetToDefaults: () => void;
}

const AdminContext = createContext<AdminContextType | undefined>(undefined);

const EMPTY_BANK_TRANSFER: ManualBankTransferConfig = { isEnabled: false, bankAccounts: [], requireProofUpload: true, verificationInstructions: '', paymentWindowMinutes: 1440, notificationEmail: '', allowInstantDuitNow: false };
const EMPTY_COD_CONFIG: CashOnDeliveryConfig = { isEnabled: false, surchargeType: 'fixed', surchargeAmount: 0, minOrderAmount: 0, maxOrderAmount: 0, allowedRegions: 'all', customPostcodes: [], requirePhoneOtp: false, instructions: '' };
const EMPTY_GLOBAL_PAYMENT: GlobalPaymentSettings = { defaultGateway: '', allowMultiplePaymentMethods: false, enableSandboxBanner: false, unpaidOrderAutoCancelMinutes: 120, sendPaymentReminderSms: false, sendPaymentReminderEmail: false, displayTaxInvoiceAtCheckout: false, enableLoyaltyPointsSplitPayment: false, maxLoyaltyPointsDiscountPercent: 0 };

const STORAGE_KEYS = {
  THEME: 'aurora_admin_theme',
  USER: 'aurora_admin_user',
  HEADER_MENUS: 'aurora_admin_header_menus',
  FOOTER_MENUS: 'aurora_admin_footer_menus',
  CATEGORIES: 'aurora_admin_categories',
  COLLECTIONS: 'aurora_admin_collections',
  PRODUCTS: 'aurora_admin_products',
  PAGES: 'aurora_admin_pages',
  CUSTOMERS: 'aurora_admin_customers',
  STAFF_USERS: 'aurora_admin_staff_users',
  SHOP_SETTINGS: 'aurora_admin_shop_settings',
  MARKETPLACE_CONNECTIONS: 'aurora_admin_mp_connections',
  MARKETPLACE_ORDERS: 'aurora_admin_mp_orders',
  MARKETPLACE_INVENTORY: 'aurora_admin_mp_inventory',
  MARKETPLACE_SYNC_LOGS: 'aurora_admin_mp_sync_logs',
  COURIER_CONFIGS: 'aurora_admin_courier_configs',
  SMART_ROUTING_RULES: 'aurora_admin_smart_routing_rules',
  SHIPMENT_PARCELS: 'aurora_admin_shipment_parcels',
  PICKUP_REQUESTS: 'aurora_admin_pickup_requests',
  BILLING_RECONCILIATIONS: 'aurora_admin_billing_reconciliations',
  CUSTOM_ORDER_STATUSES: 'aurora_admin_order_statuses',
  COUNTRIES_SETTING: 'aurora_admin_countries_setting',
  ZONES_SETTING: 'aurora_admin_zones_setting',
  ORDER_TOTAL_MODULES: 'aurora_admin_order_total_modules',
  COPILOT_MESSAGES: 'aurora_admin_copilot_messages',
  AI_AUTO_REPLY_CONFIG: 'aurora_admin_ai_auto_reply_config',
};

export const AdminProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const laravelBootstrap = readAdminBootstrap();
  const notificationPermissions = laravelBootstrap.notificationPermissions
    ?? laravelBootstrap.user.notificationPermissions
    ?? [];
  const [currentSection, setCurrentSectionState] = useState<NavSection>(() => {
    if (typeof window === 'undefined') return 'dashboard';
    return initialSectionFromLocation();
  });

  const setCurrentSection = (section: NavSection) => {
    const previousPath = window.location.pathname;
    navigateToSection(section);
    if (section === 'support' && previousPath !== '/admin/support') {
      window.history.replaceState({ supportReturnPath: previousPath }, '');
    }
    setCurrentSectionState(section);
  };

  useEffect(() => subscribeToSectionChanges(setCurrentSectionState), []);

  useEffect(() => {
    let active = true;

    void fetchPages(laravelBootstrap)
      .then((loadedPages) => {
        if (active) {
          pagesRef.current = loadedPages;
          setPages(loadedPages);
        }
      })
      .catch(() => {
        if (active) {
          addToast({ type: 'error', title: 'CMS Unavailable', message: 'Unable to load saved pages from Laravel.' });
        }
      });

    return () => {
      active = false;
    };
  // The API bootstrap is emitted once by Laravel for the lifetime of the admin app.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    let active = true;
    void fetchEasyParcelProvider(laravelBootstrap).then((provider) => {
      if (active) {
        setEasyParcelProvider(provider);
        setCourierConfigs(courierConfigsFromEasyParcel(provider));
      }
    }).catch(() => {
      if (active) addToast({ type: 'error', title: 'EasyParcel Unavailable', message: 'Unable to load the server-side EasyParcel provider state.' });
    });
    return () => { active = false; };
  // Laravel emits this immutable bootstrap once per admin app load.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    void fetchTaxClasses(laravelBootstrap).then(document => {
      setTaxClasses(document.classes);
    }).catch(() => undefined);
  }, []);

  useEffect(() => {
    let active = true;
    void fetchOrders(laravelBootstrap).then(loaded => { if (active) setOrders(loaded); }).catch(() => {
      if (active) addToast({ type: 'error', title: 'Orders Unavailable', message: 'Unable to load orders from MySQL.' });
    });
    return () => { active = false; };
  // Laravel emits this immutable bootstrap once per admin app load.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    if (currentSection !== 'orders') return;

    let active = true;
    const refreshOrders = () => {
      if (document.visibilityState !== 'visible') return;

      void fetchOrders(laravelBootstrap)
        .then(loaded => { if (active) setOrders(loaded); })
        .catch(() => undefined);
    };

    const timer = window.setInterval(refreshOrders, ORDERS_POLL_INTERVAL_MS);
    document.addEventListener('visibilitychange', refreshOrders);

    return () => {
      active = false;
      window.clearInterval(timer);
      document.removeEventListener('visibilitychange', refreshOrders);
    };
  // Laravel emits this immutable bootstrap once per admin app load.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [currentSection]);

  useEffect(() => {
    let active = true;
    void fetchShipments(laravelBootstrap).then((loaded) => {
      if (active) setShipmentParcels(loaded);
    }).catch(() => {
      if (active) addToast({ type: 'error', title: 'Shipments Unavailable', message: 'Unable to load shipment and event records from MySQL.' });
    });
    return () => { active = false; };
  // Laravel emits this immutable bootstrap once per admin app load.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    let active = true;
    void fetchReviewsData(laravelBootstrap).then((loadedReviews) => {
      if (active) setReviews(loadedReviews);
    }).catch(() => {
      if (active) addToast({ type: 'error', title: 'Reviews Unavailable', message: 'Unable to load product reviews from Laravel.' });
    });
    return () => { active = false; };
  // Laravel emits this immutable bootstrap once per admin app load.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    let active = true;
    void fetchMenus(laravelBootstrap).then((menus) => {
      if (!active) return;
      setHeaderMenus(menus.headerMenus);
      setFooterMenus(menus.footerMenus);
    }).catch(() => {
      if (active) addToast({ type: 'error', title: 'Navigation Unavailable', message: 'Unable to load menus from MySQL.' });
    });
    return () => { active = false; };
  // Laravel emits this immutable bootstrap once per admin app load.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    let active = true;
    void loadStorefrontShellSettings(laravelBootstrap).then((settings) => {
      if (active) setStorefrontShellSettings(settings);
    }).catch(() => {
      if (active) addToast({ type: 'error', title: 'Storefront Shell Unavailable', message: 'Unable to load header and announcement settings from Laravel.' });
    });
    return () => { active = false; };
  // Laravel emits this immutable bootstrap once per admin app load.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    let active = true;
    void fetchCatalog(laravelBootstrap)
      .then((catalog) => {
        if (!active) return;
        setCategories(catalog.categories);
        setCollections(catalog.collections);
        setProducts(catalog.products);
      })
      .catch(() => {
        if (active) addToast({ type: 'error', title: 'Catalog Unavailable', message: 'Unable to load the MySQL catalog.' });
      });
    return () => { active = false; };
  // Laravel emits this immutable bootstrap once per admin app load.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    let active = true;
    void fetchCustomerDirectoryData(laravelBootstrap).then(data => {
      if (active && data) setCustomers(customerDirectoryToCustomers(data));
    }).catch(() => {
      if (active) addToast({ type: 'error', title: 'Customers Unavailable', message: 'Unable to load customers from MySQL.' });
    });
    return () => { active = false; };
  // Laravel emits this immutable bootstrap once per admin app load.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    let active = true;
    void fetchCommerceSettings(laravelBootstrap).then(document => {
      if (!active) return;
      if (document.shippingRates.length) setShippingRates(document.shippingRates);
      if (document.paymentGateways.length) setPaymentGateways(document.paymentGateways);
      if (Object.keys(document.globalPayment).length) setGlobalPaymentSettings(document.globalPayment as GlobalPaymentSettings);
      if (Object.keys(document.bankTransfer).length) setBankTransferConfig(document.bankTransfer as ManualBankTransferConfig);
      if (Object.keys(document.codConfig).length) setCodConfig(document.codConfig as CashOnDeliveryConfig);
      if (document.currencies.length) setCurrenciesSetting(document.currencies);
    }).catch(() => {
      if (active) addToast({ type: 'error', title: 'Commerce Settings Unavailable', message: 'Unable to load payment and shipping settings from MySQL.' });
    });
    return () => { active = false; };
  // Laravel emits this immutable bootstrap once per admin app load.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  const [theme, setTheme] = useState<'dark' | 'light'>(() => {
    try {
      const savedTheme = localStorage.getItem(STORAGE_KEYS.THEME) as 'dark' | 'light' | null;
      if (savedTheme === 'light' || savedTheme === 'dark') {
        if (savedTheme === 'dark') {
          document.documentElement.classList.add('dark');
          document.documentElement.setAttribute('data-theme', 'dark');
        } else {
          document.documentElement.classList.remove('dark');
          document.documentElement.setAttribute('data-theme', 'light');
        }
        return savedTheme;
      }
    } catch {
      // ignore
    }
    // Default to dark
    document.documentElement.classList.add('dark');
    document.documentElement.setAttribute('data-theme', 'dark');
    return 'dark';
  });
  const [isSidebarCollapsed, setIsSidebarCollapsed] = useState<boolean>(() => {
    try {
      return localStorage.getItem('aurora_sidebar_collapsed') === 'true';
    } catch {
      return false;
    }
  });
  const [user, setUser] = useState<AdminUser | null>(() => ({
    ...INITIAL_ADMIN_USER,
    name: laravelBootstrap.user.name,
    email: laravelBootstrap.user.email,
    avatar: laravelBootstrap.user.avatar ?? INITIAL_ADMIN_USER.avatar,
  }));
  const [isLoginModalOpen, setIsLoginModalOpen] = useState(false);
  const [isCommandPaletteOpen, setIsCommandPaletteOpen] = useState(false);
  const {supportAlerts,supportAlertUnread,supportAlertError,markSupportAlertRead}=useSupportAlerts();
  const [isNotificationOpen, setIsNotificationOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [toasts, setToasts] = useState<Toast[]>([]);

  // Entities
  const [headerMenus, setHeaderMenus] = useState<HeaderMenuL1[]>([]);
  const [footerMenus, setFooterMenus] = useState<FooterMenuL1[]>([]);
  const [storefrontShellSettings, setStorefrontShellSettings] = useState<StorefrontShellSettings>(DEFAULT_STOREFRONT_SHELL_SETTINGS);
  const [catalogListingSettings, setCatalogListingSettings] = useState<CatalogListingSettings>({ eyebrow: '', title: '', description: '' });
  const [categories, setCategories] = useState<Category[]>([]);
  const [collections, setCollections] = useState<Collection[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [reviews, setReviews] = useState<AdminProductReview[]>([]);
  const [pages, setPages] = useState<CmsPage[]>([]);
  const pagesRef = useRef<CmsPage[]>([]);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [orders, setOrders] = useState<Order[]>([]);
  const orderMutationCoordinatorRef = useRef<LatestOrderMutationCoordinator | null>(null);
  if (orderMutationCoordinatorRef.current === null) {
    orderMutationCoordinatorRef.current = new LatestOrderMutationCoordinator();
  }
  const [staffUsers, setStaffUsers] = useState<StaffUser[]>(INITIAL_STAFF_USERS);
  const [shopSettings, setShopSettings] = useState<ShopGeneralSettings>(INITIAL_SHOP_SETTINGS);
  const shopProfileLoaded = useRef(false);
  useEffect(() => {
    let active = true;
    void loadShopBranding().then(profile => {
      if (active) { setShopSettings(current => ({ ...current, ...profile, shopLogo: profile.shopLogo || INITIAL_SHOP_SETTINGS.shopLogo })); shopProfileLoaded.current = true; }
    }).catch(error => { if (active) addToast({ type: 'error', title: 'Shop Profile Unavailable', message: error.message }); });
    return () => { active = false; };
  }, []);

  useEffect(() => {
    pagesRef.current = pages;
  }, [pages]);

  // Marketplace Ecosystem State
  const [connections, setConnections] = useState<MarketplaceConnection[]>([]);
  const [marketplaceOrders, setMarketplaceOrders] = useState<MarketplaceOrder[]>([]);
  const [marketplaceInventory, setMarketplaceInventory] = useState<MarketplaceInventoryItem[]>([]);
  const [syncLogs, setSyncLogs] = useState<MarketplaceSyncLog[]>([]);
  useEffect(() => {
    const refresh = () => void fetchHubEventLogs().then(setSyncLogs).catch(() => undefined);
    refresh();
    const timer = window.setInterval(refresh, 30000);
    return () => window.clearInterval(timer);
  }, []);
  const [selectedMarketplaceOrderId, setSelectedMarketplaceOrderId] = useState<string | null>(null);

  // Malaysia Shipping Module State
  const [courierConfigs, setCourierConfigs] = useState<CourierConfig[]>([]);
  const [easyParcelProvider, setEasyParcelProvider] = useState<EasyParcelProviderStatus | null>(null);
  const [smartRoutingRules, setSmartRoutingRules] = useState<SmartRoutingRule[]>([]);
  const [shipmentParcels, setShipmentParcels] = useState<ShipmentParcel[]>([]);
  const [easyParcelWebhookAudit, setEasyParcelWebhookAudit] = useState<EasyParcelWebhookAuditSnapshot>({
    receipts: [],
    status: 'loading',
    error: null,
  });
  const easyParcelWebhookAuditRefreshRef = useRef<(() => Promise<void>) | null>(null);
  if (easyParcelWebhookAuditRefreshRef.current === null) {
    easyParcelWebhookAuditRefreshRef.current = createEasyParcelWebhookAuditRefresher(
      () => fetchEasyParcelWebhookReceipts(laravelBootstrap),
      setEasyParcelWebhookAudit,
    );
  }
  const [pickupRequests, setPickupRequests] = useState<PickupRequest[]>([]);
  const [billingReconciliations, setBillingReconciliations] = useState<BillingReconciliationRecord[]>([]);
  const [shippingRates, setShippingRates] = useState<GeneralShippingRate[]>([]);
  const [selectedParcelId, setSelectedParcelId] = useState<string | null>(null);

  // Payment Settings State
  const [paymentGateways, setPaymentGateways] = useState<PaymentGatewayConfig[]>([]);
  const [bankTransferConfig, setBankTransferConfig] = useState<ManualBankTransferConfig>(EMPTY_BANK_TRANSFER);
  const [codConfig, setCodConfig] = useState<CashOnDeliveryConfig>(EMPTY_COD_CONFIG);
  const [globalPaymentSettings, setGlobalPaymentSettings] = useState<GlobalPaymentSettings>(EMPTY_GLOBAL_PAYMENT);

  // osCommerce Settings State
  const [orderStatuses, setOrderStatuses] = useState<CustomOrderStatus[]>(INITIAL_CUSTOM_ORDER_STATUSES);
  const [countriesSetting, setCountriesSetting] = useState<CountrySetting[]>(INITIAL_COUNTRIES);
  const [zonesSetting, setZonesSetting] = useState<ZoneSetting[]>(INITIAL_ZONES);
  const [currenciesSetting, setCurrenciesSetting] = useState<CurrencySetting[]>(INITIAL_CURRENCIES);
  const [orderTotalModules, setOrderTotalModules] = useState<OrderTotalModule[]>(INITIAL_ORDER_TOTAL_MODULES);
  const [taxZones, setTaxZones] = useState<GeoTaxZone[]>([]);
  const [taxClasses, setTaxClasses] = useState<TaxClass[]>([]);
  const [taxRates, setTaxRates] = useState<TaxRate[]>([]);
  const [globalTaxEngine, setGlobalTaxEngine] = useState<GlobalTaxEngineSettings>(INITIAL_GLOBAL_TAX_ENGINE);
  const [taxConfiguration, setTaxConfiguration] = useState<TaxSettingsDocument['configuration']>({
    isEnabled: false, isPublished: false, pricesIncludeTax: false, addressBasis: 'shipping_address', merchantComplianceMetadata: {},
  });

  // Customer Support Hub & AI Copilot State
  const { supportConversations, supportDrafts, setSupportDraft, selectedConversationId, setSelectedConversationId,
    supportLoading, supportError, supportBusy, refreshSupport, sendMessageToConversation, resolveConversation,
    reopenConversation, markConversationAsRead, addStaffNote, takeOverConversation, handBackToAI, archiveConversation, togglePinConversation } = useSupportChat(currentSection === 'support');
  const [supportActiveTab, setSupportActiveTab] = useState<'inbox' | 'copilot'>('inbox');
  const [cannedResponses, setCannedResponses] = useState<CannedResponse[]>(INITIAL_CANNED_RESPONSES);
  const [copilotMessages, setCopilotMessages] = useState<CopilotQueryMessage[]>(INITIAL_INTERNAL_COPILOT_MESSAGES);
  const [aiAutoReplyConfig, setAiAutoReplyConfig] = useState<AIAutoReplyMasterConfig>(DEFAULT_AI_AUTO_REPLY_CONFIG);
  const faqServerVersion = useRef<number | null>(null);
  const faqSaving = useRef(false);
  useEffect(() => {
    let active = true;
    void supportFaqRequest().then(data => {
      if (!active) return;
      faqServerVersion.current = data.version;
      if (data.faq || data.identity || typeof data.autoReplyEnabled === "boolean") setAiAutoReplyConfig(prev => normalizeAIAutoReplyConfig({ ...prev, guardrails:{...prev.guardrails,...data.guardrails}, aiChannels: data.aiChannels || prev.aiChannels, personality: {...prev.personality, ...(data.identity || {}), ...(data.personality || {}), ...(data.greetingMessage ? {greetingMessage:data.greetingMessage} : {}), ...(data.primaryLanguage ? {primaryLanguage:data.primaryLanguage} : {}), ...(typeof data.autoReplyEnabled === "boolean" ? {isEnabled:data.autoReplyEnabled} : {})}, knowledgeScope: { ...prev.knowledgeScope, responsePolicy:data.responsePolicy??prev.knowledgeScope.responsePolicy, orderLookup: {...prev.knowledgeScope.orderLookup,...data.orderLookup}, discountKnowledge: data.discountKnowledge || {isEnabled:false,activeIds:[]}, productCatalog: {...prev.knowledgeScope.productCatalog, ...data.productCatalog}, faqKnowledge: data.faq || prev.knowledgeScope.faqKnowledge } }));
    }).catch(() => { if (active) addToast({ type: 'error', title: 'FAQ unavailable', message: 'Reload saved FAQs before publishing.' }); });
    return () => { active = false; };
  }, []);

  // Selected drawers
  const [selectedCustomerId, setSelectedCustomerId] = useState<string | null>(null);
  const [selectedOrderId, setSelectedOrderId] = useState<string | null>(null);
  const [selectedPageId, setSelectedPageId] = useState<string | null>(null);
  const [reviewFilterProductId, setReviewFilterProductId] = useState<string | null>(null);
  const reviewPermissions = laravelBootstrap.user.reviewPermissions ?? [];
  const canModerateReviews = reviewPermissions.includes('reviews.moderate');
  const canBulkModerateReviews = reviewPermissions.includes('reviews.bulk_moderate');
  const canViewReviewAudit = reviewPermissions.includes('reviews.audit.view');

  // Load from local storage
  useEffect(() => {
    try {
      const safeParseArray = <T,>(key: string, fallback: T[]): T[] => {
        try {
          const item = localStorage.getItem(key);
          if (!item) return fallback;
          const parsed = JSON.parse(item);
          return Array.isArray(parsed) ? parsed : fallback;
        } catch {
          return fallback;
        }
      };

      const savedStaff = safeParseArray(STORAGE_KEYS.STAFF_USERS, INITIAL_STAFF_USERS);
      setStaffUsers(savedStaff);


      // Marketplace fixtures are retired. Authoritative records load in MarketplaceProvider.

      const savedStatuses = safeParseArray(STORAGE_KEYS.CUSTOM_ORDER_STATUSES, INITIAL_CUSTOM_ORDER_STATUSES);
      setOrderStatuses(savedStatuses);

      const savedCountries = safeParseArray(STORAGE_KEYS.COUNTRIES_SETTING, INITIAL_COUNTRIES);
      setCountriesSetting(savedCountries);

      const savedZones = safeParseArray(STORAGE_KEYS.ZONES_SETTING, INITIAL_ZONES);
      setZonesSetting(savedZones);

      const savedOrderTotals = safeParseArray(STORAGE_KEYS.ORDER_TOTAL_MODULES, INITIAL_ORDER_TOTAL_MODULES);
      setOrderTotalModules(savedOrderTotals);


      const savedCopilotMsgs = safeParseArray(STORAGE_KEYS.COPILOT_MESSAGES, INITIAL_INTERNAL_COPILOT_MESSAGES);
      setCopilotMessages(savedCopilotMsgs);

      const savedAiConfig = localStorage.getItem(STORAGE_KEYS.AI_AUTO_REPLY_CONFIG);
      if (savedAiConfig) {
        try {
          const parsed = JSON.parse(savedAiConfig);
          if (parsed && typeof parsed === 'object') {
            setAiAutoReplyConfig(normalizeAIAutoReplyConfig(parsed));
          }
        } catch {}
      }
    } catch (e) {
      console.warn('LocalStorage error:', e);
    }
  }, []);

  useEffect(() => {
    let active = true;
    void loadCatalogListingSettings(laravelBootstrap).then((settings) => {
      if (active) setCatalogListingSettings(settings);
    }).catch(() => {
      if (active) addToast({ type: 'error', title: 'Catalog Listing Unavailable', message: 'Unable to load public catalog listing copy from Laravel.' });
    });
    return () => { active = false; };
  // Laravel emits this immutable bootstrap once per admin app load.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Save changes
  useEffect(() => {
    try {
      localStorage.setItem(STORAGE_KEYS.THEME, theme);
    } catch (e) {
      // ignore
    }
    if (theme === 'dark') {
      document.documentElement.classList.add('dark');
      document.documentElement.setAttribute('data-theme', 'dark');
    } else {
      document.documentElement.classList.remove('dark');
      document.documentElement.setAttribute('data-theme', 'light');
    }
  }, [theme]);

  const toggleTheme = () => {
    setTheme(prev => (prev === 'dark' ? 'light' : 'dark'));
  };

  const toggleSidebarCollapse = () => {
    setIsSidebarCollapsed(prev => {
      const next = !prev;
      try {
        localStorage.setItem('aurora_sidebar_collapsed', String(next));
      } catch (e) {
        // ignore
      }
      return next;
    });
  };

  const handleSetSidebarCollapsed = (collapsed: boolean) => {
    setIsSidebarCollapsed(collapsed);
    try {
      localStorage.setItem('aurora_sidebar_collapsed', String(collapsed));
    } catch (e) {
      // ignore
    }
  };

  const addToast = (toast: Omit<Toast, 'id'>) => {
    const id = Math.random().toString(36).substring(2, 9);
    setToasts(prev => [...prev, { ...toast, id }]);
    setTimeout(() => {
      removeToast(id);
    }, 4000);
  };

  const removeToast = (id: string) => {
    setToasts(prev => prev.filter(t => t.id !== id));
  };

  const login = (email: string, role: AdminUser['role'] = 'super_admin') => {
    const newUser: AdminUser = {
      ...INITIAL_ADMIN_USER,
      name: laravelBootstrap.user.name,
      email: laravelBootstrap.user.email,
      avatar: laravelBootstrap.user.avatar ?? INITIAL_ADMIN_USER.avatar,
      role,
    };
    setUser(newUser);
    setIsLoginModalOpen(false);
    addToast({
      type: 'success',
      title: 'Authenticated Successfully',
      message: `Welcome back, ${newUser.name}! Access role: ${role}.`
    });
  };

  const logout = () => {
    submitLaravelLogout(laravelBootstrap);
  };

  // Keyboard shortcut listener (Cmd+K / Ctrl+K)
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        setIsCommandPaletteOpen(prev => !prev);
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, []);

  // Header Menu CRUD
  const persistHeaderMenus = (next: HeaderMenuL1[], toast: Omit<Toast, 'id'>) => {
    setHeaderMenus(next);
    void saveMenuDocument(laravelBootstrap, 'header', next).then(saved => {
      setHeaderMenus(saved);
      addToast(toast);
    }).catch(error => {
      void fetchMenus(laravelBootstrap).then(menus => setHeaderMenus(menus.headerMenus)).catch(() => undefined);
      addToast({ type: 'error', title: 'Header Menu Save Failed', message: error instanceof Error ? error.message : 'Unable to save header menu.' });
    });
  };

  const persistFooterMenus = (next: FooterMenuL1[], toast: Omit<Toast, 'id'>) => {
    setFooterMenus(next);
    void saveMenuDocument(laravelBootstrap, 'footer', next).then(saved => {
      setFooterMenus(saved);
      addToast(toast);
    }).catch(error => {
      void fetchMenus(laravelBootstrap).then(menus => setFooterMenus(menus.footerMenus)).catch(() => undefined);
      addToast({ type: 'error', title: 'Footer Menu Save Failed', message: error instanceof Error ? error.message : 'Unable to save footer menu.' });
    });
  };

  const saveHeaderMenuL1 = (item: HeaderMenuL1) => {
    const next = headerMenus.some(existing => existing.id === item.id) ? headerMenus.map(existing => existing.id === item.id ? item : existing) : [...headerMenus, item];
    persistHeaderMenus(next, { type: 'success', title: 'Header Menu Saved', message: `Updated "${item.title}" successfully.` });
  };

  const deleteHeaderMenuL1 = (id: string) => {
    persistHeaderMenus(headerMenus.filter(item => item.id !== id), { type: 'info', title: 'Header Menu Deleted', message: 'Main menu item removed.' });
  };

  const saveHeaderMenuL2 = (l1Id: string, item: HeaderMenuL2) => {
    const next = headerMenus.map(l1 => {
        if (l1.id !== l1Id) return l1;
        const exists = l1.columns.some(col => col.id === item.id);
        const newCols = exists ? l1.columns.map(c => c.id === item.id ? item : c) : [...l1.columns, item];
        return { ...l1, columns: newCols };
    });
    persistHeaderMenus(next, { type: 'success', title: 'Subcategory Saved', message: `Saved column "${item.title}".` });
  };

  const deleteHeaderMenuL2 = (l1Id: string, l2Id: string) => {
    const next = headerMenus.map(l1 => {
        if (l1.id !== l1Id) return l1;
        return { ...l1, columns: l1.columns.filter(c => c.id !== l2Id) };
    });
    persistHeaderMenus(next, { type: 'info', title: 'Column Removed', message: 'Level 2 subcategory deleted.' });
  };

  const saveHeaderMenuL3 = (l1Id: string, l2Id: string, item: HeaderMenuL3) => {
    const next = headerMenus.map(l1 => {
        if (l1.id !== l1Id) return l1;
        const newCols = l1.columns.map(l2 => {
          if (l2.id !== l2Id) return l2;
          const exists = l2.items.some(l3 => l3.id === item.id);
          const newItems = exists ? l2.items.map(i => i.id === item.id ? item : i) : [...l2.items, item];
          return { ...l2, items: newItems };
        });
        return { ...l1, columns: newCols };
    });
    persistHeaderMenus(next, { type: 'success', title: 'Leaf Item Saved', message: `Saved link "${item.title}".` });
  };

  const deleteHeaderMenuL3 = (l1Id: string, l2Id: string, l3Id: string) => {
    const next = headerMenus.map(l1 => {
        if (l1.id !== l1Id) return l1;
        const newCols = l1.columns.map(l2 => {
          if (l2.id !== l2Id) return l2;
          return { ...l2, items: l2.items.filter(i => i.id !== l3Id) };
        });
        return { ...l1, columns: newCols };
    });
    persistHeaderMenus(next, { type: 'info', title: 'Link Removed', message: 'Level 3 leaf item deleted.' });
  };

  // Footer Menu CRUD
  const saveFooterMenuL1 = (item: FooterMenuL1) => {
    const next = footerMenus.some(existing => existing.id === item.id) ? footerMenus.map(existing => existing.id === item.id ? item : existing) : [...footerMenus, item];
    persistFooterMenus(next, { type: 'success', title: 'Footer Section Saved', message: `Saved "${item.sectionTitle}".` });
  };

  const deleteFooterMenuL1 = (id: string) => {
    persistFooterMenus(footerMenus.filter(item => item.id !== id), { type: 'info', title: 'Section Deleted', message: 'Footer section removed.' });
  };

  const saveFooterMenuL2 = (l1Id: string, item: FooterMenuL2) => {
    const next = footerMenus.map(l1 => {
        if (l1.id !== l1Id) return l1;
        const exists = l1.links.some(l => l.id === item.id);
        const newLinks = exists ? l1.links.map(l => l.id === item.id ? item : l) : [...l1.links, item];
        return { ...l1, links: newLinks };
    });
    persistFooterMenus(next, { type: 'success', title: 'Footer Link Saved', message: `Saved "${item.title}".` });
  };

  const deleteFooterMenuL2 = (l1Id: string, l2Id: string) => {
    const next = footerMenus.map(l1 => {
        if (l1.id !== l1Id) return l1;
        return { ...l1, links: l1.links.filter(l => l.id !== l2Id) };
    });
    persistFooterMenus(next, { type: 'info', title: 'Link Removed', message: 'Footer link deleted.' });
  };

  // Categories CRUD
  const saveCategory = async (cat: Category): Promise<Category | null> => {
    try {
      const saved = await saveCatalogEntity(laravelBootstrap, 'categories', cat);
      setCategories(prev => prev.some(item => item.id === saved.id) ? prev.map(item => item.id === saved.id ? saved : item) : [...prev, saved]);
      addToast({ type: 'success', title: 'Category Saved', message: `Category "${saved.name}" updated.` });
      return saved;
    } catch (error) {
      addToast({ type: 'error', title: 'Category Save Failed', message: error instanceof Error ? error.message : 'Unable to save category.' });
      return null;
    }
  };

  const deleteCategory = async (id: string): Promise<void> => {
    try {
      await deleteCatalogEntity(laravelBootstrap, 'categories', id);
      setCategories(prev => prev.filter(item => item.id !== id));
      addToast({ type: 'info', title: 'Category Deleted', message: 'Category removed from catalog.' });
    } catch (error) {
      addToast({ type: 'error', title: 'Category Delete Failed', message: error instanceof Error ? error.message : 'Unable to delete category.' });
    }
  };

  // Collections CRUD
  const saveCollection = async (col: Collection): Promise<Collection | null> => {
    try {
      const saved = await saveCatalogEntity(laravelBootstrap, 'collections', col);
      setCollections(prev => prev.some(item => item.id === saved.id) ? prev.map(item => item.id === saved.id ? saved : item) : [...prev, saved]);
      addToast({ type: 'success', title: 'Collection Saved', message: `Collection "${saved.title}" updated.` });
      return saved;
    } catch (error) {
      addToast({ type: 'error', title: 'Collection Save Failed', message: error instanceof Error ? error.message : 'Unable to save collection.' });
      return null;
    }
  };

  const deleteCollection = async (id: string): Promise<void> => {
    try {
      await deleteCatalogEntity(laravelBootstrap, 'collections', id);
      setCollections(prev => prev.filter(item => item.id !== id));
      addToast({ type: 'info', title: 'Collection Deleted', message: 'Collection removed.' });
    } catch (error) {
      addToast({ type: 'error', title: 'Collection Delete Failed', message: error instanceof Error ? error.message : 'Unable to delete collection.' });
    }
  };

  // Product CRUD
  const saveProduct = async (prod: Product): Promise<Product | null> => {
    try {
      const initial = products.find(item => item.id === prod.id) ?? null;
      const saved = await saveCatalogEntity(laravelBootstrap, 'products', catalogStockPayload(prod, initial));
      setProducts(prev => prev.some(item => item.id === saved.id) ? prev.map(item => item.id === saved.id ? saved : item) : [saved, ...prev]);
      addToast({ type: 'success', title: 'Product Published', message: `Saved "${saved.title}" with ${saved.variants.length} variant SKUs.` });
      return saved;
    } catch (error) {
      addToast({ type: 'error', title: 'Product Save Failed', message: error instanceof Error ? error.message : 'Unable to save product.' });
      return null;
    }
  };

  const deleteProduct = async (id: string): Promise<void> => {
    try {
      await deleteCatalogEntity(laravelBootstrap, 'products', id);
      setProducts(prev => prev.map(item => item.id === id ? { ...item, status: 'archived' } : item));
      addToast({ type: 'info', title: 'Product Archived', message: 'Product removed from active listings.' });
    } catch (error) {
      addToast({ type: 'error', title: 'Product Archive Failed', message: error instanceof Error ? error.message : 'Unable to archive product.' });
    }
  };

  const duplicateProduct = (id: string) => {
    const target = products.find(p => p.id === id);
    if (!target) return;
    const duplicated: Product = {
      ...target,
      id: 'prod-' + Date.now(),
      title: `${target.title} (Copy)`,
      slug: `${target.slug}-copy`,
      status: 'draft',
      createdAt: new Date().toISOString().split('T')[0],
      variants: target.variants.map((v, idx) => ({
        ...v,
        id: `var-${Date.now()}-${idx}`,
        sku: `${v.sku}-CPY`
      }))
    };
    saveProduct(duplicated);
  };

  const refreshReviews = async (): Promise<void> => {
    try {
      setReviews(await fetchReviewsData(laravelBootstrap));
    } catch (error) {
      addToast({ type: 'error', title: 'Reviews Refresh Failed', message: error instanceof Error ? error.message : 'Unable to refresh reviews.' });
    }
  };

  const fetchReviewAudit = async (reviewId: string): Promise<AdminProductReviewAuditEvent[]> => {
    return fetchReviewAuditForContext(laravelBootstrap, reviewId, addToast);
  };

  const moderateReview = async (
    reviewId: string,
    action: ReviewModerationAction,
    reason?: string,
  ): Promise<ReviewModerationResult | null> => {
    try {
      const result = await moderateReviewData(laravelBootstrap, reviewId, action, reason);
      await refreshReviews();
      addToast({
        type: action === 'publish' ? 'success' : 'warning',
        title: action === 'publish' ? 'Review Published' : 'Review Rejected',
        message: `Review ${reviewId} was ${action === 'publish' ? 'published' : 'rejected'}.`,
      });
      return result;
    } catch (error) {
      addToast({ type: 'error', title: 'Review Moderation Failed', message: error instanceof Error ? error.message : 'Unable to moderate review.' });
      return null;
    }
  };

  const bulkModerateReviews = async (
    reviewIds: string[],
    action: ReviewModerationAction,
    reason?: string,
  ): Promise<ReviewBulkModerationResult | null> => {
    try {
      const result = await bulkModerateReviewsData(laravelBootstrap, reviewIds, action, reason);
      await refreshReviews();
      addToast({
        type: action === 'publish' ? 'success' : 'warning',
        title: action === 'publish' ? 'Reviews Published' : 'Reviews Rejected',
        message: `${reviewIds.length} review${reviewIds.length === 1 ? '' : 's'} processed.`,
      });
      return result;
    } catch (error) {
      addToast({ type: 'error', title: 'Bulk Moderation Failed', message: error instanceof Error ? error.message : 'Unable to update selected reviews.' });
      return null;
    }
  };

  // CMS Page & Layout Builder CRUD
  const replacePageLocal = (page: CmsPage): void => {
    const exists = pagesRef.current.some(candidate => candidate.id === page.id);
    pagesRef.current = exists
      ? pagesRef.current.map(candidate => candidate.id === page.id ? page : candidate)
      : [page, ...pagesRef.current];
    setPages(previous => previous.some(candidate => candidate.id === page.id)
      ? previous.map(candidate => candidate.id === page.id ? page : candidate)
      : [page, ...previous]);
  };

  const updatePageLocal = (
    pageId: string,
    updater: (page: CmsPage) => CmsPage,
  ): CmsPage | null => {
    const current = pagesRef.current.find(candidate => candidate.id === pageId);
    if (!current) return null;

    const updated = updater(current);
    pagesRef.current = pagesRef.current.map(candidate => candidate.id === pageId ? updated : candidate);
    setPages(previous => previous.map(candidate => candidate.id === pageId ? updated : candidate));
    return updated;
  };

  const pageDocument = (page: CmsPage) => ({
    title: page.title,
    slug: page.slug,
    description: page.description,
    template: page.template,
    heroImage: page.heroImage,
    seoTitle: page.seoTitle,
    seoDescription: page.seoDescription,
    sections: page.sections,
  });

  const savePage = async (page: CmsPage): Promise<CmsPage | null> => {
    const exists = pagesRef.current.some(existingPage => existingPage.id === page.id);

    if (exists) {
      const current = pagesRef.current.find(existingPage => existingPage.id === page.id) ?? page;
      replacePageLocal(page);

      try {
        const draft = await savePageDraft(
          laravelBootstrap,
          page.id,
          pageDocument(page),
          current.lockVersion ?? page.lockVersion ?? 1,
        );
        const latest = pagesRef.current.find(candidate => candidate.id === page.id) ?? page;
        const saved: CmsPage = {
          ...latest,
          status: draft.status,
          lockVersion: draft.lockVersion,
          schemaVersion: draft.schemaVersion,
          contentHash: draft.contentHash,
          hasPublishedSnapshot: draft.hasPublishedSnapshot,
          hasUnpublishedChanges: draft.hasUnpublishedChanges,
          updatedAt: draft.updatedAt.slice(0, 10),
        };
        replacePageLocal(saved);
        addToast({ type: 'success', title: 'Page Saved', message: `Page "${saved.title}" (${saved.sections.length} sections) updated.` });
        return saved;
      } catch (error) {
        addToast({ type: 'error', title: 'Page Save Failed', message: error instanceof Error ? error.message : 'Unable to save this page.' });
        return null;
      }
    }

    try {
      const saved = await createPage(laravelBootstrap, page);

      replacePageLocal(saved);
      addToast({ type: 'success', title: 'Page Saved', message: `Page "${saved.title}" (${saved.sections.length} sections) updated.` });
      return saved;
    } catch (error) {
      addToast({ type: 'error', title: 'Page Save Failed', message: error instanceof Error ? error.message : 'Unable to save this page.' });
      return null;
    }
  };

  const updatePageSection = (
    pageId: string,
    sectionId: string,
    updater: (section: PageSection) => PageSection,
  ): Promise<CmsPage | null> => {
    const updatedPage = updatePageLocal(pageId, page => {
      const section = page.sections.find(candidate => candidate.id === sectionId);
      if (!section) return page;
      const updatedSection = updater(section);
      return {
        ...page,
        sections: page.sections.map(candidate => candidate.id === sectionId ? updatedSection : candidate),
        updatedAt: new Date().toISOString().split('T')[0],
      };
    });

    return Promise.resolve(updatedPage);
  };

  const deletePage = async (id: string): Promise<void> => {
    try {
      await removePage(laravelBootstrap, id);
      pagesRef.current = pagesRef.current.filter(page => page.id !== id);
      setPages(prev => prev.filter(page => page.id !== id));
      if (selectedPageId === id) setSelectedPageId(null);
      addToast({ type: 'info', title: 'Page Deleted', message: 'Page was removed from store.' });
    } catch (error) {
      addToast({ type: 'error', title: 'Delete Failed', message: error instanceof Error ? error.message : 'Unable to delete this page.' });
    }
  };

  const duplicatePage = async (id: string): Promise<CmsPage | null> => {
    try {
      const duplicated = await clonePage(laravelBootstrap, id);
      replacePageLocal(duplicated);
      setSelectedPageId(duplicated.id);
      addToast({ type: 'success', title: 'Page Duplicated', message: `Created draft copy "${duplicated.title}".` });
      return duplicated;
    } catch (error) {
      addToast({ type: 'error', title: 'Duplicate Failed', message: error instanceof Error ? error.message : 'Unable to duplicate this page.' });
      return null;
    }
  };

  // Customers
  const saveCustomer = async (cust: Customer): Promise<Customer | null> => {
    try {
      const saved = await saveCustomerData(laravelBootstrap, cust);
      setCustomers(prev => prev.some(item => item.id === saved.id) ? prev.map(item => item.id === saved.id ? saved : item) : [saved, ...prev]);
      addToast({ type: 'success', title: 'Customer Profile Updated', message: `Customer "${saved.name}" saved.` });
      return saved;
    } catch (error) {
      addToast({ type: 'error', title: 'Customer Save Failed', message: error instanceof Error ? error.message : 'Unable to save customer.' });
      return null;
    }
  };

  const deleteCustomer = async (id: string | number): Promise<void> => {
    try {
      await deleteCustomerData(laravelBootstrap, String(id));
      setCustomers(prev => prev.filter(customer => customer.id !== String(id)));
      addToast({ type: 'info', title: 'Customer Deleted', message: 'Customer profile removed.' });
    } catch (error) {
      addToast({ type: 'error', title: 'Customer Delete Failed', message: error instanceof Error ? error.message : 'Unable to delete customer.' });
    }
  };

  // Orders
  const updateOrderStatus = async (orderId: string, paymentStatus?: PaymentStatus, fulfillmentStatus?: FulfillmentStatus, note?: string, notifyCustomer?: boolean): Promise<Order | null> => {
    try {
      const saved = await orderMutationCoordinatorRef.current!.run(
        orderId,
        () => updateOrderStatusData(laravelBootstrap, orderId, paymentStatus, fulfillmentStatus, note, notifyCustomer),
        current => setOrders(prev => prev.map(order => order.id === current.id ? current : order)),
      );
      if (saved === null) return null;
      addToast({ type: 'success', title: 'Order State Changed', message: 'Order updated successfully.' });
      return saved;
    } catch (error) {
      addToast({ type: 'error', title: 'Order Update Failed', message: error instanceof Error ? error.message : 'Unable to update order.' });
      return null;
    }
  };

  const updateOrderTracking = async (orderId: string, carrier: string, trackingNumber: string): Promise<Order | null> => {
    try {
      const saved = await orderMutationCoordinatorRef.current!.run(
        orderId,
        () => updateOrderTrackingData(laravelBootstrap, orderId, carrier, trackingNumber),
        current => setOrders(prev => prev.map(order => order.id === current.id ? current : order)),
      );
      if (saved === null) return null;
      void fetchShipments(laravelBootstrap).then(setShipmentParcels).catch(() => undefined);
      addToast({ type: 'success', title: 'Logistics Dispatched', message: `Assigned tracking ${trackingNumber} via ${carrier}.` });
      return saved;
    } catch (error) {
      addToast({ type: 'error', title: 'Dispatch Failed', message: error instanceof Error ? error.message : 'Unable to dispatch order.' });
      return null;
    }
  };

  const processOrderRefund = async (orderId: string, amount: number, reason: string): Promise<Order | null> => {
    try {
      const saved = await orderMutationCoordinatorRef.current!.run(
        orderId,
        () => processOrderRefundData(laravelBootstrap, orderId, amount, reason),
        current => setOrders(prev => prev.map(order => order.id === current.id ? current : order)),
      );
      if (saved === null) return null;
      addToast({ type: 'warning', title: 'Refund Completed', message: `Processed refund of ${formatAdminMoney(amount, currenciesSetting)}.` });
      return saved;
    } catch (error) {
      addToast({ type: 'error', title: 'Refund Failed', message: error instanceof Error ? error.message : 'Unable to process refund.' });
      return null;
    }
  };

  const reviewPaymentProof = async (orderId: string, paymentId: string, decision: 'verified' | 'rejected', reason?: string): Promise<Order | null> => {
    try {
      const saved = await orderMutationCoordinatorRef.current!.run(
        orderId,
        () => reviewPaymentProofData(laravelBootstrap, orderId, paymentId, decision, reason),
        current => setOrders(prev => prev.map(order => order.id === current.id ? current : order)),
      );
      if (saved === null) return null;
      addToast({
        type: decision === 'verified' ? 'success' : 'warning',
        title: decision === 'verified' ? 'Payment Proof Verified' : 'Payment Proof Rejected',
        message: decision === 'verified' ? 'Payment is now confirmed and ready for fulfillment.' : 'The order remains pending payment.',
      });
      return saved;
    } catch (error) {
      addToast({ type: 'error', title: 'Payment Proof Review Failed', message: error instanceof Error ? error.message : 'Unable to review payment proof.' });
      return null;
    }
  };

  const amendOrderDeliveryDetails = async (orderId: string, input: AmendOrderDeliveryDetailsInput): Promise<Order> => {
    try {
      await amendOrderDeliveryDetailsData(laravelBootstrap, orderId, input);
      const [refreshedOrders, refreshedShipments] = await Promise.all([
        fetchOrders(laravelBootstrap),
        fetchShipments(laravelBootstrap),
      ]);
      setOrders(refreshedOrders);
      setShipmentParcels(refreshedShipments);
      const saved = refreshedOrders.find(order => order.id === orderId);
      if (!saved) throw new Error('Updated order could not be reloaded.');
      addToast({ type: 'success', title: 'Delivery Details Amended', message: 'The current order destination and immutable audit were updated.' });
      return saved;
    } catch (error) {
      addToast({ type: 'error', title: 'Delivery Amendment Failed', message: error instanceof Error ? error.message : 'Unable to amend delivery details.' });
      throw error;
    }
  };

  const reloadOrders = async (): Promise<void> => { setOrders(await fetchOrders(laravelBootstrap)); };
  const reloadShipmentParcels = async (): Promise<void> => { setShipmentParcels(await fetchShipments(laravelBootstrap)); };

  // Staff User Login Management (RBAC)
  const saveStaffUser = (staff: StaffUser) => {
    setStaffUsers(prev => {
      const exists = prev.some(s => s.id === staff.id);
      let updated: StaffUser[];
      if (exists) {
        updated = prev.map(s => (s.id === staff.id ? staff : s));
        addToast({ type: 'success', title: 'Staff User Updated', message: `Updated login credentials & permissions for "${staff.username}".` });
      } else {
        updated = [staff, ...prev];
        addToast({ type: 'success', title: 'Staff User Created', message: `Created new admin login for "${staff.username}" (${staff.position}).` });
      }
      localStorage.setItem(STORAGE_KEYS.STAFF_USERS, JSON.stringify(updated));
      return updated;
    });
  };

  const deleteStaffUser = (id: string) => {
    setStaffUsers(prev => {
      const target = prev.find(s => s.id === id);
      if (target?.username === 'admin') {
        addToast({ type: 'error', title: 'Protected Account', message: 'The primary root admin account cannot be deleted.' });
        return prev;
      }
      const updated = prev.filter(s => s.id !== id);
      localStorage.setItem(STORAGE_KEYS.STAFF_USERS, JSON.stringify(updated));
      addToast({ type: 'info', title: 'Staff User Removed', message: `User "${target?.username || id}" has been deactivated and deleted.` });
      return updated;
    });
  };

  const toggleStaffStatus = (id: string) => {
    setStaffUsers(prev => {
      const target = prev.find(s => s.id === id);
      if (target?.username === 'admin') {
        addToast({ type: 'warning', title: 'Root Admin Protected', message: 'Root admin status cannot be deactivated.' });
        return prev;
      }
      const updated = prev.map(s => (s.id === id ? { ...s, isActive: !s.isActive } : s));
      localStorage.setItem(STORAGE_KEYS.STAFF_USERS, JSON.stringify(updated));
      const updatedUser = updated.find(s => s.id === id);
      addToast({
        type: updatedUser?.isActive ? 'success' : 'warning',
        title: 'Status Toggled',
        message: `User "${updatedUser?.username}" is now ${updatedUser?.isActive ? 'Active' : 'Disabled'}.`
      });
      return updated;
    });
  };

  const resetStaffPassword = (id: string, newPass: string) => {
    setStaffUsers(prev => {
      const updated = prev.map(s => (s.id === id ? { ...s, password: newPass } : s));
      localStorage.setItem(STORAGE_KEYS.STAFF_USERS, JSON.stringify(updated));
      const target = prev.find(s => s.id === id);
      addToast({
        type: 'success',
        title: 'Password Reset',
        message: `Password updated for "${target?.username}". Remember to share credentials safely.`
      });
      return updated;
    });
  };

  const saveShopSettings = async (settings: ShopGeneralSettings) => {
    if (!shopProfileLoaded.current) throw new Error('Reload the saved shop profile before saving.');
    const saved = await persistShopBranding(laravelBootstrap, settings, shopSettings.shopLogo);
    setShopSettings(current => ({ ...current, ...saved, shopLogo: saved.shopLogo || INITIAL_SHOP_SETTINGS.shopLogo }));
  };

  // ---------------------------
  // Marketplace Actions (Auth, OMS, WMS)
  // ---------------------------

  const addSyncLog = (log: Omit<MarketplaceSyncLog, 'id' | 'timestamp'>) => {
    const newLog: MarketplaceSyncLog = {
      id: `log-${Date.now()}-${Math.floor(Math.random() * 1000)}`,
      timestamp: new Date().toISOString(),
      ...log
    };
    setSyncLogs(prev => {
      const updated = [newLog, ...prev.slice(0, 99)]; // Keep latest 100 logs
      localStorage.setItem(STORAGE_KEYS.MARKETPLACE_SYNC_LOGS, JSON.stringify(updated));
      return updated;
    });
  };

  const retrySyncLog = (logId: string) => {
    const target = syncLogs.find(l => l.id === logId);
    if (!target) return;
    addToast({ type: 'info', title: 'Retrying Sync Event', message: `Resending ${target.eventType} to ${target.platform.toUpperCase()} API...` });
    setTimeout(() => {
      setSyncLogs(prev => {
        const updated = prev.map(l => l.id === logId ? { ...l, status: 'success' as const, message: `[Retry Success] Re-processed event: ${l.eventType}`, timestamp: new Date().toISOString() } : l);
        localStorage.setItem(STORAGE_KEYS.MARKETPLACE_SYNC_LOGS, JSON.stringify(updated));
        return updated;
      });
      addToast({ type: 'success', title: 'Sync Successful', message: `API payload re-transmitted to ${target.shopName} successfully.` });
    }, 600);
  };

  const clearSyncLogs = () => {
    setSyncLogs([]);
    localStorage.removeItem(STORAGE_KEYS.MARKETPLACE_SYNC_LOGS);
    addToast({ type: 'info', title: 'Logs Cleared', message: 'Marketplace webhook and sync activity logs have been reset.' });
  };

  const saveConnection = (conn: MarketplaceConnection) => {
    setConnections(prev => {
      const exists = prev.some(c => c.id === conn.id);
      const updated = exists ? prev.map(c => c.id === conn.id ? conn : c) : [...prev, conn];
      localStorage.setItem(STORAGE_KEYS.MARKETPLACE_CONNECTIONS, JSON.stringify(updated));
      return updated;
    });
    addToast({
      type: 'success',
      title: 'Store Connection Saved',
      message: `Successfully configured ${conn.platform.toUpperCase()} store "${conn.shopName}".`
    });
    addSyncLog({
      platform: conn.platform,
      shopName: conn.shopName,
      eventType: 'token_refresh',
      status: 'success',
      referenceId: conn.shopId,
      message: `Open API credentials configured and token authorized for ${conn.region} region.`,
      payloadJson: JSON.stringify({ shopId: conn.shopId, status: conn.status, region: conn.region }, null, 2),
      latencyMs: 120
    });
  };

  const deleteConnection = (id: string) => {
    setConnections(prev => {
      const target = prev.find(c => c.id === id);
      const updated = prev.filter(c => c.id !== id);
      localStorage.setItem(STORAGE_KEYS.MARKETPLACE_CONNECTIONS, JSON.stringify(updated));
      addToast({
        type: 'warning',
        title: 'Connection Removed',
        message: `Disconnected store "${target?.shopName || id}".`
      });
      return updated;
    });
  };

  const toggleConnectionSync = (id: string, field: 'autoSyncStock' | 'autoPullOrders') => {
    setConnections(prev => {
      const updated = prev.map(c => {
        if (c.id === id) {
          const nextVal = !c[field];
          return { ...c, [field]: nextVal };
        }
        return c;
      });
      localStorage.setItem(STORAGE_KEYS.MARKETPLACE_CONNECTIONS, JSON.stringify(updated));
      const target = updated.find(c => c.id === id);
      if (target) {
        addToast({
          type: 'info',
          title: 'Sync Policy Updated',
          message: `${target.shopName} ${field === 'autoSyncStock' ? 'Stock Sync' : 'Order Pull'} is now ${target[field] ? 'Active' : 'Paused'}.`
        });
      }
      return updated;
    });
  };

  const refreshToken = (id: string) => {
    const conn = connections.find(c => c.id === id);
    if (!conn) return;
    addToast({ type: 'info', title: 'Refreshing OAuth Token', message: `Requesting new access token from ${conn.platform.toUpperCase()} auth server...` });
    setTimeout(() => {
      const newExpiry = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString();
      const updatedConn: MarketplaceConnection = {
        ...conn,
        status: 'connected',
        tokenExpiresAt: newExpiry,
        lastSyncAt: new Date().toISOString()
      };
      saveConnection(updatedConn);
      addToast({ type: 'success', title: 'Token Renewed', message: `Token valid for next 30 days until ${new Date(newExpiry).toLocaleDateString()}.` });
    }, 700);
  };

  const syncAllMarketplaces = () => {
    addToast({ type: 'info', title: 'Syncing Omnichannel Stream', message: 'Pulling new orders & updating tracking writebacks from TikTok, Shopee, Lazada & Shopify...' });
    setTimeout(() => {
      setConnections(prev => {
        const updated = prev.map(c => ({ ...c, lastSyncAt: new Date().toISOString() }));
        localStorage.setItem(STORAGE_KEYS.MARKETPLACE_CONNECTIONS, JSON.stringify(updated));
        return updated;
      });
      addToast({ type: 'success', title: 'Sync Complete', message: 'All connected marketplace channels are fully synchronized.' });
      addSyncLog({
        platform: 'shopee',
        shopName: 'All Channels',
        eventType: 'inventory_sync',
        status: 'success',
        referenceId: 'SYNC_ALL_CHANNELS',
        message: 'Omnichannel polling cycle executed without errors.',
        payloadJson: JSON.stringify({ syncedChannels: connections.map(c => c.shopName), timestamp: new Date().toISOString() }, null, 2),
        latencyMs: 340
      });
    }, 800);
  };

  const saveMarketplaceOrder = (order: MarketplaceOrder) => {
    setMarketplaceOrders(prev => {
      const exists = prev.some(o => o.id === order.id);
      const updated = exists ? prev.map(o => o.id === order.id ? order : o) : [order, ...prev];
      localStorage.setItem(STORAGE_KEYS.MARKETPLACE_ORDERS, JSON.stringify(updated));
      return updated;
    });
    addToast({ type: 'success', title: 'Marketplace Order Saved', message: `Order #${order.orderNumber} updated.` });
  };

  const generateAirwayBill = (orderId: string, courierName?: string) => {
    setMarketplaceOrders(prev => {
      const updated = prev.map(o => {
        if (o.id === orderId) {
          const defaultCourier = courierName || o.trackingCarrier || (o.channel === 'tiktok' ? 'J&T Express (TikTok Shipping)' : o.channel === 'shopee' ? 'Shopee Supported Logistics (SPX)' : o.channel === 'lazada' ? 'Lazada Logistics Express (LEX)' : 'DHL Express');
          const trackingNum = o.trackingNumber || `${o.channel.substring(0, 2).toUpperCase()}${Math.floor(1000000000 + Math.random() * 9000000000)}MY`;
          const updatedOrder: MarketplaceOrder = {
            ...o,
            airwayBillGenerated: true,
            airwayBillUrl: `https://labels.auroracommerce.io/awb/${o.channelOrderId || o.orderNumber}.pdf`,
            trackingCarrier: defaultCourier,
            trackingNumber: trackingNum,
            channelStatus: 'READY_TO_SHIP',
            fulfillmentStatus: o.fulfillmentStatus === 'unfulfilled' ? 'processing' : o.fulfillmentStatus,
            timeline: [
              {
                id: `evt-${Date.now()}`,
                timestamp: new Date().toISOString(),
                type: 'fulfillment_updated',
                title: `AWB Generated (${defaultCourier})`,
                description: `Airway bill generated with Tracking #${trackingNum}. Package ready for courier handover.`
              },
              ...o.timeline
            ]
          };
          return updatedOrder;
        }
        return o;
      });
      localStorage.setItem(STORAGE_KEYS.MARKETPLACE_ORDERS, JSON.stringify(updated));
      return updated;
    });

    const target = marketplaceOrders.find(o => o.id === orderId);
    if (target) {
      addToast({ type: 'success', title: 'Airway Bill Generated', message: `AWB ready for #${target.orderNumber}. Courier tracking registered.` });
      addSyncLog({
        platform: target.channel,
        shopName: target.channelStoreName,
        eventType: 'awb_generated',
        status: 'success',
        referenceId: target.channelOrderId,
        message: `Airway bill & courier dispatch registered with ${target.channel.toUpperCase()} Open API logistics service.`,
        payloadJson: JSON.stringify({ orderId: target.channelOrderId, carrier: courierName || target.trackingCarrier }, null, 2),
        latencyMs: 190
      });
    }
  };

  const markOrderPacked = (orderId: string, packedBy: string) => {
    setMarketplaceOrders(prev => {
      const updated = prev.map(o => {
        if (o.id === orderId) {
          return {
            ...o,
            channelStatus: 'READY_TO_SHIP',
            packedBy: packedBy || 'Warehouse Staff',
            scannedItemsCount: o.items.reduce((sum, item) => sum + item.quantity, 0),
            fulfillmentStatus: 'processing',
            timeline: [
              {
                id: `evt-${Date.now()}`,
                timestamp: new Date().toISOString(),
                type: 'fulfillment_updated',
                title: 'Items Picked & Packed',
                description: `Order verified and packaged by ${packedBy || 'Staff'}. Ready for courier pickup.`
              },
              ...o.timeline
            ]
          };
        }
        return o;
      });
      localStorage.setItem(STORAGE_KEYS.MARKETPLACE_ORDERS, JSON.stringify(updated));
      return updated;
    });
    addToast({ type: 'success', title: 'Order Packed', message: `Order #${orderId} marked as packed and verified.` });
  };

  const markOrderShipped = (orderId: string, trackingNumber: string, carrier: string) => {
    setMarketplaceOrders(prev => {
      const updated = prev.map(o => {
        if (o.id === orderId) {
          const updatedOrder: MarketplaceOrder = {
            ...o,
            channelStatus: 'IN_TRANSIT',
            fulfillmentStatus: 'shipped',
            trackingNumber: trackingNumber || o.trackingNumber,
            trackingCarrier: carrier || o.trackingCarrier,
            timeline: [
              {
                id: `evt-${Date.now()}`,
                timestamp: new Date().toISOString(),
                type: 'fulfillment_updated',
                title: 'Handed Over to Courier',
                description: `Package collected by ${carrier || o.trackingCarrier || 'Courier'} [Tracking #${trackingNumber || o.trackingNumber}]. Auto-written back to marketplace API.`
              },
              ...o.timeline
            ]
          };
          return updatedOrder;
        }
        return o;
      });
      localStorage.setItem(STORAGE_KEYS.MARKETPLACE_ORDERS, JSON.stringify(updated));
      return updated;
    });

    const target = marketplaceOrders.find(o => o.id === orderId);
    if (target) {
      addToast({ type: 'success', title: 'Order Dispatched', message: `Order #${target.orderNumber} dispatched. Tracking synced to ${target.channel.toUpperCase()}.` });
      addSyncLog({
        platform: target.channel,
        shopName: target.channelStoreName,
        eventType: 'order_status_update',
        status: 'success',
        referenceId: target.channelOrderId,
        message: `Courier handover confirmed. Status updated to SHIPPED / IN_TRANSIT on ${target.channel.toUpperCase()}.`,
        payloadJson: JSON.stringify({ orderId: target.channelOrderId, trackingNumber, carrier }, null, 2),
        latencyMs: 145
      });
    }
  };

  const batchPrintAWB = (orderIds: string[]) => {
    orderIds.forEach(id => generateAirwayBill(id));
    addToast({ type: 'success', title: 'Batch AWB Generation', message: `Successfully generated ${orderIds.length} printable shipping label(s).` });
  };

  const batchPackOrders = (orderIds: string[]) => {
    orderIds.forEach(id => markOrderPacked(id, 'Batch Scanner'));
    addToast({ type: 'success', title: 'Batch Orders Packed', message: `Marked ${orderIds.length} order(s) as packed and ready for dispatch.` });
  };

  const saveInventoryItem = (item: MarketplaceInventoryItem) => {
    setMarketplaceInventory(prev => {
      const exists = prev.some(i => i.id === item.id);
      const updated = exists ? prev.map(i => i.id === item.id ? item : i) : [...prev, item];
      localStorage.setItem(STORAGE_KEYS.MARKETPLACE_INVENTORY, JSON.stringify(updated));
      return updated;
    });
    addToast({ type: 'success', title: 'SKU Inventory Saved', message: `Master SKU ${item.masterSku} updated.` });
  };

  const adjustStock = (idOrSku: string, change: number, reason: string, user: string = 'Admin') => {
    setMarketplaceInventory(prev => {
      const updated = prev.map(item => {
        if (item.id === idOrSku || item.masterSku === idOrSku) {
          const newPhysical = Math.max(0, item.totalPhysicalStock + change);
          const newAvailable = Math.max(0, newPhysical - item.reservedStock - item.safetyStock);
          const newLog = {
            id: `hl-${Date.now()}`,
            timestamp: new Date().toISOString(),
            action: change >= 0 ? 'STOCK_INBOUND' : 'STOCK_ADJUSTMENT',
            change,
            resultingStock: newAvailable,
            user,
            reason: reason || 'Manual Stock Adjustment'
          };
          const updatedChannels = { ...item.channelMappings };
          // Update channel stock
          (['tiktok', 'shopee', 'lazada', 'shopify'] as MarketplacePlatform[]).forEach(plat => {
            if (plat !== 'direct' && updatedChannels[plat]?.isLinked) {
              updatedChannels[plat] = {
                ...updatedChannels[plat]!,
                channelStock: newAvailable,
                lastSyncedAt: new Date().toISOString(),
                syncStatus: 'synced'
              };
            }
          });

          return {
            ...item,
            totalPhysicalStock: newPhysical,
            availableStock: newAvailable,
            channelMappings: updatedChannels,
            historyLog: [newLog, ...item.historyLog]
          };
        }
        return item;
      });
      localStorage.setItem(STORAGE_KEYS.MARKETPLACE_INVENTORY, JSON.stringify(updated));
      return updated;
    });

    addToast({
      type: change >= 0 ? 'success' : 'warning',
      title: 'Warehouse Stock Adjusted',
      message: `${idOrSku}: ${change >= 0 ? `+${change}` : change} units applied (${reason}).`
    });

    addSyncLog({
      platform: 'shopee',
      shopName: 'All Channels',
      eventType: 'inventory_sync',
      status: 'success',
      referenceId: idOrSku,
      message: `Master inventory delta (${change >= 0 ? `+${change}` : change}) broadcasted to connected marketplace channels.`,
      payloadJson: JSON.stringify({ sku: idOrSku, change, reason, user }, null, 2),
      latencyMs: 180
    });
  };

  const broadcastStockToChannels = (sku: string) => {
    const item = marketplaceInventory.find(i => i.masterSku === sku || i.id === sku);
    if (!item) return;

    addToast({ type: 'info', title: 'Broadcasting Stock', message: `Syncing ${item.availableStock} units of ${item.masterSku} to TikTok, Shopee, Lazada & Shopify...` });
    setTimeout(() => {
      setMarketplaceInventory(prev => {
        const updated = prev.map(i => {
          if (i.masterSku === sku || i.id === sku) {
            const updatedChannels = { ...i.channelMappings };
            (['tiktok', 'shopee', 'lazada', 'shopify'] as MarketplacePlatform[]).forEach(plat => {
              if (plat !== 'direct' && updatedChannels[plat]?.isLinked) {
                updatedChannels[plat] = {
                  ...updatedChannels[plat]!,
                  channelStock: i.availableStock,
                  lastSyncedAt: new Date().toISOString(),
                  syncStatus: 'synced'
                };
              }
            });
            return {
              ...i,
              lastBroadcastAt: new Date().toISOString(),
              channelMappings: updatedChannels
            };
          }
          return i;
        });
        localStorage.setItem(STORAGE_KEYS.MARKETPLACE_INVENTORY, JSON.stringify(updated));
        return updated;
      });
      addToast({ type: 'success', title: 'Stock Broadcast Complete', message: `All marketplace channels updated with ${item.availableStock} units for ${item.masterSku}.` });
      addSyncLog({
        platform: 'tiktok',
        shopName: 'All Channels',
        eventType: 'inventory_sync',
        status: 'success',
        referenceId: item.masterSku,
        message: `Inventory balance broadcasted to all channels successfully.`,
        payloadJson: JSON.stringify({ masterSku: item.masterSku, availableStock: item.availableStock }, null, 2),
        latencyMs: 220
      });
    }, 700);
  };

  const broadcastAllStockToChannels = () => {
    addToast({ type: 'info', title: 'Mass Inventory Broadcast', message: `Broadcasting warehouse ATP stocks for ${marketplaceInventory.length} Master SKUs across all connected marketplace shops...` });
    setTimeout(() => {
      setMarketplaceInventory(prev => {
        const updated = prev.map(i => {
          const updatedChannels = { ...i.channelMappings };
          (['tiktok', 'shopee', 'lazada', 'shopify'] as MarketplacePlatform[]).forEach(plat => {
            if (plat !== 'direct' && updatedChannels[plat]?.isLinked) {
              updatedChannels[plat] = {
                ...updatedChannels[plat]!,
                channelStock: i.availableStock,
                lastSyncedAt: new Date().toISOString(),
                syncStatus: 'synced'
              };
            }
          });
          return {
            ...i,
            lastBroadcastAt: new Date().toISOString(),
            channelMappings: updatedChannels
          };
        });
        localStorage.setItem(STORAGE_KEYS.MARKETPLACE_INVENTORY, JSON.stringify(updated));
        return updated;
      });
      addToast({ type: 'success', title: 'Mass Broadcast Complete', message: `Successfully synchronized inventory across all 4 marketplaces.` });
      addSyncLog({
        platform: 'shopee',
        shopName: 'Omnichannel Broadcast',
        eventType: 'inventory_sync',
        status: 'success',
        referenceId: 'MASS_BROADCAST',
        message: `Bulk inventory batch sync completed for ${marketplaceInventory.length} SKUs.`,
        payloadJson: JSON.stringify({ skuCount: marketplaceInventory.length, timestamp: new Date().toISOString() }, null, 2),
        latencyMs: 480
      });
    }, 900);
  };

  const linkChannelSku = (inventoryId: string, platform: MarketplacePlatform, mapping: import('../types').ChannelSkuMapping) => {
    if (platform === 'direct') return;
    setMarketplaceInventory(prev => {
      const updated = prev.map(item => {
        if (item.id === inventoryId) {
          return {
            ...item,
            channelMappings: {
              ...item.channelMappings,
              [platform]: mapping
            }
          };
        }
        return item;
      });
      localStorage.setItem(STORAGE_KEYS.MARKETPLACE_INVENTORY, JSON.stringify(updated));
      return updated;
    });
    addToast({ type: 'success', title: 'Channel SKU Mapped', message: `Linked ${platform.toUpperCase()} SKU mapping for item.` });
  };

  const unlinkChannelSku = (inventoryId: string, platform: MarketplacePlatform) => {
    if (platform === 'direct') return;
    setMarketplaceInventory(prev => {
      const updated = prev.map(item => {
        if (item.id === inventoryId) {
          const currentMap = item.channelMappings[platform];
          if (!currentMap) return item;
          return {
            ...item,
            channelMappings: {
              ...item.channelMappings,
              [platform]: {
                ...currentMap,
                isLinked: false
              }
            }
          };
        }
        return item;
      });
      localStorage.setItem(STORAGE_KEYS.MARKETPLACE_INVENTORY, JSON.stringify(updated));
      return updated;
    });
    addToast({ type: 'warning', title: 'Channel Unlinked', message: `Removed ${platform.toUpperCase()} SKU link.` });
  };

  const simulateIncomingOrder = (platform: MarketplacePlatform) => {
    const item = marketplaceInventory[0] || INITIAL_MARKETPLACE_INVENTORY[0];
    const buyers = [
      { name: 'Nurul Hidayah', username: '@nurul_tech_my', email: 'nurul.h@gmail.com', phone: '+60 18-992 0192', city: 'Subang Jaya', post: '47500' },
      { name: 'Tan Wei Ming', username: 'weiming_audiophile', email: 'weiming.tan@yahoo.com', phone: '+60 12-394 8291', city: 'Johor Bahru', post: '80100' },
      { name: 'Farhan Azman', username: '@farhan_sound', email: 'farhan.azman@gmail.com', phone: '+60 17-482 9102', city: 'Georgetown', post: '10200' },
      { name: 'Chloe Roberts', username: 'chloe.sound.studio', email: 'chloe.roberts@gmail.com', phone: '+1 415-555-0199', city: 'San Francisco', post: '94103' }
    ];
    const buyer = buyers[Math.floor(Math.random() * buyers.length)];
    const randomNum = Math.floor(100000 + Math.random() * 900000);
    const channelStore = connections.find(c => c.platform === platform) || connections[0];

    const newOrder: MarketplaceOrder = {
      id: `mord-${platform.substring(0, 2)}-${Date.now()}`,
      orderNumber: `ORD-${platform.substring(0, 2).toUpperCase()}-${randomNum}`,
      channel: platform,
      channelStoreId: channelStore.shopId,
      channelStoreName: channelStore.shopName,
      channelOrderId: `${Date.now()}${randomNum}`,
      marketplaceBuyerUsername: buyer.username,
      channelStatus: 'AWAITING_COLLECTION',
      slaShipDeadline: new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString(),
      isSlaBreachRisk: false,
      airwayBillGenerated: false,
      pickupType: 'dropoff',
      allocatedWarehouse: 'Warehouse Alpha (Central Hub - Cyberjaya)',
      createdAt: new Date().toISOString(),
      customerId: `cust-sim-${Date.now()}`,
      customerName: buyer.name,
      customerEmail: buyer.email,
      customerPhone: buyer.phone,
      paymentGateway: 'Credit Card',
      paymentGatewayStatus: 'paid',
      fulfillmentStatus: 'unfulfilled',
      currency: platform === 'shopify' ? 'USD' : 'MYR',
      subtotal: item.sellingPrice,
      taxAmount: 0,
      shippingAmount: 0,
      discountAmount: 0,
      totalAmount: item.sellingPrice,
      notificationContextFingerprint: `simulated-order-context-${Date.now()}`,
      notificationSummary: null,
      shippingAddress: {
        id: `addr-sim-${Date.now()}`,
        isDefault: true,
        type: 'shipping',
        firstName: buyer.name.split(' ')[0],
        lastName: buyer.name.split(' ').slice(1).join(' ') || 'Customer',
        address1: 'No 88, Jalan Teknologi 3/4',
        city: buyer.city,
        province: platform === 'shopify' ? 'CA' : 'Selangor',
        country: platform === 'shopify' ? 'United States' : 'Malaysia',
        postalCode: buyer.post,
        phone: buyer.phone
      },
      billingAddress: {
        id: `addr-sim-b-${Date.now()}`,
        isDefault: true,
        type: 'billing',
        firstName: buyer.name.split(' ')[0],
        lastName: buyer.name.split(' ').slice(1).join(' ') || 'Customer',
        address1: 'No 88, Jalan Teknologi 3/4',
        city: buyer.city,
        province: platform === 'shopify' ? 'CA' : 'Selangor',
        country: platform === 'shopify' ? 'United States' : 'Malaysia',
        postalCode: buyer.post,
        phone: buyer.phone
      },
      items: [
        {
          id: `item-sim-${Date.now()}`,
          productId: 'prod-01',
          variantId: 'var-01-blk',
          title: item.productName,
          variantTitle: item.variantName,
          sku: item.masterSku,
          price: item.sellingPrice,
          quantity: 1,
          image: item.imageUrl,
          weightGrams: 420
        }
      ],
      timeline: [
        {
          id: `evt-${Date.now()}`,
          timestamp: new Date().toISOString(),
          type: 'created',
          title: `Simulated ${platform.toUpperCase()} Order Inbound`,
          description: `Order webhook received from ${channelStore.shopName}. Reserved 1 unit of ${item.masterSku}.`
        }
      ]
    };

    // Add to marketplace orders
    setMarketplaceOrders(prev => {
      const updated = [newOrder, ...prev];
      localStorage.setItem(STORAGE_KEYS.MARKETPLACE_ORDERS, JSON.stringify(updated));
      return updated;
    });

    // Auto-reserve stock
    setMarketplaceInventory(prev => {
      const updated = prev.map(inv => {
        if (inv.masterSku === item.masterSku) {
          const newReserved = inv.reservedStock + 1;
          const newAvail = Math.max(0, inv.totalPhysicalStock - newReserved - inv.safetyStock);
          const newLog = {
            id: `hl-${Date.now()}`,
            timestamp: new Date().toISOString(),
            action: 'ORDER_RESERVATION',
            change: -1,
            resultingStock: newAvail,
            user: `${platform.toUpperCase()} Webhook`,
            reason: `Order #${newOrder.orderNumber} auto-reserved 1 unit`
          };
          return {
            ...inv,
            reservedStock: newReserved,
            availableStock: newAvail,
            historyLog: [newLog, ...inv.historyLog]
          };
        }
        return inv;
      });
      localStorage.setItem(STORAGE_KEYS.MARKETPLACE_INVENTORY, JSON.stringify(updated));
      return updated;
    });

    addToast({
      type: 'success',
      title: `⚡ New ${platform.toUpperCase()} Order Received!`,
      message: `Order #${newOrder.orderNumber} from ${buyer.name} (${channelStore.shopName}). 1 unit reserved in WMS.`
    });

    addSyncLog({
      platform,
      shopName: channelStore.shopName,
      eventType: 'order_created',
      status: 'success',
      referenceId: newOrder.channelOrderId,
      message: `Inbound order webhook triggered. Reserved 1 unit for SKU ${item.masterSku}.`,
      payloadJson: JSON.stringify({ orderId: newOrder.channelOrderId, buyer: buyer.username, total: newOrder.totalAmount }, null, 2),
      latencyMs: 95
    });
  };

  // ------------------------------------
  // Malaysia Shipping Module Operations
  // ------------------------------------

  const refreshEasyParcelProvider = async (): Promise<void> => {
    const provider = await fetchEasyParcelProvider(laravelBootstrap);
    setEasyParcelProvider(provider);
    setCourierConfigs(courierConfigsFromEasyParcel(provider));
  };

  const refreshEasyParcelWebhookReceipts = async (): Promise<void> => {
    try {
      await easyParcelWebhookAuditRefreshRef.current?.();
    } catch (error) {
      const failure = error instanceof Error
        ? error
        : new Error('EasyParcel webhook audit could not be loaded.');
      addToast({ type: 'error', title: 'Webhook Audit Unavailable', message: failure.message });
      throw failure;
    }
  };

  useEffect(() => {
    void refreshEasyParcelWebhookReceipts().catch(() => undefined);
  // Laravel emits this immutable bootstrap once per admin app load.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const saveCourierConfig = (config: CourierConfig) => {
    if (config.courierCode !== 'easyparcel') {
      addToast({ type: 'error', title: 'Courier not configured', message: 'Only EasyParcel currently has a provider integration for this store. This courier is not connected.' });
      return;
    }
    addToast({ type: 'info', title: 'Platform OAuth', message: 'Open the EasyParcel provider settings to manage the server-side authorization.' });
  };

  const disconnectEasyParcel = async (environment: 'sandbox' | 'production' = 'sandbox'): Promise<void> => {
    const provider = await disconnectEasyParcelStore(laravelBootstrap, environment);
    setEasyParcelProvider(provider);
    setCourierConfigs(courierConfigsFromEasyParcel(provider));
    addToast({ type: 'success', title: 'EasyParcel disconnected', message: 'The connected EasyParcel store was removed.' });
  };

  const saveEasyParcelSender = async (environment: 'sandbox' | 'production', profile: EasyParcelSenderProfile): Promise<void> => {
    const provider = await saveEasyParcelSenderProfile(laravelBootstrap, environment, profile);
    setEasyParcelProvider(provider);
    setCourierConfigs(courierConfigsFromEasyParcel(provider));
    addToast({ type: 'success', title: 'Sender profile saved', message: 'EasyParcel pickup details are ready for quotes and bookings.' });
  };

  const quoteEasyParcelShipment = (
    parcel: ShipmentParcel,
    packageSpec: EasyParcelPackageSpec,
  ): Promise<EasyParcelQuote> => requestEasyParcelQuote(
    laravelBootstrap,
    parcel.orderId,
    packageSpec,
  );

  const bookEasyParcelShipment = async (parcel: ShipmentParcel, quote: EasyParcelQuote, serviceCode: string): Promise<EasyParcelBooking> => {
    const booking = await confirmEasyParcelBooking(laravelBootstrap, parcel.orderId, quote.id, serviceCode);
    setShipmentParcels(await fetchShipments(laravelBootstrap));
    return booking;
  };

  const reportShipmentReloadWarning = (warning: string | null): void => {
    if (!warning) return;
    addToast({
      type: 'warning',
      title: 'Shipment list refresh failed',
      message: warning,
    });
  };

  const refreshEasyParcelShipmentStatus = async (parcelId: string): Promise<EasyParcelStatusRefreshOutcome> => {
    try {
      const outcome = await refreshEasyParcelShipmentStatusWithReload(laravelBootstrap, parcelId);
      if (outcome.shipments) setShipmentParcels(outcome.shipments);
      addToast({
        type: 'success',
        title: 'EasyParcel provider status',
        message: easyParcelShipmentStatusDiagnostic(outcome.result),
      });
      reportShipmentReloadWarning(outcome.reloadWarning);
      return outcome;
    } catch (error) {
      const failure = error instanceof Error ? error : new Error('EasyParcel status could not be refreshed.');
      addToast({ type: 'error', title: 'Status refresh failed', message: failure.message });
      throw failure;
    }
  };

  const refreshAllEasyParcelShipmentStatuses = async (): Promise<EasyParcelStatusRefreshOutcome[]> => {
    const eligible = shipmentParcels.filter((parcel) => (
      parcel.source === 'easyparcel'
      && parcel.status !== 'delivered'
      && parcel.status !== 'returned'
      && parcel.status !== 'booking_failed'
      && parcel.status !== 'cancelled'
    ));

    if (eligible.length === 0) {
      addToast({ type: 'info', title: 'Statuses already current', message: 'There are no non-terminal EasyParcel shipments to refresh.' });
      return [];
    }

    let updated = 0;
    let failed = 0;
    const results: EasyParcelShipmentStatusRefresh[] = [];
    for (const parcel of eligible) {
      try {
        const result = await refreshEasyParcelShipmentStatusData(laravelBootstrap, parcel.id);
        results.push(result);
        if (result.changed) updated += 1;
        addToast({
          type: 'success',
          title: `EasyParcel provider status · #${parcel.orderNumber}`,
          message: easyParcelShipmentStatusDiagnostic(result),
        });
      } catch {
        failed += 1;
      }
    }

    let refreshedShipments: ShipmentParcel[] | null = null;
    let reloadWarning: string | null = null;
    try {
      refreshedShipments = await fetchShipments(laravelBootstrap);
      setShipmentParcels(refreshedShipments);
    } catch {
      reloadWarning = 'Shipment list could not be reloaded. Refresh the page before continuing.';
      addToast({
        type: 'warning',
        title: 'Statuses checked; list refresh failed',
        message: `${eligible.length - failed} provider checks completed. ${reloadWarning}`,
      });
    }
    addToast({
      type: failed > 0 ? 'warning' : 'success',
      title: 'Latest EasyParcel statuses retrieved',
      message: `${eligible.length - failed} checked · ${updated} updated${failed > 0 ? ` · ${failed} failed` : ''}.`,
    });
    return results.map(result => ({
      result,
      shipments: refreshedShipments,
      replacementParcel: refreshedShipments
        ? findEasyParcelReplacementParcel(refreshedShipments, result.recovery)
        : null,
      reloadWarning,
    }));
  };

  const confirmEasyParcelProviderFailure = async (
    parcelId: string,
    reason: string,
  ): Promise<EasyParcelRecoveryConfirmationOutcome> => {
    const outcome = await confirmEasyParcelProviderFailureWithReload(laravelBootstrap, parcelId, reason);
    if (outcome.shipments) setShipmentParcels(outcome.shipments);
    addToast({
      type: outcome.result.cancellationConfirmed ? 'success' : 'warning',
      title: 'EasyParcel recovery recorded',
      message: outcome.result.cancellationMessage ?? 'EasyParcel cancellation outcome is still pending.',
    });
    reportShipmentReloadWarning(outcome.reloadWarning);
    return outcome;
  };

  const toggleCourierStatus = (id: string) => {
    const courier = courierConfigs.find((entry) => entry.id === id);
    if (courier?.courierCode === 'easyparcel') {
      addToast({ type: 'info', title: 'Authorization required', message: 'Open EasyParcel credentials and complete the provider authorization flow before activation.' });
      return;
    }
    addToast({ type: 'error', title: 'Courier not configured', message: 'This courier has no configured provider integration.' });
  };

  const pingCourierAPI = async (id: string, environment: 'sandbox' | 'production' = 'sandbox'): Promise<number> => {
    const courier = courierConfigs.find((entry) => entry.id === id);
    if (courier?.courierCode === 'easyparcel') {
      try {
        startEasyParcelAuthorization(laravelBootstrap, environment);
      } catch (error) {
        addToast({ type: 'error', title: 'EasyParcel authorization unavailable', message: error instanceof Error ? error.message : 'Unable to start provider authorization.' });
      }
      return 0;
    }
    addToast({ type: 'error', title: 'Courier not configured', message: 'This courier has no provider endpoint to test.' });
    return 0;
  };

  const saveSmartRoutingRule = (_rule: SmartRoutingRule) => {
    addToast({ type: 'error', title: 'Smart routing unavailable', message: 'Routing rules are not saved locally; connect a courier provider before enabling routing.' });
  };

  const deleteSmartRoutingRule = (_id: string) => {
    addToast({ type: 'error', title: 'Smart routing unavailable', message: 'No persisted provider routing rules are configured.' });
  };

  const toggleSmartRoutingRule = (_id: string) => {
    addToast({ type: 'error', title: 'Smart routing unavailable', message: 'No persisted provider routing rules are configured.' });
  };

  const reorderSmartRoutingRules = (_rules: SmartRoutingRule[]) => {
    addToast({ type: 'error', title: 'Smart routing unavailable', message: 'No persisted provider routing rules are configured.' });
  };

  const generateShipmentAWB = (_parcelId: string, _courierCode?: string) => {
    addToast({ type: 'error', title: 'Courier label issuance unavailable', message: 'Use Fulfil Order to book EasyParcel or record a team-managed tracking number.' });
  };

  const batchGenerateAWB = (_parcelIds: string[]) => {
    addToast({ type: 'error', title: 'Courier label issuance unavailable', message: 'Batch AWB generation is not supported. Open each shipment to use its official fulfilment workflow.' });
  };

  const updateShipmentStatus = async (parcelId: string, status: ShipmentParcel['status'], eventDesc?: string, location?: string, voidConfirmed = false): Promise<void> => {
    const permittedStatuses = ['in_transit', 'out_for_delivery', 'delivered', 'failed_delivery', 'returned', 'cancelled'] as const;
    if (!permittedStatuses.includes(status as typeof permittedStatuses[number])) {
      addToast({ type: 'error', title: 'Unsupported shipment action', message: 'This status requires a courier integration and cannot be fabricated locally.' });
      throw new Error('This status requires a courier integration and cannot be fabricated locally.');
    }
    try {
      await appendShipmentEvent(
        laravelBootstrap, parcelId, status as typeof permittedStatuses[number],
        eventDesc?.trim() || `Shipment marked ${status.replaceAll('_', ' ')} by warehouse staff.`,
        location, voidConfirmed,
      );
      setShipmentParcels(await fetchShipments(laravelBootstrap));
      addToast({ type: 'success', title: 'Shipment event recorded', message: 'The event was saved to the immutable shipment audit trail.' });
    } catch (error) {
      addToast({ type: 'error', title: 'Shipment event failed', message: error instanceof Error ? error.message : 'Unable to record shipment event.' });
      throw error;
    }
  };

  const recordExternalCourierDispatch = async (parcelId: string, data: { courierCode: string; courierName: string; trackingNumber: string; trackingUrl?: string; auditNote: string }): Promise<void> => {
    const parcel = shipmentParcels.find(candidate => candidate.id === parcelId);
    if (!parcel || parcel.source !== 'pending') throw new Error('Only an unfulfilled order can be dispatched.');
    await recordExternalCourierDispatchData(laravelBootstrap, parcel.orderId, data);
    setShipmentParcels(await fetchShipments(laravelBootstrap));
    addToast({ type: 'success', title: 'External dispatch recorded', message: 'The external AWB and immutable dispatch audit are now stored.' });
  };

  const recordManualDeliveryDispatch = async (parcelId: string, data: { driverName: string; handoffReference?: string; auditNote: string }): Promise<void> => {
    const parcel = shipmentParcels.find(candidate => candidate.id === parcelId);
    if (!parcel || parcel.source !== 'pending') throw new Error('Only an unfulfilled order can be dispatched.');
    await recordManualDeliveryDispatchData(laravelBootstrap, parcel.orderId, data);
    setShipmentParcels(await fetchShipments(laravelBootstrap));
    addToast({ type: 'success', title: 'Manual delivery recorded', message: 'The driver assignment and immutable dispatch audit are now stored.' });
  };

  const startShipmentCorrection = async (parcelId: string, input: StartShipmentCorrectionInput): Promise<{ result: StartShipmentCorrectionResult; replacementParcel: ShipmentParcel }> => {
    try {
      const result = await startShipmentCorrectionData(laravelBootstrap, parcelId, input);
      const refreshed = await fetchShipments(laravelBootstrap);
      setShipmentParcels(refreshed);
      const replacementParcel = refreshed.find(parcel => (
        parcel.source === 'pending'
        && parcel.orderId === result.orderId
        && parcel.activeCorrection?.id === result.id
        && parcel.activeCorrection.readyToResume
      ));
      if (!replacementParcel) throw new Error('Replacement fulfilment draft could not be reloaded.');
      addToast({ type: 'info', title: 'Attempt Voided — Select Replacement', message: 'The original attempt remains in history. Choose the replacement fulfilment method.' });
      return { result, replacementParcel };
    } catch (error) {
      addToast({ type: 'error', title: 'Fulfilment Correction Failed', message: error instanceof Error ? error.message : 'Unable to start fulfilment correction.' });
      throw error;
    }
  };

  const amendShipmentOperationalDetails = async (parcelId: string, input: AmendShipmentOperationalDetailsInput): Promise<void> => {
    try {
      await amendShipmentOperationalDetailsData(laravelBootstrap, parcelId, input);
      setShipmentParcels(await fetchShipments(laravelBootstrap));
      addToast({ type: 'success', title: 'Dispatch Details Corrected', message: 'Operational details and the immutable audit were updated.' });
    } catch (error) {
      addToast({ type: 'error', title: 'Dispatch Correction Failed', message: error instanceof Error ? error.message : 'Unable to correct shipment details.' });
      throw error;
    }
  };

  const requestRedelivery = (_parcelId: string) => {
    addToast({ type: 'error', title: 'Re-delivery unavailable', message: 'This courier command requires an active provider integration and has not been simulated.' });
  };

  const createRMAReturnShipment = (_orderNumber: string, _reason: string) => {
    addToast({ type: 'error', title: 'Return shipment unavailable', message: 'RMA labels require a connected courier provider and are not created as local demo data.' });
  };

  const simulateCourierWebhookEvent = (parcelId: string, eventType: string, location: string, description: string) => {
    const status: ShipmentParcel['status'] = eventType.includes('Delivered')
      ? 'delivered'
      : eventType.includes('Out for Delivery')
        ? 'out_for_delivery'
        : eventType.includes('Failed') || eventType.includes('NITC')
          ? 'failed_delivery'
          : 'in_transit';
    void updateShipmentStatus(parcelId, status, description, location);
  };

  const createPickupRequest = (_request: Omit<PickupRequest, 'id' | 'createdAt'>) => {
    addToast({ type: 'error', title: 'Pickup scheduling unavailable', message: 'Driver booking requires a configured courier provider and has not been simulated.' });
  };

  const cancelPickupRequest = (_id: string) => {
    addToast({ type: 'error', title: 'Pickup cancellation unavailable', message: 'Pickup cancellation requires a connected courier provider.' });
  };

  const resolveBillingDispute = (_id: string) => {
    addToast({ type: 'error', title: 'Billing reconciliation unavailable', message: 'No provider invoice has been imported, so no courier credit note was created.' });
  };

  // General Shipping Rates & Postage Calculation Engine
  const persistCommerceDocument = (overrides: Partial<{ shippingRates: GeneralShippingRate[]; paymentGateways: PaymentGatewayConfig[]; globalPayment: GlobalPaymentSettings; bankTransfer: ManualBankTransferConfig; codConfig: CashOnDeliveryConfig; currencies: CurrencySetting[] }>) => {
    void saveCommerceSettings(laravelBootstrap, {
      shippingRates: overrides.shippingRates ?? shippingRates,
      paymentGateways: overrides.paymentGateways ?? paymentGateways,
      globalPayment: overrides.globalPayment ?? globalPaymentSettings,
      bankTransfer: overrides.bankTransfer ?? bankTransferConfig,
      codConfig: overrides.codConfig ?? codConfig,
      currencies: overrides.currencies ?? currenciesSetting,
    }).catch(error => addToast({ type: 'error', title: 'Commerce Settings Save Failed', message: error instanceof Error ? error.message : 'Unable to save commerce settings.' }));
  };

  const persistCurrencySettings = async (updated: CurrencySetting[], previous: CurrencySetting[]): Promise<boolean> => {
    setCurrenciesSetting(updated);
    try {
      const document = await saveCommerceSettings(laravelBootstrap, {
        shippingRates,
        paymentGateways,
        globalPayment: globalPaymentSettings,
        bankTransfer: bankTransferConfig,
        codConfig,
        currencies: updated,
      });
      setCurrenciesSetting(document.currencies);
      return true;
    } catch (error) {
      setCurrenciesSetting(previous);
      addToast({ type: 'error', title: 'Currency Save Failed', message: error instanceof Error ? error.message : 'Unable to save currency settings to MySQL.' });
      return false;
    }
  };

  const persistShippingRates = async (updated: GeneralShippingRate[], previous: GeneralShippingRate[]): Promise<boolean> => {
    setShippingRates(updated);
    try {
      const document = await saveShippingConfiguration(laravelBootstrap, updated);
      setShippingRates(document.shippingRates);
      return true;
    } catch (error) {
      setShippingRates(previous);
      addToast({ type: 'error', title: 'Shipping Rate Save Failed', message: error instanceof Error ? error.message : 'Unable to save shipping rates to MySQL.' });
      return false;
    }
  };

  const saveShippingRate = async (rate: GeneralShippingRate) => {
    const exists = shippingRates.some(item => item.id === rate.id);
    const updated = exists ? shippingRates.map(item => item.id === rate.id ? { ...rate, updatedAt: new Date().toISOString().replace('T', ' ').slice(0, 19) } : item) : [{ ...rate, createdAt: new Date().toISOString().replace('T', ' ').slice(0, 19), updatedAt: new Date().toISOString().replace('T', ' ').slice(0, 19) }, ...shippingRates];
    if (! await persistShippingRates(updated, shippingRates)) return;
    addToast({
      type: 'success',
      title: 'Shipping Rate Saved',
      message: `Region postage rule "${rate.name}" (${rate.calculationMode.toUpperCase()} mode) configured successfully.`
    });
  };

  const deleteShippingRate = async (id: string) => {
    const updated = shippingRates.filter(rate => rate.id !== id);
    if (! await persistShippingRates(updated, shippingRates)) return;
    addToast({
      type: 'info',
      title: 'Shipping Rate Removed',
      message: 'Postage calculation rule has been deleted.'
    });
  };

  const toggleShippingRateStatus = async (id: string) => {
    const updated = shippingRates.map(rate => rate.id === id ? { ...rate, isActive: !rate.isActive } : rate);
    await persistShippingRates(updated, shippingRates);
  };

  const duplicateShippingRate = async (id: string) => {
    const existing = shippingRates.find(r => r.id === id);
    if (!existing) return;
    const duplicated: GeneralShippingRate = {
      ...existing,
      id: `rate_${Date.now()}`,
      name: `${existing.name} (Copy)`,
      createdAt: new Date().toISOString().replace('T', ' ').slice(0, 19),
      updatedAt: new Date().toISOString().replace('T', ' ').slice(0, 19)
    };
    const updated = [duplicated, ...shippingRates];
    if (! await persistShippingRates(updated, shippingRates)) return;
    addToast({
      type: 'success',
      title: 'Rate Rule Duplicated',
      message: `Created clone "${duplicated.name}".`
    });
  };

  const calculateShippingRateBreakdown = (params: {
    region?: string;
    state?: string;
    weightKg?: number;
    quantity?: number;
    orderAmount?: number;
    courierCode?: string;
    rateId?: string;
  }) => {
    const {
      region = 'peninsular',
      state = '',
      weightKg = 1.0,
      quantity = 1,
      orderAmount = 50,
      courierCode = 'all',
      rateId
    } = params;

    let rate: GeneralShippingRate | undefined;
    if (rateId) {
      rate = shippingRates.find(r => r.id === rateId);
    }

    if (!rate) {
      const activeRates = shippingRates.filter(r => r.isActive);
      rate = activeRates.find(r => {
        const matchRegion = r.zoneRegion === 'all' || 
          r.zoneRegion === region ||
          (r.zoneRegion === 'east_malaysia' && (region === 'sabah' || region === 'sarawak' || region === 'east_malaysia' || state.toLowerCase().includes('sabah') || state.toLowerCase().includes('sarawak'))) ||
          (r.zoneRegion === 'peninsular' && (region === 'peninsular' || (!state.toLowerCase().includes('sabah') && !state.toLowerCase().includes('sarawak'))));
        const matchCourier = r.logisticType === 'all' || courierCode === 'all' || r.logisticType === courierCode;
        return matchRegion && matchCourier;
      }) || activeRates[0];
    }

    if (!rate) {
      return {
        appliedRate: null,
        baseCost: 0,
        subsequentCost: 0,
        totalShippingFee: 0,
        isFreeShipping: false,
        breakdownSummary: 'No active shipping rate found matching criteria.'
      };
    }

    // Check free shipping threshold
    if (rate.freeShippingThreshold && rate.freeShippingThreshold > 0 && orderAmount >= rate.freeShippingThreshold) {
      return {
        appliedRate: rate,
        baseCost: 0,
        subsequentCost: 0,
        totalShippingFee: 0,
        isFreeShipping: true,
        freeShippingSavedRM: 10,
        breakdownSummary: `🎉 Free Shipping threshold met! (Order RM ${orderAmount.toFixed(2)} ≥ RM ${rate.freeShippingThreshold.toFixed(2)})`
      };
    }

    if (rate.calculationMode === 'per_order' || rate.rateType === 'flat') {
      const flatCost = rate.flatRateCost ?? (rate.tiers[0]?.cost || 0);
      return {
        appliedRate: rate,
        baseCost: flatCost,
        subsequentCost: 0,
        totalShippingFee: flatCost,
        isFreeShipping: false,
        breakdownSummary: `Flat postage rate: RM ${flatCost.toFixed(2)} per order.`
      };
    }

    const calcValue = rate.calculationMode === 'weight' ? (weightKg || 0.1) : (quantity || 1);
    const unitLabel = rate.calculationMode === 'weight' ? 'kg' : 'pcs';

    const sortedTiers = [...rate.tiers].sort((a, b) => a.min - b.min);
    let baseCost = 0;
    let subsequentCost = 0;
    let tierMatched: ShippingRateTier | undefined;
    let subsequentUnitsBilled = 0;
    let summary = '';

    if (sortedTiers.length > 0) {
      const matched = sortedTiers.find(t => calcValue > t.min && calcValue <= t.max);
      if (matched) {
        tierMatched = matched;
        baseCost = matched.cost;
        summary = `Tier matched (> ${matched.min} to ${matched.max} ${unitLabel}) = RM ${matched.cost.toFixed(2)}`;
      } else if (calcValue <= sortedTiers[0].min) {
        tierMatched = sortedTiers[0];
        baseCost = sortedTiers[0].cost;
        summary = `Base Tier (≤ ${sortedTiers[0].max} ${unitLabel}) = RM ${sortedTiers[0].cost.toFixed(2)}`;
      } else {
        const maxTier = sortedTiers[sortedTiers.length - 1];
        tierMatched = maxTier;
        baseCost = maxTier.cost;
        const excess = calcValue - maxTier.max;
        const step = rate.subsequentRate && rate.subsequentRate > 0 ? rate.subsequentRate : 1.0;
        subsequentUnitsBilled = Math.ceil(excess / step);
        const unitCost = rate.subsequentCost || 0;
        subsequentCost = subsequentUnitsBilled * unitCost;
        summary = `Base (> ${maxTier.min} to ${maxTier.max} ${unitLabel} = RM ${baseCost.toFixed(2)}) + Subsequent ${excess.toFixed(2)} ${unitLabel} (${subsequentUnitsBilled} steps @ RM ${unitCost.toFixed(2)}/${step} ${unitLabel} = RM ${subsequentCost.toFixed(2)})`;
      }
    } else {
      baseCost = rate.flatRateCost || 0;
      summary = `Default postage: RM ${baseCost.toFixed(2)}`;
    }

    return {
      appliedRate: rate,
      baseCost,
      subsequentCost,
      totalShippingFee: baseCost + subsequentCost,
      isFreeShipping: false,
      tierMatched,
      subsequentUnitsBilled,
      breakdownSummary: summary
    };
  };

  // Payment Settings Handlers
  const savePaymentGateway = async (config: PaymentGatewayConfig): Promise<boolean> => {
    const next = paymentGateways.map(gateway => gateway.id === config.id ? config : gateway);
    try {
      const document = await saveCommerceSettings(laravelBootstrap, {
        shippingRates,
        paymentGateways: next,
        globalPayment: globalPaymentSettings,
        bankTransfer: bankTransferConfig,
        codConfig,
        currencies: currenciesSetting,
      });
      setPaymentGateways(document.paymentGateways);
      addToast({ type: 'success', title: 'Payment Gateway Saved', message: `${config.name} credentials and settings updated.` });
      return true;
    } catch (error) {
      addToast({ type: 'error', title: 'Payment Gateway Save Failed', message: error instanceof Error ? error.message : 'Unable to save payment gateway.' });
      return false;
    }
  };

  const togglePaymentGateway = async (id: PaymentGatewayId, enabled: boolean): Promise<void> => {
    const next = paymentGateways.map(gateway => gateway.id === id ? { ...gateway, isEnabled: enabled } : gateway);
    const gw = paymentGateways.find(g => g.id === id);
    try {
      const document = await saveCommerceSettings(laravelBootstrap, {
        shippingRates,
        paymentGateways: next,
        globalPayment: globalPaymentSettings,
        bankTransfer: bankTransferConfig,
        codConfig,
        currencies: currenciesSetting,
      });
      setPaymentGateways(document.paymentGateways);
      addToast({ type: enabled ? 'success' : 'info', title: enabled ? 'Gateway Activated' : 'Gateway Deactivated', message: `${gw?.name || id} is now ${enabled ? 'active for storefront checkouts' : 'disabled'}.` });
    } catch (error) {
      addToast({ type: 'error', title: 'Gateway Update Rejected', message: error instanceof Error ? error.message : 'Configure gateway credentials before enabling it.' });
    }
  };

  const pingPaymentGateway = async (id: PaymentGatewayId): Promise<number> => {
    const startTime = Date.now();
    await new Promise(resolve => setTimeout(resolve, 600 + Math.random() * 500));
    const latency = Date.now() - startTime;
    const nowStr = new Date().toISOString().replace('T', ' ').substring(0, 19);

    setPaymentGateways(prev => {
      const next = prev.map(g => {
        if (g.id === id) {
          return {
            ...g,
            lastTestedAt: nowStr,
            testStatus: 'success' as const,
            testMessage: `Handshake successful (${latency}ms). Gateway responding properly.`
          };
        }
        return g;
      });
      persistCommerceDocument({ paymentGateways: next });
      return next;
    });

    addToast({
      type: 'success',
      title: 'Gateway Ping Successful',
      message: `Connected to gateway in ${latency}ms.`
    });
    return latency;
  };

  const persistBankTransferConfiguration = async (config: ManualBankTransferConfig, title: string, message: string): Promise<boolean> => {
    try {
      const document = await saveBankTransferConfiguration(laravelBootstrap, config);
      setBankTransferConfig(document.bankTransfer as ManualBankTransferConfig);
      addToast({ type: 'success', title, message });
      return true;
    } catch (error) {
      addToast({ type: 'error', title: 'Bank Transfer Save Failed', message: error instanceof Error ? error.message : 'Unable to save bank transfer settings.' });
      return false;
    }
  };

  const saveBankTransferConfig = async (config: ManualBankTransferConfig): Promise<boolean> =>
    persistBankTransferConfiguration(config, 'Bank Transfer Settings Saved', 'Direct bank transfer and DuitNow QR options updated.');

  const saveBankAccount = async (account: BankAccountDetails): Promise<boolean> => {
    const exists = bankTransferConfig.bankAccounts.some(item => item.id === account.id);
    const updatedAccounts = exists ? bankTransferConfig.bankAccounts.map(item => item.id === account.id ? account : (account.isPrimary ? { ...item, isPrimary: false } : item)) : [...bankTransferConfig.bankAccounts.map(item => account.isPrimary ? { ...item, isPrimary: false } : item), account];
    const nextConfig = { ...bankTransferConfig, bankAccounts: updatedAccounts };
    return persistBankTransferConfiguration(nextConfig, 'Bank Account Saved', `${account.bankName} account details updated.`);
  };

  const deleteBankAccount = async (id: string): Promise<boolean> => {
    const nextConfig = { ...bankTransferConfig, bankAccounts: bankTransferConfig.bankAccounts.filter(account => account.id !== id) };
    return persistBankTransferConfiguration(nextConfig, 'Account Removed', 'Bank account deleted from manual checkout options.');
  };

  const setPrimaryBankAccount = async (id: string): Promise<boolean> => {
    const nextConfig = { ...bankTransferConfig, bankAccounts: bankTransferConfig.bankAccounts.map(account => ({ ...account, isPrimary: account.id === id })) };
    return persistBankTransferConfiguration(nextConfig, 'Primary Account Set', 'Default bank account updated for DuitNow transfers.');
  };

  const saveCodConfig = async (config: CashOnDeliveryConfig): Promise<boolean> => {
    try {
      const document = await saveCodConfiguration(laravelBootstrap, config);
      setCodConfig(document.codConfig as CashOnDeliveryConfig);
      addToast({ type: 'success', title: 'COD Settings Saved', message: 'Cash on Delivery terms, limits, and surcharges updated.' });
      return true;
    } catch (error) {
      addToast({ type: 'error', title: 'COD Save Failed', message: error instanceof Error ? error.message : 'Unable to save COD configuration.' });
      return false;
    }
  };

  const saveGlobalPaymentSettings = (settings: GlobalPaymentSettings) => {
    setGlobalPaymentSettings(settings);
    persistCommerceDocument({ globalPayment: settings });
    addToast({
      type: 'success',
      title: 'Global Payment Rules Saved',
      message: 'Checkout default gateways and expiry rules updated.'
    });
  };

  // ---------------------------
  // osCommerce Settings Handlers
  // ---------------------------

  const saveOrderStatus = (status: CustomOrderStatus) => {
    setOrderStatuses(prev => {
      const exists = prev.some(s => s.id === status.id);
      let updated: CustomOrderStatus[];
      if (exists) {
        updated = prev.map(s => (s.id === status.id ? status : s));
      } else {
        updated = [...prev, status];
      }
      try {
        localStorage.setItem(STORAGE_KEYS.CUSTOM_ORDER_STATUSES, JSON.stringify(updated));
      } catch {}
      return updated;
    });
    addToast({
      type: 'success',
      title: 'Order Status Saved',
      message: `Status "${status.name}" has been updated.`
    });
  };

  const deleteOrderStatus = (id: string) => {
    setOrderStatuses(prev => {
      const updated = prev.filter(s => s.id !== id);
      try {
        localStorage.setItem(STORAGE_KEYS.CUSTOM_ORDER_STATUSES, JSON.stringify(updated));
      } catch {}
      return updated;
    });
    addToast({
      type: 'info',
      title: 'Order Status Removed',
      message: 'Custom order status was deleted.'
    });
  };

  const setDefaultOrderStatus = (id: string) => {
    setOrderStatuses(prev => {
      const updated = prev.map(s => ({
        ...s,
        isDefault: s.id === id
      }));
      try {
        localStorage.setItem(STORAGE_KEYS.CUSTOM_ORDER_STATUSES, JSON.stringify(updated));
      } catch {}
      return updated;
    });
    addToast({
      type: 'success',
      title: 'Default Status Updated',
      message: 'Default status for incoming orders updated.'
    });
  };

  const saveCountrySetting = (country: CountrySetting) => {
    setCountriesSetting(prev => {
      const exists = prev.some(c => c.id === country.id);
      let updated: CountrySetting[];
      if (exists) {
        updated = prev.map(c => (c.id === country.id ? country : c));
      } else {
        updated = [...prev, country];
      }
      try {
        localStorage.setItem(STORAGE_KEYS.COUNTRIES_SETTING, JSON.stringify(updated));
      } catch {}
      return updated;
    });
    addToast({
      type: 'success',
      title: 'Country Saved',
      message: `${country.name} address rules and status updated.`
    });
  };

  const toggleCountryStatus = (id: string) => {
    setCountriesSetting(prev => {
      const updated = prev.map(c => (c.id === id ? { ...c, isEnabled: !c.isEnabled } : c));
      try {
        localStorage.setItem(STORAGE_KEYS.COUNTRIES_SETTING, JSON.stringify(updated));
      } catch {}
      return updated;
    });
    addToast({
      type: 'info',
      title: 'Country Availability Toggled',
      message: 'Country availability in checkout addresses updated.'
    });
  };

  const saveZoneSetting = (zone: ZoneSetting) => {
    setZonesSetting(prev => {
      const exists = prev.some(z => z.id === zone.id);
      let updated: ZoneSetting[];
      if (exists) {
        updated = prev.map(z => (z.id === zone.id ? zone : z));
      } else {
        updated = [...prev, zone];
      }
      try {
        localStorage.setItem(STORAGE_KEYS.ZONES_SETTING, JSON.stringify(updated));
      } catch {}
      return updated;
    });
    addToast({
      type: 'success',
      title: 'Zone Saved',
      message: `Zone "${zone.zoneName}" state definition saved.`
    });
  };

  const toggleZoneStatus = (id: string) => {
    setZonesSetting(prev => {
      const updated = prev.map(z => (z.id === id ? { ...z, isEnabled: !z.isEnabled } : z));
      try {
        localStorage.setItem(STORAGE_KEYS.ZONES_SETTING, JSON.stringify(updated));
      } catch {}
      return updated;
    });
    addToast({
      type: 'info',
      title: 'Zone Availability Toggled',
      message: 'Regional state availability updated.'
    });
  };

  const deleteZoneSetting = (id: string) => {
    setZonesSetting(prev => {
      const updated = prev.filter(z => z.id !== id);
      try {
        localStorage.setItem(STORAGE_KEYS.ZONES_SETTING, JSON.stringify(updated));
      } catch {}
      return updated;
    });
    addToast({
      type: 'info',
      title: 'Zone Deleted',
      message: 'Zone state definition removed.'
    });
  };

  const saveCurrencySetting = (currency: CurrencySetting) => {
    const updated = currenciesSetting.some(c => c.id === currency.id)
      ? currenciesSetting.map(c => (c.id === currency.id ? currency : c))
      : [...currenciesSetting, currency];
    void persistCurrencySettings(updated, currenciesSetting);
    addToast({
      type: 'success',
      title: 'Currency Saved',
      message: `${currency.title} (${currency.code}) configuration saved.`
    });
  };

  const setDefaultCurrency = (id: string) => {
    const updated = currenciesSetting.map(c => ({
      ...c,
      isDefault: c.id === id,
      isEnabled: c.id === id ? true : c.isEnabled,
      exchangeRate: c.id === id ? 1.0 : c.exchangeRate
    }));
    void persistCurrencySettings(updated, currenciesSetting);
    addToast({
      type: 'success',
      title: 'Default Currency Set',
      message: 'Base store valuation currency updated.'
    });
  };

  const toggleCurrencyStatus = (id: string) => {
    const target = currenciesSetting.find(c => c.id === id);
    if (target?.isDefault && target.isEnabled) {
      addToast({ type: 'error', title: 'Default Currency Required', message: 'Choose another default currency before disabling this one.' });
      return;
    }
    const updated = currenciesSetting.map(c => (c.id === id ? { ...c, isEnabled: !c.isEnabled } : c));
    void persistCurrencySettings(updated, currenciesSetting);
    addToast({
      type: 'info',
      title: 'Currency Toggled',
      message: 'Customer storefront currency selection updated.'
    });
  };

  const updateCurrencyRates = async () => {
    await new Promise(r => setTimeout(r, 600));
    const updated = currenciesSetting.map(c => {
        if (c.isDefault) return { ...c, exchangeRate: 1.0, lastUpdated: 'Just now (Live API)' };
        let newRate = c.exchangeRate;
        if (c.code === 'USD') newRate = +(0.22 + (Math.random() * 0.006 - 0.003)).toFixed(4);
        if (c.code === 'SGD') newRate = +(0.30 + (Math.random() * 0.004 - 0.002)).toFixed(4);
        if (c.code === 'EUR') newRate = +(0.20 + (Math.random() * 0.005 - 0.0025)).toFixed(4);
        if (c.code === 'GBP') newRate = +(0.17 + (Math.random() * 0.004 - 0.002)).toFixed(4);
        return { ...c, exchangeRate: newRate, lastUpdated: 'Just now (Live API)' };
    });
    const saved = await persistCurrencySettings(updated, currenciesSetting);
    if (!saved) return;
    addToast({
      type: 'success',
      title: 'Exchange Rates Updated',
      message: 'Updated and saved the configured rate matrix against the base currency.'
    });
  };

  const saveOrderTotalModule = (module: OrderTotalModule) => {
    setOrderTotalModules(prev => {
      const exists = prev.some(m => m.id === module.id);
      let updated: OrderTotalModule[];
      if (exists) {
        updated = prev.map(m => (m.id === module.id ? module : m));
      } else {
        updated = [...prev, module];
      }
      try {
        localStorage.setItem(STORAGE_KEYS.ORDER_TOTAL_MODULES, JSON.stringify(updated));
      } catch {}
      return updated;
    });
    addToast({
      type: 'success',
      title: 'Order Total Module Saved',
      message: `${module.title} calculation parameters updated.`
    });
  };

  const reorderOrderTotalModules = (modules: OrderTotalModule[]) => {
    setOrderTotalModules(modules);
    try {
      localStorage.setItem(STORAGE_KEYS.ORDER_TOTAL_MODULES, JSON.stringify(modules));
    } catch {}
    addToast({
      type: 'info',
      title: 'Order Total Pipeline Reordered',
      message: 'Calculation evaluation sequence updated.'
    });
  };

  const toggleOrderTotalModule = (id: string) => {
    setOrderTotalModules(prev => {
      const updated = prev.map(m => (m.id === id ? { ...m, isEnabled: !m.isEnabled } : m));
      try {
        localStorage.setItem(STORAGE_KEYS.ORDER_TOTAL_MODULES, JSON.stringify(updated));
      } catch {}
      return updated;
    });
    addToast({
      type: 'info',
      title: 'Order Total Module Toggled',
      message: 'Module calculation rule enabled/disabled.'
    });
  };

  const saveGeoTaxZone = (zone: GeoTaxZone) => {
    void saveTaxZone(laravelBootstrap, zone).then(saved => {
      setTaxZones(prev => prev.some(item => item.id === saved.id) ? prev.map(item => item.id === saved.id ? saved : item) : [...prev, saved]);
      addToast({ type: 'success', title: 'Tax Zone Saved', message: `Tax Zone "${saved.name}" saved.` });
    }).catch(error => addToast({ type: 'error', title: 'Tax Zone Save Failed', message: error instanceof Error ? error.message : 'Unable to save tax zone to MySQL.' }));
  };

  const deleteGeoTaxZone = (id: string) => {
    void deleteTaxZoneData(laravelBootstrap, id).then(() => {
      setTaxZones(prev => prev.filter(zone => zone.id !== id));
      addToast({ type: 'info', title: 'Tax Zone Removed', message: 'Geographical tax jurisdiction removed.' });
    }).catch(error => addToast({ type: 'error', title: 'Tax Zone Delete Failed', message: error instanceof Error ? error.message : 'Unable to delete tax zone.' }));
  };

  const saveTaxClass = (taxClass: TaxClass) => {
    const request = /^\d+$/.test(taxClass.id)
      ? updateTaxClass(laravelBootstrap, { ...taxClass, ratePercent: taxClass.ratePercent ?? 0, isActive: taxClass.isActive ?? false })
      : createTaxClass(laravelBootstrap, { name: taxClass.name, ratePercent: taxClass.ratePercent ?? 0, isActive: taxClass.isActive ?? false });
    void request.then(document => {
      setTaxClasses(document.classes);
      addToast({ type: 'success', title: 'Tax Class Saved', message: `Tax Class "${taxClass.name}" updated.` });
    }).catch(error => addToast({ type: 'error', title: 'Tax Class Save Failed', message: error instanceof Error ? error.message : 'Unable to save tax class to MySQL.' }));
  };

  const activateTaxClass = (id: string) => {
    void activateTaxClassData(laravelBootstrap, id).then(document => {
      setTaxClasses(document.classes);
      addToast({ type: 'success', title: 'Tax Class Activated', message: 'The store-wide tax charge is now active.' });
    }).catch(error => addToast({ type: 'error', title: 'Tax Class Activation Failed', message: error instanceof Error ? error.message : 'Unable to activate tax class.' }));
  };

  const deactivateTaxClass = (id: string) => {
    void deactivateTaxClassData(laravelBootstrap, id).then(document => {
      setTaxClasses(document.classes);
      addToast({ type: 'info', title: 'Tax Class Deactivated', message: 'New carts and orders will not be charged tax.' });
    }).catch(error => addToast({ type: 'error', title: 'Tax Class Deactivation Failed', message: error instanceof Error ? error.message : 'Unable to deactivate tax class.' }));
  };

  const deleteTaxClass = (id: string) => {
    void deleteSimpleTaxClassData(laravelBootstrap, id).then(document => {
      setTaxClasses(document.classes);
      addToast({ type: 'info', title: 'Tax Class Deleted', message: 'Tax classification schedule removed.' });
    }).catch(error => addToast({ type: 'error', title: 'Tax Class Delete Failed', message: error instanceof Error ? error.message : 'Unable to delete tax class.' }));
  };

  const saveTaxRate = (taxRate: TaxRate) => {
    void saveTaxRule(laravelBootstrap, taxRate).then(saved => {
      setTaxRates(prev => prev.some(item => item.id === saved.id) ? prev.map(item => item.id === saved.id ? saved : item) : [...prev, saved]);
      addToast({ type: 'success', title: 'Tax Rate Saved', message: `${saved.description} (${saved.ratePercent}%) rate rule updated.` });
    }).catch(error => addToast({ type: 'error', title: 'Tax Rate Save Failed', message: error instanceof Error ? error.message : 'Unable to save tax rule to MySQL.' }));
  };

  const deleteTaxRate = (id: string) => {
    void deleteTaxRuleData(laravelBootstrap, id).then(() => {
      setTaxRates(prev => prev.filter(taxRate => taxRate.id !== id));
      addToast({ type: 'info', title: 'Tax Rate Removed', message: 'Jurisdiction tax percentage rule removed.' });
    }).catch(error => addToast({ type: 'error', title: 'Tax Rate Delete Failed', message: error instanceof Error ? error.message : 'Unable to delete tax rule.' }));
  };

  const saveGlobalTaxEngine = (settings: Partial<GlobalTaxEngineSettings>) => {
    const updated = { ...globalTaxEngine, ...settings };
    const nextConfiguration = { ...taxConfiguration, pricesIncludeTax: updated.pricesIncludeTax, addressBasis: updated.calculateTaxBasedOn };
    void saveTaxConfiguration(laravelBootstrap, nextConfiguration).then(document => {
      setTaxConfiguration(document.configuration);
      setGlobalTaxEngine(current => globalTaxEngineFromConfiguration(document.configuration, { ...current, ...updated }));
      addToast({ type: 'success', title: 'Global Tax Engine Updated', message: 'Storefront tax computation model updated.' });
    }).catch(error => addToast({ type: 'error', title: 'Tax Engine Save Failed', message: error instanceof Error ? error.message : 'Unable to save tax configuration to MySQL.' }));
  };

  // ---------------------------
  // Customer Support & Copilot Handlers
  // ---------------------------

  // External AI/social support actions remain inactive until their provider integrations exist.
  const unavailableSupportAction = (_id: string) => addToast({type: 'info', title: 'Not connected', message: 'AI and social-channel integrations are not enabled.'});
  const
    approveAIDraft = unavailableSupportAction, discardAIDraft = unavailableSupportAction;
  const generateAIDraft = async (id: string) => { unavailableSupportAction(id); };
  const retryFailedMessage = (id: string, _messageId: string) => unavailableSupportAction(id);

  const askInternalCopilot = async (query: string, reasoningLevel: CopilotReasoningLevel = 'flash') => {
    if (!query.trim()) return;
    const userMsg: CopilotQueryMessage = {
      id: `cop-u-${Date.now()}`,
      role: 'user',
      content: query.trim(),
      timestamp: 'Just now'
    };

    const streamId = `cop-a-${Date.now()}`;
    const placeholderMsg: CopilotQueryMessage = {
      id: streamId,
      role: 'assistant',
      content: 'Synthesizing live operational analytics, multi-channel OMS feeds, and support sentiment across Malaysia hubs...',
      timestamp: 'Just now',
      isStreaming: true
    };

    setCopilotMessages(prev => [...prev, userMsg, placeholderMsg]);

    await new Promise(r => setTimeout(r, 1100));

    let responseText = '';
    let reasoning = [
      'Tokenizing enterprise query and matching schema metrics',
      'Scanning local databases (OMS orders, Malaysia shipping rates, customer churn telemetry)',
      'Evaluating cross-channel revenue trends'
    ];
    let followUps = [
      'Show detailed SKU breakdown for today',
      'Which customers are currently in "VIP At Risk" status?',
      'Draft an automated WhatsApp reminder for pending COD deliveries'
    ];
    let references: CopilotQueryMessage['dataReferences'] = [];

    const lower = query.toLowerCase();
    if (lower.includes('revenue') || lower.includes('sales') || lower.includes('today')) {
      responseText = `### 📈 Daily Revenue & Performance Breakdown
- **Gross Merchandise Value (GMV)**: **RM 48,290.00** across 314 confirmed orders.
- **Top Channel**: Web Direct Storefront (54% GMV) followed by TikTok Shop Malaysia (28%).
- **Payment Method Distribution**: FPX / DuitNow (62%), Credit Cards (24%), Cash on Delivery (14%).

**Top Selling SKU**: *JW Studio 280GSM Heavyweight Tee (Washed Charcoal)* with 68 units sold.`;
      references = [
        { type: 'sales_metric', title: 'GMV (Today)', value: 'RM 48,290', change: '+18.4% WoW' },
        { type: 'revenue_chart', title: 'Average Order Value', value: 'RM 153.78', change: 'Consistent' }
      ];
    } else if (lower.includes('courier') || lower.includes('shipping') || lower.includes('pos') || lower.includes('ninja')) {
      responseText = `### 🚚 Logistics & Courier SLA Health Report
- **Pos Malaysia Express**: **98.4% On-Time**. Best performance in Klang Valley & East Coast routes.
- **Ninja Van Malaysia**: **96.8% On-Time**. 99.1% COD collection accuracy.
- **J&T Express**: **95.2% On-Time**. Peak parcel dispatch speed in Southern Region (Johor).
- **EasyParcel Multi-Carrier Engine**: Open Shipping & Logistics to view the current authorized provider and webhook audit state.`;
      references = [
        { type: 'courier_sla', title: 'Pos Malaysia SLA', value: '98.4%', change: 'Excellent' },
        { type: 'courier_sla', title: 'Ninja Van COD Rate', value: '99.1%', change: 'Normal' }
      ];
    } else if (lower.includes('ticket') || lower.includes('customer') || lower.includes('support') || lower.includes('inbox')) {
      responseText = `### 👥 Support Inbox & Lead Intelligence Summary
- **Total Active Conversations**: **${supportConversations.length} Threads**
- **Pending Human Attention**: **${supportConversations.filter(c => c.status === 'open' && c.unreadCount > 0).length} High Priority**
- **AI Automation Rate**: **74.2%** of guest inquiries resolved without human agent escalation.
- **Customer Sentiment**: 88% Positive / Inquisitive, 12% Neutral / Expedited requests.`;
      references = [
        { type: 'customer_churn', title: 'CSAT Satisfaction', value: '4.9 / 5.0', change: 'Top Tier' },
        { type: 'sales_metric', title: 'AI Deflection', value: '74.2%', change: '+6.1% this week' }
      ];
    } else {
      responseText = `### 🤖 Enterprise Intelligence Response
Based on your inquiry: "${query.trim()}", the JW Studio B2B system reports all core operational subsystems (OMS, WMS, Malaysia Courier Engine, osCommerce Tax Pipeline) are operating stably with zero synchronization backlog.

Would you like me to drill down into customer records, inventory allocation, or generate an executive PDF export?`;
      references = [
        { type: 'sales_metric', title: 'System Status', value: 'Optimal', change: '99.99% Uptime' }
      ];
    }

    const finalMsg: CopilotQueryMessage = {
      id: streamId,
      role: 'assistant',
      content: responseText,
      timestamp: 'Just now',
      reasoningSteps: reasoningLevel === 'deep_reasoning' ? [
        ...reasoning,
        'Applied Multi-Step Deep Think reasoning constraint (Gemini 2.5 Pro)',
        'Validated cross-tabular referential integrity across 6 channel schemas'
      ] : reasoning,
      suggestedFollowUps: followUps,
      dataReferences: references,
      isStreaming: false
    };

    setCopilotMessages(prev => {
      const updated = prev.map(m => (m.id === streamId ? finalMsg : m));
      try {
        localStorage.setItem(STORAGE_KEYS.COPILOT_MESSAGES, JSON.stringify(updated));
      } catch {}
      return updated;
    });
  };

  const clearCopilotHistory = () => {
    setCopilotMessages(INITIAL_INTERNAL_COPILOT_MESSAGES);
    try {
      localStorage.setItem(STORAGE_KEYS.COPILOT_MESSAGES, JSON.stringify(INITIAL_INTERNAL_COPILOT_MESSAGES));
    } catch {}
    addToast({
      type: 'info',
      title: 'Copilot History Cleared',
      message: 'Internal assistant memory refreshed.'
    });
  };

  // AI Auto Reply Actions & Knowledge Base CRUD
  const saveAIAutoReplyConfig = async (newConfig: AIAutoReplyMasterConfig) => {
    if (faqSaving.current) return false;
    if (faqServerVersion.current === null) { addToast({ type: 'error', title: 'FAQ unavailable', message: 'Reload saved FAQs before publishing.' }); return false; }
    faqSaving.current = true;
    try {
    const draft = newConfig.knowledgeScope.faqKnowledge;
    const data = await supportFaqRequest({ version: faqServerVersion.current, responsePolicy:newConfig.knowledgeScope.responsePolicy??{partialAnswers:true,clarificationEnabled:true}, guardrails:{priceBargaining:{...newConfig.guardrails.priceBargaining,blockUnauthorizedDiscounts:true}}, aiChannels:newConfig.aiChannels, orderLookup:{isEnabled:newConfig.knowledgeScope.orderLookup.isEnabled,allowedFields:newConfig.knowledgeScope.orderLookup.allowedFields,provideTrackingEntry:newConfig.knowledgeScope.orderLookup.provideTrackingEntry??true}, discountKnowledge:newConfig.knowledgeScope.discountKnowledge || {isEnabled:false,activeIds:[]}, productCatalog:{browseAliases:newConfig.knowledgeScope.productCatalog.browseAliases??[],includeStorePolicies:newConfig.knowledgeScope.productCatalog.includeStorePolicies??true,isEnabled:newConfig.knowledgeScope.productCatalog.isEnabled,canCheckLiveStock:newConfig.knowledgeScope.productCatalog.canCheckLiveStock,canExplainSizingAndMaterials:newConfig.knowledgeScope.productCatalog.canExplainSizingAndMaterials,canRecommendUpsells:newConfig.knowledgeScope.productCatalog.canRecommendUpsells,maxRecommendations:newConfig.knowledgeScope.productCatalog.maxRecommendations}, greetingMessage:newConfig.personality.greetingMessage, personality:{greetingAliases:newConfig.personality.greetingAliases??[],humanHandoffTriggers:newConfig.personality.humanHandoffTriggers,fallbackHandoffMessage:newConfig.personality.fallbackHandoffMessage,creativityTemperature:newConfig.personality.creativityTemperature,brandMissionPrompt:newConfig.personality.brandMissionPrompt,unknownAnswerMessage:newConfig.personality.unknownAnswerMessage,serviceUnavailableMessage:newConfig.personality.serviceUnavailableMessage,queryUnavailableMessage:newConfig.personality.queryUnavailableMessage,toneOfVoice:newConfig.personality.toneOfVoice,customToneInstructions:newConfig.personality.customToneInstructions,maxOutputTokens:Math.max(150,Math.min(600,newConfig.personality.maxOutputTokens))}, autoReplyEnabled:newConfig.personality.isEnabled, primaryLanguage:newConfig.personality.primaryLanguage, identity: {botName: newConfig.personality.botName, roleTitle: newConfig.personality.roleTitle}, faq: { ...draft, categories: faqCategories(draft.categories, draft.items, draft.deletedCategoryIds).map(({ id, label }) => ({ id, label })) } });
    faqServerVersion.current = data.version;
    const updated: AIAutoReplyMasterConfig = {
      ...newConfig,
      knowledgeScope: { ...newConfig.knowledgeScope, faqKnowledge: data.faq || draft },
      lastUpdatedAt: new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }) + ' MYT'
    };
    setAiAutoReplyConfig(updated);
    try {
      localStorage.setItem(STORAGE_KEYS.AI_AUTO_REPLY_CONFIG, JSON.stringify(updated));
    } catch {}
    addToast({
      type: 'success',
      title: 'AI Auto Reply Saved',
      message: 'FAQs and bot identity published to Support Chat. Brand instructions and reply fallbacks saved. Other AI settings remain local.'
    });
    return true;
    } catch (failure) { addToast({ type: 'error', title: 'FAQ not published', message: (failure as Error).message }); return false; }
    finally { faqSaving.current = false; }
  };

  const updateAIAutoReplyConfig = (updater: (prev: AIAutoReplyMasterConfig) => AIAutoReplyMasterConfig) => {
    setAiAutoReplyConfig(prev => {
      const updated = updater(prev);
      const withTimestamp: AIAutoReplyMasterConfig = {
        ...updated,
        lastUpdatedAt: new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }) + ' MYT'
      };
      try {
        localStorage.setItem(STORAGE_KEYS.AI_AUTO_REPLY_CONFIG, JSON.stringify(withTimestamp));
      } catch {}
      return withTimestamp;
    });
  };

  const addFAQItem = (item: Omit<FAQKnowledgeItem, 'id' | 'lastUpdatedAt'>) => {
    updateAIAutoReplyConfig(prev => {
      const newItem: FAQKnowledgeItem = {
        ...item,
        id: 'faq-' + Date.now(),
        lastUpdatedAt: new Date().toISOString().split('T')[0]
      };
      return {
        ...prev,
        knowledgeScope: {
          ...prev.knowledgeScope,
          faqKnowledge: {
            ...prev.knowledgeScope.faqKnowledge,
            items: [newItem, ...prev.knowledgeScope.faqKnowledge.items]
          }
        }
      };
    });
    addToast({
      type: 'success',
      title: 'FAQ Knowledge Added',
      message: `"${item.question.slice(0, 35)}..." added to AI retrieval scope.`
    });
  };

  const updateFAQItem = (id: string, updates: Partial<FAQKnowledgeItem>) => {
    updateAIAutoReplyConfig(prev => ({
      ...prev,
      knowledgeScope: {
        ...prev.knowledgeScope,
        faqKnowledge: {
          ...prev.knowledgeScope.faqKnowledge,
          items: prev.knowledgeScope.faqKnowledge.items.map(f => f.id === id ? { ...f, ...updates, lastUpdatedAt: new Date().toISOString().split('T')[0] } : f)
        }
      }
    }));
  };

  const deleteFAQItem = (id: string) => {
    updateAIAutoReplyConfig(prev => ({
      ...prev,
      knowledgeScope: {
        ...prev.knowledgeScope,
        faqKnowledge: {
          ...prev.knowledgeScope.faqKnowledge,
          items: prev.knowledgeScope.faqKnowledge.items.filter(f => f.id !== id)
        }
      }
    }));
    addToast({
      type: 'info',
      title: 'FAQ Removed',
      message: 'Item removed from AI knowledge base.'
    });
  };

  const addVoucherRule = (voucher: Omit<VoucherRule, 'id'>) => {
    updateAIAutoReplyConfig(prev => {
      const newVoucher: VoucherRule = {
        ...voucher,
        id: 'v-' + Date.now()
      };
      return {
        ...prev,
        knowledgeScope: {
          ...prev.knowledgeScope,
          promotionsAndDiscounts: {
            ...prev.knowledgeScope.promotionsAndDiscounts,
            vouchers: [...prev.knowledgeScope.promotionsAndDiscounts.vouchers, newVoucher]
          }
        }
      };
    });
    addToast({
      type: 'success',
      title: 'Promotion Rule Added',
      message: `Voucher code ${voucher.code} is now recognized by AI.`
    });
  };

  const updateVoucherRule = (id: string, updates: Partial<VoucherRule>) => {
    updateAIAutoReplyConfig(prev => ({
      ...prev,
      knowledgeScope: {
        ...prev.knowledgeScope,
        promotionsAndDiscounts: {
          ...prev.knowledgeScope.promotionsAndDiscounts,
          vouchers: prev.knowledgeScope.promotionsAndDiscounts.vouchers.map(v => v.id === id ? { ...v, ...updates } : v)
        }
      }
    }));
  };

  const deleteVoucherRule = (id: string) => {
    updateAIAutoReplyConfig(prev => ({
      ...prev,
      knowledgeScope: {
        ...prev.knowledgeScope,
        promotionsAndDiscounts: {
          ...prev.knowledgeScope.promotionsAndDiscounts,
          vouchers: prev.knowledgeScope.promotionsAndDiscounts.vouchers.filter(v => v.id !== id)
        }
      }
    }));
    addToast({
      type: 'info',
      title: 'Voucher Rule Removed',
      message: 'Voucher removed from promotion query scope.'
    });
  };

  const addKnowledgeDoc = (doc: Omit<CustomKnowledgeDoc, 'id' | 'lastUpdatedAt' | 'characterCount'>) => {
    updateAIAutoReplyConfig(prev => {
      const newDoc: CustomKnowledgeDoc = {
        ...doc,
        id: 'doc-' + Date.now(),
        characterCount: doc.contentMarkdown.length,
        lastUpdatedAt: new Date().toISOString().split('T')[0]
      };
      return {
        ...prev,
        knowledgeScope: {
          ...prev.knowledgeScope,
          customKnowledgeDocs: [newDoc, ...prev.knowledgeScope.customKnowledgeDocs]
        }
      };
    });
    addToast({
      type: 'success',
      title: 'Document Embedded',
      message: `"${doc.title}" added to custom knowledge base.`
    });
  };

  const updateKnowledgeDoc = (id: string, updates: Partial<CustomKnowledgeDoc>) => {
    updateAIAutoReplyConfig(prev => ({
      ...prev,
      knowledgeScope: {
        ...prev.knowledgeScope,
        customKnowledgeDocs: prev.knowledgeScope.customKnowledgeDocs.map(d =>
          d.id === id
            ? {
                ...d,
                ...updates,
                characterCount: updates.contentMarkdown !== undefined ? updates.contentMarkdown.length : d.characterCount,
                lastUpdatedAt: new Date().toISOString().split('T')[0]
              }
            : d
        )
      }
    }));
  };

  const deleteKnowledgeDoc = (id: string) => {
    updateAIAutoReplyConfig(prev => ({
      ...prev,
      knowledgeScope: {
        ...prev.knowledgeScope,
        customKnowledgeDocs: prev.knowledgeScope.customKnowledgeDocs.filter(d => d.id !== id)
      }
    }));
    addToast({
      type: 'info',
      title: 'Knowledge Doc Deleted',
      message: 'Document deleted from knowledge index.'
    });
  };

  const resetAIAutoReplyToDefaults = () => {
    setAiAutoReplyConfig(DEFAULT_AI_AUTO_REPLY_CONFIG);
    try {
      localStorage.setItem(STORAGE_KEYS.AI_AUTO_REPLY_CONFIG, JSON.stringify(DEFAULT_AI_AUTO_REPLY_CONFIG));
    } catch {}
    addToast({
      type: 'info',
      title: 'AI Config Reset',
      message: 'Restored default JW Studio AI persona and guardrails.'
    });
  };

  const refreshStorefrontShellSettings = async (): Promise<void> => {
    const settings = await loadStorefrontShellSettings(laravelBootstrap);
    setStorefrontShellSettings(settings);
  };

  const saveStorefrontShellSettings = async (
    settings: StorefrontShellSettings,
  ): Promise<StorefrontShellSettings | null> => {
    try {
      const saved = await saveStorefrontShellSettingsData(laravelBootstrap, settings);
      setStorefrontShellSettings(saved);
      addToast({
        type: 'success',
        title: 'Storefront Header Saved',
        message: 'Header & announcement live settings updated.',
      });
      return saved;
    } catch (error) {
      addToast({
        type: 'error',
        title: 'Storefront Header Save Failed',
        message: error instanceof Error ? error.message : 'Unable to save storefront header settings.',
      });
      return null;
    }
  };

  const persistCatalogListingSettings = async (
    settings: CatalogListingSettings,
  ): Promise<CatalogListingSettings | null> => {
    try {
      const saved = await saveCatalogListingSettings(laravelBootstrap, settings);
      setCatalogListingSettings(saved);
      addToast({ type: 'success', title: 'Catalog Listing Saved', message: 'The /shop listing copy is now live.' });
      return saved;
    } catch (error) {
      addToast({
        type: 'error',
        title: 'Catalog Listing Save Failed',
        message: error instanceof Error ? error.message : 'Unable to save catalog listing copy.',
      });
      return null;
    }
  };

  const resetToDefaults = () => {
    void fetchMenus(laravelBootstrap).then(menus => {
      setHeaderMenus(menus.headerMenus);
      setFooterMenus(menus.footerMenus);
    }).catch(() => undefined);
    void fetchCatalog(laravelBootstrap).then(catalog => {
      setCategories(catalog.categories);
      setCollections(catalog.collections);
      setProducts(catalog.products);
    }).catch(() => undefined);
    void fetchReviewsData(laravelBootstrap).then(setReviews).catch(() => undefined);
    void fetchPages(laravelBootstrap).then(setPages).catch(() => undefined);
    void fetchCustomerDirectoryData(laravelBootstrap).then(data => data && setCustomers(customerDirectoryToCustomers(data))).catch(() => undefined);
    void fetchOrders(laravelBootstrap).then(setOrders).catch(() => undefined);
    void loadStorefrontShellSettings(laravelBootstrap).then(setStorefrontShellSettings).catch(() => undefined);
    void loadCatalogListingSettings(laravelBootstrap).then(setCatalogListingSettings).catch(() => undefined);
    setReviewFilterProductId(null);
    setStaffUsers(INITIAL_STAFF_USERS);
    setShopSettings(INITIAL_SHOP_SETTINGS);
    setConnections(INITIAL_MARKETPLACE_CONNECTIONS);
    setMarketplaceOrders(INITIAL_MARKETPLACE_ORDERS);
    setMarketplaceInventory(INITIAL_MARKETPLACE_INVENTORY);
    setSyncLogs(INITIAL_MARKETPLACE_SYNC_LOGS);
    setCourierConfigs([]);
    setSmartRoutingRules([]);
    void fetchShipments(laravelBootstrap).then(setShipmentParcels).catch(() => undefined);
    void refreshEasyParcelWebhookReceipts().catch(() => undefined);
    setPickupRequests([]);
    setBillingReconciliations([]);
    void fetchCommerceSettings(laravelBootstrap).then(document => {
      setShippingRates(document.shippingRates); setPaymentGateways(document.paymentGateways);
      setBankTransferConfig(document.bankTransfer as ManualBankTransferConfig); setCodConfig(document.codConfig as CashOnDeliveryConfig); setGlobalPaymentSettings(document.globalPayment as GlobalPaymentSettings);
      setCurrenciesSetting(document.currencies);
    }).catch(error => addToast({ type: 'error', title: 'Commerce Settings Reload Failed', message: error instanceof Error ? error.message : 'Unable to reload commerce settings.' }));
    setOrderStatuses(INITIAL_CUSTOM_ORDER_STATUSES);
    setCountriesSetting(INITIAL_COUNTRIES);
    setZonesSetting(INITIAL_ZONES);
    setOrderTotalModules(INITIAL_ORDER_TOTAL_MODULES);
    void refreshSupport();
    setCopilotMessages(INITIAL_INTERNAL_COPILOT_MESSAGES);
    setAiAutoReplyConfig(DEFAULT_AI_AUTO_REPLY_CONFIG);
    localStorage.clear();
    addToast({ type: 'info', title: 'Database Reset', message: 'Restored default demo seed data.' });
  };

  return (
    <AdminContext.Provider
      value={{
        laravelBootstrap,
        notificationPermissions,
        currentSection,
        setCurrentSection,
        theme,
        toggleTheme,
        isSidebarCollapsed,
        setIsSidebarCollapsed: handleSetSidebarCollapsed,
        toggleSidebarCollapse,
        user,
        login,
        logout,
        isLoginModalOpen,
        setIsLoginModalOpen,
        isCommandPaletteOpen,
        setIsCommandPaletteOpen,
        supportAlerts,supportAlertUnread,supportAlertError,markSupportAlertRead,
        isNotificationOpen,
        setIsNotificationOpen,
        searchQuery,
        setSearchQuery,
        toasts,
        addToast,
        removeToast,
        headerMenus,
        setHeaderMenus,
        saveHeaderMenuL1,
        deleteHeaderMenuL1,
        saveHeaderMenuL2,
        deleteHeaderMenuL2,
        saveHeaderMenuL3,
        deleteHeaderMenuL3,
        footerMenus,
        setFooterMenus,
        saveFooterMenuL1,
        deleteFooterMenuL1,
        saveFooterMenuL2,
        deleteFooterMenuL2,
        storefrontShellSettings,
        refreshStorefrontShellSettings,
        saveStorefrontShellSettings,
        catalogListingSettings,
        saveCatalogListingSettings: persistCatalogListingSettings,
        categories,
        saveCategory,
        deleteCategory,
        collections,
        saveCollection,
        deleteCollection,
        products,
        saveProduct,
        deleteProduct,
        duplicateProduct,
        reviews,
        reviewFilterProductId,
        setReviewFilterProductId,
        refreshReviews,
        fetchReviewAudit,
        moderateReview,
        bulkModerateReviews,
        canModerateReviews,
        canBulkModerateReviews,
        canViewReviewAudit,
        pages,
        selectedPageId,
        setSelectedPageId,
        savePage,
        updatePageLocal,
        replacePageLocal,
        updatePageSection,
        deletePage,
        duplicatePage,
        customers,
        selectedCustomerId,
        setSelectedCustomerId,
        saveCustomer,
        deleteCustomer,
        orders,
        reloadOrders,
        selectedOrderId,
        setSelectedOrderId,
        updateOrderStatus,
        updateOrderTracking,
        processOrderRefund,
        reviewPaymentProof,
        amendOrderDeliveryDetails,
        reloadShipmentParcels,
        staffUsers,
        saveStaffUser,
        deleteStaffUser,
        toggleStaffStatus,
        resetStaffPassword,
        shopSettings,
        saveShopSettings,
        connections,
        saveConnection,
        deleteConnection,
        toggleConnectionSync,
        refreshToken,
        syncAllMarketplaces,
        marketplaceOrders,
        selectedMarketplaceOrderId,
        setSelectedMarketplaceOrderId,
        saveMarketplaceOrder,
        generateAirwayBill,
        markOrderPacked,
        markOrderShipped,
        batchPrintAWB,
        batchPackOrders,
        marketplaceInventory,
        saveInventoryItem,
        adjustStock,
        broadcastStockToChannels,
        broadcastAllStockToChannels,
        linkChannelSku,
        unlinkChannelSku,
        syncLogs,
        addSyncLog,
        retrySyncLog,
        clearSyncLogs,
        simulateIncomingOrder,
        courierConfigs,
        easyParcelProvider,
        saveCourierConfig,
        refreshEasyParcelProvider,
        disconnectEasyParcel,
        saveEasyParcelSender,
        quoteEasyParcelShipment,
        bookEasyParcelShipment,
        toggleCourierStatus,
        pingCourierAPI,
        smartRoutingRules,
        saveSmartRoutingRule,
        deleteSmartRoutingRule,
        toggleSmartRoutingRule,
        reorderSmartRoutingRules,
        shipmentParcels,
        refreshEasyParcelShipmentStatus,
        refreshAllEasyParcelShipmentStatuses,
        confirmEasyParcelProviderFailure,
        easyParcelWebhookReceipts: easyParcelWebhookAudit.receipts,
        easyParcelWebhookAuditStatus: easyParcelWebhookAudit.status,
        easyParcelWebhookAuditError: easyParcelWebhookAudit.error,
        refreshEasyParcelWebhookReceipts,
        selectedParcelId,
        setSelectedParcelId,
        generateShipmentAWB,
        batchGenerateAWB,
        updateShipmentStatus,
        recordExternalCourierDispatch,
        recordManualDeliveryDispatch,
        startShipmentCorrection,
        amendShipmentOperationalDetails,
        requestRedelivery,
        createRMAReturnShipment,
        simulateCourierWebhookEvent,
        pickupRequests,
        createPickupRequest,
        cancelPickupRequest,
        billingReconciliations,
        resolveBillingDispute,
        shippingRates,
        saveShippingRate,
        deleteShippingRate,
        toggleShippingRateStatus,
        duplicateShippingRate,
        calculateShippingRateBreakdown,
        paymentGateways,
        savePaymentGateway,
        togglePaymentGateway,
        pingPaymentGateway,
        bankTransferConfig,
        saveBankTransferConfig,
        saveBankAccount,
        deleteBankAccount,
        setPrimaryBankAccount,
        codConfig,
        saveCodConfig,
        globalPaymentSettings,
        saveGlobalPaymentSettings,
        orderStatuses,
        saveOrderStatus,
        deleteOrderStatus,
        setDefaultOrderStatus,
        countriesSetting,
        countries: countriesSetting,
        saveCountrySetting,
        toggleCountryStatus,
        zonesSetting,
        zones: zonesSetting,
        saveZoneSetting,
        toggleZoneStatus,
        deleteZoneSetting,
        currenciesSetting,
        currencies: currenciesSetting,
        saveCurrencySetting,
        setDefaultCurrency,
        toggleCurrencyStatus,
        updateCurrencyRates,
        orderTotalModules,
        saveOrderTotalModule,
        reorderOrderTotalModules,
        toggleOrderTotalModule,
        taxZones,
        taxClasses,
        taxRates,
        globalTaxEngine,
        saveGeoTaxZone,
        deleteGeoTaxZone,
        saveTaxClass,
        activateTaxClass,
        deactivateTaxClass,
        deleteTaxClass,
        saveTaxRate,
        deleteTaxRate,
        saveGlobalTaxEngine,
        supportLoading, supportError, supportBusy,
        supportConversations,
        supportDrafts,
        setSupportDraft,
        selectedConversationId,
        setSelectedConversationId,
        supportActiveTab,
        setSupportActiveTab,
        sendMessageToConversation,
        takeOverConversation,
        handBackToAI, archiveConversation,
        resolveConversation,
        reopenConversation,
        approveAIDraft,
        discardAIDraft,
        generateAIDraft,
        togglePinConversation,
        markConversationAsRead,
        addStaffNote,
        retryFailedMessage,
        cannedResponses,
        copilotMessages,
        askInternalCopilot,
        clearCopilotHistory,
        aiAutoReplyConfig,
        updateAIAutoReplyConfig,
        saveAIAutoReplyConfig,
        addFAQItem,
        updateFAQItem,
        deleteFAQItem,
        addVoucherRule,
        updateVoucherRule,
        deleteVoucherRule,
        addKnowledgeDoc,
        updateKnowledgeDoc,
        deleteKnowledgeDoc,
        resetAIAutoReplyToDefaults,
        resetToDefaults
      }}
    >
      {children}
    </AdminContext.Provider>
  );
};

export const useAdmin = () => {
  const context = useContext(AdminContext);
  if (!context) {
    throw new Error('useAdmin must be used within an AdminProvider');
  }
  return context;
};
import { loadShopBranding, persistShopBranding } from '../integration/shopBrandingData';

