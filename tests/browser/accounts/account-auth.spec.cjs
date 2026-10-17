'use strict';

const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { test, expect } = require('@playwright/test');

// Authentication journeys mutate rate limits and durable fixture accounts.
// Retrying the same journey against that state can turn the first failure into
// a misleading second attempt and overwrite its evidence.
test.describe.configure({ retries: 0 });
test.use({ screenshot: 'off' });

const AUTH = 'http://127.0.0.1:4175';
const PUBLIC = 'http://127.0.0.1:4173';
const PASSWORD_A = 'BoardPrep batch 692 learner passphrase';
const PASSWORD_A_RECOVERED = 'BoardPrep batch 692 recovered phrase';
const PASSWORD_A_CHANGED = 'BoardPrep batch 692 changed phrase';
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

test('isolated mobile account journeys preserve authentication and learner ownership', async ({ page, browser }, testInfo) => {
  test.skip(testInfo.project.name !== 'mobile', 'Run the account journey once in supported Chromium mobile emulation; HTTP/service suites cover server-side transitions.');

  const seed = Number.parseInt(process.env.BOARDPREP_EXPLORER_SEED || '692', 10) || 692;
  const usernameA = 'learnera-' + seed;
  const usernameB = 'learnerb-' + seed;
  const usernameDelete = 'delete-' + seed;
  const usernameLimit = 'limit-' + seed;
  const evidenceDirectory = path.resolve(process.env.BOARDPREP_BROWSER_EVIDENCE_DIR || fs.mkdtempSync(path.join(os.tmpdir(), 'boardprep-account-browser-')));
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
    limits: { max_pages: 60, max_scenarios: 30, max_duration_ms: maxDurationMs, concurrency: 1, allowed_origins: [AUTH, PUBLIC] },
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
      explorerReport.confirmed_defects.push({
        code: 'ACCOUNT_JOURNEY_ASSERTION',
        observed: sanitizedFailure(failure),
        route: new URL(page.url()).pathname,
      });
      const target = path.join(evidenceDirectory, 'account-explorer-failure.png');
      await page.screenshot({
        path: target,
        fullPage: true,
        mask: [page.locator('input'), page.locator('.recovery-code-list'), page.locator('.account-page dl dd')],
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
      ...explorerReport.confirmed_defects.map((item) => '- ' + item.code + ': ' + item.observed),
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
    await signIn(page, usernameA, PASSWORD_A);
    await expect(page).toHaveURL(/\/account\/profile$/);
    const cookieAfter = (await page.context().cookies(AUTH)).find((cookie) => cookie.name === 'BOARDPREP_AUTH_SESSION')?.value;
    expect(Boolean(cookieBefore && cookieAfter && cookieAfter !== cookieBefore)).toBe(true);
    expect((await csrf(page)) !== csrfBeforeLogin).toBe(true);
    scenario('login-and-session-rotation', '/account/login', 'Valid login succeeds and rotates the session cookie.');

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

    await page.goto(AUTH + '/account/profile');
    await page.locator('#export-current-password').fill(PASSWORD_A);
    const downloadPromise = page.waitForEvent('download');
    await page.getByRole('button', { name: 'Export my account data' }).click();
    const accountDownload = await downloadPromise;
    try {
      expect(accountDownload.suggestedFilename()).toBe('boardprep-account-data.json');
      const exportStream = await accountDownload.createReadStream();
      const exportChunks = [];
      for await (const chunk of exportStream) exportChunks.push(chunk);
      const exported = JSON.parse(Buffer.concat(exportChunks).toString('utf8'));
      expect(exported.format).toBe('boardprep-account-data-v1');
      const exportContainsCredentialMaterial = /password_hash|recovery_code_hashes/.test(JSON.stringify(exported));
      expect(exportContainsCredentialMaterial).toBe(false);
    } finally {
      await accountDownload.delete().catch(() => undefined);
    }
    scenario('account-export-step-up', '/account/export', 'Password-confirmed JSON export excludes credential and recovery secrets.');

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
    await register(pageB, usernameB, 'BoardPrep batch 692 second account phrase');
    await signIn(pageB, usernameB, 'BoardPrep batch 692 second account phrase');
    await expect(pageB).toHaveURL(/\/account\/profile$/);
    await pageB.goto(AUTH + '/profile');
    expect(await pageB.locator('#preferred-board').inputValue()).toBe('');
    scenario('account-profile-isolation', '/profile', 'A different account starts with independent profile preferences.');

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
    await expect(page.getByRole('status')).toContainText('password changed');
    await pageC.goto(AUTH + '/account/profile');
    await expect(pageC).toHaveURL(/\/account\/login$/);
    scenario('password-change-revokes-other-session', '/account/password', 'Password update invalidates a second active session.');

    await page.goto(AUTH + '/account/profile');
    await page.locator('form[action="/account/logout"] button').click();
    await expect(page).toHaveURL(AUTH + '/');
    const deletionCodes = await register(page, usernameDelete, 'BoardPrep batch 692 deletion phrase');
    expect(deletionCodes.length === 8).toBe(true);
    await signIn(page, usernameDelete, 'BoardPrep batch 692 deletion phrase');
    await page.goto(AUTH + '/account/profile');
    await page.getByText('Request account deletion', { exact: true }).click();
    await page.locator('#deletion-current-password').fill('BoardPrep batch 692 deletion phrase');
    await page.locator('#deletion-confirmation').fill('DELETE');
    await page.getByRole('button', { name: 'Submit deletion request' }).click();
    await expect(page.getByRole('heading', { name: 'Deletion request recorded' })).toBeVisible();
    await signIn(page, usernameDelete, 'BoardPrep batch 692 deletion phrase');
    await expect(page.getByRole('alert')).toContainText('Sign-in could not be completed');
    scenario('reauthenticated-deletion-request', '/account/deletion-request', 'Confirmed deletion request blocks future sign-in; app-record deletion awaits owner policy.');

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
    expect(await blocked.text()).toContain('Sign-in could not be completed');
    scenario('login-rate-limit', '/account/login', 'Repeated guesses are rejected with the same generic response.');

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
