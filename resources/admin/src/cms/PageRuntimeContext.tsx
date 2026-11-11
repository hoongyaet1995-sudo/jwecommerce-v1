import {builderNavigationHref} from './builderNavigation';
import {resolvePlanComparison,isPlanComparisonFacts,type PlanComparisonFacts} from './planComparison';
import {resolveStarterPricing,isStarterPricing,type StarterPricing} from './websiteBuilderStarterFaq';
import {validSaasPricingSection} from './saasPackages';
import {useCmsBrand} from './useCmsBrand';
import {isShellDesign} from './shellDesign';
import {isHeaderPresentation} from './headerPresentation';
import {isPalette,isPageStyle} from './pageStyle';
import {isPromotionOffers,type PromotionOffer} from './promotionHighlights';
import {isTypography} from './inspectorCapabilities';
import {isPaymentFacts, type PaymentFacts} from './paymentInformation';
import {deliveryRows} from './deliveryInformation';
import React, { createContext, useContext, useEffect, useState } from 'react';
import type { Category, CmsPage, Collection, PageDocument, PageSection, Product } from '../types';
import { SectionRenderer } from '../components/pages/SectionRenderer';
import { imageTargets, imageSlotFamilies } from './imageTargets';

export interface PageRuntimeProduct {
  purchaseMode?: 'buy' | 'quote' | 'display';
  priceDisplay?: 'exact' | 'from' | 'hide';
  id: string;
  title: string;
  slug: string;
  brand: string;
  summary: string;
  images: string[];
  tags: string[];
  basePrice: number;
  baseCompareAtPrice: number | null;
  commerce?: { variantId: string | null; sku: string; price: number; compareAtPrice: number | null; optionLabels: string[]; canChoose: boolean; priceLabel?: string; compareAtLabel?: string; variants?: Array<{id:string;label:string;sku:string;price:number;compareAtPrice:number|null;stock:number;enabled:boolean;image:string|null;color?:string|null;priceLabel?:string;compareAtLabel?:string}> };
}

export interface PageRuntimeSource {
  id: string;
  type: 'category' | 'collection';
  slug: string;
  label: string;
  image: string | null;
}

export interface PageRuntimeSectionFacts {
  starterPricing?: StarterPricing;
  planComparison?:PlanComparisonFacts;
  promotions?: {offers:PromotionOffer[]};
  payments?: PaymentFacts;
  delivery?: {rows: import('./deliveryInformation').DeliveryRow[]; status?: 'ready'|'unavailable'};
  listing?: {sourceId: {valid: boolean}; source: PageRuntimeSource | null; products: PageRuntimeProduct[]};
  productIds?: {
    valid: boolean;
    products: PageRuntimeProduct[];
    invalidReferenceIds: unknown[];
  };
  tabs?: Array<{
    sourceId: {valid: boolean};
    source: PageRuntimeSource | null;
    products: PageRuntimeProduct[];
  }> | Record<number, {
    sourceId: {valid: boolean};
    source: PageRuntimeSource | null;
    products: PageRuntimeProduct[];
  }>;
  newsletterCouponId?: {
    valid: boolean;
    coupon: {id: string} | null;
  };
}

export interface CmsFormSubmissionContract {
  endpoint: string;
  pageId: string;
  stage: 'draft' | 'published';
  documentHash: string;
  lockVersion: number;
  sectionId: string;
  sectionType: 'contact_form' | 'newsletter_signup';
  audience: 'admin-preview' | 'protected-canary' | 'public-storefront';
}

export interface PageRuntimeForms {
  contact: Record<string, CmsFormSubmissionContract>;
  newsletter: Record<string, CmsFormSubmissionContract>;
  adminContractsEndpoint?: string;
}

export interface PageRuntimePayload {
  page: {
    id: string;
    status: CmsPage['status'];
    stage: 'draft' | 'published';
    schemaVersion: number;
    lockVersion: number;
    contentHash: string;
    document: PageDocument;
  };
  catalog: {
    products: PageRuntimeProduct[];
    categories: PageRuntimeSource[];
    collections: PageRuntimeSource[];
  };
  forms: PageRuntimeForms;
  links: Record<string, string>;
  runtime: {
    sections: Record<number, PageRuntimeSectionFacts>;
    errors: Record<string, string[]>;
  };
  context: {
    mode: 'editing' | 'preview' | 'public';
    currency: string;
    themePalette?: import('./pageStyle').BrandPalette;
    wishlist?: {authenticated:boolean;csrfToken:string;savedProductIds:string[];loginUrl:string};
  };
}

interface AdminRuntimeCatalog {
  products: Product[];
  categories: Category[];
  collections: Collection[];
}

const PageRuntimeContext = createContext<PageRuntimePayload | null>(null);

export const PageRuntimeProvider: React.FC<{
  payload: PageRuntimePayload;
  children: React.ReactNode;
}> = ({ payload, children }) => {
  const brand=useCmsBrand(payload.context.mode==='editing')?.palette;
  const value=brand?{...payload,context:{...payload.context,themePalette:brand}}:payload;
  return <PageRuntimeContext.Provider value={value}>{children}</PageRuntimeContext.Provider>;
};

export const usePageRuntime = (): PageRuntimePayload => {
  const payload = useContext(PageRuntimeContext);
  if (!payload) throw new Error('PageRuntimeProvider is required.');
  return payload;
};

export const usePageSectionRuntime = (sectionId: string): PageRuntimeSectionFacts => {
  const payload = usePageRuntime();
  const index = payload.page.document.sections.findIndex(section => section.id === sectionId);
  return index < 0 ? {} : (payload.runtime.sections[index] ?? {});
};

export const PageRuntimeUnavailable: React.FC = () => (
  <p data-cms-page-unavailable role="alert">Page content is unavailable.</p>
);

const isRecord = (value: unknown): value is Record<string, unknown> => (
  typeof value === 'object' && value !== null && !Array.isArray(value)
);

const hasOnlyKeys = (value: Record<string, unknown>, keys: readonly string[]): boolean => (
  Object.keys(value).every(key => keys.includes(key))
);

const isStringArray = (value: unknown): value is string[] => (
  Array.isArray(value) && value.every(item => typeof item === 'string')
);

const isNullableString = (value: unknown): value is string | null => (
  value === null || typeof value === 'string'
);

const isFiniteNumber = (value: unknown): value is number => (
  typeof value === 'number' && Number.isFinite(value)
);

const formContractFields = ['endpoint', 'pageId', 'stage', 'documentHash', 'lockVersion', 'sectionId', 'sectionType', 'audience'];

const isExactContractEndpoint = (endpoint: string, contract: Record<string, unknown>): boolean => {
  if (!endpoint.startsWith('/') || endpoint.startsWith('//')) return false;
  try {
    const parsed = new URL(endpoint, 'https://cms-runtime.invalid');
    if (parsed.origin !== 'https://cms-runtime.invalid') return false;
    const kind = contract.sectionType === 'contact_form' ? 'contact' : 'newsletter';
    if (parsed.pathname !== `/cms/forms/${kind}/${contract.pageId}/${contract.stage}/${encodeURIComponent(String(contract.sectionId))}`) return false;
    const keys = [...parsed.searchParams.keys()];
    if (keys.length !== 6 || new Set(keys).size !== keys.length || !keys.every(key => ['expires', 'hash', 'version', 'type', 'audience', 'signature'].includes(key))) return false;
    return /^\d+$/.test(parsed.searchParams.get('expires') ?? '')
      && parsed.searchParams.get('hash') === contract.documentHash
      && parsed.searchParams.get('version') === String(contract.lockVersion)
      && parsed.searchParams.get('type') === contract.sectionType
      && parsed.searchParams.get('audience') === contract.audience
      && /^[a-f0-9]{40,128}$/i.test(parsed.searchParams.get('signature') ?? '');
  } catch {
    return false;
  }
};

export const isCmsFormSubmissionContract = (
  value: unknown,
  page: Pick<PageRuntimePayload['page'], 'id' | 'stage' | 'contentHash' | 'lockVersion'>,
  section: Pick<PageSection, 'id' | 'type'>,
): value is CmsFormSubmissionContract => (
  isRecord(value)
  && hasOnlyKeys(value, formContractFields)
  && typeof value.endpoint === 'string'
  && value.pageId === page.id
  && value.stage === page.stage
  && value.documentHash === page.contentHash
  && value.lockVersion === page.lockVersion
  && value.sectionId === section.id
  && value.sectionType === section.type
  && (value.sectionType === 'contact_form' || value.sectionType === 'newsletter_signup')
  && (value.audience === 'admin-preview' || value.audience === 'protected-canary' || value.audience === 'public-storefront')
  && isExactContractEndpoint(value.endpoint, value)
);

const isRuntimeForms = (value: unknown, page: PageRuntimePayload['page']): value is PageRuntimeForms => {
  if (!isRecord(value) || !hasOnlyKeys(value, ['contact', 'newsletter', 'adminContractsEndpoint']) || !isRecord(value.contact) || !isRecord(value.newsletter)) return false;
  if ('adminContractsEndpoint' in value && (typeof value.adminContractsEndpoint !== 'string'
    || value.adminContractsEndpoint !== `/admin/api/pages/${page.id}/form-contracts`)) return false;
  const sectionFor = (id: string, type: 'contact_form' | 'newsletter_signup') => page.document.sections.find(section => section.id === id && section.type === type);
  return Object.entries(value.contact).every(([id, contract]) => {
    const section = sectionFor(id, 'contact_form');
    return section !== undefined && isCmsFormSubmissionContract(contract, page, section);
  }) && Object.entries(value.newsletter).every(([id, contract]) => {
    const section = sectionFor(id, 'newsletter_signup');
    return section !== undefined && isCmsFormSubmissionContract(contract, page, section);
  });
};

const runtimeProductFields = [
  'id', 'title', 'slug', 'brand', 'summary', 'images', 'tags', 'basePrice', 'baseCompareAtPrice', 'commerce', 'purchaseMode', 'priceDisplay',
];
const runtimeSourceFields = ['id', 'type', 'slug', 'label', 'image'];

const sectionTypes = new Set([
  'hero_banner', 'split_hero', 'hero_slider', 'announcement_bar', 'rich_text',
  'payment_methods', 'policy_content', 'delivery_table', 'media_text_split', 'chapter_block', 'chapter_navigation', 'timeline_story', 'stats_counter', 'product_grid', 'product_listing', 'collection_tabs',
  'featured_deal', 'pricing_table', 'media_gallery', 'video_embed', 'before_after',
  'instagram_feed', 'feature_cards', 'values_list', 'icon_list', 'comparison_matrix', 'faq_accordion',
  'contact_form', 'store_locator', 'testimonials_slider', 'logo_cloud', 'cta_strip',
  'newsletter_signup',
  'shop_navigation', 'promotion_highlights',
]);

const sectionItemStringFields = ['pricingMode','pricingLabel','billingLabel','highlightLabel',
  'deliveryEstimate', 'deliveryFee', 'subtitle', 'description', 'icon', 'imageUrl', 'videoUrl', 'linkUrl', 'badge',
  'author', 'authorRole', 'authorAvatar', 'date', 'category', 'value', 'handle',
  'sourceId', 'sourceType', 'caption', 'thirdButtonText', 'thirdButtonLink', 'metricLabel', 'buttonText', 'secondaryButtonText', 'secondaryButtonLink',
];
const sectionItemNumberFields = ['price', 'compareAtPrice'];
const sectionColorFields = [
  'eyebrowColor', 'titleColor', 'contentColor', 'primaryBtnBg', 'primaryBtnText',
  'secondaryBtnBg', 'secondaryBtnText', 'customBgColor',
];

type PageSectionFieldRule =
  | {kind: 'required-string'}
  | {kind: 'required-boolean'}
  | {kind: 'section-type'}
  | {kind: 'optional-string'}
  | {kind: 'optional-number'}
  | {kind: 'optional-boolean'}
  | {kind: 'optional-string-or-number'}
  | {kind: 'optional-enum'; values: readonly (string | number)[]}
  | {kind: 'nested'};

// `satisfies Record<keyof PageSection, ...>` makes a PageSection type change fail compilation
// until its browser-bootstrap rule is deliberately classified here.
export const PAGE_SECTION_BOOTSTRAP_FIELD_RULES = {
 pricingPreset:{kind:'optional-enum',values:['default','saas-packages','plan-comparison']},pricingCurrency:{kind:'optional-string'},
  newsletterPreset:{kind:'optional-enum',values:['default','inline-signup']},
  surface:{kind:'optional-enum',values:['light','contrast']},
  brandPalette:{kind:'nested'},
  imageSlots: {kind: 'nested'},
  promotionSource:{kind:'optional-enum',values:['automatic','manual']},
  promotionLayout:{kind:'optional-enum',values:['side','above']},
  showOfferBadges:{kind:'optional-boolean'},
  typography: {kind:'nested'},
  motionPreset: {kind: 'optional-enum', values: ['subtle-lift', 'fade-in', 'smooth-zoom', 'dynamic-pop', 'none']},
  id: {kind: 'required-string'},
  type: {kind: 'section-type'},
  title: {kind: 'required-string'},
  subtitle: {kind: 'optional-string'},
  content: {kind: 'optional-string'},
  quote: {kind: 'optional-string'},
  authorName: {kind: 'optional-string'},
  authorRole: {kind: 'optional-string'},
  authorAvatar: {kind: 'optional-string'},
  mediaUrl: {kind: 'optional-string'},
  mediaType: {kind: 'optional-enum', values: ['image', 'video']},
  mediaPosition: {kind: 'optional-enum', values: ['left', 'right']},
  imageRatio: {kind: 'optional-enum', values: ["default", "auto", "16:9", "3:2", "4:3", "1:1", "3:4"]},
  imagePosition: {kind: 'optional-enum', values: ["top-left", "top-center", "top-right", "center-left", "center", "center-right", "bottom-left", "bottom-center", "bottom-right"]},
  imageFit: {kind: 'optional-enum', values: ["cover", "contain"]},
  imageWidth: {kind: 'optional-enum', values: ["100", "75", "50"]},
  imageShape: {kind: 'optional-enum', values: ["default", "rectangle", "rounded", "circle"]},
  imageOverlay: {kind: 'optional-boolean'},
  imageOverlayOpacity: {kind: 'optional-enum', values: [0, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100]},
  imageForeground: {kind: 'optional-boolean'},
  imageForegroundColor: {kind: 'optional-string'},
  imageForegroundOpacity: {kind: 'optional-enum', values: [0, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100]},
  shopNavigationPreset: {kind: 'optional-enum', values: ['cards','collection-banners','kids-banners','fashion-banners','market-hero','market-offers','image-overlay','image-caption','catalog-captions','recipe-cards','age-cards']},
  bannerProportions: {kind: 'optional-enum', values: ['50:50','60:40','40:60']},
  bannerHeight: {kind: 'optional-enum', values: ['compact','comfortable','tall']},
  layoutPreset: {kind: 'optional-enum', values: ['corporate-image-story','corporate-payments','corporate-courier','corporate-marketplace','corporate-section-story','image-highlights','team-story-overview','corporate-builder-overview','corporate-support','corporate-feature','saas-hero','saas-showcase','saas-analytics','default', 'editorial-hero', 'editorial-story', 'editorial-cover', 'editorial-story-cards','editorial-journal-rows', 'story-with-steps','flush-split','producer-story','tech-feature','kids-discovery', 'artisan-story']},
  containerMode: {kind: 'optional-enum', values: ['page','full-bleed']},
  sectionBorder: {kind: 'optional-enum', values: ['default','none','top','bottom','both']},
  sectionBorderColor: {kind: 'optional-string'},
  ctaPreset: {kind: 'optional-enum', values: ['editorial-illustrated','illustrated-panel','gradient-panel','saas-closing','default','image-banner','editorial-band','help-panel','industrial-outline']},
  leadText: {kind:'optional-string'},
  policyPreset:{kind:'optional-enum',values:['guidelines','choices','steps','document','directory','process-flow','timeline','tabs','columns']},
  policyNote:{kind:'optional-string'},
  contactPreset:{kind:'optional-enum',values:['enquiry','location']},
  mapEmbedUrl:{kind:'optional-string'},
  mapVisible:{kind:'optional-boolean'},
  contactAddressIcon:{kind:'optional-string'},
  paymentLayout:{kind:'optional-enum',values:['cards','list']},
  paymentNote:{kind:'optional-string'},
  deliverySource:{kind:'optional-enum',values:['settings','manual']},
  deliveryNote:{kind:'optional-string'},
  deliveryDestinationLabel:{kind:'optional-string'},
  deliveryEstimateLabel:{kind:'optional-string'},
  deliveryFeeLabel:{kind:'optional-string'},
  planContentSource:{kind:'optional-enum',values:['manual','reference']},planName:{kind:'optional-string'},planAmount:{kind:'optional-number'},planCurrency:{kind:'optional-string'},planPeriod:{kind:'optional-string'},planTerms:{kind:'optional-string'},planDescription:{kind:'optional-string'},planPricingMode:{kind:'optional-enum',values:['amount','contact']},planPriceLabel:{kind:'optional-string'},
  pricingSourcePageId:{kind:'optional-string'},pricingSourceSectionId:{kind:'optional-string'},pricingSourceItemId:{kind:'optional-string'},pricingSourceTermsSectionId:{kind:'optional-string'},
  faqPreset:{kind:'optional-enum',values:['pricing-band-faq','corporate-starter-faq','framed-faq','saas-split','default','minimal','industrial-split']},
  faqInitiallyOpen:{kind:'optional-boolean'},
  chapterPreset: {kind:'optional-enum',values:['story','perspective','principles','process','delivery','actions','policy']},
  mediaContainerMode: {kind:'optional-enum',values:['page','full-bleed']},
  ctaBannerHeight: {kind: 'optional-enum', values: ['compact','normal','spacious']},
  richTextPreset: {kind: 'optional-enum', values: ['default', 'manifesto','industrial-split']},
  featureCardsPreset: {kind: 'optional-enum', values: ['saas-modules','saas-addons','default', 'values-list', 'editorial-columns','numbered-process','service-highlights','industrial-cards','industrial-process','industrial-process-split']},
  heroBannerPreset:{kind:'optional-enum',values:['default','editorial-gradient','industrial-overlay','corporate-centered','corporate-split-composite','corporate-centered-composite']},
  testimonialPreset:{kind:'optional-enum',values:['default','editorial-quote']},
  logoPreset:{kind:'optional-enum',values:['classic','partners']},
  logoHeight:{kind:'optional-number'},
  logoGap:{kind:'optional-number'},
  marqueeSpeed:{kind:'optional-number'},
  marqueeDirection:{kind:'optional-enum',values:['left','right']},
  marqueePauseOnHover:{kind:'optional-boolean'},
  galleryPreset: {kind: 'optional-enum', values: ['focused-steps','scroll-stack-cards','editorial-knowledge-triptych','image-flow','connected-feature-rows','image-cards-four-mosaic','image-cards-three','corporate-connections','corporate-illustrated-steps','corporate-scenarios','product-preview-tabs','photo-marquee','default', 'editorial-montage', 'editorial-cards','editorial-triptych','portfolio-cards','routine-steps','industrial-mosaic','album-browser']},
  galleryFilters:{kind:'optional-boolean'}, galleryLightbox:{kind:'optional-boolean'}, galleryInitialCount:{kind:'optional-number'},galleryLoadCount:{kind:'optional-number'},
  showSectionHeading: {kind: 'optional-boolean'},
  valuesShowHeading: {kind: 'optional-boolean'},
  valuesNumberColor: {kind: 'optional-string'},
  valuesDividerColor: {kind: 'optional-string'},
  valuesShowDividers: {kind: 'optional-boolean'},
  valuesRowSpacing: {kind: 'optional-enum', values: ['compact','normal','spacious']},
  mediaAlt: {kind: 'optional-string'},
  mediaFocalPoint: {kind: 'optional-enum', values: ['center', 'top', 'bottom', 'left', 'right']},
  mediaAspectRatio: {kind: 'optional-enum', values: ['16:9', '4:3', '1:1', 'auto']},
  buttonText: {kind: 'optional-string'},
  buttonLink: {kind: 'optional-string'},
  buttonStyle: {kind: 'optional-enum', values: ['button', 'text-link']},
  secondaryButtonText: {kind: 'optional-string'},
  secondaryButtonLink: {kind: 'optional-string'},
  textAlignment: {kind: 'optional-enum', values: ['left', 'center', 'right']},
  backgroundColor: {kind: 'optional-string'},
  customColors: {kind: 'nested'},
  textColor: {kind: 'optional-enum', values: ['light', 'dark']},
  paddingY: {kind: 'optional-enum', values: ['compact', 'normal', 'spacious']},
  overlayOpacity: {kind: 'optional-number'},
  videoEmbedUrl: {kind: 'optional-string'},
  isVisible: {kind: 'required-boolean'},
  items: {kind: 'nested'},
  productIds: {kind: 'nested'},
  productTabs: {kind: 'nested'},
  sourceType: {kind: 'optional-enum', values: ['category', 'collection', 'manual']},
  listingPreset: {kind: 'optional-enum', values: ['industrial-related','default', 'editorial-grid', 'editorial-tabs','fashion-lookbook','beauty-grid','pantry-grid','pantry-tabs','tech-grid','tech-tabs','kids-grid','kids-tabs']},
  sourceId: {kind: 'optional-string'},
  productLimit: {kind: 'optional-number'},
  autoSlide: {kind: 'optional-boolean'},
  autoSlideInterval: {kind: 'optional-number'},
  showNavArrows: {kind: 'optional-boolean'},
  columnsCount: {kind: 'optional-enum', values: [2, 3, 4, 5, 6]},
  customCssClass: {kind: 'optional-string'},
  beforeImageUrl: {kind: 'optional-string'},
  afterImageUrl: {kind: 'optional-string'},
  beforeLabel: {kind: 'optional-string'},
  afterLabel: {kind: 'optional-string'},
  dealEndDate: {kind: 'optional-string'},
  discountPercent: {kind: 'optional-number'},
  dealBadge: {kind: 'optional-string'},
  formEmail: {kind: 'optional-string'},
  formSuccessMessage: {kind: 'optional-string'},
  contactPhone: {kind: 'optional-string'},
  contactAddress: {kind: 'optional-string'},
  contactHours: {kind: 'optional-string'},
  contactButtonText: {kind: 'optional-string'},
  tickerSpeed: {kind: 'optional-enum', values: ['slow', 'normal', 'fast']},
  tabCategories: {kind: 'nested'},
  tabs: {kind: 'nested'},
  newsletterCouponId: {kind: 'optional-string'},
  subjectPlaceholder: {kind: 'optional-string'},
  categoryPlaceholder: {kind: 'optional-string'},
  messagePlaceholder: {kind: 'optional-string'},
  inquiryCategories: {kind: 'nested'},
  comparisonDimensionName: {kind: 'optional-string'},
  comparisonCompetitorName: {kind: 'optional-string'},
  comparisonPreset:{kind:'optional-enum',values:['default','device-table']},
  comparisonOurBrandName: {kind: 'optional-string'},
  cardBadge: {kind: 'optional-string'},
  cardTitle: {kind: 'optional-string'},
  cardSubtitle: {kind: 'optional-string'},
  cardPrice: {kind: 'optional-string-or-number'},
  cardExtra: {kind: 'optional-string'},
  sliderInterval: {kind: 'optional-number'},
  sliderAutoplay: {kind: 'optional-boolean'},
  sliderPreset: {kind: 'optional-enum', values: ['default', 'editorial-overlay','fashion-editorial']},
  sliderHeight: {kind: 'optional-enum', values: ['panoramic', 'compact', 'normal', 'spacious', 'fullscreen']},
} as const satisfies Record<keyof PageSection, PageSectionFieldRule>;

const isSectionItem = (value: unknown): boolean => {
  if (!isRecord(value) || typeof value.id !== 'string' || typeof value.title !== 'string') return false;
  if (!sectionItemStringFields.every(field => !(field in value) || typeof value[field] === 'string')) return false;
  if ('sourceType' in value && !['category','collection','custom'].includes(String(value.sourceType))) return false;
  if (!sectionItemNumberFields.every(field => !(field in value) || isFiniteNumber(value[field]))) return false;
  if ('comparisonCells' in value && (!Array.isArray(value.comparisonCells) || !value.comparisonCells.every(cell=>isRecord(cell)&&typeof cell.deviceId==='string'&&typeof cell.value==='string'))) return false;
  if ('features' in value && !isStringArray(value.features)) return false;
  if ('isHighlighted' in value && typeof value.isHighlighted !== 'boolean') return false;
  if ('rating' in value && (!Number.isInteger(value.rating) || Number(value.rating) < 1 || Number(value.rating) > 5)) {
    return false;
  }

  return true;
};

const isRuntimeProduct = (value: unknown): value is PageRuntimeProduct => isRecord(value)
  && hasOnlyKeys(value, runtimeProductFields)
  && typeof value.id === 'string'
  && typeof value.title === 'string'
  && typeof value.slug === 'string'
  && typeof value.brand === 'string'
  && typeof value.summary === 'string'
  && isStringArray(value.images)
  && isStringArray(value.tags)
  && (!('purchaseMode' in value) || ['buy','quote','display'].includes(value.purchaseMode as string))
  && (!('priceDisplay' in value) || ['exact','from','hide'].includes(value.priceDisplay as string))
  && isFiniteNumber(value.basePrice)
  && (value.baseCompareAtPrice === null || isFiniteNumber(value.baseCompareAtPrice))
  && (!('commerce' in value) || (isRecord(value.commerce)
    && hasOnlyKeys(value.commerce, ['variantId','sku','price','compareAtPrice','optionLabels','canChoose','priceLabel','compareAtLabel','variants'])
    && (!('variants' in value.commerce) || (Array.isArray(value.commerce.variants) && value.commerce.variants.every(v => isRecord(v)
      && hasOnlyKeys(v,['id','label','sku','price','compareAtPrice','stock','enabled','image','color','priceLabel','compareAtLabel'])
      && typeof v.id === 'string' && typeof v.label === 'string' && typeof v.sku === 'string'
      && isFiniteNumber(v.price) && (v.compareAtPrice === null || isFiniteNumber(v.compareAtPrice))
      && isFiniteNumber(v.stock) && typeof v.enabled === 'boolean' && isNullableString(v.image)
      && (!('color' in v) || v.color===null || typeof v.color==='string' && /^#[a-f\d]{6}$/i.test(v.color))
      && (!('priceLabel' in v) || typeof v.priceLabel === 'string') && (!('compareAtLabel' in v) || typeof v.compareAtLabel === 'string'))))
    && isNullableString(value.commerce.variantId) && typeof value.commerce.sku === 'string'
    && isFiniteNumber(value.commerce.price) && (value.commerce.compareAtPrice === null || isFiniteNumber(value.commerce.compareAtPrice))
    && isStringArray(value.commerce.optionLabels) && typeof value.commerce.canChoose === 'boolean'
    && (!('priceLabel' in value.commerce) || typeof value.commerce.priceLabel === 'string')
    && (!('compareAtLabel' in value.commerce) || typeof value.commerce.compareAtLabel === 'string')));

const isRuntimeSource = (value: unknown, type: PageRuntimeSource['type']): value is PageRuntimeSource => (
  isRecord(value)
  && hasOnlyKeys(value, runtimeSourceFields)
  && typeof value.id === 'string'
  && value.type === type
  && typeof value.slug === 'string'
  && typeof value.label === 'string'
  && isNullableString(value.image)
);

const isPageSection = (value: unknown): value is PageSection => {
 if(!validSaasPricingSection(value))return false;
  if(isRecord(value)&&'heroBannerPreset' in value&&value.type!=='hero_banner')return false;
  if(isRecord(value)&&'testimonialPreset' in value&&value.type!=='testimonials_slider')return false;
  if(isRecord(value)&&['marqueeSpeed','marqueeDirection','marqueePauseOnHover'].some(key=>key in value)&&!['media_gallery','logo_cloud'].includes(String(value.type)))return false;
  if(isRecord(value)&&['logoPreset','logoHeight','logoGap'].some(key=>key in value)&&value.type!=='logo_cloud')return false;
  if(isRecord(value)&&'logoHeight' in value&&(!Number.isInteger(value.logoHeight)||Number(value.logoHeight)<24||Number(value.logoHeight)>96))return false;
  if(isRecord(value)&&'logoGap' in value&&(!Number.isInteger(value.logoGap)||Number(value.logoGap)<16||Number(value.logoGap)>100))return false;
  if(isRecord(value)&&'marqueeSpeed' in value&&(!Number.isInteger(value.marqueeSpeed)||Number(value.marqueeSpeed)<0||Number(value.marqueeSpeed)>100))return false;
  if(isRecord(value)&&value.type==='promotion_highlights'&&Array.isArray(value.items)&&value.items.length>6)return false;
  if(isRecord(value)&&['promotionSource','promotionLayout','showOfferBadges'].some(k=>k in value)&&value.type!=='promotion_highlights')return false;
  if(isRecord(value)&&['shopNavigationPreset','bannerProportions','bannerHeight'].some(key=>key in value)&&value.type!=='shop_navigation')return false;
  if (isRecord(value) && ('listingPreset' in value || 'productTabs' in value || value.sourceType === 'manual') && value.type !== 'product_listing') return false;
  if (isRecord(value) && 'galleryPreset' in value && value.type !== 'media_gallery') return false;
  if (isRecord(value) && 'showSectionHeading' in value && !['media_gallery','feature_cards','policy_content','chapter_block','shop_navigation'].includes(String(value.type))) return false;
  if (isRecord(value) && ['ctaPreset','ctaBannerHeight'].some(field=>field in value) && value.type !== 'cta_strip') return false;
  if(isRecord(value)&&['planName', 'planAmount', 'planCurrency', 'planPeriod', 'planDescription', 'planPricingMode', 'planPriceLabel', 'pricingSourceItemId'].some(k=>k in value)&&value.type!=='faq_accordion' || ['planContentSource','planTerms','pricingSourcePageId','pricingSourceSectionId','pricingSourceTermsSectionId'].some(k=>k in value)&&!['faq_accordion','pricing_table'].includes(String(value.type)))return false;
  if(isRecord(value)&&'mediaContainerMode' in value&&!['media_text_split','media_gallery','chapter_block','policy_content'].includes(String(value.type)))return false;
  if(isRecord(value)&&(['deliverySource','deliveryNote','deliveryDestinationLabel','deliveryEstimateLabel','deliveryFeeLabel'].some(key=>key in value)&&value.type!=='delivery_table'&&value.type!=='chapter_block'||['faqPreset','faqInitiallyOpen'].some(key=>key in value)&&value.type!=='faq_accordion'))return false;
  if(isRecord(value)&&('contactPreset' in value)&&!['contact_form','store_locator'].includes(String(value.type)))return false;
  if(isRecord(value)&&('mapEmbedUrl' in value||'mapVisible' in value||'contactAddressIcon' in value)&&value.type!=='store_locator')return false;
  if(isRecord(value)&&('paymentLayout' in value||'paymentNote' in value)&&value.type!=='payment_methods')return false;
  if(isRecord(value)&&('policyPreset' in value||'policyNote' in value)&&!['policy_content','chapter_block'].includes(String(value.type)))return false;
  if (isRecord(value) && 'chapterPreset' in value && value.type !== 'chapter_block') return false;
  if (isRecord(value) && 'leadText' in value && value.type !== 'media_text_split') return false;
  if (isRecord(value) && 'richTextPreset' in value && value.type !== 'rich_text') return false;
  if (isRecord(value)) {
    if ('featureCardsPreset' in value && value.type !== 'feature_cards') return false;
    if ('valuesShowHeading' in value && !['feature_cards','values_list'].includes(String(value.type))) return false;
    if (['valuesNumberColor','valuesDividerColor','valuesShowDividers','valuesRowSpacing'].some(field=>field in value) && value.type !== 'values_list') return false;
  }
  if (!isRecord(value)
    || !Object.keys(value).every(field => (
      Object.prototype.hasOwnProperty.call(PAGE_SECTION_BOOTSTRAP_FIELD_RULES, field)
    ))) return false;

  for (const [field, rule] of Object.entries(PAGE_SECTION_BOOTSTRAP_FIELD_RULES)) {
    const present = Object.prototype.hasOwnProperty.call(value, field);
    const fieldValue = value[field];
    if (rule.kind === 'required-string' && (!present || typeof fieldValue !== 'string')) return false;
    if (rule.kind === 'required-boolean' && (!present || typeof fieldValue !== 'boolean')) return false;
    if (rule.kind === 'section-type'
      && (!present || typeof fieldValue !== 'string' || !sectionTypes.has(fieldValue))) return false;
    if (!present) continue;
    if (rule.kind === 'optional-string' && typeof fieldValue !== 'string') return false;
    if (rule.kind === 'optional-number' && !isFiniteNumber(fieldValue)) return false;
    if (rule.kind === 'optional-boolean' && typeof fieldValue !== 'boolean') return false;
    if (rule.kind === 'optional-string-or-number'
      && typeof fieldValue !== 'string' && !isFiniteNumber(fieldValue)) return false;
    if (rule.kind === 'optional-enum' && !rule.values.includes(fieldValue as never)) return false;
  }

  if ('productIds' in value && !isStringArray(value.productIds)) return false;
  if ('productTabs' in value && (!Array.isArray(value.productTabs) || !value.productTabs.every(tab => (
    isRecord(tab) && hasOnlyKeys(tab,['id','label','sourceType','sourceId','productIds','productLimit'])
    && typeof tab.id==='string' && typeof tab.label==='string'
    && ['all','category','collection','manual'].includes(String(tab.sourceType))
    && (tab.sourceId===undefined || typeof tab.sourceId==='string')
    && (tab.productIds===undefined || isStringArray(tab.productIds))
    && (tab.productLimit===undefined || [2,3,4,6,8,12].includes(Number(tab.productLimit)) && typeof tab.productLimit==='number')
  )) || new Set(value.productTabs.map(tab=>tab.id)).size!==value.productTabs.length)) return false;
  if ('items' in value && (!Array.isArray(value.items) || !value.items.every(isSectionItem))) return false;
  if ('tabs' in value && (!Array.isArray(value.tabs) || !value.tabs.every(tab => (
    isRecord(tab)
    && hasOnlyKeys(tab, ['id', 'label', 'sourceType', 'sourceId', 'productLimit'])
    && typeof tab.id === 'string'
    && typeof tab.label === 'string'
    && ['category', 'collection'].includes(String(tab.sourceType))
    && typeof tab.sourceId === 'string'
    && typeof tab.productLimit === 'number'
    && [3, 4, 6, 8].includes(tab.productLimit)
  )))) return false;
  if ('inquiryCategories' in value && (!Array.isArray(value.inquiryCategories)
    || !value.inquiryCategories.every(item => (
      isRecord(item)
      && hasOnlyKeys(item, ['id', 'label'])
      && typeof item.id === 'string'
      && typeof item.label === 'string'
    )))) return false;
  if ('typography' in value && !isTypography(value.typography)) return false;
  if ('brandPalette' in value && !isPalette(value.brandPalette)) return false;
  if ('imageSlots' in value) {
    if (!Array.isArray(value.imageSlots)) return false;
    const available = imageTargets(value as unknown as PageSection);
    if (!(imageSlotFamilies as readonly string[]).includes(value.type as string)) return false;
    const targets = new Set<string>();
    for (const slot of value.imageSlots) {
      if (!isRecord(slot) || typeof slot.target !== 'string' || targets.has(slot.target)) return false;
      const target = available.find(target => target.id === slot.target);
      if (!target) return false;
      if (target.geometryLocked && (('imageRatio' in slot && slot.imageRatio !== 'default') || ('imageWidth' in slot && slot.imageWidth !== '100') || ('imageShape' in slot && slot.imageShape !== 'default'))) return false;
      targets.add(slot.target);
      if (!hasOnlyKeys(slot, ['target', 'mediaAlt', 'imageRatio', 'imagePosition', 'imageFit', 'imageWidth', 'imageShape', 'imageOverlay', 'imageOverlayOpacity', 'imageForeground', 'imageForegroundColor', 'imageForegroundOpacity'])) return false;
      for (const [key, field] of Object.entries(slot)) {
        if (key === 'target') continue;
        const rule = PAGE_SECTION_BOOTSTRAP_FIELD_RULES[key as keyof PageSection];
        if (rule.kind === 'optional-string' && typeof field !== 'string') return false;
        if (rule.kind === 'optional-boolean' && typeof field !== 'boolean') return false;
        if (rule.kind === 'optional-enum' && !(rule.values as readonly unknown[]).includes(field)) return false;
      }
    }
  }
  if ('customColors' in value) {
    const colors = value.customColors;
    if (!isRecord(colors)
      || !sectionColorFields.every(field => !(field in colors) || typeof colors[field] === 'string')) return false;
  }
  if ('tabCategories' in value && !isStringArray(value.tabCategories)) return false;

  return true;
};

const isRuntimeTabFact = (fact: unknown): boolean => (
  isRecord(fact)
  && hasOnlyKeys(fact, ['sourceId', 'source', 'products'])
  && isRecord(fact.sourceId)
  && hasOnlyKeys(fact.sourceId, ['valid'])
  && typeof fact.sourceId.valid === 'boolean'
  && (fact.source === null || (
    isRecord(fact.source)
    && (fact.source.type === 'category' || fact.source.type === 'collection')
    && isRuntimeSource(fact.source, fact.source.type)
  ))
  && Array.isArray(fact.products)
  && fact.products.every(isRuntimeProduct)
);

const isRuntimeTabFacts = (value: unknown): boolean => (
  (Array.isArray(value) && value.every(isRuntimeTabFact))
  || (isRecord(value) && Object.entries(value).every(([key, fact]) => (
    /^\d+$/.test(key) && isRuntimeTabFact(fact)
  )))
);

const isRuntimeSectionFacts = (value: unknown): value is PageRuntimeSectionFacts => {
  if (!isRecord(value) || !hasOnlyKeys(value, ['planComparison','starterPricing','productIds', 'tabs', 'newsletterCouponId', 'listing', 'delivery', 'payments','promotions'])) return false;
  if('planComparison' in value&&!isPlanComparisonFacts(value.planComparison))return false;
  if('starterPricing' in value&&!isStarterPricing(value.starterPricing))return false;
  if('promotions' in value&&(!isRecord(value.promotions)||!hasOnlyKeys(value.promotions,['offers'])||!isPromotionOffers(value.promotions.offers)))return false;
  if('payments' in value&&!isPaymentFacts(value.payments))return false;
  if('delivery' in value && (!isRecord(value.delivery)||!hasOnlyKeys(value.delivery,['rows','status'])||('status' in value.delivery&&!['ready','unavailable'].includes(String(value.delivery.status)))||!Array.isArray(value.delivery.rows)||!value.delivery.rows.every(row=>isRecord(row)&&hasOnlyKeys(row,['id','title','deliveryEstimate','deliveryFee'])&&['id','title','deliveryEstimate','deliveryFee'].every(key=>typeof row[key]==='string'))))return false;
  if ('listing' in value && !isRuntimeTabFact(value.listing)) return false;

  if ('productIds' in value) {
    const fact = value.productIds;
    if (!isRecord(fact)
      || !hasOnlyKeys(fact, ['valid', 'products', 'invalidReferenceIds'])
      || typeof fact.valid !== 'boolean'
      || !Array.isArray(fact.products)
      || !fact.products.every(isRuntimeProduct)
      || !Array.isArray(fact.invalidReferenceIds)) return false;
  }

  if ('tabs' in value) {
    if (!isRuntimeTabFacts(value.tabs)) return false;
  }

  if ('newsletterCouponId' in value) {
    const fact = value.newsletterCouponId;
    if (!isRecord(fact)
      || !hasOnlyKeys(fact, ['valid', 'coupon'])
      || typeof fact.valid !== 'boolean'
      || !(fact.coupon === null || (
        isRecord(fact.coupon)
        && hasOnlyKeys(fact.coupon, ['id'])
        && typeof fact.coupon.id === 'string'
      ))) {
      return false;
    }
  }

  return true;
};

const isRuntimePayload = (value: unknown): value is PageRuntimePayload => {
  if (!isRecord(value)
    || !isRecord(value.page)
    || typeof value.page.id !== 'string'
    || !['published', 'draft', 'archived'].includes(String(value.page.status))
    || !['draft', 'published'].includes(String(value.page.stage))
    || !Number.isInteger(value.page.schemaVersion)
    || !Number.isInteger(value.page.lockVersion)
    || typeof value.page.contentHash !== 'string'
    || !isRecord(value.page.document)
    || typeof value.page.document.title !== 'string'
    || typeof value.page.document.slug !== 'string'
    || !['standard', 'fullwidth', 'landing'].includes(String(value.page.document.template))
    || ('containerWidth' in value.page.document && !['standard','wide','full','site'].includes(String(value.page.document.containerWidth)))
    || ('shellDesign' in value.page.document && !isShellDesign(value.page.document.shellDesign))
    || ('headerPresentation' in value.page.document && !isHeaderPresentation(value.page.document.headerPresentation))
    || ('style' in value.page.document && !isPageStyle(value.page.document.style))
    || !Array.isArray(value.page.document.sections)
    || !value.page.document.sections.every(isPageSection)
    || !isRecord(value.catalog)
    || !Array.isArray(value.catalog.products)
    || !value.catalog.products.every(isRuntimeProduct)
    || !Array.isArray(value.catalog.categories)
    || !value.catalog.categories.every(source => isRuntimeSource(source, 'category'))
    || !Array.isArray(value.catalog.collections)
    || !value.catalog.collections.every(source => isRuntimeSource(source, 'collection'))
    || !isRuntimeForms(value.forms, value.page as PageRuntimePayload['page'])
    || !isRecord(value.links)
    || !Object.values(value.links).every(link => typeof link === 'string')
    || !isRecord(value.runtime)
    || !isRecord(value.runtime.sections)
    || !Object.entries(value.runtime.sections).every(([key, facts]) => /^\d+$/.test(key) && isRuntimeSectionFacts(facts))
    || !isRecord(value.runtime.errors)
    || !Object.values(value.runtime.errors).every(isStringArray)
    || !isRecord(value.context)
    || !['preview', 'public'].includes(String(value.context.mode))
    || typeof value.context.currency !== 'string'
    || (value.context.themePalette!==undefined&&!isPalette(value.context.themePalette))
    || (value.context.wishlist !== undefined && (!isRecord(value.context.wishlist) || typeof value.context.wishlist.authenticated !== 'boolean' || typeof value.context.wishlist.csrfToken !== 'string' || !isStringArray(value.context.wishlist.savedProductIds) || value.context.wishlist.loginUrl !== '/account/login'))) {
    return false;
  }

  return true;
};

export const readPageRuntimeBootstrap = (root: Document = document): PageRuntimePayload | null => {
  const element = root.getElementById('cms-page-runtime');
  if (!element?.textContent) return null;

  try {
    const value: unknown = JSON.parse(element.textContent);
    return isRuntimePayload(value) ? value : null;
  } catch {
    return null;
  }
};

const projectProduct = (product: Product): PageRuntimeProduct => ({
  id: product.id,
  title: product.title,
  slug: product.slug,
  brand: product.brand ?? '',
  summary: product.summary ?? '',
  images: (product.images ?? []).map((image) => typeof image === 'string' ? image : image.path),
  tags: [...(product.tags ?? [])],
  purchaseMode: product.purchaseMode ?? 'buy',
  priceDisplay: product.priceDisplay ?? 'exact',
  basePrice: product.basePrice,
  baseCompareAtPrice: product.baseCompareAtPrice ?? null,
});

export const createAdminPageRuntimePayload = (
  page: CmsPage,
  catalog: AdminRuntimeCatalog,
  currency = 'USD',
  shippingRates?: import('./deliveryInformation').DeliveryRate[],
  promotionOffers:PromotionOffer[]=[],
  sourcePages:CmsPage[]=[],
): PageRuntimePayload => {
  const productsById = new Map(
    catalog.products.filter(product => product.status === 'active').map(product => [product.id, product]),
  );
  const categoriesById = new Map(
    catalog.categories.filter(category => category.status === 'active').map(category => [category.id, category]),
  );
  const collectionsById = new Map(
    catalog.collections.filter(collection => collection.status === 'active').map(collection => [collection.id, collection]),
  );
  const runtimeSections: Record<number, PageRuntimeSectionFacts> = {};
  const resolvedProductIds = new Set<string>();
  const resolvedCategoryIds = new Set<string>();
  const resolvedCollectionIds = new Set<string>();

  page.sections.forEach((section, sectionIndex) => {
    if (!section.isVisible) return;
    if(section.type==='faq_accordion'&&['corporate-starter-faq','pricing-band-faq'].includes(section.faqPreset??''))runtimeSections[sectionIndex]={starterPricing:resolveStarterPricing(section,sourcePages.find(p=>p.id===section.pricingSourcePageId))};
    if(section.type==='pricing_table'&&section.pricingPreset==='plan-comparison')runtimeSections[sectionIndex]={planComparison:resolvePlanComparison(section,sourcePages.find(p=>p.id===section.pricingSourcePageId))};
    if(section.type==='promotion_highlights')runtimeSections[sectionIndex]={promotions:{offers:promotionOffers}};
    if(section.type==='delivery_table'||section.type==='chapter_block'&&section.chapterPreset==='delivery')runtimeSections[sectionIndex]={delivery:{rows:deliveryRows(shippingRates??[]),status:shippingRates===undefined?'unavailable':'ready'}};

    if (section.type === 'shop_navigation') {
      for (const item of section.items ?? []) {
        if (item.sourceType === 'category' && categoriesById.has(item.sourceId ?? '')) resolvedCategoryIds.add(item.sourceId!);
        if (item.sourceType === 'collection' && collectionsById.has(item.sourceId ?? '')) resolvedCollectionIds.add(item.sourceId!);
      }
    }
    if (section.type === 'product_listing') {
      const resolveFeed=(feed: Omit<PageSection,'sourceType'> & {sourceType?:'all'|'category'|'collection'|'manual'}):{listing:NonNullable<PageRuntimeSectionFacts['listing']>} => {
      const type = feed.sourceType ?? 'category';
      const source = type === 'manual' || type === 'all' ? undefined : type === 'category' ? categoriesById.get(feed.sourceId ?? '') : collectionsById.get(feed.sourceId ?? '');
      const manual = [...new Set(feed.productIds ?? [])].flatMap(id => {const product=productsById.get(id);return product ? [product] : [];});
      const items = type === 'all' ? catalog.products.filter(p=>p.status==='active').sort((a,b)=>Number(a.id)-Number(b.id)).slice(0,feed.productLimit??4) : type === 'manual' ? manual.slice(0,feed.productLimit ?? 6) : source ? catalog.products.filter(product => product.status === 'active' && (type === 'category'
        ? product.categoryId === source.id : product.collectionIds.includes(source.id))).sort((a,b) => Number(a.id)-Number(b.id)).slice(0,feed.productLimit ?? 6) : [];
      if (source) (type === 'category' ? resolvedCategoryIds : resolvedCollectionIds).add(source.id);
      const products = items.map(product => {
        resolvedProductIds.add(product.id);
        const variant = product.variants?.[0];
        return {...projectProduct(product), commerce: {
          variantId: variant?.id ?? null, sku: variant?.sku ?? '', price: variant?.price ?? product.basePrice,
          compareAtPrice: variant?.compareAtPrice ?? null, optionLabels: product.options?.[0]?.values ?? [], canChoose: (!product.purchaseMode || product.purchaseMode === 'buy') && Boolean(variant),
        }};
      });
      return {listing:{sourceId:{valid:type === 'all' ? true : type === 'manual' ? manual.length > 0 && manual.length === new Set(feed.productIds ?? []).size : Boolean(source)}, source: source ? {
        id:source.id, type:type === 'category' ? 'category' : 'collection', slug:source.slug, label:type === 'category' ? (source as Category).name : (source as Collection).title,
        image:type === 'category' ? (source as Category).image ?? null : (source as Collection).coverImage ?? null,
      } : null, products}};
      };
      runtimeSections[sectionIndex]=['editorial-tabs','pantry-tabs','tech-tabs','kids-tabs'].includes(section.listingPreset??'')?{tabs:(section.productTabs??[]).map(tab=>resolveFeed({...section,...tab,productLimit:tab.productLimit??section.productLimit??4}).listing)}:resolveFeed(section);
    }

    if (section.type === 'product_grid') {
      const requestedIds = section.productIds ?? [];
      const products = requestedIds.flatMap(id => {
        const product = productsById.get(id);
        if (!product) return [];
        resolvedProductIds.add(product.id);
        return [projectProduct(product)];
      });
      runtimeSections[sectionIndex] = {
        productIds: {
          valid: products.length === requestedIds.length,
          products,
          invalidReferenceIds: requestedIds.filter(id => !productsById.has(id)),
        },
      };
    }

    if (section.type === 'collection_tabs') {
      const tabs = section.tabs ?? [];
      const facts: NonNullable<PageRuntimeSectionFacts['tabs']> = {};
      tabs.forEach((tab, tabIndex) => {
        const source = tab.sourceType === 'category'
          ? categoriesById.get(tab.sourceId)
          : collectionsById.get(tab.sourceId);
        const products = source
          ? catalog.products.filter(product => product.status === 'active' && (
            tab.sourceType === 'category'
              ? product.categoryId === source.id
              : product.collectionIds.includes(source.id)
          )).slice(0, tab.productLimit)
          : [];
        products.forEach(product => resolvedProductIds.add(product.id));
        if (source && tab.sourceType === 'category') resolvedCategoryIds.add(source.id);
        if (source && tab.sourceType === 'collection') resolvedCollectionIds.add(source.id);
        facts[tabIndex] = {
          sourceId: { valid: Boolean(source) },
          source: source ? {
            id: source.id,
            type: tab.sourceType,
            slug: source.slug,
            label: tab.sourceType === 'category' ? (source as Category).name : (source as Collection).title,
            image: tab.sourceType === 'category'
              ? ((source as Category).image ?? null)
              : ((source as Collection).coverImage ?? null),
          } : null,
          products: products.map(projectProduct),
        };
      });
      runtimeSections[sectionIndex] = { tabs: facts };
    }
  });

  const projectCategory = (category: Category): PageRuntimeSource => ({
    id: category.id,
    type: 'category',
    slug: category.slug,
    label: category.name,
    image: category.image ?? null,
  });
  const projectCollection = (collection: Collection): PageRuntimeSource => ({
    id: collection.id,
    type: 'collection',
    slug: collection.slug,
    label: collection.title,
    image: collection.coverImage ?? null,
  });

  return {
    page: {
      id: page.id,
      status: page.status,
      stage: 'draft',
      schemaVersion: page.schemaVersion ?? 1,
      lockVersion: page.lockVersion ?? 0,
      contentHash: page.contentHash ?? '',
      document: {
        title: page.title,
        slug: page.slug,
        template: page.template,
        ...(page.shellDesign?{shellDesign:page.shellDesign}:{}),
        ...(page.headerPresentation?{headerPresentation:page.headerPresentation}:{}),
        ...(page.containerWidth?{containerWidth:page.containerWidth}:{}),
    ...(page.style?{style:page.style}:{}),
        sections: page.sections,
        ...(Object.prototype.hasOwnProperty.call(page, 'description') ? {description: page.description} : {}),
        ...(Object.prototype.hasOwnProperty.call(page, 'heroImage') ? {heroImage: page.heroImage} : {}),
        ...(Object.prototype.hasOwnProperty.call(page, 'seoSettings') ? {seoSettings: page.seoSettings} : {}),
        ...(Object.prototype.hasOwnProperty.call(page, 'seoTitle') ? {seoTitle: page.seoTitle} : {}),
        ...(Object.prototype.hasOwnProperty.call(page, 'seoDescription') ? {seoDescription: page.seoDescription} : {}),
      },
    },
    catalog: {
      products: [...resolvedProductIds].flatMap(id => {
        const product = productsById.get(id);
        return product ? [projectProduct(product)] : [];
      }),
      categories: [...resolvedCategoryIds].flatMap(id => {
        const category = categoriesById.get(id);
        return category ? [projectCategory(category)] : [];
      }),
      collections: [...resolvedCollectionIds].flatMap(id => {
        const collection = collectionsById.get(id);
        return collection ? [projectCollection(collection)] : [];
      }),
    },
    forms: {
      contact: {},
      newsletter: {},
      adminContractsEndpoint: `/admin/api/pages/${page.id}/form-contracts`,
    },
    links: Object.fromEntries(page.sections.filter(s=>s.isVisible).flatMap(s=>[s.buttonLink,s.secondaryButtonLink,...(s.items??[]).map(i=>i.linkUrl)]).filter((url):url is string=>typeof url==='string'&&url.length>0).map(url=>[url,builderNavigationHref(url,page,sourcePages)]).filter(([url,href])=>url!==href)),
    runtime: { sections: runtimeSections, errors: {} },
    context: { mode: 'editing', currency },
  };
};

const viewportForWidth = (width: number): 'desktop' | 'tablet' | 'mobile' => (
  width <= 480 ? 'mobile' : width <= 900 ? 'tablet' : 'desktop'
);

export const PageSections: React.FC<{
  sections: PageSection[];
  mode: PageRuntimePayload['context']['mode'];
}> = ({ sections, mode }) => {
  const [viewport, setViewport] = useState<'desktop' | 'tablet' | 'mobile'>(() => (
    typeof window === 'undefined' ? 'desktop' : viewportForWidth(window.innerWidth)
  ));

  useEffect(() => {
    const updateViewport = () => setViewport(viewportForWidth(window.innerWidth));
    window.addEventListener('resize', updateViewport, {passive: true});
    return () => window.removeEventListener('resize', updateViewport);
  }, []);

  return sections
    .filter(section => mode === 'editing' || section.isVisible)
    .map(section => (
      <SectionRenderer
        key={section.id}
        section={section}
        isEditingMode={mode === 'editing'}
        viewport={viewport}
      />
    ));
};
