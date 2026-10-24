'use strict';

const fs = require('node:fs');
const path = require('node:path');
const {
  test,
  expect,
  assertPageHealthy,
  tabToKeyboardFocus,
  recordBrowserEvidence,
} = require('../fixtures/test.cjs');

const developerBase = process.env.BOARDPREP_DEVELOPER_BROWSER_URL || 'http://127.0.0.1:4174';
const productionBase = process.env.BOARDPREP_BROWSER_URL || 'http://127.0.0.1:4173';
let questions;

function canonicalQuestions() {
  if (!questions) {
    questions = JSON.parse(fs.readFileSync(
      path.resolve(__dirname, '../../..', 'storage', 'questions.json'),
      'utf8'
    ));
  }
  return questions;
}

let sourceIds;
function registeredSourceIds() {
  if (!sourceIds) {
    const registry = JSON.parse(fs.readFileSync(
      path.resolve(__dirname, '../../..', 'storage', 'sources.json'),
      'utf8'
    ));
    sourceIds = new Set(registry.filter((record) => record && record.id).map((record) => String(record.id)));
  }
  return sourceIds;
}

function representativeQuestion(predicate = () => true) {
  const question = canonicalQuestions().find((item) => (
    item && item.status !== 'archived'
    && String(item.question || '').trim() !== ''
    && Array.isArray(item.choices)
    && item.choices.length > 1
    && predicate(item)
  ));
  if (!question) throw new Error('No canonical question matched the browser fixture requirement.');
  return question;
}

const scopedQuestion = () => representativeQuestion((item) => (
  item.taxonomy && item.taxonomy.board_id && item.taxonomy.subject_id
));
const sourceQuestion = () => representativeQuestion((item) => (
  Array.isArray(item.source_ids)
  && item.source_ids.some((id) => registeredSourceIds().has(String(id).trim()))
));

function repositoryUrl(parameters = {}) {
  const url = new URL('/question-editor', developerBase);
  for (const [key, value] of Object.entries(parameters)) {
    if (value !== undefined && value !== null && String(value) !== '') {
      url.searchParams.set(key, String(value));
    }
  }
  return url.toString();
}

async function openRepository(page, parameters = {}) {
  const response = await page.goto(repositoryUrl(parameters));
  expect(response?.status()).toBe(200);
  await expect(page.getByRole('heading', { name: 'Question Inventory' })).toBeVisible();
  return response;
}

async function applyFilters(page) {
  await page.getByRole('button', { name: 'Apply Filters' }).click();
  await expect(page.getByRole('heading', { name: 'Question Inventory' })).toBeVisible();
}

async function resultIds(page) {
  return page.locator('.inventory-item').evaluateAll((cards) => cards.map((card) => {
    const label = card.querySelector('p strong')?.textContent || '';
    return label.replace(/^\s*#/, '').trim();
  }));
}

async function inspectFirstResult(page) {
  await page.locator('.inventory-item').first().getByRole('link', { name: 'Inspect' }).click();
  await expect(page.getByRole('heading', { name: /^Question #/ })).toBeVisible();
}

test.describe('Developer Question Repository browser acceptance', () => {
  test('REPO-01 repository loads and reports the canonical total', async ({ page }, testInfo) => {
    await openRepository(page);
    const canonicalCount = canonicalQuestions().length;
    await expect(page.locator('body')).toContainText(`Canonical questions in repository: ${canonicalCount}`);
    await expect(page.locator('.inventory-item').first()).toBeVisible();
    await assertPageHealthy(page, testInfo, 'developer-repository-load');
    recordBrowserEvidence('developer-repository', {
      canonical_questions: canonicalCount,
      first_page_rows: await page.locator('.inventory-item').count(),
      result: 'PASS',
    }, testInfo);
  });

  test('REPO-02 exact canonical ID search returns the matching record', async ({ page }) => {
    const question = representativeQuestion();
    await openRepository(page);
    await page.locator('#question-search').fill(String(question.id));
    await applyFilters(page);
    await expect(page.locator('.inventory-item')).toHaveCount(1);
    await expect(page.locator('.inventory-item').first().locator('p strong')).toHaveText(`#${question.id}`);
  });

  test('REPO-03 text search finds the canonical stem', async ({ page }) => {
    const question = scopedQuestion();
    const phrase = String(question.question).trim().slice(0, 38);
    await openRepository(page);
    await page.locator('#question-search').fill(phrase);
    await applyFilters(page);
    await expect(page.locator('.inventory-item').first()).toContainText(phrase);
    await expect(page.locator('.question-inventory-pagination')).toContainText(/Showing [1-9]\d* of [1-9]\d* matches/);
  });

  test('REPO-04 examination-board filter narrows repository results', async ({ page }) => {
    const question = scopedQuestion();
    await openRepository(page);
    const board = String(question.taxonomy.board_id);
    await page.locator('#question-board').selectOption(board);
    await applyFilters(page);
    await expect(page.locator('#question-board')).toHaveValue(board);
    await expect(page.locator('.inventory-item').first()).toBeVisible();
    expect(await resultIds(page)).toContain(String(question.id));
  });

  test('REPO-05 eligible-subject filter returns matching records', async ({ page }) => {
    const question = scopedQuestion();
    await openRepository(page);
    const subject = String(question.taxonomy.subject_id);
    await page.locator('#question-subject').selectOption(subject);
    await applyFilters(page);
    await expect(page.locator('#question-subject')).toHaveValue(subject);
    await expect(page.locator('.inventory-item').first()).toBeVisible();
    expect(await resultIds(page)).toContain(String(question.id));
  });

  test('REPO-06 board, subject, and text filters can be combined', async ({ page }) => {
    const question = scopedQuestion();
    const phrase = String(question.question).trim().slice(0, 38);
    await openRepository(page);
    await page.locator('#question-search').fill(phrase);
    await page.locator('#question-board').selectOption(String(question.taxonomy.board_id));
    await page.locator('#question-subject').selectOption(String(question.taxonomy.subject_id));
    await applyFilters(page);
    await expect(page.locator('.inventory-item')).toHaveCount(1);
    await expect(page.locator('.inventory-item').first()).toContainText(phrase);
    await expect(page.locator('#question-board')).toHaveValue(String(question.taxonomy.board_id));
    await expect(page.locator('#question-subject')).toHaveValue(String(question.taxonomy.subject_id));
  });

  test('REPO-07 exact ID and examination-board filters can be combined', async ({ page }) => {
    const question = scopedQuestion();
    await openRepository(page);
    await page.locator('#question-search').fill(String(question.id));
    await page.locator('#question-board').selectOption(String(question.taxonomy.board_id));
    await applyFilters(page);
    await expect(page.locator('.inventory-item')).toHaveCount(1);
    await expect(page.locator('.inventory-item').first().locator('p strong')).toHaveText(`#${question.id}`);
  });

  test('REPO-08 reset clears search and filter selections', async ({ page }) => {
    const question = scopedQuestion();
    await openRepository(page, {
      search: question.id,
      board: question.taxonomy.board_id,
      subject: question.taxonomy.subject_id,
    });
    await page.getByRole('link', { name: 'Reset', exact: true }).click();
    await expect(page).toHaveURL(/\/question-editor(?:\?.*)?$/);
    await expect(page.locator('#question-search')).toHaveValue('');
    await expect(page.locator('#question-board')).toHaveValue('');
    await expect(page.locator('#question-subject')).toHaveValue('');
  });

  test('REPO-09 next page contains different canonical records', async ({ page }) => {
    await openRepository(page);
    const firstPage = await resultIds(page);
    await page.getByRole('link', { name: 'Next page' }).click();
    await expect(page.locator('.question-inventory-pagination')).toContainText(/Page 2 of/);
    const secondPage = await resultIds(page);
    expect(secondPage).toHaveLength(50);
    expect(secondPage.some((id) => firstPage.includes(id))).toBeFalsy();
  });

  test('REPO-10 previous page restores the original result page', async ({ page }) => {
    await openRepository(page);
    const firstPage = await resultIds(page);
    await page.getByRole('link', { name: 'Next page' }).click();
    await page.getByRole('link', { name: 'Previous page' }).click();
    await expect(page.locator('.question-inventory-pagination')).toContainText(/Page 1 of/);
    expect(await resultIds(page)).toEqual(firstPage);
  });

  test('REPO-11 applying a filter starts at a valid first page', async ({ page }) => {
    const question = scopedQuestion();
    await openRepository(page, { page_num: 2 });
    await expect(page.locator('.question-inventory-pagination')).toContainText(/Page 2 of/);
    await page.locator('#question-board').selectOption(String(question.taxonomy.board_id));
    await applyFilters(page);
    await expect(page.locator('.question-inventory-pagination')).toContainText(/Page 1 of/);
    await expect(page.locator('#question-board')).toHaveValue(String(question.taxonomy.board_id));
  });

  test('REPO-12 repository pagination renders no more than 50 records', async ({ page }) => {
    await openRepository(page);
    await expect(page.locator('.inventory-item')).toHaveCount(50);
    await expect(page.locator('.question-inventory-pagination')).toContainText(/Showing 50 of/);
  });

  test('REPO-13 canonical and filtered counts remain distinct', async ({ page }) => {
    const question = scopedQuestion();
    await openRepository(page, { search: question.id });
    const summary = await page.locator('.question-inventory-pagination').innerText();
    expect(summary).toContain('Showing 1 of 1 matches');
    expect(summary).toContain(`· ${canonicalQuestions().length} canonical questions in repository`);
  });

  test('REPO-14 question detail opens from its repository result', async ({ page }) => {
    const question = representativeQuestion();
    await openRepository(page, { search: question.id });
    await inspectFirstResult(page);
    await expect(page.getByRole('heading', { name: `Question #${question.id}` })).toBeVisible();
  });

  test('REPO-15 question detail shows the complete stem and every choice', async ({ page }) => {
    const question = scopedQuestion();
    await page.goto(`${developerBase}/question-inspector?id=${encodeURIComponent(question.id)}`);
    await expect(page.getByRole('heading', { name: `Question #${question.id}` })).toBeVisible();
    await expect(page.locator('.question-stem')).toContainText(String(question.question).trim().slice(0, 45));
    const renderedChoices = await page.locator('.question-choice-list li').evaluateAll((items) => items.map((item) => item.innerText.trim()));
    expect(renderedChoices).toHaveLength(question.choices.length);
    for (const choice of question.choices) {
      expect(renderedChoices.some((rendered) => rendered.startsWith(String(choice)))).toBeTruthy();
    }
  });

  test('REPO-16 question detail shows stored answer metadata and rationale', async ({ page }) => {
    const question = scopedQuestion();
    await page.goto(`${developerBase}/question-inspector?id=${encodeURIComponent(question.id)}`);
    await expect(page.locator('body')).toContainText('Stored answer metadata:');
    await expect(page.locator('body')).toContainText(String(question.answer));
    if (question.explanation) await expect(page.locator('body')).toContainText(String(question.explanation).trim().slice(0, 40));
    else await expect(page.locator('body')).toContainText('No explanation is recorded.');
  });

  test('REPO-17 question detail shows registered source and persisted access status', async ({ page }) => {
    const question = sourceQuestion();
    await page.goto(`${developerBase}/question-inspector?id=${encodeURIComponent(question.id)}`);
    await expect(page.getByRole('heading', { name: `Question #${question.id}` })).toBeVisible();
    await expect(page.locator('body')).toContainText(String(question.source_ids.find((id) => String(id).trim() !== '')));
    await expect(page.locator('body')).toContainText('Source URL audit:');
    await expect(page.locator('body')).toContainText(/Access:|No registered source IDs/);
  });

  test('REPO-18 question review detail does not infer independent approval', async ({ page }) => {
    const question = representativeQuestion();
    await page.goto(`${developerBase}/question-inspector?id=${encodeURIComponent(question.id)}`);
    await expect(page.locator('body')).toContainText('Question academic review');
    await expect(page.locator('body')).toContainText('Not inferred from repository status');
  });

  test('REPO-19 Q6403 displays its selection hold and punctuation evidence', async ({ page }) => {
    await page.goto(`${developerBase}/question-inspector?id=6403`);
    await expect(page.getByRole('heading', { name: 'Question #6403' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Question selection hold' })).toBeVisible();
    await expect(page.locator('.question-choice-list')).toContainText('AB-0072.');
    await expect(page.locator('body')).toContainText('AB-0072');
    await expect(page.locator('body')).toContainText('does not change the canonical answer key');
  });

  test('REPO-20 missing question ID returns to a safe empty search state', async ({ page }) => {
    const missingId = 'batch710-no-such-question-6e4d';
    await page.goto(`${developerBase}/question-inspector?id=${missingId}`);
    await expect(page).toHaveURL(new RegExp(`/question-editor\\?search=${missingId}$`));
    await expect(page.locator('body')).toContainText('No questions matched your filters.');
    await expect(page.locator('body')).not.toContainText(/stack trace|fatal error|exception/i);
  });

  test('REPO-21 mobile repository controls and details fit a narrow viewport', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'mobile', 'The narrow viewport check runs in the mobile project.');
    await page.setViewportSize({ width: 360, height: 800 });
    await openRepository(page);
    const layout = await page.evaluate(() => ({
      viewport: document.documentElement.clientWidth,
      document: document.documentElement.scrollWidth,
    }));
    expect(layout.document).toBeLessThanOrEqual(layout.viewport + 2);
    await expect(page.getByRole('button', { name: 'Apply Filters' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Reset', exact: true })).toBeVisible();
    await assertPageHealthy(page, testInfo, 'developer-repository-mobile');
    await page.locator('#question-search').fill('6403');
    await applyFilters(page);
    await inspectFirstResult(page);
    await expect(page.locator('.question-stem')).toBeVisible();
    await assertPageHealthy(page, testInfo, 'developer-question-detail-mobile');
  });

  test('REPO-22 filter controls are explicitly labeled and keyboard reachable', async ({ page }) => {
    await openRepository(page);
    const unlabeled = await page.locator('main input:not([type="hidden"]), main select').evaluateAll((fields) => fields
      .filter((field) => !field.labels?.length && !field.getAttribute('aria-label') && !field.getAttribute('aria-labelledby'))
      .map((field) => field.outerHTML));
    expect(unlabeled).toEqual([]);
    const search = page.locator('#question-search');
    await tabToKeyboardFocus(page, search);
    await expect(search).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(page.locator('#question-board')).toBeFocused();
    expect(await page.locator('#question-board').evaluate((element) => element.matches(':focus-visible'))).toBeTruthy();
  });

  test('REPO-23 browser back and forward preserve the selected question context', async ({ page }) => {
    const question = representativeQuestion();
    await openRepository(page, { search: question.id });
    await inspectFirstResult(page);
    await page.goBack();
    await expect(page).toHaveURL(new RegExp(`/question-editor\\?.*search=${question.id}`));
    await expect(page.locator('.inventory-item').first().locator('p strong')).toHaveText(`#${question.id}`);
    await page.goForward();
    await expect(page.getByRole('heading', { name: `Question #${question.id}` })).toBeVisible();
  });

  test('REPO-24 production does not expose developer repository or mutation routes', async ({ page }) => {
    for (const route of ['/question-editor', '/question-editor/update?id=1', '/question-editor/archive?id=1', '/question-inspector?id=1']) {
      const response = await page.request.get(new URL(route, productionBase).toString());
      expect(response.status(), `${route} must remain unavailable in the production learner server`).toBe(404);
      const body = await response.text();
      expect(body).not.toMatch(/stack trace|fatal error|developer workspace/i);
    }
  });
});
