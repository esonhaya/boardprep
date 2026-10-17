const fs = require('node:fs');
const path = require('node:path');

const { test: base, expect } = require('@playwright/test');
const {
  isKnownOptionalBrowserResponse,
  matchesExpectedHttpError,
  matchesExpectedConsoleError,
} = require('./diagnostics.cjs');

function installDiagnostics(page) {
  const diagnostics = {
    consoleErrors: [],
    pageErrors: [],
    failedRequests: [],
    allowedFailedRequests: [],
    httpErrors: [],
    allowedHttpErrors: [],
    expectedHttpErrors: [],
    allowedConsoleErrors: [],
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
    const failure = {
      url: request.url(),
      method: request.method(),
      resourceType: request.resourceType(),
      pageUrl: page.url(),
      error: request.failure()?.errorText || 'request failed',
    };
    let requestPath = '';
    try {
      requestPath = new URL(failure.url).pathname;
    } catch {
      requestPath = '';
    }
    if (failure.method === 'GET'
      && failure.resourceType === 'document'
      && requestPath === '/privacy/export'
      && failure.error === 'net::ERR_ABORTED') {
      diagnostics.allowedFailedRequests.push(failure);
      return;
    }
    diagnostics.failedRequests.push(failure);
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

function recordBrowserEvidence(name, evidence, testInfo) {
  const directory = process.env.BOARDPREP_BROWSER_EVIDENCE_DIR;
  if (!directory) return;

  fs.mkdirSync(directory, { recursive: true });
  const evidencePath = path.join(directory, `${name}.json`);
  let record = { projects: [], results: {} };
  if (fs.existsSync(evidencePath)) {
    record = JSON.parse(fs.readFileSync(evidencePath, 'utf8'));
  }
  record.projects = [...new Set([...(record.projects || []), testInfo.project.name])].sort();
  record.results = {
    ...(record.results || {}),
    [testInfo.project.name]: {
      ...(record.results?.[testInfo.project.name] || {}),
      ...evidence,
    },
  };
  fs.writeFileSync(evidencePath, JSON.stringify(record, null, 2));
}

function markExpectedConsoleErrors(diagnostics) {
  for (const expected of diagnostics.expectedHttpErrors) {
    const matchingResponse = diagnostics.httpErrors.some(
      (error) => matchesExpectedHttpError(error, expected)
    );
    if (!matchingResponse) continue;

    for (const error of diagnostics.consoleErrors) {
      if (matchesExpectedConsoleError(error, expected)
        && !diagnostics.allowedConsoleErrors.includes(error)) {
        diagnostics.allowedConsoleErrors.push(error);
      }
    }
  }
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
      if (matchesExpectedHttpError(error, expected)
        && !diagnostics.allowedHttpErrors.includes(error)) {
        diagnostics.allowedHttpErrors.push(error);
      }
    }
    if (!diagnostics.expectedHttpErrors.includes(expected)) {
      diagnostics.expectedHttpErrors.push(expected);
    }
  }
  markExpectedConsoleErrors(diagnostics);

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

async function tabToKeyboardFocus(page, locator, maxTabs = 30) {
  await page.evaluate(() => {
    // Establish a deterministic starting point; the target is reached only
    // through real keyboard Tab events below.
    const body = document.body;
    body.setAttribute('tabindex', '-1');
    body.focus({ preventScroll: true });
    body.removeAttribute('tabindex');
  });

  for (let index = 0; index < maxTabs; index += 1) {
    await page.keyboard.press('Tab');
    if (await locator.evaluate((element) => element === document.activeElement)) {
      const visible = await locator.evaluate((element) => {
        const style = window.getComputedStyle(element);
        return element.matches(':focus-visible')
          && (style.outlineStyle !== 'none' || style.boxShadow !== 'none');
      });
      expect(visible).toBeTruthy();
      return;
    }
  }

  throw new Error(`Keyboard Tab traversal did not reach ${await locator.evaluate(
    (element) => element.outerHTML.slice(0, 160)
  )}`);
}

const test = base.extend({
  page: async ({ page }, use, testInfo) => {
    const diagnostics = installDiagnostics(page);
    Object.defineProperty(page, '__boardPrepDiagnostics', { value: diagnostics });
    try {
      await use(page);
    } finally {
      markExpectedConsoleErrors(diagnostics);
      const reportPath = testInfo.outputPath('browser-diagnostics.json');
      const browser = page.context().browser();
      const diagnosticsReport = {
        engine: browser?.browserType().name() || 'unknown',
        version: browser?.version() || 'unknown',
        project: testInfo.project.name,
        viewport: page.viewportSize(),
        ...diagnostics,
      };
      fs.writeFileSync(reportPath, JSON.stringify(diagnosticsReport, null, 2));
      await testInfo.attach('browser diagnostics', {
        path: reportPath,
        contentType: 'application/json',
      });
      if (testInfo.status === 'passed' && (
        diagnostics.consoleErrors.some(
          (error) => !diagnostics.allowedConsoleErrors.includes(error)
        )
        || diagnostics.pageErrors.length > 0
        || diagnostics.failedRequests.length > 0
        || diagnostics.httpErrors.some((error) => !diagnostics.allowedHttpErrors.includes(error))
      )) {
        throw new Error(`Browser diagnostics reported errors: ${JSON.stringify({
          page: page.url(),
          consoleErrors: diagnostics.consoleErrors.filter(
            (error) => !diagnostics.allowedConsoleErrors.includes(error)
          ),
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
  tabToKeyboardFocus,
  recordBrowserEvidence,
};
