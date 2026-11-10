#!/usr/bin/env node

'use strict';

const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { privateFixturePathFor } = require('../tests/browser/private-fixture-path.cjs');
const { readReport, reportPathFor, writeMissingReport } = require('./explorer-report-contract.cjs');

if (process.platform === 'android') {
  console.error('AUTH_EXPLORER=UNAVAILABLE_LOCAL_CHROMIUM');
  console.error('Run this bounded Chromium journey on supported desktop/CI infrastructure; no Android browser installation was attempted.');
  process.exit(2);
}

const root = path.resolve(__dirname, '..');
const commitResult = spawnSync('git', ['rev-parse', 'HEAD'], { cwd: root, encoding: 'utf8' });
const testedCommit = commitResult.status === 0 ? commitResult.stdout.trim() : 'UNAVAILABLE';
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

// This private sidecar handoff lets the browser run isolated CLI backup/restore
// checks without placing fixture locations or records in uploadable evidence.
try {
  fs.rmSync(privateFixturePathFor(evidenceDirectory), { force: true });
} catch {}

const reportPath = path.join(evidenceDirectory, 'account-explorer.json');
if (!fs.existsSync(reportPath)) {
  try {
    const fallback = writeMissingReport({
      evidenceDirectory,
      kind: 'account',
      testedCommit,
      mode,
      seed,
      processResult: result,
    });
    console.error('AUTH_EXPLORER_REPORT_DIAGNOSTIC=' + fallback.state);
  } catch {
    console.error('AUTH_EXPLORER_REPORT_DIAGNOSTIC=FALLBACK_WRITE_FAILED');
  }
}
const outcome = readReport({
  evidenceDirectory,
  kind: 'account',
  expectedCommit: testedCommit,
  expectedMode: mode,
  expectedSeed: seed,
});
console.log('AUTH_EXPLORER=' + (outcome.ok ? outcome.summary.status : outcome.state));
console.log('AUTH_EXPLORER_REPORT=' + reportPathFor(evidenceDirectory, 'account'));
console.log('AUTH_EXPLORER_REPORT_CHECK=' + outcome.code);
if (outcome.summary) {
  console.log('AUTH_EXPLORER_SCENARIOS=' + outcome.summary.scenarioCount);
  console.log('AUTH_EXPLORER_COMMIT=' + outcome.summary.testedCommit);
}
if (!outcome.ok) process.exit(result.status || 1);
process.exit(result.status ?? 1);
