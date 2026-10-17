#!/usr/bin/env node

'use strict';

const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');

if (process.platform === 'android') {
  console.error('AUTH_EXPLORER=UNAVAILABLE_LOCAL_CHROMIUM');
  console.error('Run this bounded Chromium journey on supported desktop/CI infrastructure; no Android browser installation was attempted.');
  process.exit(2);
}

const root = path.resolve(__dirname, '..');
const modeArg = process.argv.find((argument) => argument.startsWith('--mode='));
const seedArg = process.argv.find((argument) => argument.startsWith('--seed='));
const mode = modeArg ? modeArg.split('=')[1] : 'full';
const seed = seedArg ? seedArg.split('=')[1] : (process.env.BOARDPREP_EXPLORER_SEED || '692');
if (!['smoke', 'full'].includes(mode) || !/^\d{1,10}$/.test(String(seed))) {
  console.error('Usage: node tools/run-account-explorer.cjs --mode=smoke|full [--seed=INTEGER]');
  process.exit(2);
}

let cli;
try {
  cli = path.join(path.dirname(require.resolve('@playwright/test/package.json')), 'cli.js');
} catch (error) {
  console.error('AUTH_EXPLORER=DEPENDENCY_MISSING ' + String(error.message || error));
  process.exit(2);
}

const evidenceDirectory = path.resolve(process.env.BOARDPREP_EXPLORER_EVIDENCE_DIR
  || fs.mkdtempSync(path.join(os.tmpdir(), 'boardprep-account-explorer-')));
fs.mkdirSync(evidenceDirectory, { recursive: true });
const env = {
  ...process.env,
  BOARDPREP_RUN_EXPLORER: '1',
  BOARDPREP_AUTH_EXPLORER: '1',
  BOARDPREP_EXPLORER_MODE: mode,
  BOARDPREP_EXPLORER_SEED: String(seed),
  BOARDPREP_BROWSER_EVIDENCE_DIR: evidenceDirectory,
  BOARDPREP_EXPLORER_EVIDENCE_DIR: evidenceDirectory,
};
const result = spawnSync(process.execPath, [
  cli,
  'test',
  '--config=tests/browser/playwright.config.cjs',
  '--project=mobile',
  'tests/browser/accounts/account-auth.spec.cjs',
], {
  cwd: root,
  env,
  stdio: 'inherit',
});

const reportPath = path.join(evidenceDirectory, 'account-explorer.json');
if (!fs.existsSync(reportPath)) {
  console.error('AUTH_EXPLORER_REPORT=MISSING ' + reportPath);
  process.exit(result.status ?? 1);
}
try {
  const report = JSON.parse(fs.readFileSync(reportPath, 'utf8'));
  const commit = spawnSync('git', ['rev-parse', 'HEAD'], { cwd: root, encoding: 'utf8' }).stdout.trim();
  console.log('AUTH_EXPLORER=' + report.status);
  console.log('AUTH_EXPLORER_SCENARIOS=' + (report.scenarios?.length ?? 0));
  console.log('AUTH_EXPLORER_REPORT=' + reportPath);
  console.log('AUTH_EXPLORER_MARKDOWN=' + path.join(evidenceDirectory, 'account-explorer.md'));
  console.log('AUTH_EXPLORER_COMMIT=' + (report.tested_commit || 'UNVERIFIED'));
  if (report.status !== 'PASS' || report.tested_commit !== commit) process.exit(result.status || 1);
} catch (error) {
  console.error('AUTH_EXPLORER_REPORT=INVALID ' + String(error.message || error));
  process.exit(1);
}
process.exit(result.status ?? 1);
