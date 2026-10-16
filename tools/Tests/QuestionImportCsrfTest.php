<?php

declare(strict_types=1);

// This test exercises a developer-only route. The application defaults to
// production when APP_ENV is unset, where the route must not be registered.
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');

require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';

$http = new \Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator(dirname(__DIR__, 2) . '/public/index.php');
$cookies = ['PHPSESSID' => 'batch676-question-import-' . bin2hex(random_bytes(8))];
$page = $http->request('GET', '/question-import', [], [], [], $cookies);
if (!$page['success'] || preg_match('/name="_csrf"\s+value="([^"]+)"/', $page['output'], $match) !== 1) {
    throw new RuntimeException('Question import form did not render a CSRF token.');
}
$token = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

$rejected = $http->request('POST', '/question-import/import', [], [], [], $cookies);
if ($rejected['status'] !== 403 || !str_contains($rejected['output'], 'Invalid CSRF token.')) {
    throw new RuntimeException('Question import accepted a cross-site request without a token.');
}

$validWithoutFile = $http->request('POST', '/question-import/import', [], ['_csrf' => $token], [], $cookies);
if ($validWithoutFile['status'] !== 302 || ($validWithoutFile['location'] ?? null) !== '/question-import') {
    throw new RuntimeException('Question import did not continue to its existing missing-file recovery with a valid token.');
}

echo "[PASS] Question import requires the rendered session CSRF token and preserves missing-file recovery.\n";
