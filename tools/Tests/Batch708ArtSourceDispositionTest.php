<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
App\Core\Autoloader::register();

use App\Core\App;
use App\Services\RepositoryHealth\SourceUrlHealthSnapshot;
use App\Services\Study\SourceRegistryService;
use App\Services\Study\StudyLibraryService;

App::boot();
$storage = App::storage();
$expectedIds = array_merge(range(361, 380), range(2449, 2454));
$expectedIds = array_map('strval', $expectedIds);
$links = array_values(array_filter($storage->all('study-question-links'), static fn(mixed $link): bool =>
    is_array($link) && ($link['material_id'] ?? '') === 'let-art-appreciation-review'
    && in_array((string) ($link['question_id'] ?? ''), $expectedIds, true)
));
if (count($links) !== 26 || count(array_unique(array_map(static fn(array $link): string => (string) $link['question_id'], $links))) !== 26) {
    throw new RuntimeException('Expected the 26 historical question-to-material links, without adding or duplicating relationships.');
}

$chapterFour = 'ung-intro-art-formal-analysis-chapter-4';
$chapterThree = 'ung-intro-art-materials-chapter-3';
$deped = 'deped-k12-arts-curriculum-guide-2016';
$sources = SourceRegistryService::all();
$validation = SourceRegistryService::validateSnapshot($sources);
if (($validation['valid'] ?? false) !== true) {
    throw new RuntimeException('Source registry validation failed after adding the bounded art references.');
}
$snapshot = $storage->all('source-url-health');
$audit = SourceUrlHealthSnapshot::inspect($snapshot, $sources);
if (($audit['status'] ?? '') !== 'CURRENT_COMPLETE') {
    throw new RuntimeException('Persisted source URL evidence is not a current complete snapshot.');
}
$statusById = [];
foreach ((array) ($snapshot[0]['records'] ?? []) as $record) {
    if (is_array($record)) $statusById[(string) ($record['source_id'] ?? '')] = (string) ($record['status'] ?? '');
}
foreach ([$chapterFour, $chapterThree, $deped] as $sourceId) {
    if (($statusById[$sourceId] ?? '') !== 'HTTP_REACHABLE') {
        throw new RuntimeException('New source lacks its bounded persisted HTTP reachability result: ' . $sourceId);
    }
}

$materials = array_values(array_filter($storage->all('study-materials'), static fn(mixed $material): bool =>
    is_array($material) && ($material['id'] ?? '') === 'let-art-appreciation-review'
));
if (count($materials) !== 1 || !in_array($deped, (array) ($materials[0]['source_ids'] ?? []), true)) {
    throw new RuntimeException('The material-level Philippine curriculum context source is missing.');
}

$materialChapterIds = ['363', '372', '376', '379', '2454'];
foreach ($links as $link) {
    $questionId = (string) $link['question_id'];
    $linkSources = array_map('strval', (array) ($link['source_ids'] ?? []));
    if (!in_array($chapterFour, $linkSources, true)) {
        throw new RuntimeException('Formal-analysis source is not attached to pre-existing source-only relationship for Q' . $questionId . '.');
    }
    if (in_array($questionId, $materialChapterIds, true) !== in_array($chapterThree, $linkSources, true)) {
        throw new RuntimeException('Material-significance source scope is incorrect for Q' . $questionId . '.');
    }
    if (isset($link['section_id']) || isset($link['lesson_section_id'])) {
        throw new RuntimeException('A new exact lesson-section mapping was introduced for Q' . $questionId . '.');
    }
    $references = StudyLibraryService::forQuestion($questionId);
    $matches = array_values(array_filter($references, static fn(array $reference): bool =>
        ($reference['material_id'] ?? '') === 'let-art-appreciation-review'
    ));
    if ($matches === [] || trim((string) ($matches[0]['section_id'] ?? '')) !== '') {
        throw new RuntimeException('Study navigation did not preserve the source-only relationship for Q' . $questionId . '.');
    }
    $resolved = array_map(static fn(array $reference): string => (string) ($reference['id'] ?? ''), (array) ($matches[0]['source_references'] ?? []));
    if (!in_array($chapterFour, $resolved, true)) {
        throw new RuntimeException('The learner-facing question reference does not resolve its accessible source for Q' . $questionId . '.');
    }
}

echo "[PASS] Twenty-six source-only LET art references resolve to accessible, scope-limited sources; no exact lesson mappings were created.\n";
