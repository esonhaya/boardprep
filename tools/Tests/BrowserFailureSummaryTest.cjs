const assert = require('node:assert/strict');
const { summarize } = require('../summarize-browser-results.cjs');

const report = {
  suites: [{
    title: 'acceptance.spec.cjs',
    file: 'tests/browser/acceptance/boardprep.spec.cjs',
    suites: [{
      title: 'PDF acceptance',
      specs: [{
        title: 'opens the expected PDF',
        file: 'tests/browser/acceptance/boardprep.spec.cjs',
        line: 190,
        tests: [
          { projectName: 'desktop', expectedStatus: 'passed', outcome: 'expected', results: [{ status: 'passed' }] },
          { projectName: 'mobile', expectedStatus: 'passed', outcome: 'unexpected', results: [{ status: 'failed', error: { message: 'PDF identity mismatch' } }] },
          { projectName: 'other', expectedStatus: 'skipped', outcome: 'skipped', results: [] },
          { projectName: 'retry', expectedStatus: 'passed', outcome: 'flaky', results: [{ status: 'failed' }, { status: 'passed' }] },
        ],
      }],
    }],
  }],
};
const result = summarize(report);
assert.deepEqual({
  total: result.total,
  passed: result.passed,
  failed: result.failed,
  skipped: result.skipped,
  flaky: result.flaky,
  not_run: result.not_run,
}, { total: 4, passed: 2, failed: 1, skipped: 1, flaky: 1, not_run: 0 });
assert.equal(result.failures[0].title, 'acceptance.spec.cjs › PDF acceptance › opens the expected PDF › mobile');
assert.equal(result.failures[0].message, 'PDF identity mismatch');
console.log('[PASS] Browser failure summary retains bounded test identity, project, and assertion evidence.');
