const fs = require('node:fs');

const { test: base, expect } = require('@playwright/test');

function isKnownOptionalBrowserResponse(error) {
  let url;
  try {
    url = new URL(error.url);
  } catch {
    return false;
  }

  // Chromium may request this optional browser chrome asset even though the
  // application does not declare a favicon. Other 4xx/5xx responses fail.
  return error.status === 404
    && url.pathname === '/favicon.ico'
    && error.resourceType === 'image';
}

function installDiagnostics(page) {
  const diagnostics = {
    consoleErrors: [],
    pageErrors: [],
    failedRequests: [],
    httpErrors: [],
    allowedHttpErrors: [],
    brokenImages: [],
  };

  page.on('console', (message) => {
    if (message.type() === 'error') {
      diagnostics.consoleErrors.push({
        message: message.text(),
        type: message.type(),
        pageUrl: page.url(),
        location: message.location(),
      });
    }
  });
  page.on('pageerror', (error) => {
    diagnostics.pageErrors.push({
      message: String(error),
      pageUrl: page.url(),
    });
  });
  page.on('requestfailed', (request) => {
    diagnostics.failedRequests.push({
      url: request.url(),
      method: request.method(),
      resourceType: request.resourceType(),
      pageUrl: page.url(),
      error: request.failure()?.errorText || 'request failed',
    });
  });
  page.on('response', (response) => {
    if (response.status() >= 400) {
      diagnostics.httpErrors.push({
        url: response.url(),
        status: response.status(),
        resourceType: response.request().resourceType(),
        pageUrl: page.url(),
      });
      const error = diagnostics.httpErrors.at(-1);
      if (isKnownOptionalBrowserResponse(error)) {
        diagnostics.allowedHttpErrors.push(error);
      }
    }
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
  for (const expected of options.allowHttpErrors || []) {
    for (const error of diagnostics.httpErrors) {
      if (error.status === expected.status
        && error.url === expected.url
        && error.resourceType === expected.resourceType
        && !diagnostics.allowedHttpErrors.includes(error)) {
        diagnostics.allowedHttpErrors.push(error);
      }
    }
  }

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
        || diagnostics.httpErrors.some((error) => !diagnostics.allowedHttpErrors.includes(error))
      )) {
        throw new Error(`Browser diagnostics reported errors: ${JSON.stringify({
          page: page.url(),
          consoleErrors: diagnostics.consoleErrors,
          pageErrors: diagnostics.pageErrors,
          failedRequests: diagnostics.failedRequests,
          httpErrors: diagnostics.httpErrors.filter(
            (error) => !diagnostics.allowedHttpErrors.includes(error)
          ),
        })}`);
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
