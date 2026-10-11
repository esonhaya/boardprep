const { execFileSync } = require('node:child_process');
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
  ['CE', 'CE'],
];

let questions;
function canonicalQuestions() {
  if (questions) return questions;
  const source = execFileSync('git', ['show', 'HEAD:storage/questions.json'], {
    cwd: path.resolve(__dirname, '../../..'),
    encoding: 'utf8',
    maxBuffer: 16 * 1024 * 1024,
  });
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
  await page.getByRole('button', { name: 'Start quiz' }).click();
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

async function completeFiveQuestionQuiz(page) {
  for (let index = 0; index < 5; index += 1) {
    await answerCurrentQuestion(page, true);
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
      '.mobile-menu-button',
      '#learner-nav a',
    ],
  });
  entries.push({
    name,
    viewport: report.viewport,
    scrollWidth: report.scrollWidth,
    pageOverflow: report.pageOverflow,
    outsideViewport: report.outsideViewport.length,
    tables: report.tables.length,
    figures: report.figures.length,
    screenshot: `${name}.png`,
  });
  return report;
}

async function inspectQuestionAtMobileWidths(page, selector) {
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
  }

  await page.setViewportSize({ width: 390, height: 844 });
  return evidence;
}

test.describe('BoardPrep deterministic visual baseline', () => {
  test('captures representative learner product states', async ({ page }, testInfo) => {
    const entries = [];
    const mobile = testInfo.project.name === 'mobile';

    await page.goto('/dashboard');
    await expect(page.locator('h1')).toHaveText('Welcome back');
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
      await capture(page, testInfo, entries, name === 'LET' ? 'board-let' : 'board-engineering', { fullPage: true });
    }

    await page.goto('/quiz');
    await expect(page.locator('h1')).toContainText('Choose how you want to study');
    await capture(page, testInfo, entries, 'quiz-config', { fullPage: false });

    await page.goto('/flashcards/review?include_new=0');
    await expect(page.locator('.empty-state')).toBeVisible();
    await capture(page, testInfo, entries, 'empty-state', { fullPage: false });

    await startQuiz(page, {
      exam: 'LET', subject: 'English', count: '5', difficulty: 'mixed',
    });
    await capture(page, testInfo, entries, 'quiz-text', { fullPage: false });
    await completeFiveQuestionQuiz(page);
    await capture(page, testInfo, entries, 'result-summary', { fullPage: false });
    const review = page.locator('.question-review').first();
    await expect(review).toBeVisible();
    await review.scrollIntoViewIfNeeded();
    await capture(page, testInfo, entries, 'result-review', { fullPage: false });

    await startQuiz(page, {
      exam: 'REE',
      subject: 'Engineering Sciences and Allied Subjects',
      topic: 'ree-allied-safety',
      count: '5',
      difficulty: 'hard',
    });
    await advanceToQuestion(page, '1637', 5);
    await expect(page.locator('.question-equation')).toBeVisible();
    const mathViewports = mobile
      ? await inspectQuestionAtMobileWidths(page, '.question-equation')
      : [];
    await capture(page, testInfo, entries, 'quiz-numeric', { fullPage: false });

    await startQuiz(page, {
      exam: 'LET', subject: 'Science', topic: 'science-data-graphs', count: '5', difficulty: 'hard',
    });
    await advanceToQuestion(page, '40', 5);
    await expect(page.locator('.question-table-wrap')).toBeVisible();
    const tableViewports = mobile
      ? await inspectQuestionAtMobileWidths(page, '.question-table-wrap')
      : [];
    await capture(page, testInfo, entries, 'quiz-table', { fullPage: false });

    await startQuiz(page, {
      exam: 'LET', subject: 'Science', topic: 'science-biology', count: '5', difficulty: 'easy',
    });
    await advanceToQuestion(page, '31', 5);
    await expect(page.locator('.question-figure img')).toBeVisible();
    await capture(page, testInfo, entries, 'quiz-svg', { fullPage: false });

    await page.goto('/study');
    await expect(page.locator('h1')).toHaveText('Study Dashboard');
    await capture(page, testInfo, entries, 'study-landing', { fullPage: true });

    await page.goto('/study-library?exam=LET');
    await expect(page.locator('h1')).toHaveText('Source-backed study materials');
    await capture(page, testInfo, entries, 'study-resource', { fullPage: true });

    await page.goto('/flashcards?include_new=1');
    await expect(page.locator('h1')).toHaveText('Flashcards');
    await capture(page, testInfo, entries, 'flashcard-landing', { fullPage: true });
    await page.getByRole('link', { name: /Start review|Review now/ }).first().click();
    await expect(page.locator('.flashcard-card')).toBeVisible();
    await capture(page, testInfo, entries, 'flashcard-unrevealed', { fullPage: false });
    await page.getByRole('link', { name: 'Reveal answer' }).click();
    await expect(page.locator('.flashcard-answer')).toBeVisible();
    await capture(page, testInfo, entries, 'flashcard-revealed', { fullPage: false });

    await page.goto('/progress');
    await expect(page.locator('h1')).toBeVisible();
    await capture(page, testInfo, entries, 'progress', { fullPage: true });

    await page.goto('/history');
    await expect(page.locator('h1')).toHaveText('Quiz history');
    await capture(page, testInfo, entries, 'history', { fullPage: true });
    const historyResult = page.getByRole('link', { name: 'View result' }).first();
    await expect(historyResult).toBeVisible();
    await historyResult.click();
    await expect(page.locator('h1')).toHaveText('Historical Quiz Result');
    await capture(page, testInfo, entries, 'history-reopen', { fullPage: true });

    await page.goto('/missing-browser-acceptance-route');
    await assertPageHealthy(page, testInfo, 'error-state', {
      allowHttpErrors: [{
        status: 404,
        url: page.url(),
        resourceType: 'document',
        consoleMessage: 'Failed to load resource: the server responded with a status of 404 (Not Found)',
      }],
    });
    await capture(page, testInfo, entries, 'error-state', { fullPage: false });

    const reportPath = writeVisualAudit(testInfo, testInfo.project.name, entries);
    await testInfo.attach('visual audit report', { path: reportPath, contentType: 'text/markdown' });
    if (mobile) {
      recordBrowserEvidence('complex-content', {
        math: mathViewports,
        tables: tableViewports,
        documentOverflow: 'PASS',
      }, testInfo);
    }
    expect(entries.length).toBe(mobile ? 22 : 21);
  });
});
