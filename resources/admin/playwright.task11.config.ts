import { defineConfig } from '@playwright/test';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const configDirectory = fileURLToPath(new URL('.', import.meta.url));
const repositoryRoot = resolve(configDirectory, '../..');

export default defineConfig({
  testDir: './tests',
  testMatch: 'task11-source-partials.spec.ts',
  globalSetup: './tests/task11-global-setup.ts',
  globalTeardown: './tests/task11-global-teardown.ts',
  timeout: 90_000,
  workers: 1,
  use: {
    baseURL: 'http://127.0.0.1:8525',
    channel: 'chrome',
    headless: true,
    viewport: { width: 1600, height: 1000 },
  },
  webServer: {
    command: 'C:\\xampp\\php\\php.exe -S 127.0.0.1:8525 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
    cwd: resolve(repositoryRoot, 'public'),
    url: 'http://127.0.0.1:8525/admin/login',
    reuseExistingServer: process.env.PLAYWRIGHT_REUSE_EXISTING_SERVER === '1',
    timeout: 30_000,
    env: {
      ...process.env,
      APP_ENV: 'testing',
      APP_URL: 'http://127.0.0.1:8525',
      DB_CONNECTION: 'sqlite',
      DB_DATABASE: resolve(repositoryRoot, 'test-results/task-11/task11-browser.sqlite'),
      SESSION_DRIVER: 'cookie',
      CACHE_STORE: 'array',
      QUEUE_CONNECTION: 'sync',
    },
  },
});
