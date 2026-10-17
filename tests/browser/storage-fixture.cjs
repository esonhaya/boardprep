'use strict';

const fs = require('node:fs');
const path = require('node:path');

const mutableLearnerFiles = [
  'attempts.json',
  'weakness.json',
  'flashcard_state.json',
  'question-statistics.json',
];

/** Copy canonical content while replacing every mutable learner collection with an empty fixture. */
function createBrowserStorageFixture(sourceDirectory, destinationDirectory) {
  fs.cpSync(sourceDirectory, destinationDirectory, { recursive: true });
  for (const name of mutableLearnerFiles) {
    const target = path.join(destinationDirectory, name);
    fs.mkdirSync(path.dirname(target), { recursive: true });
    fs.writeFileSync(target, '[]\n', { mode: 0o600 });
    fs.chmodSync(target, 0o600);
  }
  // Account credentials, local tester notes, and other runtime-only private
  // data never belong in browser fixture copies.
  fs.rmSync(path.join(destinationDirectory, 'private'), { recursive: true, force: true });
}

module.exports = { createBrowserStorageFixture, mutableLearnerFiles };
