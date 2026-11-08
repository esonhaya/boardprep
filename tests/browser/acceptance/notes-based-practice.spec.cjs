'use strict';

const fs = require('node:fs');
const path = require('node:path');
const {
  test,
  expect,
  assertPageHealthy,
} = require('../fixtures/test.cjs');

const root = path.resolve(__dirname, '../../..');
const canonicalQuestions = JSON.parse(fs.readFileSync(path.join(root, 'storage/questions.json'), 'utf8'));
const boards = JSON.parse(fs.readFileSync(path.join(root, 'storage/boards.json'), 'utf8'));
const topics = JSON.parse(fs.readFileSync(path.join(root, 'storage/topics.json'), 'utf8'));

function supportedQuestion() {
  const board = boards.find((item) => String(item.id) === 'let');
  if (!board) throw new Error('LET board fixture is unavailable.');
  const question = canonicalQuestions.find((item) => (
    item && item.status !== 'archived'
    && item.taxonomy?.board_id === 'let'
    && item.taxonomy?.subject_id
    && typeof item.question === 'string'
    && item.question.trim().length > 20
    && Array.isArray(item.choices)
    && item.choices.length > 1
    && item.choices.some((choice) => String(choice).trim().toLowerCase() === String(item.answer || '').trim().toLowerCase())
  ));
  if (!question) throw new Error('No valid canonical LET question is available for notes practice.');
  const topic = topics.find((item) => String(item.id) === String(question.taxonomy.topic_id || ''));
  return { board, question, topic };
}

test('Practice Based on My Notes completes a private learner quiz journey', async ({ page }, testInfo) => {
  const { board, question, topic } = supportedQuestion();
  const title = `Browser note ${Date.now()}`;
  const noteBody = `# ${topic?.name || question.taxonomy.topic_id || 'Study topic'}\n\n${question.question}\n\nReview the main distinction and its application.`;

  await page.goto('/my-notes');
  await expect(page.getByRole('heading', { name: 'Practice Based on My Notes' })).toBeVisible();
  await page.getByLabel('Note title').fill(title);
  await page.getByLabel('Examination board').selectOption(String(board.id));
  await page.getByRole('textbox', { name: 'Study notes' }).fill(noteBody);
  await page.getByRole('button', { name: 'Save note' }).click();
  await expect(page).toHaveURL(/\/my-notes\?note=note-[a-f0-9]{32}$/);
  await expect(page.getByRole('heading', { name: 'Practice from these notes' })).toBeVisible();

  const availableText = await page.locator('.notes-match-panel > p')
    .filter({ hasText: /^Questions available:/ })
    .textContent();
  const available = Number((availableText || '').match(/\d+/)?.[0] || 0);
  expect(available, 'the note should match at least one existing, eligible canonical question').toBeGreaterThan(0);
  expect(await page.locator('.notes-match-reasons li').count()).toBeGreaterThan(0);
  await expect(page.locator('.notes-match-panel')).toContainText('not academic endorsement');

  const count = available >= 5 ? 5 : available;
  await page.locator('#notes-practice-count').selectOption(String(count));
  await page.getByRole('button', { name: 'Practice From These Notes' }).click();
  await expect(page.locator('.quiz-question')).toBeVisible();
  const total = Number((await page.locator('.quiz-question').textContent()).match(/Question\s+1\s*\/\s*(\d+)/i)?.[1] || count);
  expect(total).toBeGreaterThan(0);
  expect(total).toBeLessThanOrEqual(count);

  for (let index = 0; index < total; index += 1) {
    const questionId = await page.locator('input[name="question_id"]').inputValue();
    const current = canonicalQuestions.find((item) => String(item.id) === questionId);
    expect(current, `question ${questionId} must remain canonical`).toBeTruthy();
    const choices = current.choices || [];
    const correctIndex = choices.findIndex((choice) => String(choice).trim().toLowerCase() === String(current.answer).trim().toLowerCase());
    expect(correctIndex).toBeGreaterThanOrEqual(0);
    const selectedIndex = index === 0 && choices.length > 1 ? (correctIndex + 1) % choices.length : correctIndex;
    await page.locator('input[name="answer"]').nth(selectedIndex).check();
    await expect(page.locator('input[name="answer"]').nth(selectedIndex)).toBeChecked();
    await page.getByRole('button', { name: 'Submit Answer' }).click();
    await expect(page.locator('.quiz-feedback')).toBeVisible();
    await page.getByRole('button', { name: index === total - 1 ? 'Finish Quiz' : 'Next Question' }).click();
  }

  await expect(page.getByRole('heading', { name: 'Quiz Result' })).toBeVisible();
  await expect(page.locator('.notes-practice-result')).toContainText(title);
  await expect(page.locator('.notes-practice-result')).toContainText('learner-written material');
  await expect(page.locator('.result-answer-review')).toContainText('From your note');
  await expect(page.locator('.notes-practice-result')).toContainText('Topics to revisit');
  await expect(page.locator('.result-next-steps')).toContainText('Return to my note');
  await assertPageHealthy(page, testInfo, 'notes-practice-result');

  await page.getByRole('link', { name: 'Return to this note' }).click();
  await page.getByRole('textbox', { name: 'Study notes' }).fill(`${noteBody}\n\nUpdated after the quiz.`);
  await page.getByRole('button', { name: 'Save changes' }).click();
  await expect(page.locator('#my-note-body')).toHaveValue(/Updated after the quiz/);
  await page.goto('/quiz?action=result');
  await expect(page.locator('.notes-practice-result')).toContainText(title);

  const isolated = await page.context().browser().newContext();
  const otherLearner = await isolated.newPage();
  await otherLearner.goto('/my-notes');
  await expect(otherLearner.getByRole('heading', { name: 'My notes' })).toBeVisible();
  await expect(otherLearner.locator('body')).not.toContainText(title);
  await isolated.close();

  await page.getByRole('link', { name: 'Return to this note' }).click();
  await page.getByLabel('I understand this permanently deletes my saved note.').check();
  await page.getByRole('button', { name: 'Delete note' }).click();
  await expect(page.getByText('The note was deleted.')).toBeVisible();
  await assertPageHealthy(page, testInfo, 'notes-delete-confirmed');
});
