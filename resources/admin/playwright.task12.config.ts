import { defineConfig } from '@playwright/test';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const configDirectory = fileURLToPath(new URL('.', import.meta.url));
const repositoryRoot = resolve(configDirectory, '../..');
const databasePath = resolve(repositoryRoot, 'test-results/task-12/task12-browser.sqlite');

export default defineConfig({
  testDir: './tests', testMatch: 'task12-forms.spec.ts', globalSetup: './tests/task12-global-setup.ts', globalTeardown: './tests/task12-global-teardown.ts', timeout: 90_000, workers: 1,
  use: {baseURL: 'http://127.0.0.1:8537', channel: 'chrome', headless: true, viewport: {width: 1600, height: 1000}},
  webServer: {
    command: 'C:\\xampp\\php\\php.exe -S 127.0.0.1:8537 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php', cwd: resolve(repositoryRoot, 'public'), url: 'http://127.0.0.1:8537/admin/login', reuseExistingServer: false, timeout: 30_000,
    env: {...process.env, APP_ENV: 'testing', APP_URL: 'http://127.0.0.1:8537', DB_CONNECTION: 'sqlite', DB_DATABASE: databasePath, SESSION_DRIVER: 'cookie', CACHE_STORE: 'database', QUEUE_CONNECTION: 'sync'},
  },
});
