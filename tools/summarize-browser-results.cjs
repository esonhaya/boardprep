#!/usr/bin/env node

const fs = require('node:fs');
const path = require('node:path');

function collectTests(report) {
  const tests = [];
  function visitSuite(suite, ancestors = []) {
    const title = typeof suite.title === 'string' ? suite.title : '';
    const scope = title ? [...ancestors, title] : ancestors;
    for (const spec of Array.isArray(suite.specs) ? suite.specs : []) {
      for (const test of Array.isArray(spec.tests) ? spec.tests : []) {
        tests.push({
          title: [...scope, spec.title, test.projectName].filter(Boolean).join(' › '),
          file: spec.file || '',
          line: Number(spec.line) || 1,
          outcome: test.outcome || '',
          ok: test.ok,
          expectedStatus: test.expectedStatus || 'passed',
          results: Array.isArray(test.results) ? test.results : [],
        });
      }
    }
    for (const child of Array.isArray(suite.suites) ? suite.suites : []) visitSuite(child, scope);
  }
  for (const suite of Array.isArray(report.suites) ? report.suites : []) visitSuite(suite);
  return tests;
}

function summarize(report) {
  const tests = collectTests(report);
  const summary = { total: tests.length, passed: 0, failed: 0, skipped: 0, flaky: 0, not_run: 0, failures: [] };
  for (const test of tests) {
    const statuses = test.results.map((result) => result.status || '');
    const finalStatus = statuses.at(-1) || '';
    if (test.expectedStatus === 'skipped' || test.outcome === 'skipped' || finalStatus === 'skipped') {
      summary.skipped++;
    } else if (test.expectedStatus === 'failed' && finalStatus === 'failed') {
      summary.passed++;
    } else if (test.outcome === 'unexpected' || test.ok === false || (test.results.length > 0 && finalStatus !== test.expectedStatus)) {
      summary.failed++;
      const failedResult = [...test.results].reverse().find((result) => result.status === 'failed');
      const error = failedResult?.error || failedResult?.errors?.[0] || {};
      summary.failures.push({ ...test, message: error.message || error.stack || 'Test finished with an unexpected status.' });
    } else if (finalStatus === test.expectedStatus || test.outcome === 'expected') {
      if (statuses.slice(0, -1).some((status) => status === 'failed' || status === 'timedOut' || status === 'interrupted')) summary.flaky++;
      summary.passed++;
    } else {
      summary.not_run++;
    }
  }
  return summary;
}

function cleanMessage(value, limit = 1200) {
  return String(value || '').replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F]/g, '').slice(0, limit);
}

function main() {
  const repositoryRoot = path.resolve(__dirname, '..');
  const reportPath = path.join(repositoryRoot, 'test-results', 'browser-results.json');
  if (!fs.existsSync(reportPath)) {
    const message = 'BROWSER_TEST_SUMMARY=NOT_AVAILABLE (Playwright JSON report was not produced).';
    console.log(message);
    if (process.env.GITHUB_STEP_SUMMARY) fs.appendFileSync(process.env.GITHUB_STEP_SUMMARY, `\n${message}\n`);
    return;
  }

  let summary;
  try {
    summary = summarize(JSON.parse(fs.readFileSync(reportPath, 'utf8')));
  } catch (error) {
    const message = `BROWSER_TEST_SUMMARY=INVALID_REPORT (${cleanMessage(error.message, 400)})`;
    console.log(message);
    if (process.env.GITHUB_STEP_SUMMARY) fs.appendFileSync(process.env.GITHUB_STEP_SUMMARY, `\n${message}\n`);
    return;
  }

  const status = summary.failed > 0 || summary.not_run > 0 ? 'FAILED' : 'COMPLETED';
  const lines = [
    '## Browser acceptance assertion summary',
    '',
    `BROWSER_TEST_SUMMARY=${status} TOTAL=${summary.total} PASSED=${summary.passed} FLAKY=${summary.flaky} FAILED=${summary.failed} SKIPPED=${summary.skipped} NOT_RUN=${summary.not_run}`,
  ];
  for (const failure of summary.failures.slice(0, 20)) {
    const message = cleanMessage(failure.message);
    lines.push('', `- **${cleanMessage(failure.title, 300)}** — \`${failure.file}:${failure.line}\``, '', '```text', message, '```');
    const annotationMessage = message.replace(/%/g, '%25').replace(/\r/g, '%0D').replace(/\n/g, '%0A');
    const annotationFile = String(failure.file).replace(/%/g, '%25').replace(/,/g, '%2C').replace(/:/g, '%3A');
    console.log(`::error file=${annotationFile},line=${failure.line},title=${encodeURIComponent(failure.title).replace(/%20/g, ' ')}::${annotationMessage}`);
  }
  if (summary.failures.length > 20) lines.push('', `Only the first 20 of ${summary.failures.length} failed test cases are included.`);
  const output = lines.join('\n') + '\n';
  console.log(output);
  if (process.env.GITHUB_STEP_SUMMARY) fs.appendFileSync(process.env.GITHUB_STEP_SUMMARY, `\n${output}`);
}

module.exports = { collectTests, summarize };

if (require.main === module) main();
