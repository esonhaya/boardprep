'use strict';

const fs = require('node:fs');
const path = require('node:path');
const { test, expect, assertPageHealthy, tabToKeyboardFocus } = require('../fixtures/test.cjs');

const root = path.resolve(__dirname, '../../..');
const questions = JSON.parse(fs.readFileSync(path.join(root, 'storage/questions.json'), 'utf8'));

function normalized(value) {
  return String(value ?? '').replace(/\s+/g, ' ').trim().toLowerCase();
}

function canonicalQuestion(id) {
  return questions.find((question) => String(question?.id) === String(id));
}

test('learner can report a result question and reports stay private to the session', async ({ page, browser }, testInfo) => {
  await page.goto('/quiz?exam=LET');
  await expect(page.locator('#quiz-exam')).toHaveValue('LET');
  const subject = page.locator('#quiz-subject option[data-exam="LET"]').first();
  await expect(subject).toHaveCount(1);
  await page.locator('#quiz-subject').selectOption(await subject.getAttribute('value'));
  await page.locator('#quiz-count').selectOption('5');
  await page.locator('#quiz-mode').selectOption('practice');
  await page.getByRole('button', { name: 'Start practice' }).click();
  await expect(page.locator('.quiz-question')).toBeVisible();

  for (let index = 0; index < 5; index += 1) {
    const questionId = await page.locator('input[name="question_id"]').inputValue();
    const question = canonicalQuestion(questionId);
    expect(question, `quiz question ${questionId} must remain canonical`).toBeTruthy();
    const correctIndex = question.choices.findIndex((choice) => normalized(choice) === normalized(question.answer));
    expect(correctIndex, `canonical answer must exist for question ${questionId}`).toBeGreaterThanOrEqual(0);
    await page.locator('input[name="answer"]').nth(correctIndex).check();
    await page.getByRole('button', { name: 'Submit Answer' }).click();
    await expect(page.locator('.quiz-feedback')).toBeVisible();
    await page.getByRole('button', { name: index === 4 ? 'Finish Quiz' : 'Next Question' }).click();
  }

  await expect(page.getByRole('heading', { name: 'Quiz Result' })).toBeVisible();
  const reportLink = page.getByRole('link', { name: 'Report this question' }).first();
  await expect(reportLink).toBeVisible();
  const href = await reportLink.getAttribute('href');
  expect(href).toMatch(/^\/question-report\?question_id=/);
  await reportLink.click();

  await expect(page.getByRole('heading', { name: 'Report a question concern' })).toBeVisible();
  const type = page.locator('#question-report-type');
  await tabToKeyboardFocus(page, type);
  await type.selectOption('UNCLEAR_WORDING');
  const note = 'Please review this wording <script>alert("x")</script>.';
  await page.locator('#question-report-description').fill(note);
  await expect(page.locator('#question-report-description')).toHaveAttribute('maxlength', '500');
  await page.getByRole('button', { name: 'Submit concern' }).click();

  await expect(page).toHaveURL(/\/question-reports$/);
  await expect(page.getByRole('heading', { name: 'My question reports' })).toBeVisible();
  const report = page.locator('.learner-question-report-record').first();
  await expect(report).toContainText('Submitted');
  await expect(report).toContainText('Wording is unclear');
  await expect(report).toContainText(note);
  await expect(page.locator('.learner-question-report-record script')).toHaveCount(0);
  await assertPageHealthy(page, testInfo, 'learner-question-report-private-list');

  const otherContext = await browser.newContext();
  try {
    const otherPage = await otherContext.newPage();
    await otherPage.goto('/question-reports');
    await expect(otherPage.getByRole('heading', { name: 'My question reports' })).toBeVisible();
    await expect(otherPage.getByRole('status')).toContainText('No reports yet');
    await expect(otherPage.locator('.learner-question-report-record')).toHaveCount(0);
  } finally {
    await otherContext.close();
  }
});
