<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';
require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator;
use App\Services\Study\StudyLibraryService;

$root = dirname(__DIR__, 2);
$storage = sys_get_temp_dir() . '/boardprep-study-library-' . bin2hex(random_bytes(6));
if (!mkdir($storage, 0700, true)) {
    throw new RuntimeException('Unable to create isolated study library fixture.');
}

foreach (['study-materials.json', 'study-lessons.json', 'sources.json', 'study-question-links.json', 'exam-intelligence.json'] as $file) {
    if (!copy($root . '/storage/' . $file, $storage . '/' . $file)) {
        throw new RuntimeException('Unable to seed study library fixture.');
    }
}

$sessionStorage = $storage . '/sessions';
if (!mkdir($sessionStorage, 0700, true)) {
    throw new RuntimeException('Unable to create isolated study library session storage.');
}
putenv('APP_ENV=production');
putenv('APP_DEBUG=false');
putenv('APP_TIMEZONE=UTC');
putenv('DB_DRIVER=json');
putenv('APP_STORAGE_PATH=' . $storage);
putenv('APP_SESSION_PATH=' . $sessionStorage);

$sessionId = 'batch662-study-library-' . bin2hex(random_bytes(6));
$http = new HttpSimulator($root . '/public/index.php');
$boards = json_decode((string) file_get_contents($root . '/storage/boards.json'), true, 512, JSON_THROW_ON_ERROR);
$materials = json_decode((string) file_get_contents($root . '/storage/study-materials.json'), true, 512, JSON_THROW_ON_ERROR);
$sourceRegistry = json_decode((string) file_get_contents($root . '/storage/sources.json'), true, 512, JSON_THROW_ON_ERROR);
$questionLinks = json_decode((string) file_get_contents($root . '/storage/study-question-links.json'), true, 512, JSON_THROW_ON_ERROR);

try {
    $expectedSourceById = [];
    foreach ($sourceRegistry as $source) {
        if (is_array($source) && is_scalar($source['id'] ?? null)) {
            $sourceId = (string) $source['id'];
            if (!array_key_exists($sourceId, $expectedSourceById)) {
                $expectedSourceById[$sourceId] = $source;
            }
        }
    }

    $indexedMaterials = StudyLibraryService::all();
    $expectedPublished = array_values(array_filter(
        $materials,
        static fn(mixed $material): bool => is_array($material)
            && ($material['status'] ?? 'published') === 'published'
    ));
    if (count($indexedMaterials) !== count($expectedPublished)) {
        throw new RuntimeException('Indexed study library changed the published material count.');
    }

    $indexedById = [];
    foreach ($indexedMaterials as $indexedMaterial) {
        $indexedById[(string) ($indexedMaterial['id'] ?? '')] = $indexedMaterial;
    }
    foreach ($expectedPublished as $expectedMaterial) {
        $materialId = (string) ($expectedMaterial['id'] ?? '');
        $indexed = $indexedById[$materialId] ?? null;
        $expectedSources = array_values(array_filter(array_map(
            static fn(mixed $sourceId): ?array => is_scalar($sourceId)
                ? ($expectedSourceById[(string) $sourceId] ?? null)
                : null,
            $expectedMaterial['source_ids'] ?? []
        )));
        $expectedLinks = array_values(array_filter(
            $questionLinks,
            static fn(mixed $link): bool => is_array($link)
                && ($link['material_id'] ?? '') === ($expectedMaterial['id'] ?? '')
        ));
        if (!is_array($indexed)
            || ($indexed['sources'] ?? null) !== $expectedSources
            || ($indexed['question_links'] ?? null) !== $expectedLinks) {
            throw new RuntimeException('Indexed material source or question provenance changed for ' . $materialId . '.');
        }
    }
    $firstMaterialId = (string) ($expectedPublished[0]['id'] ?? '');
    if ($firstMaterialId === ''
        || StudyLibraryService::find($firstMaterialId) !== ($indexedById[$firstMaterialId] ?? null)) {
        throw new RuntimeException('Direct material lookup differs from the indexed library result.');
    }

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
            || !str_contains($response['output'], 'Search lesson titles, notes, and concepts')
            || !str_contains($response['output'], '<details class="study-material-disclosure">')
            || str_contains($response['output'], 'No study materials are available yet')) {
            throw new RuntimeException('Board-scoped study materials did not render for ' . $boardId);
        }
    }

    $letMaterial = null;
    foreach ($materials as $candidate) {
        if (($candidate['status'] ?? 'published') === 'published'
            && in_array('let', array_map('strtolower', array_map('strval', (array) ($candidate['exams'] ?? []))), true)) {
            $letMaterial = $candidate;
            break;
        }
    }
    if (!is_array($letMaterial)) {
        throw new RuntimeException('No LET material is available for search verification.');
    }
    $search = $http->request('GET', '/study-library', ['exam' => 'LET', 'q' => (string) $letMaterial['title']], [], [], ['PHPSESSID' => $sessionId]);
    if (!$search['success'] || $search['status'] !== 200
        || !str_contains($search['output'], 'Showing 1 study material matching')
        || !str_contains($search['output'], (string) $letMaterial['id'])) {
        throw new RuntimeException('Study-library search did not narrow results by a matching title.');
    }

    $emptySearch = $http->request('GET', '/study-library', ['exam' => 'LET', 'q' => 'no-resource-has-this-phrase-670'], [], [], ['PHPSESSID' => $sessionId]);
    if (!$emptySearch['success'] || $emptySearch['status'] !== 200
        || !str_contains($emptySearch['output'], 'No study materials match that search')
        || !str_contains($emptySearch['output'], 'Clear search')) {
        throw new RuntimeException('An empty study-library search did not provide a clear recovery action.');
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
        || !str_contains($detail['output'], '<details class="study-material-disclosure" open>')
        || substr_count($detail['output'], 'class="card study-material-card"') !== 1) {
        throw new RuntimeException('A direct study-material reference did not resolve to exactly its target.');
    }

    $positionalNotesMaterial = null;
    foreach ($materials as $candidate) {
        foreach ((array) ($candidate['notes'] ?? []) as $note) {
            if (is_array($note) && array_is_list($note) && count($note) >= 2
                && is_scalar($note[0]) && is_scalar($note[1])
                && trim((string) $note[0]) !== '' && trim((string) $note[1]) !== '') {
                $positionalNotesMaterial = $candidate;
                break 2;
            }
        }
    }
    if (!is_array($positionalNotesMaterial)) {
        throw new RuntimeException('No source-backed material with positional note content was available for rendering.');
    }

    $notesDetail = $http->request(
        'GET',
        '/study-library',
        ['material' => (string) $positionalNotesMaterial['id']],
        [],
        [],
        ['PHPSESSID' => $sessionId]
    );
    $notesStart = strpos($notesDetail['output'], '<div class="study-material-notes">');
    $notesEnd = $notesStart === false ? false : strpos($notesDetail['output'], '</div>', $notesStart);
    $notesMarkup = $notesStart === false || $notesEnd === false
        ? ''
        : substr($notesDetail['output'], $notesStart, $notesEnd - $notesStart);
    $expectedNoteCount = 0;
    foreach ($positionalNotesMaterial['notes'] as $note) {
        if (!is_array($note)) {
            continue;
        }
        $heading = trim((string) ($note['heading'] ?? $note[0] ?? ''));
        $body = trim((string) ($note['body'] ?? $note[1] ?? ''));
        if ($heading === '' && $body === '') {
            continue;
        }
        $expectedNoteCount++;
        if (($heading !== '' && !str_contains($notesMarkup, htmlspecialchars($heading, ENT_QUOTES, 'UTF-8')))
            || ($body !== '' && !str_contains($notesMarkup, htmlspecialchars($body, ENT_QUOTES, 'UTF-8'))) ) {
            throw new RuntimeException('A positional study note heading or body was missing from the rendered material.');
        }
    }
    if (!$notesDetail['success'] || $notesDetail['status'] !== 200
        || substr_count($notesMarkup, '<section>') !== $expectedNoteCount
        || str_contains($notesMarkup, '<h3></h3>')
        || str_contains($notesMarkup, '<p></p>')) {
        throw new RuntimeException('Study material note content rendered as empty sections or lost its source-first text.');
    }

    $cseLesson = StudyLibraryService::find('cse-numerical-foundation');
    if (!is_array($cseLesson) || !is_array($cseLesson['lesson'] ?? null)
        || ($cseLesson['lesson']['id'] ?? '') !== 'cse-percent-and-ratio-reasoning'
        || ($cseLesson['pdf_url'] ?? null) !== '/study-resources/cse-percent-and-ratio-reasoning.pdf') {
        throw new RuntimeException('A published lesson or its generated PDF was not joined to the source material.');
    }
    $cseDetail = $http->request('GET', '/study-library', ['material' => 'cse-numerical-foundation'], [], [], ['PHPSESSID' => $sessionId]);
    if (!$cseDetail['success'] || $cseDetail['status'] !== 200
        || !str_contains($cseDetail['output'], 'Percent and Ratio Reasoning')
        || !str_contains($cseDetail['output'], 'Official examination scope')
        || !str_contains($cseDetail['output'], 'Instructional references')
        || !str_contains($cseDetail['output'], 'Download searchable study PDF')
        || !str_contains($cseDetail['output'], 'Prealgebra 2e, Section 6.2')) {
        throw new RuntimeException('The direct lesson page omitted instruction, separated source roles, or the PDF download.');
    }
    $valuesLesson = StudyLibraryService::find('let-specialization-values-education');
    if (!is_array($valuesLesson)
        || ($valuesLesson['lesson']['id'] ?? '') !== 'let-values-ethical-reasoning-basics'
        || ($valuesLesson['pdf_url'] ?? '') !== '/study-resources/let-values-ethical-reasoning.pdf') {
        throw new RuntimeException('The focused Values Education lesson was not distinguished from its source record or printable material.');
    }
    $valuesPdfPath = $root . '/public/study-resources/let-values-ethical-reasoning.pdf';
    if (!is_file($valuesPdfPath) || !str_starts_with((string) file_get_contents($valuesPdfPath), '%PDF-')) {
        throw new RuntimeException('The original Values Education lesson PDF was not generated as a printable learning resource.');
    }
    $cseBrowse = $http->request('GET', '/study-library', ['exam' => 'civil-service'], [], [], ['PHPSESSID' => $sessionId]);
    if (!str_contains($cseBrowse['output'], 'Open complete lesson')
        || !str_contains($cseBrowse['output'], 'Download lesson material')
        || !str_contains($cseBrowse['output'], 'Open all 11 notes')) {
        throw new RuntimeException('Study-library discovery did not present a preview and direct path to the full material.');
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
    echo "[PASS] Indexed browsing preserves all published source and question-link relationships.\n";
    echo "[PASS] A direct material link resolves to one published source-backed resource.\n";
    echo "[PASS] Positional source-first study notes render their headings and bodies without blank sections.\n";
    echo "[PASS] Source references render as separate, readable list items.\n";
    echo "[PASS] Privacy disclosure and production Developer/Doctor route boundaries are safe.\n";
} finally {
    $sessionPath = rtrim($sessionStorage, DIRECTORY_SEPARATOR) . '/sess_' . $sessionId;
    @unlink($sessionPath);
    @rmdir($sessionStorage);
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
