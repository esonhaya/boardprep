#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

if (!in_array(($argv[1] ?? ''), ['--dry-run', '--apply'], true)) {
    fwrite(STDERR, "Usage: php tools/extend-batch694-psychometrician-depth.php --dry-run|--apply\n");
    exit(2);
}
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
\App\Core\App::boot();
$storage = \App\Core\App::storage();
$data = require __DIR__ . '/batch694-psychometrician-extension-data.php';
$questions = $storage->all('questions');
$eligibility = $storage->all('question-eligibility');
$links = $storage->all('study-question-links');
$readiness = $storage->all('source-readiness-psychometrician');
$sources = [];
foreach ($storage->all('sources') as $source) if (is_array($source)) $sources[(string) ($source['id'] ?? '')] = $source;
$lessons = [];
foreach ($storage->all('study-lessons') as $lesson) if (is_array($lesson)) $lessons[(string) ($lesson['material_id'] ?? '')] = $lesson;
$expectedCount = count((array) ($data['abnormal'] ?? [])) + count((array) ($data['industrial'] ?? []));
$already = array_values(array_filter($questions, static fn(array $q): bool => ($q['batch_extension'] ?? '') === 'psychometrician-depth'));
if ($already !== []) {
    if (count($already) !== $expectedCount) throw new RuntimeException('Partial extension data exists; inspect records before retrying.');
    $addedByConcept = [];
    foreach ($already as $question) {
        $conceptId = (string) ($question['taxonomy']['concept_id'] ?? '');
        if ($conceptId !== '') $addedByConcept[$conceptId] = ($addedByConcept[$conceptId] ?? 0) + 1;
    }
    $questionsByConcept = [];
    foreach ($questions as $question) {
        if (!is_array($question) || ($question['status'] ?? 'active') === 'archived') continue;
        $conceptId = (string) ($question['taxonomy']['concept_id'] ?? '');
        if ($conceptId !== '') $questionsByConcept[$conceptId] = ($questionsByConcept[$conceptId] ?? 0) + 1;
    }
    $readinessChanged = false;
    foreach ($readiness as &$row) {
        $conceptId = (string) ($row['leaf_id'] ?? '');
        if (!isset($addedByConcept[$conceptId])) continue;
        $actualCount = (int) ($questionsByConcept[$conceptId] ?? 0);
        $currentCount = (int) ($row['current_question_count'] ?? 0);
        $before = json_encode([$row['current_question_count'] ?? null, $row['deficit_to_5'] ?? null, $row['deficit_to_10'] ?? null, $row['deficit_to_20'] ?? null, $row['max_new_questions'] ?? null, $row['readiness'] ?? null, $row['review_reason'] ?? null]);
        if ($actualCount > $currentCount) {
            $delta = $actualCount - $currentCount;
            $row['max_new_questions'] = max(0, (int) ($row['max_new_questions'] ?? 0) - $delta);
        }
        $row['current_question_count'] = $actualCount;
        $row['deficit_to_5'] = max(0, 5 - $actualCount);
        $row['deficit_to_10'] = max(0, 10 - $actualCount);
        $row['deficit_to_20'] = max(0, 20 - $actualCount);
        $row['planned_new_questions'] = 0;
        $row['limitations'][] = 'Batch 694 used source-readiness-bounded additional questions; independent academic review remains pending.';
        if ((int) ($row['max_new_questions'] ?? 0) === 0 && in_array((string) ($row['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true)) {
            $row['readiness'] = 'REVIEW';
            $row['review_reason'] = 'Batch 694 consumed the currently source-supported bounded headroom; further authoring requires refreshed evidence and academic review.';
        }
        $row['limitations'] = array_values(array_unique($row['limitations']));
        $after = json_encode([$row['current_question_count'], $row['deficit_to_5'], $row['deficit_to_10'], $row['deficit_to_20'], $row['max_new_questions'], $row['readiness'], $row['review_reason']]);
        if ($before !== $after) {
            $row['evidence_hash'] = \App\Services\RepositoryHealth\Readiness\SourceReadinessRecord::evidenceHash($row);
            $readinessChanged = true;
        }
    }
    unset($row);
    if ($readinessChanged) {
        $path = dirname(__DIR__) . '/storage/source-readiness-psychometrician.json';
        $temporary = $path . '.batch694-tmp';
        $payload = json_encode($readiness, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        if (file_put_contents($temporary, $payload, LOCK_EX) !== strlen($payload) || !rename($temporary, $path)) throw new RuntimeException('Could not atomically reconcile psychometrician readiness evidence.');
    }
    echo "BATCH694_PSYCHOMETRICIAN_EXTENSION=ALREADY_APPLIED QUESTIONS={$expectedCount}\n";
    exit(0);
}

$destinationByGroup = [
    'abnormal' => [
        'subject' => 'psychology-foundations',
        'selector' => 'abnormal-psychology',
        'material' => 'psychometrician-abnormal-psychology-classification-and-assessment',
        'source_ids' => ['prc-psychometrician-enhanced-tos-2023','who-icd11-cddr-2024','openstax-psychology-2e'],
    ],
    'industrial' => [
        'subject' => 'psychology-foundations',
        'selector' => 'industrial-organizational',
        'material' => 'psychometrician-work-psychology-job-analysis-and-evaluation',
        'source_ids' => ['prc-psychometrician-enhanced-tos-2023','openstax-psychology-2e','apa-personnel-selection-principles-2018','apa-ethics-code-2017'],
    ],
];
$sectionFor = static function (string $group, string $concept): string {
    if ($group === 'abnormal') {
        if (str_contains($concept, 'sociocultural')) return '4-ethical-communication-and-referral';
        if (str_contains($concept, 'interventions')) return '3-measurement-and-screening';
        if (str_contains($concept, 'manifestations')) return '2-pattern-duration-and-functioning';
        if (str_contains($concept, 'etiology')) return '2-pattern-duration-and-functioning';
        return '1-classification-is-a-shared-language';
    }
    if (str_contains($concept, 'team')) return '4-performance-and-organizational-evidence';
    if (str_contains($concept, 'organization')) return '4-performance-and-organizational-evidence';
    if (str_contains($concept, 'structures')) return '1-start-with-the-work';
    if (str_contains($concept, 'change')) return '4-performance-and-organizational-evidence';
    return '1-start-with-the-work';
};

foreach ($destinationByGroup as $destination) {
    $entryId = 'batch694-psychometrician-' . $destination['selector'];
    if (isset($eligibilityAt[$entryId])) continue;
    $eligibilityAt[$entryId] = count($eligibility);
    $eligibility[] = [
        'id' => $entryId,
        'question_ids' => [],
        'exam_ids' => ['psychometrician'],
        'subject_ids' => ['psychometrician' => $destination['subject']],
        'question_mappings' => [],
    ];
}

$questionAt = [];
$nextId = 1;
foreach ($questions as $i => $q) {
    if (!is_array($q) || !is_numeric($q['id'] ?? null)) continue;
    $questionAt[(string) $q['id']] = $i;
    $nextId = max($nextId, (int) $q['id'] + 1);
}
$readinessAt = [];
foreach ($readiness as $i => $row) if (is_array($row)) $readinessAt[(string) ($row['leaf_id'] ?? '')] = $i;
$eligibilityAt = [];
foreach ($eligibility as $i => $row) if (is_array($row) && isset($row['id'])) $eligibilityAt[(string) $row['id']] = $i;
$boardBlueprints = [];
foreach ($storage->all('blueprints') as $blueprint) if (($blueprint['board_id'] ?? '') === 'psychometrician') $boardBlueprints[] = $blueprint;
$knownConcepts = [];
foreach ($storage->all('taxonomy/concepts') as $concept) if (is_array($concept)) $knownConcepts[(string) ($concept['id'] ?? '')] = $concept;

$pendingQuestions = [];
$pendingMappings = [];
$readinessAdds = [];
$timestamp = gmdate('Y-m-d\TH:i:s+00:00');
foreach (['abnormal', 'industrial'] as $group) {
    $destination = $destinationByGroup[$group];
    $materialId = $destination['material'];
    $lesson = $lessons[$materialId] ?? null;
    if (!is_array($lesson) || ($lesson['status'] ?? '') !== 'published') throw new RuntimeException('Published instructional lesson is missing for ' . $group . '.');
    foreach ($destination['source_ids'] as $sourceId) if (!isset($sources[$sourceId])) throw new RuntimeException('Unregistered source ' . $sourceId . '.');
    $entryId = 'batch694-psychometrician-' . $destination['selector'];
    if (!isset($eligibilityAt[$entryId])) throw new RuntimeException('Destination eligibility record is missing: ' . $entryId);
    foreach ((array) ($data[$group] ?? []) as $row) {
        if (count($row) !== 7) throw new RuntimeException('Invalid extension row for ' . $group . '.');
        [$conceptId, $difficulty, $stem, $answer, $distractors, $explanation, $rowSources] = $row;
        $conceptId = (string) $conceptId;
        $concept = $knownConcepts[$conceptId] ?? null;
        if (!is_array($concept)) throw new RuntimeException('Unknown concept ' . $conceptId . '.');
        if (!in_array((string) $difficulty, ['easy','medium','hard'], true)) throw new RuntimeException('Invalid difficulty for ' . $conceptId);
        if (!is_array($distractors) || count($distractors) !== 3) throw new RuntimeException('Exactly three distractors are required for ' . $conceptId);
        if (trim((string) $stem) === '' || trim((string) $answer) === '' || trim((string) $explanation) === '') throw new RuntimeException('Empty authored field for ' . $conceptId);
        foreach ((array) $rowSources as $sourceId) if (!isset($sources[(string) $sourceId])) throw new RuntimeException('Unregistered row source ' . (string) $sourceId);
        $template = null;
        foreach ($questions as $q) {
            if (is_array($q) && (string) (($q['taxonomy']['concept_id'] ?? '')) === $conceptId) { $template = $q; break; }
        }
        if (!is_array($template)) throw new RuntimeException('No canonical template exists for ' . $conceptId);
        $expectedDomain = $group === 'abnormal' ? 'ple-abnormal' : 'ple-industrial';
        if ((string) ($template['taxonomy']['domain_id'] ?? '') !== $expectedDomain) throw new RuntimeException('Concept falls outside the expected domain: ' . $conceptId);
        $sectionId = $sectionFor($group, $conceptId);
        $lessonSectionIds = array_map(static fn(array $s): string => (string) ($s['id'] ?? ''), (array) ($lesson['sections'] ?? []));
        if (!in_array($sectionId, $lessonSectionIds, true)) throw new RuntimeException('Lesson section does not resolve: ' . $sectionId);
        $sourceIds = array_values(array_unique(array_merge($destination['source_ids'], (array) $rowSources)));
        $position = ($nextId * 11) % 4;
        $choices = array_values($distractors);
        array_splice($choices, $position, 0, [(string) $answer]);
        $normalizedChoices = array_map(static fn(mixed $v): string => mb_strtolower(preg_replace('/\s+/u',' ',trim((string)$v) ) ?? trim((string)$v)), $choices);
        if (count(array_unique($normalizedChoices)) !== 4) throw new RuntimeException('Duplicate choices in item for ' . $conceptId);

        $q = $template;
        $q['id'] = $nextId;
        $q['taxonomy']['board_id'] = 'psychometrician';
        $q['board'] = 'psychometrician';
        $q['status'] = 'active';
        $q['source'] = 'batch694-source-first';
        $q['batch_marker'] = 'batch694';
        $q['difficulty'] = (string) $difficulty;
        $q['question'] = (string) $stem;
        $q['choices'] = $choices;
        $q['answer'] = (string) $answer;
        $q['explanation'] = (string) $explanation;
        $q['source_ids'] = $sourceIds;
        $q['study_material_id'] = $materialId;
        $q['study_section_id'] = $sectionId;
        $q['timesUsed'] = 0;
        $q['timesCorrect'] = 0;
        $q['timesIncorrect'] = 0;
        $q['bookmarks'] = 0;
        $q['reports'] = 0;
        $q['helpfulExplanation'] = 0;
        $q['notHelpfulExplanation'] = 0;
        $q['createdAt'] = $timestamp;
        $q['updatedAt'] = $timestamp;
        $q['scenario_family'] = 'batch694-psychometrician-' . $conceptId . '-' . $nextId;
        $q['misconception_family'] = 'batch694-psychometrician-distractors-' . $nextId;
        $q['cognitive_intent'] = 'application and interpretation';
        $q['case_interpretation'] = true;
        $q['professional_judgment'] = true;
        $q['multistep'] = false;
        $q['numeric'] = false;
        $q['data_interpretation'] = false;
        $q['table_dependent'] = false;
        if (str_contains($conceptId, 'sociocultural') || str_contains($conceptId, 'interventions')) $q['ethics_scope_application'] = true;
        $q['batch_marker'] = 'batch694';
        $q['batch_extension'] = 'psychometrician-depth';

        $mapping = [
            'subject_id' => $destination['subject'],
            'domain_id' => (string) ($q['taxonomy']['domain_id'] ?? ''),
            'topic_id' => (string) ($q['taxonomy']['topic_id'] ?? ''),
            'concept_id' => $conceptId,
            'blueprint_node' => $destination['selector'],
        ];
        $validation = \App\Services\Question\QuestionEligibilityService::validateMapping($q, [
            'target_board'=>'psychometrician',
            'target_subject'=>$destination['subject'],
            'target_domain'=>$mapping['domain_id'],
            'target_topic'=>$mapping['topic_id'],
            'target_concept'=>$mapping['concept_id'],
            'blueprint_node'=>$destination['selector'],
        ], true);
        if (($validation['valid'] ?? false) !== true) throw new RuntimeException('Destination mapping rejected for ' . $nextId . ': ' . json_encode($validation['errors'] ?? []));
        $mapping = $validation['mapping'];
        $pendingQuestions[] = $q;
        $pendingMappings[] = ['id'=>(string)$nextId,'subject'=>$destination['selector'],'canonical_subject'=>$destination['subject'],'mapping'=>$mapping,'material'=>$materialId,'sources'=>$sourceIds,'section'=>$sectionId];
        $readinessAdds[$conceptId] = ($readinessAdds[$conceptId] ?? 0) + 1;
        $nextId++;
    }
}

if (count($pendingQuestions) !== $expectedCount) throw new RuntimeException('Extension count did not reconcile.');
foreach ($readinessAdds as $conceptId => $addCount) {
    $rowIndex = $readinessAt[$conceptId] ?? null;
    if ($rowIndex === null) throw new RuntimeException('Readiness row is missing for ' . $conceptId);
    $headroom = (int) ($readiness[$rowIndex]['max_new_questions'] ?? 0);
    if ($headroom < $addCount) throw new RuntimeException('Source-readiness headroom exceeded for ' . $conceptId . ': ' . $headroom . ' available, ' . $addCount . ' planned.');
}

foreach ($pendingMappings as $pending) {
    $entryIndex = $eligibilityAt['batch694-psychometrician-' . $pending['subject']];
    $entry =& $eligibility[$entryIndex];
    $entry['question_ids'][] = $pending['id'];
    $entry['question_mappings'][$pending['id']] = [
        'exam_ids'=>['psychometrician'],
        'subject_ids'=>['psychometrician'=>$pending['canonical_subject']],
        'placements'=>['psychometrician'=>$pending['mapping']],
    ];
    unset($entry);
    $links[] = ['id'=>'batch694-psychometrician-'.$pending['id'],'material_id'=>$pending['material'],'question_id'=>$pending['id'],'source_ids'=>$pending['sources']];
}
foreach ($readinessAdds as $conceptId => $addCount) {
    $i = $readinessAt[$conceptId];
    $readiness[$i]['current_question_count'] = (int) ($readiness[$i]['current_question_count'] ?? 0) + $addCount;
    $readiness[$i]['deficit_to_5'] = max(0, 5 - $readiness[$i]['current_question_count']);
    $readiness[$i]['deficit_to_10'] = max(0, 10 - $readiness[$i]['current_question_count']);
    $readiness[$i]['deficit_to_20'] = max(0, 20 - $readiness[$i]['current_question_count']);
    $readiness[$i]['max_new_questions'] = (int) ($readiness[$i]['max_new_questions'] ?? 0) - $addCount;
    $readiness[$i]['planned_new_questions'] = 0;
    $readiness[$i]['limitations'][] = 'Batch 694 used source-readiness-bounded additional questions; independent academic review remains pending.';
    if ($readiness[$i]['max_new_questions'] === 0 && in_array((string) ($readiness[$i]['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true)) {
        $readiness[$i]['readiness'] = 'REVIEW';
        $readiness[$i]['review_reason'] = 'Batch 694 consumed the currently source-supported bounded headroom; further authoring requires refreshed evidence and academic review.';
    }
    $readiness[$i]['limitations'] = array_values(array_unique($readiness[$i]['limitations']));
    $readiness[$i]['evidence_hash'] = \App\Services\RepositoryHealth\Readiness\SourceReadinessRecord::evidenceHash($readiness[$i]);
}

$questions = array_merge($questions, $pendingQuestions);
$index = new \App\Services\Question\QuestionEligibilityIndex(
    $eligibility,
    $storage->all('blueprints'),
    \App\Services\Question\QuestionReuseEligibilityResolver::productionDerivedRelationships(),
    $storage->all('question-reuse-overrides')
);
foreach ($pendingQuestions as $q) {
    $placement = $index->forExam($q, 'psychometrician');
    $subject = str_contains((string) ($q['taxonomy']['concept_id'] ?? ''), 'psych-abnormal-') ? 'abnormal-psychology' : 'industrial-organizational';
    if (!is_array($placement) || (string) ($placement['subject_id'] ?? '') !== $subject) throw new RuntimeException('Final learner eligibility did not resolve for question ' . $q['id']);
}

if (($argv[1] ?? '') === '--apply') {
    $storage->replace('questions', $questions);
    $storage->replace('question-eligibility', $eligibility);
    $storage->replace('study-question-links', $links);
    $readinessPath = dirname(__DIR__) . '/storage/source-readiness-psychometrician.json';
    $readinessTemporary = $readinessPath . '.batch694-tmp';
    $readinessPayload = json_encode($readiness, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    if (file_put_contents($readinessTemporary, $readinessPayload, LOCK_EX) !== strlen($readinessPayload) || !rename($readinessTemporary, $readinessPath)) throw new RuntimeException('Could not atomically update psychometrician source-readiness records.');
}
printf("BATCH694_PSYCHOMETRICIAN_EXTENSION=%s QUESTIONS=%d ABNORMAL=21 INDUSTRIAL=15 IDS=%d-%d\n", ($argv[1] ?? '') === '--apply' ? 'APPLIED' : 'DRY_RUN_PASS', count($pendingQuestions), (int) ($pendingQuestions[0]['id'] ?? 0), (int) ($pendingQuestions[array_key_last($pendingQuestions)]['id'] ?? 0));
