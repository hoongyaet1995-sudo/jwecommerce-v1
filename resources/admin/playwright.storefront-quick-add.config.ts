import { defineConfig } from '@playwright/test';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const directory = fileURLToPath(new URL('.', import.meta.url));

export default defineConfig({
  testDir: './tests',
  testMatch: 'storefront-*.spec.ts',
  outputDir: resolve(directory, '../../test-results/storefront-quick-add/playwright-output'),
  timeout: 60_000,
  expect: { timeout: 15_000 },
  workers: 1,
  use: {
    baseURL: 'http://127.0.0.1:8500',
    channel: 'chrome',
    headless: true,
    viewport: { width: 1440, height: 980 },
  },
});
