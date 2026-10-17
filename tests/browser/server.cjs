#!/usr/bin/env node

const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawn, spawnSync } = require('node:child_process');

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
fs.cpSync(path.join(applicationRoot, 'storage'), runtimeStorage, { recursive: true });
fs.mkdirSync(sessionStorage, { recursive: true });
fs.mkdirSync(developerSessionStorage, { recursive: true });
fs.mkdirSync(acceptanceStorage, { recursive: true, mode: 0o700 });

const port = String(process.env.BOARDPREP_BROWSER_PORT || '4173');
const developerPort = String(process.env.BOARDPREP_DEVELOPER_BROWSER_PORT || '4174');
console.log(`BROWSER_FIXTURE_URL=http://127.0.0.1:${port}`);
console.log(`BROWSER_DEVELOPER_FIXTURE_URL=http://127.0.0.1:${developerPort}`);
console.log('BROWSER_FIXTURE_ENV=isolated production learner and testing developer servers; storage and sessions are temporary; listeners are loopback-only.');
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
const phpProcesses = [php, developerPhp];

let shuttingDown = false;
function cleanup() {
  if (shuttingDown) return;
  shuttingDown = true;
  for (const phpChild of phpProcesses) if (!phpChild.killed) phpChild.kill('SIGTERM');
  fs.rmSync(temporaryRoot, { recursive: true, force: true });
}

process.on('SIGINT', () => {
  cleanup();
  process.exit(130);
});
process.on('SIGTERM', () => {
  cleanup();
  process.exit(143);
});
process.on('exit', cleanup);
for (const phpChild of phpProcesses) phpChild.on('exit', (code, signal) => {
  if (!shuttingDown) {
    cleanup();
    process.exit(code ?? (signal ? 1 : 0));
  }
});
