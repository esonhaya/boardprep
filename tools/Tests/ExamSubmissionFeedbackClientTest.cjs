'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const test = require('node:test');

test('exam submit feedback is truthful and duplicate client submits are stopped', () => {
  let submitHandler = null;
  let pageShowHandler = null;
  const button = { disabled: false };
  const status = { hidden: true, textContent: '' };
  const form = {
    dataset: {},
    attributes: {},
    addEventListener(name, callback) {
      if (name === 'submit') submitHandler = callback;
    },
    querySelector(selector) { return selector === 'button[type="submit"]' ? button : null; },
    setAttribute(name, value) { this.attributes[name] = value; },
    removeAttribute(name) { delete this.attributes[name]; },
  };
  const document = {
    querySelectorAll(selector) { return selector === '[data-exam-submit-form]' ? [form] : []; },
    getElementById(id) { return id === 'exam-submit-status' ? status : null; },
  };
  const window = {
    addEventListener(name, callback) {
      if (name === 'pageshow') pageShowHandler = callback;
    },
  };
  const source = fs.readFileSync(path.join(__dirname, '../../public/assets/js/exam-submit.js'), 'utf8');
  vm.runInNewContext(source, { document, window });
  assert.equal(typeof submitHandler, 'function');

  let firstPrevented = false;
  submitHandler({ preventDefault() { firstPrevented = true; } });
  assert.equal(firstPrevented, false, 'the first submit must continue to the authoritative server');
  assert.equal(form.dataset.submitting, 'true');
  assert.equal(form.attributes['aria-busy'], 'true');
  assert.equal(button.disabled, true);
  assert.equal(status.hidden, false);
  assert.match(status.textContent, /Wait for the server to confirm your result/);
  assert.doesNotMatch(status.textContent, /completed|saved successfully/i);

  let duplicatePrevented = false;
  submitHandler({ preventDefault() { duplicatePrevented = true; } });
  assert.equal(duplicatePrevented, true, 'a second form submit must not issue a duplicate client request');

  pageShowHandler({ persisted: true });
  assert.equal(form.dataset.submitting, 'false', 'a back-forward-cache restore must unlock a safe retry');
  assert.equal(Object.hasOwn(form.attributes, 'aria-busy'), false);
  assert.equal(button.disabled, false);
  assert.equal(status.hidden, false);
  assert.match(status.textContent, /If you did not reach your result, submit again/);
  assert.match(status.textContent, /will not create a duplicate attempt/);
  assert.doesNotMatch(status.textContent, /submission (?:completed|succeeded)/i);
});
