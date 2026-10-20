const { defineConfig, devices } = require('@playwright/test');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

const evidenceDirectory = path.resolve(
  process.env.BOARDPREP_BROWSER_EVIDENCE_DIR
    || path.join(os.tmpdir(), `boardprep-browser-evidence-${process.pid}`)
);
const resultsDirectory = path.join(evidenceDirectory, 'test-results');
fs.mkdirSync(resultsDirectory, { recursive: true });

module.exports = defineConfig({
  testDir: __dirname,
  testMatch: /.*\.spec\.cjs$/,
  testIgnore: process.env.BOARDPREP_RUN_EXPLORER === '1' ? [] : ['**/explorer/**'],
  timeout: 90_000,
  expect: {
    timeout: 10_000,
  },
  fullyParallel: false,
  workers: 1,
  // A retry can hide a real intermittent learner failure. Capture it for
  // diagnosis and let the job fail so the original result stays visible.
  retries: 0,
  outputDir: resultsDirectory,
  reporter: process.env.CI
    ? [
      ['dot'],
      ['json', { outputFile: path.join(resultsDirectory, 'browser-results.json') }],
      ['html', { outputFolder: path.join(evidenceDirectory, 'playwright-report'), open: 'never' }],
    ]
    : [
      ['list'],
      ['json', { outputFile: path.join(resultsDirectory, 'browser-results.json') }],
    ],
  use: {
    baseURL: 'http://127.0.0.1:4173',
    trace: 'retain-on-failure',
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
