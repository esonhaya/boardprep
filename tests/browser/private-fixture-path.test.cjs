'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const test = require('node:test');
const { privateFixturePathFor } = require('./private-fixture-path.cjs');

test('private browser handoff is a per-run sidecar outside the uploadable evidence tree', () => {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'boardprep-private-handoff-contract-'));
  try {
    const evidenceA = path.join(root, 'run-a', 'evidence');
    const evidenceB = path.join(root, 'run-b', 'evidence');
    fs.mkdirSync(evidenceA, { recursive: true });
    fs.mkdirSync(evidenceB, { recursive: true });

    const privateA = privateFixturePathFor(evidenceA);
    const privateB = privateFixturePathFor(evidenceB);
    assert.equal(privateA, `${path.resolve(evidenceA)}.private.json`);
    assert.equal(path.relative(path.resolve(evidenceA), privateA).startsWith('..'), true);
    assert.notEqual(privateA, privateB, 'parallel acceptance runs need distinct private handoffs');
    assert.throws(() => privateFixturePathFor(''), /evidence directory is required/);
    assert.deepEqual(fs.readdirSync(evidenceA), [], 'the artifact directory must not contain private fixture metadata');
  } finally {
    fs.rmSync(root, { recursive: true, force: true });
  }
});
