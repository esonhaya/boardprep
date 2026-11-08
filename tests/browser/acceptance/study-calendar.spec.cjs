'use strict';

const { test, expect, assertPageHealthy, tabToKeyboardFocus } = require('../fixtures/test.cjs');

function dateAfter(date, days) {
  const [year, month, day] = date.split('-').map(Number);
  return new Date(Date.UTC(year, month - 1, day + days)).toISOString().slice(0, 10);
}

test('learner can generate a transparent study calendar and complete a task on mobile and desktop', async ({ page }, testInfo) => {
  await page.goto('/study-calendar');
  await expect(page.getByRole('heading', { name: 'Study Calendar' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Set up your plan' })).toBeVisible();

  const board = page.locator('#calendar-board');
  await expect(board).toBeVisible();
  expect(await board.locator('option').count()).toBeGreaterThan(0);
  await tabToKeyboardFocus(page, board);
  await board.selectOption({ index: 0 });

  const today = await page.locator('#calendar-start-date').getAttribute('min');
  expect(today).toMatch(/^\d{4}-\d{2}-\d{2}$/);
  await page.locator('#calendar-exam-date').fill(dateAfter(today, 14));
  await page.locator('#calendar-minutes').fill('30');
  await page.getByRole('button', { name: 'Generate study calendar' }).click();

  await expect(page).toHaveURL(/\/study-calendar$/);
  await expect(page.getByRole('heading', { name: 'Your sessions' })).toBeVisible();
  const plan = page.locator('section[aria-labelledby="calendar-plan-heading"]');
  await expect(plan.getByRole('status')).toContainText(/Performance evidence:\s+INSUFFICIENT DATA/i);
  await expect(page.locator('.study-calendar-session').first()).toBeVisible();

  const firstSession = page.locator('.study-calendar-session').first();
  await expect(firstSession.getByRole('link', { name: 'Open this study activity' })).toBeVisible();
  await expect(firstSession.getByRole('button', { name: 'Mark complete' })).toBeVisible();
  await firstSession.getByRole('button', { name: 'Mark complete' }).click();
  await expect(page.getByRole('status').filter({ hasText: 'Study session updated.' })).toBeVisible();
  await expect(firstSession).toContainText('Completed');
  await expect(firstSession.getByRole('button', { name: 'Mark complete' })).toHaveCount(0);

  await page.setViewportSize({ width: 360, height: 800 });
  const layout = await page.evaluate(() => ({
    viewport: document.documentElement.clientWidth,
    document: document.documentElement.scrollWidth,
  }));
  expect(layout.document, 'calendar should not overflow a narrow mobile viewport').toBeLessThanOrEqual(layout.viewport + 2);
  await assertPageHealthy(page, testInfo, 'study-calendar-generated-and-completed');
});
