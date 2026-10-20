'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const net = require('node:net');
const { spawn } = require('node:child_process');
const test = require('node:test');

const root = path.resolve(__dirname, '../..');

async function availableLoopbackPort() {
  const server = net.createServer();
  await new Promise((resolve, reject) => {
    server.once('error', reject);
    server.listen(0, '127.0.0.1', resolve);
  });
  const port = server.address().port;
  await new Promise((resolve, reject) => server.close((error) => error ? reject(error) : resolve()));
  return port;
}

async function waitForHealth(url, child, getErrors, timeoutMs = 30_000) {
  const deadline = Date.now() + timeoutMs;
  let lastError = null;
  while (Date.now() < deadline) {
    if (child.exitCode !== null || child.signalCode !== null) {
      throw new Error(`PHP fixture exited before becoming ready (${child.exitCode ?? child.signalCode}). ${getErrors()}`);
    }
    try {
      const response = await fetch(`${url}/health.php`, { signal: AbortSignal.timeout(1500) });
      if (response.status === 200) {
        const health = await response.json();
        if (health.status === 'PASS') return;
        lastError = new Error(`Health route returned ${JSON.stringify(health)}.`);
      } else {
        lastError = new Error(`Health route returned HTTP ${response.status}.`);
      }
    } catch (error) {
      lastError = error;
    }
    await new Promise((resolve) => setTimeout(resolve, 100));
  }
  throw new Error(`PHP fixture did not become healthy within ${timeoutMs}ms: ${lastError?.message || 'no response'}. ${getErrors()}`);
}

test('production-equivalent PHP fixture serves loopback routes and shuts down its children before cleanup', async () => {
  const fixtureParent = fs.mkdtempSync(path.join(os.tmpdir(), 'boardprep-browser-lifecycle-'));
  const ports = await Promise.all([availableLoopbackPort(), availableLoopbackPort(), availableLoopbackPort()]);
  const child = spawn(process.execPath, ['tests/browser/server.cjs'], {
    cwd: root,
    env: {
      ...process.env,
      BOARDPREP_BROWSER_PORT: String(ports[0]),
      BOARDPREP_DEVELOPER_BROWSER_PORT: String(ports[1]),
      BOARDPREP_AUTH_BROWSER_PORT: String(ports[2]),
      TMPDIR: fixtureParent,
    },
    stdio: ['ignore', 'pipe', 'pipe'],
  });
  let output = '';
  let errors = '';
  child.stdout.setEncoding('utf8').on('data', (chunk) => { output += chunk; });
  child.stderr.setEncoding('utf8').on('data', (chunk) => { errors += chunk; });
  const exitPromise = new Promise((resolve) => child.once('exit', (code, signal) => resolve({ code, signal })));
  try {
    const fixtureUrl = await new Promise((resolve, reject) => {
      const timeout = setTimeout(() => reject(new Error(`PHP server did not announce its loopback URL. ${errors}`)), 120_000);
      const inspectOutput = () => {
        const match = output.match(/BROWSER_FIXTURE_URL=(http:\/\/127\.0\.0\.1:\d+)/);
        if (match) {
          clearTimeout(timeout);
          resolve(match[1]);
        }
      };
      child.stdout.on('data', inspectOutput);
      child.once('error', (error) => { clearTimeout(timeout); reject(error); });
      child.once('exit', (code, signal) => {
        clearTimeout(timeout);
        reject(new Error(`PHP fixture exited before startup (${code ?? signal}). ${errors}`));
      });
      inspectOutput();
    });
    assert.match(fixtureUrl, /^http:\/\/127\.0\.0\.1:/, 'fixture must bind to loopback');

    await waitForHealth(fixtureUrl, child, () => errors);
    const healthResponse = await fetch(`${fixtureUrl}/health.php`, { signal: AbortSignal.timeout(10_000) });
    assert.equal(healthResponse.status, 200, 'production health route should be available');
    const health = await healthResponse.json();
    assert.equal(health.status, 'PASS');
    const flashcardResponse = await fetch(`${fixtureUrl}/flashcards?board=let&subject=english`, { signal: AbortSignal.timeout(30_000) });
    assert.equal(flashcardResponse.status, 200, 'scoped flashcard route should not return an HTTP error');
    assert.match(await flashcardResponse.text(), /<h1>Flashcards<\/h1>/);

    child.kill('SIGTERM');
    let shutdownDeadline;
    const exit = await Promise.race([
      exitPromise,
      new Promise((_, reject) => { shutdownDeadline = setTimeout(() => reject(new Error('PHP fixture did not finish graceful shutdown within 12 seconds.')), 12_000); }),
    ]);
    clearTimeout(shutdownDeadline);
    assert.equal(exit.code, 143, 'SIGTERM should remain visible as the server exit status');
    assert.deepEqual(fs.readdirSync(fixtureParent), [], 'server shutdown must remove its private fixture only after PHP children exit');
  } finally {
    if (child.exitCode === null && child.signalCode === null) {
      child.kill('SIGTERM');
      let cleanupDeadline;
      const exited = await Promise.race([
        exitPromise.then(() => true),
        new Promise((resolve) => { cleanupDeadline = setTimeout(() => resolve(false), 6_000); }),
      ]);
      clearTimeout(cleanupDeadline);
      if (!exited) {
        child.kill('SIGKILL');
        await exitPromise;
      }
    }
    fs.rmSync(fixtureParent, { recursive: true, force: true });
  }
});
