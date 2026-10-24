'use strict';

const fs = require('node:fs');
const path = require('node:path');
const { test, expect, assertPageHealthy } = require('../fixtures/test.cjs');

const root = path.resolve(__dirname, '../../..');
const questions = JSON.parse(fs.readFileSync(path.join(root, 'storage/questions.json'), 'utf8'));
const topics = JSON.parse(fs.readFileSync(path.join(root, 'storage/taxonomy/topics.json'), 'utf8'));

function normalized(value) {
  return String(value ?? '').replace(/\s+/g, ' ').trim().toLowerCase();
}

function canonicalQuestion(id) {
  return questions.find((question) => String(question?.id) === String(id));
}

async function submit(page, question, wrong) {
  const choices = Array.isArray(question?.choices) ? question.choices : [];
  const correct = choices.findIndex((choice) => normalized(choice) === normalized(question?.answer));
  expect(correct, `canonical answer must appear among question ${question?.id} choices`).toBeGreaterThanOrEqual(0);
  const selected = wrong && choices.length > 1 ? (correct + 1) % choices.length : correct;
  await page.locator('input[name="answer"]').nth(selected).check();
  await page.getByRole('button', { name: 'Submit Answer' }).click();
  await expect(page.locator('.quiz-feedback')).toBeVisible();
}

test('Mistake Notebook supports ordinary quiz, explanation, safe retry, and recovery status', async ({ page }, testInfo) => {
  await page.goto('/quiz?exam=LET');
  await expect(page.locator('#quiz-exam')).toHaveValue('LET');
  const subject = page.locator('#quiz-subject option[data-exam="LET"]').first();
  await expect(subject).toHaveCount(1);
  await page.locator('#quiz-subject').selectOption(await subject.getAttribute('value'));
  await page.locator('#quiz-count').selectOption('5');
  await page.locator('#quiz-mode').selectOption('practice');
  await page.getByRole('button', { name: 'Start practice' }).click();
  await expect(page.locator('.quiz-question')).toBeVisible();

  const questionIds = [];
  for (let index = 0; index < 5; index += 1) {
    const id = await page.locator('input[name="question_id"]').inputValue();
    const question = canonicalQuestion(id);
    expect(question, `quiz question ${id} must remain canonical`).toBeTruthy();
    questionIds.push(id);
    await submit(page, question, index === 0);
    await page.getByRole('button', { name: index === 4 ? 'Finish Quiz' : 'Next Question' }).click();
  }
  await expect(page.getByRole('heading', { name: 'Quiz Result' })).toBeVisible();
  await expect(page.locator('.result-answer-review')).toContainText(canonicalQuestion(questionIds[0]).question);
  await assertPageHealthy(page, testInfo, 'mistake-notebook-ordinary-result');

  await page.goto('/mistake-notebook?board=let');
  await expect(page.getByRole('heading', { name: 'Mistake Notebook' })).toBeVisible();
  const reviewLink = page.getByRole('link', { name: 'Understand this answer' }).first();
  await expect(reviewLink).toBeVisible();
  const reviewUrl = await reviewLink.getAttribute('href');
  await reviewLink.click();
  await expect(page.getByRole('heading', { name: 'Understand this answer' })).toBeVisible();
  await expect(page.getByText('Saved explanation')).toBeVisible();
  await expect(page.getByText('Your recorded answer')).toBeVisible();
  await assertPageHealthy(page, testInfo, 'mistake-notebook-review');

  const anchor = new URL(reviewUrl, 'http://boardprep.test');
  const missedId = anchor.searchParams.get('question_id');
  const missed = canonicalQuestion(missedId);
  expect(missed, `mistake question ${missedId} remains canonical`).toBeTruthy();
  await page.getByLabel('Maximum session size').selectOption('5');
  await page.getByRole('button', { name: 'Retry Missed Questions' }).click();
  await expect(page.locator('.quiz-question')).toBeVisible();
  const retryId = await page.locator('input[name="question_id"]').inputValue();
  expect(retryId).toBe(missedId);
  await submit(page, canonicalQuestion(retryId), false);
  await page.getByRole('button', { name: 'Finish Quiz' }).click();
  await expect(page.locator('.mistake-recovery-result')).toContainText(/Correct:\s*1/);
  await page.getByRole('link', { name: 'Return to Mistake Notebook' }).click();
  await expect(page.locator('.mistake-card-list')).toContainText('Correct on latest retry');
  await assertPageHealthy(page, testInfo, 'mistake-notebook-recovered');

  const topicId = missed.taxonomy?.topic_id;
  if (topicId) {
    const topic = topics.find((row) => String(row.id) === String(topicId));
    expect(topic || topicId).toBeTruthy();
  }
});

test('Mistake Notebook empty state and filters remain understandable at mobile width', async ({ page }, testInfo) => {
  await page.setViewportSize({ width: 360, height: 800 });
  await page.goto('/mistake-notebook');
  await expect(page.getByRole('heading', { name: 'Mistake Notebook' })).toBeVisible();
  await expect(page.getByRole('status')).toContainText(/There are no recorded mistakes yet|Try clearing or changing the filters/);
  await expect(page.getByLabel('Examination board')).toBeVisible();
  await expect(page.getByLabel('Subject')).toBeVisible();
  await assertPageHealthy(page, testInfo, 'mistake-notebook-mobile');
});
