'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const test = require('node:test');

test('exam timer uses one-second rendering and catches up when a mobile tab returns', () => {
  const callbacks = { document: new Map(), window: new Map() };
  let now = 0;
  let tick = null;
  let intervalMs = null;
  let submissions = 0;
  const timer = { dataset: { remainingSeconds: '5' } };
  const value = { textContent: '' };
  const announcement = { textContent: '' };
  const expiryForm = { requestSubmit() { submissions++; } };
  const document = {
    hidden: false,
    querySelector(selector) { return selector === '.exam-countdown[data-remaining-seconds]' ? timer : null; },
    getElementById(id) {
      return ({ 'exam-expiry-check': expiryForm, 'exam-countdown-value': value, 'exam-countdown-announcement': announcement })[id] || null;
    },
    addEventListener(name, callback) { callbacks.document.set(name, callback); },
  };
  const window = {
    setInterval(callback, delay) { tick = callback; intervalMs = delay; return 1; },
    addEventListener(name, callback) { callbacks.window.set(name, callback); },
  };
  const source = fs.readFileSync(path.join(__dirname, '../../public/assets/js/exam-timer.js'), 'utf8');
  vm.runInNewContext(source, { document, window, performance: { now: () => now }, Number, Math, String });

  assert.equal(intervalMs, 1000, 'the displayed whole-second timer should not poll four times per second');
  assert.equal(value.textContent, '00:05');
  now = 1000;
  tick();
  assert.equal(value.textContent, '00:04');

  now = 2700;
  callbacks.document.get('visibilitychange')();
  assert.equal(value.textContent, '00:03', 'visibility return should immediately account for background time');
  now = 5500;
  document.hidden = true;
  callbacks.document.get('visibilitychange')();
  assert.equal(submissions, 0, 'hidden visibility does not fabricate an expiry confirmation');
  document.hidden = false;
  callbacks.window.get('pageshow')();
  assert.equal(value.textContent, '00:00');
  assert.equal(submissions, 1, 'expiry submits only once when the page resumes after the deadline');
  assert.match(announcement.textContent, /Time is up/);
  callbacks.window.get('focus')();
  tick();
  assert.equal(submissions, 1, 'repeated focus and interval events cannot submit twice');
});
