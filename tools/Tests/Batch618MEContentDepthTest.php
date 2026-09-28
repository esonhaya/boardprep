<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\Shared\QuestionValidationService;

$root = dirname(__DIR__, 2);
$read = static function (string $name) use ($root): array {
    return json_decode(
        (string) file_get_contents($root . '/storage/' . $name . '.json'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$normalize = static function (string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;
    return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
};

$questions = array_values(array_filter($read('questions'), 'is_array'));
$metadata = array_values(array_filter($read('question-reuse-metadata'), 'is_array'));
$links = array_values(array_filter($read('study-question-links'), 'is_array'));
$eligibility = array_values(array_filter($read('question-eligibility'), 'is_array'));
$coverage = array_values(array_filter($read('board-concept-coverage'), 'is_array'));
$materials = array_values(array_filter($read('study-materials'), 'is_array'));
$sources = array_values(array_filter($read('sources'), 'is_array'));
$concepts = array_values(array_filter($read('taxonomy/concepts'), 'is_array'));
$requirements = array_values(array_filter($read('question-required-concepts'), 'is_array'));

$new = array_values(array_filter(
    $questions,
    static fn (array $question): bool => (int) ($question['id'] ?? 0) >= 3301
        && (int) ($question['id'] ?? 0) <= 3544
));
$assert(count($new) === 244, 'Batch 618 must contain exactly 244 new questions');

$selected = [
    'me-topic-2-1-concept',
    'me-topic-2-2-concept',
    'me-topic-2-3-concept',
    'me-topic-3-1-concept',
    'me-topic-3-2-concept',
    'me-topic-3-3-concept',
    'me-topic-3-4-concept',
    'me-topic-3-5-concept',
];
$questionById = [];
$allNormalized = [];
foreach ($questions as $question) {
    $id = (string) ($question['id'] ?? '');
    $questionById[$id] = $question;
    $normalized = $normalize((string) ($question['question'] ?? ''));
    if ($normalized !== '') {
        $allNormalized[$normalized][] = $id;
    }
}

$metadataById = [];
foreach ($metadata as $record) {
    $metadataById[(string) ($record['question_id'] ?? '')] = $record['reuse'] ?? [];
}
$linksById = [];
foreach ($links as $link) {
    $linksById[(string) ($link['question_id'] ?? '')][] = $link;
}
$sourceIds = [];
foreach ($sources as $source) {
    $sourceIds[(string) ($source['id'] ?? '')] = true;
}
$materialIds = [];
foreach ($materials as $material) {
    $materialIds[(string) ($material['id'] ?? '')] = true;
}
$globalConceptIds = [];
foreach ($concepts as $concept) {
    $globalConceptIds[(string) ($concept['id'] ?? '')] = true;
}
$eligibleIds = [];
foreach ($eligibility as $entry) {
    if (!in_array('mechanical-engineering', $entry['exam_ids'] ?? [], true)) {
        continue;
    }
    foreach ($entry['question_ids'] ?? [] as $id) {
        $eligibleIds[(string) $id] = true;
    }
}

$seen = [];
$conceptCounts = [];
$depthCounts = [];
$contextCounts = [];
foreach ($new as $question) {
    $id = (int) ($question['id'] ?? 0);
    $idString = (string) $id;
    $assert(!isset($seen[$id]), 'Duplicate Batch 618 ID: ' . $id);
    $seen[$id] = true;
    $assert(($question['board'] ?? '') === 'mechanical-engineering', 'Wrong board: ' . $id);
    $assert(($question['source'] ?? '') === 'batch618-source-first', 'Missing Batch 618 source marker: ' . $id);
    $assert(in_array($question['taxonomy']['concept_id'] ?? '', $selected, true), 'Unexpected concept: ' . $id);
    $assert(QuestionValidationService::validate($question)['valid'], 'Question validation failed: ' . $id);
    $assert(count($question['choices'] ?? []) === 4, 'Question does not have four choices: ' . $id);
    $assert(in_array($question['answer'] ?? '', $question['choices'] ?? [], true), 'Answer is not a choice: ' . $id);
    $assert(trim((string) ($question['explanation'] ?? '')) !== '', 'Explanation is empty: ' . $id);
    $assert(isset($eligibleIds[$idString]), 'Missing direct ME eligibility: ' . $id);

    $reuse = $metadataById[$idString] ?? null;
    $assert(is_array($reuse), 'Missing reuse metadata: ' . $id);
    $assert(($reuse['reuse_policy'] ?? '') === 'REVIEW', 'New item is not REVIEW: ' . $id);
    $assert(in_array($reuse['required_depth'] ?? null, [3, 4], true), 'New item has invalid depth: ' . $id);
    $assert(in_array($reuse['context_mode'] ?? '', ['NEUTRAL', 'PROFESSIONAL'], true), 'New item has invalid context: ' . $id);
    $depth = (string) ($reuse['required_depth'] ?? '');
    $depthCounts[$depth] = ($depthCounts[$depth] ?? 0) + 1;
    $context = (string) ($reuse['context_mode'] ?? '');
    $contextCounts[$context] = ($contextCounts[$context] ?? 0) + 1;

    $concept = (string) $question['taxonomy']['concept_id'];
    $conceptCounts[$concept] = ($conceptCounts[$concept] ?? 0) + 1;
    $questionNormalized = $normalize((string) $question['question']);
    $assert(count($allNormalized[$questionNormalized] ?? []) === 1, 'Normalized duplicate: ' . $id);

    $questionLinks = $linksById[$idString] ?? [];
    $assert(count($questionLinks) === 1, 'Expected one study link: ' . $id);
    $link = $questionLinks[0];
    $assert(isset($materialIds[(string) ($link['material_id'] ?? '')]), 'Invalid study material link: ' . $id);
    foreach ($link['source_ids'] ?? [] as $sourceId) {
        $assert(isset($sourceIds[(string) $sourceId]), 'Invalid source link: ' . $id);
    }
}
$assert(count($seen) === 244 && min(array_keys($seen)) === 3301 && max(array_keys($seen)) === 3544, 'ID range is not deterministic');

$coverageByConcept = [];
foreach ($coverage as $row) {
    if (($row['board_id'] ?? '') === 'mechanical-engineering') {
        $coverageByConcept[(string) ($row['concept_id'] ?? '')] = $row;
    }
}
foreach ($selected as $concept) {
    $assert(($coverageByConcept[$concept]['max_depth'] ?? null) === 4, 'Coverage depth is not 4: ' . $concept);
}

$requirementKeys = [];
$graph = [];
$primaryByQuestion = [];
foreach ($questions as $question) {
    $primaryByQuestion[(string) ($question['id'] ?? '')] = (string) ($question['taxonomy']['concept_id'] ?? '');
}
foreach ($requirements as $requirement) {
    $questionId = (string) ($requirement['question_id'] ?? '');
    $requiredConcept = (string) ($requirement['concept_id'] ?? '');
    $key = $questionId . '|' . $requiredConcept;
    $assert(!isset($requirementKeys[$key]), 'Duplicate prerequisite: ' . $key);
    $requirementKeys[$key] = true;
    $assert(isset($questionById[$questionId]), 'Orphan prerequisite question: ' . $key);
    $assert(isset($globalConceptIds[$requiredConcept]), 'Invalid prerequisite concept: ' . $key);
    $assert(($requirement['requirement_type'] ?? '') === 'REQUIRED', 'Unexpected requirement type in Batch 618 storage: ' . $key);
    $primary = $primaryByQuestion[$questionId] ?? '';
    $assert($primary !== '' && $primary !== $requiredConcept, 'Self/empty prerequisite: ' . $key);
    $graph[$primary][$requiredConcept] = true;
}

$state = [];
$visit = function (string $node) use (&$visit, &$state, &$graph): void {
    $state[$node] = 1;
    foreach (array_keys($graph[$node] ?? []) as $neighbor) {
        if (($state[$neighbor] ?? 0) === 1) {
            throw new RuntimeException('Circular prerequisite: ' . $node . '>' . $neighbor);
        }
        if (($state[$neighbor] ?? 0) === 0) {
            $visit($neighbor);
        }
    }
    $state[$node] = 2;
};
$nodes = array_keys($graph);
foreach ($graph as $neighbors) {
    foreach (array_keys($neighbors) as $neighbor) {
        $nodes[] = $neighbor;
    }
}
foreach (array_values(array_unique($nodes)) as $node) {
    if (($state[$node] ?? 0) === 0) {
        $visit($node);
    }
}

echo json_encode([
    'status' => 'PASS',
    'new_questions' => count($new),
    'concepts' => $conceptCounts,
    'depths' => $depthCounts,
    'contexts' => $contextCounts,
    'coverage_rows_calibrated' => count($selected),
    'required_concept_rows' => count($requirements),
], JSON_UNESCAPED_SLASHES) . PHP_EOL;
