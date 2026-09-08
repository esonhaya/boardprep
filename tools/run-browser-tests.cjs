#!/usr/bin/env node

const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');

if (process.platform === 'android') {
  console.log('BROWSER_ACCEPTANCE=DEPENDENCY_MISSING');
  console.log('Playwright Chromium is not supported by the local Termux Node runtime.');
  process.exit(process.env.CI ? 1 : 0);
}

let cli;
try {
  const packageRoot = path.dirname(require.resolve('@playwright/test/package.json'));
  const { chromium } = require('@playwright/test');
  if (!fs.existsSync(chromium.executablePath())) {
    console.log('BROWSER_ACCEPTANCE=DEPENDENCY_MISSING');
    console.log('Install Chromium with: npm run test:browser:install');
    process.exit(process.env.CI ? 1 : 0);
  }
  cli = path.join(packageRoot, 'cli.js');
} catch (error) {
  console.log('BROWSER_ACCEPTANCE=DEPENDENCY_MISSING');
  console.log(String(error && error.message ? error.message : error));
  process.exit(process.env.CI ? 1 : 0);
}

const result = spawnSync(process.execPath, [
  cli,
  'test',
  '--config=tests/browser/playwright.config.cjs',
  ...process.argv.slice(2),
], {
  cwd: path.resolve(__dirname, '..'),
  env: {
    ...process.env,
    ...(process.argv.includes('--update-snapshots') || process.env.VISUAL_REGRESSION === '1'
      ? { VISUAL_REGRESSION: '1' }
      : {}),
  },
  stdio: 'inherit',
});

process.exit(result.status ?? 1);
