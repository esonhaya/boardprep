'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const listeners = new Map();
const control = (value = '') => ({
  value,
  hidden: false,
  disabled: false,
  required: false,
  textContent: '',
  addEventListener(name, callback) {
    listeners.set(`${this.id || 'control'}:${name}`, callback);
  },
});

const exam = Object.assign(control('CE'), { id: 'quiz-exam' });
const mode = Object.assign(control('exam'), { id: 'quiz-mode' });
const count = Object.assign(control('20'), { id: 'quiz-count' });
const timerMode = Object.assign(control('untimed'), { id: 'quiz-timer-mode' });
const timeLimit = Object.assign(control('60'), { id: 'quiz-time-limit' });
const subject = Object.assign(control('*'), {
  id: 'quiz-subject',
  options: [
    { value: '*', dataset: { exam: 'all' }, hidden: false, disabled: false },
    { value: 'ce-subject', dataset: { exam: 'CE' }, hidden: false, disabled: false },
    { value: 'me-subject', dataset: { exam: 'ME' }, hidden: false, disabled: false },
  ],
});
Object.defineProperty(subject, 'selectedOptions', {
  get() { return [this.options.find((option) => option.value === this.value)].filter(Boolean); },
});

const makePreview = (board, weights) => {
  const lines = weights.map((weight) => {
    const countNode = { textContent: '' };
    const totalNode = { textContent: '' };
    return {
      dataset: { blueprintWeight: String(weight) },
      querySelector(selector) {
        if (selector === '[data-blueprint-count]') return countNode;
        if (selector === '[data-blueprint-total]') return totalNode;
        return null;
      },
      querySelectorAll() { return []; },
      countNode,
      totalNode,
    };
  });
  return {
    dataset: { board },
    hidden: true,
    querySelectorAll(selector) {
      return selector === '[data-blueprint-weight]' ? lines : [];
    },
    lines,
  };
};

const cePreview = makePreview('CE', [35, 35, 30]);
const mePreview = makePreview('ME', [35, 35, 30]);
const focusedNote = { hidden: true };
const controls = new Map([
  ['quiz-exam', exam], ['quiz-subject', subject], ['quiz-mode', mode],
  ['exam-timer-fields', control()], ['quiz-count', count],
  ['quiz-timer-mode', timerMode], ['quiz-time-limit', timeLimit],
  ['quiz-time-limit-field', control()], ['start-session-button', control()],
]);

global.document = {
  getElementById(id) { return controls.get(id) || null; },
  querySelectorAll(selector) {
    return selector === '[data-exam-blueprint-preview]' ? [cePreview, mePreview] : [];
  },
  querySelector(selector) {
    return selector === '[data-exam-blueprint-focused-note]' ? focusedNote : null;
  },
};

const source = fs.readFileSync(path.join(__dirname, '../../public/assets/js/quiz-settings.js'), 'utf8');
vm.runInThisContext(source, { filename: 'quiz-settings.js' });

assert.equal(cePreview.hidden, false, 'selected full-board simulation should show its board preview');
assert.equal(mePreview.hidden, true, 'other-board preview should remain hidden');
assert.deepEqual(cePreview.lines.map((line) => line.countNode.textContent), ['7', '7', '6']);

subject.value = 'ce-subject';
listeners.get('quiz-subject:change')();
assert.equal(cePreview.hidden, true, 'focused subject mode must not show full-board allocation');
assert.equal(focusedNote.hidden, false, 'focused subject mode should explain its scope');

exam.value = 'ME';
listeners.get('quiz-exam:change')();
assert.equal(subject.value, '*', 'changing board should clear a now-ineligible selected subject');
assert.equal(mePreview.hidden, false, 'a board change should show the new full-board preview');
assert.equal(focusedNote.hidden, true, 'full-board mode should hide the focused-subject note');

count.value = '5';
listeners.get('quiz-count:change')();
assert.deepEqual(mePreview.lines.map((line) => line.countNode.textContent), ['2', '2', '1']);
assert.deepEqual(mePreview.lines.map((line) => line.totalNode.textContent), ['5', '5', '5']);

console.log('[PASS] blueprint client updates board, focus scope, rounding, and ineligible subject fallback.');
