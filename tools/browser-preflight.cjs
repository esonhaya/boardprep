#!/usr/bin/env node
'use strict';

const fs = require('node:fs');
const http = require('node:http');
const os = require('node:os');
const path = require('node:path');
const { execFileSync } = require('node:child_process');

const root = path.resolve(__dirname, '..');
const lock = JSON.parse(fs.readFileSync(path.join(root, 'package-lock.json'), 'utf8'));
const expectedPlaywright = lock.packages?.['node_modules/@playwright/test']?.version || null;
const supportedPlatforms = ['linux', 'darwin', 'win32'];
const termuxRuntime = Boolean(process.env.PREFIX && process.env.PREFIX.includes('/com.termux/'));
const report = {
  schema_version: 1,
  checked_at: new Date().toISOString(),
  commit: (() => {
    try {
      return execFileSync('git', ['rev-parse', 'HEAD'], { cwd: root, encoding: 'utf8' }).trim();
    } catch {
      return null;
    }
  })(),
  tracked_worktree_clean: (() => {
    try {
      return execFileSync('git', ['status', '--porcelain', '--untracked-files=no'], {
        cwd: root,
        encoding: 'utf8',
      }).trim() === '';
    } catch {
      return false;
    }
  })(),
  environment: {
    platform: process.platform,
    release: os.release(),
    architecture: process.arch,
    node: process.version,
    termux: termuxRuntime,
  },
  pinned_playwright_version: expectedPlaywright,
  system_browser_candidates: [],
  checks: {},
};

function record(name, status, details = {}) {
  report.checks[name] = { status, ...details };
}

function executableOnPath(names) {
  const pathEntries = (process.env.PATH || '').split(path.delimiter).filter(Boolean);
  const found = [];
  for (const name of names) {
    for (const entry of pathEntries) {
      const candidate = path.join(entry, name);
      try {
        fs.accessSync(candidate, fs.constants.X_OK);
        found.push(candidate);
        break;
      } catch {}
    }
  }
  return found;
}

function loopbackProbe(url) {
  return new Promise((resolve, reject) => {
    const request = http.get(url, { timeout: 3000 }, (response) => {
      let body = '';
      response.setEncoding('utf8');
      response.on('data', (chunk) => { body += chunk; });
      response.on('end', () => {
        if (response.statusCode === 200 && body === 'boardprep-browser-preflight') resolve();
        else reject(new Error(`Loopback probe returned HTTP ${response.statusCode}.`));
      });
    });
    request.once('timeout', () => request.destroy(new Error('Loopback probe timed out.')));
    request.once('error', reject);
  });
}

async function closeServer(server) {
  if (!server.listening) return;
  server.closeAllConnections?.();
  await new Promise((resolve, reject) => {
    server.close((error) => error ? reject(error) : resolve());
  });
}

async function main() {
  const androidRuntime = process.platform === 'android';
  record('node_runtime', 'PASS', { platform: process.platform, architecture: process.arch });
  if (androidRuntime || termuxRuntime || !supportedPlatforms.includes(process.platform)) {
    record('playwright_driver_compatibility', 'BLOCKED', {
      reason: `The project browser runner requires a supported desktop host; this runtime reports ${process.platform}/${process.arch}${termuxRuntime ? ' under Termux' : ''}.`,
    });
  } else {
    record('playwright_driver_compatibility', 'PASS', { platform: process.platform });
  }

  let chromium = null;
  let installedVersion = null;
  let packageError = null;
  try {
    const packageJson = require.resolve('@playwright/test/package.json', { paths: [root] });
    installedVersion = JSON.parse(fs.readFileSync(packageJson, 'utf8')).version;
    chromium = require(require.resolve('@playwright/test', { paths: [root] })).chromium;
    record('playwright_package', installedVersion === expectedPlaywright ? 'PASS' : 'FAIL', {
      installed_version: installedVersion,
      expected_version: expectedPlaywright,
    });
  } catch (error) {
    packageError = String(error?.message || error).split('\n')[0];
    record('playwright_package', 'BLOCKED', {
      installed_version: null,
      expected_version: expectedPlaywright,
      reason: 'Run npm ci on a supported desktop host to install the lockfile-pinned test driver.',
      detail: packageError,
    });
  }

  let executable = null;
  if (chromium) {
    try {
      executable = chromium.executablePath();
      fs.accessSync(executable, fs.constants.X_OK);
      record('chromium_executable', 'PASS', { path: executable });
    } catch (error) {
      record('chromium_executable', 'BLOCKED', {
        path: executable,
        reason: 'The lockfile-matched Playwright Chromium executable is not installed or executable.',
        detail: String(error?.message || error).split('\n')[0],
      });
    }
  } else {
    record('chromium_executable', 'BLOCKED', { reason: 'The Playwright package is unavailable.' });
  }
  report.system_browser_candidates = executableOnPath([
    'chromium', 'chromium-browser', 'google-chrome', 'google-chrome-stable', 'firefox',
  ]);

  const server = http.createServer((_request, response) => {
    response.writeHead(200, { 'content-type': 'text/plain; charset=utf-8' });
    response.end('boardprep-browser-preflight');
  });
  let browser = null;
  let browserLaunched = false;
  try {
    await new Promise((resolve, reject) => {
      server.once('error', reject);
      server.listen(0, '127.0.0.1', resolve);
    });
    const address = server.address();
    const loopbackUrl = `http://127.0.0.1:${address.port}/`;
    try {
      await loopbackProbe(loopbackUrl);
      record('loopback_connectivity', 'PASS', { bind_address: '127.0.0.1' });
    } catch (error) {
      record('loopback_connectivity', 'FAIL', { reason: String(error?.message || error) });
    }

    if (report.checks.playwright_driver_compatibility.status !== 'PASS'
      || report.checks.playwright_package.status !== 'PASS'
      || report.checks.chromium_executable.status !== 'PASS') {
      record('browser_launch', 'BLOCKED', { reason: 'Platform, pinned driver, or executable precondition is unavailable.' });
      record('browser_loopback_navigation', 'BLOCKED', { reason: 'No compatible browser could be launched.' });
      record('browser_clean_shutdown', 'BLOCKED', { reason: 'No compatible browser process was launched.' });
    } else {
      try {
        browser = await chromium.launch({ headless: true, timeout: 15_000 });
        browserLaunched = true;
        record('browser_launch', 'PASS', { engine: 'chromium', version: browser.version() });
        const page = await browser.newPage();
        const response = await page.goto(loopbackUrl, { waitUntil: 'load', timeout: 10_000 });
        const body = await page.locator('body').innerText();
        if (response?.status() === 200 && body === 'boardprep-browser-preflight') {
          record('browser_loopback_navigation', 'PASS', { status: response.status() });
        } else {
          record('browser_loopback_navigation', 'FAIL', { status: response?.status() ?? null, body });
        }
      } catch (error) {
        record('browser_launch', 'FAIL', { reason: String(error?.message || error).split('\n')[0] });
        if (browserLaunched) record('browser_loopback_navigation', 'FAIL', { reason: 'Browser did not complete the loopback navigation.' });
        else record('browser_loopback_navigation', 'BLOCKED', { reason: 'Browser launch failed.' });
      } finally {
        if (browserLaunched) {
          try {
            await browser.close();
            record('browser_clean_shutdown', 'PASS');
          } catch (error) {
            record('browser_clean_shutdown', 'FAIL', { reason: String(error?.message || error).split('\n')[0] });
          }
        }
      }
    }
  } catch (error) {
    record('loopback_connectivity', 'FAIL', { reason: String(error?.message || error) });
    if (!report.checks.browser_launch) record('browser_launch', 'BLOCKED', { reason: 'Loopback fixture server failed to start.' });
  } finally {
    try {
      await closeServer(server);
      record('loopback_server_shutdown', 'PASS');
    } catch (error) {
      record('loopback_server_shutdown', 'FAIL', { reason: String(error?.message || error) });
    }
  }

  const statuses = Object.values(report.checks).map((check) => check.status);
  report.status = statuses.includes('FAIL') ? 'FAIL' : statuses.includes('BLOCKED') ? 'BLOCKED' : 'PASS';
  process.stdout.write(`${JSON.stringify(report, null, 2)}\n`);
  process.exitCode = report.status === 'PASS' ? 0 : report.status === 'BLOCKED' ? 2 : 1;
}

main().catch((error) => {
  process.stderr.write(`BROWSER_PREFLIGHT=FAIL ${String(error?.message || error)}\n`);
  process.exitCode = 1;
});
