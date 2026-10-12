const { defineConfig, devices } = require('@playwright/test');
const path = require('node:path');

const repositoryRoot = path.resolve(__dirname, '../..');

module.exports = defineConfig({
  testDir: __dirname,
  testMatch: /.*\.spec\.cjs$/,
  timeout: 90_000,
  expect: {
    timeout: 10_000,
  },
  fullyParallel: false,
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI
    ? [
      ['dot'],
      ['json', { outputFile: path.join(repositoryRoot, 'test-results', 'browser-results.json') }],
      ['html', { outputFolder: path.join(repositoryRoot, 'playwright-report'), open: 'never' }],
    ]
    : [
      ['list'],
      ['json', { outputFile: path.join(repositoryRoot, 'test-results', 'browser-results.json') }],
    ],
  use: {
    baseURL: 'http://127.0.0.1:4173',
    trace: 'off',
    screenshot: 'only-on-failure',
    video: 'off',
  },
  webServer: {
    command: 'node server.cjs',
    cwd: __dirname,
    url: 'http://127.0.0.1:4173/',
    timeout: 120_000,
    reuseExistingServer: false,
    stdout: 'pipe',
    stderr: 'pipe',
  },
  projects: [
    {
      name: 'mobile',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 390, height: 844 },
        isMobile: true,
        hasTouch: true,
      },
    },
    {
      name: 'desktop',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1366, height: 768 },
      },
    },
  ],
});
