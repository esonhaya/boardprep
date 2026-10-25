'use strict';

const { test, expect, assertPageHealthy, tabToKeyboardFocus, recordBrowserEvidence } = require('../fixtures/test.cjs');

const productionBase = process.env.BOARDPREP_BROWSER_URL || 'http://127.0.0.1:4173';
const developerBase = process.env.BOARDPREP_DEVELOPER_BROWSER_URL || 'http://127.0.0.1:4174';
const authBase = process.env.BOARDPREP_AUTH_BROWSER_URL || 'http://127.0.0.1:4175';

async function signInSyntheticReviewer(page) {
  await page.goto(`${authBase}/account/login`);
  await page.locator('#account-username').fill('b716-synthetic-reviewer');
  await page.locator('#account-password').fill('B716 synthetic browser fixture password only');
  await page.getByRole('button', { name: 'Sign in' }).click();
  await expect(page).not.toHaveURL(/\/account\/login(?:\?|$)/);
}

function allowExpectedDocumentError(page, response, status) {
  const expected = page.__boardPrepDiagnostics.httpErrors.find((entry) => (
    entry.status === status && entry.url === response.url() && entry.resourceType === 'document'
  ));
  expect(expected).toBeTruthy();
  page.__boardPrepDiagnostics.allowedHttpErrors.push(expected);
}

test.describe('Academic review pilot access boundary', () => {
  test('B715-PILOT-SEC-01 production has no academic review route', async ({ page }) => {
    const url = `${productionBase}/developer/academic-review?pilot=1`;
    const response = await page.goto(url);
    expect(response?.status()).toBe(404);
    allowExpectedDocumentError(page, response, 404);
    await expect(page.locator('body')).toContainText('Route not found.');
    await expect(page.locator('body')).not.toContainText('20-item academic pilot');
  });

  test('B715-PILOT-SEC-02 anonymous developer browser cannot inspect pilot proposals', async ({ page }) => {
    const url = `${developerBase}/developer/academic-review?pilot=1`;
    const response = await page.goto(url);
    expect(response?.status()).toBe(401);
    allowExpectedDocumentError(page, response, 401);
    await expect(page.locator('body')).toContainText('Authenticated reviewer account required.');
    await expect(page.locator('body')).not.toContainText('Proposal #9124');
  });

  test('B716-PILOT-UX-01 synthetic scoped reviewer can search and filter the read-only pilot queue', async ({ page }, testInfo) => {
    await signInSyntheticReviewer(page);
    const response = await page.goto(`${authBase}/developer/academic-review?pilot=1&board=let&subject=early-childhood-education`);
    expect(response?.status()).toBe(200);
    await expect(page.getByRole('heading', { name: 'Academic review' })).toBeVisible();
    await expect(page.locator('.proposal-item')).toHaveCount(4);
    const visibleIds = await page.locator('.proposal-item').evaluateAll((items) => items.map((item) => item.dataset.proposalId).sort());
    expect(visibleIds).toEqual(['batch695-9200', 'batch695-9204', 'batch695-9210', 'batch695-9228']);
    await expect(page.locator('#review-pilot')).toHaveValue('1');
    await tabToKeyboardFocus(page, page.getByRole('button', { name: 'Apply filters' }));
    await page.locator('#review-search').fill('9204');
    await page.getByRole('button', { name: 'Apply filters' }).click();
    await expect(page.locator('.proposal-item')).toHaveCount(1);
    await expect(page.locator('.proposal-item').first()).toContainText('Proposal #9204');
    await page.locator('#review-assigned').selectOption('mine');
    await page.getByRole('button', { name: 'Apply filters' }).click();
    await expect(page.locator('.proposal-item')).toHaveCount(0);
    await expect(page.locator('.empty-state')).toContainText('No proposals match');
    await assertPageHealthy(page, testInfo, 'b716-reviewer-queue', { screenshot: false });
    recordBrowserEvidence('b716-academic-pilot', {
      reviewer: 'SYNTHETIC_TEST_FIXTURE_ONLY',
      scope: 'LET / Early Childhood Education',
      queue_items_in_scope: 4,
      assigned_to_me_items: 0,
      real_decisions_or_assignments: 0,
      result: 'ASSERTIONS_PREPARED_FOR_SUPPORTED_RUNNER',
    }, testInfo);
  });

  test('B716-PILOT-UX-02 version-bound inspector presents source provenance, blockers, and print packet without mutation', async ({ page }, testInfo) => {
    await page.addInitScript(() => { window.__b716PrintRequested = false; window.print = () => { window.__b716PrintRequested = true; }; });
    await signInSyntheticReviewer(page);
    const response = await page.goto(`${authBase}/developer/academic-review/proposal?id=batch695-9348`);
    expect(response?.status()).toBe(200);
    await expect(page.getByRole('heading', { name: 'Proposal #9348' })).toBeVisible();
    await expect(page.locator('#pilot-evidence')).toContainText('matches prepared dossier');
    await expect(page.getByRole('heading', { name: 'Batch 716 source recheck · not expert review' })).toBeVisible();
    await expect(page.locator('.batch716-source-matrix .pilot-stale-warning')).toContainText('Supplemental evidence version is stale');
    await expect(page.locator('.batch716-source-row').first()).toContainText('pp. 65–66');
    await expect(page.locator('#decision-history')).toContainText('No academic decision or source review has been recorded');
    await expect(page.getByRole('button', { name: 'Assign to me' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Import this approved proposal' })).toHaveCount(0);
    const printButton = page.getByRole('button', { name: 'Print review packet for proposal 9348' });
    await expect(printButton).toBeVisible();
    await printButton.click();
    await expect.poll(() => page.evaluate(() => window.__b716PrintRequested)).toBeTruthy();
    await page.emulateMedia({ media: 'print' });
    await page.evaluate(() => window.dispatchEvent(new Event('beforeprint')));
    await expect.poll(() => page.locator('.batch716-source-row').first().evaluate((node) => node.open)).toBeTruthy();
    await page.evaluate(() => {
      const packet = document.querySelector('[data-review-packet]');
      const marker = document.createElement('span');
      marker.id = 'b716-synthetic-private-print-marker';
      marker.className = 'academic-review-private-metadata';
      marker.textContent = 'SYNTHETIC_PRIVATE_ACCOUNT_MARKER';
      packet?.append(marker);
    });
    await expect(page.locator('#b716-synthetic-private-print-marker')).toBeHidden();
    await page.emulateMedia({ media: 'screen' });
    await page.evaluate(() => window.dispatchEvent(new Event('afterprint')));
    await page.locator('#b716-synthetic-private-print-marker').evaluate((node) => node.remove());
    await assertPageHealthy(page, testInfo, 'b716-version-bound-review-packet', { screenshot: false });
    recordBrowserEvidence('b716-academic-pilot', {
      proposal: '9348',
      version: 'current B715 proposal version',
      inspected_source_pages: [65, 66],
      source_expert_review: 'PENDING_QUALIFIED_REVIEWER',
      academic_decision: 'NONE',
      canonical_import: 'NONE',
      print_packet: 'PRIVATE_PAGE; reviewer identifiers hidden in print CSS',
      result: 'ASSERTIONS_PREPARED_FOR_SUPPORTED_RUNNER',
    }, testInfo);
  });

  test('B716-PILOT-SEC-03 synthetic reviewer scope cannot inspect a foreign subject proposal', async ({ page }) => {
    await signInSyntheticReviewer(page);
    const response = await page.goto(`${authBase}/developer/academic-review/proposal?id=batch695-9290`);
    expect(response?.status()).toBe(403);
    allowExpectedDocumentError(page, response, 403);
    await expect(page.locator('body')).not.toContainText('Proposal #9290');
  });
});
