'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { createBrowserStorageFixture, mutableLearnerFiles } = require('./storage-fixture.cjs');

const root = fs.mkdtempSync(path.join(os.tmpdir(), 'boardprep-browser-storage-fixture-'));
const source = path.join(root, 'source');
const fixture = path.join(root, 'fixture');
fs.mkdirSync(path.join(source, 'private', 'learner-accounts'), { recursive: true });

try {
  fs.writeFileSync(path.join(source, 'questions.json'), '{"canonical":"retained"}\n');
  fs.writeFileSync(path.join(source, 'attempts.json'), '[{"user_id":"synthetic-owner-marker"}]\n');
  fs.writeFileSync(path.join(source, 'weakness.json'), '[{"user_id":"synthetic-owner-marker"}]\n');
  fs.writeFileSync(path.join(source, 'flashcard_state.json'), '[{"user_id":"synthetic-owner-marker"}]\n');
  fs.writeFileSync(path.join(source, 'question-statistics.json'), '[{"synthetic-owner-marker":1}]\n');
  fs.writeFileSync(path.join(source, 'private', 'learner-accounts', 'accounts.json'), '{"synthetic_owner_marker":true}\n');

  createBrowserStorageFixture(source, fixture);
  assert.equal(fs.readFileSync(path.join(fixture, 'questions.json'), 'utf8'), '{"canonical":"retained"}\n');
  for (const file of mutableLearnerFiles) {
    assert.deepEqual(JSON.parse(fs.readFileSync(path.join(fixture, file), 'utf8')), [], `${file} was not isolated`);
    assert.equal(fs.statSync(path.join(fixture, file)).mode & 0o077, 0, `${file} is accessible to group/other`);
  }
  assert.equal(fs.existsSync(path.join(fixture, 'private')), false, 'private runtime storage was copied into the browser fixture');
  process.stdout.write('[PASS] Browser fixtures retain canonical question content and replace tracked learner collections with empty private temporary state.\n');
} finally {
  fs.rmSync(root, { recursive: true, force: true });
}
