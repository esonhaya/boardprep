#!/usr/bin/env node

'use strict';

const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');

if (process.platform === 'android') {
  console.error('SITE_EXPLORER=UNAVAILABLE_LOCAL_CHROMIUM');
  console.error('Run npm run test:explore:smoke or npm run test:explore:full on supported desktop/CI Chromium. No Android browser installation was attempted.');
  process.exit(2);
}

const root = path.resolve(__dirname, '..');
const modeArg = process.argv.find((argument) => argument.startsWith('--mode='));
const seedArg = process.argv.find((argument) => argument.startsWith('--seed='));
const mode = modeArg ? modeArg.split('=')[1] : 'full';
const seed = seedArg ? seedArg.split('=')[1] : (process.env.BOARDPREP_EXPLORER_SEED || '691');
if (!['smoke', 'full'].includes(mode) || !/^\d{1,10}$/.test(String(seed))) {
  console.error('Usage: node tools/run-site-explorer.cjs --mode=smoke|full [--seed=INTEGER]');
  process.exit(2);
}

let playwrightCli;
try {
  playwrightCli = path.join(path.dirname(require.resolve('@playwright/test/package.json')), 'cli.js');
} catch (error) {
  console.error(`SITE_EXPLORER=DEPENDENCY_MISSING ${String(error.message || error)}`);
  process.exit(2);
}

const evidenceDirectory = path.resolve(process.env.BOARDPREP_EXPLORER_EVIDENCE_DIR
  || fs.mkdtempSync(path.join(os.tmpdir(), 'boardprep-site-explorer-')));
fs.mkdirSync(evidenceDirectory, { recursive: true });
const env = {
  ...process.env,
  BOARDPREP_RUN_EXPLORER: '1',
  BOARDPREP_EXPLORER_MODE: mode,
  BOARDPREP_EXPLORER_SEED: String(seed),
  BOARDPREP_EXPLORER_EVIDENCE_DIR: evidenceDirectory,
  BOARDPREP_BROWSER_EVIDENCE_DIR: evidenceDirectory,
  BOARDPREP_EXPLORER_MAX_PAGES: process.env.BOARDPREP_EXPLORER_MAX_PAGES || (mode === 'smoke' ? '60' : '100'),
  BOARDPREP_EXPLORER_MAX_ACTIONS: process.env.BOARDPREP_EXPLORER_MAX_ACTIONS || (mode === 'smoke' ? '180' : '320'),
  BOARDPREP_EXPLORER_MAX_DURATION_MS: process.env.BOARDPREP_EXPLORER_MAX_DURATION_MS || (mode === 'smoke' ? '180000' : '540000'),
};
const args = [
  playwrightCli,
  'test',
  '--config=tests/browser/playwright.config.cjs',
  '--project=desktop',
  'tests/browser/explorer/site-explorer.spec.cjs',
];
const result = spawnSync(process.execPath, args, {
  cwd: root,
  env,
  stdio: 'inherit',
});

const jsonPath = path.join(evidenceDirectory, 'site-explorer.json');
if (fs.existsSync(jsonPath)) {
  try {
    const report = JSON.parse(fs.readFileSync(jsonPath, 'utf8'));
    console.log(`SITE_EXPLORER=${report.status}`);
    console.log(`SITE_EXPLORER_REPORT=${jsonPath}`);
    console.log(`SITE_EXPLORER_COMMIT=${report.tested_commit || 'UNVERIFIED'}`);
  } catch (error) {
    console.error(`SITE_EXPLORER_REPORT=INVALID ${String(error.message || error)}`);
  }
} else {
  console.error(`SITE_EXPLORER_REPORT=MISSING expected ${jsonPath}`);
}
let exitStatus = result.status ?? 1;
console.log('SITE_EXPLORER_SUITE=authentication');
const authResult = spawnSync(process.execPath, [
  playwrightCli,
  'test',
  '--config=tests/browser/playwright.config.cjs',
  '--project=mobile',
  'tests/browser/accounts/account-auth.spec.cjs',
], {
  cwd: root,
  env: { ...env, BOARDPREP_AUTH_EXPLORER: '1' },
  stdio: 'inherit',
});
if ((authResult.status ?? 1) !== 0) exitStatus = authResult.status ?? 1;

const authJsonPath = path.join(evidenceDirectory, 'account-explorer.json');
if (fs.existsSync(authJsonPath)) {
  try {
    const report = JSON.parse(fs.readFileSync(authJsonPath, 'utf8'));
    const commit = spawnSync('git', ['rev-parse', 'HEAD'], { cwd: root, encoding: 'utf8' }).stdout.trim();
    console.log('AUTH_EXPLORER=' + report.status);
    console.log('AUTH_EXPLORER_SCENARIOS=' + (report.scenarios?.length ?? 0));
    console.log('AUTH_EXPLORER_REPORT=' + authJsonPath);
    console.log('AUTH_EXPLORER_COMMIT=' + (report.tested_commit || 'UNVERIFIED'));
    if (report.status !== 'PASS' || report.tested_commit !== commit) exitStatus = exitStatus || 1;
  } catch (error) {
    console.error('AUTH_EXPLORER_REPORT=INVALID ' + String(error.message || error));
    exitStatus = exitStatus || 1;
  }
} else {
  console.error('AUTH_EXPLORER_REPORT=MISSING expected ' + authJsonPath);
  exitStatus = exitStatus || 1;
}
process.exit(exitStatus);
