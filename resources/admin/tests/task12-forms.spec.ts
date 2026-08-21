import { expect, test, type ConsoleMessage, type Page } from '@playwright/test';
import { Buffer } from 'node:buffer';
import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const testDirectory = fileURLToPath(new URL('.', import.meta.url));
const repositoryRoot = resolve(testDirectory, '../../..');
const screenshotRoot = resolve(repositoryRoot, 'test-results/task-12/screenshots');
const databasePath = resolve(repositoryRoot, 'test-results/task-12/task12-browser.sqlite');
const php = 'C:\\xampp\\php\\php.exe';

type Contract = {endpoint: string};

const runtime = async (page: Page) => JSON.parse(await page.locator('#cms-page-runtime').textContent() ?? '{}') as {
  page: {stage: string}; forms: {contact: Record<string, Contract>; newsletter: Record<string, Contract>};
};

const contract = async (page: Page, kind: 'contact' | 'newsletter', id: string) => {
  const value = (await runtime(page)).forms[kind][id];
  expect(value.endpoint).toMatch(/^\/cms\/forms\//);
  expect(value.endpoint).toContain('signature=');
  return value.endpoint;
};

const csrf = async (page: Page) => page.locator('meta[name="csrf-token"]').getAttribute('content');

const post = async (page: Page, endpoint: string, body: Record<string, string>, token?: string | null) => page.evaluate(async ({endpoint_, body_, token_}) => {
  const response = await fetch(endpoint_, {
    method: 'POST', credentials: 'same-origin',
    headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(token_ ? {'X-CSRF-TOKEN': token_} : {})},
    body: JSON.stringify(body_),
  });
  return response.status;
}, {endpoint_: endpoint, body_: body, token_: token});

const contactPayload = () => ({name: 'Søren Lindqvist', email: 'contact@example.test', subject: 'Studio setup', category: 'Product Enquiry', message: 'Need a balanced monitor setup.'});

const databaseEvidence = () => JSON.parse(execFileSync(php, [resolve(testDirectory, 'fixtures/task12-browser-assert.php')], {
  cwd: repositoryRoot,
  env: {...process.env, APP_ENV: 'testing', APP_URL: 'http://127.0.0.1:8537', DB_CONNECTION: 'sqlite', DB_DATABASE: databasePath, SESSION_DRIVER: 'cookie', CACHE_STORE: 'array', QUEUE_CONNECTION: 'sync'},
  encoding: 'utf8',
})) as {contact: number; newsletter: number; newsletter_emails: string[]};

const clearRateLimits = () => execFileSync(php, [resolve(testDirectory, 'fixtures/task12-browser-clear-rate-limits.php')], {
  cwd: repositoryRoot,
  env: {...process.env, APP_ENV: 'testing', APP_URL: 'http://127.0.0.1:8537', DB_CONNECTION: 'sqlite', DB_DATABASE: databasePath, SESSION_DRIVER: 'cookie', CACHE_STORE: 'database', QUEUE_CONNECTION: 'sync'},
});

test('Task 12 forms use real middleware, signed contracts, and accepted desktop/mobile UI', async ({ page }) => {
  const consoleErrors: string[] = [];
  const pageErrors: string[] = [];
  page.on('console', (message: ConsoleMessage) => {
    const expectedProbeFailure = /server responded with a status of (403|419|422|429)/.test(message.text());
    if (message.type() === 'error' && !expectedProbeFailure) consoleErrors.push(`${message.text()} @ ${message.location().url}`);
  });
  page.on('pageerror', error => pageErrors.push(error.message));

  // The isolated browser sandbox denies the legacy external avatar request. Keep the real
  // Laravel route under test while serving the same harmless image request locally.
  await page.route('https://images.unsplash.com/photo-1534528741775-53994a69daeb**', route => route.fulfill({
    contentType: 'image/gif',
    body: Buffer.from('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==', 'base64'),
  }));

  await page.goto('/admin/pages');
  await page.getByLabel('Admin Work Email').fill('task12-admin@example.test');
  await page.getByLabel('Password').fill('Task12-password!');
  await page.getByRole('button', {name: 'Sign in to Admin'}).click();
  await expect(page).toHaveURL(/\/admin\/pages$/);

  await page.goto('/admin/cms-canary/pages/1');
  await expect(page.locator('#cms-page-root')).toBeVisible();
  expect((await runtime(page)).page.stage).toBe('draft');
  const contactEndpoint = await contract(page, 'contact', 'task12-contact');
  const newsletterEndpoint = await contract(page, 'newsletter', 'task12-newsletter');
  const token = await csrf(page);
  expect(token).toBeTruthy();

  // Real web middleware: no token and an invalid token both fail before writes.
  expect(await post(page, contactEndpoint, contactPayload())).toBe(419);
  expect(await post(page, contactEndpoint, contactPayload(), 'invalid-csrf-token')).toBe(419);
  expect(await post(page, contactEndpoint.replace(/expires=\d+/, 'expires=1'), contactPayload(), token)).toBe(403);
  expect(await post(page, newsletterEndpoint.replace('hash=', 'hash=stale-'), {email: 'stale@example.test'}, token)).toBe(403);

  const contact = page.locator('#section-task12-contact');
  await contact.getByPlaceholder('e.g. Søren Lindqvist').fill('Søren Lindqvist');
  await contact.getByPlaceholder('soren@example.com').fill('contact@example.test');
  await contact.getByPlaceholder('e.g. Custom Engraving / Setup Inquiry').fill('Studio setup');
  await contact.getByRole('combobox').selectOption({label: 'Product Enquiry'});
  await contact.getByPlaceholder('Tell us about your audio setup or custom acoustic requirements...').fill('Need a balanced monitor setup.');
  await contact.getByRole('button', {name: /Send/}).click();
  await expect(contact.getByText('Inquiry Received', {exact: true})).toBeVisible();
  await page.screenshot({path: resolve(screenshotRoot, 'canary-contact-success-desktop.png'), fullPage: true});

  const newsletter = page.locator('#section-task12-newsletter');
  await newsletter.getByPlaceholder('Enter your VIP email address...').fill('New.Listener@Example.Test');
  await newsletter.getByRole('button', {name: 'Claim 15% Voucher'}).click();
  await expect(newsletter.getByText('Your subscriber voucher has been sent to your email!', {exact: true})).toBeVisible();
  await expect(newsletter.getByText('Voucher Code:', {exact: true})).toBeVisible();
  await expect(newsletter.getByText('TASK12VOUCHER', {exact: true})).toBeVisible();
  await page.screenshot({path: resolve(screenshotRoot, 'canary-newsletter-new-voucher-desktop.png'), fullPage: true});

  await page.reload();
  const duplicate = page.locator('#section-task12-newsletter');
  await duplicate.getByPlaceholder('Enter your VIP email address...').fill(' new.listener@example.test ');
  await duplicate.getByRole('button', {name: 'Claim 15% Voucher'}).click();
  await expect(duplicate.getByText('Your subscriber voucher has been sent to your email!', {exact: true})).toBeVisible();
  await expect(duplicate.getByText('Voucher Code:', {exact: true})).toHaveCount(0);
  await expect(duplicate.getByText('TASK12VOUCHER', {exact: true})).toHaveCount(0);

  // Both named limits use valid CSRF and invalid bodies, so a 429 cannot have written a row.
  const reloadedToken = await csrf(page);
  const reloadedContactEndpoint = await contract(page, 'contact', 'task12-contact');
  const reloadedNewsletterEndpoint = await contract(page, 'newsletter', 'task12-newsletter');
  clearRateLimits();
  for (let attempt = 0; attempt < 3; attempt++) expect(await post(page, reloadedContactEndpoint, {...contactPayload(), message: ''}, reloadedToken)).toBe(422);
  expect(await post(page, reloadedContactEndpoint, {...contactPayload(), message: ''}, reloadedToken)).toBe(429);
  for (let attempt = 0; attempt < 3; attempt++) expect(await post(page, reloadedNewsletterEndpoint, {email: 'bad-email'}, reloadedToken)).toBe(422);
  expect(await post(page, reloadedNewsletterEndpoint, {email: 'bad-email'}, reloadedToken)).toBe(429);
  clearRateLimits();

  await page.goto('/admin/cms-canary/pages/2');
  const exhausted = page.locator('#section-task12-expired-newsletter');
  await exhausted.getByPlaceholder('Enter your VIP email address...').fill('expired@example.test');
  await exhausted.getByRole('button', {name: 'Claim 15% Voucher'}).click();
  await expect(exhausted.getByText('Selected subscriber coupon is unavailable or expired. Please contact support.', {exact: true})).toBeVisible();

  // Canvas starts without public coupon facts, then fetches protected draft contracts and submits successfully.
  await page.goto('/admin/pages');
  await page.getByRole('button', {name: 'Edit Layout'}).first().click();
  const canvasNewsletter = page.locator('#page-visual-builder #section-task12-newsletter');
  await canvasNewsletter.getByPlaceholder('Enter your VIP email address...').fill('canvas@example.test');
  await canvasNewsletter.getByRole('button', {name: 'Claim 15% Voucher'}).click();
  await expect(canvasNewsletter.getByText('Voucher Code:', {exact: true})).toBeVisible();
  await expect(canvasNewsletter.getByText('TASK12VOUCHER', {exact: true})).toBeVisible();

  await page.goto('/admin/cms-canary/pages/1');
  await page.setViewportSize({width: 390, height: 844});
  const overflow = await page.locator('html').evaluate(element => ({clientWidth: element.clientWidth, scrollWidth: element.scrollWidth}));
  expect(overflow.scrollWidth).toBeLessThanOrEqual(overflow.clientWidth + 1);
  await page.screenshot({path: resolve(screenshotRoot, 'canary-mobile.png'), fullPage: true});

  expect(databaseEvidence()).toEqual({contact: 1, newsletter: 2, newsletter_emails: ['canvas@example.test', 'new.listener@example.test']});
  expect(consoleErrors).toEqual([]);
  expect(pageErrors).toEqual([]);
});
