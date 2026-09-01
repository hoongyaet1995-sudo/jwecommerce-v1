import { execFileSync, spawn, spawnSync, type ChildProcess } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { existsSync, mkdirSync, writeFileSync } from 'node:fs';
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
  businessStateUnchanged?: boolean;
  finalCounts?: Record<string, number>;
  counts?: Record<string, number>;
  businessCounts?: Record<string, number>;
  businessFingerprint?: string;
  templateFingerprint?: string;
  orderId?: number;
  ownedAssociations?: Record<string, { ids: number[]; fingerprint: string }>;
  associationGuardId?: number;
  associationStateRestored?: boolean;
};

type RequestLedgerEntry = {
  method: string;
  path: string;
  classification: 'foreign-origin' | 'read' | 'login' | 'read-only-preview' | 'mutation';
  allowed: boolean;
};

const testDirectory = fileURLToPath(new URL('.', import.meta.url));
const repositoryRoot = resolve(testDirectory, '../../..');
const evidenceDirectory = resolve(repositoryRoot, 'output/playwright/order-notification-email');
const harness = resolve(testDirectory, 'fixtures/order-notification-email-harness.php');
const php = 'C:\\xampp\\php\\php.exe';
const port = 8569;
const baseUrl = `http://127.0.0.1:${port}`;
const acceptedOrigin = new URL(baseUrl).origin;
const router = resolve(repositoryRoot, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php');
const token = randomBytes(10).toString('hex');
const adminEmail = `task9-admin-${token}@example.test`;
const adminPassword = `${randomBytes(24).toString('base64url')}aA1!`;
const privateProviderUrl = `https://private.easyparcel.test/${token}?token=TASK9_PRIVATE_TOKEN`;
const privateProviderId = `TASK9_PROVIDER_PRIVATE_`;
const statePath = resolve(repositoryRoot, 'storage/framework/testing', `task9-order-notification-${token}.json`);

let server: ChildProcess | null = null;
let setup: HarnessResult | null = null;

const classifyInternalRequest = (
  rawUrl: string,
  method: string,
  fixtureOrderId: number,
): Pick<RequestLedgerEntry, 'path' | 'classification' | 'allowed'> => {
  const parsed = new URL(rawUrl);
  const path = parsed.pathname;
  const normalizedMethod = method.toUpperCase();
  const isRead = normalizedMethod === 'GET' || normalizedMethod === 'HEAD';
  const isLogin = normalizedMethod === 'POST' && path === '/admin/login';
  const isReadOnlyPreview = normalizedMethod === 'POST'
    && path === `/admin/api/orders/${fixtureOrderId}/notifications/preview`;
  const classification: RequestLedgerEntry['classification'] = parsed.origin !== acceptedOrigin
    ? 'foreign-origin'
    : isRead
      ? 'read'
      : isLogin
        ? 'login'
        : isReadOnlyPreview
          ? 'read-only-preview'
          : 'mutation';

  return { path, classification, allowed: classification !== 'foreign-origin' && classification !== 'mutation' };
};

const acceptanceEnvironment = (inherited: NodeJS.ProcessEnv = process.env): NodeJS.ProcessEnv => {
  const child = { ...inherited };
  for (const key of [
    'APP_CONFIG_CACHE',
    'APP_EVENTS_CACHE',
    'APP_PACKAGES_CACHE',
    'APP_ROUTES_CACHE',
    'APP_SERVICES_CACHE',
    'DB_CONNECTION',
    'DB_DATABASE',
    'DB_HOST',
    'DB_PASSWORD',
    'DB_PORT',
    'DB_QUEUE_CONNECTION',
    'DB_SOCKET',
    'DB_URL',
    'DB_USERNAME',
    'MYSQL_ATTR_SSL_CA',
    'SESSION_CONNECTION',
  ]) delete child[key];

  return {
    ...child,
    APP_ENV: 'testing',
    APP_DEBUG: 'false',
    APP_URL: baseUrl,
    DB_CONNECTION: 'mysql',
    DB_DATABASE: 'jw_ecommerce_testing',
    DB_HOST: '127.0.0.1',
    DB_PORT: '3306',
    DB_URL: '',
    DB_SOCKET: '',
    DB_QUEUE_CONNECTION: 'mysql',
    QUEUE_CONNECTION: 'database',
    MAIL_MAILER: 'array',
    LOG_CHANNEL: 'null',
    CACHE_STORE: 'array',
    SESSION_DRIVER: 'database',
    SESSION_CONNECTION: 'mysql',
    ORDER_NOTIFICATION_ACCEPTANCE_TOKEN: token,
    ORDER_NOTIFICATION_ACCEPTANCE_ADMIN_PASSWORD: adminPassword,
    ORDER_NOTIFICATION_ACCEPTANCE_STATE_PATH: statePath,
  };
};

const portHasListener = (): Promise<boolean> => new Promise(resolvePort => {
  const socket = connect({ host: '127.0.0.1', port });
  socket.once('connect', () => { socket.destroy(); resolvePort(true); });
  socket.once('error', () => { socket.destroy(); resolvePort(false); });
  socket.setTimeout(1000, () => { socket.destroy(); resolvePort(false); });
});

const waitForLogin = async (): Promise<void> => {
  for (let attempt = 0; attempt < 80; attempt += 1) {
    try {
      const response = await fetch(`${baseUrl}/up`);
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

type HarnessMode = 'probe' | 'baseline' | 'setup' | 'snapshot' | 'inject-cleanup-guard' | 'remove-cleanup-guard' | 'cleanup';

const runHarness = (mode: HarnessMode): HarnessResult => {
  const raw = execFileSync(php, [harness, mode], {
    cwd: repositoryRoot,
    env: acceptanceEnvironment(),
    encoding: 'utf8',
  });
  return JSON.parse(raw.trim()) as HarnessResult;
};

const refusedHarnessMode = (mode: HarnessMode): { exitCode: number; code: string } => {
  const result = spawnSync(php, [harness, mode], {
    cwd: repositoryRoot,
    env: acceptanceEnvironment(),
    encoding: 'utf8',
  });
  const payload = JSON.parse((result.stderr || result.stdout).trim()) as { code?: string };
  return { exitCode: result.status ?? -1, code: payload.code ?? 'missing_code' };
};

const refusedHarnessProbe = (overrides: NodeJS.ProcessEnv): { exitCode: number; code: string } => {
  const result = spawnSync(php, [harness, 'probe'], {
    cwd: repositoryRoot,
    env: { ...acceptanceEnvironment(), ...overrides },
    encoding: 'utf8',
  });
  const payload = JSON.parse((result.stderr || result.stdout).trim()) as { code?: string };
  return { exitCode: result.status ?? -1, code: payload.code ?? 'missing_code' };
};

const injectedSetupFailure = (): { exitCode: number; code: string } => {
  const result = spawnSync(php, [harness, 'setup'], {
    cwd: repositoryRoot,
    env: {
      ...acceptanceEnvironment(),
      ORDER_NOTIFICATION_ACCEPTANCE_FAIL_SETUP_AT: 'after_state_write',
    },
    encoding: 'utf8',
  });
  const payload = JSON.parse((result.stderr || result.stdout).trim()) as { code?: string };
  return { exitCode: result.status ?? -1, code: payload.code ?? 'missing_code' };
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
    await page.goto(`${baseUrl}${destination}`);
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

    expect(classifyInternalRequest(`${acceptedOrigin}/admin/api/orders/123/notifications/preview`, 'POST', 123))
      .toEqual({
        path: '/admin/api/orders/123/notifications/preview',
        classification: 'read-only-preview',
        allowed: true,
      });
    expect(classifyInternalRequest('http://127.0.0.1:8570/admin/settings/email', 'GET', 123))
      .toEqual({ path: '/admin/settings/email', classification: 'foreign-origin', allowed: false });
    expect(classifyInternalRequest(`${acceptedOrigin}/admin/api/orders/124/notifications/preview`, 'POST', 123))
      .toEqual({
        path: '/admin/api/orders/124/notifications/preview',
        classification: 'mutation',
        allowed: false,
      });

    execFileSync(php, ['artisan', 'optimize:clear', '--no-interaction'], {
      cwd: repositoryRoot,
      env: acceptanceEnvironment(),
      stdio: 'ignore',
    });

    const sanitized = acceptanceEnvironment({
      ...process.env,
      DB_HOST: 'production.invalid',
      DB_PORT: '6432',
      DB_URL: 'mysql://fake:fake@production.invalid:6432/jw_ecommerce_testing',
      DB_SOCKET: 'hostile-production-socket',
      DB_QUEUE_CONNECTION: 'production',
      SESSION_CONNECTION: 'production',
    });
    expect(sanitized).toMatchObject({
      DB_HOST: '127.0.0.1',
      DB_PORT: '3306',
      DB_URL: '',
      DB_SOCKET: '',
      DB_QUEUE_CONNECTION: 'mysql',
      SESSION_CONNECTION: 'mysql',
    });
    const hostileProofs = {
      inheritedEnvironmentSanitized: true,
      remoteHost: refusedHarnessProbe({ DB_HOST: 'production.invalid', DB_URL: '' }),
      remoteUrl: refusedHarnessProbe({
        DB_HOST: '127.0.0.1',
        DB_URL: 'mysql://fake:fake@production.invalid:3306/jw_ecommerce_testing',
      }),
      socket: refusedHarnessProbe({ DB_SOCKET: 'hostile-production-socket' }),
      wrongDatabase: refusedHarnessProbe({ DB_DATABASE: 'jw_ecommerce' }),
    };
    for (const refusal of [hostileProofs.remoteHost, hostileProofs.remoteUrl, hostileProofs.socket, hostileProofs.wrongDatabase]) {
      expect(refusal).toEqual({ exitCode: 1, code: 'unsafe_runtime_config' });
    }
    writeEvidence('hostile-environment-guard.json', hostileProofs);

    const probe = runHarness('probe');
    expect(probe).toMatchObject({
      ok: true,
      mode: 'probe',
      database: 'jw_ecommerce_testing',
      connectedDatabase: 'jw_ecommerce_testing',
      host: '127.0.0.1',
      port: 3306,
      urlPresent: false,
      socketPresent: false,
      queueConnection: 'mysql',
      sessionConnection: 'mysql',
    });
    writeEvidence('database-isolation-proof.json', probe);

    execFileSync(php, ['artisan', 'migrate', '--env=testing', '--force', '--no-interaction'], {
      cwd: repositoryRoot,
      env: acceptanceEnvironment(),
      stdio: 'ignore',
    });

    const failureBaselineBefore = runHarness('baseline');
    const failure = injectedSetupFailure();
    expect(failure).toEqual({ exitCode: 1, code: 'harness_exception' });
    expect(existsSync(statePath)).toBe(false);
    const failureBaselineAfter = runHarness('baseline');
    expect(failureBaselineAfter.counts).toEqual(failureBaselineBefore.counts);
    expect(failureBaselineAfter.templateFingerprint).toBe(failureBaselineBefore.templateFingerprint);
    writeEvidence('fixture-failure-atomicity.json', {
      injectionPoint: 'after_state_write',
      refusal: failure,
      countsUnchanged: true,
      templateFingerprintUnchanged: true,
      stateFileRemoved: true,
    });

    setup = runHarness('setup');
    writeEvidence('fixture-setup.json', setup);

    const cleanupGuard = runHarness('inject-cleanup-guard');
    expect(cleanupGuard.associationGuardId).toBeGreaterThan(0);
    const cleanupRefusal = refusedHarnessMode('cleanup');
    expect(cleanupRefusal).toEqual({ exitCode: 1, code: 'fixture_business_state_changed' });
    expect(existsSync(statePath)).toBe(true);
    const cleanupGuardRemoval = runHarness('remove-cleanup-guard');
    expect(cleanupGuardRemoval.associationStateRestored).toBe(true);
    writeEvidence('fixture-cleanup-fail-closed.json', {
      injectedTable: 'order_status_histories',
      cleanupRefusal,
      fixturePreserved: true,
      stateFilePreserved: true,
      exactInjectedRowRemoved: true,
      associationStateRestored: cleanupGuardRemoval.associationStateRestored,
    });

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
    if (setup !== null && cleanup?.countsRestored !== true) throw new Error('Task 9 smoke fixture cleanup did not restore the exact baseline.');
    if (setup !== null && cleanup?.businessStateUnchanged !== true) throw new Error('Task 9 smoke detected a business-state mutation before cleanup.');
    if (!portClear) throw new Error(`Task 9 did not prove port ${port} clear after stopping its owned server.`);
  });

  test('loads the protected Settings and Order notification surfaces without delivery actions', async ({ page }) => {
    const orderNumber = setup?.orderNumber;
    const orderId = setup?.orderId;
    expect(orderNumber).toBeTruthy();
    expect(orderId).toBeGreaterThan(0);
    for (const table of [
      'order_items',
      'order_addresses',
      'order_payments',
      'order_status_histories',
      'order_refunds',
      'order_tax_lines',
      'shipments',
      'shipment_events',
      'shipment_quotes',
      'order_payment_proof_events',
      'coupon_usages',
      'product_reviews',
      'product_review_media',
      'product_review_events',
      'payment_webhook_events',
      'order_tracking_verifications',
      'shipping_webhook_receipts',
      'easyparcel_reconciliation_tasks',
      'easyparcel_shipment_recoveries',
      'notification_dispatches',
    ]) {
      expect(setup?.ownedAssociations?.[table]).toEqual(expect.objectContaining({
        ids: expect.any(Array),
        fingerprint: expect.any(String),
      }));
    }

    const blockedExternalRequests: string[] = [];
    const privateNetworkLeaks: string[] = [];
    const requestLedger: RequestLedgerEntry[] = [];
    const responseBodyChecks: Promise<void>[] = [];
    await page.route('**/*', async route => {
      const url = route.request().url();
      const method = route.request().method().toUpperCase();
      if (url.includes(privateProviderUrl) || url.includes(privateProviderId)) privateNetworkLeaks.push(url);
      const parsed = new URL(url);
      const decision = classifyInternalRequest(url, method, orderId!);
      if (decision.classification === 'foreign-origin') {
        blockedExternalRequests.push(`${parsed.protocol}//${parsed.host}${parsed.pathname}`);
        await route.abort('blockedbyclient');
        return;
      }

      requestLedger.push({ method, ...decision });
      if (!decision.allowed) {
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

    const beforeBrowser = runHarness('snapshot');
    expect(beforeBrowser.businessFingerprint).toBe(setup?.businessFingerprint);
    expect(beforeBrowser.counts).toEqual(setup?.businessCounts);

    await page.setViewportSize({ width: 1440, height: 1000 });
    await login(page, '/admin/settings/email');
    await expect(page.getByRole('heading', { name: /Auto Email & WhatsApp Templates/ })).toBeVisible();
    await expect(page.getByRole('button', { name: /Payment Proof Rejected/ })).toBeVisible();
    await expect(page.getByRole('button', { name: /On Hold/ })).toContainText('DORMANT');
    await expect(page.getByText('Live Customer Preview')).toBeVisible();
    await expect(page.getByText('[JW Studio] Order #ORD-9845 Received - Please Complete Payment')).toBeVisible();
    await expect(page.getByText(/Dear Elena Rostova,/)).toBeVisible();
    await page.screenshot({ path: resolve(evidenceDirectory, 'settings-desktop-smoke.png'), fullPage: true });

    await page.setViewportSize({ width: 390, height: 844 });
    await expect(page.getByRole('heading', { name: /Auto Email & WhatsApp Templates/ })).toBeAttached();
    await expect(page.getByText('[JW Studio] Order #ORD-9845 Received - Please Complete Payment')).toBeAttached();
    await expect(page.getByText(/Dear Elena Rostova,/)).toBeAttached();
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
    await expect(orderModal).toContainText(`[JW Studio] Order #${orderNumber} Received - Please Complete Payment`);
    await expect(orderModal).toContainText('Dear Task Nine Smoke,');
    await expect(orderModal).not.toContainText('Loading current Email preview...');
    await expect(orderModal).not.toContainText('Current Email preview unavailable');
    await expect(orderModal).toContainText('Not connected');
    await page.screenshot({ path: resolve(evidenceDirectory, 'order-detail-desktop-smoke.png'), fullPage: true });

    await page.setViewportSize({ width: 390, height: 844 });
    await expect(orderModal).toBeAttached();
    await expect(orderModal).toContainText(`[JW Studio] Order #${orderNumber} Received - Please Complete Payment`);
    await expect(orderModal).toContainText('Dear Task Nine Smoke,');
    await page.screenshot({ path: resolve(evidenceDirectory, 'order-detail-mobile-smoke.png'), fullPage: true });

    await expect(page.locator('body')).not.toContainText(privateProviderUrl);
    await expect(page.locator('body')).not.toContainText(privateProviderId);
    await Promise.all(responseBodyChecks);
    expect(privateNetworkLeaks).toEqual([]);
    const blockedMutations = requestLedger.filter(entry => entry.classification === 'mutation');
    const executedDeliveryActions = requestLedger.filter(entry => entry.allowed && (
      (entry.method === 'POST' && /^\/admin\/api\/notification-dispatches\/[^/]+\/(retry|resend)$/.test(entry.path))
      || (entry.method === 'POST' && /^\/admin\/api\/orders\/\d+\/notifications\/send-current$/.test(entry.path))
      || (entry.method === 'POST' && /^\/admin\/api\/notification-templates\/[^/]+\/test$/.test(entry.path))
    ));
    expect(blockedMutations).toEqual([]);
    expect(executedDeliveryActions).toEqual([]);
    expect(requestLedger.filter(entry => entry.classification === 'login')).toHaveLength(1);

    const afterBrowser = runHarness('snapshot');
    expect(afterBrowser.counts).toEqual(beforeBrowser.counts);
    expect(afterBrowser.businessFingerprint).toBe(beforeBrowser.businessFingerprint);
    writeEvidence('business-state-read-only-proof.json', {
      before: beforeBrowser,
      after: afterBrowser,
      countsUnchanged: true,
      fingerprintUnchanged: true,
    });
    writeEvidence('browser-smoke-security.json', {
      scope: 'authenticated-read-only-smoke',
      deliveryActionsExecuted: executedDeliveryActions.length > 0,
      privateProviderDataExposed: false,
      privateNetworkLeaks,
      externalRequestsBlockedBeforeNetwork: [...new Set(blockedExternalRequests)].sort(),
      requestLedger,
      blockedMutationAttempts: blockedMutations,
      executedDeliveryActions,
      requestAllowlist: {
        acceptedOrigin,
        exactPreviewPath: `/admin/api/orders/${orderId}/notifications/preview`,
      },
      officialRoutes: ['/admin/settings/email', '/admin/orders'],
    });
  });
});
