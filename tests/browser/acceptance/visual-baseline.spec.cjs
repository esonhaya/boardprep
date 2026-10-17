const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');

const {
  test,
  expect,
  assertPageHealthy,
  tabToKeyboardFocus,
  recordBrowserEvidence,
} = require('../fixtures/test.cjs');
const { captureVisualState, writeVisualAudit } = require('../fixtures/visual.cjs');

const representativeBoards = [
  ['LET', 'LET'],
  ['CSE', 'CSE'],
  ['Nursing', 'NLE'],
  ['CPA', 'CPA'],
  ['CE', 'CE'],
];

let questions;
function canonicalQuestions() {
  if (questions) return questions;
  const source = fs.readFileSync(path.resolve(__dirname, '../../..', 'storage', 'questions.json'), 'utf8');
  questions = JSON.parse(source);
  return questions;
}

function questionById(id) {
  return canonicalQuestions().find((question) => String(question.id) === String(id));
}

function normalize(value) {
  return String(value || '').replace(/\s+/g, ' ').trim().toLowerCase();
}

async function startQuiz(page, {
  exam,
  subject,
  topic = '',
  count = '5',
  difficulty = 'mixed',
} = {}) {
  const query = new URLSearchParams({ exam, topic });
  await page.goto(`/quiz?${query.toString()}`);
  const activeSessionNotice = page.locator('.active-session-warning');
  if (await activeSessionNotice.count()) {
    await expect(activeSessionNotice).toContainText('Starting the session below will end this unfinished attempt and discard its answers.');
    await expect(page.getByRole('link', { name: 'Resume current session' })).toHaveAttribute('href', '/quiz');
  }
  const subjectSelect = page.locator('#quiz-subject');
  const subjectOptions = await subjectSelect.locator('option').evaluateAll((options) => options
    .filter((option) => !option.disabled && !option.hidden)
    .map((option) => ({ value: option.value, label: option.label })));
  const wantedSubject = normalize(subject);
  const subjectOption = subjectOptions.find((option) => (
    normalize(option.value) === wantedSubject || normalize(option.label) === wantedSubject
  ));
  if (!subjectOption) throw new Error(`Subject is not offered by the learner form: ${subject}`);
  await subjectSelect.selectOption(subjectOption.value);
  await page.locator('#quiz-count').selectOption(count);
  await page.locator('#quiz-difficulty').selectOption(difficulty);
  await page.getByRole('button', { name: 'Start practice' }).click();
  await expect(page.locator('.quiz-question')).toBeVisible();
}

async function answerCurrentQuestion(page, wrong = false) {
  const id = await page.locator('input[name="question_id"]').inputValue();
  const question = questionById(id);
  const choices = question?.choices || [];
  const correctIndex = choices.findIndex((choice) => normalize(choice) === normalize(question?.answer));
  let answerIndex = correctIndex >= 0 ? correctIndex : 0;
  if (wrong && choices.length > 1) answerIndex = (answerIndex + 1) % choices.length;
  await page.locator('input[name="answer"]').nth(answerIndex).check();
  await page.getByRole('button', { name: 'Submit Answer' }).click();
  await expect(page.locator('.quiz-feedback')).toBeVisible();
}

async function finishActiveQuiz(page, maxQuestions = 20) {
  for (let index = 0; index < maxQuestions; index += 1) {
    await answerCurrentQuestion(page);
    const finish = page.getByRole('button', { name: 'Finish Quiz' });
    if (await finish.isVisible()) {
      await finish.click();
      await expect(page.locator('.quiz-result-page')).toBeVisible();
      return;
    }
    await page.getByRole('button', { name: 'Next Question' }).click();
    await expect(page.locator('.quiz-answer-form')).toBeVisible();
  }
  throw new Error('Quiz did not reach its finish action within the configured question bound');
}

async function completeFiveQuestionQuiz(page, startAtIndex = 0, beforeFinish = null) {
  for (let index = startAtIndex; index < 5; index += 1) {
    await answerCurrentQuestion(page, true);
    if (index === 4 && beforeFinish) await beforeFinish();
    await page.getByRole('button', { name: index === 4 ? 'Finish Quiz' : 'Next Question' }).click();
    if (index < 4) await expect(page.locator('.quiz-answer-form')).toBeVisible();
  }
  await expect(page.locator('h1')).toContainText('Quiz Result');
}

async function advanceToQuestion(page, targetId, maxQuestions = 20) {
  for (let index = 0; index < maxQuestions; index += 1) {
    const currentId = await page.locator('input[name="question_id"]').inputValue();
    if (String(currentId) === String(targetId)) return;
    await answerCurrentQuestion(page);
    if (await page.getByRole('button', { name: 'Finish Quiz' }).isVisible()) break;
    const next = page.getByRole('button', { name: 'Next Question' });
    await expect(next).toBeVisible();
    await next.click();
    await expect(page.locator('.quiz-answer-form')).toBeVisible();
  }
  throw new Error(`Deterministic visual fixture ${targetId} was not reachable in the bounded production quiz path`);
}

async function capture(page, testInfo, entries, name, options = {}) {
  const report = await captureVisualState(page, testInfo, name, {
    ...options,
    mobile: testInfo.project.name === 'mobile',
    importantSelectors: options.importantSelectors || [
      'main h1',
      'main .button',
      'main button',
      'main select',
      '.quiz-choice',
      '.mobile-menu-button',
      '#learner-nav a',
    ],
  });
  entries.push({
    name,
    viewport: report.screenshotWidth,
    viewportHeight: report.screenshotHeight,
    clientWidth: report.viewport,
    scrollWidth: report.scrollWidth,
    pageOverflow: report.pageOverflow,
    outsideViewport: report.outsideViewport.length,
    tables: report.tables.length,
    figures: report.figures.length,
    screenshot: report.screenshot,
  });
  return report;
}

async function inspectQuestionAtMobileWidths(page, selector, captureAtSize = null) {
  const sizes = [
    { width: 320, height: 640 },
    { width: 360, height: 800 },
    { width: 390, height: 844 },
    { width: 430, height: 932 },
    { width: 768, height: 1024 },
  ];
  const evidence = [];

  for (const size of sizes) {
    await page.setViewportSize(size);
    const layout = await page.evaluate((contentSelector) => {
      const element = document.querySelector(contentSelector);
      return {
        documentWidth: document.documentElement.scrollWidth,
        viewportWidth: document.documentElement.clientWidth,
        contentWidth: element?.getBoundingClientRect().width ?? 0,
        contentScrollWidth: element?.scrollWidth ?? 0,
      };
    }, selector);
    expect(layout.documentWidth, `${selector} document at ${size.width}px`).toBeLessThanOrEqual(size.width + 2);
    expect(layout.contentWidth, `${selector} visibility at ${size.width}px`).toBeGreaterThan(0);
    evidence.push(`${size.width}x${size.height}`);
    if (captureAtSize) {
      await captureAtSize(size);
    }
  }

  await page.setViewportSize({ width: 390, height: 844 });
  return evidence;
}

test.describe('BoardPrep deterministic visual baseline', () => {
  test('captures representative learner product states', async ({ page }, testInfo) => {
    const entries = [];
    const mobile = testInfo.project.name === 'mobile';

    await page.goto('/');
    await expect(page.locator('h1')).toBeVisible();
    await capture(page, testInfo, entries, 'home', { fullPage: true });

    await page.goto('/dashboard');
    const dashboardHeading = page.locator('main h1');
    await expect(dashboardHeading).toHaveCount(1);
    await expect(dashboardHeading).toHaveText('Welcome to BoardPrep');
    await capture(page, testInfo, entries, 'dashboard', { fullPage: true });

    if (mobile) {
      const menu = page.locator('.mobile-menu-button');
      await menu.click();
      await expect(menu).toHaveAttribute('aria-expanded', 'true');
      await expect(page.locator('#learner-nav.is-open')).toBeVisible();
      await tabToKeyboardFocus(page, page.locator('#learner-nav a[href="/exams"]'));
      await capture(page, testInfo, entries, 'mobile-nav', { fullPage: false });
      await menu.click();
    }

    await page.goto('/exams');
    await expect(page.locator('.exam-card')).toHaveCount(10);
    await capture(page, testInfo, entries, 'exams', { fullPage: true });

    for (const [name, exam] of representativeBoards) {
      await page.goto(`/exams?exam=${exam}`);
      await expect(page.locator('h1')).toBeVisible();
      const screenshotName = {
        LET: 'board-let',
        CSE: 'board-cse',
        Nursing: 'board-nursing',
        CPA: 'board-cpa',
        CE: 'board-engineering',
      }[name];
      await capture(page, testInfo, entries, screenshotName, { fullPage: true });
    }

    await page.goto('/profile');
    await page.locator('#preferred-board').selectOption('LET');
    await page.locator('#profile-let-level').selectOption('ELEMENTARY');
    const letSelectWidths = await page.locator('#profile-let-level, #profile-let-specialization').evaluateAll((elements) => elements
      .map((element) => element.getBoundingClientRect().width));
    expect(Math.min(...letSelectWidths)).toBeGreaterThan(240);
    await page.getByRole('button', { name: 'Save exam preferences' }).click();
    await expect(page.locator('.alert-success[role="status"]')).toContainText('preferences were saved');
    await page.goto('/quiz?exam=LET&subject=english');
    await expect(page.locator('.quiz-context-summary')).toContainText('Licensure Examination for Teachers');
    await expect(page.locator('.quiz-context-summary')).toContainText('English');
    await capture(page, testInfo, entries, 'quiz-config-let-elementary', { fullPage: false });
    await page.goto('/profile');
    await page.locator('#preferred-board').selectOption('CSE');
    await page.locator('#profile-cse-level').selectOption('PROFESSIONAL');
    await page.getByRole('button', { name: 'Save exam preferences' }).click();
    await expect(page.locator('.alert-success[role="status"]')).toContainText('preferences were saved');
    await page.goto('/quiz?exam=CSE');
    await expect(page.locator('.quiz-context-summary')).toContainText('Civil Service');
    await capture(page, testInfo, entries, 'quiz-config-cse-professional', { fullPage: false });

    await page.goto('/quiz');
  await expect(page.locator('h1')).toContainText('Choose practice or an exam simulation');
    await capture(page, testInfo, entries, 'quiz-config', { fullPage: false });

    await page.goto('/history');
    await expect(page.locator('.empty-state')).toBeVisible();
    await expect(page.locator('.history-summary')).toContainText('—');
    await expect(page.locator('.history-summary')).toContainText('No completed quizzes yet');
    await capture(page, testInfo, entries, 'history-empty', { fullPage: true });
    await page.goto('/progress');
    await expect(page.locator('.progress-page > .page-header a[href="/quiz"]')).toHaveClass(/button-green/);
    await expect(page.locator('.progress-summary')).toContainText('—');
    await expect(page.locator('.progress-summary')).toContainText('No completed quizzes yet');
    await capture(page, testInfo, entries, 'progress-empty', { fullPage: true });

    await page.goto('/flashcards/review?include_new=0');
    await expect(page.locator('.empty-state')).toBeVisible();
    await capture(page, testInfo, entries, 'empty-state', { fullPage: false });

    await startQuiz(page, {
      exam: 'LET', subject: 'English', count: '5', difficulty: 'mixed',
    });
    await capture(page, testInfo, entries, 'quiz-text', { fullPage: false });
    const firstAnswer = page.locator('input[name="answer"]').first();
    await firstAnswer.focus();
    await page.keyboard.press('Space');
    await expect(firstAnswer).toBeChecked();
    await expect(page.locator('.quiz-feedback')).toHaveCount(0);
    await capture(page, testInfo, entries, 'answer-selected', { fullPage: false });
    await page.getByRole('button', { name: 'Submit Answer' }).click();
    await expect(page.locator('.quiz-feedback')).toBeVisible();
    await capture(page, testInfo, entries, 'answer-feedback', { fullPage: false });
    await page.getByRole('button', { name: 'Next Question' }).click();
    await completeFiveQuestionQuiz(page, 1, () => capture(
      page,
      testInfo,
      entries,
      'finish-quiz-ready',
      { fullPage: false },
    ));
    await capture(page, testInfo, entries, 'result-summary', { fullPage: false });
    const review = page.locator('.question-review').first();
    await expect(review).toBeVisible();
    await review.scrollIntoViewIfNeeded();
    await capture(page, testInfo, entries, 'result-review', { fullPage: false });

    await startQuiz(page, {
      exam: 'CPA',
      subject: 'cpa-financial-accounting',
      topic: 'cpa-financial-accounting-inventory',
      count: '20',
      difficulty: 'mixed',
    });
    await advanceToQuestion(page, '1261', 20);
    await expect(page.locator('.question-equation')).toBeVisible();
    const mathViewports = mobile
      ? await inspectQuestionAtMobileWidths(page, '.question-equation', () => capture(
        page,
        testInfo,
        entries,
        'quiz-numeric',
        { fullPage: false, importantSelectors: ['main h1', 'main button', '.question-equation', '.quiz-choice', '.mobile-menu-button'] },
      ))
      : [];
    await capture(page, testInfo, entries, 'quiz-numeric', { fullPage: false });

    await startQuiz(page, {
      exam: 'CPA',
      subject: 'cpa-financial-accounting',
      topic: 'cpa-financial-accounting-liabilities',
      count: '20',
      difficulty: 'mixed',
    });
    await advanceToQuestion(page, '1267', 20);
    await expect(page.locator('.question-table-wrap')).toBeVisible();
    const tableViewports = mobile
      ? await inspectQuestionAtMobileWidths(page, '.question-table-wrap', () => capture(
        page,
        testInfo,
        entries,
        'quiz-table',
        { fullPage: false, importantSelectors: ['main h1', 'main button', '.question-table-wrap', '.quiz-choice', '.mobile-menu-button'] },
      ))
      : [];
    await capture(page, testInfo, entries, 'quiz-table', { fullPage: false });

    if (mobile) {
      await finishActiveQuiz(page);
      await expect(page.locator('.result-answer-review .question-table-wrap').first()).toBeVisible();
      await assertPageHealthy(page, testInfo, 'table-question-result');
      await capture(page, testInfo, entries, 'table-question-result', { fullPage: true });
    }

    await startQuiz(page, {
      exam: 'LET',
      subject: 'science',
      topic: 'science-electricity-circuits',
      count: '20',
      difficulty: 'mixed',
    });
    await advanceToQuestion(page, '34', 20);
    await expect(page.locator('.question-figure img')).toBeVisible();
    await capture(page, testInfo, entries, 'quiz-svg', { fullPage: false });

    await page.goto('/study');
    await expect(page.locator('h1')).toHaveText('Study Dashboard');
    await capture(page, testInfo, entries, 'study-landing', { fullPage: true });

    await page.goto('/study-library?exam=LET');
    await expect(page.locator('h1')).toHaveText('Study resources');
    const librarySearch = page.getByRole('searchbox', { name: 'Search lesson titles, notes, and concepts' });
    const librarySearchHeight = await librarySearch.evaluate((input) => input.getBoundingClientRect().height);
    expect(librarySearchHeight).toBeLessThan(60);
    const studyMaterialCards = page.locator('.study-material-card');
    const studyMaterialCount = await studyMaterialCards.count();
    expect(studyMaterialCount).toBeGreaterThan(0);
    const firstStudyMaterial = studyMaterialCards.first();
    const firstStudyTitle = await firstStudyMaterial.locator('h2').innerText();
    await librarySearch.fill('no-resource-has-this-phrase-670');
    await librarySearch.press('Enter');
    await expect(page.getByRole('heading', { name: 'No study materials match that search' })).toBeVisible();
    await page.getByRole('link', { name: 'Clear search' }).click();
    await expect(studyMaterialCards).toHaveCount(studyMaterialCount);
    await librarySearch.fill(firstStudyTitle);
    await librarySearch.press('Enter');
    await expect(studyMaterialCards).toHaveCount(1);
    await capture(page, testInfo, entries, 'study-resource', { fullPage: true });
    const firstStudyMaterialDetail = page.locator('.study-material-card').first();
    await expect(firstStudyMaterialDetail).toBeVisible();
    await firstStudyMaterialDetail.locator('.study-material-disclosure > summary').click();
    await capture(page, testInfo, entries, 'study-material-detail', { fullPage: false });

    // Use an explicit board for the review-card screenshot so this visual
    // state does not depend on the CSE preference saved earlier in this test.
    await page.goto('/flashcards?board=let&include_new=1');
    await expect(page.locator('h1')).toHaveText('Flashcards');
    await capture(page, testInfo, entries, 'flashcard-landing', { fullPage: true });
    await page.getByRole('link', { name: /Start review|Review now/ }).first().click();
    await expect(page.locator('.flashcard-card')).toBeVisible();
    await capture(page, testInfo, entries, 'flashcard-unrevealed', { fullPage: false });
    await page.getByRole('link', { name: 'Reveal answer' }).click();
    await expect(page.locator('.flashcard-answer')).toBeVisible();
    await capture(page, testInfo, entries, 'flashcard-revealed', { fullPage: false });

    if (mobile) {
      const structuredFlashcardQuestion = questionById('135');
      expect(structuredFlashcardQuestion?.data_interpretation).toBe(true);
      expect(structuredFlashcardQuestion?.content_blocks?.some((block) => block.type === 'table')).toBe(true);
      const structuredConceptId = structuredFlashcardQuestion?.taxonomy?.concept_id;
      expect(structuredConceptId).toBeTruthy();
      const structuredFlashcardQuery = new URLSearchParams({
        include_new: '1',
        board: 'let',
        card_key: `q:135:formula-or-data:${structuredConceptId}:r1`,
        reveal: '1',
      });
      await page.goto(`/flashcards/review?${structuredFlashcardQuery.toString()}`);
      const structuredTable = page.locator('.flashcard-card .question-table-wrap');
      await expect(page.getByText('That flashcard is unavailable or no longer eligible.')).toHaveCount(0);
      await expect(structuredTable).toBeVisible();
      await expect(structuredTable).toHaveRole('region');
      await expect(structuredTable).toHaveAccessibleName('Scrollable question data');
      await expect(structuredTable).toHaveAttribute('tabindex', '0');
      const hasHorizontalOverflow = await structuredTable.evaluate((element) => element.scrollWidth > element.clientWidth);
      expect(hasHorizontalOverflow).toBe(true);
      await structuredTable.focus();
      await page.keyboard.press('ArrowRight');
      await expect.poll(() => structuredTable.evaluate((element) => element.scrollLeft)).toBeGreaterThan(0);
      await structuredTable.evaluate((element) => {
        element.scrollLeft = 0;
        element.blur();
      });
      await assertPageHealthy(page, testInfo, 'structured-flashcard-table');
      await capture(page, testInfo, entries, 'structured-flashcard-table', { fullPage: false });
    }

    await page.goto('/progress');
    await expect(page.locator('h1')).toBeVisible();
    await expect(page.locator('.progress-page > .page-header a[href="/quiz"]')).toHaveClass(/button-green/);
    await capture(page, testInfo, entries, 'progress', { fullPage: true });

    await page.goto('/history');
    await expect(page.locator('h1')).toHaveText('Quiz history');
    await capture(page, testInfo, entries, 'history', { fullPage: true });
    const historyResult = page.getByRole('link', { name: 'View result' }).first();
    await expect(historyResult).toBeVisible();
    await historyResult.click();
    await expect(page.locator('h1')).toHaveText('Historical Quiz Result');
    await capture(page, testInfo, entries, 'history-reopen', { fullPage: true });

    await page.goto('/history');
    await expect(page.getByRole('link', { name: 'Start practice' })).toHaveClass(/button-outline/);
    await capture(page, testInfo, entries, 'history', { fullPage: true });

    await page.goto('/privacy');
    await expect(page.locator('h1')).toHaveText('Data and privacy');
    await capture(page, testInfo, entries, 'privacy-export', { fullPage: true });
    const clearConfirmation = page.locator('input[name="confirm_clear"]');
    await clearConfirmation.check();
    await capture(page, testInfo, entries, 'privacy-delete-confirmation', { fullPage: true });

    await page.goto('/missing-browser-acceptance-route');
    await expect(page.locator('.site-header')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'We couldn’t find that page' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Go to home' })).toHaveAttribute('href', '/');
    await expect(page.getByRole('link', { name: 'Browse examinations' })).toBeVisible();
    await assertPageHealthy(page, testInfo, 'error-state', {
      allowHttpErrors: [{
        status: 404,
        url: page.url(),
        resourceType: 'document',
        consoleMessage: 'Failed to load resource: the server responded with a status of 404 (Not Found)',
      }],
    });
    await capture(page, testInfo, entries, 'error-state', { fullPage: false });

    if (mobile) {
      await page.goto('/dashboard');
      for (const size of [
        { width: 320, height: 640 },
        { width: 360, height: 800 },
        { width: 390, height: 844 },
        { width: 430, height: 932 },
        { width: 768, height: 1024 },
      ]) {
        await page.setViewportSize(size);
        if (size.width === 320) {
          await expect(page.locator('.ui-dashboard-hero .page-actions a[href="/flashcards/review?mode=weakness"]')).toBeVisible();
        }
        await capture(page, testInfo, entries, `dashboard-width-${size.width}`, { fullPage: true });
      }
      recordBrowserEvidence('complex-content', {
        math: mathViewports,
        tables: tableViewports,
        documentOverflow: 'PASS',
      }, testInfo);
    } else {
      await page.goto('/dashboard');
      await page.setViewportSize({ width: 1440, height: 900 });
      await capture(page, testInfo, entries, 'dashboard-wide', { fullPage: true });
    }
    const reportPath = writeVisualAudit(testInfo, testInfo.project.name, entries);
    await testInfo.attach('visual audit report', { path: reportPath, contentType: 'text/markdown' });
    expect(entries.length).toBeGreaterThan(mobile ? 30 : 20);
  });
});
