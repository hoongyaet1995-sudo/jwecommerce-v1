import { execFileSync, spawn } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { mkdirSync, writeFileSync } from 'node:fs';
import { connect } from 'node:net';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const testDirectory = fileURLToPath(new URL('.', import.meta.url));
const root = resolve(testDirectory, '../../..');
const evidence = resolve(root, 'test-results/catalog-media-acceptance');
const harness = resolve(testDirectory, 'fixtures/catalog-media-acceptance-harness.php');
const php = 'C:\\xampp\\php\\php.exe';
const port = 8542;

const listener = () => new Promise<boolean>(resolveListener => {
  const socket = connect({host: '127.0.0.1', port});
  socket.once('connect', () => { socket.destroy(); resolveListener(true); });
  socket.once('error', () => { socket.destroy(); resolveListener(false); });
});

export default async (): Promise<void> => {
  mkdirSync(evidence, {recursive: true});
  if (await listener()) throw new Error(`Catalog acceptance refuses to run: port ${port} already has a listener.`);
  const token = randomBytes(12).toString('hex');
  Object.assign(process.env, {
    CATALOG_ACCEPTANCE_TOKEN: token,
    CATALOG_ACCEPTANCE_ADMIN_EMAIL: `catalog-${token}@example.test`,
    CATALOG_ACCEPTANCE_ADMIN_PASSWORD: `${randomBytes(18).toString('base64url')}Aa1!`,
  });
  const server = spawn(php, ['-d', 'display_errors=0', '-S', `127.0.0.1:${port}`, resolve(root, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], {
    cwd: resolve(root, 'public'), env: {...process.env}, detached: true, stdio: 'ignore',
  });
  if (!server.pid) throw new Error('Catalog acceptance could not start its owned PHP server.');
  server.unref(); process.env.CATALOG_ACCEPTANCE_SERVER_PID = String(server.pid);
  for (let attempt = 0; attempt < 40 && !(await listener()); attempt += 1) await new Promise(done => setTimeout(done, 250));
  if (!(await listener())) throw new Error('Catalog acceptance runtime did not become ready.');
  const setup = JSON.parse(execFileSync(php, [harness, 'setup'], {cwd: root, env: {...process.env}, encoding: 'utf8'}));
  process.env.CATALOG_ACCEPTANCE_CATEGORY_ID = String(setup.owned.ids.category);
  process.env.CATALOG_ACCEPTANCE_COLLECTION_ID = String(setup.owned.ids.collection);
  process.env.CATALOG_ACCEPTANCE_PRODUCT_ID = String(setup.owned.ids.product);
  writeFileSync(resolve(evidence, 'setup.json'), `${JSON.stringify(setup, null, 2)}\n`);
};
