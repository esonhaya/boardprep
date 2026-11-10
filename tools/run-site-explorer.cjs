#!/usr/bin/env node

'use strict';

const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { readReport, reportPathFor, writeMissingReport } = require('./explorer-report-contract.cjs');

if (process.platform === 'android') {
  console.error('SITE_EXPLORER=UNAVAILABLE_LOCAL_CHROMIUM');
  console.error('Run npm run test:explore:smoke or npm run test:explore:full on supported desktop/CI Chromium. No Android browser installation was attempted.');
  process.exit(2);
}

const root = path.resolve(__dirname, '..');
const commitResult = spawnSync('git', ['rev-parse', 'HEAD'], { cwd: root, encoding: 'utf8' });
const testedCommit = commitResult.status === 0 ? commitResult.stdout.trim() : 'UNAVAILABLE';
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

let exitStatus = result.status ?? 1;
const reportOutcome = (kind, processResult, prefix) => {
  let outcome = readReport({
    evidenceDirectory,
    kind,
    expectedCommit: testedCommit,
    expectedMode: mode,
    expectedSeed: seed,
  });
  if (outcome.state === 'MISSING') {
    try {
      const fallback = writeMissingReport({
        evidenceDirectory,
        kind,
        testedCommit,
        mode,
        seed,
        processResult,
      });
      console.error(`${prefix}_REPORT_DIAGNOSTIC=${fallback.state}`);
      outcome = readReport({
        evidenceDirectory,
        kind,
        expectedCommit: testedCommit,
        expectedMode: mode,
        expectedSeed: seed,
      });
    } catch {
      console.error(`${prefix}_REPORT_DIAGNOSTIC=FALLBACK_WRITE_FAILED`);
    }
  }
  const reportPath = reportPathFor(evidenceDirectory, kind);
  console.log(`${prefix}=${outcome.ok ? outcome.summary.status : outcome.state}`);
  console.log(`${prefix}_REPORT=${reportPath}`);
  console.log(`${prefix}_REPORT_CHECK=${outcome.code}`);
  if (outcome.summary) {
    console.log(`${prefix}_COMMIT=${outcome.summary.testedCommit}`);
    console.log(`${prefix}_ROUTES=${outcome.summary.routeCount}`);
    if (kind === 'site') console.log(`${prefix}_BOARDS=${outcome.summary.boardCount}`);
    if (kind === 'account') console.log(`${prefix}_SCENARIOS=${outcome.summary.scenarioCount}`);
  }
  if (!outcome.ok) exitStatus = exitStatus || 1;
  return outcome;
};
reportOutcome('site', result, 'SITE_EXPLORER');

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

reportOutcome('account', authResult, 'AUTH_EXPLORER');
process.exit(exitStatus);
