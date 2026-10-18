'use strict';

const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const crypto = require('node:crypto');
const { execFileSync, spawn } = require('node:child_process');
const { test, expect } = require('@playwright/test');

// Authentication journeys mutate rate limits and durable fixture accounts.
// Retrying the same journey against that state can turn the first failure into
// a misleading second attempt and overwrite its evidence.
test.describe.configure({ retries: 0 });
test.use({ screenshot: 'off' });

const AUTH = 'http://127.0.0.1:4175';
const PUBLIC = 'http://127.0.0.1:4173';
const PASSWORD_A = 'BoardPrep batch 692 learner passphrase';
const PASSWORD_B = 'BoardPrep batch 692 second account phrase';
const PASSWORD_A_RECOVERED = 'BoardPrep batch 692 recovered phrase';
const PASSWORD_A_CHANGED = 'BoardPrep batch 692 changed phrase';
const BACKUP_PASSPHRASE = 'BoardPrep batch 693 synthetic backup fixture phrase';
const reportSensitiveValues = new Set();

function sanitizedFailure(error) {
  let message = String(error && error.message ? error.message : error).slice(0, 500);
  for (const value of reportSensitiveValues) {
    if (value.length >= 4) message = message.split(value).join('[REDACTED]');
  }
  return message
    .replace(/\b[A-F0-9]{8}(?:-[A-F0-9]{8}){4}\b/gi, '[REDACTED_RECOVERY_CODE]')
    .replace(/\b[a-f0-9]{64}\b/gi, '[REDACTED_TOKEN]');
}

function csrf(page) {
  return page.locator('input[name="csrf_token"]').first().inputValue();
}

async function register(page, username, password) {
  reportSensitiveValues.add(username);
  reportSensitiveValues.add(password);
  await page.goto(AUTH + '/account/register');
  await page.locator('#account-username').fill(username);
  await page.locator('#account-password').fill(password);
  await page.locator('#account-password-confirmation').fill(password);
  await page.getByRole('button', { name: 'Create account' }).click();
  await expect(page.getByRole('heading', { name: 'Account recovery codes' })).toBeVisible();
  const codes = await page.locator('.recovery-code-list code').allInnerTexts();
  for (const code of codes) reportSensitiveValues.add(code);
  expect(codes.length === 8).toBe(true);
  return codes;
}

async function signIn(page, username, password) {
  reportSensitiveValues.add(username);
  reportSensitiveValues.add(password);
  await page.goto(AUTH + '/account/login');
  await page.locator('#account-username').fill(username);
  await page.locator('#account-password').fill(password);
  await page.getByRole('button', { name: 'Sign in' }).click();
}

async function completeSyntheticPractice(page) {
  await page.goto(AUTH + '/quiz?exam=LET');
  const subject = await page.locator('#quiz-subject option[data-exam="LET"]').first().getAttribute('value');
  expect(subject).toBeTruthy();
  await page.locator('#quiz-subject').selectOption(subject);
  await page.locator('#quiz-count').selectOption('5');
  await page.locator('#quiz-mode').selectOption('practice');
  await page.getByRole('button', { name: 'Start practice' }).click();
  await expect(page.locator('.quiz-question')).toBeVisible();
  for (let index = 0; index < 5; index += 1) {
    await page.locator('input[name="answer"]').first().check();
    await page.getByRole('button', { name: /submit answer/i }).click();
    await expect(page.locator('.quiz-feedback')).toBeVisible();
    await page.getByRole('button', { name: index === 4 ? 'Finish Quiz' : 'Next Question' }).click();
    if (index < 4) await expect(page.locator('.quiz-answer-form')).toBeVisible();
  }
  await expect(page.getByRole('heading', { name: /Quiz Result/i })).toBeVisible();
}

async function reviewOneSyntheticFlashcard(page) {
  await page.goto(AUTH + '/flashcards?include_new=1');
  const start = page.getByRole('link', { name: /Start review|Review now/ }).first();
  await expect(start).toBeVisible();
  await start.click();
  await expect(page.locator('.flashcard-card')).toBeVisible();
  await page.getByRole('link', { name: 'Reveal answer' }).click();
  await expect(page.locator('.flashcard-answer')).toBeVisible();
  await page.getByRole('button', { name: /Good Remembered/ }).click();
  await expect(page).toHaveURL(/\/flashcards\/review/);
}

async function runAccountCli(authStorage, script, args) {
  const root = path.resolve(__dirname, '../../..');
  const child = spawn(process.env.PHP_BINARY || 'php', [
    path.join(root, script),
    ...args,
    '--passphrase-fd=3',
  ], {
    cwd: root,
    env: {
      ...process.env,
      APP_ENV: 'testing',
      APP_DEBUG: 'false',
      BOARDPREP_ACCOUNTS_ENABLED: 'true',
      DB_DRIVER: 'json',
      APP_STORAGE_PATH: authStorage,
    },
    stdio: ['ignore', 'pipe', 'pipe', 'pipe'],
  });
  reportSensitiveValues.add(BACKUP_PASSPHRASE);
  const output = [];
  let errorOutput = '';
  child.stdout.on('data', (chunk) => output.push(chunk));
  child.stderr.on('data', (chunk) => { errorOutput += chunk.toString('utf8'); });
  child.stdio[3].end(BACKUP_PASSPHRASE + '\n');
  const code = await new Promise((resolve, reject) => {
    child.once('error', reject);
    child.once('close', resolve);
  });
  if (code !== 0) {
    const category = errorOutput.match(/\b(?:BACKUP|RESTORE|ACCOUNT|CLI|DEPENDENCY)_[A-Z0-9_]+/)?.[0] || 'UNCLASSIFIED';
    throw new Error('Synthetic account backup or restore CLI failed with status ' + code + ' (' + category + ').');
  }
  try {
    return JSON.parse(Buffer.concat(output).toString('utf8'));
  } catch {
    throw new Error('Synthetic account backup or restore CLI returned an invalid report.');
  }
}

function collectionSnapshot(storage, excludedLearnerId = '') {
  const result = {};
  for (const name of ['attempts', 'weakness', 'flashcard_state']) {
    const file = path.join(storage, name + '.json');
    const records = fs.existsSync(file) ? JSON.parse(fs.readFileSync(file, 'utf8')) : [];
    result[name] = records.filter((record) => record && record.user_id !== excludedLearnerId);
  }
  return crypto.createHash('sha256').update(JSON.stringify(result)).digest('hex');
}

test('isolated mobile account journeys preserve authentication and learner ownership', async ({ page, browser }, testInfo) => {
  test.skip(testInfo.project.name !== 'mobile', 'Run the account journey once in supported Chromium mobile emulation; HTTP/service suites cover server-side transitions.');

  const seed = Number.parseInt(process.env.BOARDPREP_EXPLORER_SEED || '692', 10) || 692;
  const usernameA = 'learnera-' + seed;
  const usernameB = 'learnerb-' + seed;
  const usernameDelete = 'delete-' + seed;
  const usernameLimit = 'limit-' + seed;
  const evidenceDirectory = path.resolve(process.env.BOARDPREP_BROWSER_EVIDENCE_DIR || fs.mkdtempSync(path.join(os.tmpdir(), 'boardprep-account-browser-')));
  const fixtureManifestPath = path.join(evidenceDirectory, 'account-fixture-private.json');
  if (!fs.existsSync(fixtureManifestPath)) throw new Error('The isolated account fixture handoff is missing.');
  const authStorage = JSON.parse(fs.readFileSync(fixtureManifestPath, 'utf8')).storage;
  if (typeof authStorage !== 'string' || !path.isAbsolute(authStorage)) throw new Error('The isolated account fixture path is invalid.');
  const mode = process.env.BOARDPREP_EXPLORER_MODE || 'browser-acceptance';
  const maxDurationMs = mode === 'smoke' ? 180000 : 300000;
  const startedAt = Date.now();
  testInfo.setTimeout(Math.ceil((maxDurationMs + 30000) / 1000) * 1000);
  const explorerReport = {
    schema_version: 1,
    report_type: 'BOUNDED_ACCOUNT_EXPLORER',
    status: 'RUNNING',
    tested_commit: execFileSync('git', ['rev-parse', 'HEAD'], { cwd: path.resolve(__dirname, '../../..'), encoding: 'utf8' }).trim(),
    seed,
    mode,
    viewport: '390x844 Chromium mobile emulation',
    limits: { max_pages: 120, max_scenarios: 36, max_duration_ms: maxDurationMs, concurrency: 1, allowed_origins: [AUTH, PUBLIC] },
    page_navigation_count: 0,
    unexpected_origins: [],
    viewports_tested: ['320x640', '360x800', '390x844', '430x932', '768x1024'],
    scenarios: [],
    confirmed_defects: [],
    routes_visited: [],
    actions_executed: 0,
  };
  const contexts = [];
  reportSensitiveValues.clear();
  const trackPage = (trackedPage) => trackedPage.on('framenavigated', (frame) => {
    if (frame !== trackedPage.mainFrame()) return;
    const url = new URL(frame.url());
    if (url.protocol === 'about:') return;
    if (!explorerReport.limits.allowed_origins.includes(url.origin)) {
      explorerReport.unexpected_origins.push(url.origin);
      return;
    }
    explorerReport.page_navigation_count += 1;
  });
  trackPage(page);
  const scenario = (id, route, observed) => {
    if (explorerReport.scenarios.length >= explorerReport.limits.max_scenarios
      || Date.now() - startedAt > maxDurationMs) {
      throw new Error('Authentication exploration exceeded its configured scenario or time budget.');
    }
    if (explorerReport.page_navigation_count > explorerReport.limits.max_pages
      || explorerReport.unexpected_origins.length > 0) {
      throw new Error('Authentication exploration exceeded its page budget or left the loopback origin allowlist.');
    }
    explorerReport.scenarios.push({ id, route, result: 'PASS', observed });
    explorerReport.routes_visited.push(route);
  };
  const recordReport = async (status, failure = null) => {
    explorerReport.status = status;
    if (failure) {
      const observed = sanitizedFailure(failure);
      const serverCategory = observed.match(/\b(?:BACKUP|RESTORE|ACCOUNT|CLI|DEPENDENCY)_[A-Z0-9_]+/)?.[0]
        || 'ASSERTION_OR_NAVIGATION_FAILURE';
      const lastCompletedScenario = explorerReport.scenarios.at(-1)?.id || null;
      const failureRoute = new URL(page.url()).pathname;
      explorerReport.confirmed_defects.push({
        code: 'ACCOUNT_JOURNEY_ASSERTION',
        scenario: lastCompletedScenario
          ? 'action following ' + lastCompletedScenario
          : 'journey initialization at ' + failureRoute,
        last_completed_scenario: lastCompletedScenario,
        route: failureRoute,
        expected_actual_result: observed,
        server_exception_category: serverCategory,
        reproduction_steps: explorerReport.scenarios.slice(-6).map(({ id, route, observed: step }) => ({ id, route, observed: step })),
      });
      const target = path.join(evidenceDirectory, 'account-explorer-failure.png');
      await page.screenshot({
        path: target,
        fullPage: true,
        mask: [page.locator('main'), page.locator('input'), page.locator('.recovery-code-list'), page.locator('.account-page dl dd'), page.locator('.quiz-question, .result-answer-review, .flashcard-card, .flashcard-answer')],
      }).catch(() => undefined);
      explorerReport.confirmed_defects[0].screenshot = path.basename(target);
    }
    fs.mkdirSync(evidenceDirectory, { recursive: true });
    fs.writeFileSync(path.join(evidenceDirectory, 'account-explorer.json'), JSON.stringify(explorerReport, null, 2) + '\n', { mode: 0o600 });
    const markdown = [
      '# BoardPrep authentication explorer',
      '',
      'STATUS=' + explorerReport.status,
      'TESTED_COMMIT=' + explorerReport.tested_commit,
      'SEED=' + explorerReport.seed,
      'MODE=' + explorerReport.mode,
      'SCENARIOS=' + explorerReport.scenarios.length,
      '',
      '## Scenarios',
      ...explorerReport.scenarios.map((item) => '- ' + item.id + ': ' + item.result + ' — ' + item.observed),
      '',
      '## Confirmed defects',
      ...explorerReport.confirmed_defects.map((item) => '- ' + item.code + ' at ' + item.route + ' (' + item.scenario + '): ' + item.expected_actual_result),
      explorerReport.confirmed_defects.length ? '' : '- None observed.',
      '',
      'This report contains no usernames, passwords, recovery codes, session IDs, or learner records.',
      '',
    ].join('\n');
    fs.writeFileSync(path.join(evidenceDirectory, 'account-explorer.md'), markdown, { mode: 0o600 });
  };

  try {
    await expect.poll(async () => {
      try {
        return (await page.request.get(AUTH + '/account/login')).status();
      } catch {
        return 0;
      }
    }).toBe(200);

    for (const viewport of [
      { width: 320, height: 640 },
      { width: 360, height: 800 },
      { width: 390, height: 844 },
      { width: 430, height: 932 },
      { width: 768, height: 1024 },
    ]) {
      await page.setViewportSize(viewport);
      await page.goto(AUTH + '/account/login');
      const layout = await page.evaluate(() => {
        const width = document.documentElement.scrollWidth;
        const viewportWidth = document.documentElement.clientWidth;
        const button = document.querySelector('main form button');
        const rect = button?.getBoundingClientRect();
        return {
          width,
          viewportWidth,
          buttonVisible: Boolean(rect && rect.width > 0 && rect.height > 0 && rect.left >= 0 && rect.right <= viewportWidth + 1),
          missingLabels: [...document.querySelectorAll('main input:not([type="hidden"])')].filter((input) => !input.labels?.length).length,
        };
      });
      expect(layout.width).toBeLessThanOrEqual(layout.viewportWidth + 2);
      expect(layout.buttonVisible).toBe(true);
      expect(layout.missingLabels).toBe(0);
      scenario('mobile-login-layout-' + viewport.width, '/account/login', 'No horizontal overflow; primary control visible; fields have labels.');
    }
    await page.setViewportSize({ width: 390, height: 844 });

    const productionRoute = await page.request.get(PUBLIC + '/account/register');
    expect(productionRoute.status()).toBe(404);
    scenario('production-route-exclusion', '/account/register', 'Production fixture returns 404 while nonproduction account preview is available.');

    const anonymousHome = await page.request.get(PUBLIC + '/');
    expect(anonymousHome.status()).toBe(200);
    const unauthorizedProfile = await page.request.get(AUTH + '/account/profile', { maxRedirects: 0 });
    expect(unauthorizedProfile.status()).toBe(303);
    expect(unauthorizedProfile.headers().location).toBe('/account/login');
    const tamperedProfile = await page.request.get(AUTH + '/account/profile?account_id=account-' + 'f'.repeat(32), { maxRedirects: 0 });
    expect(tamperedProfile.status()).toBe(303);
    expect(tamperedProfile.headers().location).toBe('/account/login');
    scenario('anonymous-access-and-account-authorization', '/account/profile', 'Anonymous learning remains available; private profile ignores untrusted account identifiers and redirects to sign-in.');

    const noLinkGet = await page.request.get(AUTH + '/account/link-anonymous', { maxRedirects: 0 });
    const noLinkPost = await page.request.post(AUTH + '/account/link-anonymous', { maxRedirects: 0 });
    expect(noLinkGet.status()).toBe(404);
    expect(noLinkPost.status()).toBe(404);
    scenario('anonymous-data-linking-disabled', '/account/link-anonymous', 'Explicit linking and repeated-link routes are absent until ownership-safe migration is designed.');

    const csrfRejected = await page.request.post(AUTH + '/account/register', {
      form: { username: 'csrf-fixture-' + seed, password: PASSWORD_A, password_confirmation: PASSWORD_A },
    });
    expect((await csrfRejected.text()).includes('form expired')).toBe(true);
    scenario('csrf-registration-rejection', '/account/register', 'A registration POST without a session CSRF token is rejected.');

    const codesA = await register(page, usernameA, PASSWORD_A);
    scenario('registration-recovery-code-generation', '/account/register', 'Registration flow displays one-time recovery codes; values are not persisted in this report.');

    const duplicateContext = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
    contexts.push(duplicateContext);
    const duplicatePage = await duplicateContext.newPage();
    trackPage(duplicatePage);
    const duplicateCodes = await register(duplicatePage, usernameA, PASSWORD_A);
    expect(duplicateCodes.length === 8).toBe(true);
      await expect(duplicatePage.getByText('If this username was available')).toBeVisible();
    scenario('duplicate-registration-enumeration', '/account/register', 'Existing and new names receive the same generic result shape.');
    await duplicateContext.close();

    await page.goto(AUTH + '/account/login');
    await page.locator('#account-username').fill(usernameA);
    await page.locator('#account-password').fill('incorrect account password');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page.getByRole('alert')).toContainText('Sign-in could not be completed');
    scenario('wrong-password-generic-response', '/account/login', 'Wrong password receives a generic sign-in error.');

    const csrfBeforeLogin = await csrf(page);
    const cookieBefore = (await page.context().cookies(AUTH)).find((cookie) => cookie.name === 'BOARDPREP_AUTH_SESSION')?.value;
    if (cookieBefore) reportSensitiveValues.add(cookieBefore);
    await signIn(page, usernameA, PASSWORD_A);
    await expect(page).toHaveURL(/\/account\/profile$/);
    const cookieAfter = (await page.context().cookies(AUTH)).find((cookie) => cookie.name === 'BOARDPREP_AUTH_SESSION')?.value;
    if (cookieAfter) reportSensitiveValues.add(cookieAfter);
    expect(Boolean(cookieBefore && cookieAfter && cookieAfter !== cookieBefore)).toBe(true);
    expect((await csrf(page)) !== csrfBeforeLogin).toBe(true);
    scenario('login-and-session-rotation', '/account/login', 'Valid login succeeds and rotates the session cookie.');

    const replayedCsrf = await page.request.post(AUTH + '/account/deletion-request', {
      form: { csrf_token: csrfBeforeLogin, current_password: PASSWORD_A, confirm_deletion: 'DELETE' },
      maxRedirects: 0,
    });
    expect(replayedCsrf.status()).toBe(303);
    expect((await page.request.get(AUTH + '/account/profile')).status()).toBe(200);
    scenario('rotated-csrf-replay-rejection', '/account/deletion-request', 'A pre-login CSRF token cannot authorize deletion after the authenticated session rotates.');

    if (mode === 'smoke') {
      await page.goto(AUTH + '/account/profile');
      await page.locator('form[action="/account/logout"] button').click();
      await expect(page).toHaveURL(AUTH + '/');
      scenario('logout-authorization', '/account/logout', 'Smoke confirms sign-out and return to anonymous browsing.');
      explorerReport.scenarios_executed = explorerReport.scenarios.length;
      await recordReport('PASS');
      return;
    }

    await page.goto(AUTH + '/profile');
    await page.locator('#preferred-board').selectOption('CSE');
    await page.locator('#profile-cse-level').selectOption('PROFESSIONAL');
    await page.getByRole('button', { name: 'Save exam preferences' }).click();
    await expect(page.locator('.alert-success')).toContainText('saved to your account');
    scenario('authenticated-profile-update', '/profile/preferences', 'Candidate preferences save to the account profile.');

    await completeSyntheticPractice(page);
    await reviewOneSyntheticFlashcard(page);
    scenario('account-practice-history-and-flashcard-progress', '/quiz and /flashcards/review', 'A completed isolated practice session and a rated flashcard review are stored under Account A.');

    await page.goto(AUTH + '/account/profile');
    await page.locator('#export-current-password').fill(PASSWORD_A);
    const downloadPromise = page.waitForEvent('download');
    let exportedA = null;
    await page.getByRole('button', { name: 'Export my account data' }).click();
    const accountDownload = await downloadPromise;
    try {
      expect(accountDownload.suggestedFilename()).toBe('boardprep-account-data.json');
      const exportStream = await accountDownload.createReadStream();
      const exportChunks = [];
      for await (const chunk of exportStream) exportChunks.push(chunk);
      exportedA = JSON.parse(Buffer.concat(exportChunks).toString('utf8'));
      expect(exportedA.format).toBe('boardprep-account-data-v1');
      expect(exportedA.manifest?.record_counts?.quiz_attempts).toBeGreaterThan(0);
      expect(exportedA.manifest?.record_counts?.flashcard_reviews).toBeGreaterThan(0);
      expect(exportedA.manifest.excluded_secrets).toContain('password_hashes_from_exports');
      expect(exportedA.manifest.excluded_secrets).toContain('recovery_code_hashes_from_exports');
      expect(exportedA.account.password_hash).toBeUndefined();
      expect(exportedA.account.recovery_code_hashes).toBeUndefined();
      expect(JSON.stringify(exportedA.records)).not.toContain('session_id');
    } finally {
      await accountDownload.delete().catch(() => undefined);
    }
    scenario('account-export-step-up', '/account/export', 'Password-confirmed JSON export excludes credential and recovery secrets.');

    const accountFile = path.join(authStorage, 'private', 'learner-accounts', 'accounts.json');
    const accountState = JSON.parse(fs.readFileSync(accountFile, 'utf8'));
    const accountA = accountState.accounts[usernameA];
    expect(/^account-[a-f0-9]{32}$/.test(accountA?.account_id || '')).toBe(true);
    reportSensitiveValues.add(accountA.account_id);
    reportSensitiveValues.add(accountA.learner_id);
    const privateBackupRoot = path.join(path.dirname(authStorage), 'account-backup-fixture');
    const restoreParent = path.join(privateBackupRoot, 'restore-parent');
    fs.mkdirSync(restoreParent, { recursive: true, mode: 0o700 });
    fs.chmodSync(privateBackupRoot, 0o700);
    fs.chmodSync(restoreParent, 0o700);
    const backupPath = path.join(privateBackupRoot, 'account-a.bpk');
    const restoreDestination = path.join(restoreParent, 'account-a-restored');
    const beforeBackupHash = collectionSnapshot(authStorage);
    const createdBackup = await runAccountCli(authStorage, 'tools/account-backup.php', [
      '--create', `--account-id=${accountA.account_id}`, `--output=${backupPath}`,
    ]);
    expect(createdBackup.status).toBe('BACKUP_CREATED');
    const archiveText = fs.readFileSync(backupPath, 'utf8');
    expect(!archiveText.includes(PASSWORD_A)).toBe(true);
    expect(!archiveText.includes(codesA[0])).toBe(true);
    const verifiedBackup = await runAccountCli(authStorage, 'tools/account-backup.php', [
      '--verify', `--input=${backupPath}`,
    ]);
    expect(verifiedBackup.status).toBe('BACKUP_VERIFIED');
    const dryRestore = await runAccountCli(authStorage, 'tools/account-restore.php', [
      `--backup=${backupPath}`, `--destination=${restoreDestination}`, '--dry-run',
    ]);
    expect(dryRestore.status).toBe('RESTORE_DRY_RUN_PASS');
    expect(fs.existsSync(restoreDestination)).toBe(false);
    const committedRestore = await runAccountCli(authStorage, 'tools/account-restore.php', [
      `--backup=${backupPath}`, `--destination=${restoreDestination}`, '--commit-isolated-restore',
    ]);
    expect(committedRestore.status).toBe('RESTORE_CREATED_ISOLATED_DESTINATION');
    const restoredState = JSON.parse(fs.readFileSync(path.join(restoreDestination, 'private', 'learner-accounts', 'accounts.json'), 'utf8'));
    expect(restoredState.accounts[usernameA]?.learner_id === accountA.learner_id).toBe(true);
    expect(collectionSnapshot(authStorage) === beforeBackupHash).toBe(true);
    scenario('encrypted-backup-restore-fixture-integrity', 'CLI/account-backup and CLI/account-restore', 'Authenticated backup verification and atomic isolated restore preserve source storage without exposing secrets.');

    await page.goto(AUTH + '/account/profile');
    await page.locator('form[action="/account/logout"] button').click();
    await expect(page).toHaveURL(AUTH + '/');
    const loggedOutPage = await page.request.get(AUTH + '/account/profile');
    expect(loggedOutPage.status()).toBe(200);
    expect(loggedOutPage.url()).toMatch(/\/account\/login$/);
    scenario('logout-authorization', '/account/logout', 'Sign-out rotates the session and private profile redirects for a guest.');

    const contextB = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
    contexts.push(contextB);
    const pageB = await contextB.newPage();
    trackPage(pageB);
    await register(pageB, usernameB, PASSWORD_B);
    await signIn(pageB, usernameB, PASSWORD_B);
    await expect(pageB).toHaveURL(/\/account\/profile$/);
    await pageB.goto(AUTH + '/profile');
    expect(await pageB.locator('#preferred-board').inputValue()).toBe('');
    scenario('account-profile-isolation', '/profile', 'A different account starts with independent profile preferences.');
    await completeSyntheticPractice(pageB);
    await reviewOneSyntheticFlashcard(pageB);
    await pageB.goto(AUTH + '/account/profile');
    await pageB.locator('#export-current-password').fill(PASSWORD_B);
    const exportBPromise = pageB.waitForEvent('download');
    await pageB.getByRole('button', { name: 'Export my account data' }).click();
    const exportBDownload = await exportBPromise;
    const exportBStream = await exportBDownload.createReadStream();
    const exportBChunks = [];
    for await (const chunk of exportBStream) exportBChunks.push(chunk);
    const exportedB = JSON.parse(Buffer.concat(exportBChunks).toString('utf8'));
    const accountStateAfterB = JSON.parse(fs.readFileSync(accountFile, 'utf8'));
    const accountARecord = accountStateAfterB.accounts[usernameA];
    const accountBRecord = accountStateAfterB.accounts[usernameB];
    reportSensitiveValues.add(accountBRecord.account_id);
    reportSensitiveValues.add(accountBRecord.learner_id);
    const accountBAttemptIds = exportedB.records.quiz_attempts.map((record) => record.id);
    const accountAAttemptIds = exportedA.records.quiz_attempts.map((record) => record.id);
    expect(exportedB.manifest.record_counts.quiz_attempts).toBeGreaterThan(0);
    expect(exportedB.manifest.record_counts.flashcard_reviews).toBeGreaterThan(0);
    expect(accountBAttemptIds.some((id) => accountAAttemptIds.includes(id))).toBe(false);
    expect(!JSON.stringify(exportedB.records).includes(accountARecord.learner_id)).toBe(true);
    await exportBDownload.delete().catch(() => undefined);
    scenario('account-b-export-isolation', '/account/export', 'Account B exports its own practice and review data without Account A learner IDs or attempts.');

    const contextC = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
    contexts.push(contextC);
    const pageC = await contextC.newPage();
    trackPage(pageC);
    await signIn(pageC, usernameA, PASSWORD_A);
    await expect(pageC).toHaveURL(/\/account\/profile$/);
    await pageC.goto(AUTH + '/profile');
    expect(await pageC.locator('#preferred-board').inputValue()).toBe('CSE');
    expect(await pageC.locator('#profile-cse-level').inputValue()).toBe('PROFESSIONAL');
    scenario('independent-session-profile-persistence', '/profile', 'A second browser context retrieves saved account preferences.');

    const anonymousContext = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
    contexts.push(anonymousContext);
    const anonymousPage = await anonymousContext.newPage();
    trackPage(anonymousPage);
    await completeSyntheticPractice(anonymousPage);
    const anonymousBeforeResponse = await anonymousPage.request.get(AUTH + '/privacy/export');
    expect(anonymousBeforeResponse.ok()).toBe(true);
    const anonymousBefore = await anonymousBeforeResponse.json();
    expect(anonymousBefore.records?.quiz_attempts?.length).toBeGreaterThan(0);
    const anonymousIdentity = ['attempts', 'weakness', 'flashcard_state'].flatMap((name) => {
      const records = JSON.parse(fs.readFileSync(path.join(authStorage, name + '.json'), 'utf8'));
      return records.map((record) => record?.user_id).filter((id) => typeof id === 'string' && /^learner-/.test(id));
    })[0];
    if (anonymousIdentity) reportSensitiveValues.add(anonymousIdentity);
    scenario('anonymous-learner-c-remains-separate', '/privacy/export', 'Anonymous learner C completes and exports its own practice history without an account.');

    await page.goto(AUTH + '/account/recover');
    await page.locator('#recovery-username').fill(usernameA);
    await page.locator('#recovery-code').fill(codesA[0]);
    await page.locator('#new-account-password').fill(PASSWORD_A_RECOVERED);
    await page.locator('#new-account-password-confirmation').fill(PASSWORD_A_RECOVERED);
    await page.getByRole('button', { name: 'Use recovery code' }).click();
    await expect(page.getByRole('status')).toContainText('recovery code has been consumed');
    scenario('single-use-recovery-code', '/account/recover', 'Saved code recovers the account once.');

    const recoveryToken = await csrf(page);
    const replay = await page.request.post(AUTH + '/account/recover', {
      form: {
        csrf_token: recoveryToken,
        username: usernameA,
        recovery_code: codesA[0],
        new_password: 'BoardPrep batch 692 replay phrase',
        new_password_confirmation: 'BoardPrep batch 692 replay phrase',
      },
    });
    expect((await replay.text()).includes('Recovery could not be completed')).toBe(true);
    scenario('recovery-replay-rejection', '/account/recover', 'Consumed recovery code cannot be replayed.');

    await signIn(page, usernameA, PASSWORD_A_RECOVERED);
    await expect(page).toHaveURL(/\/account\/profile$/);
    await page.goto(AUTH + '/account/security');
    await page.locator('#current-password').fill(PASSWORD_A_RECOVERED);
    await page.locator('#new-password').fill(PASSWORD_A_CHANGED);
    await page.locator('#new-password-confirmation').fill(PASSWORD_A_CHANGED);
    await page.getByRole('button', { name: 'Change password' }).click();
    await expect(page).toHaveURL(/\/account\/security$/);
    await expect(page.getByRole('alert')).toContainText('password changed');
    await pageC.goto(AUTH + '/account/profile');
    await expect(pageC).toHaveURL(/\/account\/login$/);
    scenario('password-change-revokes-other-session', '/account/password', 'Password update invalidates a second active session.');

    await page.goto(AUTH + '/account/profile');
    await page.locator('form[action="/account/logout"] button').click();
    await expect(page).toHaveURL(AUTH + '/');
    const deletionPassword = 'BoardPrep batch 693 synthetic deletion phrase';
    const deletionCodes = await register(page, usernameDelete, deletionPassword);
    expect(deletionCodes.length === 8).toBe(true);
    await signIn(page, usernameDelete, deletionPassword);
    await completeSyntheticPractice(page);
    await reviewOneSyntheticFlashcard(page);
    const preDeletionState = JSON.parse(fs.readFileSync(accountFile, 'utf8'));
    const accountD = preDeletionState.accounts[usernameDelete];
    expect(/^account-[a-f0-9]{32}$/.test(accountD?.account_id || '')).toBe(true);
    reportSensitiveValues.add(accountD.account_id);
    reportSensitiveValues.add(accountD.learner_id);
    const dLearnerId = accountD.learner_id;
    const beforeDeletionOthers = collectionSnapshot(authStorage, dLearnerId);
    const ownedCountsBefore = Object.fromEntries(['attempts', 'weakness', 'flashcard_state'].map((name) => {
      const records = JSON.parse(fs.readFileSync(path.join(authStorage, name + '.json'), 'utf8'));
      return [name, records.filter((record) => record?.user_id === dLearnerId).length];
    }));
    expect(Object.values(ownedCountsBefore).every((count) => count > 0)).toBe(true);
    const globalQuestionHash = crypto.createHash('sha256').update(fs.readFileSync(path.join(authStorage, 'questions.json'))).digest('hex');
    await page.goto(AUTH + '/account/profile');
    await page.getByText('Request account deletion', { exact: true }).click();
    await page.locator('#deletion-current-password').fill(deletionPassword);
    await page.locator('#deletion-confirmation').fill('DELETE');
    await page.getByRole('button', { name: 'Delete account and application data' }).click();
    await expect(page.getByRole('heading', { name: 'Application account data deleted' })).toBeVisible();
    const postDeletionState = JSON.parse(fs.readFileSync(accountFile, 'utf8'));
    const tombstone = postDeletionState.accounts['deleted:' + crypto.createHash('sha256').update(accountD.account_id).digest('hex')];
    expect(tombstone?.status).toBe('deletion_completed');
    expect(tombstone?.password_hash).toBeUndefined();
    expect(postDeletionState.accounts[usernameDelete]).toBeUndefined();
    for (const name of Object.keys(ownedCountsBefore)) {
      const records = JSON.parse(fs.readFileSync(path.join(authStorage, name + '.json'), 'utf8'));
      expect(records.filter((record) => record?.user_id === dLearnerId)).toHaveLength(0);
    }
    expect(collectionSnapshot(authStorage, dLearnerId)).toBe(beforeDeletionOthers);
    expect(crypto.createHash('sha256').update(fs.readFileSync(path.join(authStorage, 'questions.json'))).digest('hex')).toBe(globalQuestionHash);
    scenario('deletion-removes-owned-records-and-revokes-credentials', '/account/deletion-request', 'Confirmed deletion removes all D records and recovery credentials after clean verification.');

    await signIn(page, usernameDelete, deletionPassword);
    await expect(page.getByRole('alert')).toContainText('Sign-in could not be completed');
    scenario('reauthenticated-deletion-request', '/account/deletion-request', 'The explicitly confirmed, password-reauthenticated deletion makes future sign-in fail.');
    await signIn(pageC, usernameA, PASSWORD_A_CHANGED);
    await expect(pageC).toHaveURL(/\/account\/profile$/);
    await pageB.goto(AUTH + '/account/profile');
    await expect(pageB).toHaveURL(/\/account\/profile$/);
    const anonymousAfterResponse = await anonymousPage.request.get(AUTH + '/privacy/export');
    expect(anonymousAfterResponse.ok()).toBe(true);
    const anonymousAfter = await anonymousAfterResponse.json();
    const anonymousRecordsUnchanged = crypto.createHash('sha256')
      .update(JSON.stringify(anonymousAfter.records)).digest('hex')
      === crypto.createHash('sha256').update(JSON.stringify(anonymousBefore.records)).digest('hex');
    expect(anonymousRecordsUnchanged).toBe(true);
    scenario('account-a-b-and-anonymous-c-survive-d-deletion', '/account/deletion-request', 'Independent A/B accounts and anonymous learner C retain their records after D is deleted.');
    scenario('expired-and-tampered-account-requests-rejected', '/account/profile?account_id=tampered', 'Tampered account identifiers are ignored and the stale pre-password-change session is redirected to sign-in.');

    await register(page, usernameLimit, 'BoardPrep batch 692 rate-limit phrase');
    const loginPage = await page.request.get(AUTH + '/account/login');
    const loginHtml = await loginPage.text();
    const tokenMatch = loginHtml.match(/name="csrf_token" value="([^"]+)"/);
    expect(tokenMatch).toBeTruthy();
    for (let attempt = 0; attempt < 8; attempt += 1) {
      const response = await page.request.post(AUTH + '/account/login', {
        form: { csrf_token: tokenMatch[1], username: usernameLimit, password: 'wrong password' },
      });
      expect(response.status()).toBe(200);
    }
    const blocked = await page.request.post(AUTH + '/account/login', {
      form: { csrf_token: tokenMatch[1], username: usernameLimit, password: 'BoardPrep batch 692 rate-limit phrase' },
    });
    expect((await blocked.text()).includes('Sign-in could not be completed')).toBe(true);
    scenario('login-rate-limit', '/account/login', 'Repeated guesses are rejected with the same generic response.');

    const reportText = JSON.stringify(explorerReport);
    expect([...reportSensitiveValues].some((value) => value.length >= 4 && reportText.includes(value))).toBe(false);
    scenario('qa-artifact-secret-exclusion', 'account-explorer.json', 'Passwords, recovery codes, encryption passphrases, cookies, and private learner values are absent from the sanitized report.');

    explorerReport.scenarios_executed = explorerReport.scenarios.length;
    await recordReport('PASS');
  } catch (error) {
    explorerReport.scenarios_executed = explorerReport.scenarios.length;
    await recordReport('FAIL', error);
    throw error;
  } finally {
    for (const context of contexts) await context.close().catch(() => undefined);
  }
});
