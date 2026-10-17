#!/usr/bin/env node
'use strict';

const fs = require('node:fs');
const http = require('node:http');
const net = require('node:net');
const os = require('node:os');
const path = require('node:path');
const { spawn, spawnSync } = require('node:child_process');

function argument(name) {
  const index = process.argv.indexOf(name);
  return index >= 0 ? process.argv[index + 1] : '';
}

const repositoryRoot = fs.realpathSync(path.resolve(argument('--repository-root') || path.join(__dirname, '../..')));
const buildSha = argument('--build-sha');
const configuredAcceptanceStorage = argument('--acceptance-storage');
if (!/^[a-f0-9]{40}$/.test(buildSha)) {
  process.stderr.write('A full committed build SHA is required.\n');
  process.exit(1);
}
if (!configuredAcceptanceStorage) {
  process.stderr.write('An absolute private feedback directory is required.\n');
  process.exit(1);
}
const acceptanceStorage = path.resolve(configuredAcceptanceStorage);
if (!path.isAbsolute(acceptanceStorage)) {
  process.stderr.write('An absolute private feedback directory is required.\n');
  process.exit(1);
}

function prospectiveRealPath(candidate) {
  let probe = candidate;
  const missing = [];
  while (!fs.existsSync(probe)) {
    const part = path.basename(probe);
    if (!part || part === '.' || part === '..') break;
    missing.unshift(part);
    const parent = path.dirname(probe);
    if (parent === probe) break;
    probe = parent;
  }
  const realParent = fs.realpathSync(probe);
  return path.resolve(realParent, ...missing);
}

function isInsideRepository(candidate) {
  return candidate === repositoryRoot || candidate.startsWith(`${repositoryRoot}${path.sep}`);
}

if (isInsideRepository(prospectiveRealPath(acceptanceStorage))) {
  process.stderr.write('Feedback storage must remain outside the repository.\n');
  process.exit(1);
}
fs.mkdirSync(acceptanceStorage, { recursive: true, mode: 0o700 });
fs.chmodSync(acceptanceStorage, 0o700);
const realStorage = fs.realpathSync(acceptanceStorage);
if (isInsideRepository(realStorage)) {
  process.stderr.write('Feedback storage must remain outside the repository.\n');
  process.exit(1);
}

const temporaryRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'boardprep-acceptance-'));
const archivePath = path.join(temporaryRoot, 'fixture.tar');
const archiveFile = fs.openSync(archivePath, 'w');
const archive = spawnSync('git', [
  '-C', repositoryRoot,
  'archive', '--format=tar', '--prefix=BoardPrepAcceptance/', buildSha,
], { stdio: ['ignore', archiveFile, 'pipe'], maxBuffer: 4 * 1024 * 1024 });
fs.closeSync(archiveFile);
if (archive.status !== 0) {
  process.stderr.write(archive.stderr?.toString() || 'Unable to create the committed application fixture.\n');
  fs.rmSync(temporaryRoot, { recursive: true, force: true });
  process.exit(1);
}
const extraction = spawnSync('tar', ['-xf', archivePath, '-C', temporaryRoot], { encoding: 'utf8' });
fs.unlinkSync(archivePath);
if (extraction.status !== 0) {
  process.stderr.write(extraction.stderr || 'Unable to extract the isolated application fixture.\n');
  fs.rmSync(temporaryRoot, { recursive: true, force: true });
  process.exit(1);
}

const fixtureRoot = path.join(temporaryRoot, 'BoardPrepAcceptance');
const applicationStorage = path.join(temporaryRoot, 'application-storage');
const learnerSessionStorage = path.join(temporaryRoot, 'learner-sessions');
const guideSessionStorage = path.join(temporaryRoot, 'guide-sessions');
fs.cpSync(path.join(fixtureRoot, 'storage'), applicationStorage, { recursive: true });
for (const directory of [learnerSessionStorage, guideSessionStorage]) {
  fs.mkdirSync(directory, { recursive: true, mode: 0o700 });
  fs.chmodSync(directory, 0o700);
}

function reservePort() {
  return new Promise((resolve, reject) => {
    const server = net.createServer();
    server.once('error', reject);
    server.listen(0, '127.0.0.1', () => {
      const { port } = server.address();
      server.close((error) => error ? reject(error) : resolve(port));
    });
  });
}

function waitForReady(port, child, requestPath, expectedBody = '') {
  return new Promise((resolve, reject) => {
    const started = Date.now();
    let settled = false;
    const probe = () => {
      if (settled) return;
      if (child.exitCode !== null) {
        settled = true;
        reject(new Error(`The isolated PHP server exited with ${child.exitCode}.`));
        return;
      }
      const request = http.get({ host: '127.0.0.1', port, path: requestPath, timeout: 1200 }, (response) => {
        let body = '';
        response.setEncoding('utf8');
        response.on('data', (chunk) => { body += chunk; });
        response.on('end', () => {
          if (response.statusCode === 200 && (!expectedBody || body.includes(expectedBody))) {
            settled = true;
            resolve();
          } else if (Date.now() - started > 15000) {
            settled = true;
            reject(new Error(`The isolated preview did not respond successfully at ${requestPath}.`));
          } else {
            setTimeout(probe, 200);
          }
        });
      });
      request.on('error', () => {
        if (Date.now() - started > 15000) {
          settled = true;
          reject(new Error('The isolated preview did not start on its loopback port.'));
        } else {
          setTimeout(probe, 200);
        }
      });
      request.on('timeout', () => request.destroy());
    };
    probe();
  });
}

function waitForPort(port, child) {
  return new Promise((resolve, reject) => {
    const started = Date.now();
    const probe = () => {
      if (child.exitCode !== null) {
        reject(new Error(`The isolated PHP server exited with ${child.exitCode}.`));
        return;
      }
      const socket = net.createConnection({ host: '127.0.0.1', port });
      socket.once('connect', () => {
        socket.destroy();
        resolve();
      });
      socket.once('error', () => {
        socket.destroy();
        if (Date.now() - started > 15000) {
          reject(new Error('The isolated learner preview did not open its loopback port.'));
        } else {
          setTimeout(probe, 200);
        }
      });
    };
    probe();
  });
}

let learnerPhp;
let guidePhp;
let learnerPort;
let guidePort;
let shuttingDown = false;

async function start() {
  for (let attempt = 0; attempt < 8; attempt += 1) {
    learnerPort = await reservePort();
    guidePort = await reservePort();
    if (guidePort === learnerPort) {
      guidePort = await reservePort();
    }
    learnerPhp = spawn('php', [
      '-d', `session.save_path=${learnerSessionStorage}`,
      '-S', `127.0.0.1:${learnerPort}`,
      '-t', path.join(fixtureRoot, 'public'),
      path.join(fixtureRoot, 'public', 'router.php'),
    ], {
      cwd: fixtureRoot,
      env: {
        ...process.env,
        APP_ENV: 'production',
        APP_DEBUG: 'false',
        APP_TIMEZONE: 'Asia/Manila',
        BUILD_COMMIT: buildSha,
        DB_DRIVER: 'json',
        APP_STORAGE_PATH: applicationStorage,
      },
      stdio: ['ignore', 'ignore', 'inherit'],
    });
    guidePhp = spawn('php', [
      '-d', 'session.name=BOARDPREP_GUIDE_SESSION',
      '-d', `session.save_path=${guideSessionStorage}`,
      '-S', `127.0.0.1:${guidePort}`,
      '-t', path.join(fixtureRoot, 'public'),
      path.join(fixtureRoot, 'public', 'router.php'),
    ], {
      cwd: fixtureRoot,
      env: {
        ...process.env,
        APP_ENV: 'testing',
        APP_DEBUG: 'false',
        APP_TIMEZONE: 'Asia/Manila',
        BUILD_COMMIT: buildSha,
        DB_DRIVER: 'json',
        APP_STORAGE_PATH: applicationStorage,
        BOARDPREP_ACCEPTANCE_STORAGE_PATH: realStorage,
        BOARDPREP_ACCEPTANCE_LEARNER_BASE_URL: `http://127.0.0.1:${learnerPort}`,
      },
      stdio: ['ignore', 'ignore', 'inherit'],
    });
    try {
      await waitForPort(learnerPort, learnerPhp);
      await waitForReady(guidePort, guidePhp, '/developer/acceptance/build', buildSha);
      break;
    } catch (error) {
      if (learnerPhp && learnerPhp.exitCode === null) learnerPhp.kill('SIGTERM');
      if (guidePhp && guidePhp.exitCode === null) guidePhp.kill('SIGTERM');
      if (attempt === 7) throw error;
    }
  }
  if (!learnerPort || !guidePort) throw new Error('Unable to reserve local preview ports.');
  console.log(`BUILD_SHA=${buildSha}`);
  console.log(`REPORT_EXPORT_LOCATION=${realStorage}`);
  console.log(`LEARNER_PREVIEW_URL=http://127.0.0.1:${learnerPort}/`);
  console.log(`OPEN_THIS_URL=http://127.0.0.1:${guidePort}/developer/acceptance`);
  console.log('BIND_ADDRESS=127.0.0.1');
  console.log('ENVIRONMENT=production-mode learner preview plus a separate nonproduction Acceptance Center; learner records and feedback use separate isolated storage.');
  const handleUnexpectedExit = (code, signal) => {
    if (!shuttingDown) {
      cleanup();
      process.exit(code ?? (signal ? 1 : 0));
    }
  };
  learnerPhp.once('exit', handleUnexpectedExit);
  guidePhp.once('exit', handleUnexpectedExit);
}

function cleanup() {
  if (shuttingDown) return;
  shuttingDown = true;
  if (learnerPhp && learnerPhp.exitCode === null) learnerPhp.kill('SIGTERM');
  if (guidePhp && guidePhp.exitCode === null) guidePhp.kill('SIGTERM');
  fs.rmSync(temporaryRoot, { recursive: true, force: true });
}

for (const signal of ['SIGINT', 'SIGTERM']) {
  process.on(signal, () => {
    cleanup();
    process.exit(signal === 'SIGINT' ? 130 : 143);
  });
}
process.on('exit', cleanup);

start().catch((error) => {
  process.stderr.write(`${error.message}\n`);
  cleanup();
  process.exit(1);
});
