<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';

use Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator;

$root = dirname(__DIR__, 2);
$storage = sys_get_temp_dir() . '/boardprep-study-library-' . bin2hex(random_bytes(6));
if (!mkdir($storage, 0700, true)) {
    throw new RuntimeException('Unable to create isolated study library fixture.');
}

foreach (['study-materials.json', 'sources.json', 'study-question-links.json', 'exam-intelligence.json'] as $file) {
    if (!copy($root . '/storage/' . $file, $storage . '/' . $file)) {
        throw new RuntimeException('Unable to seed study library fixture.');
    }
}

putenv('APP_ENV=production');
putenv('APP_DEBUG=false');
putenv('APP_TIMEZONE=UTC');
putenv('DB_DRIVER=json');
putenv('APP_STORAGE_PATH=' . $storage);

$sessionId = 'batch662-study-library-' . bin2hex(random_bytes(6));
$http = new HttpSimulator($root . '/public/index.php');
$boards = json_decode((string) file_get_contents($root . '/storage/boards.json'), true, 512, JSON_THROW_ON_ERROR);
$materials = json_decode((string) file_get_contents($root . '/storage/study-materials.json'), true, 512, JSON_THROW_ON_ERROR);
$sourceRegistry = json_decode((string) file_get_contents($root . '/storage/sources.json'), true, 512, JSON_THROW_ON_ERROR);

try {
    foreach ($boards as $board) {
        $boardId = (string) ($board['id'] ?? '');
        $expected = null;
        foreach ($materials as $material) {
            if (($material['status'] ?? 'published') === 'published'
                && in_array($boardId, array_map('strval', (array) ($material['exams'] ?? [])), true)) {
                $expected = $material;
                break;
            }
        }
        if ($boardId === '' || !is_array($expected)) {
            throw new RuntimeException('A supported board has no published study material: ' . $boardId);
        }

        $response = $http->request(
            'GET',
            '/study-library',
            ['exam' => $boardId],
            [],
            [],
            ['PHPSESSID' => $sessionId]
        );
        $expectedTitle = htmlspecialchars((string) ($expected['title'] ?? ''), ENT_QUOTES, 'UTF-8');
        if (!$response['success'] || $response['status'] !== 200
            || !str_contains($response['output'], 'Study Library · ' . strtoupper($boardId))
            || !str_contains($response['output'], $expectedTitle)
            || str_contains($response['output'], 'No study materials are available yet')) {
            throw new RuntimeException('Board-scoped study materials did not render for ' . $boardId);
        }
    }

    $material = null;
    foreach ($materials as $candidate) {
        if (($candidate['status'] ?? 'published') === 'published'
            && trim((string) ($candidate['id'] ?? '')) !== '') {
            $material = $candidate;
            break;
        }
    }
    if (!is_array($material)) {
        throw new RuntimeException('No published study material is available for direct-link verification.');
    }

    $detail = $http->request(
        'GET',
        '/study-library',
        ['material' => (string) $material['id']],
        [],
        [],
        ['PHPSESSID' => $sessionId]
    );
    $expectedTitle = htmlspecialchars((string) ($material['title'] ?? ''), ENT_QUOTES, 'UTF-8');
    if (!$detail['success'] || $detail['status'] !== 200
        || !str_contains($detail['output'], 'study-material-' . (string) $material['id'])
        || !str_contains($detail['output'], $expectedTitle)
        || substr_count($detail['output'], 'class="card study-material-card"') !== 1) {
        throw new RuntimeException('A direct study-material reference did not resolve to exactly its target.');
    }

    $sourceById = [];
    foreach ($sourceRegistry as $source) {
        if (is_array($source) && is_scalar($source['id'] ?? null)) {
            $sourceById[(string) $source['id']] = $source;
        }
    }
    $sources = array_values(array_filter(array_map(
        static fn(mixed $id): ?array => is_scalar($id) ? ($sourceById[(string) $id] ?? null) : null,
        (array) ($material['source_ids'] ?? [])
    )));
    $sourceStart = strpos($detail['output'], '<section class="study-material-sources"');
    $sourceEnd = $sourceStart === false ? false : strpos($detail['output'], '</section>', $sourceStart);
    $sourceMarkup = $sourceStart === false || $sourceEnd === false
        ? ''
        : substr($detail['output'], $sourceStart, $sourceEnd - $sourceStart);
    if ($sources === [] || substr_count($sourceMarkup, '<li>') !== count($sources)) {
        throw new RuntimeException('Study material sources did not render as separate, readable list items.');
    }
    foreach ($sources as $source) {
        $title = htmlspecialchars((string) ($source['title'] ?? 'Verified reference'), ENT_QUOTES, 'UTF-8');
        if (!str_contains($sourceMarkup, '>' . $title . '</a>')) {
            throw new RuntimeException('A study material source title is missing from its separated list item.');
        }
    }

    $privacy = $http->request('GET', '/privacy', [], [], [], ['PHPSESSID' => $sessionId]);
    if ($privacy['status'] !== 200
        || !str_contains($privacy['output'], 'No learner accounts')
        || !str_contains($privacy['output'], 'Download my saved learning data')
        || !str_contains($privacy['output'], 'Clear my learning data')
        || !str_contains($privacy['output'], 'Public launch must wait')
        || !str_contains($privacy['output'], 'BoardPrep v1.0.1')
        || str_contains($privacy['output'], $root)) {
        throw new RuntimeException('Production data disclosure or release identity did not render safely.');
    }

    foreach (['/developer', '/developer/doctor', '/question-editor'] as $privateRoute) {
        $response = $http->request('GET', $privateRoute, [], [], [], ['PHPSESSID' => $sessionId]);
        if ($response['status'] !== 404 || str_contains($response['output'], $root)) {
            throw new RuntimeException('A non-production tool route leaked into production: ' . $privateRoute);
        }
    }

    $doctorApi = new HttpSimulator($root . '/public/api/doctor.php');
    $doctorResponse = $doctorApi->request('GET', '/api/doctor.php', [], [], [], ['PHPSESSID' => $sessionId]);
    if ($doctorResponse['status'] !== 404 || str_contains($doctorResponse['output'], $root)) {
        throw new RuntimeException('The production Doctor API was exposed or disclosed internals.');
    }

    echo "[PASS] Production study library renders correctly scoped materials for all ten boards.\n";
    echo "[PASS] A direct material link resolves to one published source-backed resource.\n";
    echo "[PASS] Source references render as separate, readable list items.\n";
    echo "[PASS] Privacy disclosure and production Developer/Doctor route boundaries are safe.\n";
} finally {
    $sessionPath = $root . '/storage/doctor/simulation-sessions/sess-' . $sessionId;
    @unlink($sessionPath);
    foreach (glob($storage . '/*') ?: [] as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
    @rmdir($storage);
    putenv('APP_ENV');
    putenv('APP_DEBUG');
    putenv('APP_TIMEZONE');
    putenv('DB_DRIVER');
    putenv('APP_STORAGE_PATH');
}
