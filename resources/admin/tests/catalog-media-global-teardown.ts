import { execFileSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const testDirectory = fileURLToPath(new URL('.', import.meta.url));
const root = resolve(testDirectory, '../../..');
const evidence = resolve(root, 'test-results/catalog-media-acceptance');
const harness = resolve(testDirectory, 'fixtures/catalog-media-acceptance-harness.php');

export default async (): Promise<void> => {
  try {
    const cleanup = JSON.parse(execFileSync('C:\\xampp\\php\\php.exe', [harness, 'cleanup'], {cwd: root, env: {...process.env}, encoding: 'utf8'}));
    writeFileSync(resolve(evidence, 'cleanup.json'), `${JSON.stringify(cleanup, null, 2)}\n`);
    if (cleanup.ownedRemaining !== 0) throw new Error('Catalog acceptance cleanup left owned records.');
  } finally {
    const pid = Number(process.env.CATALOG_ACCEPTANCE_SERVER_PID ?? 0);
    if (pid > 0) { try { process.kill(pid); } catch {} }
  }
};
