const fs = require('node:fs');

const { expect } = require('@playwright/test');

async function waitForVisualStability(page) {
  await page.waitForLoadState('domcontentloaded');
  await page.evaluate(async () => {
    if (document.fonts?.ready) await document.fonts.ready;
  });
  await page.addStyleTag({
    content: `
      *, *::before, *::after {
        animation-duration: 0s !important;
        animation-delay: 0s !important;
        transition-duration: 0s !important;
        transition-delay: 0s !important;
        caret-color: transparent !important;
      }
    `,
  });
}

async function inspectVisualLayout(page, {
  importantSelectors = [],
  mobile = false,
} = {}) {
  return page.evaluate(({ importantSelectors: selectors, mobileViewport }) => {
    const viewport = document.documentElement.clientWidth;
    const visible = (element) => {
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.display !== 'none'
        && style.visibility !== 'hidden'
        && Number(style.opacity) !== 0
        && rect.width > 0
        && rect.height > 0;
    };
    const describe = (element) => {
      const rect = element.getBoundingClientRect();
      return {
        selector: element.matches('[id]') ? `#${element.id}` : element.className || element.tagName,
        width: Math.round(rect.width * 100) / 100,
        height: Math.round(rect.height * 100) / 100,
        left: Math.round(rect.left * 100) / 100,
        right: Math.round(rect.right * 100) / 100,
      };
    };
    const elements = [];
    for (const selector of selectors) {
      for (const element of document.querySelectorAll(selector)) {
        if (visible(element)) elements.push(element);
      }
    }
    const outsideViewport = elements
      .filter((element) => {
        const rect = element.getBoundingClientRect();
        return rect.left < -2 || rect.right > viewport + 2;
      })
      .map(describe);
    const clippedControls = elements
      .filter((element) => {
        const rect = element.getBoundingClientRect();
        return rect.left < -2 || rect.right > viewport + 2 || rect.width < 1 || rect.height < 1;
      })
      .map(describe);
    const undersizedTouchTargets = mobileViewport
      ? elements
        .filter((element) => /^(A|BUTTON|SELECT|INPUT)$/.test(element.tagName))
        .filter((element) => {
          const rect = element.getBoundingClientRect();
          return rect.width < 40 || rect.height < 40;
        })
        .map(describe)
      : [];
    const tables = [...document.querySelectorAll('.question-table-wrap')]
      .filter(visible)
      .map((wrapper) => {
        const wrapperRect = wrapper.getBoundingClientRect();
        const table = wrapper.querySelector('table');
        const tableRect = table?.getBoundingClientRect();
        return {
          wrapper: describe(wrapper),
          table: table ? describe(table) : null,
          wrapperInsideViewport: wrapperRect.left >= -2 && wrapperRect.right <= viewport + 2,
          tableContained: Boolean(tableRect)
            && tableRect.left >= wrapperRect.left - 1
            && tableRect.right <= wrapperRect.right + 1,
          internallyScrollable: wrapper.scrollWidth > wrapper.clientWidth + 1,
        };
      });
    const figures = [...document.querySelectorAll('.question-figure')]
      .filter(visible)
      .map((figure) => {
        const image = figure.querySelector('img');
        const rect = figure.getBoundingClientRect();
        return {
          figure: describe(figure),
          imageLoaded: Boolean(image) && image.complete && image.naturalWidth > 0,
          imageVisible: Boolean(image) && image.getBoundingClientRect().width > 0,
          altText: image?.alt || '',
          insideViewport: rect.left >= -2 && rect.right <= viewport + 2,
        };
      });
    return {
      viewport,
      scrollWidth: document.documentElement.scrollWidth,
      pageOverflow: document.documentElement.scrollWidth > viewport + 2,
      outsideViewport,
      clippedControls,
      undersizedTouchTargets,
      tables,
      figures,
    };
  }, { importantSelectors, mobile });
}

async function assertBasicAccessibility(page, name) {
  const failures = await page.evaluate(() => {
    const visible = (element) => {
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.display !== 'none'
        && style.visibility !== 'hidden'
        && rect.width > 0
        && rect.height > 0;
    };
    const labelled = (element) => {
      if (element.getAttribute('aria-label')?.trim()) return true;
      if (element.getAttribute('aria-labelledby')?.trim()) return true;
      if (element.textContent?.trim()) return true;
      if (element.tagName === 'A' && element.querySelector('img[alt], svg[aria-label]')) return true;
      if (element.closest('label')) return true;
      return element.id !== '' && Boolean(document.querySelector(`label[for="${CSS.escape(element.id)}"]`));
    };
    return {
      buttons: [...document.querySelectorAll('button')].filter(visible).filter((element) => !labelled(element)).map((element) => element.outerHTML.slice(0, 160)),
      links: [...document.querySelectorAll('a[href]')].filter(visible).filter((element) => !labelled(element)).map((element) => element.outerHTML.slice(0, 160)),
      controls: [...document.querySelectorAll('input:not([type="hidden"]), select, textarea')].filter(visible).filter((element) => !labelled(element)).map((element) => element.outerHTML.slice(0, 160)),
      images: [...document.querySelectorAll('img')].filter(visible).filter((element) => !element.hasAttribute('alt')).map((element) => element.outerHTML.slice(0, 160)),
    };
  });
  expect(failures.buttons, `${name}: unnamed visible buttons`).toEqual([]);
  expect(failures.links, `${name}: unnamed visible links`).toEqual([]);
  expect(failures.controls, `${name}: unlabeled visible form controls`).toEqual([]);
  expect(failures.images, `${name}: visible images without alt text`).toEqual([]);
  return failures;
}

function assertLayoutReport(report, name) {
  expect(report.pageOverflow, `${name}: page-level horizontal overflow`).toBeFalsy();
  expect(report.outsideViewport, `${name}: important content outside viewport`).toEqual([]);
  expect(report.clippedControls, `${name}: important controls clipped`).toEqual([]);
  expect(report.undersizedTouchTargets, `${name}: primary touch target undersized`).toEqual([]);
  for (const table of report.tables) {
    expect(table.wrapperInsideViewport, `${name}: table wrapper outside viewport`).toBeTruthy();
    expect(table.table, `${name}: table missing`).not.toBeNull();
    expect(
      table.tableContained || table.internallyScrollable,
      `${name}: table exceeds its wrapper without intentional internal scrolling`,
    ).toBeTruthy();
  }
  for (const figure of report.figures) {
    expect(figure.imageLoaded, `${name}: figure image failed to load`).toBeTruthy();
    expect(figure.imageVisible, `${name}: figure image is not visible`).toBeTruthy();
    expect(figure.altText, `${name}: figure alt text missing`).not.toBe('');
    expect(figure.insideViewport, `${name}: figure outside viewport`).toBeTruthy();
  }
}

async function captureVisualState(page, testInfo, name, options = {}) {
  await waitForVisualStability(page);
  const report = await inspectVisualLayout(page, options);
  assertLayoutReport(report, name);
  await assertBasicAccessibility(page, name);

  const screenshotPath = testInfo.outputPath(`${name}.png`);
  await page.screenshot({
    path: screenshotPath,
    fullPage: options.fullPage === true,
    animations: 'disabled',
    caret: 'hide',
  });
  await testInfo.attach(`${name} visual screenshot`, {
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

  return report;
}

function visualAuditMarkdown(project, entries) {
  const lines = [
    '# BoardPrep Batch 570 visual audit',
    '',
    `- Project: \`${project}\``,
    '- Screenshots are deterministic viewport captures from the production path.',
    '- Geometry checks use a 2px page-overflow tolerance; intentional table scrolling is recorded separately.',
    '',
    '| State | Viewport | Scroll width | Page overflow | Outside viewport | Tables | Figures | Screenshot |',
    '| --- | ---: | ---: | --- | ---: | ---: | ---: | --- |',
  ];
  for (const entry of entries) {
    lines.push(`| ${entry.name} | ${entry.viewport}px | ${entry.scrollWidth}px | ${entry.pageOverflow ? 'FAIL' : 'PASS'} | ${entry.outsideViewport} | ${entry.tables} | ${entry.figures} | \`${entry.screenshot}\` |`);
  }
  lines.push('', '## Manual UX review', '', 'This report records measurable visual evidence. Human review of the retained screenshots should classify hierarchy, spacing, typography, navigation, form, quiz, result, study, flashcard, progress, history, and consistency findings before Batch 571. No subjective finding is asserted by automation.', '');
  return lines.join('\n');
}

function writeVisualAudit(testInfo, project, entries) {
  const reportPath = testInfo.outputPath('visual-audit.md');
  fs.writeFileSync(reportPath, visualAuditMarkdown(project, entries));
  return reportPath;
}

module.exports = {
  captureVisualState,
  assertBasicAccessibility,
  inspectVisualLayout,
  waitForVisualStability,
  writeVisualAudit,
};
