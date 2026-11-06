'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const test = require('node:test');

test('exam timer requests one server deadline check when a mobile tab returns after zero', () => {
  const callbacks = { document: new Map(), window: new Map() };
  let now = 0;
  let tick = null;
  let intervalMs = null;
  let deadlineChecks = 0;
  const timer = { dataset: { remainingSeconds: '5' } };
  const value = { textContent: '' };
  const announcement = { textContent: '' };
  const expiryForm = { requestSubmit() { deadlineChecks++; } };
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
  assert.equal(deadlineChecks, 0, 'hidden visibility does not fabricate an expiry confirmation');
  document.hidden = false;
  callbacks.window.get('pageshow')();
  assert.equal(value.textContent, '00:00');
  assert.equal(deadlineChecks, 1, 'the client asks the server to validate expiry once after the countdown reaches zero');
  assert.match(announcement.textContent, /Time is up/);
  assert.match(announcement.textContent, /checking the server deadline/i,
    'the browser must announce that the server is checking expiry before claiming the attempt is being submitted');
  assert.doesNotMatch(announcement.textContent, /submitting your exam simulation/i,
    'a client countdown alone must not claim that the server has finalized the attempt');
  callbacks.window.get('focus')();
  tick();
  assert.equal(deadlineChecks, 1, 'repeated focus and interval events cannot issue duplicate deadline checks');
});

test('exam timer accounts for response-to-script delay and device sleep', () => {
  const callbacks = { document: new Map(), window: new Map() };
  let performanceNow = 4500;
  let wallNow = 100000;
  let tick = null;
  let deadlineChecks = 0;
  const timer = { dataset: { remainingSeconds: '10' } };
  const value = { textContent: '' };
  const expiryForm = { requestSubmit() { deadlineChecks++; } };
  const document = {
    hidden: false,
    querySelector(selector) { return selector === '.exam-countdown[data-remaining-seconds]' ? timer : null; },
    getElementById(id) {
      return ({ 'exam-expiry-check': expiryForm, 'exam-countdown-value': value })[id] || null;
    },
    addEventListener(name, callback) { callbacks.document.set(name, callback); },
  };
  const window = {
    setInterval(callback) { tick = callback; return 1; },
    addEventListener(name, callback) { callbacks.window.set(name, callback); },
  };
  const source = fs.readFileSync(path.join(__dirname, '../../public/assets/js/exam-timer.js'), 'utf8');
  vm.runInNewContext(source, {
    document,
    window,
    performance: {
      now: () => performanceNow,
      getEntriesByType: (type) => type === 'navigation' ? [{ responseStart: 500 }] : [],
    },
    Date: { now: () => wallNow },
    Number,
    Math,
    String,
  });

  assert.equal(value.textContent, '00:06', 'four seconds between the response and script must not be added back to the exam');
  performanceNow += 1000;
  wallNow += 1000;
  tick();
  assert.equal(value.textContent, '00:05');

  wallNow += 5000;
  callbacks.window.get('pageshow')();
  assert.equal(value.textContent, '00:00', 'a sleeping mobile device must catch up from wall time when the page resumes');
  assert.equal(deadlineChecks, 1);
});
