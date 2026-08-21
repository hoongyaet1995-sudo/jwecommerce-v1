import { expect, test } from '@playwright/test';

const bootstrap = {
  user: { name: 'Julian Admin', email: 'julian@example.test', avatar: null },
  csrfToken: 'csrf-token',
  logoutUrl: '/logout',
  pagesUrl: '/admin/api/pages',
  mediaAssetsUrl: '/admin/api/media-assets',
};

const pageFixture = (
  status: 'draft' | 'published' | 'archived' = 'draft',
  hasUnpublishedChanges = status !== 'published',
) => ({
  id: 'page-a',
  title: 'Original Page',
  slug: 'original-page',
  description: 'Original description',
  status,
  template: 'landing' as const,
  heroImage: null,
  seoTitle: '',
  seoDescription: '',
  createdAt: '2026-08-20',
  updatedAt: '2026-08-21',
  lockVersion: 1,
  hasPublishedSnapshot: status !== 'draft',
  hasUnpublishedChanges,
  sections: [{
    id: 'page-a-hero',
    type: 'hero_banner' as const,
    title: 'Original headline',
    subtitle: 'Original eyebrow',
    content: 'Original content',
    mediaUrl: 'https://cdn.example.org/original.webp',
    isVisible: true,
  }],
});

const draftPayload = (page: ReturnType<typeof pageFixture>) => ({
  pageId: page.id,
  status: page.status,
  schemaVersion: 1,
  lockVersion: page.lockVersion,
  contentHash: `hash-${page.lockVersion}`,
  hasPublishedSnapshot: page.hasPublishedSnapshot,
  hasUnpublishedChanges: page.hasUnpublishedChanges,
  document: {
    title: page.title,
    slug: page.slug,
    description: page.description,
    template: page.template,
    heroImage: page.heroImage,
    seoTitle: page.seoTitle,
    seoDescription: page.seoDescription,
    sections: page.sections,
  },
  updatedAt: '2026-08-21T00:00:00.000000Z',
});

test('rapid title and Inspector edits render immediately and debounce to one latest full draft save', async ({ page }) => {
  let current = pageFixture();
  const patches: Array<Record<string, any>> = [];

  await page.addInitScript(value => { window.__JW_ADMIN__ = value; }, bootstrap);
  await page.route('**/admin/api/pages**', async route => {
    const request = route.request();
    const url = new URL(request.url());
    if (request.method() === 'GET' && url.pathname === '/admin/api/pages') {
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [current] }) });
    }
    if (request.method() === 'GET' && url.pathname === '/admin/api/pages/page-a/draft') {
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: draftPayload(current) }) });
    }
    if (request.method() === 'PATCH' && url.pathname === '/admin/api/pages/page-a/draft') {
      const envelope = request.postDataJSON() as Record<string, any>;
      patches.push(structuredClone(envelope));
      current = {
        ...current,
        ...envelope.document,
        lockVersion: current.lockVersion + 1,
        hasUnpublishedChanges: true,
      };
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: draftPayload(current) }) });
    }
    return route.abort();
  });

  await page.goto('/tests/fixtures/media-upload-stale-state.html');
  await page.getByRole('button', { name: 'Edit Layout' }).click();
  const title = page.locator('#page-visual-builder input[type="text"]').first();
  const sectionTitle = page.getByPlaceholder('Section main heading');
  await title.fill('A');
  await title.fill('AB');
  await title.fill('ABC');
  await sectionTitle.fill('Latest Inspector headline');

  await expect(title).toHaveValue('ABC');
  await expect(sectionTitle).toHaveValue('Latest Inspector headline');
  expect(patches).toHaveLength(0);
  await expect.poll(() => patches.length, { timeout: 2_000 }).toBe(1);
  expect(patches[0]).toMatchObject({
    lockVersion: 1,
    document: {
      title: 'ABC',
      sections: [{ title: 'Latest Inspector headline' }],
    },
  });
});

test('an in-flight save never overlaps and follows with only the newest queued full document', async ({ page }) => {
  let current = pageFixture();
  const patches: Array<Record<string, any>> = [];
  let activeRequests = 0;
  let maximumActiveRequests = 0;
  let releaseFirst!: () => void;
  let markFirstStarted!: () => void;
  const firstRelease = new Promise<void>(resolve => { releaseFirst = resolve; });
  const firstStarted = new Promise<void>(resolve => { markFirstStarted = resolve; });

  await page.addInitScript(value => { window.__JW_ADMIN__ = value; }, bootstrap);
  await page.route('**/admin/api/pages**', async route => {
    const request = route.request();
    const url = new URL(request.url());
    if (request.method() === 'GET' && url.pathname === '/admin/api/pages') {
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [current] }) });
    }
    if (request.method() === 'GET' && url.pathname.endsWith('/draft')) {
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: draftPayload(current) }) });
    }
    if (request.method() === 'PATCH' && url.pathname.endsWith('/draft')) {
      activeRequests += 1;
      maximumActiveRequests = Math.max(maximumActiveRequests, activeRequests);
      const envelope = request.postDataJSON() as Record<string, any>;
      patches.push(structuredClone(envelope));
      if (patches.length === 1) {
        markFirstStarted();
        await firstRelease;
      }
      current = { ...current, ...envelope.document, lockVersion: current.lockVersion + 1, hasUnpublishedChanges: true };
      activeRequests -= 1;
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: draftPayload(current) }) });
    }
    return route.abort();
  });

  await page.goto('/tests/fixtures/media-upload-stale-state.html');
  await page.getByRole('button', { name: 'Edit Layout' }).click();
  const title = page.locator('#page-visual-builder input[type="text"]').first();
  await title.fill('First request');
  await firstStarted;
  await title.fill('Queued A');
  await title.fill('Queued latest');
  await page.waitForTimeout(700);
  expect(patches).toHaveLength(1);

  releaseFirst();
  await expect.poll(() => patches.length, { timeout: 2_000 }).toBe(2);
  expect(patches[1]).toMatchObject({ lockVersion: 2, document: { title: 'Queued latest' } });
  expect(maximumActiveRequests).toBe(1);
});

test('Preview waits for flush while conflict cancels back navigation and retains visible local input', async ({ page }) => {
  let current = pageFixture();
  let listGets = 0;
  let patchCount = 0;
  let releaseSave!: () => void;
  let markSaveStarted!: () => void;
  const saveRelease = new Promise<void>(resolve => { releaseSave = resolve; });
  const saveStarted = new Promise<void>(resolve => { markSaveStarted = resolve; });

  await page.addInitScript(value => { window.__JW_ADMIN__ = value; }, bootstrap);
  await page.route('**/admin/api/pages**', async route => {
    const request = route.request();
    const url = new URL(request.url());
    if (request.method() === 'GET' && url.pathname === '/admin/api/pages') {
      listGets += 1;
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [current] }) });
    }
    if (request.method() === 'GET' && url.pathname.endsWith('/draft')) {
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: draftPayload(current) }) });
    }
    if (request.method() === 'PATCH' && url.pathname.endsWith('/draft')) {
      patchCount += 1;
      markSaveStarted();
      await saveRelease;
      return route.fulfill({
        status: 409,
        contentType: 'application/json',
        body: JSON.stringify({
          message: 'The page draft was changed by another session.',
          code: 'cms_version_conflict',
          errors: { lockVersion: ['Reload or reconcile your local changes before saving again.'] },
          currentLockVersion: 9,
        }),
      });
    }
    return route.abort();
  });

  await page.goto('/tests/fixtures/media-upload-stale-state.html');
  await page.getByRole('button', { name: 'Edit Layout' }).click();
  const title = page.locator('#page-visual-builder input[type="text"]').first();
  await title.fill('Conflict-safe local title');
  await page.getByRole('button', { name: 'Preview', exact: true }).click();
  await saveStarted;
  await expect(page.getByText('Live Customer View')).toHaveCount(0);

  releaseSave();
  await expect(page.getByText('Live Customer View')).toHaveCount(0);
  await page.getByRole('button', { name: 'Pages' }).click();
  await expect(page.locator('#page-visual-builder')).toBeVisible();
  await expect(title).toHaveValue('Conflict-safe local title');
  expect(patchCount).toBe(1);
  expect(listGets).toBe(1);
});

test('published dirty state has one source-styled Publish Changes action and lifecycle uses the post-flush version', async ({ page }) => {
  let current = pageFixture('published', false);
  const lifecycle: Array<{ action: string; body: Record<string, any> | null }> = [];

  await page.addInitScript(value => { window.__JW_ADMIN__ = value; }, bootstrap);
  await page.route('**/admin/api/pages**', async route => {
    const request = route.request();
    const url = new URL(request.url());
    if (request.method() === 'GET' && url.pathname === '/admin/api/pages') {
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [current] }) });
    }
    if (request.method() === 'GET' && url.pathname.endsWith('/draft')) {
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: draftPayload(current) }) });
    }
    if (request.method() === 'PATCH' && url.pathname.endsWith('/draft')) {
      const envelope = request.postDataJSON() as Record<string, any>;
      current = { ...current, ...envelope.document, lockVersion: current.lockVersion + 1, hasUnpublishedChanges: true };
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: draftPayload(current) }) });
    }
    const action = url.pathname.split('/').at(-1) ?? '';
    if (request.method() === 'POST' && ['publish', 'unpublish', 'archive'].includes(action)) {
      const body = request.postData() ? request.postDataJSON() as Record<string, any> : null;
      lifecycle.push({ action, body });
      current = {
        ...current,
        status: action === 'unpublish' ? 'draft' : action === 'archive' ? 'archived' : 'published',
        hasPublishedSnapshot: true,
        hasUnpublishedChanges: action === 'publish' ? false : current.hasUnpublishedChanges,
      };
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: current }) });
    }
    return route.abort();
  });

  await page.goto('/tests/fixtures/media-upload-stale-state.html');
  await page.getByRole('button', { name: 'Edit Layout' }).click();
  await expect(page.getByRole('button', { name: 'Publish Changes' })).toHaveCount(0);
  await page.locator('#page-visual-builder input[type="text"]').first().fill('Unpublished local title');
  await expect(page.getByRole('button', { name: 'Publish Changes' })).toHaveCount(1);

  await page.getByRole('button', { name: 'Publish Changes' }).click();
  await expect.poll(() => lifecycle.length).toBe(1);
  expect(lifecycle[0]).toEqual({ action: 'publish', body: { lockVersion: 2 } });
  await expect(page.getByRole('button', { name: 'Publish Changes' })).toHaveCount(0);

  const status = page.locator('#page-visual-builder select').first();
  await status.selectOption('draft');
  await expect.poll(() => lifecycle.at(-1)?.action).toBe('unpublish');
  await status.selectOption('published');
  await expect.poll(() => lifecycle.at(-1)?.action).toBe('publish');
  expect(lifecycle.at(-1)?.body).toEqual({ lockVersion: 2 });
  await status.selectOption('archived');
  await expect.poll(() => lifecycle.at(-1)?.action).toBe('archive');
});
