#!/usr/bin/env node
'use strict';

const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { execFileSync } = require('node:child_process');

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
    return entry.isDirectory() ? listFiles(file) : [file];
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
const artifactFiles = listFiles(evidenceDirectory).map((file) => path.relative(evidenceDirectory, file).split(path.sep).join('/'));
const stats = browserResults?.stats || {};
const workflowSha = process.env.GITHUB_SHA || null;
const manifest = {
  schema_version: 1,
  generated_at: new Date().toISOString(),
  provenance: {
    tested_sha: commit,
    workflow_sha: workflowSha,
    workflow_sha_matches_checkout: workflowSha === null || workflowSha === commit,
    build_sha: commit,
    worktree_clean: command('git', ['status', '--porcelain']) === '',
    workflow_run_id: process.env.GITHUB_RUN_ID || null,
    workflow_attempt: process.env.GITHUB_RUN_ATTEMPT || null,
    workflow_ref: process.env.GITHUB_REF || null,
    workflow_url: process.env.GITHUB_SERVER_URL && process.env.GITHUB_REPOSITORY && process.env.GITHUB_RUN_ID
      ? `${process.env.GITHUB_SERVER_URL}/${process.env.GITHUB_REPOSITORY}/actions/runs/${process.env.GITHUB_RUN_ID}`
      : null,
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
  browser_execution_status: browserExecution?.status || 'NOT_COMPLETED',
  browser_results: {
    passed: Number.isInteger(stats.expected) ? stats.expected : null,
    failed: Number.isInteger(stats.unexpected) ? stats.unexpected : null,
    flaky: Number.isInteger(stats.flaky) ? stats.flaky : null,
    skipped: Number.isInteger(stats.skipped) ? stats.skipped : null,
  },
  artifact_references: artifactFiles.filter((file) => file !== 'browser-ci-manifest.json'),
};

fs.mkdirSync(evidenceDirectory, { recursive: true, mode: 0o700 });
fs.writeFileSync(path.join(evidenceDirectory, 'browser-ci-manifest.json'), `${JSON.stringify(manifest, null, 2)}\n`, { mode: 0o600 });
process.stdout.write(`BROWSER_CI_MANIFEST=${path.join(evidenceDirectory, 'browser-ci-manifest.json')}\n`);
process.stdout.write(`TESTED_SHA=${commit || 'UNAVAILABLE'} WORKFLOW_SHA_MATCH=${manifest.provenance.workflow_sha_matches_checkout}\n`);
if (!commit || !manifest.provenance.workflow_sha_matches_checkout) process.exitCode = 1;
