'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const test = require('node:test');
const { summarize, summaryStatus } = require('../../tools/summarize-browser-results.cjs');

const root = path.resolve(__dirname, '../..');

test('Linux browser workflow pins runtime and executes fixture, preflight, suite, and provenance steps', () => {
  const workflow = fs.readFileSync(path.join(root, '.github/workflows/browser-acceptance.yml'), 'utf8');
  const lock = JSON.parse(fs.readFileSync(path.join(root, 'package-lock.json'), 'utf8'));
  assert.match(workflow, /runs-on:\s*ubuntu-24\.04/);
  assert.match(workflow, /node-version:\s*22\.23\.3/);
  assert.match(workflow, /npm ci/);
  assert.match(workflow, /npm run test:browser:fixture/);
  for (const regression of [
    'BackupManifestPathSafetyTest.php',
    'ProductionQuizCsrfTest.php',
    'AnonymousLearnerPrivacyHttpTest.php',
    'SessionConfigurationTest.php',
    'SecurityTrustBoundaryTest.php',
    'FlashcardControllerReadPathTest.php',
    'FlashcardHttpTest.php',
  ]) assert.ok(workflow.includes(regression), `workflow omitted focused regression ${regression}`);
  assert.match(workflow, /playwright install --with-deps chromium/);
  assert.match(workflow, /browser-preflight\.cjs/);
  assert.match(workflow, /npm run test:browser/);
  assert.match(workflow, /write-browser-ci-manifest\.cjs/);
  assert.match(workflow, /permissions:\s*\n\s*contents:\s*read/);
  assert.ok(Number(workflow.match(/timeout-minutes:\s*(\d+)/)?.[1]) > 0);
  assert.equal(lock.packages?.['node_modules/@playwright/test']?.version, '1.63.0');
});

test('browser certification has no retries and a flaky result is a failure', () => {
  const config = fs.readFileSync(path.join(root, 'tests/browser/playwright.config.cjs'), 'utf8');
  const runner = fs.readFileSync(path.join(root, 'tools/run-browser-tests.cjs'), 'utf8');
  assert.match(config, /retries:\s*0/);
  assert.match(config, /trace:\s*'retain-on-failure'/);
  assert.match(runner, /browserReport\.stats\?\.flaky\s*===\s*0/);

  const report = {
    suites: [{ specs: [{ title: 'synthetic retry', file: 'fixture.spec.cjs', line: 1, tests: [{
      projectName: 'mobile', outcome: 'expected', ok: true, expectedStatus: 'passed',
      results: [{ status: 'failed' }, { status: 'passed' }],
    }] }] }],
  };
  const summary = summarize(report);
  assert.equal(summary.flaky, 1);
  assert.equal(summaryStatus(summary), 'FAILED');
});

test('browser provenance records exact commit and leaves missing browser evidence unrun', () => {
  const evidence = fs.mkdtempSync(path.join(os.tmpdir(), 'boardprep-browser-manifest-contract-'));
  const commit = spawnSync('git', ['rev-parse', 'HEAD'], { cwd: root, encoding: 'utf8' }).stdout.trim();
  try {
    const result = spawnSync(process.execPath, ['tools/write-browser-ci-manifest.cjs'], {
      cwd: root,
      encoding: 'utf8',
      env: { ...process.env, BOARDPREP_BROWSER_EVIDENCE_DIR: evidence, GITHUB_SHA: commit, GITHUB_ACTIONS: 'false' },
    });
    assert.equal(result.status, 0, result.stderr || result.stdout);
    const manifest = JSON.parse(fs.readFileSync(path.join(evidence, 'browser-ci-manifest.json'), 'utf8'));
    assert.equal(manifest.provenance.tested_sha, commit);
    assert.equal(manifest.provenance.workflow_sha_matches_checkout, true);
    assert.equal(manifest.browser_execution_status, 'NOT_COMPLETED');
    assert.equal(manifest.browser_preflight_status, 'NOT_RUN');
    assert.deepEqual(manifest.acceptance_case_ids, Array.from({ length: 12 }, (_, index) => `MVP-${String(index + 1).padStart(2, '0')}`));

    const mismatchResult = spawnSync(process.execPath, ['tools/write-browser-ci-manifest.cjs'], {
      cwd: root,
      encoding: 'utf8',
      env: { ...process.env, BOARDPREP_BROWSER_EVIDENCE_DIR: evidence, GITHUB_SHA: '0'.repeat(40), GITHUB_ACTIONS: 'true' },
    });
    assert.equal(mismatchResult.status, 1, 'provenance writer accepted a workflow SHA that differs from the checkout');
    const mismatchedManifest = JSON.parse(fs.readFileSync(path.join(evidence, 'browser-ci-manifest.json'), 'utf8'));
    assert.equal(mismatchedManifest.provenance.workflow_sha_matches_checkout, false);
  } finally {
    fs.rmSync(evidence, { recursive: true, force: true });
  }
});

test('browser preflight explicitly blocks a Termux host instead of implying desktop compatibility', () => {
  const result = spawnSync(process.execPath, ['tools/browser-preflight.cjs'], {
    cwd: root,
    encoding: 'utf8',
    env: { ...process.env, PREFIX: '/data/data/com.termux/files/usr' },
  });
  assert.equal(result.status, 2, result.stderr || result.stdout);
  const report = JSON.parse(result.stdout);
  assert.equal(report.environment.termux, true);
  assert.equal(report.checks.playwright_driver_compatibility.status, 'BLOCKED');
  assert.match(report.checks.playwright_driver_compatibility.reason, /Termux/);
  assert.equal(report.checks.loopback_connectivity.status, 'PASS');
});
