import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: '.',
  testMatch: '*.spec.mjs',
  use: { baseURL: process.env.BASE_URL, ignoreHTTPSErrors: true },
  projects: [
    { name: 'desktop', use: { viewport: { width: 1280, height: 900 } } },
    { name: 'zoom-200', use: { viewport: { width: 640, height: 450 }, deviceScaleFactor: 2 } },
    { name: 'reflow-320', use: { viewport: { width: 320, height: 640 } } },
    { name: 'forced-colors', use: { viewport: { width: 1280, height: 900 }, forcedColors: 'active' } },
  ],
});
