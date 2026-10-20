#!/usr/bin/env node

const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawn, spawnSync } = require('node:child_process');
const { createBrowserStorageFixture } = require('./storage-fixture.cjs');

const repositoryRoot = path.resolve(__dirname, '../..');
const temporaryRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'boardprep-browser-'));
const prefix = 'BoardPrepBrowser/';
const commitResult = spawnSync('git', ['-C', repositoryRoot, 'rev-parse', 'HEAD'], { encoding: 'utf8' });
const buildCommit = commitResult.stdout.trim();
if (commitResult.status !== 0 || !/^[0-9a-f]{40}$/.test(buildCommit)) {
  process.stderr.write('Unable to identify the committed browser fixture.\n');
  fs.rmSync(temporaryRoot, { recursive: true, force: true });
  process.exit(1);
}
const archivePath = path.join(temporaryRoot, 'fixture.tar');
const archiveFile = fs.openSync(archivePath, 'w');
let archive;
try {
  archive = spawnSync(
    'git',
    ['-C', repositoryRoot, 'archive', '--format=tar', `--prefix=${prefix}`, 'HEAD'],
    { stdio: ['ignore', archiveFile, 'pipe'], maxBuffer: 4 * 1024 * 1024 }
  );
} finally {
  fs.closeSync(archiveFile);
}

if (archive.status !== 0) {
  process.stderr.write(archive.stderr?.toString() || 'Unable to export committed browser fixture.\n');
  fs.rmSync(temporaryRoot, { recursive: true, force: true });
  process.exit(1);
}

const extraction = spawnSync('tar', ['-xf', archivePath, '-C', temporaryRoot], { encoding: 'utf8' });
fs.unlinkSync(archivePath);
if (extraction.status !== 0) {
  process.stderr.write(extraction.stderr?.toString() || 'Unable to extract browser fixture.\n');
  fs.rmSync(temporaryRoot, { recursive: true, force: true });
  process.exit(1);
}

const applicationRoot = path.join(temporaryRoot, prefix.replace(/\/$/, ''));
const runtimeStorage = path.join(temporaryRoot, 'runtime-storage');
const sessionStorage = path.join(temporaryRoot, 'sessions');
const developerSessionStorage = path.join(temporaryRoot, 'developer-sessions');
const acceptanceStorage = path.join(temporaryRoot, 'acceptance-feedback');
const authStorage = path.join(temporaryRoot, 'auth-runtime-storage');
const authSessionStorage = path.join(temporaryRoot, 'auth-sessions');
createBrowserStorageFixture(path.join(applicationRoot, 'storage'), runtimeStorage);
createBrowserStorageFixture(path.join(applicationRoot, 'storage'), authStorage);
fs.mkdirSync(sessionStorage, { recursive: true });
fs.mkdirSync(developerSessionStorage, { recursive: true });
fs.mkdirSync(acceptanceStorage, { recursive: true, mode: 0o700 });
fs.mkdirSync(authSessionStorage, { recursive: true, mode: 0o700 });
const evidenceDirectory = process.env.BOARDPREP_EXPLORER_EVIDENCE_DIR || process.env.BOARDPREP_BROWSER_EVIDENCE_DIR || '';
if (evidenceDirectory) {
  fs.mkdirSync(evidenceDirectory, { recursive: true, mode: 0o700 });
  fs.writeFileSync(path.join(evidenceDirectory, 'account-fixture-private.json'), JSON.stringify({ storage: authStorage }) + '\n', { mode: 0o600 });
}
const port = String(process.env.BOARDPREP_BROWSER_PORT || '4173');
const developerPort = String(process.env.BOARDPREP_DEVELOPER_BROWSER_PORT || '4174');
const authPort = String(process.env.BOARDPREP_AUTH_BROWSER_PORT || '4175');
console.log(`BROWSER_FIXTURE_URL=http://127.0.0.1:${port}`);
console.log(`BROWSER_DEVELOPER_FIXTURE_URL=http://127.0.0.1:${developerPort}`);
console.log('BROWSER_FIXTURE_ENV=isolated production learner, testing developer, and testing account servers; account storage and sessions are separate temporary fixtures; listeners are loopback-only.');
console.log('BROWSER_AUTH_FIXTURE_URL=http://127.0.0.1:' + authPort);
const php = spawn(
  'php',
  [
    '-d', `session.save_path=${sessionStorage}`,
    '-S', `127.0.0.1:${port}`,
    '-t', path.join(applicationRoot, 'public'),
    path.join(applicationRoot, 'public', 'router.php'),
  ],
  {
    cwd: applicationRoot,
    env: {
      ...process.env,
      APP_ENV: 'production',
      APP_DEBUG: 'false',
      APP_TIMEZONE: 'Asia/Manila',
      BUILD_COMMIT: buildCommit,
      DB_DRIVER: 'json',
      APP_STORAGE_PATH: runtimeStorage,
    },
    stdio: 'inherit',
  }
);
const developerPhp = spawn(
  'php',
  [
    '-d', 'session.name=BOARDPREP_GUIDE_SESSION',
    '-d', `session.save_path=${developerSessionStorage}`,
    '-S', `127.0.0.1:${developerPort}`,
    '-t', path.join(applicationRoot, 'public'),
    path.join(applicationRoot, 'public', 'router.php'),
  ],
  {
    cwd: applicationRoot,
    env: {
      ...process.env,
      APP_ENV: 'testing',
      APP_DEBUG: 'false',
      APP_TIMEZONE: 'Asia/Manila',
      BUILD_COMMIT: buildCommit,
      DB_DRIVER: 'json',
      APP_STORAGE_PATH: runtimeStorage,
      BOARDPREP_ACCEPTANCE_STORAGE_PATH: acceptanceStorage,
      BOARDPREP_ACCEPTANCE_LEARNER_BASE_URL: `http://127.0.0.1:${port}`,
    },
    stdio: 'inherit',
  }
);
const authPhp = spawn(
  'php',
  [
    '-d', 'session.name=BOARDPREP_AUTH_SESSION',
    '-d', 'session.save_path=' + authSessionStorage,
    '-S', '127.0.0.1:' + authPort,
    '-t', path.join(applicationRoot, 'public'),
    path.join(applicationRoot, 'public', 'router.php'),
  ],
  {
    cwd: applicationRoot,
    env: {
      ...process.env,
      APP_ENV: 'testing',
      APP_DEBUG: 'false',
      APP_TIMEZONE: 'Asia/Manila',
      BUILD_COMMIT: buildCommit,
      BOARDPREP_ACCOUNTS_ENABLED: 'true',
      DB_DRIVER: 'json',
      APP_STORAGE_PATH: authStorage,
      APP_SESSION_PATH: authSessionStorage,
    },
    stdio: 'inherit',
  }
);
const phpProcesses = [php, developerPhp, authPhp];

let shuttingDown = false;
let cleanupPromise = null;

function hasExited(child) {
  return child.exitCode !== null || child.signalCode !== null;
}

function exited(child) {
  if (hasExited(child)) return Promise.resolve();
  return new Promise((resolve) => child.once('exit', resolve));
}

async function stopPhpChildren() {
  let active = phpProcesses.filter((child) => !hasExited(child));
  if (!active.length) return;
  const gracefulExits = active.map(exited);
  for (const child of active) child.kill('SIGTERM');
  let deadline;
  await Promise.race([
    Promise.all(gracefulExits),
    new Promise((resolve) => { deadline = setTimeout(resolve, 3000); deadline.unref(); }),
  ]);
  clearTimeout(deadline);

  active = phpProcesses.filter((child) => !hasExited(child));
  if (!active.length) return;
  const forcedExits = active.map(exited);
  for (const child of active) child.kill('SIGKILL');
  let forcedDeadline;
  await Promise.race([
    Promise.all(forcedExits),
    new Promise((resolve) => { forcedDeadline = setTimeout(resolve, 1000); forcedDeadline.unref(); }),
  ]);
  clearTimeout(forcedDeadline);
}

function cleanup() {
  if (cleanupPromise) return cleanupPromise;
  shuttingDown = true;
  cleanupPromise = (async () => {
    await stopPhpChildren();
    fs.rmSync(temporaryRoot, { recursive: true, force: true });
  })();
  return cleanupPromise;
}

async function shutdown(exitCode) {
  await cleanup();
  process.exit(exitCode);
}

process.once('SIGINT', () => { void shutdown(130); });
process.once('SIGTERM', () => { void shutdown(143); });
process.on('exit', () => {
  // Synchronous fallback for a fatal parent-process exit. Normal shutdown
  // awaits child termination before this point.
  if (!cleanupPromise) {
    for (const child of phpProcesses) if (!hasExited(child)) child.kill('SIGTERM');
    fs.rmSync(temporaryRoot, { recursive: true, force: true });
  }
});
for (const phpChild of phpProcesses) phpChild.on('exit', (code, signal) => {
  if (!shuttingDown) void shutdown(code ?? (signal ? 1 : 0));
});
