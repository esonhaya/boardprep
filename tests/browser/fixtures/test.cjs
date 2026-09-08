const fs = require('node:fs');

const { test: base, expect } = require('@playwright/test');

function installDiagnostics(page) {
  const diagnostics = {
    consoleErrors: [],
    pageErrors: [],
    failedRequests: [],
    brokenImages: [],
  };

  page.on('console', (message) => {
    if (message.type() === 'error') {
      diagnostics.consoleErrors.push(message.text());
    }
  });
  page.on('pageerror', (error) => {
    diagnostics.pageErrors.push(String(error));
  });
  page.on('requestfailed', (request) => {
    diagnostics.failedRequests.push({
      url: request.url(),
      method: request.method(),
      error: request.failure()?.errorText || 'request failed',
    });
  });

  return diagnostics;
}

async function assertPageHealthy(page, testInfo, name, options = {}) {
  await page.waitForLoadState('domcontentloaded');

  const brokenImages = await page.locator('img').evaluateAll((images) => images
    .filter((image) => image.complete && image.naturalWidth === 0)
    .map((image) => image.currentSrc || image.src || image.alt || 'unnamed image'));
  const overflow = await page.evaluate(() => ({
    width: document.documentElement.scrollWidth,
    viewport: document.documentElement.clientWidth,
  }));

  const diagnostics = page.__boardPrepDiagnostics;
  diagnostics.brokenImages.push(...brokenImages);

  if (overflow.width > overflow.viewport + 2) {
    throw new Error(`Material page overflow on ${name}: ${overflow.width}px > ${overflow.viewport}px`);
  }
  if (brokenImages.length > 0) {
    throw new Error(`Broken rendered images on ${name}: ${brokenImages.join(', ')}`);
  }

  if (options.screenshot !== false) {
    const screenshotPath = testInfo.outputPath(`${name.replace(/[^a-z0-9_-]+/gi, '-')}.png`);
    await page.screenshot({ path: screenshotPath, fullPage: true });
    await testInfo.attach(`${name} screenshot`, {
      path: screenshotPath,
      contentType: 'image/png',
    });

    if (process.env.VISUAL_REGRESSION === '1') {
      await expect(page).toHaveScreenshot(`${name}.png`, {
        animations: 'disabled',
        caret: 'hide',
        maxDiffPixels: 250,
        threshold: 0.2,
        mask: [
          page.locator('.stat-value'),
          page.locator('.quiz-meta'),
          page.locator('.flashcard-progress'),
        ],
      });
    }
  }

  return { overflow, diagnostics };
}

async function assertKeyboardFocus(page, locator) {
  await locator.focus();
  await expect(locator).toBeFocused();
  const visible = await locator.evaluate((element) => {
    const style = window.getComputedStyle(element);
    return style.outlineStyle !== 'none'
      || style.boxShadow !== 'none'
      || element.matches(':focus-visible');
  });
  expect(visible).toBeTruthy();
}

const test = base.extend({
  page: async ({ page }, use, testInfo) => {
    const diagnostics = installDiagnostics(page);
    Object.defineProperty(page, '__boardPrepDiagnostics', { value: diagnostics });
    try {
      await use(page);
    } finally {
      const reportPath = testInfo.outputPath('browser-diagnostics.json');
      fs.writeFileSync(reportPath, JSON.stringify(diagnostics, null, 2));
      await testInfo.attach('browser diagnostics', {
        path: reportPath,
        contentType: 'application/json',
      });
      if (testInfo.status === 'passed' && (
        diagnostics.consoleErrors.length > 0
        || diagnostics.pageErrors.length > 0
        || diagnostics.failedRequests.length > 0
      )) {
        throw new Error(`Browser diagnostics reported errors: ${JSON.stringify(diagnostics)}`);
      }
    }
  },
});

module.exports = {
  test,
  expect,
  assertPageHealthy,
  assertKeyboardFocus,
};
