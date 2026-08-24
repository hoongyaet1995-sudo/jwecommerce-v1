import { expect, test, type BrowserContext, type ConsoleMessage, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const testDirectory = fileURLToPath(new URL('.', import.meta.url));
const repositoryRoot = resolve(testDirectory, '../../..');
const evidenceDirectory = resolve(repositoryRoot, 'test-results/task-14/pre-cutover');
const harness = resolve(testDirectory, 'fixtures/task14-live-harness.php');
const localImage = resolve(repositoryRoot, 'public/images/source/jwstudio_logo_1786766390670.jpg');
const php = 'C:\\xampp\\php\\php.exe';

type HarnessEvidence = {
  action: string;
  counts?: {counts: Record<string, number>; rowDigests: Record<string, string>; hash: string};
  before?: {counts: Record<string, number>; rowDigests: Record<string, string>; hash: string};
  after?: {counts: Record<string, number>; rowDigests: Record<string, string>; hash: string};
  owned?: {
    ids: Record<string, number | number[] | null>;
    page: null | {
      status: string;
      isPublishedLegacyFlag: boolean;
      draftHash: string;
      draftVersion: number;
      publishedHash: string | null;
      publishedVersion: number | null;
      sectionIds: string[];
      hero: Record<string, unknown>;
      tabs: Array<Record<string, unknown>>;
      testimonialItemCount: number;
      testimonialFirstTitle: string;
    };
    history: {
      contactCount: number;
      contactPageIds: Array<number | null>;
      contactStages: string[];
      newsletterCount: number;
      newsletterPageIds: Array<number | null>;
      newsletterStages: string[];
    };
  };
  snapshot?: HarnessEvidence['owned'];
  ownedRemaining?: Record<string, number>;
  ownedRemainingHash?: string;
  expectedBaselineHash?: string | null;
  baselineMatches?: boolean;
};

const environment = () => {
  const required = (name: string): string => {
    const value = process.env[name];
    if (!value) throw new Error(`Missing acceptance environment ${name}.`);
    return value;
  };

  return {
    token: required('CMS_ACCEPTANCE_TOKEN'),
    adminEmail: required('CMS_ACCEPTANCE_ADMIN_EMAIL'),
    adminPassword: required('CMS_ACCEPTANCE_ADMIN_PASSWORD'),
    couponCode: required('CMS_ACCEPTANCE_COUPON_CODE'),
    slug: required('CMS_ACCEPTANCE_PAGE_SLUG'),
    contactEmail: required('CMS_ACCEPTANCE_CONTACT_EMAIL'),
    newsletterEmail: required('CMS_ACCEPTANCE_NEWSLETTER_EMAIL'),
    categoryId: required('CMS_ACCEPTANCE_CATEGORY_ID'),
    collectionId: required('CMS_ACCEPTANCE_COLLECTION_ID'),
    productId: required('CMS_ACCEPTANCE_PRODUCT_ID'),
    couponId: required('CMS_ACCEPTANCE_COUPON_ID'),
    baselineDigest: required('CMS_ACCEPTANCE_BASELINE_DIGEST'),
  };
};

const runHarness = (action: 'inspect' | 'cleanup'): HarnessEvidence => JSON.parse(execFileSync(
  php,
  [harness, action],
  {
    cwd: repositoryRoot,
    env: { ...process.env, APP_URL: 'http://127.0.0.1:8500' },
    encoding: 'utf8',
  },
)) as HarnessEvidence;

const writeEvidence = (name: string, evidence: unknown): void => {
  mkdirSync(evidenceDirectory, { recursive: true });
  writeFileSync(resolve(evidenceDirectory, name), `${JSON.stringify(evidence, null, 2)}\n`);
};

const registerDiagnostics = (
  context: BrowserContext,
  consoleErrors: string[],
  pageErrors: string[],
): void => {
  const registerPage = (page: Page) => {
    page.on('console', (message: ConsoleMessage) => {
      if (message.type() === 'error') consoleErrors.push(`${message.text()} @ ${message.location().url}`);
    });
    page.on('pageerror', error => pageErrors.push(error.message));
  };
  context.pages().forEach(registerPage);
  context.on('page', registerPage);
};

const stubExternalImages = async (context: BrowserContext): Promise<void> => {
  await context.route('https://images.unsplash.com/**', route => route.fulfill({
    status: 200,
    contentType: 'image/jpeg',
    path: localImage,
  }));
};

const login = async (page: Page, email: string, password: string): Promise<void> => {
  await page.goto('/admin/pages');
  await page.getByLabel('Admin Work Email').fill(email);
  await page.getByLabel('Password').fill(password);
  await page.getByRole('button', { name: 'Sign in to Admin' }).click();
  await expect(page).toHaveURL(/\/admin\/pages$/);
  await expect(page.locator('#page-admin-directory')).toBeVisible();
};

const runtimePayload = async (scope: Page | ReturnType<Page['frameLocator']>) => JSON.parse(
  await scope.locator('#cms-page-runtime').textContent() ?? '{}',
) as {
  page: {stage: string; document: {title: string; sections: Array<Record<string, unknown>>}};
  forms: {
    contact: Record<string, {audience: string}>;
    newsletter: Record<string, {audience: string}>;
  };
  context: {mode: string};
};

const documentFor = (values: ReturnType<typeof environment>) => ({
  title: 'Task 14 Acceptance Page',
  slug: values.slug,
  description: 'Task 14 live XAMPP lifecycle acceptance.',
  template: 'landing',
  heroImage: null,
  seoTitle: 'Task 14 Acceptance',
  seoDescription: 'Protected pre-cutover acceptance page.',
  sections: [
    {
      id: `task14-${values.token}-hero`,
      type: 'hero_banner',
      title: 'Initial Acceptance Headline',
      subtitle: 'INITIAL EYEBROW',
      content: 'Initial acceptance description.',
      mediaUrl: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=1600&auto=format&fit=crop&q=80',
      mediaType: 'image',
      buttonText: 'Initial Primary',
      buttonLink: '/shop',
      secondaryButtonText: 'Initial Secondary',
      secondaryButtonLink: '/collections/all',
      textAlignment: 'center',
      backgroundColor: 'bg-neutral-900',
      customColors: {
        eyebrowColor: '#818cf8',
        titleColor: '#ffffff',
        contentColor: '#d4d4d8',
        primaryBtnBg: '#4f46e5',
        primaryBtnText: '#ffffff',
        secondaryBtnBg: 'rgba(255, 255, 255, 0.1)',
        secondaryBtnText: '#ffffff',
        customBgColor: '#0a0a0a',
      },
      textColor: 'light',
      paddingY: 'normal',
      overlayOpacity: 50,
      isVisible: true,
    },
    {
      id: `task14-${values.token}-collection`,
      type: 'collection_tabs',
      title: 'Acceptance Commerce Runtime',
      subtitle: 'REAL MYSQL CATALOG',
      content: 'Products are resolved by canonical category and collection mappings.',
      tabCategories: ['Acceptance Category', 'Acceptance Collection'],
      tabs: [
        {id: `task14-${values.token}-tab-category`, label: 'Acceptance Category', sourceType: 'category', sourceId: values.categoryId, productLimit: 4},
        {id: `task14-${values.token}-tab-collection`, label: 'Acceptance Collection', sourceType: 'collection', sourceId: values.collectionId, productLimit: 4},
      ],
      textAlignment: 'center',
      backgroundColor: 'bg-neutral-900',
      textColor: 'light',
      paddingY: 'normal',
      isVisible: true,
    },
    {
      id: `task14-${values.token}-testimonials`,
      type: 'testimonials_slider',
      title: 'Acceptance Repeatable Proof',
      subtitle: 'NESTED MYSQL DOCUMENT DATA',
      content: 'Repeatable testimonial items remain canonical.',
      columnsCount: 3,
      textAlignment: 'center',
      backgroundColor: 'bg-neutral-950',
      textColor: 'light',
      paddingY: 'normal',
      isVisible: true,
      items: [
        {id: `task14-${values.token}-review-1`, title: 'Initial nested reviewer', description: 'Initial nested quote.', authorRole: 'Acceptance Reviewer', authorAvatar: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=200&auto=format&fit=crop&q=80', rating: 5},
        {id: `task14-${values.token}-review-2`, title: 'Second nested reviewer', description: 'Second nested quote.', authorRole: 'Studio Reviewer', authorAvatar: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=200&auto=format&fit=crop&q=80', rating: 4},
      ],
    },
    {
      id: `task14-${values.token}-contact`,
      type: 'contact_form',
      title: 'Task 14 Contact Acceptance',
      subtitle: 'REAL ENDPOINT PERSISTENCE',
      content: 'Submit one acceptance-owned historical contact record.',
      formEmail: 'acceptance@example.test',
      contactPhone: '+60 3 0000 0014',
      contactAddress: 'JW Studio Acceptance Lab',
      contactButtonText: 'Send Acceptance Inquiry',
      formSuccessMessage: 'Task 14 contact submission accepted.',
      subjectPlaceholder: 'Acceptance subject',
      categoryPlaceholder: 'Choose acceptance category',
      messagePlaceholder: 'Acceptance message',
      inquiryCategories: [
        {id: `task14-${values.token}-inquiry`, label: 'Acceptance Enquiry'},
        {id: `task14-${values.token}-support`, label: 'Acceptance Support'},
      ],
      textAlignment: 'left',
      backgroundColor: 'bg-neutral-950',
      textColor: 'light',
      paddingY: 'normal',
      isVisible: true,
    },
    {
      id: `task14-${values.token}-newsletter`,
      type: 'newsletter_signup',
      title: 'Task 14 Newsletter Acceptance',
      subtitle: 'RESPONSE ONLY COUPON',
      content: 'The coupon code appears only after an eligible response.',
      buttonText: 'Claim 14% Voucher',
      formSuccessMessage: 'Task 14 subscriber voucher accepted!',
      newsletterCouponId: values.couponId,
      textAlignment: 'center',
      backgroundColor: 'bg-neutral-950',
      textColor: 'light',
      paddingY: 'normal',
      isVisible: true,
    },
  ],
});

const addTemplate = async (page: Page, builder: ReturnType<Page['locator']>, title: string): Promise<void> => {
  await builder.getByRole('button', { name: 'Add Section' }).first().click();
  const modal = page.locator('#add-section-modal');
  await expect(modal).toBeVisible();
  await modal.getByRole('button').filter({ hasText: title }).click();
  await expect(modal).toBeHidden();
};

const overflow = async (page: Page) => page.locator('html').evaluate(element => ({
  clientWidth: element.clientWidth,
  scrollWidth: element.scrollWidth,
}));

test('Task 14A real XAMPP CMS lifecycle is clean before official cutover', async ({ browser }) => {
  const values = environment();
  const consoleErrors: string[] = [];
  const pageErrors: string[] = [];
  const firstContext = await browser.newContext({
    baseURL: 'http://127.0.0.1:8500',
    viewport: {width: 1600, height: 1000},
  });
  registerDiagnostics(firstContext, consoleErrors, pageErrors);
  await stubExternalImages(firstContext);
  const firstPage = await firstContext.newPage();
  await login(firstPage, values.adminEmail, values.adminPassword);

  const createResponsePromise = firstPage.waitForResponse(response => (
    response.url().endsWith('/admin/api/pages') && response.request().method() === 'POST'
  ));
  await firstPage.getByRole('button', { name: 'Create Layout Page' }).click();
  const createResponse = await createResponsePromise;
  expect(createResponse.status()).toBe(201);
  const pageId = String((await createResponse.json() as {data: {id: string}}).data.id);
  const builder = firstPage.locator('#page-visual-builder');
  await expect(builder).toBeVisible();

  await addTemplate(firstPage, builder, 'Category & Collection Tab Showcase');
  await addTemplate(firstPage, builder, 'Customer Reviews & Rating Cards');
  await addTemplate(firstPage, builder, 'Interactive Support & Inquiries Form');
  await addTemplate(firstPage, builder, 'Lead Capture & VIP Newsletter Strip');
  await expect(builder.getByTitle('Duplicate Section')).toHaveCount(5);
  await expect.poll(async () => {
    const response = await firstPage.request.get(`/admin/api/pages/${pageId}/draft`);
    const payload = await response.json() as {data: {document: {sections: Array<Record<string, unknown>>}}};
    return payload.data.document.sections.length;
  }).toBe(5);

  await builder.getByTitle('Export / Import JSON Layout').click();
  const jsonModal = firstPage.getByRole('heading', {name: 'Page Layout JSON Schema'}).locator('..');
  await jsonModal.locator('textarea').fill(JSON.stringify(documentFor(values), null, 2));
  await jsonModal.getByRole('button', {name: 'Apply / Import Layout'}).click();
  await expect(firstPage.getByText('Layout Imported', {exact: true})).toBeVisible();
  await expect(firstPage.locator('#section-task14-' + values.token + '-hero')).toBeVisible();
  await expect.poll(async () => {
    const response = await firstPage.request.get(`/admin/api/pages/${pageId}/draft`);
    const payload = await response.json() as {data: {document: {sections: Array<Record<string, unknown>>}}};
    const hero = payload.data.document.sections.find(section => section.id === `task14-${values.token}-hero`);
    return `${payload.data.document.sections.length}|${String(hero?.title ?? '')}`;
  }).toBe('5|Initial Acceptance Headline');

  const heroId = `task14-${values.token}-hero`;
  await builder.locator(`#section-${heroId}`).click();
  await builder.getByPlaceholder('e.g. THE DESIGN MANIFESTO').fill('FINAL ACCEPTANCE EYEBROW');
  await builder.getByPlaceholder('Section main heading').fill('Task 14 Published Acceptance Headline');
  await builder.getByPlaceholder('Descriptive copy or narrative block...').fill('Task 14 exact saved description from the Admin Inspector.');
  await builder.getByPlaceholder('https://images.unsplash.com/... or upload').fill('https://images.unsplash.com/photo-1511379938547-c1f69419868d?w=1600&auto=format&fit=crop&q=80');
  await builder.getByPlaceholder('e.g. Shop Now').fill('Shop Acceptance Product');
  await builder.getByPlaceholder('/collections/...').fill(`/products/task14-${values.token}-product`);
  await builder.getByPlaceholder('e.g. Learn More').fill('Read Acceptance Notes');
  await builder.getByPlaceholder('/pages/about').fill('#task14-details');
  await builder.getByRole('button', {name: 'Right', exact: true}).click();
  await builder.getByRole('button', {name: /^spacious$/i}).click();
  await builder.getByText('Background Canvas Style', {exact: true}).locator('..').locator('select').selectOption('bg-neutral-950');
  await expect.poll(async () => {
    const response = await firstPage.request.get(`/admin/api/pages/${pageId}/draft`);
    const payload = await response.json() as {data: {document: {sections: Array<Record<string, unknown>>}}};
    const hero = payload.data.document.sections.find(section => section.id === heroId) as {title?: string; backgroundColor?: string} | undefined;
    return `${hero?.title ?? ''}|${hero?.backgroundColor ?? ''}`;
  }).toBe('Task 14 Published Acceptance Headline|bg-neutral-950');
  const themeSaveResponse = firstPage.waitForResponse(response => (
    response.url().endsWith(`/admin/api/pages/${pageId}/draft`) && response.request().method() === 'PATCH'
  ));
  await builder.getByRole('button', {name: /Midnight Luxury \(Gold & Black\)/}).click();
  expect((await themeSaveResponse).status()).toBe(200);
  await expect.poll(async () => {
    const response = await firstPage.request.get(`/admin/api/pages/${pageId}/draft`);
    const payload = await response.json() as {data: {document: {sections: Array<Record<string, unknown>>}}};
    const hero = payload.data.document.sections.find(section => section.id === heroId) as {customColors?: {eyebrowColor?: string}} | undefined;
    return hero?.customColors?.eyebrowColor ?? '';
  }).toBe('#fbbf24');

  const collectionId = `task14-${values.token}-collection`;
  await builder.locator(`#section-${collectionId}`).click();
  await builder.getByPlaceholder('e.g. Flagship Headphones').first().fill('Acceptance Category Edited');
  const firstTab = builder.getByText('Tab #1', {exact: true}).locator('xpath=../../..');
  await firstTab.getByRole('button', {name: '6', exact: true}).click();

  const testimonialsId = `task14-${values.token}-testimonials`;
  await builder.locator(`#section-${testimonialsId}`).click();
  await builder.getByPlaceholder('Title / Name').first().fill('Persisted nested acceptance reviewer');
  await builder.getByPlaceholder('Description copy or quote...').first().fill('Persisted repeatable content from the live Inspector.');
  await builder.getByRole('button', {name: 'Add Card'}).click();
  await expect(builder.getByText('Card #3', {exact: true})).toBeVisible();

  await builder.getByRole('button', {name: 'Pages'}).click();
  await expect(firstPage.locator('#page-admin-directory')).toBeVisible();
  await expect.poll(async () => {
    const response = await firstPage.request.get(`/admin/api/pages/${pageId}/draft`);
    if (!response.ok()) return '';
    const payload = await response.json() as {data: {document: {sections: Array<Record<string, unknown>>}}};
    return String(payload.data.document.sections.find(section => section.id === heroId)?.title ?? '');
  }).toBe('Task 14 Published Acceptance Headline');
  await firstContext.close();

  const persistedContext = await browser.newContext({
    baseURL: 'http://127.0.0.1:8500',
    viewport: {width: 1600, height: 1000},
  });
  registerDiagnostics(persistedContext, consoleErrors, pageErrors);
  await stubExternalImages(persistedContext);
  const editorPage = await persistedContext.newPage();
  await login(editorPage, values.adminEmail, values.adminPassword);
  await editorPage.getByPlaceholder('Search by page title or slug...').fill('Task 14 Acceptance Page');
  const card = editorPage.locator('#page-admin-directory div.rounded-3xl.bg-white').filter({hasText: values.slug});
  await expect(card).toHaveCount(1);
  await card.getByRole('button', {name: 'Edit Layout'}).click();
  const persistedBuilder = editorPage.locator('#page-visual-builder');
  await expect(persistedBuilder.locator(`#section-${heroId}`)).toContainText('Task 14 Published Acceptance Headline');
  await expect(persistedBuilder.locator(`#section-${collectionId}`)).toContainText('Task 14 Acceptance Product');

  const persistedDraft = await editorPage.request.get(`/admin/api/pages/${pageId}/draft`);
  expect(persistedDraft.status()).toBe(200);
  const persistedPayload = await persistedDraft.json() as {data: {document: ReturnType<typeof documentFor>}};
  const persistedHero = persistedPayload.data.document.sections.find(section => section.id === heroId) as Record<string, unknown>;
  const persistedCollection = persistedPayload.data.document.sections.find(section => section.id === collectionId) as {tabs: Array<Record<string, unknown>>};
  const persistedTestimonials = persistedPayload.data.document.sections.find(section => section.id === testimonialsId) as {items: Array<Record<string, unknown>>};
  expect(persistedHero).toMatchObject({
    subtitle: 'FINAL ACCEPTANCE EYEBROW',
    content: 'Task 14 exact saved description from the Admin Inspector.',
    buttonText: 'Shop Acceptance Product',
    buttonLink: `/products/task14-${values.token}-product`,
    secondaryButtonText: 'Read Acceptance Notes',
    secondaryButtonLink: '#task14-details',
    textAlignment: 'right',
    paddingY: 'spacious',
    backgroundColor: 'bg-neutral-950',
    customColors: {
      eyebrowColor: '#fbbf24',
      primaryBtnBg: '#f59e0b',
      secondaryBtnText: '#fbbf24',
    },
  });
  expect(persistedCollection.tabs[0]).toMatchObject({
    label: 'Acceptance Category Edited',
    sourceType: 'category',
    sourceId: values.categoryId,
    productLimit: 6,
  });
  expect(persistedCollection.tabs[1]).toMatchObject({
    sourceType: 'collection',
    sourceId: values.collectionId,
  });
  expect(persistedTestimonials.items).toHaveLength(3);
  expect(persistedTestimonials.items[0].title).toBe('Persisted nested acceptance reviewer');

  await persistedBuilder.getByTitle('Desktop Full Screen').click();
  await persistedBuilder.locator(`#section-${heroId}`).scrollIntoViewIfNeeded();
  const adminOverflow = await overflow(editorPage);
  expect(adminOverflow.scrollWidth).toBeLessThanOrEqual(adminOverflow.clientWidth + 1);
  await editorPage.screenshot({path: resolve(evidenceDirectory, 'admin-canvas-desktop.png'), fullPage: true});
  await persistedBuilder.getByTitle('Mobile 390px').click();
  const canvasContact = persistedBuilder.locator(`#section-task14-${values.token}-contact`);
  await canvasContact.scrollIntoViewIfNeeded();
  const sourceClipping = await canvasContact.evaluate(element => ({clientWidth: element.clientWidth, scrollWidth: element.scrollWidth}));
  expect(sourceClipping.scrollWidth).toBeGreaterThan(sourceClipping.clientWidth);
  await editorPage.screenshot({path: resolve(evidenceDirectory, 'admin-canvas-mobile-stage-source-clipping.png'), fullPage: true});
  await persistedBuilder.getByTitle('Desktop Full Screen').click();

  await persistedBuilder.getByRole('button', {name: 'Preview', exact: true}).click();
  const previewFrame = editorPage.frameLocator('iframe[title^="Saved preview of"]');
  const previewIframe = editorPage.locator('iframe[title^="Saved preview of"]');
  const previewModal = editorPage.locator('div.fixed.inset-0.z-50');
  await expect(previewFrame.locator('#cms-page-root')).toBeVisible();
  const previewRuntime = await runtimePayload(previewFrame);
  expect(previewRuntime.page.stage).toBe('draft');
  expect(Object.values(previewRuntime.forms.contact).every(contract => contract.audience === 'admin-preview')).toBe(true);
  expect(Object.values(previewRuntime.forms.newsletter).every(contract => contract.audience === 'admin-preview')).toBe(true);
  await expect(previewFrame.locator(`#section-${heroId}`)).toContainText('Task 14 Published Acceptance Headline');
  await expect(previewFrame.locator(`#section-${collectionId}`)).toContainText('Task 14 Acceptance Product');
  const previewDesktopOverflow = await previewFrame.locator('html').evaluate(element => ({clientWidth: element.clientWidth, scrollWidth: element.scrollWidth}));
  expect(previewDesktopOverflow.scrollWidth).toBeLessThanOrEqual(previewDesktopOverflow.clientWidth + 1);
  await editorPage.screenshot({path: resolve(evidenceDirectory, 'preview-desktop.png'), fullPage: true});
  await previewModal.getByTitle('Mobile 390px').click();
  await expect.poll(async () => (await previewIframe.boundingBox())?.width ?? 0).toBeLessThanOrEqual(390);
  const previewMobileOverflow = await previewFrame.locator('html').evaluate(element => ({clientWidth: element.clientWidth, scrollWidth: element.scrollWidth}));
  expect(previewMobileOverflow.scrollWidth).toBeLessThanOrEqual(previewMobileOverflow.clientWidth + 1);
  await editorPage.screenshot({path: resolve(evidenceDirectory, 'preview-mobile.png'), fullPage: true});
  await previewFrame.locator('body').evaluate(element => { element.tabIndex = -1; element.focus(); });
  await editorPage.keyboard.press('Escape');
  await expect(editorPage.getByText('Live Customer View')).toHaveCount(0);

  const statusSelect = persistedBuilder.getByRole('combobox').first();
  await persistedBuilder.locator(`#section-${testimonialsId}`).click();
  const hideUnsupportedSectionResponse = editorPage.waitForResponse(response => (
    response.url().endsWith(`/admin/api/pages/${pageId}/draft`) && response.request().method() === 'PATCH'
  ));
  await persistedBuilder.getByTitle('Hide Section').click();
  expect((await hideUnsupportedSectionResponse).status()).toBe(200);

  const supportedPublishResponse = editorPage.waitForResponse(response => (
    response.url().endsWith(`/admin/api/pages/${pageId}/publish`) && response.request().method() === 'POST'
  ));
  await statusSelect.selectOption('published');
  expect((await supportedPublishResponse).status()).toBe(200);

  const canaryPage = await persistedContext.newPage();
  await canaryPage.goto(`/admin/cms-canary/pages/${pageId}`);
  await expect(canaryPage.locator('#cms-page-root')).toBeVisible();
  const canaryRuntime = await runtimePayload(canaryPage);
  expect(canaryRuntime.page.stage).toBe('published');
  expect(Object.values(canaryRuntime.forms.contact).every(contract => contract.audience === 'protected-canary')).toBe(true);
  expect(Object.values(canaryRuntime.forms.newsletter).every(contract => contract.audience === 'protected-canary')).toBe(true);
  await expect(canaryPage.locator(`#section-${heroId}`)).toContainText('Task 14 Published Acceptance Headline');

  await editorPage.bringToFront();
  await persistedBuilder.locator(`#section-${heroId}`).click();
  await persistedBuilder.getByPlaceholder('Section main heading').fill('Task 14 Draft Only Revision');
  await expect.poll(async () => {
    const response = await editorPage.request.get(`/admin/api/pages/${pageId}/draft`);
    const payload = await response.json() as {data: {document: {sections: Array<Record<string, unknown>>}}};
    return String(payload.data.document.sections.find(section => section.id === heroId)?.title ?? '');
  }).toBe('Task 14 Draft Only Revision');
  await canaryPage.reload();
  await expect(canaryPage.locator(`#section-${heroId}`)).toContainText('Task 14 Published Acceptance Headline');
  await expect(canaryPage.locator('body')).not.toContainText('Task 14 Draft Only Revision');

  await editorPage.bringToFront();
  const publishChangesResponse = editorPage.waitForResponse(response => (
    response.url().endsWith(`/admin/api/pages/${pageId}/publish`) && response.request().method() === 'POST'
  ));
  await persistedBuilder.getByRole('button', {name: 'Publish Changes'}).click();
  expect((await publishChangesResponse).status()).toBe(200);
  await canaryPage.reload();
  await expect(canaryPage.locator(`#section-${heroId}`)).toContainText('Task 14 Draft Only Revision');

  const canaryBootstrap = await canaryPage.locator('#cms-page-runtime').textContent() ?? '';
  expect(canaryBootstrap).not.toContain(values.couponCode);
  await canaryPage.locator(`#section-${collectionId}`).getByRole('button', {name: 'Acceptance Collection'}).click();
  await expect(canaryPage.locator(`#section-${collectionId}`)).toContainText('Task 14 Acceptance Product');
  const canaryDesktopOverflow = await overflow(canaryPage);
  expect(canaryDesktopOverflow.scrollWidth).toBeLessThanOrEqual(canaryDesktopOverflow.clientWidth + 1);
  await canaryPage.screenshot({path: resolve(evidenceDirectory, 'canary-desktop.png'), fullPage: true});
  await canaryPage.setViewportSize({width: 390, height: 844});
  const canaryMobileOverflow = await overflow(canaryPage);
  expect(canaryMobileOverflow.scrollWidth).toBeLessThanOrEqual(canaryMobileOverflow.clientWidth + 1);
  await canaryPage.screenshot({path: resolve(evidenceDirectory, 'canary-mobile.png'), fullPage: true});

  await editorPage.bringToFront();
  await persistedBuilder.getByRole('button', {name: 'Preview', exact: true}).click();
  const formsPreviewFrame = editorPage.frameLocator('iframe[title^="Saved preview of"]');
  await expect(formsPreviewFrame.locator('#cms-page-root')).toBeVisible();
  expect((await runtimePayload(formsPreviewFrame)).page.stage).toBe('draft');
  const formsPreviewBootstrap = await formsPreviewFrame.locator('#cms-page-runtime').textContent() ?? '';
  expect(formsPreviewBootstrap).not.toContain(values.couponCode);

  const contact = formsPreviewFrame.locator(`#section-task14-${values.token}-contact`);
  await contact.getByPlaceholder('e.g. Søren Lindqvist').fill('Task Fourteen Acceptance');
  await contact.getByPlaceholder('soren@example.com').fill(values.contactEmail);
  await contact.getByPlaceholder('Acceptance subject').fill('Pre-cutover lifecycle');
  await contact.getByRole('combobox').selectOption({label: 'Acceptance Enquiry'});
  await contact.getByPlaceholder('Acceptance message').fill('This record proves the live protected draft form contract before official cutover.');
  const contactResponsePromise = editorPage.waitForResponse(response => (
    response.url().includes('/cms/forms/contact/') && response.request().method() === 'POST'
  ));
  await contact.getByRole('button', {name: 'Send Acceptance Inquiry'}).click();
  const contactResponse = await contactResponsePromise;
  const contactResponseBody = await contactResponse.text();
  expect(contactResponse.status(), contactResponseBody).toBe(201);
  await expect(contact.getByText('Inquiry Received', {exact: true})).toBeVisible();

  const newsletter = formsPreviewFrame.locator(`#section-task14-${values.token}-newsletter`);
  await newsletter.getByPlaceholder('Enter your VIP email address...').fill(values.newsletterEmail);
  const newsletterResponsePromise = editorPage.waitForResponse(response => (
    response.url().includes('/cms/forms/newsletter/') && response.request().method() === 'POST'
  ));
  await newsletter.getByRole('button', {name: 'Claim 14% Voucher'}).click();
  const newsletterResponse = await newsletterResponsePromise;
  const newsletterResponseBody = await newsletterResponse.text();
  expect(newsletterResponse.status(), newsletterResponseBody).toBe(201);
  await expect(newsletter.getByText('Task 14 subscriber voucher accepted!', {exact: true})).toBeVisible();
  await expect(newsletter.getByText('Voucher Code:', {exact: true})).toBeVisible();
  await expect(newsletter.getByText(values.couponCode, {exact: true})).toBeVisible();
  await editorPage.getByTitle('Close Preview (Esc)').click();
  await persistedBuilder.getByRole('button', {name: 'Preview', exact: true}).click();
  const duplicatePreviewFrame = editorPage.frameLocator('iframe[title^="Saved preview of"]');
  await expect(duplicatePreviewFrame.locator('#cms-page-root')).toBeVisible();
  const duplicateNewsletter = duplicatePreviewFrame.locator(`#section-task14-${values.token}-newsletter`);
  await duplicateNewsletter.getByPlaceholder('Enter your VIP email address...').fill(` ${values.newsletterEmail.toUpperCase()} `);
  const duplicateNewsletterResponsePromise = editorPage.waitForResponse(response => (
    response.url().includes('/cms/forms/newsletter/') && response.request().method() === 'POST'
  ));
  await duplicateNewsletter.getByRole('button', {name: 'Claim 14% Voucher'}).click();
  const duplicateNewsletterResponse = await duplicateNewsletterResponsePromise;
  expect(duplicateNewsletterResponse.status(), await duplicateNewsletterResponse.text()).toBe(200);
  await expect(duplicateNewsletter.getByText('Task 14 subscriber voucher accepted!', {exact: true})).toBeVisible();
  await expect(duplicateNewsletter.getByText('Voucher Code:', {exact: true})).toHaveCount(0);
  await expect(duplicateNewsletter.getByText(values.couponCode, {exact: true})).toHaveCount(0);
  await editorPage.getByTitle('Close Preview (Esc)').click();

  const lifecycleEvidence = runHarness('inspect');
  writeEvidence('mysql-lifecycle-evidence.json', lifecycleEvidence);
  expect(lifecycleEvidence.owned?.ids.page).toBe(Number(pageId));
  expect(lifecycleEvidence.owned?.ids.legacySections).toEqual([]);
  expect(lifecycleEvidence.owned?.page?.status).toBe('published');
  expect(lifecycleEvidence.owned?.page?.isPublishedLegacyFlag).toBe(false);
  expect(lifecycleEvidence.owned?.page?.draftHash).toBe(lifecycleEvidence.owned?.page?.publishedHash);
  expect(lifecycleEvidence.owned?.page?.hero).toMatchObject({
    title: 'Task 14 Draft Only Revision',
    subtitle: 'FINAL ACCEPTANCE EYEBROW',
    textAlignment: 'right',
    paddingY: 'spacious',
    backgroundColor: 'bg-neutral-950',
  });
  expect(lifecycleEvidence.owned?.page?.tabs[0]).toMatchObject({sourceId: values.categoryId, productLimit: 6});
  expect(lifecycleEvidence.owned?.page?.tabs[1]).toMatchObject({sourceId: values.collectionId});
  expect(lifecycleEvidence.owned?.page?.testimonialItemCount).toBe(3);
  expect(lifecycleEvidence.owned?.history.contactCount).toBe(1);
  expect(lifecycleEvidence.owned?.history.newsletterCount).toBe(1);
  expect(lifecycleEvidence.owned?.history.contactStages).toEqual(['draft']);
  expect(lifecycleEvidence.owned?.history.newsletterStages).toEqual(['draft']);

  await editorPage.bringToFront();
  const unpublishResponse = editorPage.waitForResponse(response => (
    response.url().endsWith(`/admin/api/pages/${pageId}/unpublish`) && response.request().method() === 'POST'
  ));
  await statusSelect.selectOption('draft');
  expect((await unpublishResponse).status()).toBe(200);
  expect((await persistedContext.request.get(`/admin/cms-canary/pages/${pageId}`)).status()).toBe(404);

  const archiveResponse = editorPage.waitForResponse(response => (
    response.url().endsWith(`/admin/api/pages/${pageId}/archive`) && response.request().method() === 'POST'
  ));
  await statusSelect.selectOption('archived');
  expect((await archiveResponse).status()).toBe(200);
  const archivedSummary = await editorPage.request.get('/admin/api/pages');
  const archivedPages = await archivedSummary.json() as {data: Array<{id: string; status: string}>};
  expect(archivedPages.data.find(item => item.id === pageId)?.status).toBe('archived');

  await persistedBuilder.getByRole('button', {name: 'Pages'}).click();
  await editorPage.getByPlaceholder('Search by page title or slug...').fill('Task 14 Acceptance Page');
  const archivedCard = editorPage.locator('#page-admin-directory div.rounded-3xl.bg-white').filter({hasText: values.slug});
  await expect(archivedCard).toHaveCount(1);
  const deleteResponse = editorPage.waitForResponse(response => (
    response.url().endsWith(`/admin/api/pages/${pageId}`) && response.request().method() === 'DELETE'
  ));
  await archivedCard.getByTitle('Delete Page').click();
  expect((await deleteResponse).status()).toBe(204);
  await expect(archivedCard).toHaveCount(0);
  expect((await persistedContext.request.get(`/admin/cms-canary/pages/${pageId}`)).status()).toBe(404);
  expect((await persistedContext.request.get(`/pages/${values.slug}`)).status()).toBe(404);

  const postDeleteEvidence = runHarness('inspect');
  writeEvidence('post-delete-history-evidence.json', postDeleteEvidence);
  expect(postDeleteEvidence.owned?.ids.page).toBeNull();
  expect(postDeleteEvidence.owned?.history.contactCount).toBe(1);
  expect(postDeleteEvidence.owned?.history.newsletterCount).toBe(1);
  expect(postDeleteEvidence.owned?.history.contactPageIds).toEqual([null]);
  expect(postDeleteEvidence.owned?.history.newsletterPageIds).toEqual([null]);

  const cleanupEvidence = runHarness('cleanup');
  writeEvidence('cleanup-evidence.json', cleanupEvidence);
  expect(Object.values(cleanupEvidence.ownedRemaining ?? {}).every(count => count === 0)).toBe(true);
  expect(cleanupEvidence.expectedBaselineHash).toBe(values.baselineDigest);
  expect(cleanupEvidence.after?.hash).toBe(values.baselineDigest);
  expect(cleanupEvidence.baselineMatches).toBe(true);
  const setupEvidence = JSON.parse(readFileSync(resolve(evidenceDirectory, 'setup-evidence.json'), 'utf8')) as HarnessEvidence;
  expect(cleanupEvidence.after?.counts).toEqual(setupEvidence.before?.counts);
  expect(cleanupEvidence.after?.rowDigests).toEqual(setupEvidence.before?.rowDigests);
  expect(consoleErrors).toEqual([]);
  expect(pageErrors).toEqual([]);

  writeEvidence('browser-diagnostics.json', {
    browserResult: 'passed',
    baseUrl: 'http://127.0.0.1:8500',
    pageId,
    consoleErrors,
    pageErrors,
    overflow: {
      adminActualViewport: adminOverflow,
      canvasContactMobileStageSourceClipping: sourceClipping,
      previewDesktop: previewDesktopOverflow,
      previewMobile: previewMobileOverflow,
      canaryDesktop: canaryDesktopOverflow,
      canaryMobile: canaryMobileOverflow,
    },
  });
  await persistedContext.close();
});
