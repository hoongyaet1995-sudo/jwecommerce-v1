import { expect, test } from '@playwright/test';

const managedUrl = '/storage/cms/media/7ea92b56-24d3-4f35-b80c-44cf0bd78292.webp';
const bootstrap = {
  user: { name: 'Julian Admin', email: 'julian@example.test', avatar: null },
  csrfToken: 'csrf-token',
  logoutUrl: '/logout',
  pagesUrl: '/admin/api/pages',
  mediaAssetsUrl: '/admin/api/media-assets',
};
const pageFixture = (id: string, title: string, sectionTitle: string) => ({
  id,
  title,
  slug: id,
  description: `${title} description`,
  status: 'draft' as const,
  template: 'landing',
  createdAt: '2026-08-21',
  updatedAt: '2026-08-21',
  lockVersion: 1,
  hasPublishedSnapshot: false,
  hasUnpublishedChanges: true,
  sections: [{
    id: `${id}-hero`,
    type: 'hero_banner',
    title: sectionTitle,
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
    sections: page.sections,
  },
  updatedAt: '2026-08-21T00:00:00.000000Z',
});

test('deferred upload applies to its original page and merges concurrent section edits', async ({ page }) => {
  const pages = [
    pageFixture('page-a', 'Original Page', 'Original headline'),
    pageFixture('page-b', 'Newly Selected Page', 'Second headline'),
  ];
  const patches: Array<{ pageId: string; lockVersion: number; document: Record<string, any> }> = [];
  let releaseUpload!: () => void;
  let markUploadStarted!: () => void;
  const uploadRelease = new Promise<void>((resolve) => { releaseUpload = resolve; });
  const uploadStarted = new Promise<void>((resolve) => { markUploadStarted = resolve; });

  await page.addInitScript((value) => { window.__JW_ADMIN__ = value; }, bootstrap);
  await page.route('**/admin/api/media-assets', async (route) => {
    markUploadStarted();
    await uploadRelease;
    await route.fulfill({
      status: 201,
      contentType: 'application/json',
      body: JSON.stringify({ data: { id: 'asset-id', url: managedUrl, mimeType: 'image/webp', size: 12 } }),
    });
  });
  await page.route('**/admin/api/pages**', async (route) => {
    const request = route.request();
    const path = new URL(request.url()).pathname;
    if (request.method() === 'GET' && path === '/admin/api/pages') {
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: pages }) });
    }
    const match = path.match(/^\/admin\/api\/pages\/([^/]+)\/draft$/);
    const pageId = match?.[1];
    const index = pages.findIndex(item => item.id === pageId);
    if (request.method() === 'GET' && index >= 0) {
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: draftPayload(pages[index]) }) });
    }
    if (request.method() === 'PATCH' && index >= 0 && pageId) {
      const envelope = request.postDataJSON() as { lockVersion: number; document: Record<string, any> };
      patches.push({ pageId, ...structuredClone(envelope) });
      pages[index] = {
        ...pages[index],
        ...envelope.document,
        lockVersion: pages[index].lockVersion + 1,
        hasUnpublishedChanges: true,
      } as typeof pages[number];
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: draftPayload(pages[index]) }) });
    }
    return route.abort();
  });

  await page.goto('/tests/fixtures/media-upload-stale-state.html');
  await expect(page.getByText('Original Page', { exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'Edit Layout' }).first().click();
  await page.locator('input[type="file"]').first().setInputFiles({
    name: 'hero.webp',
    mimeType: 'image/webp',
    buffer: Buffer.from('test-only-upload'),
  });
  await uploadStarted;

  await page.getByPlaceholder('Section main heading').fill('Concurrent headline edit');
  await expect.poll(() => patches.some((patch) => patch.pageId === 'page-a'
    && patch.document.sections[0]?.title === 'Concurrent headline edit')).toBe(true);

  await page.getByRole('button', { name: 'Pages' }).click();
  await page.getByRole('button', { name: 'Edit Layout' }).nth(1).click();
  await expect(page.getByPlaceholder('Section main heading')).toHaveValue('Second headline');
  releaseUpload();

  await expect.poll(() => patches.some((patch) => patch.pageId === 'page-a'
    && patch.document.sections[0]?.mediaUrl === managedUrl)).toBe(true);
  const completedUpload = [...patches].reverse().find((patch) => patch.pageId === 'page-a'
    && patch.document.sections[0]?.mediaUrl === managedUrl);

  expect(completedUpload?.document.sections[0]?.title).toBe('Concurrent headline edit');
  expect(patches.some((patch) => patch.pageId === 'page-b'
    && patch.document.sections[0]?.mediaUrl === managedUrl)).toBe(false);
  await expect(page.getByPlaceholder('Section main heading')).toHaveValue('Second headline');
});

test('reopening an upload target reconciles its detached save before accepting another edit', async ({ page }) => {
  const pages = [
    pageFixture('page-a', 'Original Page', 'Original headline'),
    pageFixture('page-b', 'Newly Selected Page', 'Second headline'),
  ];
  const pageAPatches: Array<{ lockVersion: number; document: Record<string, any> }> = [];
  let activePageAPatches = 0;
  let maximumActivePageAPatches = 0;
  let releaseUpload!: () => void;
  let markUploadStarted!: () => void;
  let releaseDetachedPatch!: () => void;
  let markDetachedPatchStarted!: () => void;
  let markSecondPageAPatchStarted!: () => void;
  const uploadRelease = new Promise<void>(resolve => { releaseUpload = resolve; });
  const uploadStarted = new Promise<void>(resolve => { markUploadStarted = resolve; });
  const detachedPatchRelease = new Promise<void>(resolve => { releaseDetachedPatch = resolve; });
  const detachedPatchStarted = new Promise<void>(resolve => { markDetachedPatchStarted = resolve; });
  const secondPageAPatchStarted = new Promise<void>(resolve => { markSecondPageAPatchStarted = resolve; });

  await page.addInitScript(value => { window.__JW_ADMIN__ = value; }, bootstrap);
  await page.route('**/admin/api/media-assets', async route => {
    markUploadStarted();
    await uploadRelease;
    await route.fulfill({
      status: 201,
      contentType: 'application/json',
      body: JSON.stringify({ data: { id: 'asset-id', url: managedUrl, mimeType: 'image/webp', size: 12 } }),
    });
  });
  await page.route('**/admin/api/pages**', async route => {
    const request = route.request();
    const path = new URL(request.url()).pathname;
    if (request.method() === 'GET' && path === '/admin/api/pages') {
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: pages }) });
    }

    const match = path.match(/^\/admin\/api\/pages\/([^/]+)\/draft$/);
    const pageId = match?.[1];
    const index = pages.findIndex(item => item.id === pageId);
    if (request.method() === 'GET' && index >= 0) {
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: draftPayload(pages[index]) }) });
    }
    if (request.method() !== 'PATCH' || index < 0 || !pageId) return route.abort();

    const envelope = request.postDataJSON() as { lockVersion: number; document: Record<string, any> };
    if (pageId === 'page-a') {
      activePageAPatches += 1;
      maximumActivePageAPatches = Math.max(maximumActivePageAPatches, activePageAPatches);
      pageAPatches.push(structuredClone(envelope));
      if (pageAPatches.length === 1) {
        markDetachedPatchStarted();
        await detachedPatchRelease;
      } else {
        markSecondPageAPatchStarted();
      }
    }

    if (envelope.lockVersion !== pages[index].lockVersion) {
      if (pageId === 'page-a') activePageAPatches -= 1;
      return route.fulfill({
        status: 409,
        contentType: 'application/json',
        body: JSON.stringify({
          message: 'The page draft was changed by another session.',
          code: 'cms_version_conflict',
          errors: { lockVersion: ['Reload or reconcile your local changes before saving again.'] },
          currentLockVersion: pages[index].lockVersion,
        }),
      });
    }

    pages[index] = {
      ...pages[index],
      ...envelope.document,
      lockVersion: pages[index].lockVersion + 1,
      hasUnpublishedChanges: true,
    } as typeof pages[number];
    if (pageId === 'page-a') activePageAPatches -= 1;

    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: draftPayload(pages[index]) }),
    });
  });

  await page.goto('/tests/fixtures/media-upload-stale-state.html');
  await page.getByRole('button', { name: 'Edit Layout' }).first().click();
  await page.locator('input[type="file"]').first().setInputFiles({
    name: 'hero.webp',
    mimeType: 'image/webp',
    buffer: Buffer.from('test-only-upload'),
  });
  await uploadStarted;

  await page.getByRole('button', { name: 'Pages' }).click();
  await expect(page.locator('#page-admin-directory')).toBeVisible();
  await page.getByRole('button', { name: 'Edit Layout' }).nth(1).click();
  await expect(page.getByPlaceholder('Section main heading')).toHaveValue('Second headline');

  releaseUpload();
  await detachedPatchStarted;
  await page.getByRole('button', { name: 'Pages' }).click();
  await expect(page.locator('#page-admin-directory')).toBeVisible();
  await page.getByRole('button', { name: 'Edit Layout' }).first().click();

  const reopenedEdit = page.getByPlaceholder('Section main heading').fill('Reopened concurrent edit');
  await Promise.race([
    secondPageAPatchStarted,
    page.waitForTimeout(850),
  ]);
  releaseDetachedPatch();
  await reopenedEdit;

  await expect.poll(() => pageAPatches.length, { timeout: 2_000 }).toBe(2);
  await expect.poll(() => activePageAPatches).toBe(0);
  expect(maximumActivePageAPatches).toBe(1);
  expect(pageAPatches[1]).toMatchObject({
    lockVersion: 2,
    document: {
      sections: [{
        title: 'Reopened concurrent edit',
        mediaUrl: managedUrl,
      }],
    },
  });
  await expect(page.getByPlaceholder('Section main heading')).toHaveValue('Reopened concurrent edit');
});

test('page takeover queues an upload completed during a held stale draft fetch before exposing one controller', async ({ page }) => {
  const pages = [
    pageFixture('page-a', 'Original Page', 'Original headline'),
    pageFixture('page-b', 'Newly Selected Page', 'Second headline'),
  ];
  const pageAPatches: Array<{ lockVersion: number; document: Record<string, any> }> = [];
  let pageADraftGets = 0;
  let activePageAPatches = 0;
  let maximumActivePageAPatches = 0;
  let releaseUpload!: () => void;
  let markUploadStarted!: () => void;
  let releaseTakeoverGet!: () => void;
  let markTakeoverGetStarted!: () => void;
  let releaseFirstPageAPatch!: () => void;
  let markFirstPageAPatchStarted!: () => void;
  let markSecondPageAPatchStarted!: () => void;
  const uploadRelease = new Promise<void>(resolve => { releaseUpload = resolve; });
  const uploadStarted = new Promise<void>(resolve => { markUploadStarted = resolve; });
  const takeoverGetRelease = new Promise<void>(resolve => { releaseTakeoverGet = resolve; });
  const takeoverGetStarted = new Promise<void>(resolve => { markTakeoverGetStarted = resolve; });
  const firstPageAPatchRelease = new Promise<void>(resolve => { releaseFirstPageAPatch = resolve; });
  const firstPageAPatchStarted = new Promise<void>(resolve => { markFirstPageAPatchStarted = resolve; });
  const secondPageAPatchStarted = new Promise<void>(resolve => { markSecondPageAPatchStarted = resolve; });

  await page.addInitScript(value => { window.__JW_ADMIN__ = value; }, bootstrap);
  await page.route('**/admin/api/media-assets', async route => {
    markUploadStarted();
    await uploadRelease;
    await route.fulfill({
      status: 201,
      contentType: 'application/json',
      body: JSON.stringify({ data: { id: 'asset-id', url: managedUrl, mimeType: 'image/webp', size: 12 } }),
    });
  });
  await page.route('**/admin/api/pages**', async route => {
    const request = route.request();
    const path = new URL(request.url()).pathname;
    if (request.method() === 'GET' && path === '/admin/api/pages') {
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: pages }) });
    }

    const match = path.match(/^\/admin\/api\/pages\/([^/]+)\/draft$/);
    const pageId = match?.[1];
    const index = pages.findIndex(item => item.id === pageId);
    if (request.method() === 'GET' && index >= 0) {
      if (pageId === 'page-a') {
        pageADraftGets += 1;
        if (pageADraftGets === 2) {
          const stalePayload = draftPayload(pages[index]);
          markTakeoverGetStarted();
          await takeoverGetRelease;
          return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: stalePayload }) });
        }
      }
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: draftPayload(pages[index]) }) });
    }
    if (request.method() !== 'PATCH' || index < 0 || !pageId) return route.abort();

    const envelope = request.postDataJSON() as { lockVersion: number; document: Record<string, any> };
    if (pageId === 'page-a') {
      activePageAPatches += 1;
      maximumActivePageAPatches = Math.max(maximumActivePageAPatches, activePageAPatches);
      pageAPatches.push(structuredClone(envelope));
      if (pageAPatches.length === 1) {
        markFirstPageAPatchStarted();
        await firstPageAPatchRelease;
      } else {
        markSecondPageAPatchStarted();
      }
    }

    if (envelope.lockVersion !== pages[index].lockVersion) {
      if (pageId === 'page-a') activePageAPatches -= 1;
      return route.fulfill({
        status: 409,
        contentType: 'application/json',
        body: JSON.stringify({
          message: 'The page draft was changed by another session.',
          code: 'cms_version_conflict',
          errors: { lockVersion: ['Reload or reconcile your local changes before saving again.'] },
          currentLockVersion: pages[index].lockVersion,
        }),
      });
    }

    pages[index] = {
      ...pages[index],
      ...envelope.document,
      lockVersion: pages[index].lockVersion + 1,
      hasUnpublishedChanges: true,
    } as typeof pages[number];
    if (pageId === 'page-a') activePageAPatches -= 1;
    return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: draftPayload(pages[index]) }) });
  });

  await page.goto('/tests/fixtures/media-upload-stale-state.html');
  await page.getByRole('button', { name: 'Edit Layout' }).first().click();
  await page.locator('input[type="file"]').first().setInputFiles({
    name: 'hero.webp',
    mimeType: 'image/webp',
    buffer: Buffer.from('test-only-upload'),
  });
  await uploadStarted;

  await page.getByRole('button', { name: 'Pages' }).click();
  await page.getByRole('button', { name: 'Edit Layout' }).nth(1).click();
  await expect(page.getByPlaceholder('Section main heading')).toHaveValue('Second headline');
  await page.getByRole('button', { name: 'Pages' }).click();
  await page.getByRole('button', { name: 'Edit Layout' }).first().click();
  await takeoverGetStarted;

  releaseUpload();
  await expect(page.locator(`img[src="${managedUrl}"]`)).toBeVisible();
  releaseTakeoverGet();
  await firstPageAPatchStarted;

  const reopenedEdit = page.getByPlaceholder('Section main heading').fill('Takeover-safe edit');
  await Promise.race([
    secondPageAPatchStarted,
    page.waitForTimeout(850),
  ]);
  const maximumBeforeFirstRelease = maximumActivePageAPatches;
  releaseFirstPageAPatch();
  await reopenedEdit;

  await expect.poll(() => pageAPatches.length, { timeout: 3_000 }).toBe(2);
  await expect.poll(() => activePageAPatches).toBe(0);
  expect(maximumBeforeFirstRelease).toBe(1);
  expect(maximumActivePageAPatches).toBe(1);
  expect(pageAPatches.map(patch => patch.lockVersion)).toEqual([1, 2]);
  expect(pageAPatches[1].document.sections[0]).toMatchObject({
    title: 'Takeover-safe edit',
    mediaUrl: managedUrl,
  });
  await expect(page.getByPlaceholder('Section main heading')).toHaveValue('Takeover-safe edit');
});

test('a stale cross-page takeover cannot replace or dispose the latest dirty page controller', async ({ page }) => {
  const pages = [
    pageFixture('page-a', 'Original Page', 'Original headline'),
    pageFixture('page-b', 'Newly Selected Page', 'Second headline'),
  ];
  const pageBPatches: Array<{ lockVersion: number; document: Record<string, any> }> = [];
  let pageAPatchCount = 0;
  let releasePageAGet!: () => void;
  let markPageAGetStarted!: () => void;
  let markPageAGetReturned!: () => void;
  let releaseFirstPageBPatch!: () => void;
  let markFirstPageBPatchStarted!: () => void;
  const pageAGetRelease = new Promise<void>(resolve => { releasePageAGet = resolve; });
  const pageAGetStarted = new Promise<void>(resolve => { markPageAGetStarted = resolve; });
  const pageAGetReturned = new Promise<void>(resolve => { markPageAGetReturned = resolve; });
  const firstPageBPatchRelease = new Promise<void>(resolve => { releaseFirstPageBPatch = resolve; });
  const firstPageBPatchStarted = new Promise<void>(resolve => { markFirstPageBPatchStarted = resolve; });

  await page.addInitScript(value => { window.__JW_ADMIN__ = value; }, bootstrap);
  await page.route('**/admin/api/pages**', async route => {
    const request = route.request();
    const path = new URL(request.url()).pathname;
    if (request.method() === 'GET' && path === '/admin/api/pages') {
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: pages }) });
    }

    const match = path.match(/^\/admin\/api\/pages\/([^/]+)\/draft$/);
    const pageId = match?.[1];
    const index = pages.findIndex(item => item.id === pageId);
    if (request.method() === 'GET' && index >= 0) {
      const payload = draftPayload(pages[index]);
      if (pageId === 'page-a') {
        markPageAGetStarted();
        await pageAGetRelease;
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: payload }) });
        markPageAGetReturned();
        return;
      }
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: payload }) });
    }
    if (request.method() !== 'PATCH' || index < 0 || !pageId) return route.abort();

    const envelope = request.postDataJSON() as { lockVersion: number; document: Record<string, any> };
    if (pageId === 'page-a') pageAPatchCount += 1;
    if (pageId === 'page-b') {
      pageBPatches.push(structuredClone(envelope));
      if (pageBPatches.length === 1) {
        markFirstPageBPatchStarted();
        await Promise.race([firstPageBPatchRelease, new Promise(resolve => setTimeout(resolve, 8_000))]);
      }
    }

    if (envelope.lockVersion !== pages[index].lockVersion) {
      return route.fulfill({
        status: 409,
        contentType: 'application/json',
        body: JSON.stringify({
          message: 'The page draft was changed by another session.',
          code: 'cms_version_conflict',
          errors: { lockVersion: ['Reload or reconcile your local changes before saving again.'] },
          currentLockVersion: pages[index].lockVersion,
        }),
      });
    }

    pages[index] = {
      ...pages[index],
      ...envelope.document,
      lockVersion: pages[index].lockVersion + 1,
      hasUnpublishedChanges: true,
    } as typeof pages[number];
    return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: draftPayload(pages[index]) }) });
  });

  await page.goto('/tests/fixtures/media-upload-stale-state.html');
  await page.getByRole('button', { name: 'Edit Layout' }).first().click();
  await pageAGetStarted;

  await page.getByRole('button', { name: 'Edit Layout' }).nth(1).click();
  const heading = page.getByPlaceholder('Section main heading');
  await expect(heading).toHaveValue('Second headline');
  await heading.fill('B dirty edit');
  await firstPageBPatchStarted;

  releasePageAGet();
  await pageAGetReturned;
  await expect(heading).toHaveValue('B dirty edit');
  await heading.fill('B queued while saving');
  releaseFirstPageBPatch();

  await expect.poll(() => pageBPatches.length, { timeout: 3_000 }).toBe(2);
  expect(pageBPatches.map(patch => patch.lockVersion)).toEqual([1, 2]);
  expect(pageBPatches[1].document.sections[0].title).toBe('B queued while saving');
  expect(pageAPatchCount).toBe(0);
  await expect(heading).toHaveValue('B queued while saving');
});

test('an A to B to A request promotes and joins the original A takeover without duplicate ownership', async ({ page }) => {
  const pages = [
    pageFixture('page-a', 'Original Page', 'Original headline'),
    pageFixture('page-b', 'Newly Selected Page', 'Second headline'),
  ];
  const pageAPatches: Array<{ lockVersion: number; document: Record<string, any> }> = [];
  let pageAGetCount = 0;
  let pageBGetCount = 0;
  let pageBPatchCount = 0;
  let releasePageAGet!: () => void;
  let releasePageBGet!: () => void;
  let markPageAGetStarted!: () => void;
  let markPageBGetStarted!: () => void;
  let markPageAGetReturned!: () => void;
  let markPageBGetReturned!: () => void;
  let releaseFirstPageAPatch!: () => void;
  let markFirstPageAPatchStarted!: () => void;
  const pageAGetRelease = new Promise<void>(resolve => { releasePageAGet = resolve; });
  const pageBGetRelease = new Promise<void>(resolve => { releasePageBGet = resolve; });
  const pageAGetStarted = new Promise<void>(resolve => { markPageAGetStarted = resolve; });
  const pageBGetStarted = new Promise<void>(resolve => { markPageBGetStarted = resolve; });
  const pageAGetReturned = new Promise<void>(resolve => { markPageAGetReturned = resolve; });
  const pageBGetReturned = new Promise<void>(resolve => { markPageBGetReturned = resolve; });
  const firstPageAPatchRelease = new Promise<void>(resolve => { releaseFirstPageAPatch = resolve; });
  const firstPageAPatchStarted = new Promise<void>(resolve => { markFirstPageAPatchStarted = resolve; });

  await page.addInitScript(value => { window.__JW_ADMIN__ = value; }, bootstrap);
  await page.route('**/admin/api/pages**', async route => {
    const request = route.request();
    const path = new URL(request.url()).pathname;
    if (request.method() === 'GET' && path === '/admin/api/pages') {
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: pages }) });
    }

    const match = path.match(/^\/admin\/api\/pages\/([^/]+)\/draft$/);
    const pageId = match?.[1];
    const index = pages.findIndex(item => item.id === pageId);
    if (request.method() === 'GET' && index >= 0) {
      const payload = draftPayload(pages[index]);
      if (pageId === 'page-a') {
        pageAGetCount += 1;
        markPageAGetStarted();
        await pageAGetRelease;
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: payload }) });
        markPageAGetReturned();
        return;
      }
      pageBGetCount += 1;
      markPageBGetStarted();
      await pageBGetRelease;
      await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: payload }) });
      markPageBGetReturned();
      return;
    }
    if (request.method() !== 'PATCH' || index < 0 || !pageId) return route.abort();

    const envelope = request.postDataJSON() as { lockVersion: number; document: Record<string, any> };
    if (pageId === 'page-b') pageBPatchCount += 1;
    if (pageId === 'page-a') {
      pageAPatches.push(structuredClone(envelope));
      if (pageAPatches.length === 1) {
        markFirstPageAPatchStarted();
        await Promise.race([firstPageAPatchRelease, new Promise(resolve => setTimeout(resolve, 8_000))]);
      }
    }

    if (envelope.lockVersion !== pages[index].lockVersion) {
      return route.fulfill({
        status: 409,
        contentType: 'application/json',
        body: JSON.stringify({
          message: 'The page draft was changed by another session.',
          code: 'cms_version_conflict',
          errors: { lockVersion: ['Reload or reconcile your local changes before saving again.'] },
          currentLockVersion: pages[index].lockVersion,
        }),
      });
    }

    pages[index] = {
      ...pages[index],
      ...envelope.document,
      lockVersion: pages[index].lockVersion + 1,
      hasUnpublishedChanges: true,
    } as typeof pages[number];
    return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: draftPayload(pages[index]) }) });
  });

  await page.goto('/tests/fixtures/media-upload-stale-state.html');
  await page.getByRole('button', { name: 'Edit Layout' }).first().click();
  await pageAGetStarted;
  await page.getByRole('button', { name: 'Edit Layout' }).nth(1).click();
  await pageBGetStarted;
  await page.getByRole('button', { name: 'Edit Layout' }).first().click();

  releasePageBGet();
  await pageBGetReturned;
  releasePageAGet();
  await pageAGetReturned;

  const heading = page.getByPlaceholder('Section main heading');
  await expect(heading).toHaveValue('Original headline');
  expect(pageAGetCount).toBe(1);
  expect(pageBGetCount).toBe(1);
  expect(pageBPatchCount).toBe(0);

  await heading.fill('A dirty edit');
  await firstPageAPatchStarted;
  await heading.fill('A queued while saving');
  releaseFirstPageAPatch();

  await expect.poll(() => pageAPatches.length, { timeout: 3_000 }).toBe(2);
  expect(pageAPatches.map(patch => patch.lockVersion)).toEqual([1, 2]);
  expect(pageAPatches[1].document.sections[0].title).toBe('A queued while saving');
  await expect(heading).toHaveValue('A queued while saving');
});

test('a detached upload conflict cancels reopening and preserves the local media state', async ({ page }) => {
  const pages = [
    pageFixture('page-a', 'Original Page', 'Original headline'),
    pageFixture('page-b', 'Newly Selected Page', 'Second headline'),
  ];
  let pageAPatchCount = 0;
  let releaseUpload!: () => void;
  let markUploadStarted!: () => void;
  let markConflictReturned!: () => void;
  const uploadRelease = new Promise<void>(resolve => { releaseUpload = resolve; });
  const uploadStarted = new Promise<void>(resolve => { markUploadStarted = resolve; });
  const conflictReturned = new Promise<void>(resolve => { markConflictReturned = resolve; });

  await page.addInitScript(value => { window.__JW_ADMIN__ = value; }, bootstrap);
  await page.route('**/admin/api/media-assets', async route => {
    markUploadStarted();
    await uploadRelease;
    await route.fulfill({
      status: 201,
      contentType: 'application/json',
      body: JSON.stringify({ data: { id: 'asset-id', url: managedUrl, mimeType: 'image/webp', size: 12 } }),
    });
  });
  await page.route('**/admin/api/pages**', async route => {
    const request = route.request();
    const path = new URL(request.url()).pathname;
    if (request.method() === 'GET' && path === '/admin/api/pages') {
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: pages }) });
    }

    const match = path.match(/^\/admin\/api\/pages\/([^/]+)\/draft$/);
    const pageId = match?.[1];
    const index = pages.findIndex(item => item.id === pageId);
    if (request.method() === 'GET' && index >= 0) {
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: draftPayload(pages[index]) }) });
    }
    if (request.method() === 'PATCH' && pageId === 'page-a') {
      pageAPatchCount += 1;
      markConflictReturned();
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
  await page.getByRole('button', { name: 'Edit Layout' }).first().click();
  await page.locator('input[type="file"]').first().setInputFiles({
    name: 'hero.webp',
    mimeType: 'image/webp',
    buffer: Buffer.from('test-only-upload'),
  });
  await uploadStarted;

  await page.getByRole('button', { name: 'Pages' }).click();
  await page.getByRole('button', { name: 'Edit Layout' }).nth(1).click();
  await expect(page.getByPlaceholder('Section main heading')).toHaveValue('Second headline');
  releaseUpload();
  await conflictReturned;

  await page.getByRole('button', { name: 'Pages' }).click();
  await expect(page.locator('#page-admin-directory')).toBeVisible();
  await expect(page.locator(`img[src="${managedUrl}"]`)).toBeVisible();
  await page.getByRole('button', { name: 'Edit Layout' }).first().click();

  await expect(page.locator('#page-admin-directory')).toBeVisible();
  await expect(page.locator('#page-visual-builder')).toHaveCount(0);
  await expect(page.locator(`img[src="${managedUrl}"]`)).toBeVisible();
  expect(pageAPatchCount).toBe(1);
});
