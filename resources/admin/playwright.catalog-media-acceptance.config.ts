import { defineConfig } from '@playwright/test';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const directory = fileURLToPath(new URL('.', import.meta.url));
export default defineConfig({
  testDir: './tests', testMatch: 'catalog-media-acceptance.spec.ts',
  globalSetup: './tests/catalog-media-global-setup.ts', globalTeardown: './tests/catalog-media-global-teardown.ts',
  outputDir: resolve(directory, '../../test-results/catalog-media-acceptance/playwright-output'),
  timeout: 120_000, expect: {timeout: 15_000}, workers: 1,
  use: {baseURL: 'http://127.0.0.1:8542', channel: 'chrome', headless: true, viewport: {width: 1600, height: 1000}},
});
