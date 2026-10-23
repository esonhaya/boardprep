<?php

declare(strict_types=1);

// The question editor is intentionally unavailable in production. This test
// exercises its isolated developer/testing route, so make that environment
// explicit instead of inheriting the application's production default.
putenv('APP_ENV=testing');

require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';

use Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator;

$http = new HttpSimulator(dirname(__DIR__, 2) . '/public/index.php');
$cookies = ['PHPSESSID' => 'batch684-question-editor-' . bin2hex(random_bytes(8))];

$pages = [];
foreach ([1, 2] as $pageNumber) {
    $response = $http->request('GET', '/question-editor', ['page_num' => $pageNumber], [], [], $cookies);
    if (!$response['success'] || $response['status'] !== 200) {
        throw new RuntimeException('Question editor page ' . $pageNumber . ' did not return HTTP 200.');
    }

    $html = $response['output'];
    if (strlen($html) > 400_000) {
        throw new RuntimeException('Question editor page ' . $pageNumber . ' exceeded the 400 KB response budget.');
    }
    if (!preg_match('/Showing 50 of ([0-9]+) matches · ([0-9]+) canonical questions in repository · Page ' . $pageNumber . ' of ([0-9]+)/u', $html, $countMatch)) {
        throw new RuntimeException('Question editor page ' . $pageNumber . ' did not report the expected result count and page.');
    }
    $matchingCount = (int) $countMatch[1];
    $expectedPages = (int) ceil($matchingCount / 50);
    if ($matchingCount < 8962 || (int) $countMatch[2] < $matchingCount || (int) $countMatch[3] !== $expectedPages) {
        throw new RuntimeException('Question editor pagination does not match the current canonical inventory.');
    }
    if (preg_match_all('/<article class="card inventory-item">.*?<strong>\s*#([^<]+)<\/strong>/s', $html, $matches) !== 50) {
        throw new RuntimeException('Question editor page ' . $pageNumber . ' did not render exactly 50 question cards.');
    }
    if ($pageNumber === 1 && !str_contains($html, 'rel="next"')) {
        throw new RuntimeException('Question editor page one is missing its next-page link.');
    }
    if ($pageNumber === 2 && !str_contains($html, 'rel="prev"')) {
        throw new RuntimeException('Question editor page two is missing its previous-page link.');
    }

    $ids = array_map('trim', $matches[1]);
    if (count(array_unique($ids)) !== 50) {
        throw new RuntimeException('Question editor page ' . $pageNumber . ' contains duplicate question cards.');
    }
    $pages[$pageNumber] = $ids;
}

if (array_intersect($pages[1], $pages[2]) !== []) {
    throw new RuntimeException('Question editor pagination repeated questions on adjacent pages.');
}

$questionSnapshot = json_decode((string) file_get_contents($http->storagePath() . '/questions.json'), true, 512, JSON_THROW_ON_ERROR);
$canonicalCount = count(array_filter($questionSnapshot, 'is_array'));
$exactId = $http->request('GET', '/question-editor', ['search' => '6403'], [], [], $cookies);
if (!$exactId['success'] || $exactId['status'] !== 200
    || !str_contains($exactId['output'], '#6403')
    || !str_contains($exactId['output'], 'Showing 1 of 1 matches · ' . $canonicalCount . ' canonical questions in repository')) {
    throw new RuntimeException('Exact canonical ID search did not return one result while preserving the full repository count.');
}

$heldQuestion = $http->request('GET', '/question-inspector', ['id' => '6403'], [], [], $cookies);
if (!$heldQuestion['success'] || $heldQuestion['status'] !== 200
    || !str_contains($heldQuestion['output'], 'Question selection hold')
    || !str_contains($heldQuestion['output'], 'AB-0072.')) {
    throw new RuntimeException('Question inspector did not expose the Q6403 hold and conflicting exact-punctuation option.');
}

$sourceQuestion = $http->request('GET', '/question-inspector', ['id' => '361'], [], [], $cookies);
if (!$sourceQuestion['success'] || $sourceQuestion['status'] !== 200
    || !str_contains($sourceQuestion['output'], 'Source-only; no precise lesson section is asserted.')
    || !str_contains($sourceQuestion['output'], 'HTTP_REACHABLE')) {
    throw new RuntimeException('Question inspector did not expose source-only relationship and persisted URL reachability.');
}

echo "[PASS] Question editor renders distinct 50-item pages with bounded responses and working adjacent-page links.\n";
