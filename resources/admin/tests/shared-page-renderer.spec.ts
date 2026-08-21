import { expect, test, type ConsoleMessage } from '@playwright/test';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const testDirectory = fileURLToPath(new URL('.', import.meta.url));
const repositoryRoot = resolve(testDirectory, '../../..');
const screenshotRoot = resolve(repositoryRoot, 'test-results/task-10');
const localImage = resolve(repositoryRoot, 'public/images/source/jwstudio_logo_1786766390670.jpg');

test('saved Preview and protected canary share the renderer, real shell, and responsive controls', async ({ page }) => {
  const consoleErrors: string[] = [];
  const pageErrors: string[] = [];
  await page.addInitScript(() => {
    type ListenerCounts = {adds: number; removes: number};
    type InstrumentedTop = Window & {__task10IframeKeydown?: ListenerCounts};
    const host = window.top as InstrumentedTop;
    if (window === window.top) host.__task10IframeKeydown = {adds: 0, removes: 0};
    const originalAdd = window.addEventListener;
    const originalRemove = window.removeEventListener;
    window.addEventListener = function (
      type: string,
      listener: EventListenerOrEventListenerObject,
      options?: boolean | AddEventListenerOptions,
    ) {
      if (window !== window.top && type === 'keydown') host.__task10IframeKeydown!.adds += 1;
      return originalAdd.call(window, type, listener, options);
    } as typeof window.addEventListener;
    window.removeEventListener = function (
      type: string,
      listener: EventListenerOrEventListenerObject,
      options?: boolean | EventListenerOptions,
    ) {
      if (window !== window.top && type === 'keydown') host.__task10IframeKeydown!.removes += 1;
      return originalRemove.call(window, type, listener, options);
    } as typeof window.removeEventListener;
  });
  const recordConsoleError = (message: ConsoleMessage) => {
    if (message.type() === 'error') consoleErrors.push(message.text());
  };
  page.on('console', recordConsoleError);
  page.on('pageerror', error => pageErrors.push(error.message));
  await page.route('https://images.unsplash.com/**', route => route.fulfill({
    status: 200,
    contentType: 'image/jpeg',
    path: localImage,
  }));

  await page.goto('/admin/pages');
  await page.getByLabel('Admin Work Email').fill('task10-admin@example.test');
  await page.getByLabel('Password').fill('Task10-password!');
  await page.getByRole('button', { name: 'Sign in to Admin' }).click();
  await expect(page).toHaveURL(/\/admin\/pages$/);
  await page.getByRole('button', { name: 'Edit Layout' }).click();

  const canvasHero = page.locator('#page-visual-builder #section-saved-hero');
  await expect(canvasHero).toContainText('SAVED MYSQL HERO');
  await expect(page.locator('#page-visual-builder #section-saved-products')).toContainText('Saved Catalog Product');
  await expect(page.locator('#page-visual-builder #section-hidden-secret')).toContainText('TASK 10 HIDDEN SECRET');

  const titleInput = page.locator('#page-visual-builder input[type="text"]').first();
  await titleInput.fill('Task 10 Flushed Draft');

  let releasePreview!: () => void;
  let markPreviewRequested!: () => void;
  const previewRelease = new Promise<void>(resolvePromise => { releasePreview = resolvePromise; });
  const previewRequested = new Promise<void>(resolvePromise => { markPreviewRequested = resolvePromise; });
  await page.route('**/admin/pages/1/preview', async route => {
    markPreviewRequested();
    await previewRelease;
    await route.continue();
  }, { times: 1 });

  await page.getByRole('button', { name: 'Preview', exact: true }).click();
  await previewRequested;
  await titleInput.fill('TASK 10 UNSAVED LATER LOCAL EDIT', { force: true });
  releasePreview();

  const previewFrame = page.frameLocator('iframe[title^="Saved preview of"]');
  await expect(previewFrame.locator('#cms-page-root')).toBeVisible();
  await expect(previewFrame.locator('#section-saved-hero')).toContainText('SAVED MYSQL HERO');
  await expect(previewFrame.locator('#section-saved-products')).toContainText('Saved Catalog Product');
  await expect(previewFrame.locator('#section-hidden-secret')).toHaveCount(0);
  const savedBootstrap = await previewFrame.locator('#cms-page-runtime').textContent();
  expect(savedBootstrap).toContain('Task 10 Flushed Draft');
  expect(savedBootstrap).not.toContain('TASK 10 UNSAVED LATER LOCAL EDIT');

  await expect(previewFrame.locator('[data-mega-nav]')).toBeVisible();
  await expect(previewFrame.locator('nav[aria-label="Atelier collections"]').getByRole('link', { name: 'Task 10 Real Menu' })).toBeVisible();
  await expect(previewFrame.locator('[data-mobile-mega-panel]')).toHaveCount(1);
  await expect(previewFrame.getByText('Saved Footer Menu', { exact: true })).toBeVisible();
  await expect(previewFrame.getByText(/ATELIER Design Studio Inc\./)).toBeVisible();
  await expect(previewFrame.locator('body')).not.toContainText('AURORA');

  const iframe = page.locator('iframe[title^="Saved preview of"]');
  const previewModal = page.locator('div.fixed.inset-0.z-50');
  const desktopBox = await iframe.boundingBox();
  expect(desktopBox?.width ?? 0).toBeGreaterThan(1000);
  await page.screenshot({ path: resolve(screenshotRoot, 'preview-desktop.png'), fullPage: true });

  await previewFrame.locator('body').evaluate(element => {
    element.tabIndex = -1;
    element.focus();
  });
  await page.keyboard.press('Alt+ArrowDown');
  await expect(previewModal.getByText('2/3', { exact: true })).toBeVisible();
  await expect.poll(async () => {
    const box = await previewFrame.locator('#section-saved-products').boundingBox();
    return box?.y ?? Number.POSITIVE_INFINITY;
  }).toBeLessThan(240);
  await page.keyboard.press('Alt+ArrowUp');
  await expect(previewModal.getByText('1/3', { exact: true })).toBeVisible();
  await expect.poll(async () => {
    const box = await previewFrame.locator('#section-saved-hero').boundingBox();
    return box?.y ?? Number.POSITIVE_INFINITY;
  }).toBeLessThan(240);

  await previewModal.getByTitle('Tablet 768px').click();
  await expect.poll(async () => (await iframe.boundingBox())?.width ?? 0).toBeLessThanOrEqual(768);
  expect((await iframe.boundingBox())?.width ?? 0).toBeGreaterThan(740);

  await previewModal.getByTitle('Mobile 390px').click();
  await expect.poll(async () => (await iframe.boundingBox())?.width ?? 0).toBeLessThanOrEqual(390);
  expect((await iframe.boundingBox())?.width ?? 0).toBeGreaterThan(370);
  const iframeOverflow = await previewFrame.locator('html').evaluate(element => ({
    clientWidth: element.clientWidth,
    scrollWidth: element.scrollWidth,
  }));
  expect(iframeOverflow.scrollWidth).toBeLessThanOrEqual(iframeOverflow.clientWidth + 1);
  await page.screenshot({ path: resolve(screenshotRoot, 'preview-mobile-stage.png'), fullPage: true });

  await previewModal.locator('aside').getByRole('button').filter({ hasText: 'SAVED CLOSING STORY' }).click();
  await expect.poll(async () => {
    const box = await previewFrame.locator('#section-saved-story').boundingBox();
    return box?.y ?? Number.POSITIVE_INFINITY;
  }).toBeLessThan(240);

  const firstListenerCounts = await page.evaluate(() => (
    window as typeof window & {__task10IframeKeydown: {adds: number; removes: number}}
  ).__task10IframeKeydown);
  expect(firstListenerCounts.adds).toBeGreaterThan(firstListenerCounts.removes);
  await previewFrame.locator('body').evaluate(element => element.focus());
  await page.keyboard.press('Escape');
  await expect(page.getByText('Live Customer View')).toHaveCount(0);
  await expect.poll(async () => page.evaluate(() => (
    window as typeof window & {__task10IframeKeydown: {removes: number}}
  ).__task10IframeKeydown.removes)).toBeGreaterThan(firstListenerCounts.removes);

  await page.getByRole('button', { name: 'Preview', exact: true }).click();
  await expect(previewFrame.locator('#cms-page-root')).toBeVisible();
  await expect.poll(async () => page.evaluate(() => (
    window as typeof window & {__task10IframeKeydown: {adds: number}}
  ).__task10IframeKeydown.adds)).toBeGreaterThan(firstListenerCounts.adds);
  const reopenedListenerCounts = await page.evaluate(() => (
    window as typeof window & {__task10IframeKeydown: {adds: number; removes: number}}
  ).__task10IframeKeydown);
  expect(reopenedListenerCounts.adds - reopenedListenerCounts.removes).toBe(1);
  await previewFrame.locator('body').evaluate(element => element.focus());
  await page.keyboard.press('Alt+ArrowUp');
  await expect(previewModal.getByText('2/3', { exact: true })).toBeVisible();
  const beforeSecondClose = await page.evaluate(() => (
    window as typeof window & {__task10IframeKeydown: {removes: number}}
  ).__task10IframeKeydown.removes);
  await page.keyboard.press('Escape');
  await expect(page.getByText('Live Customer View')).toHaveCount(0);
  await expect.poll(async () => page.evaluate(() => (
    window as typeof window & {__task10IframeKeydown: {removes: number}}
  ).__task10IframeKeydown.removes)).toBeGreaterThan(beforeSecondClose);

  await page.goto('/admin/cms-canary/pages/1');
  await expect(page.locator('[data-mega-nav]')).toBeVisible();
  await expect(page.locator('nav[aria-label="Atelier collections"]').getByRole('link', { name: 'Task 10 Real Menu' })).toBeVisible();
  await expect(page.locator('#section-saved-hero')).toContainText('SAVED MYSQL HERO');
  await expect(page.locator('#section-saved-products')).toContainText('Saved Catalog Product');
  await expect(page.locator('#section-hidden-secret')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('AURORA');
  const canaryDesktopOverflow = await page.locator('html').evaluate(element => ({
    clientWidth: element.clientWidth,
    scrollWidth: element.scrollWidth,
  }));
  expect(canaryDesktopOverflow.scrollWidth).toBeLessThanOrEqual(canaryDesktopOverflow.clientWidth + 1);
  await page.screenshot({ path: resolve(screenshotRoot, 'canary-desktop.png'), fullPage: true });

  await page.setViewportSize({ width: 390, height: 844 });
  const canaryMobileOverflow = await page.locator('html').evaluate(element => ({
    clientWidth: element.clientWidth,
    scrollWidth: element.scrollWidth,
  }));
  expect(canaryMobileOverflow.scrollWidth).toBeLessThanOrEqual(canaryMobileOverflow.clientWidth + 1);
  await page.screenshot({ path: resolve(screenshotRoot, 'canary-mobile.png'), fullPage: true });
  await page.getByRole('button', { name: 'Open menu' }).click();
  await expect(page.locator('[data-mobile-mega-panel]')).toBeVisible();
  await expect(page.locator('[data-mobile-mega-panel]').getByText('Task 10 Real Menu', { exact: true })).toBeVisible();

  expect(consoleErrors).toEqual([]);
  expect(pageErrors).toEqual([]);
});
