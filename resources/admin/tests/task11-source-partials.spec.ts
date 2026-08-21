import { expect, test, type ConsoleMessage, type FrameLocator, type Locator } from '@playwright/test';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const testDirectory = fileURLToPath(new URL('.', import.meta.url));
const repositoryRoot = resolve(testDirectory, '../../..');
const screenshotRoot = resolve(repositoryRoot, 'test-results/task-11/converted');
const localImage = resolve(repositoryRoot, 'public/images/source/jwstudio_logo_1786766390670.jpg');

const completeContact = async (scope: Locator | FrameLocator) => {
  await scope.getByPlaceholder('e.g. Søren Lindqvist').fill('Søren Lindqvist');
  await scope.getByPlaceholder('soren@example.com').fill('soren@example.com');
  await scope.getByPlaceholder('e.g. Custom Engraving / Setup Inquiry').fill('Custom Engraving');
  await scope.getByRole('combobox').selectOption({ label: 'Product Enquiry' });
  await scope.getByPlaceholder('Tell us about your audio setup or custom acoustic requirements...').fill('Please advise on a balanced studio setup.');
  await scope.getByRole('button', { name: 'Send Inquiries to Atelier' }).click();
  await expect(scope.getByText('Inquiry Received', { exact: true })).toBeVisible();
};

test('converted Canvas, saved Preview, and canary preserve the three selected source regions', async ({ page }) => {
  const consoleErrors: string[] = [];
  const pageErrors: string[] = [];
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
  await page.getByLabel('Admin Work Email').fill('task11-admin@example.test');
  await page.getByLabel('Password').fill('Task11-password!');
  await page.getByRole('button', { name: 'Sign in to Admin' }).click();
  await expect(page).toHaveURL(/\/admin\/pages$/);
  await page.getByRole('button', { name: 'Edit Layout' }).click();

  const canvas = page.locator('#page-visual-builder');
  const canvasCollection = canvas.locator('#section-task11-collection');
  const canvasContact = canvas.locator('#section-task11-contact');
  const canvasNewsletter = canvas.locator('#section-task11-newsletter');

  await expect(canvasCollection).toContainText('Explore Aurora Audio Ecosystem');
  await expect(canvasCollection).toContainText('Aurora Reference One');
  await expect(canvasCollection).toContainText('Aurora Reference Two');
  await expect(canvasCollection).not.toContainText('Aurora Limit Excluded');
  await canvasCollection.getByRole('button', { name: 'Premium Audio' }).click();
  await expect(canvasCollection).toContainText('Aurora Studio Pro Wireless Headphones');
  await expect(canvasCollection.getByRole('button', { name: 'Premium Audio' })).toHaveClass(/bg-indigo-600/);
  await canvasCollection.scrollIntoViewIfNeeded();
  await page.screenshot({ path: resolve(screenshotRoot, 'canvas-collection-desktop.png'), fullPage: true });

  await canvasContact.scrollIntoViewIfNeeded();
  await page.screenshot({ path: resolve(screenshotRoot, 'canvas-contact-desktop.png'), fullPage: true });
  await page.getByTitle('Mobile 390px').click();
  await canvasContact.scrollIntoViewIfNeeded();
  const canvasContactWidths = await canvasContact.evaluate(element => ({
    clientWidth: element.clientWidth,
    scrollWidth: element.scrollWidth,
  }));
  expect(canvasContactWidths.scrollWidth).toBeGreaterThan(canvasContactWidths.clientWidth);
  await page.screenshot({ path: resolve(screenshotRoot, 'canvas-contact-mobile-stage-source-clipping.png'), fullPage: true });
  await page.getByTitle('Desktop Full Screen').click();
  await canvasContact.scrollIntoViewIfNeeded();
  await completeContact(canvasContact);
  await page.screenshot({ path: resolve(screenshotRoot, 'canvas-contact-success.png'), fullPage: true });

  await canvasNewsletter.click();
  await expect(page.getByText('SUBSCRIBER INCENTIVE COUPON', { exact: true })).toBeVisible();
  await expect(page.getByRole('option', { name: /\$30 Subscriber Voucher/ })).toHaveCount(1);
  await canvasNewsletter.getByPlaceholder('Enter your VIP email address...').fill('listener@example.com');
  await canvasNewsletter.getByRole('button', { name: 'Claim $30 Voucher' }).click();
  await expect(canvasNewsletter).toContainText('Selected subscriber coupon is unavailable or expired. Please contact support.');
  await page.screenshot({ path: resolve(screenshotRoot, 'canvas-newsletter-mock-gap.png'), fullPage: true });

  await page.getByRole('button', { name: 'Preview', exact: true }).click();
  const previewFrame = page.frameLocator('iframe[title^="Saved preview of"]');
  const previewIframe = page.locator('iframe[title^="Saved preview of"]');
  const previewModal = page.locator('div.fixed.inset-0.z-50');
  await expect(previewFrame.locator('#cms-page-root')).toBeVisible();

  const previewCollection = previewFrame.locator('#section-task11-collection');
  await expect(previewCollection).toContainText('Aurora Reference One');
  await expect(previewCollection).not.toContainText('Aurora Limit Excluded');
  await previewCollection.getByRole('button', { name: 'Premium Audio' }).click();
  await expect(previewCollection).toContainText('Aurora Studio Pro Wireless Headphones');
  await previewModal.getByTitle('Desktop 100%').click();
  await previewCollection.screenshot({ path: resolve(screenshotRoot, 'preview-collection-desktop.png') });

  const previewContact = previewFrame.locator('#section-task11-contact');
  await completeContact(previewContact);
  await previewContact.screenshot({ path: resolve(screenshotRoot, 'preview-contact-success.png') });

  const previewNewsletter = previewFrame.locator('#section-task11-newsletter');
  await previewNewsletter.getByPlaceholder('Enter your VIP email address...').fill('listener@example.com');
  await previewNewsletter.getByRole('button', { name: 'Claim $30 Voucher' }).click();
  await expect(previewNewsletter).toContainText('Your subscriber voucher has been sent to your email!');
  await expect(previewNewsletter).not.toContainText('TASK11SECRET');
  await previewNewsletter.screenshot({ path: resolve(screenshotRoot, 'preview-newsletter-success-no-code.png') });

  await previewModal.getByTitle('Mobile 390px').click();
  await expect.poll(async () => (await previewIframe.boundingBox())?.width ?? 0).toBeLessThanOrEqual(390);
  const previewOverflow = await previewFrame.locator('html').evaluate(element => ({
    clientWidth: element.clientWidth,
    scrollWidth: element.scrollWidth,
  }));
  expect(previewOverflow.scrollWidth).toBeLessThanOrEqual(previewOverflow.clientWidth + 1);
  await page.screenshot({ path: resolve(screenshotRoot, 'preview-mobile-stage.png'), fullPage: true });

  await previewFrame.locator('body').evaluate(element => {
    element.tabIndex = -1;
    element.focus();
  });
  await page.keyboard.press('Escape');
  await expect(page.getByText('Live Customer View')).toHaveCount(0);

  await page.setViewportSize({ width: 1600, height: 1000 });
  await page.goto('/admin/cms-canary/pages/1');
  await expect(page.locator('#section-task11-collection')).toContainText('Aurora Reference One');
  await expect(page.locator('#section-task11-contact')).toContainText('Connect with Our Acoustic Atelier');
  await expect(page.locator('#section-task11-newsletter')).toContainText('Receive $30 Off Your First Order');
  await expect(page.locator('body')).not.toContainText('TASK11SECRET');
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

  expect(consoleErrors).toEqual([]);
  expect(pageErrors).toEqual([]);
});
