#!/usr/bin/env node

const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');

if (process.platform === 'android') {
  console.log('BROWSER_ACCEPTANCE=DEPENDENCY_MISSING');
  console.log('Playwright Chromium is not supported by the local Termux Node runtime.');
  process.exit(process.env.CI ? 1 : 0);
}

let cli;
try {
  const packageRoot = path.dirname(require.resolve('@playwright/test/package.json'));
  const { chromium } = require('@playwright/test');
  if (!fs.existsSync(chromium.executablePath())) {
    console.log('BROWSER_ACCEPTANCE=DEPENDENCY_MISSING');
    console.log('Install Chromium with: npm run test:browser:install');
    process.exit(process.env.CI ? 1 : 0);
  }
  cli = path.join(packageRoot, 'cli.js');
} catch (error) {
  console.log('BROWSER_ACCEPTANCE=DEPENDENCY_MISSING');
  console.log(String(error && error.message ? error.message : error));
  process.exit(process.env.CI ? 1 : 0);
}

const evidenceDirectory = fs.mkdtempSync(path.join(os.tmpdir(), 'boardprep-browser-evidence-'));
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

const repositoryRoot = path.resolve(__dirname, '..');
const evidencePath = (name) => path.join(evidenceDirectory, `${name}.json`);
const readEvidence = (name) => JSON.parse(fs.readFileSync(evidencePath(name), 'utf8'));
let exitStatus = result.status ?? 1;

if (exitStatus === 0) {
  try {
    const browserReportPath = path.join(repositoryRoot, 'test-results', 'browser-results.json');
    const browserReport = JSON.parse(fs.readFileSync(browserReportPath, 'utf8'));
    const runtime = readEvidence('runtime');
    const keyboard = readEvidence('keyboard');
    const accessibility = readEvidence('accessibility');
    const privacy = readEvidence('privacy');
    const productionBoundary = readEvidence('production-boundary');
    const tenBoard = readEvidence('ten-board');
    const viewports = readEvidence('viewports');
    const complexContent = readEvidence('complex-content');
    const requiredViewportSizes = ['320x640', '360x800', '390x844', '430x932', '768x1024'];
    const selectedProjects = process.argv.includes('--project=mobile')
      ? ['mobile']
      : process.argv.includes('--project=desktop')
        ? ['desktop']
        : ['desktop', 'mobile'];
    const actualProjectEvidence = selectedProjects.every((project) => (
      runtime.projects.includes(project)
      && runtime.results[project]?.engine === 'chromium'
      && runtime.results[project]?.version
    ));
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
      && complexContent.projects.includes('mobile')
      && requiredViewportSizes.every((size) => complexContent.results.mobile?.math?.includes(size))
      && requiredViewportSizes.every((size) => complexContent.results.mobile?.tables?.includes(size))
      && browserReport.stats?.unexpected === 0
      && browserReport.stats?.expected > 0
      && !process.argv.includes('--update-snapshots')
      && process.env.VISUAL_REGRESSION !== '1';
    const commit = spawnSync('git', ['rev-parse', 'HEAD'], {
      cwd: repositoryRoot,
      encoding: 'utf8',
    }).stdout.trim();
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
      viewports_executed: viewports.results.mobile?.sizes || [],
      journey_evidence: {
        keyboard_navigation: keyboard.projects.length > 0,
        accessibility_semantics: accessibility.projects.length > 0,
        privacy_export_and_deletion: privacy.projects.length > 0,
        production_boundary: productionBoundary.projects.length > 0,
        ten_board_journeys: tenBoard.results.mobile?.boards?.length === 10,
        responsive_viewport_sweep: viewports.results.mobile?.sizes?.length === requiredViewportSizes.length,
        math_and_table_viewports: complexContent.projects.includes('mobile'),
      },
      results: {
        passed: browserReport.stats?.expected ?? null,
        failed: browserReport.stats?.unexpected ?? null,
        skipped: browserReport.stats?.skipped ?? null,
        flaky: browserReport.stats?.flaky ?? null,
      },
      report: 'test-results/browser-results.json',
    };
    fs.mkdirSync(path.join(repositoryRoot, 'test-results'), { recursive: true });
    fs.writeFileSync(
      path.join(repositoryRoot, 'test-results', 'browser-execution.json'),
      `${JSON.stringify(execution, null, 2)}\n`
    );
    console.log(`BROWSER_EXECUTION=${execution.status}`);
  } catch (error) {
    console.error(`BROWSER_EXECUTION=EVIDENCE_INCOMPLETE (${error.message})`);
  }
}

if (evidenceDirectory) fs.rmSync(evidenceDirectory, { recursive: true, force: true });
process.exit(exitStatus);
