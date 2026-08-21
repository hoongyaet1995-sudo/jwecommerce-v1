import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests',
  use: {
    baseURL: 'http://127.0.0.1:8510',
    channel: 'chrome',
    headless: true,
  },
  webServer: {
    command: 'npm run dev',
    url: 'http://127.0.0.1:8510',
    reuseExistingServer: process.env.PLAYWRIGHT_REUSE_EXISTING_SERVER === '1',
    timeout: 120_000,
  },
});
