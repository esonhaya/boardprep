#!/usr/bin/env node
'use strict';

const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { classifySkippedTests } = require('../tests/browser/expected-project-skips.cjs');

const root = path.resolve(__dirname, '..');
const evidenceDirectory = path.resolve(
  process.env.BOARDPREP_BROWSER_EVIDENCE_DIR
    || path.join(os.tmpdir(), `boardprep-browser-evidence-${process.pid}`)
);

function command(commandName, args) {
  try {
    return execFileSync(commandName, args, { cwd: root, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim();
  } catch {
    return null;
  }
}

function readJson(file) {
  try {
    return JSON.parse(fs.readFileSync(file, 'utf8'));
  } catch {
    return null;
  }
}

function listFiles(directory) {
  if (!fs.existsSync(directory)) return [];
  return fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const file = path.join(directory, entry.name);
    if (entry.isDirectory()) return listFiles(file);
    return entry.isFile() ? [file] : [];
  });
}

function artifactInventory(directory) {
  return listFiles(directory)
    .map((file) => ({ file, relative: path.relative(directory, file).split(path.sep).join('/') }))
    .filter(({ relative }) => ![
      'account-fixture-private.json',
      'browser-ci-manifest.json',
      'browser-ci-manifest.sha256',
    ].includes(relative))
    .sort((left, right) => left.relative.localeCompare(right.relative))
    .map(({ file, relative }) => {
      const bytes = fs.readFileSync(file);
      return {
        path: relative,
        size_bytes: bytes.length,
        sha256: require('node:crypto').createHash('sha256').update(bytes).digest('hex'),
      };
    });
}

const commit = command('git', ['rev-parse', 'HEAD']);
const packageLock = readJson(path.join(root, 'package-lock.json'));
const playwrightVersion = packageLock?.packages?.['node_modules/@playwright/test']?.version || null;
let chromiumVersion = null;
try {
  const { chromium } = require('@playwright/test');
  const executable = chromium.executablePath();
  if (fs.existsSync(executable)) chromiumVersion = command(executable, ['--version']);
} catch {}

const browserResultsPath = path.join(evidenceDirectory, 'test-results', 'browser-results.json');
const browserExecution = readJson(path.join(evidenceDirectory, 'test-results', 'browser-execution.json'));
const browserResults = readJson(browserResultsPath);
const preflight = readJson(path.join(evidenceDirectory, 'browser-preflight.json'));
const stats = browserResults?.stats || {};
const skippedTests = classifySkippedTests(browserResults);
const skippedCountsMatch = stats.skipped === skippedTests.expected.length + skippedTests.unexpected.length;
const workflowSha = process.env.GITHUB_SHA || null;
const worktreeClean = command('git', ['status', '--porcelain']) === '';
const testedSha = typeof browserExecution?.commit === 'string' ? browserExecution.commit : null;
const executionCommitMatchesCheckout = testedSha !== null && testedSha === commit;
const preflightCommitMatchesExecution = !preflight || preflight.commit === testedSha;
const artifactIntegrity = artifactInventory(evidenceDirectory);
const artifactsByPath = new Set(artifactIntegrity.map((item) => item.path));
const hasRequiredResultArtifacts = artifactsByPath.has('test-results/browser-results.json')
  && artifactsByPath.has('test-results/browser-execution.json')
  && artifactsByPath.has('browser-preflight.json');
const hasCompleteResultStats = Number.isInteger(stats.expected) && stats.expected > 0
  && Number.isInteger(stats.unexpected) && Number.isInteger(stats.flaky)
  && Number.isInteger(stats.skipped);
const evidenceIssues = [];
let acceptanceStatus = 'NOT_EXECUTED';

if (browserExecution || (workflowSha !== null && workflowSha !== commit)) {
  if (!commit || !executionCommitMatchesCheckout || !preflightCommitMatchesExecution
    || (workflowSha !== null && workflowSha !== commit)
    || browserExecution?.working_tree_clean !== true
    || !worktreeClean) {
    acceptanceStatus = 'UNVERIFIED_STALE_OR_MISMATCHED';
    if (!executionCommitMatchesCheckout) evidenceIssues.push('browser execution SHA does not match the current checkout SHA');
    if (!preflightCommitMatchesExecution) evidenceIssues.push('browser preflight SHA does not match the browser execution SHA');
    if (workflowSha !== null && workflowSha !== commit) evidenceIssues.push('workflow SHA does not match the current checkout SHA');
    if (browserExecution?.working_tree_clean !== true || !worktreeClean) evidenceIssues.push('worktree was not clean for execution or manifest generation');
  } else if (browserExecution.status === 'BROWSER_EXECUTED_FAIL'
    || (browserExecution.status === 'BROWSER_EXECUTED_PASS' && browserExecution.exit_code !== 0)
    || (Number.isInteger(stats.unexpected) && stats.unexpected > 0)
    || (Number.isInteger(stats.flaky) && stats.flaky > 0)) {
    acceptanceStatus = 'FAIL';
    evidenceIssues.push('browser execution or test result counts report a failure');
  } else if (browserExecution.status !== 'BROWSER_EXECUTED_PASS'
    || browserExecution.exit_code !== 0
    || preflight?.status !== 'PASS'
    || !hasRequiredResultArtifacts
    || !hasCompleteResultStats
    || !skippedCountsMatch
    || skippedTests.unexpected.length > 0
    || typeof browserExecution.command !== 'string'
    || browserExecution.command.trim() === ''
    || typeof browserExecution.fixture_identity !== 'string'
    || browserExecution.fixture_identity.trim() === '') {
    acceptanceStatus = 'INCOMPLETE';
    evidenceIssues.push(skippedTests.unexpected.length > 0 || !skippedCountsMatch
      ? 'one or more browser tests were skipped outside the reviewed project-specific skip list; the acceptance run is incomplete'
      : 'execution, preflight, fixture identity, command, or required artifacts are incomplete');
  } else {
    acceptanceStatus = 'PASS';
  }
}

const reportGeneratedAt = new Date().toISOString();
const manifest = {
  schema_version: 2,
  generated_at: reportGeneratedAt,
  acceptance_status: acceptanceStatus,
  evidence_issues: evidenceIssues,
  provenance: {
    tested_sha: testedSha,
    checkout_sha: commit,
    workflow_sha: workflowSha,
    workflow_sha_matches_checkout: workflowSha === null || workflowSha === commit,
    execution_sha_matches_checkout: executionCommitMatchesCheckout,
    preflight_sha_matches_execution: preflightCommitMatchesExecution,
    build_sha: testedSha,
    worktree_clean: worktreeClean,
    workflow_run_id: process.env.GITHUB_RUN_ID || null,
    workflow_attempt: process.env.GITHUB_RUN_ATTEMPT || null,
    workflow_ref: process.env.GITHUB_REF || null,
    workflow_url: process.env.GITHUB_SERVER_URL && process.env.GITHUB_REPOSITORY && process.env.GITHUB_RUN_ID
      ? `${process.env.GITHUB_SERVER_URL}/${process.env.GITHUB_REPOSITORY}/actions/runs/${process.env.GITHUB_RUN_ID}`
      : null,
  },
  execution: browserExecution ? {
    suite: 'Playwright browser acceptance',
    command: browserExecution.command || null,
    exit_code: Number.isInteger(browserExecution.exit_code) ? browserExecution.exit_code : null,
    started_at: browserExecution.started_at || null,
    completed_at: browserExecution.executed_at || null,
    elapsed_ms: Number.isFinite(browserExecution.elapsed_ms) ? browserExecution.elapsed_ms : null,
    fixture_identity: browserExecution.fixture_identity || null,
    limitations: Array.isArray(browserExecution.limitations) ? browserExecution.limitations : [],
  } : {
    suite: 'Playwright browser acceptance',
    status: 'NOT_EXECUTED',
    command: null,
    exit_code: null,
    started_at: null,
    completed_at: null,
    elapsed_ms: null,
    fixture_identity: null,
    limitations: [
      'No browser execution artifact exists; static browser contracts are not rendered-browser acceptance.',
    ],
  },
  environment: {
    execution: process.env.GITHUB_ACTIONS === 'true' ? 'github-actions' : 'local',
    runner_os: process.env.RUNNER_OS || null,
    platform: process.platform,
    architecture: process.arch,
    kernel_release: os.release(),
    node: process.version,
    npm: command('npm', ['--version']),
    php: command('php', ['-r', 'echo PHP_VERSION;']),
    playwright: playwrightVersion,
    chromium: chromiumVersion,
  },
  acceptance_case_ids: Array.from({ length: 12 }, (_, index) => `MVP-${String(index + 1).padStart(2, '0')}`),
  browser_preflight_status: preflight?.status || 'NOT_RUN',
  browser_preflight: preflight,
  browser_execution_status: browserExecution?.status || 'NOT_EXECUTED',
  browser_results: {
    passed: Number.isInteger(stats.expected) ? stats.expected : null,
    failed: Number.isInteger(stats.unexpected) ? stats.unexpected : null,
    flaky: Number.isInteger(stats.flaky) ? stats.flaky : null,
    skipped: Number.isInteger(stats.skipped) ? stats.skipped : null,
    expected_skipped: skippedTests.expected.length,
    unexpected_skipped: skippedTests.unexpected.length,
  },
  artifact_integrity: artifactIntegrity,
  artifact_references: artifactIntegrity.map((item) => item.path),
};

fs.mkdirSync(evidenceDirectory, { recursive: true, mode: 0o700 });
const manifestPath = path.join(evidenceDirectory, 'browser-ci-manifest.json');
const manifestBytes = `${JSON.stringify(manifest, null, 2)}\n`;
fs.writeFileSync(manifestPath, manifestBytes, { mode: 0o600 });
const manifestSha256 = require('node:crypto').createHash('sha256').update(manifestBytes).digest('hex');
fs.writeFileSync(path.join(evidenceDirectory, 'browser-ci-manifest.sha256'), `${manifestSha256}  browser-ci-manifest.json\n`, { mode: 0o600 });
process.stdout.write(`BROWSER_CI_MANIFEST=${path.join(evidenceDirectory, 'browser-ci-manifest.json')}\n`);
process.stdout.write(`ACCEPTANCE_STATUS=${acceptanceStatus} TESTED_SHA=${testedSha || 'NOT_EXECUTED'} CHECKOUT_SHA=${commit || 'UNAVAILABLE'} MANIFEST_SHA256=${manifestSha256}\n`);
if (acceptanceStatus === 'UNVERIFIED_STALE_OR_MISMATCHED' || acceptanceStatus === 'FAIL') process.exitCode = 1;
else if (acceptanceStatus === 'INCOMPLETE') process.exitCode = 2;
