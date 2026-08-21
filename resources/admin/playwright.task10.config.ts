import { defineConfig } from '@playwright/test';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const configDirectory = fileURLToPath(new URL('.', import.meta.url));
const repositoryRoot = resolve(configDirectory, '../..');

export default defineConfig({
  testDir: './tests',
  testMatch: 'shared-page-renderer.spec.ts',
  globalSetup: './tests/task10-global-setup.ts',
  globalTeardown: './tests/task10-global-teardown.ts',
  timeout: 60_000,
  workers: 1,
  use: {
    baseURL: 'http://127.0.0.1:8524',
    channel: 'chrome',
    headless: true,
    viewport: { width: 1600, height: 1000 },
  },
  webServer: {
    command: 'C:\\xampp\\php\\php.exe -S 127.0.0.1:8524 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
    cwd: resolve(repositoryRoot, 'public'),
    url: 'http://127.0.0.1:8524/admin/login',
    reuseExistingServer: false,
    timeout: 30_000,
    env: {
      ...process.env,
      APP_ENV: 'testing',
      APP_URL: 'http://127.0.0.1:8524',
      DB_CONNECTION: 'sqlite',
      DB_DATABASE: resolve(repositoryRoot, 'test-results/task-10/task10-browser.sqlite'),
      SESSION_DRIVER: 'cookie',
      CACHE_STORE: 'array',
      QUEUE_CONNECTION: 'sync',
    },
  },
});
