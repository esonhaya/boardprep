'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const test = require('node:test');
const { readReport, reportPathFor, writeMissingReport } = require('../../../tools/explorer-report-contract.cjs');

const COMMIT = 'a'.repeat(40);

function makeEvidenceDirectory(t) {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'boardprep-explorer-report-test-'));
  t.after(() => fs.rmSync(root, { recursive: true, force: true }));
  const evidence = path.join(root, 'evidence');
  fs.mkdirSync(evidence, { mode: 0o700 });
  return evidence;
}

test('missing explorer report produces a private diagnostic that cannot count as acceptance', (t) => {
  const evidenceDirectory = makeEvidenceDirectory(t);
  const written = writeMissingReport({
    evidenceDirectory,
    kind: 'account',
    testedCommit: COMMIT,
    mode: 'full',
    seed: '691',
    processResult: { status: 1, signal: null },
  });
  assert.deepEqual(written, { written: true, state: 'FALLBACK_WRITTEN' });

  const target = reportPathFor(evidenceDirectory, 'account');
  const mode = fs.lstatSync(target).mode & 0o777;
  assert.equal(mode & 0o077, 0, 'fallback evidence must be private to the test runner account');
  const report = JSON.parse(fs.readFileSync(target, 'utf8'));
  assert.equal(report.status, 'INCOMPLETE');
  assert.equal(report.diagnostic_code, 'PLAYWRIGHT_REPORT_NOT_EMITTED');
  assert.equal(report.tested_commit, COMMIT);
  assert.equal(report.process_exit_status, 1);
  assert.match(report.reason, /diagnostic only/);
  assert.doesNotMatch(JSON.stringify(report), /password|cookie|token|fixture path/i);

  const validation = readReport({
    evidenceDirectory,
    kind: 'account',
    expectedCommit: COMMIT,
    expectedMode: 'full',
    expectedSeed: 691,
  });
  assert.equal(validation.ok, false);
  assert.equal(validation.state, 'INCOMPLETE');
});

test('missing-report fallback never overwrites a partial or pre-existing report', (t) => {
  const evidenceDirectory = makeEvidenceDirectory(t);
  const target = reportPathFor(evidenceDirectory, 'site');
  fs.writeFileSync(target, '{partial evidence', { mode: 0o600 });

  const result = writeMissingReport({
    evidenceDirectory,
    kind: 'site',
    testedCommit: COMMIT,
    mode: 'smoke',
    seed: '691',
    processResult: { status: 1 },
  });
  assert.deepEqual(result, { written: false, state: 'EXISTING_REPORT_PRESERVED' });
  assert.equal(fs.readFileSync(target, 'utf8'), '{partial evidence');
});

test('site explorer accepts only exact-commit complete board coverage', (t) => {
  const evidenceDirectory = makeEvidenceDirectory(t);
  const target = reportPathFor(evidenceDirectory, 'site');
  const report = {
    schema_version: 1,
    report_type: 'BOUNDED_SITE_EXPLORER',
    status: 'PASS_WITH_REVIEW_CUES',
    tested_commit: COMMIT,
    mode: 'full',
    seed: 691,
    coverage_requirements: { required_board_count: 10 },
    routes_visited: ['/'],
    boards_covered: ['LET', 'CSE', 'CLE', 'NLE', 'PLE', 'CE', 'CPA', 'ME', 'REE', 'ARCH'],
    confirmed_defects: [],
  };
  fs.writeFileSync(target, JSON.stringify(report), { mode: 0o600 });
  assert.equal(readReport({
    evidenceDirectory, kind: 'site', expectedCommit: COMMIT, expectedMode: 'full', expectedSeed: 691,
  }).ok, true);

  report.boards_covered.pop();
  fs.writeFileSync(target, JSON.stringify(report), { mode: 0o600 });
  const incomplete = readReport({
    evidenceDirectory, kind: 'site', expectedCommit: COMMIT, expectedMode: 'full', expectedSeed: 691,
  });
  assert.equal(incomplete.state, 'INCOMPLETE');
  assert.equal(incomplete.code, 'REQUIRED_BOARD_COVERAGE_MISSING');

  report.boards_covered.push('NOT_A_BOARD');
  assert.equal(readReport({
    evidenceDirectory, kind: 'site', expectedCommit: COMMIT, expectedMode: 'full', expectedSeed: 691,
  }).state, 'INCOMPLETE');

  report.boards_covered.pop();
  report.routes_visited = [];
  fs.writeFileSync(target, JSON.stringify(report), { mode: 0o600 });
  assert.equal(readReport({
    evidenceDirectory, kind: 'site', expectedCommit: COMMIT, expectedMode: 'full', expectedSeed: 691,
  }).code, 'REQUIRED_BOARD_COVERAGE_MISSING');

  report.boards_covered.pop();
  report.boards_covered.push('ARCH');
  report.tested_commit = 'b'.repeat(40);
  fs.writeFileSync(target, JSON.stringify(report), { mode: 0o600 });
  assert.equal(readReport({
    evidenceDirectory, kind: 'site', expectedCommit: COMMIT, expectedMode: 'full', expectedSeed: 691,
  }).state, 'STALE');
});

test('account explorer acceptance requires a complete sanitized report for the current run', (t) => {
  const evidenceDirectory = makeEvidenceDirectory(t);
  const target = reportPathFor(evidenceDirectory, 'account');
  const report = {
    schema_version: 1,
    report_type: 'BOUNDED_ACCOUNT_EXPLORER',
    status: 'PASS',
    tested_commit: COMMIT,
    mode: 'smoke',
    seed: 691,
    scenarios_executed: 1,
    scenarios: [{ id: 'synthetic-scenario', route: '/account/login', result: 'PASS' }],
    diagnostics: [],
    routes_visited: ['/account/login'],
    unexpected_origins: [],
    confirmed_defects: [],
  };
  fs.writeFileSync(target, JSON.stringify(report), { mode: 0o600 });
  assert.equal(readReport({
    evidenceDirectory, kind: 'account', expectedCommit: COMMIT, expectedMode: 'smoke', expectedSeed: 691,
  }).ok, true);

  report.scenarios_executed = 0;
  fs.writeFileSync(target, JSON.stringify(report), { mode: 0o600 });
  assert.equal(readReport({
    evidenceDirectory, kind: 'account', expectedCommit: COMMIT, expectedMode: 'smoke', expectedSeed: 691,
  }).state, 'INCOMPLETE');

  report.scenarios_executed = 1;
  report.scenarios = [{ id: 'synthetic-scenario', route: '/account/login', result: 'FAIL' }];
  fs.writeFileSync(target, JSON.stringify(report), { mode: 0o600 });
  assert.equal(readReport({
    evidenceDirectory, kind: 'account', expectedCommit: COMMIT, expectedMode: 'smoke', expectedSeed: 691,
  }).code, 'ACCOUNT_REPORT_INCOMPLETE');

  report.scenarios_executed = 0;
  report.scenarios = [];
  fs.writeFileSync(target, JSON.stringify(report), { mode: 0o600 });
  assert.equal(readReport({
    evidenceDirectory, kind: 'account', expectedCommit: COMMIT, expectedMode: 'smoke', expectedSeed: 691,
  }).code, 'ACCOUNT_REPORT_INCOMPLETE');

  assert.equal(readReport({
    evidenceDirectory, kind: 'account', expectedCommit: COMMIT, expectedMode: 'unrecognized', expectedSeed: 691,
  }).code, 'RUN_MODE_INVALID');
});
