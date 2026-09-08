const { execFileSync } = require('node:child_process');
const path = require('node:path');

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
  exam = 'LET',
  subject = 'English',
  topic = '',
  count = '5',
  difficulty = 'mixed',
} = {}) {
  const query = new URLSearchParams({ exam, subject, topic, count, difficulty });
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
  await page.locator('#quiz-difficulty').selectOption(difficulty);
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

async function inspectStructuredQuestions(page, testInfo, targets, maxQuestions) {
  const found = new Set();

  for (let index = 0; index < maxQuestions; index += 1) {
    const questionId = await page.locator('input[name="question_id"]').inputValue();
    const target = targets[questionId];
    if (target) {
      await expect(page.locator(target.selector)).toHaveCount(1);
      if (target.visible) {
        await expect(page.locator(target.selector)).toBeVisible();
      }
      found.add(questionId);
      await assertPageHealthy(page, testInfo, `quiz-${target.name}`);
    }

    if (found.size === Object.keys(targets).length) return;

    await submitAnswer(page, false);
    const next = page.getByRole('button', { name: 'Next Question' });
    await expect(next).toBeVisible();
    await next.click();
    await expect(page.locator('.quiz-answer-form')).toBeVisible();
  }

  throw new Error(`Structured fixtures not rendered: ${JSON.stringify({
    expected: Object.keys(targets),
    found: [...found],
  })}`);
}

test.describe('BoardPrep learner browser acceptance', () => {
  test('dashboard navigation and mobile menu are usable', async ({ page }, testInfo) => {
    await page.goto('/dashboard');
    await expect(page.locator('h1')).toHaveText('Welcome back');
    for (const href of ['/exams', '/history', '/flashcards', '/progress', '/study-library']) {
      await expect(page.locator(`a[href="${href}"]:visible`).first()).toBeVisible();
    }
    const menu = page.locator('.mobile-menu-button');
    if (testInfo.project.name === 'mobile') {
      await expect(menu).toHaveAttribute('aria-expanded', 'false');
      await menu.click();
      await expect(menu).toHaveAttribute('aria-expanded', 'true');
      await expect(page.locator('#learner-nav')).toHaveClass(/is-open/);
      const mobileExamsLink = page.locator('#learner-nav a[href="/exams"]');
      await expect(mobileExamsLink).toBeVisible();
      await assertKeyboardFocus(page, mobileExamsLink);
      await menu.click();
      await expect(menu).toHaveAttribute('aria-expanded', 'false');
    } else {
      await expect(menu).toBeHidden();
      const desktopExamsLink = page.locator('#learner-nav a[href="/exams"]');
      await expect(desktopExamsLink).toBeVisible();
      await assertKeyboardFocus(page, desktopExamsLink);
    }
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
    await startQuiz(page, {
      exam: 'LET',
      subject: 'Science',
      topic: 'science-biology',
      count: '2',
      difficulty: 'easy',
    });
    await inspectStructuredQuestions(page, testInfo, {
      31: { name: 'svg', selector: '.question-figure img', visible: true },
    }, 2);

    await startQuiz(page, {
      exam: 'LET',
      subject: 'Science',
      topic: 'science-data-graphs',
      count: '2',
      difficulty: 'hard',
    });
    await inspectStructuredQuestions(page, testInfo, {
      40: { name: 'table', selector: '.question-table-wrap', visible: true },
    }, 2);
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
    await assertPageHealthy(page, testInfo, '404', {
      allowHttpErrors: [{
        status: 404,
        url: page.url(),
        resourceType: 'document',
      }],
    });
  });
});
