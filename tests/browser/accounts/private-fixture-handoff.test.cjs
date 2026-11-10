'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const test = require('node:test');
const { readPrivateAccountFixture } = require('./private-fixture-handoff.cjs');

function makeRoot(t) {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'boardprep-account-handoff-test-'));
  t.after(() => fs.rmSync(root, { recursive: true, force: true }));
  return root;
}

function fixtureBytes(root) {
  const storage = path.join(root, 'account-auth-runtime-storage');
  const sessions = path.join(root, 'account-auth-sessions');
  return { storage, sessions, bytes: JSON.stringify({ accountAuth: { storage, sessions } }) };
}

test('private fixture handoff accepts only a regular private file and isolated temporary paths', (t) => {
  const root = makeRoot(t);
  const file = path.join(root, 'evidence.private.json');
  const fixture = fixtureBytes(root);
  fs.writeFileSync(file, fixture.bytes, { mode: 0o600 });
  fs.chmodSync(file, 0o600);

  assert.deepEqual(readPrivateAccountFixture(file, root), {
    authStorage: fixture.storage,
    authSessionStorage: fixture.sessions,
  });
  assert.equal((fs.lstatSync(file).mode & 0o077), 0);
});

test('private fixture handoff fails with a sanitized missing-file diagnostic', (t) => {
  const root = makeRoot(t);
  assert.throws(
    () => readPrivateAccountFixture(path.join(root, 'missing.private.json'), root),
    (error) => error.code === 'ACCOUNT_FIXTURE_HANDOFF_MISSING'
      && !error.message.includes(root)
      && !/password|token|cookie/i.test(error.message),
  );
});

test('private fixture handoff rejects broad permissions, symlinks, malformed data, and unsafe paths', (t) => {
  const root = makeRoot(t);
  const file = path.join(root, 'evidence.private.json');
  const fixture = fixtureBytes(root);

  fs.writeFileSync(file, fixture.bytes, { mode: 0o644 });
  fs.chmodSync(file, 0o644);
  assert.throws(() => readPrivateAccountFixture(file, root), { code: 'ACCOUNT_FIXTURE_HANDOFF_UNSAFE' });

  fs.chmodSync(file, 0o600);
  const symlink = path.join(root, 'evidence-link.private.json');
  fs.symlinkSync(file, symlink);
  assert.throws(() => readPrivateAccountFixture(symlink, root), { code: 'ACCOUNT_FIXTURE_HANDOFF_UNSAFE' });

  fs.writeFileSync(file, '{invalid private contents}', { mode: 0o600 });
  assert.throws(() => readPrivateAccountFixture(file, root), { code: 'ACCOUNT_FIXTURE_HANDOFF_INVALID' });

  fs.writeFileSync(file, JSON.stringify({
    accountAuth: { storage: '/private/outside/storage', sessions: '/private/outside/account-auth-sessions' },
  }), { mode: 0o600 });
  assert.throws(() => readPrivateAccountFixture(file, root), { code: 'ACCOUNT_FIXTURE_PATHS_UNSAFE' });
});
