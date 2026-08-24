import { defineConfig } from '@playwright/test';

const sourceRoot = 'C:/Users/User/AppData/Local/Temp/jw-ecommerce-dashboard-7-source';

export default defineConfig({
  testDir: './tests',
  testMatch: 'catalog-media-source-baseline.spec.ts',
  timeout: 90_000,
  workers: 1,
  use: {
    baseURL: 'http://127.0.0.1:8539',
    channel: 'chrome',
    headless: true,
    viewport: { width: 1440, height: 1100 },
  },
  webServer: {
    command: 'npm.cmd run dev -- --host 127.0.0.1 --port 8539',
    cwd: sourceRoot,
    url: 'http://127.0.0.1:8539',
    reuseExistingServer: process.env.PLAYWRIGHT_REUSE_EXISTING_SERVER === '1',
    timeout: 30_000,
  },
});
