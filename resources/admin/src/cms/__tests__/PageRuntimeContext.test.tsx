import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';
import { SECTION_TEMPLATES } from '../../components/pages/AddSectionModal';
import {
  PageRuntimeProvider,
  PageRuntimeUnavailable,
  PageSections,
  createAdminPageRuntimePayload,
  readPageRuntimeBootstrap,
  usePageRuntime,
  type PageRuntimePayload,
} from '../PageRuntimeContext';

const runtimeProduct = {
  id: '2',
  title: 'Saved Product',
  slug: 'saved-product',
  brand: 'Saved Brand',
  summary: 'Saved summary',
  images: ['/storage/saved.jpg'],
  tags: ['Saved tag'],
  basePrice: 129,
  baseCompareAtPrice: null,
};

const payload = (): PageRuntimePayload => ({
  page: {
    id: '7',
    status: 'published',
    stage: 'published',
    schemaVersion: 1,
    lockVersion: 3,
    contentHash: 'saved-hash',
    document: {
      title: 'Saved page',
      slug: 'saved-page',
      description: 'Saved description',
      template: 'landing',
      heroImage: null,
      seoTitle: 'Saved SEO',
      seoDescription: 'Saved SEO description',
      sections: [
        {
          id: 'visible-products',
          type: 'product_grid',
          title: 'Real products',
          isVisible: true,
          productIds: ['author-id-is-not-operational-truth'],
        },
        {
          id: 'hidden-copy',
          type: 'rich_text',
          title: 'HIDDEN COPY',
          content: 'HIDDEN COPY',
          isVisible: false,
        },
      ],
    },
  },
  catalog: {
    products: [runtimeProduct],
    categories: [],
    collections: [],
  },
  forms: {
    contact: {},
    newsletter: {},
  },
  links: {},
  runtime: {
    sections: {
      0: {
        productIds: {
          valid: true,
          products: [runtimeProduct],
          invalidReferenceIds: [],
        },
      },
    },
    errors: {},
  },
  context: {
    mode: 'public',
    currency: 'USD',
  },
});

const readBootstrapValue = (value: unknown) => readPageRuntimeBootstrap({
  getElementById: () => ({ textContent: JSON.stringify(value) }),
} as unknown as Document);

const readBootstrapText = (textContent: string) => readPageRuntimeBootstrap({
  getElementById: () => ({ textContent }),
} as unknown as Document);

const renderRuntimeOrFallback = (parsed: PageRuntimePayload | null) => renderToStaticMarkup(parsed ? (
  <PageRuntimeProvider payload={parsed}>
    <PageSections sections={parsed.page.document.sections} mode={parsed.context.mode} />
  </PageRuntimeProvider>
) : <PageRuntimeUnavailable />);

describe('PageRuntimeContext', () => {
  it.each(['subtle-lift', 'fade-in', 'smooth-zoom', 'dynamic-pop', 'none'] as const)('renders all section templates safely with %s without browser observers', (motionPreset) => {
    const value = payload();
    value.page.document.sections = SECTION_TEMPLATES.map((template, index) => ({ ...template.defaultData, id: 'motion-' + index, isVisible: true, motionPreset }));
    expect(value.page.document.sections).toHaveLength(27);
    expect(() => renderRuntimeOrFallback(value)).not.toThrow();
  });

  it.each(['subtle-lift', 'fade-in', 'smooth-zoom', 'dynamic-pop', 'none'] as const)('accepts %s from the saved public bootstrap', (motionPreset) => {
    const value = payload();
    value.page.document.sections[0].motionPreset = motionPreset;
    expect(readBootstrapValue(value)?.page.document.sections[0].motionPreset).toBe(motionPreset);
  });

  it('rejects an unknown motion preset in the public bootstrap', () => {
    const value = payload();
    (value.page.document.sections[0] as unknown as Record<string, unknown>).motionPreset = 'unknown';
    expect(readBootstrapValue(value)).toBeNull();
  });

  it('reads one non-executable JSON bootstrap value without evaluating hostile text', () => {
    const value = payload();
    value.page.document.title = '</script><script>globalThis.pwned=true</script>\u2028\u2029';
    const root = {
      getElementById: (id: string) => id === 'cms-page-runtime'
        ? { textContent: JSON.stringify(value) }
        : null,
    } as unknown as Document;

    expect(readPageRuntimeBootstrap(root)?.page.document.title).toBe(value.page.document.title);
    expect((globalThis as {pwned?: boolean}).pwned).toBeUndefined();
  });

  it('returns null for a missing or malformed bootstrap without throwing', () => {
    const missing = { getElementById: () => null } as unknown as Document;
    const malformed = {
      getElementById: () => ({ textContent: '{"page":' }),
    } as unknown as Document;
    const wrongShape = {
      getElementById: () => ({ textContent: '{"page":{"document":{"sections":"nope"}}}' }),
    } as unknown as Document;

    expect(readPageRuntimeBootstrap(missing)).toBeNull();
    expect(readPageRuntimeBootstrap(malformed)).toBeNull();
    expect(readPageRuntimeBootstrap(wrongShape)).toBeNull();
    expect(renderToStaticMarkup(<PageRuntimeUnavailable />)).toContain('Page content is unavailable.');
  });

  it('rejects malformed required scalars and every nested section, catalog, and runtime-fact entry', () => {
    const cases: Array<[string, (value: Record<string, any>) => void]> = [
      ['page id', value => { value.page.id = 7; }],
      ['page status', value => { value.page.status = 'unknown'; }],
      ['page stage', value => { value.page.stage = 'editing'; }],
      ['schema version', value => { value.page.schemaVersion = '1'; }],
      ['lock version', value => { value.page.lockVersion = null; }],
      ['content hash', value => { value.page.contentHash = 3; }],
      ['document title', value => { value.page.document.title = null; }],
      ['document slug', value => { value.page.document.slug = 1; }],
      ['document template', value => { value.page.document.template = 'unknown'; }],
      ['section entry', value => { value.page.document.sections = [null]; }],
      ['section id', value => { value.page.document.sections[0].id = null; }],
      ['section type', value => { value.page.document.sections[0].type = 'unknown'; }],
      ['section title', value => { value.page.document.sections[0].title = []; }],
      ['section visibility', value => { value.page.document.sections[0].isVisible = 'yes'; }],
      ['section product ids', value => { value.page.document.sections[0].productIds = [null]; }],
      ['section items', value => { value.page.document.sections[0].items = [null]; }],
      ['catalog product entry', value => { value.catalog.products = [null]; }],
      ['catalog product id', value => { value.catalog.products[0].id = 2; }],
      ['catalog product title', value => { value.catalog.products[0].title = null; }],
      ['catalog product slug', value => { value.catalog.products[0].slug = []; }],
      ['catalog product brand', value => { value.catalog.products[0].brand = false; }],
      ['catalog product summary', value => { value.catalog.products[0].summary = 1; }],
      ['catalog product images', value => { value.catalog.products[0].images = [null]; }],
      ['catalog product tags', value => { value.catalog.products[0].tags = 'tag'; }],
      ['catalog product price', value => { value.catalog.products[0].basePrice = '129'; }],
      ['catalog product compare price', value => { value.catalog.products[0].baseCompareAtPrice = '199'; }],
      ['category entry', value => { value.catalog.categories = [null]; }],
      ['collection entry', value => { value.catalog.collections = [null]; }],
      ['runtime section entry', value => { value.runtime.sections['0'] = null; }],
      ['runtime product fact', value => { value.runtime.sections['0'].productIds.valid = 'yes'; }],
      ['runtime product projection', value => { value.runtime.sections['0'].productIds.products = [null]; }],
      ['runtime invalid ids', value => { value.runtime.sections['0'].productIds.invalidReferenceIds = null; }],
      ['runtime tab entry', value => { value.runtime.sections['0'].tabs = {'0': null}; }],
      ['runtime newsletter fact', value => { value.runtime.sections['0'].newsletterCouponId = {valid: true, coupon: {id: 9}}; }],
      ['runtime error entry', value => { value.runtime.errors.problem = 'nope'; }],
      ['form contracts', value => { value.forms.contact = []; }],
      ['form contract extra key', value => { value.forms.invented = true; }],
      ['link entry', value => { value.links.checkout = 9; }],
      ['context mode', value => { value.context.mode = 'editing'; }],
      ['context currency', value => { value.context.currency = null; }],
    ];

    for (const [label, mutate] of cases) {
      const value = structuredClone(payload()) as unknown as Record<string, any>;
      mutate(value);
      expect(readBootstrapValue(value), label).toBeNull();
    }
  });

  it('rejects extra keys in Task 11 nested authoring and coupon runtime facts with a neutral fallback', () => {
    const cases: Array<[string, (value: Record<string, any>) => void]> = [
      ['collection tab extra key', value => {
        value.page.document.sections = [{
          id: 'tabs', type: 'collection_tabs', title: 'Tabs', isVisible: true,
          tabs: [{id: 'tab-1', label: 'Audio', sourceType: 'category', sourceId: '1', productLimit: 4, invented: true}],
        }];
        value.runtime.sections = {};
      }],
      ['inquiry category extra key', value => {
        value.page.document.sections = [{
          id: 'contact', type: 'contact_form', title: 'Contact', isVisible: true,
          inquiryCategories: [{id: 'cat-1', label: 'Support', invented: true}],
        }];
        value.runtime.sections = {};
      }],
      ['newsletter fact extra key', value => {
        value.runtime.sections['0'].newsletterCouponId = {valid: true, coupon: {id: '1'}, invented: true};
      }],
      ['newsletter coupon extra key', value => {
        value.runtime.sections['0'].newsletterCouponId = {valid: true, coupon: {id: '1', code: 'SECRET'}};
      }],
    ];

    for (const [label, mutate] of cases) {
      const value = structuredClone(payload()) as unknown as Record<string, any>;
      mutate(value);
      const parsed = readBootstrapValue(value);
      expect(parsed, label).toBeNull();
      expect(() => renderRuntimeOrFallback(parsed), label).not.toThrow();
      expect(renderRuntimeOrFallback(parsed), label).toContain('Page content is unavailable.');
    }
  });

  it('accepts only a same-origin, section-bound signed form endpoint with exactly its contract query', () => {
    const valid = structuredClone(payload()) as unknown as Record<string, any>;
    valid.page.document.sections = [{id: 'contact-form', type: 'contact_form', title: 'Contact', isVisible: true}];
    valid.runtime.sections = {};
    const endpoint = '/cms/forms/contact/7/published/contact-form?expires=1893456000&hash=saved-hash&version=3&type=contact_form&audience=public-storefront&signature=' + 'a'.repeat(64);
    valid.forms.contact = {
      'contact-form': {
        endpoint,
        pageId: '7', stage: 'published', documentHash: 'saved-hash', lockVersion: 3,
        sectionId: 'contact-form', sectionType: 'contact_form', audience: 'public-storefront',
      },
    };
    expect(readBootstrapValue(valid)).not.toBeNull();

    for (const invalidEndpoint of [
      `https://attacker.test${endpoint}`,
      `//attacker.test${endpoint}`,
      endpoint.replace('/cms/forms/contact/', '/cms/forms/newsletter/'),
      `${endpoint}&invented=true`,
    ]) {
      const adversary = structuredClone(valid);
      adversary.forms.contact['contact-form'].endpoint = invalidEndpoint;
      expect(readBootstrapValue(adversary)).toBeNull();
    }

    const audienceMismatch = structuredClone(valid);
    audienceMismatch.forms.contact['contact-form'].audience = 'protected-canary';
    expect(readBootstrapValue(audienceMismatch)).toBeNull();

    const unknownAudience = structuredClone(valid);
    unknownAudience.forms.contact['contact-form'].audience = 'public-before-cutover';
    unknownAudience.forms.contact['contact-form'].endpoint = endpoint.replace('public-storefront', 'public-before-cutover');
    expect(readBootstrapValue(unknownAudience)).toBeNull();
  });

  it('accepts the exact PHP collection-tab fact list and rejects extra nested runtime keys', () => {
    const valid = structuredClone(payload()) as unknown as Record<string, any>;
    valid.page.document.sections = [{
      id: 'tabs', type: 'collection_tabs', title: 'Runtime tabs', isVisible: true,
      tabs: [{id: 'tab-1', label: 'Audio', sourceType: 'category', sourceId: '1', productLimit: 3}],
    }];
    valid.runtime.sections = {
      0: {
        tabs: [{
          sourceId: {valid: true},
          source: {id: '1', type: 'category', slug: 'audio', label: 'Audio', image: null},
          products: [runtimeProduct],
        }],
      },
    };

    const parsed = readBootstrapValue(valid);
    expect(parsed).not.toBeNull();
    expect(renderRuntimeOrFallback(parsed)).toContain('Runtime tabs');
    expect(renderRuntimeOrFallback(parsed)).toContain('Saved Product');

    const adversaries: Array<[string, (value: Record<string, any>) => void]> = [
      ['tab fact', value => { value.runtime.sections['0'].tabs[0].invented = true; }],
      ['source id fact', value => { value.runtime.sections['0'].tabs[0].sourceId.invented = true; }],
      ['source projection', value => { value.runtime.sections['0'].tabs[0].source.invented = true; }],
      ['product projection', value => { value.runtime.sections['0'].tabs[0].products[0].invented = true; }],
    ];

    for (const [label, mutate] of adversaries) {
      const value = structuredClone(valid);
      mutate(value);
      expect(readBootstrapValue(value), label).toBeNull();
    }
  });

  it('falls back without passing a malformed section entry to PageSections', () => {
    const value = structuredClone(payload()) as unknown as Record<string, any>;
    value.page.document.sections = [null];
    const parsed = readBootstrapValue(value);

    expect(() => renderToStaticMarkup(parsed ? (
      <PageRuntimeProvider payload={parsed}>
        <PageSections sections={parsed.page.document.sections} mode={parsed.context.mode} />
      </PageRuntimeProvider>
    ) : <PageRuntimeUnavailable />)).not.toThrow();
    expect(renderToStaticMarkup(<PageRuntimeUnavailable />)).toContain('Page content is unavailable.');
  });

  it('rejects every renderer-consumed nested item field with the wrong type', () => {
    const stringFields = [
      'subtitle', 'description', 'icon', 'imageUrl', 'videoUrl', 'linkUrl', 'badge',
      'author', 'authorRole', 'authorAvatar', 'date', 'category', 'value', 'handle',
      'metricLabel', 'buttonText', 'secondaryButtonText', 'secondaryButtonLink',
    ];
    const numberFields = ['price', 'compareAtPrice'];
    const cases: Array<[string, unknown]> = [
      ...stringFields.map(field => [field, 7] as [string, unknown]),
      ...numberFields.map(field => [field, '7'] as [string, unknown]),
      ['features', 'not-an-array'],
      ['features entry', [false]],
      ['rating', '5'],
      ['isHighlighted', 'yes'],
    ];

    for (const [label, invalid] of cases) {
      const value = structuredClone(payload()) as unknown as Record<string, any>;
      value.page.document.sections = [{
        id: 'nested-item',
        type: 'pricing_table',
        title: 'Nested item',
        isVisible: true,
        items: [{id: 'item-1', title: 'Item one', [label === 'features entry' ? 'features' : label]: invalid}],
      }];
      value.runtime.sections = {};
      expect(readBootstrapValue(value), label).toBeNull();
    }
  });

  it('rejects wrong types for the complete optional PageSection scalar inventory', () => {
    const optionalStrings = [
      'subtitle', 'content', 'quote', 'authorName', 'authorRole', 'authorAvatar', 'mediaUrl',
      'buttonText', 'buttonLink', 'secondaryButtonText', 'secondaryButtonLink', 'backgroundColor',
      'videoEmbedUrl', 'customCssClass', 'beforeImageUrl', 'afterImageUrl', 'beforeLabel',
      'afterLabel', 'dealEndDate', 'dealBadge', 'formEmail', 'formSuccessMessage', 'contactPhone',
      'contactAddress', 'contactHours', 'contactButtonText', 'newsletterCouponId', 'subjectPlaceholder',
      'categoryPlaceholder', 'messagePlaceholder', 'comparisonDimensionName',
      'comparisonCompetitorName', 'comparisonOurBrandName', 'cardBadge', 'cardTitle', 'cardSubtitle',
      'cardExtra',
    ];
    const optionalNumbers = ['overlayOpacity', 'discountPercent', 'sliderInterval'];
    const optionalEnums = [
      'mediaType', 'mediaPosition', 'mediaAspectRatio', 'textAlignment', 'textColor', 'paddingY',
      'tickerSpeed', 'sliderHeight',
    ];
    const cases: Array<[string, unknown]> = [
      ...optionalStrings.map(field => [field, {bad: true}] as [string, unknown]),
      ...optionalNumbers.map(field => [field, 'not-a-number'] as [string, unknown]),
      ...optionalEnums.map(field => [field, 'not-an-enum-value'] as [string, unknown]),
      ['columnsCount', 7],
      ['sliderAutoplay', 'yes'],
      ['cardPrice', {bad: true}],
    ];

    for (const [field, invalid] of cases) {
      const value = structuredClone(payload()) as unknown as Record<string, any>;
      value.page.document.sections[0][field] = invalid;
      expect(readBootstrapValue(value), field).toBeNull();
    }
  });

  it('falls back before direct-child and numeric-enum section fields can reach the renderer', () => {
    const malformedSection = (type: string, field: string, invalid: unknown) => {
      const value = structuredClone(payload()) as unknown as Record<string, any>;
      value.page.document.sections = [{
        id: 'malformed-section',
        type,
        title: 'Malformed section',
        isVisible: true,
        [field]: invalid,
      }];
      value.runtime.sections = {};
      return value;
    };
    const malformed: Array<[string, Record<string, any>]> = [
      ['rich text subtitle object', malformedSection('rich_text', 'subtitle', {bad: true})],
      ['deal percentage object', malformedSection('featured_deal', 'discountPercent', {bad: true})],
      ['columns numeric enum', malformedSection('feature_cards', 'columnsCount', 7)],
    ];

    for (const [label, value] of malformed) {
      let html = '';
      let parsed: PageRuntimePayload | null = null;
      expect(() => {
        parsed = readBootstrapValue(value);
        html = renderRuntimeOrFallback(parsed);
      }, label).not.toThrow();
      expect(parsed, label).toBeNull();
      expect(html, label).toContain('Page content is unavailable.');
    }
  });

  it('uses the neutral fallback instead of rendering malformed pricing features or testimonial ratings', () => {
    const pricing = structuredClone(payload()) as unknown as Record<string, any>;
    pricing.page.document.sections = [{
      id: 'pricing',
      type: 'pricing_table',
      title: 'Pricing',
      isVisible: true,
      items: [{id: 'tier', title: 'Tier', features: 'bad'}],
    }];
    pricing.runtime.sections = {};

    const testimonial = (rating: unknown) => {
      const value = structuredClone(payload()) as unknown as Record<string, any>;
      value.page.document.sections = [{
        id: 'testimonial',
        type: 'testimonials_slider',
        title: 'Testimonials',
        isVisible: true,
        items: [{id: 'quote', title: 'Quote', rating}],
      }];
      value.runtime.sections = {};
      return value;
    };
    const finiteRatingJson = JSON.stringify(testimonial(5));
    const malformed = [
      ['pricing features', JSON.stringify(pricing)],
      ['negative rating', JSON.stringify(testimonial(-1))],
      ['fractional rating', JSON.stringify(testimonial(4.5))],
      ['out-of-range rating', JSON.stringify(testimonial(6))],
      ['non-finite rating', finiteRatingJson.replace('"rating":5', '"rating":1e309')],
    ];

    for (const [label, text] of malformed) {
      let html = '';
      let parsed: PageRuntimePayload | null = null;
      expect(() => {
        parsed = readBootstrapText(text);
        html = renderRuntimeOrFallback(parsed);
      }, label).not.toThrow();
      expect(parsed, label).toBeNull();
      expect(html, label).toContain('Page content is unavailable.');
    }
  });

  it('provides page, public catalog, forms, links, runtime facts, and context', () => {
    const Consumer = () => {
      const runtime = usePageRuntime();
      return <output>{JSON.stringify({
        title: runtime.page.document.title,
        product: runtime.catalog.products[0].title,
        contact: runtime.forms.contact,
        links: runtime.links,
        valid: runtime.runtime.sections[0].productIds?.valid,
        mode: runtime.context.mode,
        currency: runtime.context.currency,
      })}</output>;
    };

    const html = renderToStaticMarkup(
      <PageRuntimeProvider payload={payload()}><Consumer /></PageRuntimeProvider>,
    );
    expect(html).toContain('&quot;title&quot;:&quot;Saved page&quot;');
    expect(html).toContain('&quot;product&quot;:&quot;Saved Product&quot;');
    expect(html).toContain('&quot;valid&quot;:true');
    expect(html).toContain('&quot;mode&quot;:&quot;public&quot;');
  });

  it('adapts the real Admin catalog into the public-safe payload and server fact order', () => {
    const adminPage = {
      id: '8',
      title: 'Editing page',
      slug: 'editing-page',
      description: '',
      status: 'draft' as const,
      template: 'landing' as const,
      heroImage: null,
      seoTitle: '',
      seoDescription: '',
      createdAt: '2026-08-20',
      updatedAt: '2026-08-20',
      lockVersion: 4,
      schemaVersion: 1,
      contentHash: 'draft-hash',
      sections: [{
        id: 'admin-products',
        type: 'product_grid' as const,
        title: 'Admin products',
        isVisible: true,
        productIds: ['2', '1'],
      }],
    };
    const adminProducts = [
      {
        ...runtimeProduct,
        id: '1',
        title: 'First Product',
        categoryId: 'cat-1',
        collectionIds: [],
        descriptionHtml: '<p>private</p>',
        status: 'active' as const,
        hasVariants: false,
        options: [],
        variants: [],
        totalInventory: 88,
        featured: false,
        createdAt: 'secret-created',
        updatedAt: 'secret-updated',
      },
      {
        ...runtimeProduct,
        categoryId: 'cat-1',
        collectionIds: [],
        descriptionHtml: '<p>private</p>',
        status: 'active' as const,
        hasVariants: false,
        options: [],
        variants: [],
        totalInventory: 99,
        featured: true,
        createdAt: 'secret-created',
        updatedAt: 'secret-updated',
      },
    ];

    const adapted = createAdminPageRuntimePayload(adminPage, {
      products: adminProducts,
      categories: [],
      collections: [],
    }, 'MYR');

    expect(adapted.context).toEqual({ mode: 'editing', currency: 'MYR' });
    expect(adapted.forms).toEqual({
      contact: {},
      newsletter: {},
      adminContractsEndpoint: '/admin/api/pages/8/form-contracts',
    });
    expect(adapted.runtime.sections[0].productIds?.products.map(product => product.id)).toEqual(['2', '1']);
    expect(Object.keys(adapted.catalog.products[0])).toEqual([
      'id', 'title', 'slug', 'brand', 'summary', 'images', 'tags', 'basePrice', 'baseCompareAtPrice',
    ]);
    expect(JSON.stringify(adapted)).not.toContain('descriptionHtml');
    expect(JSON.stringify(adapted)).not.toContain('totalInventory');
  });

  it('renders runtime-resolved products and hides sections outside editing mode', () => {
    const value = payload();
    const publicHtml = renderToStaticMarkup(
      <PageRuntimeProvider payload={value}>
        <PageSections sections={value.page.document.sections} mode="public" />
      </PageRuntimeProvider>,
    );
    const editingHtml = renderToStaticMarkup(
      <PageRuntimeProvider payload={{...value, context: {...value.context, mode: 'editing'}}}>
        <PageSections sections={value.page.document.sections} mode="editing" />
      </PageRuntimeProvider>,
    );

    expect(publicHtml).toContain('id="section-visible-products"');
    expect(publicHtml).toContain('Saved Product');
    expect(publicHtml).not.toContain('author-id-is-not-operational-truth');
    expect(publicHtml).not.toContain('HIDDEN COPY');
    expect(editingHtml).toContain('HIDDEN COPY');
  });

  it('does not invent a brand or image when the saved catalog projection has none', () => {
    const value = payload();
    const product = value.runtime.sections[0].productIds!.products[0];
    product.brand = '';
    product.images = [];

    const html = renderToStaticMarkup(
      <PageRuntimeProvider payload={value}>
        <PageSections sections={value.page.document.sections} mode="public" />
      </PageRuntimeProvider>,
    );

    expect(html).not.toContain('Aurora Audio');
    expect(html).not.toContain('photo-1505740420928-5e560c06d30e');
  });

  it('does not read CMS documents or product arrays from localStorage', () => {
    const previous = Object.getOwnPropertyDescriptor(globalThis, 'localStorage');
    Object.defineProperty(globalThis, 'localStorage', {
      configurable: true,
      value: {
        getItem: () => { throw new Error('renderer touched localStorage'); },
      },
    });
    const value = payload();

    try {
      expect(() => renderToStaticMarkup(
        <PageRuntimeProvider payload={value}>
          <PageSections sections={value.page.document.sections} mode="public" />
        </PageRuntimeProvider>,
      )).not.toThrow();
    } finally {
      if (previous) {
        Object.defineProperty(globalThis, 'localStorage', previous);
      } else {
        Reflect.deleteProperty(globalThis, 'localStorage');
      }
    }
  });
});
