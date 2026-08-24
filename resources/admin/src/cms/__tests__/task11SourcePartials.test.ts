import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

const read = (relativePath: string) => readFileSync(
  new URL(relativePath, import.meta.url),
  'utf8',
);

const addSectionSource = read('../../components/pages/AddSectionModal.tsx');
const inspectorSource = read('../../components/pages/SectionInspector.tsx');
const rendererSource = read('../../components/pages/SectionRenderer.tsx');
const manifest = JSON.parse(read('../../../../../resources/cms/page-contract.v1.json'));
const fixture = JSON.parse(read('../../../../../tests/Fixtures/Cms/page-document-all-sections.v1.json'));

const sectionFixture = (type: string) => fixture.sections.find((section: {type: string}) => section.type === type);

describe('Task 11 source-faithful CMS partials', () => {
  it('preserves the ZIP4 Collection Tabs defaults, source mapping controls, and responsive product cards', () => {
    expect(addSectionSource).toContain("tabCategories: ['Electronics & Gadgets', 'Premium Audio', 'Best Sellers 2026', 'Studio Essentials']");
    expect(addSectionSource).toContain("{ id: 'tab-1', label: 'Electronics & Gadgets', sourceType: 'category', sourceId: 'cat-electronics', productLimit: 4 }");
    expect(inspectorSource).toContain('Map each customer tab to a live category or collection');
    expect(inspectorSource).toContain('Customer-Facing Tab Label');
    expect(inspectorSource).toContain('Source Type & Catalog Selection');
    expect(inspectorSource).toContain('Max {tab.productLimit || 4} items');
    expect(rendererSource).toContain('No products found for this tab');
    expect(rendererSource).toContain('className="w-20 h-20 rounded-xl object-cover bg-neutral-950 flex-shrink-0"');
    expect(rendererSource).toContain('runtimeSection.tabs?.[activeTabIndex]?.products');
  });

  it('uses the catalog runtime slug for every CMS product-card destination', () => {
    expect(rendererSource).toContain('href={`/products/${prod.slug}`}');
  });

  it('preserves the ZIP5 Contact form fields, repeatable categories, responsive DOM, and operational submit ownership', () => {
    for (const marker of [
      "subjectPlaceholder: 'e.g. Custom Engraving / Setup Inquiry'",
      "categoryPlaceholder: 'Select an inquiry category...'",
      "messagePlaceholder: 'Tell us about your audio setup or custom acoustic requirements...'",
      "{ id: 'cat-5', label: 'Other' }",
    ]) expect(addSectionSource).toContain(marker);

    expect(inspectorSource).toContain('Inquiry Categories ({(section.inquiryCategories || []).length})');
    expect(inspectorSource).toContain('Initialize Default Categories');
    expect(rendererSource).toContain('className="grid grid-cols-1 lg:grid-cols-12 gap-10 items-center"');
    expect(rendererSource).toContain('className="grid grid-cols-1 sm:grid-cols-2 gap-4"');
    expect(rendererSource).toContain('value={contactSubject}');
    expect(rendererSource).toContain('value={contactCategory}');
    expect(rendererSource).toContain("resolveFormContract('contact')");
    expect(rendererSource).toContain('readContactSuccess(body)');
  });

  it('preserves the exact ZIP6 success subtree and binds a code only from a validated new response', () => {
    expect(addSectionSource).toContain("formSuccessMessage: 'Your subscriber voucher has been sent to your email!'");
    expect(addSectionSource).toContain("newsletterCouponId: ''");
    expect(inspectorSource).toContain('SUBSCRIBER INCENTIVE COUPON');
    expect(inspectorSource).toContain('Selected coupon is unavailable or expired. Choose another coupon before publishing.');
    expect(rendererSource).toContain('Selected subscriber coupon is unavailable or expired. Please contact support.');
    expect(rendererSource).toContain('runtimeSection.newsletterCouponId?.valid');
    expect(rendererSource).not.toContain('INITIAL_PROMOTIONS');
    expect(rendererSource).toContain('className="p-5 rounded-2xl bg-emerald-500/20 border border-emerald-500/30 text-emerald-300 text-sm font-semibold max-w-md mx-auto space-y-2"');
    expect(rendererSource).toContain('Voucher Code:');
    expect(rendererSource).toContain('className="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-emerald-500/20 border border-emerald-500/40 text-emerald-200 font-mono text-xs"');
    expect(rendererSource).toContain('{newsletterCouponCode}');
    expect(rendererSource).toContain('fields.alreadySubscribed !== true');
    expect(rendererSource).toContain("typeof fields.couponCode === 'string'");
  });

  it('keeps the exact accepted fields canonical and the all-sections fixture lossless', () => {
    expect(manifest.definitions.collectionTab.fields).toEqual({
      id: {type: 'string', required: true},
      label: {type: 'string', required: true},
      sourceType: {type: 'string', required: true, enum: ['category', 'collection']},
      sourceId: {type: 'string', required: true},
      productLimit: {type: 'integer', required: true, enum: [3, 4, 6, 8]},
    });
    expect(manifest.definitions.inquiryCategory.fields).toEqual({
      id: {type: 'string', required: true},
      label: {type: 'string', required: true},
    });
    expect(sectionFixture('collection_tabs').tabs).toEqual(expect.arrayContaining([
      expect.objectContaining({id: 'tab-category', sourceType: 'category', productLimit: 3}),
      expect.objectContaining({id: 'tab-collection', sourceType: 'collection', productLimit: 8}),
    ]));
    expect(sectionFixture('contact_form')).toEqual(expect.objectContaining({
      subjectPlaceholder: 'Subject',
      categoryPlaceholder: 'Choose category',
      messagePlaceholder: 'Message',
      inquiryCategories: [{id: 'inquiry-general', label: 'General'}],
    }));
    expect(sectionFixture('newsletter_signup')).toEqual(expect.objectContaining({
      buttonText: 'Subscribe',
      formSuccessMessage: 'Subscribed',
      newsletterCouponId: '',
    }));
  });

  it('marks Contact renderer-capable once Task 12 binds its operational submit loop', () => {
    expect(manifest.section.sectionTypes.collection_tabs.renderer.capable).toBe(true);
    expect(manifest.section.sectionTypes.contact_form.renderer.capable).toBe(true);
    expect(manifest.section.sectionTypes.newsletter_signup.renderer.capable).toBe(true);
  });
});
