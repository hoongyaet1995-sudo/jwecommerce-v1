import React, { createContext, useContext, useEffect, useState } from 'react';
import type { Category, CmsPage, Collection, PageDocument, PageSection, Product } from '../types';
import { SectionRenderer } from '../components/pages/SectionRenderer';

export interface PageRuntimeProduct {
  id: string;
  title: string;
  slug: string;
  brand: string;
  summary: string;
  images: string[];
  tags: string[];
  basePrice: number;
  baseCompareAtPrice: number | null;
}

export interface PageRuntimeSource {
  id: string;
  type: 'category' | 'collection';
  slug: string;
  label: string;
  image: string | null;
}

export interface PageRuntimeSectionFacts {
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
}> = ({ payload, children }) => (
  <PageRuntimeContext.Provider value={payload}>{children}</PageRuntimeContext.Provider>
);

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

const formContractFields = ['endpoint', 'pageId', 'stage', 'documentHash', 'lockVersion', 'sectionId', 'sectionType'];

const isExactContractEndpoint = (endpoint: string, contract: Record<string, unknown>): boolean => {
  if (!endpoint.startsWith('/') || endpoint.startsWith('//')) return false;
  try {
    const parsed = new URL(endpoint, 'https://cms-runtime.invalid');
    if (parsed.origin !== 'https://cms-runtime.invalid') return false;
    const kind = contract.sectionType === 'contact_form' ? 'contact' : 'newsletter';
    if (parsed.pathname !== `/cms/forms/${kind}/${contract.pageId}/${contract.stage}/${encodeURIComponent(String(contract.sectionId))}`) return false;
    const keys = [...parsed.searchParams.keys()];
    if (keys.length !== 5 || new Set(keys).size !== keys.length || !keys.every(key => ['expires', 'hash', 'version', 'type', 'signature'].includes(key))) return false;
    return /^\d+$/.test(parsed.searchParams.get('expires') ?? '')
      && parsed.searchParams.get('hash') === contract.documentHash
      && parsed.searchParams.get('version') === String(contract.lockVersion)
      && parsed.searchParams.get('type') === contract.sectionType
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
  'id', 'title', 'slug', 'brand', 'summary', 'images', 'tags', 'basePrice', 'baseCompareAtPrice',
];
const runtimeSourceFields = ['id', 'type', 'slug', 'label', 'image'];

const sectionTypes = new Set([
  'hero_banner', 'split_hero', 'hero_slider', 'announcement_bar', 'rich_text',
  'media_text_split', 'timeline_story', 'stats_counter', 'product_grid', 'collection_tabs',
  'featured_deal', 'pricing_table', 'media_gallery', 'video_embed', 'before_after',
  'instagram_feed', 'feature_cards', 'icon_list', 'comparison_matrix', 'faq_accordion',
  'contact_form', 'store_locator', 'testimonials_slider', 'logo_cloud', 'cta_strip',
  'newsletter_signup',
]);

const sectionItemStringFields = [
  'subtitle', 'description', 'icon', 'imageUrl', 'videoUrl', 'linkUrl', 'badge',
  'author', 'authorRole', 'authorAvatar', 'date', 'category', 'value', 'handle',
  'metricLabel', 'buttonText', 'secondaryButtonText', 'secondaryButtonLink',
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
  mediaAspectRatio: {kind: 'optional-enum', values: ['16:9', '4:3', '1:1', 'auto']},
  buttonText: {kind: 'optional-string'},
  buttonLink: {kind: 'optional-string'},
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
  comparisonOurBrandName: {kind: 'optional-string'},
  cardBadge: {kind: 'optional-string'},
  cardTitle: {kind: 'optional-string'},
  cardSubtitle: {kind: 'optional-string'},
  cardPrice: {kind: 'optional-string-or-number'},
  cardExtra: {kind: 'optional-string'},
  sliderInterval: {kind: 'optional-number'},
  sliderAutoplay: {kind: 'optional-boolean'},
  sliderHeight: {kind: 'optional-enum', values: ['compact', 'normal', 'spacious', 'fullscreen']},
} as const satisfies Record<keyof PageSection, PageSectionFieldRule>;

const isSectionItem = (value: unknown): boolean => {
  if (!isRecord(value) || typeof value.id !== 'string' || typeof value.title !== 'string') return false;
  if (!sectionItemStringFields.every(field => !(field in value) || typeof value[field] === 'string')) return false;
  if (!sectionItemNumberFields.every(field => !(field in value) || isFiniteNumber(value[field]))) return false;
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
  && isFiniteNumber(value.basePrice)
  && (value.baseCompareAtPrice === null || isFiniteNumber(value.baseCompareAtPrice));

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
  if (!isRecord(value) || !hasOnlyKeys(value, ['productIds', 'tabs', 'newsletterCouponId'])) return false;

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
    || typeof value.context.currency !== 'string') {
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
  images: [...(product.images ?? [])],
  tags: [...(product.tags ?? [])],
  basePrice: product.basePrice,
  baseCompareAtPrice: product.baseCompareAtPrice ?? null,
});

export const createAdminPageRuntimePayload = (
  page: CmsPage,
  catalog: AdminRuntimeCatalog,
  currency = 'USD',
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
        sections: page.sections,
        ...(Object.prototype.hasOwnProperty.call(page, 'description') ? {description: page.description} : {}),
        ...(Object.prototype.hasOwnProperty.call(page, 'heroImage') ? {heroImage: page.heroImage} : {}),
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
    links: {},
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
