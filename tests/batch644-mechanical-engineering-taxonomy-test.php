<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';

use App\Core\App;
use App\Core\Autoloader;
use App\Services\Flashcard\FlashcardDerivationService;
use App\Services\RepositoryHealth\Engine\RepositoryContextFactory;
use App\Services\RepositoryHealth\Readiness\SourceReadinessService;
use App\Services\Shared\QuestionValidationService;

Autoloader::register();
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
App::boot();

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$collections = [];
foreach (RepositoryContextFactory::collectionNames() as $name) {
    $collections[$name] = App::storage()->all($name);
}
$questions = array_values(array_filter(
    $collections['questions'],
    static fn(array $question): bool => ($question['taxonomy']['board_id'] ?? '') === 'mechanical-engineering'
        && strtolower((string) ($question['status'] ?? 'active')) !== 'archived'
));
$assert(count($questions) === 464, 'Native ME canonical count changed.');

$counts = SourceReadinessService::nativeLeafCounts($collections, 'mechanical-engineering');
$readiness = SourceReadinessService::validateCollections($collections);
$meReadiness = $readiness['by_board']['mechanical-engineering'] ?? [];
$assert(count($counts) === 35, 'ME active granular leaf count is not 35.');
$assert(($meReadiness['valid'] ?? false) === true, 'Permanent ME readiness validation failed.');
$assert(count($meReadiness['records'] ?? []) === 35, 'ME does not have exactly one readiness record per active leaf.');
$assert(array_key_exists('b644-me-engineering-drawing-practice', $counts) && $counts['b644-me-engineering-drawing-practice'] === 0, 'Official drawing-practice scope gap is not visible as a zero leaf.');
$assert(array_key_exists('b644-me-pressure-components', $counts) && $counts['b644-me-pressure-components'] === 0, 'Pressure-component scope/evidence gap is not visible as a zero leaf.');

$concepts = [];
foreach ($collections['taxonomy/concepts'] as $concept) {
    $concepts[(string) ($concept['id'] ?? '')] = $concept;
}
$topics = [];
foreach ($collections['taxonomy/topics'] as $topic) {
    $topics[(string) ($topic['id'] ?? '')] = $topic;
}
$domains = [];
foreach ($collections['taxonomy/domains'] as $domain) {
    $domains[(string) ($domain['id'] ?? '')] = $domain;
}
$meBlueprints = array_values(array_filter(
    $collections['blueprints'],
    static fn(array $blueprint): bool => ($blueprint['id'] ?? '') === 'portfolio-mechanical-engineering-v1'
        && ($blueprint['status'] ?? '') === 'active'
));
$assert(count($meBlueprints) === 1, 'Exactly one active current ME blueprint must be registered.');
$expectedAreaDomains = [
    'mathematics' => 'b644-me-domain-mathematics',
    'power-plant-engineering' => 'b644-me-domain-power-plant-engineering',
    'machine-design-materials-shop-practice' => 'b644-me-domain-machine-design-materials-shop-practice',
];
$blueprintSections = [];
foreach ((array) ($meBlueprints[0]['sections'] ?? []) as $section) {
    $blueprintSections[(string) ($section['id'] ?? '')] = $section;
}
$assert(count($blueprintSections) === 3, 'The ME blueprint must retain all three PRC examination areas.');
foreach ($expectedAreaDomains as $areaId => $domainId) {
    $assert(($blueprintSections[$areaId]['domain_id'] ?? '') === $domainId, 'ME official-area health mapping is broken: ' . $areaId);
    $assert(isset($domains[$domainId]), 'ME official-area domain does not resolve: ' . $domainId);
}
$subjects = [];
foreach ($collections['subjects'] as $subject) {
    $subjects[(string) ($subject['id'] ?? '')] = $subject;
}
$questionIds = [];
$linkByQuestion = [];
foreach ($collections['study-question-links'] as $link) {
    $linkByQuestion[(string) ($link['question_id'] ?? '')][] = $link;
}
$materials = [];
foreach ($collections['study-materials'] as $material) {
    $materials[(string) ($material['id'] ?? '')] = $material;
}
$sources = [];
foreach ($collections['sources'] as $source) {
    $sources[(string) ($source['id'] ?? '')] = $source;
}
$flashcards = new FlashcardDerivationService(
    null,
    $collections['study-materials'],
    $collections['study-question-links'],
    $collections['flashcard-exclusions']
);
$remapMetadataCount = 0;
$newConcepts = 0;
$structuralNodes = 0;
foreach ($concepts as $id => $concept) {
    if (($concept['boardprep_taxonomy']['batch'] ?? null) === 644) {
        $assert(($concept['boardprep_taxonomy']['board_id'] ?? '') === 'mechanical-engineering', 'Batch644 concept lacks its ME scope marker.');
        if (str_starts_with($id, 'b644-me-')) {
            $newConcepts++;
        } else {
            $assert(($concept['status'] ?? '') === 'structural', 'A legacy parent was not retained as structural.');
            $structuralNodes++;
        }
    }
}
$assert($newConcepts === 35, 'Expected exactly 35 new ME-specific concepts.');

foreach ($questions as $question) {
    $id = (string) $question['id'];
    $assert(!isset($questionIds[$id]), 'Duplicate native ME question ID: ' . $id);
    $questionIds[$id] = true;
    $taxonomy = $question['taxonomy'];
    $subject = (string) ($taxonomy['subject_id'] ?? '');
    $domain = (string) ($taxonomy['domain_id'] ?? '');
    $topic = (string) ($taxonomy['topic_id'] ?? '');
    $concept = (string) ($taxonomy['concept_id'] ?? '');
    $assert(isset($subjects[$subject], $domains[$domain], $topics[$topic], $concepts[$concept]), 'Question taxonomy path has a dangling reference: ' . $id);
    $assert(($domains[$domain]['subject_id'] ?? '') === $subject, 'Question domain/subject path mismatch: ' . $id);
    $assert(($topics[$topic]['domain_id'] ?? '') === $domain, 'Question topic/domain path mismatch: ' . $id);
    $assert(($concepts[$concept]['topic_id'] ?? '') === $topic, 'Question concept/topic path mismatch: ' . $id);
    $assert(($concepts[$concept]['status'] ?? '') === 'active', 'Question maps to a non-active target leaf: ' . $id);
    $assert(QuestionValidationService::validate($question)['valid'], 'Question validation failed after taxonomy remap: ' . $id);

    $migration = (array) ($taxonomy['batch644_migration'] ?? []);
    $currentFingerprint = hash('sha256', json_encode(
        array_diff_key($question, ['taxonomy' => true, 'updatedAt' => true]),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ));
    $assert(($migration['original_content_fingerprint'] ?? '') === $currentFingerprint, 'Question body or non-taxonomy fields changed: ' . $id);
    $assert(($migration['new_leaf'] ?? '') === $concept, 'Migration evidence differs from current leaf: ' . $id);
    $remapMetadataCount++;

    $links = $linkByQuestion[$id] ?? [];
    $assert(count($links) === 1, 'Question must retain exactly one study trace link: ' . $id);
    foreach ($links as $link) {
        $materialId = (string) ($link['material_id'] ?? '');
        $assert(isset($materials[$materialId]), 'Question study material does not resolve: ' . $id);
        $sourceIds = array_values(array_map('strval', (array) ($link['source_ids'] ?? [])));
        $assert($sourceIds !== [], 'Question source trace is empty: ' . $id);
        foreach ($sourceIds as $sourceId) {
            $assert(isset($sources[$sourceId]), 'Question source does not resolve: ' . $id . '/' . $sourceId);
        }
        foreach ((array) ($materials[$materialId]['source_ids'] ?? []) as $sourceId) {
            $assert(isset($sources[(string) $sourceId]), 'Study material source does not resolve: ' . $materialId . '/' . $sourceId);
        }
    }

    $card = $flashcards->derive($question);
    $assert(is_array($card) && ($card['question_id'] ?? '') === $id, 'Flashcard derivation failed after remap: ' . $id);
}
$assert($remapMetadataCount === 464, 'Not all ME questions carry migration evidence.');

$equivalences = App::storage()->all('concept-equivalence');
$batch644Equivalences = array_values(array_filter(
    $equivalences,
    static fn(array $row): bool => (int) ($row['batch'] ?? 0) === 644
        || str_contains((string) json_encode($row), 'b644-me-')
));
$assert($batch644Equivalences === [], 'Batch644 activated a different-ID concept equivalence.');

echo json_encode([
    'status' => 'PASS',
    'native_me_questions' => count($questions),
    'active_leaves' => count($counts),
    'readiness_records' => count($meReadiness['records'] ?? []),
    'question_taxonomy_paths_valid' => 464,
    'question_content_fingerprints_preserved' => 464,
    'question_study_source_chains_valid' => 464,
    'flashcards_derivable' => 464,
    'new_me_concepts' => $newConcepts,
    'legacy_structural_nodes' => $structuralNodes,
    'batch644_concept_equivalences' => count($batch644Equivalences),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
