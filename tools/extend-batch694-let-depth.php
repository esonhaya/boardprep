#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
if (!in_array(($argv[1] ?? ''), ['--dry-run', '--apply'], true)) {
    fwrite(STDERR, "Usage: php tools/extend-batch694-let-depth.php --dry-run|--apply\n");
    exit(2);
}
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
\App\Core\App::boot();
$storage = \App\Core\App::storage();
$data = require __DIR__ . '/batch694-let-depth-extension-data.php';
$questions = $storage->all('questions');
$eligibility = $storage->all('question-eligibility');
$links = $storage->all('study-question-links');
$readiness = $storage->all('source-readiness-let');
$sourceById = [];
foreach ($storage->all('sources') as $source) if (is_array($source)) $sourceById[(string) ($source['id'] ?? '')] = $source;
$materials = [];
foreach ($storage->all('study-materials') as $material) if (is_array($material)) $materials[(string) ($material['id'] ?? '')] = $material;
$lessons = [];
foreach ($storage->all('study-lessons') as $lesson) if (is_array($lesson)) $lessons[(string) ($lesson['material_id'] ?? '')] = $lesson;
$expected = count($data);
$already = array_values(array_filter($questions, static fn(array $q): bool => ($q['batch_extension'] ?? '') === 'let-source-readiness-depth'));

$conceptQuestionCounts = [];
foreach ($questions as $question) {
    if (!is_array($question) || strtolower((string) ($question['status'] ?? 'active')) === 'archived') continue;
    $conceptId = (string) ($question['taxonomy']['concept_id'] ?? '');
    if ($conceptId !== '') $conceptQuestionCounts[$conceptId] = ($conceptQuestionCounts[$conceptId] ?? 0) + 1;
}
$reconcileReadiness = static function (array &$records, array $touchedConcepts, array $actualCounts): bool {
    $changed = false;
    foreach ($records as &$record) {
        $conceptId = (string) ($record['leaf_id'] ?? '');
        if (!isset($touchedConcepts[$conceptId])) continue;
        $actual = (int) ($actualCounts[$conceptId] ?? 0);
        $current = (int) ($record['current_question_count'] ?? 0);
        $before = json_encode([$record['current_question_count'] ?? null,$record['deficit_to_5'] ?? null,$record['deficit_to_10'] ?? null,$record['deficit_to_20'] ?? null,$record['max_new_questions'] ?? null,$record['readiness'] ?? null,$record['review_reason'] ?? null]);
        if ($actual > $current) $record['max_new_questions'] = max(0, (int) ($record['max_new_questions'] ?? 0) - ($actual - $current));
        $record['current_question_count'] = $actual;
        $record['deficit_to_5'] = max(0, 5 - $actual);
        $record['deficit_to_10'] = max(0, 10 - $actual);
        $record['deficit_to_20'] = max(0, 20 - $actual);
        $record['planned_new_questions'] = 0;
        $record['limitations'][] = 'Batch 694 used source-readiness-bounded additional questions; independent academic review remains pending.';
        $record['limitations'] = array_values(array_unique($record['limitations']));
        if ((int) ($record['max_new_questions'] ?? 0) === 0 && in_array((string) ($record['readiness'] ?? ''), ['READY','READY_WITH_LIMITS'], true)) {
            $record['readiness'] = 'REVIEW';
            $record['review_reason'] = 'Batch 694 consumed the currently source-supported bounded headroom; further authoring requires refreshed evidence and academic review.';
        }
        $after = json_encode([$record['current_question_count'],$record['deficit_to_5'],$record['deficit_to_10'],$record['deficit_to_20'],$record['max_new_questions'],$record['readiness'],$record['review_reason']]);
        if ($before !== $after) {
            $record['evidence_hash'] = \App\Services\RepositoryHealth\Readiness\SourceReadinessRecord::evidenceHash($record);
            $changed = true;
        }
    }
    unset($record);
    return $changed;
};
$writeReadiness = static function (array $records): void {
    $path = dirname(__DIR__) . '/storage/source-readiness-let.json';
    $temporary = $path . '.batch694-tmp';
    $payload = json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    if (file_put_contents($temporary, $payload, LOCK_EX) !== strlen($payload) || !rename($temporary, $path)) {
        throw new RuntimeException('Could not atomically update LET source-readiness records.');
    }
};

if ($already !== []) {
    if (count($already) !== $expected) throw new RuntimeException('Partial LET extension exists; inspect records before retrying.');
    $touched = array_fill_keys(array_keys($data), true);
    if ($reconcileReadiness($readiness, $touched, $conceptQuestionCounts)) $writeReadiness($readiness);
    echo "BATCH694_LET_DEPTH_EXTENSION=ALREADY_APPLIED QUESTIONS={$expected}\n";
    exit(0);
}

$subjectByPrefix = [
    'ece-' => ['subject'=>'early-childhood-education','specialization'=>'elementary-early-childhood-education','material'=>'let-early-childhood-learning-and-inclusion'],
    'sne-' => ['subject'=>'special-needs-education','specialization'=>'elementary-special-needs-education','material'=>'let-special-needs-inclusive-education'],
    'tvted-' => ['subject'=>'technical-vocational-teacher-education','specialization'=>'secondary-technical-vocational-teacher-education','material'=>'let-tvted-competency-based-teaching'],
    'cae-' => ['subject'=>'culture-and-arts-education','specialization'=>'secondary-culture-and-arts-education','material'=>'let-culture-arts-learning-foundation'],
    'pe-' => ['subject'=>'physical-education','specialization'=>'secondary-physical-education','material'=>'let-physical-education-movement-and-safety'],
];
$destinationFor = static function (string $conceptId) use ($subjectByPrefix): array {
    foreach ($subjectByPrefix as $prefix => $destination) if (str_starts_with($conceptId, $prefix)) return $destination;
    throw new RuntimeException('No destination subject for source-readiness leaf ' . $conceptId);
};
$questionIndexById = [];
$nextId = 1;
foreach ($questions as $index => $question) {
    if (!is_array($question) || !is_numeric($question['id'] ?? null)) continue;
    $questionIndexById[(string) $question['id']] = $index;
    $nextId = max($nextId, (int) $question['id'] + 1);
}
$readinessAt = [];
foreach ($readiness as $index => $record) if (is_array($record)) $readinessAt[(string) ($record['leaf_id'] ?? '')] = $index;
$eligibilityAt = [];
foreach ($eligibility as $index => $entry) if (is_array($entry)) $eligibilityAt[(string) ($entry['id'] ?? '')] = $index;
$index = \App\Services\Question\QuestionEligibilityService::index();
$pending = [];
$pendingMappings = [];
$readinessAdds = [];
$timestamp = gmdate('Y-m-d\TH:i:s+00:00');
$positionCounts = array_fill(0, 4, 0);

foreach ($data as $conceptId => $row) {
    if (count($row) !== 6) throw new RuntimeException('Invalid authoring row for ' . $conceptId);
    [$difficulty,$stem,$answer,$distractors,$explanation,$rowSources] = $row;
    $destination = $destinationFor((string) $conceptId);
    $readinessRow = $readiness[$readinessAt[(string) $conceptId] ?? -1] ?? null;
    if (!is_array($readinessRow) || (int) ($readinessRow['max_new_questions'] ?? 0) < 1 || !in_array((string) ($readinessRow['readiness'] ?? ''), ['READY','READY_WITH_LIMITS'], true)) {
        throw new RuntimeException('No verified authoring headroom for ' . $conceptId);
    }
    if (count($distractors) !== 3 || trim((string) $stem) === '' || trim((string) $explanation) === '') throw new RuntimeException('Incomplete authored content for ' . $conceptId);
    foreach ($rowSources as $sourceId) if (!isset($sourceById[(string) $sourceId])) throw new RuntimeException('Unregistered source ' . $sourceId . ' for ' . $conceptId);
    $template = null;
    $placement = null;
    foreach ($questions as $candidate) {
        if (!is_array($candidate) || ($candidate['taxonomy']['board_id'] ?? '') !== 'let' || ($candidate['taxonomy']['concept_id'] ?? '') !== $conceptId) continue;
        $resolved = \App\Services\Question\QuestionEligibilityService::forExam($candidate, 'let', $index);
        if (!is_array($resolved) || (string) ($resolved['subject_id'] ?? '') !== $destination['subject']) continue;
        $template = $candidate;
        $placement = is_array($resolved['placement'] ?? null) ? $resolved['placement'] : [];
        break;
    }
    if (!is_array($template) || !is_array($placement)) throw new RuntimeException('No eligible LET template exists for ' . $conceptId);
    $materialId = (string) ($template['study_material_id'] ?? $destination['material']);
    if ($materialId !== $destination['material'] || !isset($materials[$materialId], $lessons[$materialId]) || ($lessons[$materialId]['status'] ?? '') !== 'published') {
        throw new RuntimeException('Published source-first lesson missing for ' . $conceptId);
    }
    $sectionId = (string) ($template['study_section_id'] ?? '');
    $sectionIds = array_map(static fn(array $section): string => (string) ($section['id'] ?? ''), (array) ($lessons[$materialId]['sections'] ?? []));
    if ($sectionId === '' || !in_array($sectionId, $sectionIds, true)) throw new RuntimeException('Instructional section does not resolve for ' . $conceptId);

    $validation = \App\Services\Question\QuestionEligibilityService::validateMapping($template, [
        'target_board'=>'let',
        'target_subject'=>$destination['subject'],
        'target_domain'=>(string) ($template['taxonomy']['domain_id'] ?? ''),
        'target_topic'=>(string) ($template['taxonomy']['topic_id'] ?? ''),
        'target_concept'=>(string) $conceptId,
        'blueprint_node'=>(string) ($placement['blueprint_node'] ?? $destination['specialization']),
        'let_level'=>(string) ($placement['let_level'] ?? $placement['level'] ?? ''),
        'let_component'=>(string) ($placement['let_component'] ?? $placement['component'] ?? ''),
        'let_specialization_id'=>(string) ($placement['specialization_id'] ?? $destination['specialization']),
    ], true);
    if (($validation['valid'] ?? false) !== true) throw new RuntimeException('LET placement invalid for ' . $conceptId . ': ' . json_encode($validation['errors'] ?? []));
    $placement = $validation['mapping'];

    $id = $nextId++;
    $choices = array_values($distractors);
    $answerPosition = $id % 4;
    array_splice($choices, $answerPosition, 0, [(string) $answer]);
    $normalized = array_map(static fn(mixed $choice): string => mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $choice)) ?? trim((string) $choice)), $choices);
    if (count(array_unique($normalized)) !== 4) throw new RuntimeException('Repeated choices for new question ' . $id);
    $positionCounts[$answerPosition]++;
    $question = $template;
    $question['id'] = $id;
    $question['board'] = 'let';
    $question['taxonomy']['board_id'] = 'let';
    $question['status'] = 'active';
    $question['source'] = 'batch694-source-first';
    $question['batch_marker'] = 'batch694';
    $question['batch_extension'] = 'let-source-readiness-depth';
    $question['difficulty'] = (string) $difficulty;
    $question['question'] = (string) $stem;
    $question['choices'] = $choices;
    $question['answer'] = (string) $answer;
    $question['explanation'] = (string) $explanation;
    $question['source_ids'] = array_values(array_unique(array_map('strval', $rowSources)));
    $question['study_material_id'] = $materialId;
    $question['study_section_id'] = $sectionId;
    $question['let_placement'] = array_merge((array) ($question['let_placement'] ?? []), $placement);
    foreach (['timesUsed','timesCorrect','timesIncorrect','bookmarks','reports','helpfulExplanation','notHelpfulExplanation'] as $counter) $question[$counter] = 0;
    $question['createdAt'] = $timestamp;
    $question['updatedAt'] = $timestamp;
    $question['scenario_family'] = 'batch694-let-' . $conceptId;
    $question['misconception_family'] = 'batch694-let-distractors-' . $conceptId;
    $question['cognitive_intent'] = 'application and instructional judgment';
    $question['case_interpretation'] = true;
    $question['professional_judgment'] = true;
    $question['multistep'] = false;
    $question['numeric'] = false;

    $entryId = 'batch694-let-' . $destination['subject'];
    if (!isset($eligibilityAt[$entryId])) {
        $eligibilityAt[$entryId] = count($eligibility);
        $eligibility[] = ['id'=>$entryId,'question_ids'=>[],'exam_ids'=>['let'],'subject_ids'=>['let'=>$destination['subject']],'question_mappings'=>[]];
    }
    $pending[] = $question;
    $pendingMappings[] = ['id'=>(string)$id,'subject'=>$destination['subject'],'placement'=>$placement,'entry_id'=>$entryId,'material'=>$materialId,'sources'=>$question['source_ids']];
    $readinessAdds[(string) $conceptId] = 1;
}

foreach ($pendingMappings as $mapping) {
    $entryIndex = $eligibilityAt[$mapping['entry_id']];
    $eligibility[$entryIndex]['question_ids'][] = $mapping['id'];
    $eligibility[$entryIndex]['question_mappings'][$mapping['id']] = [
        'exam_ids'=>['let'],
        'subject_ids'=>['let'=>$mapping['subject']],
        'placements'=>['let'=>$mapping['placement']],
    ];
    $links[] = ['id'=>'batch694-let-'.$mapping['id'],'material_id'=>$mapping['material'],'question_id'=>$mapping['id'],'source_ids'=>$mapping['sources']];
}
$readinessChanged = $reconcileReadiness($readiness, $readinessAdds, $conceptQuestionCounts);
foreach ($readinessAdds as $conceptId => $count) {
    $readiness[$readinessAt[$conceptId]]['current_question_count'] = (int) ($readiness[$readinessAt[$conceptId]]['current_question_count'] ?? 0) + $count;
    $readiness[$readinessAt[$conceptId]]['max_new_questions'] = max(0, (int) ($readiness[$readinessAt[$conceptId]]['max_new_questions'] ?? 0) - $count);
    $readiness[$readinessAt[$conceptId]]['deficit_to_5'] = max(0, 5 - $readiness[$readinessAt[$conceptId]]['current_question_count']);
    $readiness[$readinessAt[$conceptId]]['deficit_to_10'] = max(0, 10 - $readiness[$readinessAt[$conceptId]]['current_question_count']);
    $readiness[$readinessAt[$conceptId]]['deficit_to_20'] = max(0, 20 - $readiness[$readinessAt[$conceptId]]['current_question_count']);
    $recordIndex = $readinessAt[$conceptId];
    $readiness[$recordIndex]['limitations'][] = 'Batch 694 used source-readiness-bounded additional questions; independent academic review remains pending.';
    $readiness[$recordIndex]['limitations'] = array_values(array_unique($readiness[$recordIndex]['limitations']));
    if ($readiness[$recordIndex]['max_new_questions'] === 0) {
        $readiness[$recordIndex]['readiness'] = 'REVIEW';
        $readiness[$recordIndex]['review_reason'] = 'Batch 694 consumed the currently source-supported bounded headroom; further authoring requires refreshed evidence and academic review.';
    }
    $readiness[$recordIndex]['evidence_hash'] = \App\Services\RepositoryHealth\Readiness\SourceReadinessRecord::evidenceHash($readiness[$recordIndex]);
}

$questions = array_merge($questions, $pending);
$finalIndex = new \App\Services\Question\QuestionEligibilityIndex(
    $eligibility,
    $storage->all('blueprints'),
    \App\Services\Question\QuestionReuseEligibilityResolver::productionDerivedRelationships(),
    $storage->all('question-reuse-overrides')
);
foreach ($pending as $question) {
    $resolved = $finalIndex->forExam($question, 'let');
    $subject = (string) ($question['taxonomy']['subject_id'] ?? '');
    if (!is_array($resolved) || (string) ($resolved['subject_id'] ?? '') !== $subject) throw new RuntimeException('Destination subject resolution failed for question ' . $question['id']);
}
if (min($positionCounts) < 1 || max($positionCounts) - min($positionCounts) > 1) throw new RuntimeException('Answer positions are unexpectedly concentrated.');

if (($argv[1] ?? '') === '--apply') {
    $storage->replace('questions', $questions);
    $storage->replace('question-eligibility', $eligibility);
    $storage->replace('study-question-links', $links);
    $writeReadiness($readiness);
}
printf("BATCH694_LET_DEPTH_EXTENSION=%s QUESTIONS=%d IDS=%d-%d POSITIONS=%s\n", ($argv[1] ?? '') === '--apply' ? 'APPLIED' : 'DRY_RUN_PASS', count($pending), (int) ($pending[0]['id'] ?? 0), (int) ($pending[array_key_last($pending)]['id'] ?? 0), implode('/', $positionCounts));
