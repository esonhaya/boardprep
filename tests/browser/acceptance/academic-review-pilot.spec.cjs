'use strict';

const { test, expect } = require('../fixtures/test.cjs');

const productionBase = process.env.BOARDPREP_BROWSER_URL || 'http://127.0.0.1:4173';
const developerBase = process.env.BOARDPREP_DEVELOPER_BROWSER_URL || 'http://127.0.0.1:4174';

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
});
