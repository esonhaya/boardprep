'use strict';

// These tests deliberately run in only the project that matches their
// viewport or execution mode. A matching title alone is insufficient: the
// file, project, and explicit skip reason must all stay as reviewed.
const expectedProjectSkips = new Set([
  ['acceptance/boardprep.spec.cjs', 'developer question editor opens in isolated read-only browser workflow', 'mobile', 'The isolated developer editor workflow runs once at desktop width.'],
  ['acceptance/boardprep.spec.cjs', 'Acceptance Center exposes MVP-10 guidance and draft export from the dashboard', 'mobile', 'The separate nonproduction Acceptance Center runs once in the desktop project.'],
  ['acceptance/boardprep.spec.cjs', 'exam timer falls back to server validation when JavaScript is disabled', 'mobile', 'The no-JavaScript fallback runs once in the desktop project.'],
  ['acceptance/boardprep.spec.cjs', 'mobile board navigation opens its study resource and lesson', 'desktop', 'Mobile study-resource navigation is exercised at the phone viewport.'],
  ['acceptance/boardprep.spec.cjs', 'Acceptance Center dashboard and feedback export fit phone viewports', 'desktop', 'Acceptance Center mobile checks run once in the mobile project.'],
  ['acceptance/boardprep.spec.cjs', 'production browser flow reaches study, practice, results, history, and progress for all boards', 'desktop', 'The complete ten-board browser matrix runs once at a phone viewport.'],
  ['acceptance/boardprep.spec.cjs', 'mobile layouts remain in the viewport at supported narrow widths', 'desktop', 'Viewport sweep runs once in the mobile project.'],
  ['acceptance/developer-question-repository.spec.cjs', 'REPO-21 mobile repository controls and details fit a narrow viewport', 'desktop', 'The narrow viewport check runs in the mobile project.'],
  ['accounts/account-auth.spec.cjs', 'isolated mobile account journeys preserve authentication and learner ownership', 'desktop', 'Run the account journey once in supported Chromium mobile emulation; HTTP/service suites cover server-side transitions.'],
].map((parts) => parts.join('\u0000')));

function isExpectedProjectSkip(test) {
  const annotation = (Array.isArray(test.annotations) ? test.annotations : [])
    .find((item) => item?.type === 'skip');
  if (!annotation || typeof annotation.description !== 'string') return false;
  return expectedProjectSkips.has([
    test.file || '',
    test.title || '',
    test.projectName || '',
    annotation.description,
  ].join('\u0000'));
}

function classifySkippedTests(report) {
  const expected = [];
  const unexpected = [];
  function visit(suites) {
    for (const suite of Array.isArray(suites) ? suites : []) {
      for (const spec of Array.isArray(suite.specs) ? suite.specs : []) {
        for (const test of Array.isArray(spec.tests) ? spec.tests : []) {
          const results = Array.isArray(test.results) ? test.results : [];
          const skipped = test.status === 'skipped'
            || test.expectedStatus === 'skipped'
            || results.some((result) => result.status === 'skipped');
          if (!skipped) continue;
          const item = {
            file: spec.file || '',
            title: spec.title || '',
            projectName: test.projectName || '',
            annotations: Array.isArray(test.annotations) ? test.annotations : [],
          };
          (isExpectedProjectSkip(item) ? expected : unexpected).push(item);
        }
      }
      visit(suite.suites);
    }
  }
  visit(report?.suites);
  return { expected, unexpected };
}

module.exports = { classifySkippedTests, isExpectedProjectSkip };
