import { execFileSync, spawn, type ChildProcess } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { mkdirSync, writeFileSync } from 'node:fs';
import { connect } from 'node:net';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { expect, test, type Page } from '@playwright/test';

type HarnessResult = {
  ok: boolean;
  mode: string;
  database: string;
  orderNumber?: string;
  baselineCounts?: Record<string, number>;
  fixtureCounts?: Record<string, number>;
  countsRestored?: boolean;
  finalCounts?: Record<string, number>;
};

const testDirectory = fileURLToPath(new URL('.', import.meta.url));
const repositoryRoot = resolve(testDirectory, '../../..');
const evidenceDirectory = resolve(repositoryRoot, 'output/playwright/order-notification-email');
const harness = resolve(testDirectory, 'fixtures/order-notification-email-harness.php');
const php = 'C:\\xampp\\php\\php.exe';
const port = 8569;
const baseUrl = `http://127.0.0.1:${port}`;
const router = resolve(repositoryRoot, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php');
const token = randomBytes(10).toString('hex');
const adminEmail = `task9-admin-${token}@example.test`;
const adminPassword = `${randomBytes(24).toString('base64url')}aA1!`;
const privateProviderUrl = `https://private.easyparcel.test/${token}?token=TASK9_PRIVATE_TOKEN`;
const privateProviderId = `TASK9_PROVIDER_PRIVATE_`;
const statePath = resolve(repositoryRoot, 'storage/framework/testing', `task9-order-notification-${token}.json`);

let server: ChildProcess | null = null;
let setup: HarnessResult | null = null;

const acceptanceEnvironment = (): NodeJS.ProcessEnv => ({
  ...process.env,
  APP_ENV: 'testing',
  APP_DEBUG: 'false',
  APP_URL: baseUrl,
  DB_CONNECTION: 'mysql',
  DB_DATABASE: 'jw_ecommerce_testing',
  QUEUE_CONNECTION: 'database',
  MAIL_MAILER: 'array',
  LOG_CHANNEL: 'null',
  CACHE_STORE: 'array',
  SESSION_DRIVER: 'database',
  ORDER_NOTIFICATION_ACCEPTANCE_TOKEN: token,
  ORDER_NOTIFICATION_ACCEPTANCE_ADMIN_PASSWORD: adminPassword,
  ORDER_NOTIFICATION_ACCEPTANCE_STATE_PATH: statePath,
});

const portHasListener = (): Promise<boolean> => new Promise(resolvePort => {
  const socket = connect({ host: '127.0.0.1', port });
  socket.once('connect', () => { socket.destroy(); resolvePort(true); });
  socket.once('error', () => { socket.destroy(); resolvePort(false); });
  socket.setTimeout(1000, () => { socket.destroy(); resolvePort(false); });
});

const waitForLogin = async (): Promise<void> => {
  for (let attempt = 0; attempt < 80; attempt += 1) {
    try {
      const response = await fetch(`${baseUrl}/admin/login`);
      if (response.ok) return;
    } catch {}
    await new Promise(resolveWait => setTimeout(resolveWait, 250));
  }
  throw new Error('Owned Task 9 smoke runtime did not make /admin/login ready.');
};

const stopOwnedServer = async (): Promise<boolean> => {
  if (server?.pid) {
    try { process.kill(server.pid); } catch {}
  }
  for (let attempt = 0; attempt < 30 && await portHasListener(); attempt += 1) {
    await new Promise(resolveWait => setTimeout(resolveWait, 100));
  }
  return !(await portHasListener());
};

const runHarness = (mode: 'probe' | 'setup' | 'cleanup'): HarnessResult => {
  const raw = execFileSync(php, [harness, mode], {
    cwd: repositoryRoot,
    env: acceptanceEnvironment(),
    encoding: 'utf8',
  });
  return JSON.parse(raw.trim()) as HarnessResult;
};

const writeEvidence = (name: string, evidence: unknown): void => {
  mkdirSync(evidenceDirectory, { recursive: true });
  writeFileSync(resolve(evidenceDirectory, name), `${JSON.stringify(evidence, null, 2)}\n`);
};

const login = async (page: Page, destination: string): Promise<void> => {
  await page.goto(`${baseUrl}${destination}`);
  if (await page.getByRole('heading', { name: 'Admin Portal Login' }).isVisible().catch(() => false)) {
    await page.locator('#email').fill(adminEmail);
    await page.locator('#password').fill(adminPassword);
    await Promise.all([
      page.waitForURL(url => url.pathname.startsWith('/admin')),
      page.getByRole('button', { name: 'Sign in to Admin' }).click(),
    ]);
  }
};

test.describe.serial('Order notification Email authenticated localhost smoke', () => {
  test.setTimeout(120_000);

  test.beforeAll(async () => {
    mkdirSync(evidenceDirectory, { recursive: true });
    mkdirSync(resolve(repositoryRoot, 'storage/framework/testing'), { recursive: true });
    if (await portHasListener()) {
      throw new Error(`Task 9 refuses to run because port ${port} already has a listener. No process was touched.`);
    }

    execFileSync(php, ['artisan', 'optimize:clear', '--no-interaction'], {
      cwd: repositoryRoot,
      env: acceptanceEnvironment(),
      stdio: 'ignore',
    });
    const probe = runHarness('probe');
    expect(probe).toMatchObject({ ok: true, mode: 'probe', database: 'jw_ecommerce_testing' });
    writeEvidence('database-isolation-proof.json', probe);

    execFileSync(php, ['artisan', 'migrate', '--env=testing', '--force', '--no-interaction'], {
      cwd: repositoryRoot,
      env: acceptanceEnvironment(),
      stdio: 'ignore',
    });
    setup = runHarness('setup');
    writeEvidence('fixture-setup.json', setup);

    const args = ['-d', 'display_errors=0', '-S', `127.0.0.1:${port}`, router];
    server = spawn(php, args, {
      cwd: resolve(repositoryRoot, 'public'),
      env: acceptanceEnvironment(),
      detached: true,
      stdio: 'ignore',
    });
    if (!server.pid) throw new Error('Task 9 could not obtain its owned PHP server PID.');
    server.unref();
    await waitForLogin();
    writeEvidence('owned-runtime.json', {
      status: 'started',
      host: '127.0.0.1',
      port,
      listenerPreflight: false,
      serverPid: server.pid,
      database: 'jw_ecommerce_testing',
      mailer: 'array',
      startedAt: new Date().toISOString(),
    });
  });

  test.afterAll(async () => {
    let cleanup: HarnessResult | null = null;
    let cleanupError: unknown = null;
    try {
      if (setup !== null) {
        cleanup = runHarness('cleanup');
        writeEvidence('fixture-cleanup.json', cleanup);
      }
    } catch (error) {
      cleanupError = error;
    }

    const portClear = await stopOwnedServer();
    writeEvidence('owned-runtime-stop.json', {
      status: portClear ? 'stopped' : 'stop-not-proven',
      port,
      serverPid: server?.pid ?? null,
      portClear,
      stoppedAt: new Date().toISOString(),
    });
    if (cleanupError) throw cleanupError;
    if (cleanup?.countsRestored !== true) throw new Error('Task 9 smoke fixture cleanup did not restore the exact baseline.');
    if (!portClear) throw new Error(`Task 9 did not prove port ${port} clear after stopping its owned server.`);
  });

  test('loads the protected Settings and Order notification surfaces without delivery actions', async ({ page }) => {
    const orderNumber = setup?.orderNumber;
    expect(orderNumber).toBeTruthy();

    const blockedExternalRequests: string[] = [];
    const privateNetworkLeaks: string[] = [];
    const responseBodyChecks: Promise<void>[] = [];
    await page.route('**/*', async route => {
      const url = route.request().url();
      if (url.includes(privateProviderUrl) || url.includes(privateProviderId)) privateNetworkLeaks.push(url);
      const parsed = new URL(url);
      if (parsed.hostname !== '127.0.0.1') {
        blockedExternalRequests.push(`${parsed.protocol}//${parsed.host}${parsed.pathname}`);
        await route.abort('blockedbyclient');
        return;
      }
      await route.continue();
    });
    page.on('response', response => {
      const contentType = response.headers()['content-type'] ?? '';
      if (!response.url().startsWith(baseUrl) || (!contentType.includes('json') && !contentType.includes('html'))) return;
      responseBodyChecks.push(response.text().then(bodyText => {
        if (bodyText.includes(privateProviderUrl) || bodyText.includes(privateProviderId)) {
          privateNetworkLeaks.push(`${response.request().method()} ${new URL(response.url()).pathname} response body`);
        }
      }).catch(() => undefined));
    });

    await page.setViewportSize({ width: 1440, height: 1000 });
    await login(page, '/admin/settings/email');
    await expect(page.getByRole('heading', { name: /Auto Email & WhatsApp Templates/ })).toBeVisible();
    await expect(page.getByRole('button', { name: /Payment Proof Rejected/ })).toBeVisible();
    await expect(page.getByRole('button', { name: /On Hold/ })).toContainText('DORMANT');
    await expect(page.getByText('Live Customer Preview')).toBeVisible();
    await page.screenshot({ path: resolve(evidenceDirectory, 'settings-desktop-smoke.png'), fullPage: true });

    await page.setViewportSize({ width: 390, height: 844 });
    await expect(page.getByRole('heading', { name: /Auto Email & WhatsApp Templates/ })).toBeAttached();
    await page.screenshot({ path: resolve(evidenceDirectory, 'settings-mobile-smoke.png'), fullPage: true });
    await page.setViewportSize({ width: 1440, height: 1000 });

    await page.goto(`${baseUrl}/admin/orders`);
    await page.getByPlaceholder('Search order #, customer name, email...').fill(orderNumber!);
    const orderRow = page.locator('tbody tr').filter({ hasText: orderNumber! });
    await expect(orderRow).toHaveCount(1);
    await orderRow.getByRole('button', { name: 'Details' }).click();
    const orderModal = page.locator('div.fixed.inset-0').filter({ hasText: orderNumber! }).first();
    await expect(orderModal).toContainText('Update Order Status & Dispatch Customer Notification');
    await expect(orderModal).toContainText('Email Delivery Audit');
    await expect(orderModal.getByRole('button', { name: 'Retry' })).toBeVisible();
    await expect(orderModal.getByRole('button', { name: 'Resend' })).toBeVisible();
    await page.getByRole('button', { name: 'Preview Message Template' }).click();
    await expect(orderModal).toContainText('Email Subject:');
    await expect(orderModal).toContainText('Not connected');
    await page.screenshot({ path: resolve(evidenceDirectory, 'order-detail-desktop-smoke.png'), fullPage: true });

    await page.setViewportSize({ width: 390, height: 844 });
    await expect(orderModal).toBeAttached();
    await page.screenshot({ path: resolve(evidenceDirectory, 'order-detail-mobile-smoke.png'), fullPage: true });

    await expect(page.locator('body')).not.toContainText(privateProviderUrl);
    await expect(page.locator('body')).not.toContainText(privateProviderId);
    await Promise.all(responseBodyChecks);
    expect(privateNetworkLeaks).toEqual([]);
    writeEvidence('browser-smoke-security.json', {
      scope: 'authenticated-read-only-smoke',
      deliveryActionsExecuted: false,
      privateProviderDataExposed: false,
      privateNetworkLeaks,
      externalRequestsBlockedBeforeNetwork: [...new Set(blockedExternalRequests)].sort(),
      officialRoutes: ['/admin/settings/email', '/admin/orders'],
    });
  });
});
