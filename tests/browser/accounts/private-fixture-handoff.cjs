'use strict';

const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

function fixtureFailure(code, message) {
  const error = new Error(message);
  error.code = code;
  return error;
}

function readPrivateAccountFixture(fixtureManifestPath, temporaryDirectory = os.tmpdir()) {
  if (!fs.existsSync(fixtureManifestPath)) {
    throw fixtureFailure('ACCOUNT_FIXTURE_HANDOFF_MISSING', 'The isolated account fixture handoff is missing.');
  }

  let fixtureStat;
  try {
    fixtureStat = fs.lstatSync(fixtureManifestPath);
  } catch {
    throw fixtureFailure('ACCOUNT_FIXTURE_HANDOFF_UNREADABLE', 'The isolated account fixture handoff could not be inspected.');
  }
  if (!fixtureStat.isFile() || fixtureStat.isSymbolicLink() || (fixtureStat.mode & 0o077) !== 0) {
    throw fixtureFailure('ACCOUNT_FIXTURE_HANDOFF_UNSAFE', 'The isolated account fixture handoff failed its private-file checks.');
  }

  let fixture;
  try {
    fixture = JSON.parse(fs.readFileSync(fixtureManifestPath, 'utf8'));
  } catch {
    throw fixtureFailure('ACCOUNT_FIXTURE_HANDOFF_INVALID', 'The isolated account fixture handoff is invalid.');
  }
  const authStorage = fixture?.accountAuth?.storage;
  const authSessionStorage = fixture?.accountAuth?.sessions;
  const temporaryRoot = path.resolve(temporaryDirectory) + path.sep;
  if (typeof authStorage !== 'string' || !path.isAbsolute(authStorage)
      || typeof authSessionStorage !== 'string' || !path.isAbsolute(authSessionStorage)
      || path.dirname(path.resolve(authStorage)) !== path.dirname(path.resolve(authSessionStorage))
      || path.basename(path.resolve(authSessionStorage)) !== 'account-auth-sessions'
      || !path.resolve(authSessionStorage).startsWith(temporaryRoot)) {
    throw fixtureFailure('ACCOUNT_FIXTURE_PATHS_UNSAFE', 'The isolated account fixture paths failed their safety checks.');
  }
  return { authStorage, authSessionStorage };
}

module.exports = { readPrivateAccountFixture };
