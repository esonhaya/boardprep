#!/usr/bin/env node

const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');

if (process.platform === 'android') {
  console.log('BROWSER_ACCEPTANCE=BROWSER_BLOCKED');
  console.log('REASON=Playwright Chromium does not support the Android/Termux Node runtime.');
  process.exit(process.env.CI ? 1 : 2);
}

let cli;
try {
  const packageRoot = path.dirname(require.resolve('@playwright/test/package.json'));
  const { chromium } = require('@playwright/test');
  if (!fs.existsSync(chromium.executablePath())) {
    console.log('BROWSER_ACCEPTANCE=BROWSER_BLOCKED');
    console.log('REASON=The lockfile-pinned Playwright Chromium executable is not installed.');
    console.log('NEXT=On a supported desktop host, run npm ci && npm run test:browser:install.');
    process.exit(process.env.CI ? 1 : 2);
  }
  cli = path.join(packageRoot, 'cli.js');
} catch (error) {
  console.log('BROWSER_ACCEPTANCE=BROWSER_BLOCKED');
  console.log('REASON=The lockfile-pinned Playwright dependency is unavailable.');
  console.log(String(error && error.message ? error.message : error));
  process.exit(process.env.CI ? 1 : 2);
}

const configuredEvidenceDirectory = process.env.BOARDPREP_BROWSER_EVIDENCE_DIR;
const evidenceDirectory = configuredEvidenceDirectory
  ? path.resolve(configuredEvidenceDirectory)
  : fs.mkdtempSync(path.join(os.tmpdir(), 'boardprep-browser-evidence-'));
const removeEvidenceDirectory = !configuredEvidenceDirectory;
fs.mkdirSync(evidenceDirectory, { recursive: true });
const result = spawnSync(process.execPath, [
  cli,
  'test',
  '--config=tests/browser/playwright.config.cjs',
  ...process.argv.slice(2),
], {
  cwd: path.resolve(__dirname, '..'),
  env: {
    ...process.env,
    BOARDPREP_BROWSER_EVIDENCE_DIR: evidenceDirectory,
    ...(process.argv.includes('--update-snapshots') || process.env.VISUAL_REGRESSION === '1'
      ? { VISUAL_REGRESSION: '1' }
      : {}),
  },
  stdio: 'inherit',
});

try {
  fs.unlinkSync(path.join(evidenceDirectory, 'account-fixture-private.json'));
} catch {}

const repositoryRoot = path.resolve(__dirname, '..');
const evidencePath = (name) => path.join(evidenceDirectory, `${name}.json`);
const readEvidence = (name) => JSON.parse(fs.readFileSync(evidencePath(name), 'utf8'));
let exitStatus = result.status ?? 1;

if (exitStatus === 0) {
  try {
    const browserReportPath = path.join(evidenceDirectory, 'test-results', 'browser-results.json');
    const browserReport = JSON.parse(fs.readFileSync(browserReportPath, 'utf8'));
    const runtime = readEvidence('runtime');
    const keyboard = readEvidence('keyboard');
    const accessibility = readEvidence('accessibility');
    const privacy = readEvidence('privacy');
    const productionBoundary = readEvidence('production-boundary');
    const tenBoard = readEvidence('ten-board');
    const viewports = readEvidence('viewports');
    const complexContent = readEvidence('complex-content');
    const accountExplorer = readEvidence('account-explorer');
    const accountScenarioIds = [
      'mobile-login-layout-320',
      'mobile-login-layout-360',
      'mobile-login-layout-390',
      'mobile-login-layout-430',
      'mobile-login-layout-768',
      'production-route-exclusion',
      'anonymous-access-and-account-authorization',
      'anonymous-data-linking-disabled',
      'csrf-registration-rejection',
      'rotated-csrf-replay-rejection',
      'registration-recovery-code-generation',
      'duplicate-registration-enumeration',
      'wrong-password-generic-response',
      'login-and-session-rotation',
      'authenticated-profile-update',
      'account-practice-history-and-flashcard-progress',
      'account-export-step-up',
      'encrypted-backup-restore-fixture-integrity',
      'logout-authorization',
      'account-profile-isolation',
      'account-b-export-isolation',
      'independent-session-profile-persistence',
      'single-use-recovery-code',
      'recovery-replay-rejection',
      'password-change-revokes-other-session',
      'anonymous-learner-c-remains-separate',
      'deletion-removes-owned-records-and-revokes-credentials',
      'reauthenticated-deletion-request',
      'account-a-b-and-anonymous-c-survive-d-deletion',
      'expired-and-tampered-account-requests-rejected',
      'login-rate-limit',
      'qa-artifact-secret-exclusion',
    ];
    const commit = spawnSync('git', ['rev-parse', 'HEAD'], {
      cwd: repositoryRoot,
      encoding: 'utf8',
    }).stdout.trim();
    const responsiveStates = readEvidence('responsive-states');
    const requiredViewportSizes = ['320x568', '360x800', '390x844', '412x915', '768x1024'];
    const requiredDesktopViewport = { width: 1366, height: 768 };
    const selectedProjects = process.argv.includes('--project=mobile')
      ? ['mobile']
      : process.argv.includes('--project=desktop')
        ? ['desktop']
        : ['desktop', 'mobile'];
    const actualProjectEvidence = selectedProjects.every((project) => (
      runtime.projects.includes(project)
      && runtime.results[project]?.engine === 'chromium'
      && runtime.results[project]?.version
    ))
      && runtime.results.desktop?.viewport?.width === requiredDesktopViewport.width
      && runtime.results.desktop?.viewport?.height === requiredDesktopViewport.height
      && runtime.results.mobile?.viewport?.width === 390
      && runtime.results.mobile?.viewport?.height === 844;
    const completeBrowserEvidence = selectedProjects.length === 2
      && actualProjectEvidence
      && ['desktop', 'mobile'].every((project) => keyboard.projects.includes(project))
      && ['desktop', 'mobile'].every((project) => accessibility.projects.includes(project))
      && ['desktop', 'mobile'].every((project) => privacy.projects.includes(project))
      && ['desktop', 'mobile'].every((project) => productionBoundary.projects.includes(project))
      && tenBoard.projects.includes('mobile')
      && tenBoard.results.mobile?.boards?.length === 10
      && viewports.projects.includes('mobile')
      && requiredViewportSizes.every((size) => viewports.results.mobile?.sizes?.includes(size))
      && responsiveStates.projects.includes('mobile')
      && [
        'activePractice',
        'practiceResultReview',
        'simulatorReview',
        'flashcardReview',
        'privacyControls',
        'errorRecovery',
        'quizValidationError',
        'structured_figure',
        'structured_table',
      ].every((journey) => requiredViewportSizes.every((size) => responsiveStates.results.mobile?.[journey]?.includes(size)))
      && complexContent.projects.includes('mobile')
      && requiredViewportSizes.every((size) => complexContent.results.mobile?.math?.includes(size))
      && requiredViewportSizes.every((size) => complexContent.results.mobile?.tables?.includes(size))
      && accountExplorer.status === 'PASS'
      && accountExplorer.tested_commit === commit
      && accountScenarioIds.every((id) => accountExplorer.scenarios?.some((item) => item.id === id && item.result === 'PASS'))
      && browserReport.stats?.unexpected === 0
      && browserReport.stats?.flaky === 0
      && browserReport.stats?.expected > 0
      && !process.argv.includes('--update-snapshots')
      && process.env.VISUAL_REGRESSION !== '1';
    const workingTree = spawnSync('git', ['status', '--porcelain'], {
      cwd: repositoryRoot,
      encoding: 'utf8',
    }).stdout.trim();
    const execution = {
      format_version: 1,
      status: completeBrowserEvidence && workingTree === ''
        ? 'BROWSER_EXECUTED_PASS'
        : 'BROWSER_EXECUTED_PARTIAL',
      executed_at: new Date().toISOString(),
      commit,
      working_tree_clean: workingTree === '',
      command: `npm run test:browser${process.argv.slice(2).length ? ` -- ${process.argv.slice(2).join(' ')}` : ''}`,
      engine: 'chromium',
      engine_versions: Object.fromEntries(Object.entries(runtime.results).map(([project, item]) => [project, item.version])),
      projects: selectedProjects,
      desktop_viewport_executed: runtime.results.desktop?.viewport || null,
      viewports_executed: viewports.results.mobile?.sizes || [],
      journey_evidence: {
        keyboard_navigation: keyboard.projects.length > 0,
        accessibility_semantics: accessibility.projects.length > 0,
        privacy_export_and_deletion: privacy.projects.length > 0,
        production_boundary: productionBoundary.projects.length > 0,
        ten_board_journeys: tenBoard.results.mobile?.boards?.length === 10,
        responsive_viewport_sweep: viewports.results.mobile?.sizes?.length === requiredViewportSizes.length,
        responsive_active_practice_results_simulator_flashcards_privacy_and_errors: responsiveStates.projects.includes('mobile')
          && [
            'activePractice',
            'practiceResultReview',
            'simulatorReview',
            'flashcardReview',
            'privacyControls',
            'errorRecovery',
            'quizValidationError',
            'structured_figure',
            'structured_table',
          ].every((journey) => requiredViewportSizes.every((size) => responsiveStates.results.mobile?.[journey]?.includes(size))),
        math_and_table_viewports: complexContent.projects.includes('mobile'),
        authenticated_account_journeys: accountExplorer.status === 'PASS'
          && accountExplorer.tested_commit === commit
          && accountScenarioIds.every((id) => accountExplorer.scenarios?.some((item) => item.id === id && item.result === 'PASS')),
      },
      results: {
        passed: browserReport.stats?.expected ?? null,
        failed: browserReport.stats?.unexpected ?? null,
        skipped: browserReport.stats?.skipped ?? null,
        flaky: browserReport.stats?.flaky ?? null,
      },
      report: 'test-results/browser-results.json',
      authentication_report: 'account-explorer.json',
    };
    fs.mkdirSync(path.join(evidenceDirectory, 'test-results'), { recursive: true });
    fs.writeFileSync(
      path.join(evidenceDirectory, 'test-results', 'browser-execution.json'),
      `${JSON.stringify(execution, null, 2)}\n`
    );
    console.log(`BROWSER_EXECUTION=${execution.status}`);
    if (execution.status !== 'BROWSER_EXECUTED_PASS') {
      console.error('::error title=Browser certification evidence incomplete::Required projects, journeys, viewports, or clean-checkout evidence were not all present.');
      exitStatus = 1;
    }
  } catch (error) {
    console.error(`BROWSER_EXECUTION=EVIDENCE_INCOMPLETE (${error.message})`);
    exitStatus = 1;
  }
}

if (removeEvidenceDirectory && exitStatus === 0) {
  fs.rmSync(evidenceDirectory, { recursive: true, force: true });
} else if (removeEvidenceDirectory) {
  console.error(`BROWSER_EVIDENCE_DIR=${evidenceDirectory}`);
}
process.exit(exitStatus);
