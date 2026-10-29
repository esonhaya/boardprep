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
const makeSelect = (id, value, options) => {
  const select = Object.assign(control(value), { id, options });
  Object.defineProperty(select, 'selectedOptions', {
    get() { return [this.options.find((option) => option.value === this.value)].filter(Boolean); },
  });
  return select;
};
const letLevel = makeSelect('quiz-let-level', '', [
  { value: '', dataset: { level: '' }, hidden: false, disabled: false },
  { value: 'ELEMENTARY', dataset: { level: '' }, hidden: false, disabled: false },
  { value: 'SECONDARY', dataset: { level: '' }, hidden: false, disabled: false },
]);
const letSpecialization = makeSelect('quiz-let-specialization', 'elementary-specialization', [
  { value: '', dataset: { level: '' }, hidden: false, disabled: false },
  { value: 'elementary-specialization', dataset: { level: 'ELEMENTARY' }, hidden: false, disabled: false },
  { value: 'secondary-specialization', dataset: { level: 'SECONDARY' }, hidden: false, disabled: false },
]);
const civilServiceLevel = makeSelect('quiz-cse-level', '', [
  { value: '', dataset: { level: '' }, hidden: false, disabled: false },
  { value: 'PROFESSIONAL', dataset: { level: '' }, hidden: false, disabled: false },
  { value: 'SUBPROFESSIONAL', dataset: { level: '' }, hidden: false, disabled: false },
]);
const variantGroup = (id, candidateVariant, fields) => ({
  id,
  dataset: { candidateVariant },
  hidden: true,
  querySelectorAll(selector) { return selector === 'select, input' ? fields : []; },
});
const letVariantGroup = variantGroup('quiz-let-variant-fields', 'let', [letLevel, letSpecialization]);
const cseVariantGroup = variantGroup('quiz-cse-variant-fields', 'cse', [civilServiceLevel]);
const adaptive = Object.assign(control(), { id: 'quiz-adaptive' });
const modeDescription = { textContent: '' };
const controls = new Map([
  ['quiz-exam', exam], ['quiz-subject', subject], ['quiz-mode', mode],
  ['exam-timer-fields', control()], ['quiz-count', count],
  ['quiz-timer-mode', timerMode], ['quiz-time-limit', timeLimit],
  ['quiz-let-level', letLevel], ['quiz-let-specialization', letSpecialization],
  ['quiz-cse-level', civilServiceLevel], ['quiz-adaptive', adaptive],
  ['quiz-mode-description', modeDescription],
  ['quiz-time-limit-field', control()], ['start-session-button', control()],
]);

global.document = {
  getElementById(id) { return controls.get(id) || null; },
  querySelectorAll(selector) {
    if (selector === '[data-exam-blueprint-preview]') return [cePreview, mePreview];
    if (selector === '[data-candidate-variant]') return [letVariantGroup, cseVariantGroup];
    return [];
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
assert.equal(letVariantGroup.hidden, true, 'LET variant controls should stay hidden on Mechanical Engineering');
assert.equal(letLevel.disabled, true, 'inactive board variants must not be submitted');
assert.equal(cseVariantGroup.hidden, true, 'CSE variant controls should stay hidden on Mechanical Engineering');
assert.equal(adaptive.disabled, true, 'adaptive selection must be disabled in exam simulation');
assert.match(modeDescription.textContent, /withholds answer feedback/);

exam.value = 'LET';
mode.value = 'practice';
listeners.get('quiz-exam:change')();
assert.equal(letVariantGroup.hidden, false, 'LET setup should expose LET variant controls');
assert.equal(letLevel.disabled, false, 'active LET variant controls must be submitted');
assert.equal(civilServiceLevel.required, false, 'inactive CSE level must not block LET setup');
letLevel.value = 'SECONDARY';
listeners.get('quiz-let-level:change')();
assert.equal(letSpecialization.value, '', 'changing to an incompatible LET level should clear the old specialization');
assert.equal(letSpecialization.required, true, 'Secondary LET setup must request a specialization');
assert.equal(letSpecialization.options[1].disabled, true, 'incompatible LET specialization must be unavailable');
assert.equal(letSpecialization.options[2].disabled, false, 'matching LET specialization must remain available');
assert.equal(adaptive.disabled, false, 'adaptive selection is available in practice');

exam.value = 'CSE';
listeners.get('quiz-exam:change')();
assert.equal(cseVariantGroup.hidden, false, 'CSE setup should expose Civil Service variant controls');
assert.equal(civilServiceLevel.required, true, 'CSE must require a supported candidate level');
assert.equal(letLevel.disabled, true, 'inactive LET controls must not be submitted on CSE');
assert.match(modeDescription.textContent, /Practice shows answer feedback/);

count.value = '5';
listeners.get('quiz-count:change')();
assert.deepEqual(mePreview.lines.map((line) => line.countNode.textContent), ['2', '2', '1']);
assert.deepEqual(mePreview.lines.map((line) => line.totalNode.textContent), ['5', '5', '5']);

console.log('[PASS] blueprint client updates board, focus scope, rounding, and ineligible subject fallback.');
