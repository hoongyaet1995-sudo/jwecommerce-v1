import { defineConfig } from '@playwright/test';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const configDirectory = fileURLToPath(new URL('.', import.meta.url));
const repositoryRoot = resolve(configDirectory, '../..');

export default defineConfig({
  testDir: './tests',
  testMatch: 'cms-page-builder.spec.ts',
  globalSetup: './tests/task14-global-setup.ts',
  globalTeardown: './tests/task14-global-teardown.ts',
  outputDir: resolve(repositoryRoot, 'test-results/task-14/pre-cutover/playwright-output'),
  timeout: 240_000,
  expect: { timeout: 15_000 },
  use: {
    baseURL: 'http://127.0.0.1:8500',
    channel: 'chrome',
    headless: true,
    viewport: { width: 1600, height: 1000 },
    actionTimeout: 15_000,
  },
  workers: 1,
});
