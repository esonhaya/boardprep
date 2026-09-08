const assert = require('node:assert/strict');

const {
  isKnownOptionalBrowserResponse,
  matchesExpectedHttpError,
  matchesExpectedConsoleError,
} = require('./diagnostics.cjs');

const expected = {
  status: 404,
  url: 'http://127.0.0.1:4173/missing-browser-acceptance-route',
  pageUrl: 'http://127.0.0.1:4173/missing-browser-acceptance-route',
  resourceType: 'document',
  consoleMessage: /^Failed to load resource: the server responded with a status of 404\b/,
};

assert.equal(matchesExpectedHttpError({
  status: 404,
  url: expected.url,
  resourceType: 'document',
}, expected), true);
assert.equal(matchesExpectedConsoleError({
  message: 'Failed to load resource: the server responded with a status of 404 (Not Found)',
  pageUrl: expected.pageUrl,
}, expected), true);
assert.equal(matchesExpectedConsoleError({
  message: 'Failed to load resource: the server responded with a status of 404 (Not Found)',
  pageUrl: 'http://127.0.0.1:4173/other-page',
}, expected), false);
assert.equal(matchesExpectedConsoleError({
  message: 'Unexpected console error',
  pageUrl: expected.pageUrl,
}, expected), false);
assert.equal(matchesExpectedHttpError({
  status: 500,
  url: expected.url,
  resourceType: 'document',
}, expected), false);
assert.equal(isKnownOptionalBrowserResponse({
  status: 404,
  url: 'http://127.0.0.1:4173/favicon.ico',
  resourceType: 'image',
}), true);
assert.equal(isKnownOptionalBrowserResponse({
  status: 404,
  url: expected.url,
  resourceType: 'document',
}), false);

console.log('DIAGNOSTICS_FIXTURE_TEST=PASS');
