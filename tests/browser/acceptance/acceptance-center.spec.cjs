'use strict';

const { test, expect } = require('../fixtures/test.cjs');

const centerUrl = 'http://127.0.0.1:4174/developer/acceptance';
const viewportTargets = [
  { width: 320, height: 568 },
  { width: 360, height: 800 },
  { width: 390, height: 844 },
  { width: 412, height: 915 },
  { width: 768, height: 1024 },
];

async function openCenter(page) {
  await page.goto(centerUrl);
  await expect(page.getByRole('heading', { name: 'Try BoardPrep as a learner' })).toBeVisible();
  await expect(page.locator('#buildSha')).toHaveText(/^[0-9a-f]{40}$/);
}

async function openCaseGuide(page, caseId) {
  const row = page.locator('.case-list-item').filter({ hasText: caseId });
  await row.getByRole('button', { name: 'Open guide' }).click();
  await expect(page.locator('#caseIdLabel')).toHaveText(caseId);
}

test.describe('guided Human Acceptance Center', () => {
  test('loads the canonical twelve scenarios and shows a one-tap next test', async ({ page }) => {
    await openCenter(page);
    await expect(page.locator('#launchNextTest')).toBeEnabled();
    await expect(page.locator('#launchNextTest')).toContainText('Launch next test');
    await page.getByRole('button', { name: 'All tests' }).click();
    await expect(page.locator('.case-list-item')).toHaveCount(12);
    for (let number = 1; number <= 12; number += 1) {
      await expect(page.locator('.case-list-item').nth(number - 1)).toContainText(`MVP-${String(number).padStart(2, '0')}`);
    }
    await openCaseGuide(page, 'MVP-12');
    await expect(page.getByRole('heading', { name: 'Use keyboard and accessibility support' })).toBeVisible();
    await expect(page.getByText(/Focus order and announcements are understandable/)).toBeVisible();
  });

  test('opens real learner routes in one separate tab and keeps the guide available', async ({ page }) => {
    await openCenter(page);
    await page.getByRole('button', { name: 'All tests' }).click();
    await openCaseGuide(page, 'MVP-01');
    const [learnerPage] = await Promise.all([
      page.waitForEvent('popup'),
      page.getByRole('link', { name: 'Open Exam selection in the learner tab' }).click(),
    ]);
    await expect(learnerPage).toHaveURL(/\/exams$/);
    await expect(learnerPage.locator('body')).toContainText(/exam|board|licensure/i);
    await expect(page.locator('#caseTitle')).toHaveText('Find your way into practice');
    await expect(page.locator('#routesOpened')).toContainText('Exam selection');
    await page.getByRole('button', { name: /Continue \/ return to this test guide/ }).click();
    await expect(page.locator('#resultTitle')).toBeFocused();
    await page.getByRole('link', { name: 'Open Practice setup in the learner tab' }).click();
    await expect(learnerPage).toHaveURL(/\/quiz\?exam=LET$/);
  });

  test('requires the tester to confirm a pass and rejects a missing CSRF token', async ({ page }) => {
    await openCenter(page);
    await page.getByRole('button', { name: 'Open test guide' }).click();
    await page.getByLabel(/I tried it and it worked for me/).check();
    await page.getByRole('button', { name: 'Save this result' }).click();
    await expect(page.locator('#notice')).toContainText(/confirm that you personally tried/i);
    await expect(page.locator('#caseOutcomeBadge')).toHaveText('NOT RUN');
    const csrfResponse = await page.request.post('http://127.0.0.1:4174/developer/acceptance/update', {
      data: { action: 'outcome', case_id: 'MVP-01', result: 'PASS', pass_confirmed: true },
    });
    const csrfStatus = csrfResponse.status();
    const csrfResult = await csrfResponse.json();
    expect(csrfResult.ok).toBe(false);
    expect(csrfResult.error).toContain('page expired');
    expect(csrfStatus).toBe(403);
    await page.request.get('http://127.0.0.1:4173/');
    const productionRoute = await page.request.get('http://127.0.0.1:4173/developer/acceptance');
    expect(productionRoute.status()).toBe(404);
  });

  test('autosaves a draft and restores it after reload without changing the human result', async ({ page }) => {
    await openCenter(page);
    await page.getByRole('button', { name: 'All tests' }).click();
    await openCaseGuide(page, 'MVP-03');
    const comment = 'I saw this on my phone — keep the spacing.\nThe second line must stay exactly.';
    await page.locator('[data-feedback-fields="case"] [data-field="comments"]').fill(comment);
    await expect(page.locator('#draftStatus')).toContainText('Draft saved');
    await expect(page.locator('#caseOutcomeBadge')).toHaveText('NOT RUN');
    await page.reload();
    await expect(page.locator('#buildSha')).toHaveText(/^[0-9a-f]{40}$/);
    await page.getByRole('button', { name: 'All tests' }).click();
    await openCaseGuide(page, 'MVP-03');
    await expect(page.locator('[data-feedback-fields="case"] [data-field="comments"]')).toHaveValue(comment);
    await expect(page.locator('#caseOutcomeBadge')).toHaveText('NOT RUN');
  });

  test('warns when the saved session belongs to a different build', async ({ page }) => {
    await page.route('**/developer/acceptance/data', async (route) => {
      const response = await route.fetch();
      const payload = await response.json();
      payload.current_build_sha = '0'.repeat(40);
      await route.fulfill({ response, json: payload });
    });
    await page.goto(centerUrl);
    await expect(page.locator('#buildSha')).toHaveText(/^[0-9a-f]{40}$/);
    await expect(page.locator('#buildWarning')).toBeVisible();
    await expect(page.locator('#buildWarning')).toContainText('running app is 000000000000');
    await expect(page.locator('#buildWarning')).toContainText(/session began on/);
  });

  test('saves multiple observations and exports exact feedback as Markdown and JSON', async ({ page }) => {
    await openCenter(page);
    await page.getByRole('button', { name: 'Open test guide' }).click();
    await page.getByRole('button', { name: 'Save this note' }).click();
    await expect(page.locator('#notice')).toContainText('Add a comment or another detail');
    await expect(page.locator('#savedObservations .observation-card')).toHaveCount(0);
    const comment = 'Keep this | exact **comment**.\nA literal ``` code fence and <tag>.';
    await page.locator('[data-feedback-fields="case"] [data-field="category"]').selectOption('BUG');
    await page.locator('[data-feedback-fields="case"] [data-field="priority"]').selectOption('HIGH');
    await page.locator('[data-feedback-fields="case"] [data-field="comments"]').fill(comment);
    await page.locator('[data-feedback-fields="case"] [data-field="expected_behavior"]').fill('I expected clear feedback.');
    await page.locator('[data-feedback-fields="case"] [data-field="actual_behavior"]').fill('I saw no feedback.');
    await page.locator('[data-feedback-fields="case"] [data-field="reproduction_steps"]').fill('Open the page, then tap the button.');
    await page.getByRole('button', { name: 'Save this note' }).click();
    await expect(page.locator('#savedObservations')).toContainText(comment);
    await page.locator('[data-feedback-fields="case"] [data-field="category"]').selectOption('IMPROVEMENT');
    await page.locator('[data-feedback-fields="case"] [data-field="comments"]').fill('This suggestion does not make the test a failure.');
    await page.getByRole('button', { name: 'Save this note' }).click();
    await expect(page.locator('#savedObservations .observation-card')).toHaveCount(2);
    await page.getByRole('button', { name: 'Review & export' }).click();
    await page.getByRole('button', { name: 'Refresh report preview' }).click();
    await expect(page.locator('#reportPreview')).toContainText(comment);
    await expect(page.locator('#reportPreview')).toContainText('ALL_REPORTED_BUGS');
    await expect(page.locator('#reportPreview')).toContainText('SUMMARY_FOR_NEXT_CODEX_BATCH');
    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.getByRole('button', { name: 'Download JSON' }).click(),
    ]);
    const jsonPath = await download.path();
    const report = JSON.parse(require('node:fs').readFileSync(jsonPath, 'utf8'));
    expect(report.PROJECT).toBe('BoardPrep');
    expect(report.REPORT_TYPE).toBe('HUMAN_ACCEPTANCE_FEEDBACK');
    expect(report.REPORT_SCHEMA_VERSION).toBe('1.0');
    expect(report.TESTS_TOTAL).toBe(12);
    expect(report.TESTS_NOT_RUN).toBe(12);
    expect(report.CASE_RESULTS[0].OBSERVATIONS[0].comments).toBe(comment);
    expect(report.CASE_RESULTS[0].OBSERVATIONS[0].expected_behavior).toBe('I expected clear feedback.');
    expect(report.CASE_RESULTS[0].OBSERVATIONS[0].actual_behavior).toBe('I saw no feedback.');
    expect(report.CASE_RESULTS[0].OBSERVATIONS[0].reproduction_steps).toBe('Open the page, then tap the button.');
    expect(report.CASE_RESULTS[0].EVIDENCE_REFERENCES).toEqual([]);
    expect(report.VIEWPORT).toMatch(/^\d+x\d+ CSS px$/);
    expect(report.OS_AND_VERSION).toMatch(/\S+/);
    expect(report.BROWSER).toMatch(/\S+/);
    expect(report.ALL_REPORTED_BUGS).toHaveLength(1);
    expect(report.TESTS_PASSED).toBe(0);
    expect(report.OVERALL_HUMAN_ACCEPTANCE_STATUS).toBe('NOT_RUN');
    const [markdownDownload] = await Promise.all([
      page.waitForEvent('download'),
      page.getByRole('button', { name: 'Download Markdown' }).click(),
    ]);
    const markdown = require('node:fs').readFileSync(await markdownDownload.path(), 'utf8');
    expect(markdown).toContain('REPORT_TYPE=HUMAN_ACCEPTANCE_FEEDBACK');
    expect(markdown).toContain('VIEWPORT=');
    expect(markdown).toContain('CASE_ID=MVP-01');
    expect(markdown).toContain('CASE_TITLE=Find your way into practice');
    expect(markdown).toContain('HUMAN_RESULT=NOT_RUN');
    expect(markdown).toContain('FEEDBACK_CATEGORY=BUG');
    expect(markdown).toContain('PRIORITY=HIGH');
    expect(markdown).toContain('COMMENTS');
    expect(markdown).toContain('EXPECTED_BEHAVIOR');
    expect(markdown).toContain('ACTUAL_BEHAVIOR');
    expect(markdown).toContain('SUGGESTED_IMPROVEMENT');
    expect(markdown).toContain('REPRODUCTION_STEPS');
    expect(markdown).toContain('EVIDENCE_REFERENCES');
    expect(markdown).toContain(comment);
    expect(markdown).toContain('I expected clear feedback.');
    expect(markdown).toContain('I saw no feedback.');
    expect(markdown).toContain('Open the page, then tap the button.');
    expect(markdown).toContain('UNRESOLVED_TESTS');
  });

  test('supports browser clipboard, clipboard fallback, and the system share API when available', async ({ page }) => {
    await page.goto(centerUrl);
    await page.addInitScript(() => {
      Object.defineProperty(navigator, 'share', {
        configurable: true,
        value: async (payload) => { window.__sharedAcceptanceReport = payload.text; },
      });
    });
    await page.reload();
    await page.getByRole('button', { name: 'Review & export' }).click();
    await page.getByRole('button', { name: 'Refresh report preview' }).click();
    await page.context().grantPermissions(['clipboard-read', 'clipboard-write'], { origin: 'http://127.0.0.1:4174' });
    await page.getByRole('button', { name: /Copy report/ }).click();
    const copied = await page.evaluate(() => navigator.clipboard.readText());
    expect(copied).toContain('PROJECT=BoardPrep');
    expect(copied).toContain('OVERALL_HUMAN_ACCEPTANCE_STATUS=NOT_RUN');
    await page.getByRole('button', { name: 'Share report' }).click();
    await expect(page.locator('#exportStatus')).toContainText('browser share action completed');
    expect(await page.evaluate(() => window.__sharedAcceptanceReport)).toContain('HUMAN_ACCEPTANCE_FEEDBACK');
  });

  test('falls back to the selected report text when browser clipboard access is denied', async ({ page }) => {
    await page.addInitScript(() => {
      Object.defineProperty(navigator, 'clipboard', {
        configurable: true,
        value: { writeText: async () => { throw new Error('clipboard denied'); } },
      });
      document.execCommand = (command) => {
        if (command !== 'copy') return false;
        window.__fallbackCopiedText = document.activeElement?.value || '';
        return true;
      };
    });
    await openCenter(page);
    await page.getByRole('button', { name: 'Review & export' }).click();
    await page.getByRole('button', { name: 'Refresh report preview' }).click();
    await page.getByRole('button', { name: /Copy report/ }).click();
    await expect(page.locator('#exportStatus')).toContainText('Copied. Paste it into ChatGPT');
    expect(await page.evaluate(() => window.__fallbackCopiedText)).toContain('PROJECT=BoardPrep');
  });

  test('autosaves general notes and groups explicit bugs and improvement requests', async ({ page }) => {
    await openCenter(page);
    await page.getByRole('button', { name: 'General feedback' }).click();
    const form = page.locator('[data-feedback-fields="general"]');
    await form.locator('[data-field="category"]').selectOption('BUG');
    await form.locator('[data-field="comments"]').fill('The separate privacy tab is difficult to find.');
    await expect(page.locator('#generalDraftStatus')).toContainText('Draft saved');
    await page.getByRole('button', { name: 'Save this note' }).click();
    await expect(page.locator('#generalObservations .observation-card')).toHaveCount(1);
    await form.locator('[data-field="category"]').selectOption('OTHER');
    await form.locator('[data-field="suggested_improvement"]').fill('Place privacy controls beside the session actions.');
    await page.getByRole('button', { name: 'Save this note' }).click();
    await page.getByRole('button', { name: 'Review & export' }).click();
    await page.getByRole('button', { name: 'Refresh report preview' }).click();
    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.getByRole('button', { name: 'Download JSON' }).click(),
    ]);
    const report = JSON.parse(require('node:fs').readFileSync(await download.path(), 'utf8'));
    expect(report.GENERAL_FEEDBACK).toHaveLength(2);
    expect(report.ALL_REPORTED_BUGS).toHaveLength(1);
    expect(report.ALL_REQUESTED_IMPROVEMENTS).toHaveLength(1);
    expect(report.CASE_RESULTS.every((item) => item.HUMAN_RESULT === 'NOT_RUN')).toBe(true);
    await page.reload();
    await page.getByRole('button', { name: 'General feedback' }).click();
    await expect(page.locator('#generalObservations .observation-card')).toHaveCount(2);
  });

  test('shows all acceptance controls without horizontal overflow at the five target widths', async ({ page }) => {
    await openCenter(page);
    await page.getByRole('button', { name: 'Open test guide' }).click();
    await page.locator('[data-feedback-fields="case"] [data-field="comments"]').fill('A harmless responsive-layout fixture note.');
    await page.getByRole('button', { name: 'Save this note' }).click();
    for (const viewport of viewportTargets) {
      await page.setViewportSize(viewport);
      await expect(page.locator('#viewportInfo')).toHaveValue(`${viewport.width}x${viewport.height} CSS px`);
      const measurements = await page.evaluate(() => {
        const visible = (element) => element.getClientRects().length > 0 && getComputedStyle(element).visibility !== 'hidden';
        const controls = [...document.querySelectorAll('.ac-shell button, .ac-shell a, .ac-shell input:not([type=radio]):not([type=checkbox]), .ac-shell select, .ac-shell textarea')].filter(visible);
        const tooSmall = controls.filter((element) => element.getBoundingClientRect().height < 44)
          .map((element) => element.textContent?.trim() || element.getAttribute('aria-label') || element.tagName);
        const unlabeled = [...document.querySelectorAll('.ac-shell input, .ac-shell select, .ac-shell textarea')]
          .filter(visible)
          .filter((element) => !element.labels?.length && !element.getAttribute('aria-label') && !element.getAttribute('aria-labelledby'));
        return { documentWidth: document.documentElement.scrollWidth, viewportWidth: innerWidth, tooSmall, unlabeled: unlabeled.length };
      });
      expect(measurements.documentWidth, `overflow at ${viewport.width}px`).toBeLessThanOrEqual(measurements.viewportWidth + 2);
      expect(measurements.tooSmall, `small controls at ${viewport.width}px`).toEqual([]);
      expect(measurements.unlabeled, `unlabeled fields at ${viewport.width}px`).toBe(0);
    }
  });

  test('keeps feedback isolated between browser owners and preserves historical sessions', async ({ browser }) => {
    const firstContext = await browser.newContext();
    let firstPage = await firstContext.newPage();
    await openCenter(firstPage);
    const firstSession = await firstPage.locator('#savedSessions option').first().getAttribute('value');
    firstPage.once('dialog', (dialog) => dialog.accept());
    await firstPage.getByRole('button', { name: 'Start a new session' }).click();
    await expect(firstPage.locator('#savedSessions option')).toHaveCount(2);
    const secondSession = await firstPage.locator('#savedSessions').inputValue();
    expect(secondSession).not.toBe(firstSession);
    await firstPage.close();
    firstPage = await firstContext.newPage();
    await openCenter(firstPage);
    await expect(firstPage.locator('#savedSessions option')).toHaveCount(2);
    await expect(firstPage.locator('#savedSessions')).toHaveValue(secondSession);
    const secondContext = await browser.newContext();
    const secondPage = await secondContext.newPage();
    await openCenter(secondPage);
    await expect(secondPage.locator('#savedSessions option')).toHaveCount(1);
    await expect(secondPage.locator('#progressText')).toHaveText('0 of 12 tests have a recorded result');
    await firstContext.close();
    await secondContext.close();
  });

  test('shows keyboard focus and keeps feedback entry usable without mouse-only controls', async ({ page }) => {
    await openCenter(page);
    await page.keyboard.press('Tab');
    await expect(page.locator('.skip-link')).toBeFocused();
    const focusStyle = await page.locator('.skip-link').evaluate((element) => {
      const style = getComputedStyle(element);
      return { outlineStyle: style.outlineStyle, outlineWidth: style.outlineWidth };
    });
    expect(focusStyle.outlineStyle).not.toBe('none');
    await page.getByRole('button', { name: 'General feedback' }).focus();
    await page.keyboard.press('Enter');
    await expect(page.getByRole('heading', { name: 'General feedback' })).toBeVisible();
    await expect(page.locator('[data-feedback-fields="general"] [data-field="comments"]')).toBeVisible();
  });
});
