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
  for (const [action, sha, release] of [
    ['actions/checkout', 'de0fac2e4500dabe0009e67214ff5f5447ce83dd', 'v6.0.2'],
    ['shivammathur/setup-php', 'eb7c497e18156a6bbabfef1d3a82760b9eda3962', '2.40.0'],
    ['actions/setup-node', '949feb2413d6458794dcd2491c4babbbce0c15c1', 'v7.1.0'],
    ['actions/upload-artifact', '043fb46d1a93c77aae656e7c1c64a875d1fc6a0a', 'v7.0.1'],
  ]) {
    assert.ok(workflow.includes(`uses: ${action}@${sha} # ${release}`), `${action} must stay pinned to the reviewed immutable release SHA`);
  }
  assert.doesNotMatch(workflow, /uses:\s*(?:actions\/(?:checkout|setup-node|upload-artifact)|shivammathur\/setup-php)@v?\d+(?:\s|$)/m,
    'browser CI actions must not use mutable version tags');
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
  const readme = fs.readFileSync(path.join(root, 'README.md'), 'utf8');
  assert.match(readme, /Termux\/Android runtime reports `BROWSER_ACCEPTANCE=BROWSER_BLOCKED`/,
    'local browser instructions must match the runner status');
});

test('all automated workflows pin external actions and acceptance covers calendar plus both backup stores', () => {
  for (const workflowName of ['browser-acceptance.yml', 'release-gates.yml', 'site-explorer.yml']) {
    const workflow = fs.readFileSync(path.join(root, '.github/workflows', workflowName), 'utf8');
    const actionReferences = [...workflow.matchAll(/^\s*-?\s*uses:\s*([^\s#]+)/gm)];
    assert.ok(actionReferences.length > 0, `${workflowName} must declare its external actions`);
    for (const [, reference] of actionReferences) {
      assert.match(reference, /@[0-9a-f]{40}$/, `${workflowName} contains a mutable or non-SHA action reference: ${reference}`);
    }
  }

  const calendarSpec = fs.readFileSync(path.join(root, 'tests/browser/acceptance/study-calendar.spec.cjs'), 'utf8');
  assert.match(calendarSpec, /\/study-calendar/);
  assert.match(calendarSpec, /Generate study calendar/);
  assert.match(calendarSpec, /Mark complete/);
  assert.match(calendarSpec, /documentElement\.scrollWidth/);

  const releaseWorkflow = fs.readFileSync(path.join(root, '.github/workflows/release-gates.yml'), 'utf8');
  assert.match(releaseWorkflow, /account-encrypted-backup-restore-sqlite[\s\S]*?backup_test_driver: sqlite/);
  assert.match(releaseWorkflow, /BOARDPREP_BACKUP_TEST_DRIVER:\s*\$\{\{\s*matrix\.backup_test_driver/);
});

test('browser evidence contracts cover isolated data, desktop/mobile access paths, and retained diagnostics', () => {
  const workflow = fs.readFileSync(path.join(root, '.github/workflows/browser-acceptance.yml'), 'utf8');
  const config = fs.readFileSync(path.join(root, 'tests/browser/playwright.config.cjs'), 'utf8');
  const learnerSpec = fs.readFileSync(path.join(root, 'tests/browser/acceptance/boardprep.spec.cjs'), 'utf8');
  const accountSpec = fs.readFileSync(path.join(root, 'tests/browser/accounts/account-auth.spec.cjs'), 'utf8');
  const reportSpec = fs.readFileSync(path.join(root, 'tests/browser/acceptance/question-reports.spec.cjs'), 'utf8');
  const server = fs.readFileSync(path.join(root, 'tests/browser/server.cjs'), 'utf8');

  assert.match(config, /name:\s*'mobile'[\s\S]*?viewport:\s*\{\s*width:\s*390,\s*height:\s*844/);
  assert.match(config, /name:\s*'desktop'[\s\S]*?viewport:\s*\{\s*width:\s*1366,\s*height:\s*768/);
  assert.match(config, /trace:\s*'retain-on-failure'/);
  assert.match(config, /screenshot:\s*'only-on-failure'/);
  assert.match(learnerSpec, /page\.keyboard\.press\('Tab'\)/);
  assert.match(accountSpec, /anonymous-access-and-account-authorization/);
  assert.match(accountSpec, /login-and-session-rotation/);
  assert.match(accountSpec, /authenticated-exam-recovery-session-loss/);
  assert.match(accountSpec, /clearCookies\(\{ name: 'BOARDPREP_AUTH_SESSION' \}\)/);
  assert.match(accountSpec, /fs\.unlinkSync\(lostSessionFile\)/,
    'the account browser journey must simulate server-side PHP session-data loss in its isolated fixture');
  assert.match(reportSpec, /learner can report a result question and reports stay private to the session/);
  assert.match(reportSpec, /tabToKeyboardFocus/);
  assert.match(reportSpec, /UNCLEAR_WORDING/);
  assert.match(reportSpec, /browser\.newContext\(\)/);
  assert.match(reportSpec, /learner-question-report-record script/);
  assert.match(server, /\['-C', repositoryRoot, 'archive', '--format=tar'/);
  assert.match(workflow, /Upload browser visual and diagnostic artifacts[\s\S]*?if:\s*always\(\)[\s\S]*?actions\/upload-artifact/);
  assert.match(workflow, /retention-days:\s*14/);
});

test('browser certification has no retries and a flaky result is a failure', () => {
  const config = fs.readFileSync(path.join(root, 'tests/browser/playwright.config.cjs'), 'utf8');
  const runner = fs.readFileSync(path.join(root, 'tools/run-browser-tests.cjs'), 'utf8');
  assert.match(config, /retries:\s*0/);
  assert.match(config, /trace:\s*'retain-on-failure'/);
  assert.match(runner, /browserReport\.stats\?\.flaky\s*===\s*0/);
  assert.match(runner, /browserReport\.stats\?\.skipped\s*===\s*0/);
  assert.match(fs.readFileSync(path.join(root, 'tools/write-browser-ci-manifest.cjs'), 'utf8'), /stats\.skipped\s*>\s*0/);

  const report = {
    suites: [{ specs: [{ title: 'synthetic retry', file: 'fixture.spec.cjs', line: 1, tests: [{
      projectName: 'mobile', outcome: 'expected', ok: true, expectedStatus: 'passed',
      results: [{ status: 'failed' }, { status: 'passed' }],
    }] }] }],
  };
  const summary = summarize(report);
  assert.equal(summary.flaky, 1);
  assert.equal(summaryStatus(summary), 'FAILED');

  const skippedSummary = summarize({
    suites: [{ specs: [{ title: 'mandatory skipped flow', file: 'fixture.spec.cjs', line: 1, tests: [{
      projectName: 'mobile', outcome: 'skipped', ok: true, expectedStatus: 'skipped', results: [],
    }] }] }],
  });
  assert.equal(skippedSummary.skipped, 1);
  assert.equal(summaryStatus(skippedSummary), 'INCOMPLETE',
    'a skipped mandatory browser flow must remain unexecuted, not certified complete');
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
    assert.equal(manifest.provenance.tested_sha, null);
    assert.equal(manifest.provenance.checkout_sha, commit);
    assert.equal(manifest.provenance.workflow_sha_matches_checkout, true);
    assert.equal(manifest.acceptance_status, 'NOT_EXECUTED');
    assert.equal(manifest.browser_execution_status, 'NOT_EXECUTED');
    assert.equal(manifest.browser_preflight_status, 'NOT_RUN');
    assert.deepEqual(manifest.acceptance_case_ids, Array.from({ length: 12 }, (_, index) => `MVP-${String(index + 1).padStart(2, '0')}`));
    const sidecarSha = fs.readFileSync(path.join(evidence, 'browser-ci-manifest.sha256'), 'utf8').trim().split(/\s+/)[0];
    const manifestSha = require('node:crypto').createHash('sha256')
      .update(fs.readFileSync(path.join(evidence, 'browser-ci-manifest.json'))).digest('hex');
    assert.equal(sidecarSha, manifestSha, 'the evidence manifest sidecar must verify its exact bytes');

    const mismatchResult = spawnSync(process.execPath, ['tools/write-browser-ci-manifest.cjs'], {
      cwd: root,
      encoding: 'utf8',
      env: { ...process.env, BOARDPREP_BROWSER_EVIDENCE_DIR: evidence, GITHUB_SHA: '0'.repeat(40), GITHUB_ACTIONS: 'true' },
    });
    assert.equal(mismatchResult.status, 1, 'provenance writer accepted a workflow SHA that differs from the checkout');
    const mismatchedManifest = JSON.parse(fs.readFileSync(path.join(evidence, 'browser-ci-manifest.json'), 'utf8'));
    assert.equal(mismatchedManifest.provenance.workflow_sha_matches_checkout, false);

    const staleSha = '0'.repeat(40);
    fs.mkdirSync(path.join(evidence, 'test-results'), { recursive: true });
    fs.writeFileSync(path.join(evidence, 'browser-preflight.json'), JSON.stringify({ status: 'PASS', commit: staleSha }));
    fs.writeFileSync(path.join(evidence, 'test-results', 'browser-execution.json'), JSON.stringify({
      status: 'BROWSER_EXECUTED_PASS',
      commit: staleSha,
      working_tree_clean: true,
      exit_code: 0,
      command: 'npm run test:browser',
      executed_at: '2026-11-04T00:00:00Z',
    }));
    fs.writeFileSync(path.join(evidence, 'test-results', 'browser-results.json'), JSON.stringify({ stats: {
      expected: 1, unexpected: 0, flaky: 0, skipped: 0,
    } }));
    fs.writeFileSync(path.join(evidence, 'account-fixture-private.json'), JSON.stringify({ storage: '/synthetic/private/path' }));
    const staleResult = spawnSync(process.execPath, ['tools/write-browser-ci-manifest.cjs'], {
      cwd: root,
      encoding: 'utf8',
      env: { ...process.env, BOARDPREP_BROWSER_EVIDENCE_DIR: evidence, GITHUB_SHA: commit, GITHUB_ACTIONS: 'false' },
    });
    assert.equal(staleResult.status, 1, 'the evidence writer accepted browser PASS artifacts from another commit');
    const staleManifest = JSON.parse(fs.readFileSync(path.join(evidence, 'browser-ci-manifest.json'), 'utf8'));
    assert.equal(staleManifest.acceptance_status, 'UNVERIFIED_STALE_OR_MISMATCHED');
    assert.equal(staleManifest.browser_execution_status, 'BROWSER_EXECUTED_PASS');
    assert.ok(staleManifest.evidence_issues.some((issue) => issue.includes('execution SHA')));
    assert.ok(!staleManifest.artifact_references.includes('account-fixture-private.json'), 'private fixture handoff metadata must not enter the artifact manifest');
    assert.ok(staleManifest.artifact_integrity.every((artifact) => /^[a-f0-9]{64}$/.test(artifact.sha256)));

    const isolatedRepo = fs.mkdtempSync(path.join(os.tmpdir(), 'boardprep-browser-skip-manifest-'));
    const fixtureEvidence = fs.mkdtempSync(path.join(os.tmpdir(), 'boardprep-browser-skip-evidence-'));
    try {
      fs.mkdirSync(path.join(isolatedRepo, 'tools'), { recursive: true });
      fs.mkdirSync(path.join(fixtureEvidence, 'test-results'), { recursive: true });
      fs.copyFileSync(path.join(root, 'tools/write-browser-ci-manifest.cjs'), path.join(isolatedRepo, 'tools/write-browser-ci-manifest.cjs'));
      fs.writeFileSync(path.join(isolatedRepo, 'package-lock.json'), JSON.stringify({ packages: {} }));
      const git = (args) => spawnSync('git', args, { cwd: isolatedRepo, encoding: 'utf8' });
      assert.equal(git(['init', '-q']).status, 0);
      assert.equal(git(['config', 'user.email', 'fixture@example.invalid']).status, 0);
      assert.equal(git(['config', 'user.name', 'Acceptance Fixture']).status, 0);
      assert.equal(git(['add', '.']).status, 0);
      assert.equal(git(['commit', '-qm', 'synthetic browser evidence fixture']).status, 0);
      const fixtureCommit = git(['rev-parse', 'HEAD']).stdout.trim();
      fs.writeFileSync(path.join(fixtureEvidence, 'browser-preflight.json'), JSON.stringify({ status: 'PASS', commit: fixtureCommit }));
      fs.writeFileSync(path.join(fixtureEvidence, 'test-results', 'browser-execution.json'), JSON.stringify({
        status: 'BROWSER_EXECUTED_PASS', commit: fixtureCommit, working_tree_clean: true,
        exit_code: 0, command: 'npm run test:browser', executed_at: '2026-11-04T00:00:00Z',
        fixture_identity: 'synthetic-isolated-fixture',
      }));
      const fixtureManifestCommand = () => spawnSync(process.execPath, ['tools/write-browser-ci-manifest.cjs'], {
        cwd: isolatedRepo,
        encoding: 'utf8',
        env: { ...process.env, BOARDPREP_BROWSER_EVIDENCE_DIR: fixtureEvidence, GITHUB_SHA: fixtureCommit, GITHUB_ACTIONS: 'false' },
      });
      const browserResultsPath = path.join(fixtureEvidence, 'test-results', 'browser-results.json');
      fs.writeFileSync(browserResultsPath, JSON.stringify({ stats: {
        expected: 10, unexpected: 0, flaky: 0, skipped: 0,
      } }));
      const completeResult = fixtureManifestCommand();
      assert.equal(completeResult.status, 0, completeResult.stderr || completeResult.stdout);
      const completeManifest = JSON.parse(fs.readFileSync(path.join(fixtureEvidence, 'browser-ci-manifest.json'), 'utf8'));
      assert.equal(completeManifest.acceptance_status, 'PASS', 'complete synthetic exact-SHA evidence should certify');

      fs.writeFileSync(browserResultsPath, JSON.stringify({ stats: {
        expected: 10, unexpected: 0, flaky: 0, skipped: 1,
      } }));
      const skippedResult = fixtureManifestCommand();
      assert.equal(skippedResult.status, 2, 'the provenance writer must reject a suite with a skipped required flow as incomplete');
      const skippedManifest = JSON.parse(fs.readFileSync(path.join(fixtureEvidence, 'browser-ci-manifest.json'), 'utf8'));
      assert.equal(skippedManifest.acceptance_status, 'INCOMPLETE');
      assert.equal(skippedManifest.browser_results.skipped, 1);
    } finally {
      fs.rmSync(isolatedRepo, { recursive: true, force: true });
      fs.rmSync(fixtureEvidence, { recursive: true, force: true });
    }
  } finally {
    fs.rmSync(evidence, { recursive: true, force: true });
  }
});

test('required rendered-browser viewport evidence includes 320, 360, 390, 430, and 768 pixels', () => {
  const runner = fs.readFileSync(path.join(root, 'tools/run-browser-tests.cjs'), 'utf8');
  const accountSpec = fs.readFileSync(path.join(root, 'tests/browser/accounts/account-auth.spec.cjs'), 'utf8');
  const expected = [
    ['320', '568'], ['360', '800'], ['390', '844'], ['430', '932'], ['768', '1024'],
  ];
  const accountViewportBlock = accountSpec.match(/for \(const viewport of \[([\s\S]*?)\]\)/)?.[1] || '';
  const actualAccountViewports = [...accountViewportBlock.matchAll(/width:\s*(\d+),\s*height:\s*(\d+)/g)]
    .map((match) => `${match[1]}x${match[2]}`);
  const requiredBlock = runner.match(/const requiredViewportSizes = \[([^\]]+)\]/)?.[1] || '';
  const requiredViewports = [...requiredBlock.matchAll(/'([^']+)'/g)].map((match) => match[1]);

  for (const [width, height] of expected) {
    assert.ok(actualAccountViewports.includes(`${width}x${height}`), `account browser journey omits ${width}x${height}`);
    assert.ok(requiredViewports.includes(`${width}x${height}`), `browser certification does not require ${width}x${height}`);
  }
  for (const [width, height] of expected) {
    assert.ok(accountSpec.includes(`'${width}x${height}'`), `account browser evidence omits ${width}x${height}`);
  }
  assert.match(accountSpec, /authenticated-exam-recovery-session-loss/);
  assert.match(runner, /'authenticated-exam-recovery-session-loss'/);
});

test('Developer Question Repository browser suite covers every required REPO case without mutation', () => {
  const suitePath = path.join(root, 'tests/browser/acceptance/developer-question-repository.spec.cjs');
  const suite = fs.readFileSync(suitePath, 'utf8');
  for (let index = 1; index <= 24; index += 1) {
    const caseId = `REPO-${String(index).padStart(2, '0')}`;
    assert.ok(suite.includes(`'${caseId}`), `browser suite omitted ${caseId}`);
  }
  assert.match(suite, /question-editor\/archive/);
  assert.match(suite, /question-editor\/update/);
  assert.match(suite, /question-inspector/);
  assert.match(suite, /Q6403/);
  assert.match(suite, /Not inferred from repository status/);
  assert.match(suite, /source_ids/);
  assert.match(suite, /page\.goBack\(\)/);
  assert.match(suite, /page\.goForward\(\)/);
  assert.match(suite, /tabToKeyboardFocus/);
  assert.match(suite, /document\.documentElement\.scrollWidth/);
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
