import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import {
  INSPECTOR_COLOR_CONTROL_FIELDS,
  INSPECTOR_COLLECTION_TAB_CONTROL_FIELDS,
  INSPECTOR_INQUIRY_CATEGORY_CONTROL_FIELDS,
  INSPECTOR_ITEM_CONTROL_FIELDS,
  INSPECTOR_SECTION_CONTROL_FIELDS,
  visibleInspectorColorFields,
  visibleInspectorCollectionTabFields,
  visibleInspectorFields,
  visibleInspectorInquiryCategoryFields,
  visibleInspectorItemFields,
} from '../../components/pages/SectionInspector';

const manifest = JSON.parse(readFileSync(
  new URL('../../../../../resources/cms/page-contract.v1.json', import.meta.url),
  'utf8',
));

const sectionControlInventory = [
  'title',
  'isVisible',
  'subtitle',
  'eyebrow',
  'content',
  'cardBadge',
  'cardTitle',
  'cardSubtitle',
  'cardPrice',
  'cardExtra',
  'tickerSpeed',
  'productIds',
  'dealEndDate',
  'dealBadge',
  'discountPercent',
  'beforeImageUrl',
  'beforeLabel',
  'afterImageUrl',
  'afterLabel',
  'videoEmbedUrl',
  'comparisonDimensionName',
  'comparisonOurBrandName',
  'comparisonCompetitorName',
  'formEmail',
  'contactPhone',
  'contactAddress',
  'contactButtonText',
  'formSuccessMessage',
  'subjectPlaceholder',
  'categoryPlaceholder',
  'messagePlaceholder',
  'inquiryCategories',
  'newsletterCouponId',
  'tabCategories',
  'tabs',
  'sliderHeight',
  'sliderAutoplay',
  'sliderInterval',
  'overlayOpacity',
  'mediaUrl',
  'buttonText',
  'buttonLink',
  'secondaryButtonText',
  'secondaryButtonLink',
  'items',
  'textAlignment',
  'paddingY',
  'backgroundColor',
  'customColors',
] as const;

const itemControlInventory = [
  'title',
  'eyebrow',
  'description',
  'imageUrl',
  'badge',
  'buttonText',
  'linkUrl',
  'secondaryButtonText',
  'secondaryButtonLink',
  'thirdButtonText',
  'thirdButtonLink',
  'authorRole',
  'rating',
  'authorAvatar',
  'handle',
  'value',
  'date',
  'price',
  'icon',
] as const;

const colorControlInventory = [
  'eyebrowColor',
  'titleColor',
  'contentColor',
  'primaryBtnBg',
  'primaryBtnText',
  'secondaryBtnBg',
  'secondaryBtnText',
  'customBgColor',
] as const;

const collectionTabControlInventory = ['id', 'label', 'sourceType', 'sourceId', 'productLimit'] as const;
const inquiryCategoryControlInventory = ['id', 'label'] as const;

describe('SectionInspector own-key visibility inventory', () => {
  it('locks every existing section, item, and color control to a canonical manifest field', () => {
    expect(INSPECTOR_SECTION_CONTROL_FIELDS).toEqual(sectionControlInventory);
    expect(INSPECTOR_ITEM_CONTROL_FIELDS).toEqual(itemControlInventory);
    expect(INSPECTOR_COLOR_CONTROL_FIELDS).toEqual(colorControlInventory);

    const commonFields = manifest.section.commonFields as string[];
    const typeFields = Object.values(manifest.section.sectionTypes)
      .flatMap((definition: any) => definition.fields as string[]);
    const canonicalSectionFields = new Set([...commonFields, ...typeFields]);

    expect(sectionControlInventory.filter(field => !canonicalSectionFields.has(field))).toEqual([]);
    expect(itemControlInventory.filter(field => !manifest.definitions.sectionItem.fields[field])).toEqual([]);
    expect(colorControlInventory.filter(field => !manifest.definitions.sectionColorConfig.fields[field])).toEqual([]);
  });

  it('keeps present empty, zero, false, null, array, and object fields visible while excluding missing fields', () => {
    const visible = visibleInspectorFields({
      title: '',
      subtitle: '',
      overlayOpacity: 0,
      sliderAutoplay: false,
      mediaUrl: null,
      items: [],
      customColors: {},
    });

    expect(visible).toEqual([
      'title',
      'subtitle',
      'sliderAutoplay',
      'overlayOpacity',
      'mediaUrl',
      'items',
      'customColors',
    ]);
    expect(visible).not.toContain('content');
    expect(visible).not.toContain('buttonText');
  });

  it('uses exact own-property semantics instead of inherited or sibling keys', () => {
    const section = Object.create({ subtitle: 'inherited fallback' });
    section.title = 'Own title';
    section.items = [{ id: 'item-1', title: '', description: '' }, { id: 'item-2', title: '' }];
    section.customColors = Object.assign(Object.create({ titleColor: '#fff' }), { eyebrowColor: '' });

    expect(visibleInspectorFields(section)).toContain('title');
    expect(visibleInspectorFields(section)).not.toContain('subtitle');
    expect(visibleInspectorItemFields(section.items[0])).toEqual(['title', 'description']);
    expect(visibleInspectorItemFields(section.items[1])).toEqual(['title']);
    expect(visibleInspectorColorFields(section.customColors)).toEqual(['eyebrowColor']);
  });

  it('checks every item independently and never infers fields from its type or a sibling item', () => {
    const first = { id: 'first', title: '', imageUrl: '', rating: 0 };
    const second = { id: 'second', title: '', badge: '', isHighlighted: false };

    expect(visibleInspectorItemFields(first)).toEqual(['title', 'imageUrl', 'rating']);
    expect(visibleInspectorItemFields(second)).toEqual(['title', 'badge']);
  });

  it('uses exact own-key visibility independently for every collection tab field', () => {
    expect(INSPECTOR_COLLECTION_TAB_CONTROL_FIELDS).toEqual(collectionTabControlInventory);
    expect(visibleInspectorCollectionTabFields({
      id: '',
      label: '',
      sourceType: '',
      sourceId: '',
      productLimit: 0,
    })).toEqual(collectionTabControlInventory);

    const first = Object.assign(Object.create({sourceId: 'inherited'}), {id: 'one', label: ''});
    const second = {id: 'two', sourceType: 'category', sourceId: '', productLimit: 4};
    expect(visibleInspectorCollectionTabFields(first)).toEqual(['id', 'label']);
    expect(visibleInspectorCollectionTabFields(second)).toEqual(['id', 'sourceType', 'sourceId', 'productLimit']);
  });

  it('uses exact own-key visibility independently for every inquiry category field', () => {
    expect(INSPECTOR_INQUIRY_CATEGORY_CONTROL_FIELDS).toEqual(inquiryCategoryControlInventory);
    expect(visibleInspectorInquiryCategoryFields({id: '', label: ''})).toEqual(['id', 'label']);
    expect(visibleInspectorInquiryCategoryFields({id: 'first'})).toEqual(['id']);
    expect(visibleInspectorInquiryCategoryFields({label: ''})).toEqual(['label']);
    expect(visibleInspectorInquiryCategoryFields(Object.create({label: 'inherited'}))).toEqual([]);
  });
});
