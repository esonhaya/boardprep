'use strict';

const path = require('node:path');

/** Keep the account-fixture handoff beside, never inside, uploadable evidence. */
function privateFixturePathFor(evidenceDirectory) {
  if (typeof evidenceDirectory !== 'string' || evidenceDirectory.trim() === '') {
    throw new TypeError('A browser evidence directory is required for the private fixture handoff.');
  }

  const evidencePath = path.resolve(evidenceDirectory);
  const privatePath = `${evidencePath}.private.json`;
  if (privatePath === evidencePath || privatePath.startsWith(evidencePath + path.sep)) {
    throw new Error('The private browser fixture handoff must remain outside the evidence directory.');
  }

  return privatePath;
}

module.exports = { privateFixturePathFor };
