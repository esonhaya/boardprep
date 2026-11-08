'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { runInNewContext } = require('node:vm');
const test = require('node:test');
const { collectHorizontalOverflowDiagnostics } = require('./overflow-diagnostics.cjs');

const root = path.resolve(__dirname, '../../..');

function node(tagName, className, rect, options = {}) {
  const element = {
    tagName: tagName.toUpperCase(),
    className,
    rect,
    parentElement: null,
    children: [],
    scrollWidth: options.scrollWidth ?? Math.max(0, rect.right - rect.left),
    clientWidth: options.clientWidth ?? Math.max(0, rect.right - rect.left),
    styles: options.styles || {},
    getBoundingClientRect() { return this.rect; },
  };
  Object.defineProperty(element, 'value', {
    get() { throw new Error('diagnostics must not read form values'); },
  });
  Object.defineProperty(element, 'textContent', {
    get() { throw new Error('diagnostics must not read rendered text'); },
  });
  Object.defineProperty(element, 'id', {
    get() { throw new Error('diagnostics must not read element IDs'); },
  });
  return element;
}

function append(parent, child) {
  child.parentElement = parent;
  parent.children.push(child);
  return child;
}

function descendants(parent) {
  return parent.children.flatMap((child) => [child, ...descendants(child)]);
}

function browserReport(bodyChildren, { width = 390, height = 844, documentWidth = 434, bodyWidth = 434 } = {}) {
  const body = node('body', '', { left: 0, right: width, top: 0, bottom: height, width, height }, { scrollWidth: bodyWidth, clientWidth: width });
  body.children = bodyChildren;
  for (const child of body.children) child.parentElement = body;
  body.querySelectorAll = () => descendants(body);
  const sandbox = {
    document: {
      documentElement: { clientWidth: width, scrollWidth: documentWidth },
      body,
    },
    window: { innerWidth: width, innerHeight: height },
    getComputedStyle: (element) => ({
      width: `${element.rect.width ?? (element.rect.right - element.rect.left)}px`,
      minWidth: '0px',
      maxWidth: 'none',
      overflowX: 'visible',
      whiteSpace: 'normal',
      boxSizing: 'border-box',
      display: 'block',
      position: 'static',
      flex: '0 1 auto',
      gridTemplateColumns: 'none',
      ...element.styles,
    }),
  };
  const report = runInNewContext(`(${collectHorizontalOverflowDiagnostics.toString()})()`, sandbox);
  return JSON.parse(JSON.stringify(report));
}

test('overflow report identifies bounded element geometry and parent sizing without private values', () => {
  const body = node('body', '', { left: 0, right: 390, top: 0, bottom: 844, width: 390, height: 844 });
  const main = append(body, node('main', 'review-shell', { left: 16, right: 374, top: 100, bottom: 800, width: 358, height: 700 }));
  const section = append(main, node('section', 'batch717-evidence', { left: 16, right: 374, top: 120, bottom: 780, width: 358, height: 660 }));
  append(section, node('a', 'batch717-citation source-link', { left: 16, right: 434, top: 200, bottom: 230, width: 418, height: 30 }, {
    styles: { minWidth: '418px', whiteSpace: 'nowrap', display: 'inline-block' },
  }));
  append(section, node('input', 'private-token-field', { left: 16, right: 300, top: 240, bottom: 280, width: 284, height: 40 }));

  const report = browserReport(body.children);
  assert.deepEqual(report.document, { clientWidth: 390, scrollWidth: 434 });
  assert.deepEqual(report.body, { clientWidth: 390, scrollWidth: 434 });
  assert.deepEqual(report.viewport, { width: 390, height: 844 });
  assert.ok(report.offenders.some((item) => item.selector.includes('a.batch717-citation.source-link')));
  const citation = report.offenders.find((item) => item.tag === 'a');
  assert.equal(citation.rect.right, 434);
  assert.equal(citation.sizing.minWidth, '418px');
  assert.equal(citation.sizing.whiteSpace, 'nowrap');
  assert.equal(citation.parents[0].selector.includes('section.batch717-evidence'), true);
  assert.ok(citation.parents[0].sizing.width);
  assert.ok(report.scannedElements >= 4);
  assert.ok(report.offenders.length <= 8);
  const serialized = JSON.stringify(report);
  assert.equal(serialized.includes('value'), false);
  assert.equal(serialized.includes('textContent'), false);
  assert.equal(serialized.includes('secret'), false);
  assert.ok(serialized.length < 24_000, `diagnostic output exceeded its bound: ${serialized.length}`);
});

test('overflow report bounds offender details and marks truncated DOM scans', () => {
  const rootNode = node('main', 'content', { left: 0, right: 390, top: 0, bottom: 800, width: 390, height: 800 });
  for (let index = 0; index < 20; index += 1) {
    append(rootNode, node('div', `overflow-item-${index}`, { left: 0, right: 430 + index, top: index * 2, bottom: index * 2 + 1, width: 430 + index, height: 1 }));
  }
  const report = browserReport([rootNode]);
  assert.equal(report.offenderCount, 20);
  assert.equal(report.offenders.length, 8);
  assert.ok(JSON.stringify(report).length < 24_000);

  const many = node('main', 'large-content', { left: 0, right: 390, top: 0, bottom: 800, width: 390, height: 800 });
  for (let index = 0; index < 2501; index += 1) {
    append(many, node('span', '', { left: 0, right: 100, top: 0, bottom: 1, width: 100, height: 1 }));
  }
  const truncated = browserReport([many]);
  assert.equal(truncated.scannedElements, 2500);
  assert.equal(truncated.scanTruncated, true);
  assert.equal(truncated.totalElements, 2502);
});

test('healthy-page checks defer detailed overflow scanning until measured overflow exists', () => {
  const fixture = fs.readFileSync(path.join(root, 'tests/browser/fixtures/test.cjs'), 'utf8');
  assert.match(fixture, /if \(measurements\.width > measurements\.viewport \+ 2\) \{\s+try \{\s+overflow = await page\.evaluate\(collectHorizontalOverflowDiagnostics\)/);
  assert.match(fixture, /detailedDiagnostics: 'unavailable'/,
    'a diagnostic failure must not replace the original overflow assertion with an unrelated failure');
});
