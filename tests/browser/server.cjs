#!/usr/bin/env node

const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawn, spawnSync } = require('node:child_process');

const repositoryRoot = path.resolve(__dirname, '../..');
const temporaryRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'boardprep-browser-'));
const prefix = 'BoardPrepBrowser/';
const archive = spawnSync(
  'git',
  ['-C', repositoryRoot, 'archive', '--format=tar', `--prefix=${prefix}`, 'HEAD'],
  { encoding: null, maxBuffer: 64 * 1024 * 1024 }
);

if (archive.status !== 0) {
  process.stderr.write(archive.stderr?.toString() || 'Unable to export committed browser fixture.\n');
  process.exit(1);
}

const extraction = spawnSync('tar', ['-xf', '-', '-C', temporaryRoot], {
  input: archive.stdout,
  encoding: null,
});
if (extraction.status !== 0) {
  process.stderr.write(extraction.stderr?.toString() || 'Unable to extract browser fixture.\n');
  process.exit(1);
}

const applicationRoot = path.join(temporaryRoot, prefix.replace(/\/$/, ''));
const runtimeStorage = path.join(temporaryRoot, 'runtime-storage');
const sessionStorage = path.join(temporaryRoot, 'sessions');
fs.cpSync(path.join(applicationRoot, 'storage'), runtimeStorage, { recursive: true });
fs.mkdirSync(sessionStorage, { recursive: true });

const port = String(process.env.BOARDPREP_BROWSER_PORT || '4173');
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
      APP_ENV: 'testing',
      APP_DEBUG: 'false',
      APP_TIMEZONE: 'Asia/Manila',
      DB_DRIVER: 'json',
      APP_STORAGE_PATH: runtimeStorage,
    },
    stdio: 'inherit',
  }
);

let shuttingDown = false;
function cleanup() {
  if (shuttingDown) return;
  shuttingDown = true;
  if (!php.killed) php.kill('SIGTERM');
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
php.on('exit', (code, signal) => {
  if (!shuttingDown) {
    cleanup();
    process.exit(code ?? (signal ? 1 : 0));
  }
});
