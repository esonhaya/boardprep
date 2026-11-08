'use strict';

/**
 * Runs in the browser only after a page-level horizontal overflow is detected.
 * It deliberately records structure and computed sizing, never text, form
 * values, IDs, URLs, or arbitrary attributes that could contain private data.
 */
function collectHorizontalOverflowDiagnostics() {
  const root = document.documentElement;
  const body = document.body;
  const viewportWidth = root.clientWidth;
  const viewportHeight = window.innerHeight;
  const allElements = Array.from(body.querySelectorAll('*'));
  const scanLimit = 2500;
  const elements = allElements.slice(0, scanLimit);
  const allowedToken = /^[A-Za-z][A-Za-z0-9_-]{0,39}$/;
  const short = (value, maximum = 96) => String(value ?? '').slice(0, maximum);
  const number = (value) => Number.isFinite(value) ? Math.round(value) : 0;

  function classes(element) {
    const value = typeof element.className === 'string' ? element.className : '';
    return value.split(/\s+/).filter((token) => allowedToken.test(token)).slice(0, 3);
  }

  function selectorPath(element) {
    const parts = [];
    let current = element;
    while (current && current !== body && parts.length < 6) {
      const tag = String(current.tagName || 'unknown').toLowerCase();
      const classSuffix = classes(current).map((token) => `.${token}`).join('');
      const parent = current.parentElement;
      let position = '';
      if (parent?.children) {
        const siblings = Array.from(parent.children).filter((sibling) => sibling.tagName === current.tagName);
        if (siblings.length > 1) position = `:nth-of-type(${siblings.indexOf(current) + 1})`;
      }
      parts.unshift(`${tag}${classSuffix}${position}`);
      current = parent;
    }
    return short(parts.join(' > ') || 'body', 180);
  }

  function sizing(element) {
    const style = getComputedStyle(element);
    return {
      width: short(style.width),
      minWidth: short(style.minWidth),
      maxWidth: short(style.maxWidth),
      overflowX: short(style.overflowX),
      whiteSpace: short(style.whiteSpace),
      boxSizing: short(style.boxSizing),
      display: short(style.display),
      position: short(style.position),
      flex: short(style.flex),
      gridTemplateColumns: short(style.gridTemplateColumns, 120),
    };
  }

  function rectangle(element) {
    const rect = element.getBoundingClientRect();
    return {
      left: number(rect.left),
      right: number(rect.right),
      top: number(rect.top),
      bottom: number(rect.bottom),
      width: number(rect.width),
      height: number(rect.height),
    };
  }

  function parentSizing(element) {
    const parents = [];
    let current = element.parentElement;
    while (current && parents.length < 3) {
      parents.push({
        tag: String(current.tagName || 'unknown').toLowerCase(),
        classNames: classes(current),
        selector: selectorPath(current),
        rect: rectangle(current),
        sizing: sizing(current),
      });
      current = current.parentElement;
    }
    return parents;
  }

  const candidates = [];
  for (const element of elements) {
    const rect = rectangle(element);
    const style = getComputedStyle(element);
    const leftOverflow = Math.max(0, -rect.left);
    const rightOverflow = Math.max(0, rect.right - viewportWidth);
    const boxOverflow = Math.max(leftOverflow, rightOverflow);
    const internalOverflow = Math.max(0, element.scrollWidth - element.clientWidth);
    const scrollable = style.overflowX === 'auto' || style.overflowX === 'scroll';
    if (boxOverflow <= 2 && (internalOverflow <= 2 || scrollable)) continue;

    candidates.push({
      element,
      rect,
      overshoot: number(boxOverflow),
      scrollWidth: number(element.scrollWidth),
      clientWidth: number(element.clientWidth),
    });
  }

  candidates.sort((left, right) => right.overshoot - left.overshoot
    || (right.scrollWidth - right.clientWidth) - (left.scrollWidth - left.clientWidth));

  const offenders = candidates.slice(0, 8).map((candidate) => ({
    tag: String(candidate.element.tagName || 'unknown').toLowerCase(),
    classNames: classes(candidate.element),
    selector: selectorPath(candidate.element),
    rect: candidate.rect,
    overshoot: candidate.overshoot,
    scrollWidth: candidate.scrollWidth,
    clientWidth: candidate.clientWidth,
    sizing: sizing(candidate.element),
    parents: parentSizing(candidate.element),
  }));

  return {
    document: {
      clientWidth: number(root.clientWidth),
      scrollWidth: number(root.scrollWidth),
    },
    body: {
      clientWidth: number(body.clientWidth),
      scrollWidth: number(body.scrollWidth),
    },
    viewport: {
      width: number(window.innerWidth),
      height: number(viewportHeight),
    },
    scannedElements: elements.length,
    totalElements: allElements.length,
    scanTruncated: allElements.length > scanLimit,
    offenderCount: candidates.length,
    offenders,
  };
}

module.exports = { collectHorizontalOverflowDiagnostics };
