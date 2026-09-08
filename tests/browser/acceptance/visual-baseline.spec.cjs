const { execFileSync } = require('node:child_process');
const path = require('node:path');

const {
  test,
  expect,
  assertPageHealthy,
  tabToKeyboardFocus,
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
  const query = new URLSearchParams({ exam, subject, topic, count, difficulty });
  await page.goto(`/quiz?${query.toString()}`);
  await page.locator('#quiz-subject').evaluate((select, value) => {
    if (![...select.options].some((option) => option.value === value)) {
      select.add(new Option(value, value));
    }
    select.value = value;
  }, subject);
  await page.locator('input[name="topic"]').evaluate((input, value) => {
    input.value = value;
  }, topic);
  await page.locator('#quiz-count').evaluate((select, value) => {
    if (![...select.options].some((option) => option.value === value)) {
      select.add(new Option(value, value));
    }
    select.value = value;
  }, count);
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
      subject: 'REE Engineering Sciences and Allied Subjects',
      topic: 'ree-allied-safety',
      count: '2',
      difficulty: 'hard',
    });
    await advanceToQuestion(page, '1637', 2);
    await expect(page.locator('.question-equation')).toBeVisible();
    await capture(page, testInfo, entries, 'quiz-numeric', { fullPage: false });

    await startQuiz(page, {
      exam: 'LET', subject: 'Science', topic: 'science-data-graphs', count: '2', difficulty: 'hard',
    });
    await advanceToQuestion(page, '40', 2);
    await expect(page.locator('.question-table-wrap')).toBeVisible();
    await capture(page, testInfo, entries, 'quiz-table', { fullPage: false });

    await startQuiz(page, {
      exam: 'LET', subject: 'Science', topic: 'science-biology', count: '2', difficulty: 'easy',
    });
    await advanceToQuestion(page, '31', 2);
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
    expect(entries.length).toBe(mobile ? 22 : 21);
  });
});
