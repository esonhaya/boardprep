#!/usr/bin/env node

const fs = require('node:fs');
const path = require('node:path');

const repositoryRoot = path.resolve(__dirname, '..');
const resultsRoot = path.join(repositoryRoot, 'test-results');

function listFiles(directory) {
  if (!fs.existsSync(directory)) return [];
  return fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const fullPath = path.join(directory, entry.name);
    return entry.isDirectory() ? listFiles(fullPath) : [fullPath];
  });
}

function locationPath(value) {
  try {
    return new URL(value).pathname;
  } catch {
    return '';
  }
}

function safeMessage(value) {
  return String(value || 'Unknown browser error')
    .replace(/https?:\/\/[^\s)]+/g, (url) => locationPath(url) || '[external URL]')
    .replace(/(token|secret|password|api[_-]?key|cookie|session)[=: ]+[^\s&]+/gi, '$1=[redacted]')
    .replace(/\b(gh[pousr]_[A-Za-z0-9]{20,}|github_pat_[A-Za-z0-9_]{20,})\b/g, '[redacted credential]');
}

function errorKey(error) {
  return [error.message, error.type, error.pageUrl, error.location?.url, error.location?.lineNumber].join('|');
}

fs.mkdirSync(resultsRoot, { recursive: true });
for (const category of ['mobile', 'tablet', 'desktop']) {
  fs.mkdirSync(path.join(repositoryRoot, 'screenshots', category), { recursive: true });
}

const resultFiles = listFiles(resultsRoot);
const diagnosticsFiles = resultFiles.filter((file) => path.basename(file) === 'browser-diagnostics.json');
const consoleErrors = [];

for (const file of diagnosticsFiles) {
  let report;
  try {
    report = JSON.parse(fs.readFileSync(file, 'utf8'));
  } catch {
    consoleErrors.push(`${path.relative(repositoryRoot, file)}: diagnostics JSON could not be parsed`);
    continue;
  }
  const allowed = new Set((report.allowedConsoleErrors || []).map(errorKey));
  for (const error of report.consoleErrors || []) {
    if (!allowed.has(errorKey(error))) {
      consoleErrors.push(`${path.relative(repositoryRoot, file)}: ${safeMessage(error.message)}`);
    }
  }
  for (const error of report.pageErrors || []) {
    consoleErrors.push(`${path.relative(repositoryRoot, file)}: page error: ${safeMessage(error.message)}`);
  }
  for (const error of report.failedRequests || []) {
    consoleErrors.push(`${path.relative(repositoryRoot, file)}: failed ${error.method || 'request'} ${locationPath(error.url) || '[URL omitted]'} (${safeMessage(error.error)})`);
  }
}

fs.writeFileSync(
  path.join(repositoryRoot, 'console-errors.txt'),
  `${consoleErrors.length ? consoleErrors.join('\n') : 'No unexpected browser console errors, uncaught page errors, or failed requests were recorded.'}\n`
);

const auditFiles = resultFiles.filter((file) => path.basename(file) === 'visual-audit.md');
const screenshotFiles = listFiles(path.join(repositoryRoot, 'screenshots'))
  .filter((file) => file.toLowerCase().endsWith('.png'))
  .map((file) => path.relative(repositoryRoot, file))
  .sort();
const auditSections = auditFiles.map((file) => (
  `## ${path.relative(repositoryRoot, file)}\n\n${fs.readFileSync(file, 'utf8').trim()}`
));
const runUrl = process.env.GITHUB_SERVER_URL && process.env.GITHUB_REPOSITORY && process.env.GITHUB_RUN_ID
  ? `${process.env.GITHUB_SERVER_URL}/${process.env.GITHUB_REPOSITORY}/actions/runs/${process.env.GITHUB_RUN_ID}`
  : 'Not running in GitHub Actions.';
const visualAudit = [
  '# BoardPrep Batch 667 visual audit',
  '',
  `- Workflow run: ${runUrl}`,
  `- Curated Chromium screenshots: ${screenshotFiles.length}`,
  '- Automated geometry checks use a 2px horizontal overflow tolerance; CSS/DOM checks are not visual-review substitutes.',
  '- The defect register records actual screenshot review and before/after evidence.',
  '',
  '## Screenshot files',
  '',
  ...(screenshotFiles.length ? screenshotFiles.map((file) => `- ${file}`) : ['- No curated screenshots were produced.']),
  '',
  '## Per-project geometry audits',
  '',
  ...(auditSections.length ? auditSections : ['No visual audit report was produced by the browser tests.']),
  '',
  '## Review limitations',
  '',
  'Chromium screenshots do not establish physical Android touch usability, device-specific rendering, screen-reader behavior, or WCAG conformance.',
  '',
].join('\n');
fs.writeFileSync(path.join(repositoryRoot, 'visual-audit.md'), visualAudit);

const defectSource = path.join(repositoryRoot, 'tests', 'browser', 'visual-defect-register.md');
if (fs.existsSync(defectSource)) {
  fs.copyFileSync(defectSource, path.join(repositoryRoot, 'defect-register.md'));
}

const browserResultsPath = path.join(resultsRoot, 'browser-results.json');
const browserResultsOutput = path.join(repositoryRoot, 'browser-results.json');
if (fs.existsSync(browserResultsPath)) {
  fs.copyFileSync(browserResultsPath, browserResultsOutput);
} else {
  fs.writeFileSync(browserResultsOutput, `${JSON.stringify({ status: 'NOT_PRODUCED', reason: 'Playwright JSON report missing.' }, null, 2)}\n`);
}

console.log(`BROWSER_ARTIFACTS screenshots=${screenshotFiles.length} diagnostics=${diagnosticsFiles.length} console_errors=${consoleErrors.length}`);
