const { execFileSync } = require('node:child_process');

const {
  test,
  expect,
  assertPageHealthy,
  assertKeyboardFocus,
} = require('../fixtures/test.cjs');

const boards = [
  ['LET', 'let'],
  ['CSE', 'civil-service'],
  ['CLE', 'criminologist'],
  ['NLE', 'nursing'],
  ['PLE', 'psychometrician'],
  ['CE', 'civil-engineering'],
  ['CPA', 'cpa'],
  ['ME', 'mechanical-engineering'],
  ['REE', 'registered-electrical-engineering'],
  ['ARCH', 'architecture'],
];

let questions;
function canonicalQuestions() {
  if (questions) return questions;
  const source = execFileSync('git', ['show', 'HEAD:storage/questions.json'], {
    cwd: process.cwd(),
    encoding: 'utf8',
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

async function startQuiz(page, { exam = 'LET', subject = 'English', topic = '', count = '5' } = {}) {
  const query = new URLSearchParams({ exam, subject, topic, count });
  await page.goto(`/quiz?${query.toString()}`);
  const subjectSelect = page.locator('#quiz-subject');
  await subjectSelect.evaluate((select, value) => {
    if (![...select.options].some((option) => option.value === value)) {
      select.add(new Option(value, value));
    }
    select.value = value;
  }, subject);
  await page.locator('input[name="topic"]').evaluate((input, value) => {
    input.value = value;
  }, topic);
  await page.getByRole('button', { name: 'Start quiz' }).click();
  await expect(page.locator('.quiz-question')).toBeVisible();
}

async function submitAnswer(page, wrong = false) {
  const questionId = await page.locator('input[name="question_id"]').inputValue();
  const question = questionById(questionId);
  const choices = question?.choices || [];
  const correctIndex = choices.findIndex((choice) => normalize(choice) === normalize(question?.answer));
  let answerIndex = correctIndex >= 0 ? correctIndex : 0;
  if (wrong && choices.length > 1) answerIndex = (answerIndex + 1) % choices.length;
  await page.locator('input[name="answer"]').nth(answerIndex).check();
  await expect(page.locator('input[name="answer"]').nth(answerIndex)).toBeChecked();
  await page.getByRole('button', { name: 'Submit Answer' }).click();
  await expect(page.locator('.quiz-feedback')).toBeVisible();
}

async function completeQuiz(page, wrong = false) {
  for (let index = 0; index < 5; index += 1) {
    await submitAnswer(page, wrong);
    const next = page.getByRole('button', { name: index === 4 ? 'Finish Quiz' : 'Next Question' });
    await next.click();
    if (index < 4) {
      await expect(page.locator('.quiz-answer-form')).toBeVisible();
    }
  }
  await expect(page.locator('h1')).toContainText('Quiz Result');
}

test.describe('BoardPrep learner browser acceptance', () => {
  test('dashboard navigation and mobile menu are usable', async ({ page }, testInfo) => {
    await page.goto('/dashboard');
    await expect(page.locator('h1')).toHaveText('Welcome back');
    for (const href of ['/exams', '/history', '/flashcards', '/progress', '/study-library']) {
      await expect(page.locator(`a[href="${href}"]`).first()).toBeVisible();
    }
    const menu = page.locator('.mobile-menu-button');
    await expect(menu).toHaveAttribute('aria-expanded', 'false');
    await menu.click();
    await expect(menu).toHaveAttribute('aria-expanded', 'true');
    await expect(page.locator('#learner-nav')).toHaveClass(/is-open/);
    await assertKeyboardFocus(page, page.locator('#learner-nav a[href="/exams"]'));
    await menu.click();
    await expect(menu).toHaveAttribute('aria-expanded', 'false');
    await assertPageHealthy(page, testInfo, 'dashboard');
  });

  test('all populated board details render without browser failures', async ({ page }, testInfo) => {
    await page.goto('/exams');
    await expect(page.locator('.exam-card')).toHaveCount(10);
    await expect(page.locator('.status-soon')).toHaveCount(0);
    await assertPageHealthy(page, testInfo, 'exams');

    for (const [code] of boards) {
      await page.goto(`/exams?exam=${encodeURIComponent(code)}`);
      await expect(page.locator('.exam-detail h1')).toBeVisible();
      await expect(page.locator('body')).not.toContainText('Coming soon');
      await assertPageHealthy(page, testInfo, `board-${code}`, {
        screenshot: ['LET', 'ME', 'ARCH'].includes(code),
      });
    }
  });

  test('quiz configuration and deterministic text round trip work', async ({ page }, testInfo) => {
    await startQuiz(page, {
      exam: 'LET',
      subject: 'professional-education',
      topic: 'prof-development',
      count: '5',
    });
    await expect(page.locator('.quiz-meta')).toContainText('Question 1 / 5');
    await completeQuiz(page, true);
    await expect(page.locator('.result-summary')).toContainText('Score:');
    await assertPageHealthy(page, testInfo, 'quiz-result');

    await expect(page.getByRole('link', { name: 'Review missed concepts' })).toBeVisible();
    await page.getByRole('link', { name: 'Review missed concepts' }).click();
    await expect(page.locator('.flashcard-card')).toBeVisible();
    await assertPageHealthy(page, testInfo, 'missed-flashcards');
    await page.goto('/quiz?action=result');

    await page.getByRole('link', { name: 'Back to Study Dashboard' }).click();
    await expect(page.locator('h1')).toHaveText('Study Dashboard');
    await assertPageHealthy(page, testInfo, 'study-after-result');

    await page.getByRole('link', { name: /Practice/ }).first().click();
    await expect(page.locator('h1')).toContainText('Choose how you want to study');
    await assertPageHealthy(page, testInfo, 'quiz-config');

    await page.goto('/history');
    await expect(page.locator('.quiz-history-item').first()).toBeVisible();
    await page.getByRole('link', { name: 'View result' }).first().click();
    await expect(page.locator('h1')).toHaveText('Historical Quiz Result');
    await assertPageHealthy(page, testInfo, 'history-result');
  });

  test('table and figure questions remain usable on the production path', async ({ page }, testInfo) => {
    let equationFound = false;
    for (let attempt = 0; attempt < 5 && !equationFound; attempt += 1) {
      await startQuiz(page, {
        exam: 'CE',
        subject: 'civil-engineering-applied-mathematics',
        topic: 'applied-engineering',
        count: '5',
      });
      equationFound = await page.locator('.question-equation').count() > 0;
    }
    expect(equationFound).toBeTruthy();
    await assertPageHealthy(page, testInfo, 'quiz-engineering-equation');

    await startQuiz(page, {
      exam: 'LET',
      subject: 'mathematics',
      topic: 'math-algebra',
      count: '5',
    });
    await expect(page.locator('.question-table-wrap')).toHaveCount(1);
    await assertPageHealthy(page, testInfo, 'quiz-table');

    await startQuiz(page, {
      exam: 'ARCH',
      subject: 'architecture-architectural-design-and-site-planning',
      topic: 'architecture-site-reasoning',
      count: '5',
    });
    await expect(page.locator('.question-figure img')).toHaveCount(1);
    await expect(page.locator('.question-figure img')).toBeVisible();
    await assertPageHealthy(page, testInfo, 'quiz-svg');
    await submitAnswer(page, false);
    await page.getByRole('button', { name: 'Next Question' }).click();
    await expect(page.locator('.question-figure img')).toHaveCount(1);
    await assertPageHealthy(page, testInfo, 'quiz-svg-next');
  });

  test('flashcard review, progress, history, and recovery states work', async ({ page }, testInfo) => {
    await page.goto('/flashcards/review?include_new=0');
    await expect(page.locator('h2')).toContainText('No review card is available');
    await expect(page.getByRole('link', { name: 'Adjust flashcards' })).toBeVisible();
    await assertPageHealthy(page, testInfo, 'flashcards-empty');

    await page.goto('/flashcards?include_new=1');
    await expect(page.locator('h1')).toHaveText('Flashcards');
    await page.getByRole('link', { name: /Start review|Review now/ }).first().click();
    await expect(page.locator('.flashcard-card')).toBeVisible();
    await expect(page.locator('.flashcard-answer')).toHaveCount(0);
    await assertPageHealthy(page, testInfo, 'flashcards-review-before-reveal');
    await page.getByRole('link', { name: 'Reveal answer' }).click();
    await expect(page.locator('.flashcard-answer')).toBeVisible();
    await expect(page.getByRole('button', { name: /Good/ })).toBeVisible();
    await assertPageHealthy(page, testInfo, 'flashcards-review-after-reveal');
    await page.getByRole('button', { name: /Good/ }).click();
    await expect(page).toHaveURL(/\/flashcards\/review/);

    await page.goto('/progress');
    await expect(page.locator('h1')).toHaveText('Know what to review next');
    await assertPageHealthy(page, testInfo, 'progress');
    await page.goto('/history');
    await expect(page.locator('h1')).toHaveText('Quiz history');
    await assertPageHealthy(page, testInfo, 'history');

    await page.goto('/missing-browser-acceptance-route');
    await expect(page.locator('body')).not.toContainText('Stack trace');
    await expect(page.locator('body')).not.toContainText('Fatal error');
    await assertPageHealthy(page, testInfo, '404');
  });
});
