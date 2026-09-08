function isKnownOptionalBrowserResponse(error) {
  let url;
  try {
    url = new URL(error.url);
  } catch {
    return false;
  }

  // Chromium may request this optional browser chrome asset even though the
  // application does not declare a favicon. Other 4xx/5xx responses fail.
  return error.status === 404
    && url.pathname === '/favicon.ico'
    && error.resourceType === 'image';
}

function matchesExpectedHttpError(error, expected) {
  return error.status === expected.status
    && error.url === expected.url
    && error.resourceType === expected.resourceType;
}

function matchesExpectedConsoleError(error, expected) {
  if (error.pageUrl !== (expected.pageUrl || expected.url)) return false;
  if (expected.status !== 404) return false;

  const message = expected.consoleMessage;
  if (message instanceof RegExp) return message.test(error.message);
  return typeof message === 'string' && error.message === message;
}

module.exports = {
  isKnownOptionalBrowserResponse,
  matchesExpectedHttpError,
  matchesExpectedConsoleError,
};
