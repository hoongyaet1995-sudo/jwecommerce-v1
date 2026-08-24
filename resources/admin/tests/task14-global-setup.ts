import { execFileSync, spawn } from 'node:child_process';
import { createHash, randomBytes } from 'node:crypto';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { connect } from 'node:net';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const testDirectory = fileURLToPath(new URL('.', import.meta.url));
const repositoryRoot = resolve(testDirectory, '../../..');
const evidenceDirectory = resolve(repositoryRoot, 'test-results/task-14/pre-cutover');
const harness = resolve(testDirectory, 'fixtures/task14-live-harness.php');
const php = 'C:\\xampp\\php\\php.exe';
const runtimeEvidencePath = resolve(evidenceDirectory, 'owned-runtime-evidence.json');

const portHasListener = (): Promise<boolean> => new Promise(resolvePort => {
  const socket = connect({host: '127.0.0.1', port: 8500});
  socket.once('connect', () => { socket.destroy(); resolvePort(true); });
  socket.once('error', () => { socket.destroy(); resolvePort(false); });
  socket.setTimeout(1000, () => { socket.destroy(); resolvePort(false); });
});

const waitForLogin = async (): Promise<void> => {
  for (let attempt = 0; attempt < 40; attempt += 1) {
    try {
      const response = await fetch('http://127.0.0.1:8500/admin/login');
      if (response.ok) return;
    } catch {}
    await new Promise(resolveWait => setTimeout(resolveWait, 250));
  }
  throw new Error('Owned Task 14 runtime did not make /admin/login ready on port 8500.');
};

const stopOwnedRuntime = async (pid: number): Promise<boolean> => {
  try { process.kill(pid); } catch {}
  for (let attempt = 0; attempt < 20 && await portHasListener(); attempt += 1) {
    await new Promise(resolveWait => setTimeout(resolveWait, 100));
  }
  return !(await portHasListener());
};

const sha256 = (path: string): string => createHash('sha256').update(readFileSync(path)).digest('hex');

const servedBuildHashes = (): Record<string, string> => {
  const manifestPath = resolve(repositoryRoot, 'public/build-admin/manifest.json');
  const manifest = JSON.parse(readFileSync(manifestPath, 'utf8')) as Record<string, {file?: string; css?: string[]}>;
  const paths = new Set<string>(['manifest.json']);
  for (const entry of Object.values(manifest)) {
    if (entry.file) paths.add(entry.file);
    for (const css of entry.css ?? []) paths.add(css);
  }
  return Object.fromEntries([...paths].sort().map(path => [
    `public/build-admin/${path}`,
    sha256(resolve(repositoryRoot, 'public/build-admin', path)),
  ]));
};

const setAcceptanceEnvironment = (): void => {
  const token = process.env.CMS_ACCEPTANCE_TOKEN ?? randomBytes(12).toString('hex');
  process.env.CMS_ACCEPTANCE_TOKEN = token;
  process.env.CMS_ACCEPTANCE_ADMIN_EMAIL ??= `task14-${token}@example.test`;
  process.env.CMS_ACCEPTANCE_ADMIN_PASSWORD ??= `${randomBytes(24).toString('base64url')}aA1!`;
  process.env.CMS_ACCEPTANCE_COUPON_CODE ??= `T14${token.toUpperCase()}`;
  process.env.CMS_ACCEPTANCE_PAGE_SLUG ??= `task-14-${token}`;
  process.env.CMS_ACCEPTANCE_CONTACT_EMAIL ??= `contact-${token}@example.test`;
  process.env.CMS_ACCEPTANCE_NEWSLETTER_EMAIL ??= `newsletter-${token}@example.test`;
};

export default async (): Promise<void> => {
  mkdirSync(evidenceDirectory, { recursive: true });
  if (await portHasListener()) {
    writeFileSync(runtimeEvidencePath, `${JSON.stringify({
      status: 'refused-pre-existing-listener',
      host: '127.0.0.1',
      port: 8500,
      checkedAt: new Date().toISOString(),
    }, null, 2)}\n`);
    throw new Error('Task 14 refuses to run because port 8500 already has a listener. No process was touched.');
  }

  const publicDirectory = resolve(repositoryRoot, 'public');
  const router = resolve(repositoryRoot, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php');
  const args = ['-S', '127.0.0.1:8500', router];
  const server = spawn(php, args, {
    cwd: publicDirectory,
    env: {...process.env},
    detached: true,
    stdio: 'ignore',
  });
  if (!server.pid) throw new Error('Task 14 could not obtain its owned PHP server PID.');
  server.unref();
  const serverPid = server.pid;
  process.env.CMS_ACCEPTANCE_RUNTIME_PID = String(serverPid);
  process.env.CMS_ACCEPTANCE_RUNTIME_LAUNCHER_PID = String(process.pid);
  writeFileSync(runtimeEvidencePath, `${JSON.stringify({
    status: 'started',
    host: '127.0.0.1',
    port: 8500,
    listenerPreflight: false,
    launcherPid: process.pid,
    serverPid,
    executable: php,
    arguments: args,
    cwd: publicDirectory,
    startedAt: new Date().toISOString(),
    servedBuildHashes: servedBuildHashes(),
  }, null, 2)}\n`);

  try {
    await waitForLogin();
    setAcceptanceEnvironment();
    const raw = execFileSync(php, [harness, 'setup'], {
      cwd: repositoryRoot,
      env: { ...process.env, APP_URL: 'http://127.0.0.1:8500' },
      encoding: 'utf8',
    });
    const evidence = JSON.parse(raw) as {
      before: {hash: string};
      owned: {ids: {category: number; collection: number; product: number; coupon: number}};
    };
    process.env.CMS_ACCEPTANCE_CATEGORY_ID = String(evidence.owned.ids.category);
    process.env.CMS_ACCEPTANCE_COLLECTION_ID = String(evidence.owned.ids.collection);
    process.env.CMS_ACCEPTANCE_PRODUCT_ID = String(evidence.owned.ids.product);
    process.env.CMS_ACCEPTANCE_COUPON_ID = String(evidence.owned.ids.coupon);
    process.env.CMS_ACCEPTANCE_BASELINE_DIGEST = evidence.before.hash;
    writeFileSync(resolve(evidenceDirectory, 'setup-evidence.json'), `${JSON.stringify(evidence, null, 2)}\n`);
  } catch (error) {
    const portClearAfterFailure = await stopOwnedRuntime(serverPid);
    const runtime = JSON.parse(readFileSync(runtimeEvidencePath, 'utf8')) as Record<string, unknown>;
    writeFileSync(runtimeEvidencePath, `${JSON.stringify({
      ...runtime,
      status: portClearAfterFailure ? 'setup-failed-owned-runtime-stopped' : 'setup-failed-stop-not-proven',
      setupFailedAt: new Date().toISOString(),
      ownedServerPidAbsent: (() => {
        try { process.kill(serverPid, 0); return false; } catch { return true; }
      })(),
      portClearAfterFailure,
    }, null, 2)}\n`);
    throw error;
  }
};
