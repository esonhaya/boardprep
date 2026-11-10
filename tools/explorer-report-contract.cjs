'use strict';

const fs = require('node:fs');
const path = require('node:path');

const REPORTS = Object.freeze({
  site: Object.freeze({
    file: 'site-explorer.json',
    type: 'BOUNDED_SITE_EXPLORER',
    acceptedStatuses: ['PASS', 'PASS_WITH_REVIEW_CUES'],
  }),
  account: Object.freeze({
    file: 'account-explorer.json',
    type: 'BOUNDED_ACCOUNT_EXPLORER',
    acceptedStatuses: ['PASS'],
  }),
});

const MAX_REPORT_BYTES = 2 * 1024 * 1024;
const SUPPORTED_BOARD_CODES = new Set(['LET', 'CSE', 'CLE', 'NLE', 'PLE', 'CE', 'CPA', 'ME', 'REE', 'ARCH']);

function reportPathFor(evidenceDirectory, kind) {
  const definition = REPORTS[kind];
  if (!definition) throw new TypeError('Unknown Site Explorer report kind.');
  if (typeof evidenceDirectory !== 'string' || evidenceDirectory.trim() === '') {
    throw new TypeError('A Site Explorer evidence directory is required.');
  }
  return path.join(path.resolve(evidenceDirectory), definition.file);
}

function readReport({ evidenceDirectory, kind, expectedCommit, expectedMode, expectedSeed }) {
  const definition = REPORTS[kind];
  if (!definition) throw new TypeError('Unknown Site Explorer report kind.');
  if (!['smoke', 'full'].includes(expectedMode)) {
    return { ok: false, state: 'INVALID', code: 'RUN_MODE_INVALID' };
  }
  const reportPath = reportPathFor(evidenceDirectory, kind);
  let stat;
  try {
    stat = fs.lstatSync(reportPath);
  } catch (error) {
    if (error.code === 'ENOENT') return { ok: false, state: 'MISSING', code: 'REPORT_NOT_EMITTED' };
    return { ok: false, state: 'INVALID', code: 'REPORT_UNREADABLE' };
  }
  if (!stat.isFile() || stat.isSymbolicLink() || stat.size > MAX_REPORT_BYTES) {
    return { ok: false, state: 'INVALID', code: 'REPORT_FILE_UNSAFE_OR_OVERSIZED' };
  }

  let report;
  try {
    report = JSON.parse(fs.readFileSync(reportPath, 'utf8'));
  } catch {
    return { ok: false, state: 'INVALID', code: 'REPORT_JSON_INVALID' };
  }
  if (!report || Array.isArray(report) || typeof report !== 'object'
      || report.schema_version !== 1 || report.report_type !== definition.type
      || !/^[0-9a-f]{40}$/.test(String(report.tested_commit || ''))) {
    return { ok: false, state: 'INVALID', code: 'REPORT_SCHEMA_INVALID' };
  }
  if (report.tested_commit !== expectedCommit) {
    return {
      ok: false,
      state: 'STALE',
      code: 'TESTED_COMMIT_MISMATCH',
      testedCommit: report.tested_commit,
    };
  }
  if (report.mode !== expectedMode || Number(report.seed) !== Number(expectedSeed)) {
    return { ok: false, state: 'INVALID', code: 'RUN_CONFIGURATION_MISMATCH' };
  }
  if (report.diagnostic_code === 'PLAYWRIGHT_REPORT_NOT_EMITTED') {
    return { ok: false, state: 'INCOMPLETE', code: 'PLAYWRIGHT_REPORT_NOT_EMITTED' };
  }

  const summary = {
    status: String(report.status || 'UNAVAILABLE'),
    testedCommit: report.tested_commit,
    routeCount: Array.isArray(report.routes_visited) ? report.routes_visited.length : 0,
    boardCount: Array.isArray(report.boards_covered) ? new Set(report.boards_covered).size : 0,
    scenarioCount: Array.isArray(report.scenarios) ? report.scenarios.length : 0,
    defectCount: Array.isArray(report.confirmed_defects) ? report.confirmed_defects.length : -1,
  };

  if (kind === 'site') {
    const expectedBoards = expectedMode === 'smoke' ? 2 : 10;
    if (!Array.isArray(report.routes_visited) || report.routes_visited.length === 0
        || !Array.isArray(report.boards_covered)
        || !Array.isArray(report.confirmed_defects)
        || Number(report.coverage_requirements?.required_board_count) !== expectedBoards
        || report.boards_covered.some((code) => !SUPPORTED_BOARD_CODES.has(code))
        || (expectedMode === 'full' && summary.boardCount !== SUPPORTED_BOARD_CODES.size)
        || summary.boardCount < expectedBoards) {
      return { ok: false, state: 'INCOMPLETE', code: 'REQUIRED_BOARD_COVERAGE_MISSING', summary };
    }
  } else if (!Array.isArray(report.scenarios) || report.scenarios.length === 0
      || report.scenarios.some((scenario) => !scenario || typeof scenario !== 'object'
        || typeof scenario.id !== 'string' || scenario.id === ''
        || typeof scenario.route !== 'string' || scenario.route === ''
        || scenario.result !== 'PASS')
      || !Array.isArray(report.routes_visited) || report.routes_visited.length === 0
      || !Array.isArray(report.confirmed_defects)
      || !Array.isArray(report.diagnostics)
      || report.diagnostics.length !== 0
      || !Array.isArray(report.unexpected_origins)
      || report.scenarios_executed !== report.scenarios.length
      || report.unexpected_origins.length !== 0) {
    return { ok: false, state: 'INCOMPLETE', code: 'ACCOUNT_REPORT_INCOMPLETE', summary };
  }

  if (!definition.acceptedStatuses.includes(summary.status) || summary.defectCount !== 0) {
    return { ok: false, state: 'FAIL', code: 'EXPLORATION_DID_NOT_PASS', summary };
  }
  return { ok: true, state: 'PASS', code: 'REPORT_VERIFIED', summary };
}

function writeMissingReport({ evidenceDirectory, kind, testedCommit, mode, seed, processResult }) {
  const definition = REPORTS[kind];
  if (!definition) throw new TypeError('Unknown Site Explorer report kind.');
  const directory = path.resolve(evidenceDirectory);
  fs.mkdirSync(directory, { recursive: true, mode: 0o700 });
  const directoryStat = fs.lstatSync(directory);
  if (!directoryStat.isDirectory() || directoryStat.isSymbolicLink()) {
    throw new Error('The Site Explorer evidence path is not a real directory.');
  }

  const target = reportPathFor(directory, kind);
  const report = {
    schema_version: 1,
    report_type: definition.type,
    status: 'INCOMPLETE',
    diagnostic_code: 'PLAYWRIGHT_REPORT_NOT_EMITTED',
    tested_commit: /^[0-9a-f]{40}$/.test(String(testedCommit || '')) ? testedCommit : 'UNAVAILABLE',
    mode: ['smoke', 'full'].includes(mode) ? mode : 'UNAVAILABLE',
    seed: /^\d{1,10}$/.test(String(seed)) ? Number(seed) : null,
    route_count: 0,
    board_count: 0,
    scenario_count: 0,
    confirmed_defect_count: null,
    process_exit_status: Number.isInteger(processResult?.status) ? processResult.status : null,
    process_signal: /^SIG[A-Z0-9]+$/.test(String(processResult?.signal || '')) ? processResult.signal : null,
    spawn_error_code: /^[A-Z0-9_]+$/.test(String(processResult?.error?.code || '')) ? processResult.error.code : null,
    reason: 'Playwright did not emit its expected scenario report. This fallback is diagnostic only and is never acceptance evidence.',
  };
  let descriptor;
  try {
    descriptor = fs.openSync(target, 'wx', 0o600);
  } catch (error) {
    if (error.code === 'EEXIST') return { written: false, state: 'EXISTING_REPORT_PRESERVED' };
    throw error;
  }
  try {
    fs.writeFileSync(descriptor, `${JSON.stringify(report, null, 2)}\n`, 'utf8');
    fs.fsyncSync(descriptor);
  } finally {
    fs.closeSync(descriptor);
  }
  if (process.platform !== 'win32') fs.chmodSync(target, 0o600);
  return { written: true, state: 'FALLBACK_WRITTEN' };
}

module.exports = { MAX_REPORT_BYTES, readReport, reportPathFor, writeMissingReport };
