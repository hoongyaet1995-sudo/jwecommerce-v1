import { expect, test, type Page, type Route } from '@playwright/test';

const bootstrap = {
  user: { name: 'Julian Admin', email: 'julian@example.test', avatar: null },
  csrfToken: 'csrf-token',
  logoutUrl: '/logout',
  pagesUrl: '/admin/api/pages',
  mediaAssetsUrl: '/admin/api/media-assets',
};

type MockPage = Record<string, any> & { id: string; lockVersion: number; sections: Array<Record<string, any>> };

const pageFixture = (sections: Array<Record<string, any>>): MockPage => ({
  id: 'page-a',
  title: 'Original Page',
  slug: 'original-page',
  description: '',
  status: 'draft',
  template: 'landing',
  heroImage: null,
  seoTitle: '',
  seoDescription: '',
  createdAt: '2026-08-20',
  updatedAt: '2026-08-21',
  lockVersion: 1,
  hasPublishedSnapshot: false,
  hasUnpublishedChanges: true,
  sections,
});

const documentFrom = (current: MockPage) => ({
  title: current.title,
  slug: current.slug,
  description: current.description,
  template: current.template,
  heroImage: current.heroImage,
  seoTitle: current.seoTitle,
  seoDescription: current.seoDescription,
  sections: current.sections,
});

const draftPayload = (current: MockPage) => ({
  pageId: current.id,
  status: current.status,
  schemaVersion: 1,
  lockVersion: current.lockVersion,
  contentHash: `hash-${current.lockVersion}`,
  hasPublishedSnapshot: current.hasPublishedSnapshot,
  hasUnpublishedChanges: current.hasUnpublishedChanges,
  document: documentFrom(current),
  updatedAt: '2026-08-21T00:00:00.000000Z',
});

const openEditor = async (page: Page) => {
  await page.goto('/tests/fixtures/media-upload-stale-state.html');
  await page.getByRole('button', { name: 'Edit Layout' }).click();
};

const fulfillPageRoute = async (
  route: Route,
  current: MockPage,
  patches: Array<Record<string, any>>,
  lifecycle: string[],
  onPatch?: (route: Route, envelope: Record<string, any>, patchNumber: number) => Promise<MockPage | null>,
): Promise<MockPage> => {
  const request = route.request();
  const path = new URL(request.url()).pathname;

  if (request.method() === 'GET' && path === '/admin/api/pages') {
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [current] }) });
    return current;
  }

  if (request.method() === 'GET' && path === `/admin/api/pages/${current.id}/draft`) {
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: draftPayload(current) }) });
    return current;
  }

  if (request.method() === 'PATCH' && path === `/admin/api/pages/${current.id}/draft`) {
    const envelope = request.postDataJSON() as Record<string, any>;
    patches.push(structuredClone(envelope));
    const controlled = onPatch ? await onPatch(route, envelope, patches.length) : undefined;
    if (controlled === null) return current;
    const next = controlled ?? {
      ...current,
      ...envelope.document,
      lockVersion: current.lockVersion + 1,
      hasUnpublishedChanges: true,
    };
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: draftPayload(next) }) });
    return next;
  }

  if (request.method() === 'POST') {
    lifecycle.push(path);
  }
  await route.abort();
  return current;
};

test('Inspector uses exact own keys across scalar, type-specific, item, layout, and nested color controls', async ({ page }) => {
  let current = pageFixture([
    {
      id: 'slider',
      type: 'hero_slider',
      title: 'Sparse slider',
      isVisible: true,
      subtitle: '',
      buttonText: '',
      sliderHeight: 'compact',
      sliderAutoplay: false,
      sliderInterval: 0,
      overlayOpacity: 0,
      textAlignment: 'center',
      paddingY: 'normal',
      backgroundColor: 'bg-neutral-950',
      items: [
        { id: 'slide-one', title: '', description: '', imageUrl: '', buttonText: '' },
        { id: 'slide-two', title: '', badge: '' },
      ],
      customColors: { eyebrowColor: '', titleColor: '#ffffff' },
    },
    { id: 'split', type: 'split_hero', title: 'Sparse split', isVisible: true, cardTitle: '' },
    { id: 'products', type: 'product_grid', title: 'Sparse products', isVisible: true },
    { id: 'compare', type: 'before_after', title: 'Sparse comparison', isVisible: true, beforeLabel: '' },
    { id: 'contact', type: 'contact_form', title: 'Sparse contact', isVisible: true, formEmail: '' },
    { id: 'media', type: 'hero_banner', title: 'Sparse media', isVisible: true, mediaUrl: '' },
  ]);
  const patches: Array<Record<string, any>> = [];
  const lifecycle: string[] = [];

  await page.addInitScript(value => { window.__JW_ADMIN__ = value; }, bootstrap);
  await page.route('**/admin/api/pages**', async route => {
    current = await fulfillPageRoute(route, current, patches, lifecycle);
  });

  await openEditor(page);

  await expect(page.getByPlaceholder('e.g. THE DESIGN MANIFESTO')).toBeVisible();
  await expect(page.getByPlaceholder('Descriptive copy or narrative block...')).toHaveCount(0);
  await expect(page.getByPlaceholder('e.g. Shop Now')).toBeVisible();
  await expect(page.getByPlaceholder('/collections/...')).toHaveCount(0);
  await expect(page.getByPlaceholder('e.g. Learn More')).toHaveCount(0);
  await expect(page.getByText('Hero Slider Settings')).toBeVisible();
  await expect(page.getByText('Autoplay').locator('..').locator('select')).toHaveValue('false');
  await expect(page.getByText('Dark Overlay Contrast').locator('..').locator('..').locator('input[type="range"]')).toHaveValue('0');
  await expect(page.getByPlaceholder('https://images.unsplash.com/... or upload')).toHaveCount(1);
  await expect(page.getByPlaceholder('e.g. Flagship Launch 2026')).toHaveCount(1);
  await expect(page.getByPlaceholder('Compelling story copy for this slide...')).toHaveCount(1);
  await expect(page.getByPlaceholder('Shop Now', { exact: true })).toHaveCount(1);
  await expect(page.getByPlaceholder('/products/...')).toHaveCount(0);
  await expect(page.getByPlaceholder('#818cf8')).toHaveCount(1);
  await expect(page.getByPlaceholder('#ffffff')).toHaveCount(1);
  await expect(page.getByPlaceholder('#d4d4d8')).toHaveCount(0);
  await expect(page.getByPlaceholder('Bg #4f46e5')).toHaveCount(0);

  await page.getByText('Sparse split', { exact: true }).first().click();
  await expect(page.getByPlaceholder('e.g. Aurora Titanium Series X')).toBeVisible();
  await expect(page.getByPlaceholder('e.g. Flagship Model')).toHaveCount(0);
  await expect(page.getByPlaceholder('499')).toHaveCount(0);
  await expect(page.getByText('Layout & Theme')).toHaveCount(0);
  await expect(page.getByText('Brand Palette & Colors')).toHaveCount(0);

  await page.getByText('Sparse products', { exact: true }).first().click();
  await expect(page.getByText(/Select Featured Products/)).toHaveCount(0);

  await page.getByText('Sparse comparison', { exact: true }).first().click();
  await expect(page.getByPlaceholder('e.g. Standard Ambience')).toBeVisible();
  await expect(page.getByPlaceholder('https://... (Standard Environment)')).toHaveCount(0);
  await expect(page.getByPlaceholder('e.g. Aurora Pure Silence')).toHaveCount(0);

  await page.getByText('Sparse contact', { exact: true }).first().click();
  await expect(page.getByPlaceholder('concierge@aurora-audio.se')).toBeVisible();
  await expect(page.getByPlaceholder('+46 8 123 4567 (Mon–Fri 09:00–18:00 CET)')).toHaveCount(0);
  await expect(page.getByPlaceholder('Send Inquiries to Atelier')).toHaveCount(0);

  await page.getByText('Sparse media', { exact: true }).first().click();
  await expect(page.getByPlaceholder('https://images.unsplash.com/... or upload')).toBeVisible();
  await expect(page.getByText('Dark Overlay Opacity')).toHaveCount(0);
  expect(patches).toHaveLength(0);
  expect(lifecycle).toEqual([]);
});

test('present-empty fields stay editable and saved while missing fields never enter the PATCH or reload UI', async ({ page }) => {
  let current = pageFixture([{
    id: 'hero',
    type: 'hero_banner',
    title: 'Headline',
    isVisible: true,
    subtitle: '',
    mediaUrl: '',
  }]);
  const patches: Array<Record<string, any>> = [];
  const lifecycle: string[] = [];

  await page.addInitScript(value => { window.__JW_ADMIN__ = value; }, bootstrap);
  await page.route('**/admin/api/pages**', async route => {
    current = await fulfillPageRoute(route, current, patches, lifecycle);
  });

  await openEditor(page);
  const subtitle = page.getByPlaceholder('e.g. THE DESIGN MANIFESTO');
  await expect(subtitle).toHaveValue('');
  await expect(page.getByPlaceholder('Descriptive copy or narrative block...')).toHaveCount(0);
  await expect(page.getByText('Call to Action Buttons')).toHaveCount(0);
  await subtitle.fill('Persisted eyebrow');
  await expect.poll(() => patches.length, { timeout: 2_000 }).toBe(1);

  const savedSection = patches[0].document.sections[0];
  expect(savedSection).toMatchObject({ subtitle: 'Persisted eyebrow', mediaUrl: '' });
  expect(savedSection).not.toHaveProperty('content');
  expect(savedSection).not.toHaveProperty('buttonText');
  expect(savedSection).not.toHaveProperty('overlayOpacity');
  expect(savedSection).not.toHaveProperty('customColors');

  await page.getByRole('button', { name: 'Pages' }).click();
  await page.getByRole('button', { name: 'Edit Layout' }).click();
  await expect(page.getByPlaceholder('e.g. THE DESIGN MANIFESTO')).toHaveValue('Persisted eyebrow');
  await expect(page.getByPlaceholder('Descriptive copy or narrative block...')).toHaveCount(0);
});

test('JSON export is the canonical body and its own output imports only to the current draft', async ({ page }) => {
  let current = pageFixture([{ id: 'hero', type: 'hero_banner', title: 'Headline', isVisible: true, subtitle: '' }]);
  const patches: Array<Record<string, any>> = [];
  const lifecycle: string[] = [];

  await page.addInitScript(value => { window.__JW_ADMIN__ = value; }, bootstrap);
  await page.route('**/admin/api/pages**', async route => {
    current = await fulfillPageRoute(route, current, patches, lifecycle);
  });

  await openEditor(page);
  await page.locator('button[title="Export / Import JSON Layout"]').click();
  const textarea = page.getByRole('textbox').filter({ has: page.locator('xpath=..') }).last();
  const exported = JSON.parse(await textarea.inputValue());

  expect(exported).toEqual(documentFrom(current));
  for (const key of ['id', 'pageId', 'status', 'schemaVersion', 'lockVersion', 'contentHash', 'createdAt', 'updatedAt', 'hasPublishedSnapshot', 'hasUnpublishedChanges']) {
    expect(exported).not.toHaveProperty(key);
  }

  await page.getByRole('button', { name: 'Apply / Import Layout' }).click();
  await expect.poll(() => patches.length).toBe(1);
  expect(patches[0].document).toEqual(exported);
  expect(patches[0]).not.toHaveProperty('pageId');
  expect(lifecycle).toEqual([]);
  await expect(page.getByText('Page Layout JSON Schema')).toHaveCount(0);
});

test('rejected import leaves current state and modal unchanged and performs no draft or lifecycle write', async ({ page }) => {
  let current = pageFixture([{ id: 'hero', type: 'hero_banner', title: 'Visible original', isVisible: true }]);
  const patches: Array<Record<string, any>> = [];
  const lifecycle: string[] = [];

  await page.addInitScript(value => { window.__JW_ADMIN__ = value; }, bootstrap);
  await page.route('**/admin/api/pages**', async route => {
    current = await fulfillPageRoute(route, current, patches, lifecycle);
  });

  await openEditor(page);
  await page.locator('button[title="Export / Import JSON Layout"]').click();
  const textarea = page.locator('textarea').last();
  const rejected = { ...documentFrom(current), id: 'page-b', status: 'published', unknown: true };
  await textarea.fill(JSON.stringify(rejected));
  await page.getByRole('button', { name: 'Apply / Import Layout' }).click();

  await expect(page.getByText('Page Layout JSON Schema')).toBeVisible();
  await expect(page.getByPlaceholder('Section main heading')).toHaveValue('Visible original');
  expect(patches).toEqual([]);
  expect(lifecycle).toEqual([]);
});

test('successful import flushes the pending draft first, saves the imported body second, and never calls lifecycle', async ({ page }) => {
  let current = pageFixture([{ id: 'hero', type: 'hero_banner', title: 'Original headline', isVisible: true }]);
  const patches: Array<Record<string, any>> = [];
  const lifecycle: string[] = [];

  await page.addInitScript(value => { window.__JW_ADMIN__ = value; }, bootstrap);
  await page.route('**/admin/api/pages**', async route => {
    current = await fulfillPageRoute(route, current, patches, lifecycle);
  });

  await openEditor(page);
  await page.locator('#page-visual-builder input[type="text"]').first().fill('Pending local title');
  await page.locator('button[title="Export / Import JSON Layout"]').click();
  const imported = { ...documentFrom(current), title: 'Imported title', sections: [{ ...current.sections[0], title: 'Imported headline' }] };
  await page.locator('textarea').last().fill(JSON.stringify(imported));
  await page.getByRole('button', { name: 'Apply / Import Layout' }).click();

  await expect.poll(() => patches.length, { timeout: 2_000 }).toBe(2);
  expect(patches[0]).toMatchObject({ lockVersion: 1, document: { title: 'Pending local title' } });
  expect(patches[1]).toMatchObject({ lockVersion: 2, document: { title: 'Imported title', sections: [{ title: 'Imported headline' }] } });
  expect(lifecycle).toEqual([]);
  await expect(page.locator('#page-visual-builder input[type="text"]').first()).toHaveValue('Imported title');
  await expect(page.getByText('Page Layout JSON Schema')).toHaveCount(0);
});

test('import save conflict keeps imported local state and modal open without destructive refetch', async ({ page }) => {
  let current = pageFixture([{ id: 'hero', type: 'hero_banner', title: 'Original headline', isVisible: true }]);
  const patches: Array<Record<string, any>> = [];
  const lifecycle: string[] = [];
  let listGets = 0;

  await page.addInitScript(value => { window.__JW_ADMIN__ = value; }, bootstrap);
  await page.route('**/admin/api/pages**', async route => {
    if (route.request().method() === 'GET' && new URL(route.request().url()).pathname === '/admin/api/pages') listGets += 1;
    current = await fulfillPageRoute(route, current, patches, lifecycle, async (patchRoute, envelope, patchNumber) => {
      if (patchNumber !== 2) {
        return { ...current, ...envelope.document, lockVersion: current.lockVersion + 1 };
      }
      await patchRoute.fulfill({
        status: 409,
        contentType: 'application/json',
        body: JSON.stringify({
          message: 'The page draft was changed by another session.',
          code: 'cms_version_conflict',
          errors: { lockVersion: ['Reload or reconcile your local changes before saving again.'] },
          currentLockVersion: 9,
        }),
      });
      return null;
    });
  });

  await openEditor(page);
  await page.locator('#page-visual-builder input[type="text"]').first().fill('Pending local title');
  await page.locator('button[title="Export / Import JSON Layout"]').click();
  const imported = { ...documentFrom(current), title: 'Conflict-kept import' };
  await page.locator('textarea').last().fill(JSON.stringify(imported));
  await page.getByRole('button', { name: 'Apply / Import Layout' }).click();

  await expect.poll(() => patches.length, { timeout: 2_000 }).toBe(2);
  await expect(page.getByText('Page Layout JSON Schema')).toBeVisible();
  await expect(page.locator('#page-visual-builder input[type="text"]').first()).toHaveValue('Conflict-kept import');
  expect(listGets).toBe(1);
  expect(lifecycle).toEqual([]);
});
