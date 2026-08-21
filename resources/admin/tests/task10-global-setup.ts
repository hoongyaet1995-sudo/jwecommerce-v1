import { mkdirSync, writeFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const testDirectory = fileURLToPath(new URL('.', import.meta.url));
const repositoryRoot = resolve(testDirectory, '../../..');
const artifactDirectory = resolve(repositoryRoot, 'test-results/task-10');
const databasePath = resolve(artifactDirectory, 'task10-browser.sqlite');
const php = 'C:\\xampp\\php\\php.exe';
const environment = {
  ...process.env,
  APP_ENV: 'testing',
  APP_URL: 'http://127.0.0.1:8524',
  DB_CONNECTION: 'sqlite',
  DB_DATABASE: databasePath,
  SESSION_DRIVER: 'cookie',
  CACHE_STORE: 'array',
  QUEUE_CONNECTION: 'sync',
};

const runPhp = (arguments_: string[]) => {
  const result = spawnSync(php, arguments_, {
    cwd: repositoryRoot,
    env: environment,
    encoding: 'utf8',
  });
  if (result.status !== 0) {
    throw new Error([result.stdout, result.stderr].filter(Boolean).join('\n'));
  }
};

export default async () => {
  mkdirSync(artifactDirectory, { recursive: true });
  writeFileSync(databasePath, '');
  runPhp(['artisan', 'migrate', '--force']);
  runPhp([resolve(testDirectory, 'fixtures/task10-browser-seed.php')]);
};
