import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { DraftImportError, parseDraftImport } from '../pageImport';

const allSectionsDocument = JSON.parse(readFileSync(
  new URL('../../../../../tests/Fixtures/Cms/page-document-all-sections.v1.json', import.meta.url),
  'utf8',
));

const mediaHostParity = JSON.parse(readFileSync(
  new URL('../../../../../tests/Fixtures/Cms/media-url-host-parity.json', import.meta.url),
  'utf8',
)) as { unsafe: string[]; safe: string[] };

const minimalDocument = () => ({
  title: 'Imported page',
  slug: 'imported-page',
  description: '',
  template: 'landing',
  heroImage: null,
  seoTitle: '',
  seoDescription: '',
  sections: [{
    id: 'section-1',
    type: 'hero_slider',
    title: '',
    isVisible: false,
    subtitle: '',
    overlayOpacity: 0,
    sliderInterval: 0,
    sliderAutoplay: false,
    items: [],
    customColors: {},
  }],
});

const expectImportError = (
  value: unknown,
  path: string,
  messageFragment?: string,
) => {
  let error: unknown;

  try {
    parseDraftImport(typeof value === 'string' ? value : JSON.stringify(value), 'page-current');
  } catch (caught) {
    error = caught;
  }

  expect(error).toBeInstanceOf(DraftImportError);
  expect(error).toMatchObject({ code: 'cms_draft_import_invalid', path });
  if (messageFragment) expect((error as Error).message).toContain(messageFragment);
};

describe('parseDraftImport', () => {
  it('accepts the complete canonical manifest fixture without losing any present token', () => {
    const parsed = parseDraftImport(JSON.stringify(allSectionsDocument), 'page-current');

    expect(parsed).toEqual(allSectionsDocument);
    expect(parsed).not.toBe(allSectionsDocument);
    expect(parsed.heroImage).toBeNull();
    expect(parsed.sections[0].subtitle).toBe('');
    expect(parsed.sections[0].overlayOpacity).toBe(0);
    expect(parsed.sections[0].items).toEqual([]);
    expect(parsed.sections[1].sliderAutoplay).toBe(false);
  });

  it('returns a detached document whose later editor mutation cannot alias another parse', () => {
    const raw = JSON.stringify(minimalDocument());
    const first = parseDraftImport(raw, 'page-current');
    const second = parseDraftImport(raw, 'page-current');

    first.sections[0].title = 'Mutated locally';
    first.sections[0].customColors = { eyebrowColor: '#ffffff' };

    expect(second.sections[0].title).toBe('');
    expect(second.sections[0].customColors).toEqual({});
  });

  it.each([
    ['malformed JSON', '{not json', '$'],
    ['null root', 'null', '$'],
    ['array root', '[]', '$'],
    ['scalar root', '42', '$'],
  ])('rejects %s with a stable root path', (_name, raw, path) => {
    expectImportError(raw, path);
  });

  it.each([
    ['title', { title: undefined }],
    ['slug', { slug: undefined }],
    ['template', { template: undefined }],
    ['sections', { sections: undefined }],
  ])('rejects a missing required document field at %s', (path, omission) => {
    const document = minimalDocument() as Record<string, unknown>;
    delete document[Object.keys(omission)[0]];
    expectImportError(document, path, 'required');
  });

  it.each([
    'id',
    'pageId',
    'status',
    'published',
    'publishedAt',
    'archivedAt',
    'createdAt',
    'updatedAt',
    'lockVersion',
    'contentHash',
    'hasPublishedSnapshot',
    'hasUnpublishedChanges',
    'document',
    'page',
  ])('rejects root ownership/envelope key %s before it can target current page identity', key => {
    expectImportError({ ...minimalDocument(), [key]: key === 'document' ? minimalDocument() : 'attacker' }, key);
  });

  it('rejects both unsupported and envelope schema versions while treating current page ID as trusted context only', () => {
    expectImportError({ ...minimalDocument(), schemaVersion: 2 }, 'schemaVersion', 'Unsupported schema version');
    expectImportError({ ...minimalDocument(), schemaVersion: 1 }, 'schemaVersion');

    let error: unknown;
    try {
      parseDraftImport(JSON.stringify(minimalDocument()), '');
    } catch (caught) {
      error = caught;
    }
    expect(error).toMatchObject({ code: 'cms_draft_import_invalid', path: 'currentPageId' });
  });

  it.each([
    ['unknown document field', (document: any) => { document.invented = true; }, 'invented'],
    ['unknown section field', (document: any) => { document.sections[0].invented = true; }, 'sections.0.invented'],
    ['unknown color field', (document: any) => { document.sections[0].customColors.invented = '#fff'; }, 'sections.0.customColors.invented'],
    ['unknown nested item field', (document: any) => {
      document.sections[0].items = [{ id: 'item-1', title: 'Item', invented: true }];
    }, 'sections.0.items.0.invented'],
    ['lifecycle key inside a section', (document: any) => { document.sections[0].status = 'published'; }, 'sections.0.status'],
  ])('rejects %s at its exact path', (_name, mutate, path) => {
    const document = minimalDocument();
    mutate(document);
    expectImportError(document, path);
  });

  it.each([
    ['unsupported section type', (document: any) => { document.sections[0].type = 'executable_widget'; }, 'sections.0.type'],
    ['missing section type', (document: any) => { delete document.sections[0].type; }, 'sections.0.type'],
    ['missing section title', (document: any) => { delete document.sections[0].title; }, 'sections.0.title'],
    ['missing section visibility', (document: any) => { delete document.sections[0].isVisible; }, 'sections.0.isVisible'],
    ['non-object section', (document: any) => { document.sections[0] = 'hero'; }, 'sections.0'],
  ])('rejects malformed sections: %s', (_name, mutate, path) => {
    const document = minimalDocument();
    mutate(document);
    expectImportError(document, path);
  });

  it.each([
    ['wrong scalar type', (document: any) => { document.sections[0].overlayOpacity = '0'; }, 'sections.0.overlayOpacity'],
    ['wrong object type', (document: any) => { document.sections[0].customColors = ['#fff']; }, 'sections.0.customColors'],
    ['invalid enum', (document: any) => { document.template = 'executable'; }, 'template'],
    ['data URL', (document: any) => { document.heroImage = 'data:image/png;base64,AA=='; }, 'heroImage'],
    ['HTTP URL', (document: any) => { document.heroImage = 'http://cdn.example.com/hero.jpg'; }, 'heroImage'],
    ['private host URL', (document: any) => { document.heroImage = 'https://localhost/hero.jpg'; }, 'heroImage'],
    ['credential URL', (document: any) => { document.heroImage = 'https://user:pass@cdn.example.com/hero.jpg'; }, 'heroImage'],
    ['IPv4-mapped private IPv6 URL', (document: any) => { document.heroImage = 'https://[::ffff:127.0.0.1]/hero.jpg'; }, 'heroImage'],
  ])('rejects invalid contract value: %s', (_name, mutate, path) => {
    const document = minimalDocument();
    mutate(document);
    expectImportError(document, path);
  });

  it.each(mediaHostParity.unsafe)('rejects PHP-unsafe raw hostname vector %s', url => {
    const document = minimalDocument();
    document.heroImage = url;

    expectImportError(document, 'heroImage');
  });

  it.each(mediaHostParity.safe)('accepts PHP-safe explicit ASCII hostname vector %s', url => {
    const document = minimalDocument();
    document.heroImage = url;

    expect(parseDraftImport(JSON.stringify(document), 'page-current').heroImage)
      .toBe(url);
  });

  it('rejects duplicate and invalid section IDs with exact paths', () => {
    const duplicate = minimalDocument();
    duplicate.sections.push({ ...duplicate.sections[0] });
    expectImportError(duplicate, 'sections.1.id', 'Duplicate');

    const invalid = minimalDocument();
    invalid.sections[0].id = '';
    expectImportError(invalid, 'sections.0.id');

    const whitespace = minimalDocument();
    whitespace.sections[0].id = 'not a stable id';
    expectImportError(whitespace, 'sections.0.id');
  });

  it('rejects duplicate and invalid IDs in every canonical repeatable-item family', () => {
    const items = minimalDocument();
    items.sections[0].items = [
      { id: 'item-1', title: 'One' },
      { id: 'item-1', title: 'Two' },
    ];
    expectImportError(items, 'sections.0.items.1.id', 'Duplicate');

    const tabs = minimalDocument() as any;
    tabs.sections = [{
      id: 'collections',
      type: 'collection_tabs',
      title: 'Collections',
      isVisible: true,
      tabs: [
        { id: 'tab-1', label: 'One', sourceType: 'category', sourceId: '1', productLimit: 3 },
        { id: 'tab-1', label: 'Two', sourceType: 'collection', sourceId: '2', productLimit: 4 },
      ],
    }];
    expectImportError(tabs, 'sections.0.tabs.1.id', 'Duplicate');

    const inquiries = minimalDocument() as any;
    inquiries.sections = [{
      id: 'contact',
      type: 'contact_form',
      title: 'Contact',
      isVisible: true,
      inquiryCategories: [{ id: '', label: 'General' }],
    }];
    expectImportError(inquiries, 'sections.0.inquiryCategories.0.id');
  });
});

it('keeps numeric typography range validation consistent with saved drafts',()=>{
 const document=minimalDocument();
 const numericDocument={...document,sections:[{id:'cover',type:'media_text_split',title:'Cover',isVisible:true,typography:{lead:{fontSize:20,lineHeight:2,letterSpacing:2}}}]};
 expect(()=>parseDraftImport(JSON.stringify(numericDocument), 'page-current')).not.toThrow();
 expect(()=>parseDraftImport(JSON.stringify({...numericDocument,sections:[{...numericDocument.sections[0],typography:{lead:{lineHeight:3.1}}}]}), 'page-current')).toThrow();
});
