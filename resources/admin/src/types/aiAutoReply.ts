export type AIToneOfVoice =
  | 'luxury_professional'
  | 'friendly_helpful'
  | 'concise_efficient'
  | 'energetic_streetwear'
  | 'formal_corporate'
  | 'custom';

export type AIPrimaryLanguage = 'auto_detect' | 'en' | 'ms' | 'zh';

export interface AIPersonalityConfig {
  isEnabled: boolean;
  botName: string;
  avatarUrl: string;
  roleTitle: string;
  toneOfVoice: AIToneOfVoice;
  customToneInstructions?: string;
  primaryLanguage: AIPrimaryLanguage;
  supportedLanguages: string[];
  brandMissionPrompt: string;
  creativityTemperature: number; // 0.0 - 1.0
  maxOutputTokens: number;
  greetingMessage: {
    en: string;
    ms: string;
    zh: string;
  };
  greetingAliases?: string[];
  unknownAnswerMessage: {en:string;ms:string;zh:string};
  queryUnavailableMessage?: {en:string;ms:string;zh:string};
  serviceUnavailableMessage: {en:string;ms:string;zh:string};
  offlineMessage: {
    en: string;
    ms: string;
    zh: string;
  };
  fallbackHandoffMessage: {
    en: string;
    ms: string;
    zh: string;
  };
  humanHandoffTriggers: {
    onFrustrationDetected: boolean;
    consecutiveFailureCount: number;
    onRefundOrDispute: boolean;
    onExplicitRequest: boolean;
    onServiceUnavailable: boolean;
    urgentKeywords: string[];
  };
}

export interface FAQKnowledgeItem {
  id: string;
  category: string;
  question: string;
  answer: string;
  keywords: string[];
  isActive: boolean;
  lastUpdatedAt: string;
}

export interface VoucherRule {
  id: string;
  code: string;
  discountType: 'percentage' | 'fixed_myr' | 'free_shipping';
  value: number;
  minSpend: number;
  description: string;
  expiresAt: string;
  isActive: boolean;
  publiclyDisclosable: boolean;
}

export interface CustomKnowledgeDoc {
  id: string;
  title: string;
  category: string;
  contentMarkdown: string;
  tags: string[];
  isActive: boolean;
  characterCount: number;
  lastUpdatedAt: string;
}

export interface AIKnowledgeScopeConfig {
  responsePolicy?: {partialAnswers:boolean;clarificationEnabled:boolean};
  faqKnowledge: {
    categories?: { id: string; label: string }[];
    deletedCategoryIds?: string[];
    isEnabled: boolean;
    retrievalDepth: 'strict' | 'semantic' | 'deep';
    items: FAQKnowledgeItem[];
  };
  orderLookup: {
    isEnabled: boolean;
    provideTrackingEntry?: boolean;
    requiresVerification: boolean; // Phone or Email confirmation required before order status release
    verificationMethod: 'phone_last4' | 'full_phone' | 'email_match';
    allowedFields: {
      orderStatus: boolean;
      trackingNumberAndCourier: boolean;
      estimatedDeliveryDate: boolean;
      itemSummary: boolean;
      shippingAddressMasked: boolean;
      paymentStatus: boolean;
    };
    allowAddressChangeRequest: boolean;
    allowCancellationRequest: boolean;
  };
  productCatalog: {
    isEnabled: boolean;
    includeStorePolicies?: boolean;
    browseAliases?: string[];
    canCheckLiveStock: boolean;
    canRecommendUpsells: boolean;
    canExplainSizingAndMaterials: boolean;
    includeOutOfStockAlternatives: boolean;
    maxRecommendations: number;
    allowedCategories: string[];
  };
  discountKnowledge?: {isEnabled:boolean;activeIds:string[]};
  promotionsAndDiscounts: {
    isEnabled: boolean;
    canDiscloseActiveVouchers: boolean;
    canCalculateEstimatedSavings: boolean;
    strictNoPriceBargaining: boolean;
    vouchers: VoucherRule[];
  };
  customKnowledgeDocs: CustomKnowledgeDoc[];
}

export interface AIGuardrailsConfig {
  competitorMentions: {
    blockOrNeutralize: boolean;
    blacklistedCompetitors: string[];
    neutralizeStrategy: 'redirect_to_store_advantages' | 'neutral_polite_refusal';
    customCannedResponse: string;
  };
  priceBargaining: {
    blockUnauthorizedDiscounts: boolean;
    suggestOfficialVouchersOnly: boolean;
    refusalTemplate: string;
    refusalMessages?: {ms: string; zh: string};
  };
  antiHallucinationRules: {
    strictStoreContextOnly: boolean;
    confidenceThresholdCutoff: number; // 50 - 95
    refuseNonEcommerceQueries: boolean;
    nonEcommerceRefusalMessage: string;
  };
  piiDataMasking: {
    maskCreditCards: boolean;
    maskNationalIds: boolean;
    maskFullAddresses: boolean;
    maskPhoneNumbers: boolean;
  };
  profanityAndHarassment: {
    profanityFilterLevel: 'strict' | 'moderate' | 'lenient';
    banAfterAbuseCount: number;
    customForbiddenPhrases: string[];
  };
}

export interface AIAutoReplyMasterConfig {
  replyCadence?: ReplyCadenceConfig;
  customerMemory?: {enabled:boolean;retentionDays:number};
  attachmentAnalysis?: AttachmentAnalysisConfig;
  attachmentReadiness?: {image:boolean;document:boolean;audio:boolean;provider:string|null;model:string|null};
  aiChannels?: Record<'storefront'|'facebook'|'instagram'|'whatsapp'|'tiktok',boolean>;
  isAutoReplyGloballyEnabled: boolean;
  activeChannels: {
    storefrontWebChat: boolean;
    whatsapp: boolean;
    instagramDm: boolean;
    tiktokShopChat: boolean;
    shopeeChat: boolean;
    lazadaChat: boolean;
  };
  personality: AIPersonalityConfig;
  knowledgeScope: AIKnowledgeScopeConfig;
  guardrails: AIGuardrailsConfig;
  lastUpdatedBy: string;
  lastUpdatedAt: string;
}

export interface AttachmentAnalysisConfig {
  image:boolean; document:boolean; audio:boolean;
  pdfPages:number; audioSeconds:number; retentionDays:number;
  transcriptionModel:'gpt-4o-mini-transcribe'|'gpt-4o-transcribe';
}
export interface ReplyCadenceConfig {enabled:boolean;collectionSeconds:number;intervalSeconds:number;maxSegments:number;}

export interface AISimulatorMessage {
  id: string;
  sender: 'customer' | 'ai' | 'system';
  text: string;
  timestamp: string;
  guardrailTriggered?: {
    ruleName: string;
    actionTaken: 'intercepted' | 'modified' | 'approved' | 'escalated';
    reason: string;
  };
  retrievedContextSources?: string[];
  confidenceScore?: number;
  latencyMs?: number;
}
