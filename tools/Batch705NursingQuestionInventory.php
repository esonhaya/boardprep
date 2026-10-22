<?php

declare(strict_types=1);

/**
 * Read-only Batch 705 inventory. Nursing ownership is resolved by the same
 * production eligibility index used by learner question selection.
 * Risk labels prioritize review; they do not declare a question unsafe.
 */

putenv('APP_ENV=production');
putenv('APP_DEBUG=false');
putenv('DB_DRIVER=json');

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\App;
use App\Services\Question\QuestionEligibilityService;

$allQuestions = App::storage()->all('questions');
$eligibility = QuestionEligibilityService::index();
$lessons = App::storage()->all('study-lessons');
$links = App::storage()->all('study-question-links');
$sources = App::storage()->all('sources');
$requiredConcepts = App::storage()->all('question-required-concepts');
$concepts = App::storage()->all('taxonomy/concepts');

$lessonById = [];
foreach ($lessons as $lesson) {
    if (is_array($lesson) && trim((string) ($lesson['id'] ?? '')) !== '') {
        $lessonById[(string) $lesson['id']] = $lesson;
    }
}
$sourceById = [];
foreach ($sources as $source) {
    if (is_array($source) && trim((string) ($source['id'] ?? '')) !== '') {
        $sourceById[(string) $source['id']] = $source;
    }
}
$conceptById = [];
foreach ($concepts as $concept) {
    if (is_array($concept) && trim((string) ($concept['id'] ?? '')) !== '') {
        $conceptById[(string) $concept['id']] = $concept;
    }
}
$linksByQuestion = [];
foreach ($links as $link) {
    if (is_array($link) && is_scalar($link['question_id'] ?? null)) {
        $linksByQuestion[(string) $link['question_id']][] = $link;
    }
}
$requirementsByQuestion = [];
foreach ($requiredConcepts as $requirement) {
    if (!is_array($requirement) || !is_scalar($requirement['question_id'] ?? null)) continue;
    $requirementsByQuestion[(string) $requirement['question_id']][] = (string) ($requirement['concept_id'] ?? '');
}

$contains = static function (string $value, array $needles): bool {
    foreach ($needles as $needle) if ($needle !== '' && str_contains($value, $needle)) return true;
    return false;
};
$riskFor = static function (array $question, string $conceptId, string $conceptName) use ($contains): array {
    $text = mb_strtolower(implode(' ', array_filter([
        (string) ($question['question'] ?? ''),
        is_array($question['choices'] ?? null) ? implode(' ', array_map('strval', $question['choices'])) : '',
        (string) ($question['answer'] ?? ''),
        (string) ($question['explanation'] ?? ''),
        $conceptName,
    ])));
    $concept = strtolower($conceptId);
    $categories = [];
    if ($concept === 'nle-np-iv-medication' || $contains($text, ['medication error', 'dose calculation', 'mcg/kg', 'mg/kg', 'infusion pump', 'high-alert', 'medication order', 'insulin dose'])) {
        $categories[] = 'MEDICATION_ADMINISTRATION_CALCULATION';
    }
    $maternalNewborn = str_starts_with($concept, 'nle-np-ii-')
        || preg_match('/\b(?:pregnan\w*|labou?r|postpartum|newborn|infant feeding|maternal)\b/i', $text) === 1;
    if ($maternalNewborn) {
        $categories[] = 'MATERNAL_NEWBORN';
    }
    if ($concept === 'nle-np-v-crisis'
        || $contains($text, ['suicid', 'self-harm', 'self harm', 'kill myself', 'harm themself', 'harm themselves'])) {
        $categories[] = 'PSYCHIATRIC_SAFETY';
    }
    if (in_array($concept, ['nle-np-v-emergency', 'nle-np-v-shock', 'nle-np-v-acuity'], true)
        || $contains($text, ['worsening breathing', 'falling blood pressure', 'new confusion', 'activate emergency', 'sepsis risk', 'acute deterioration', 'shock', 'unequal pupils', 'decreasing responsiveness', 'decline in level of consciousness', 'new focal deficit', 'sudden weakness'])) {
        $categories[] = 'DETERIORATION_TRIAGE';
    }
    if ($concept === 'nle-np-iii-infection'
        || preg_match('/\b(?:infection control|transmission precautions|isolation precaution|ppe|needlestick|exposure|immunosuppressive therapy|immunosuppression|febrile-risk)\b/i', $text) === 1) {
        $categories[] = 'INFECTION_CONTROL_EXPOSURE';
    }
    if ($contains($text, ['emergency response', 'urgent escalation', 'call for help', 'activate the response'])) {
        $categories[] = 'EMERGENCY_RESPONSE_ESCALATION';
    }
    if ($contains($text, ['delegat', 'assignment response', 'scope of practice', 'retain accountability'])) {
        $categories[] = 'DELEGATION_SCOPE_ACCOUNTABILITY';
    }
    if ($maternalNewborn && $contains($text, ['hemorrhage', 'haemorrhage', 'eclampsia', 'preeclampsia', 'pre-eclampsia', 'severe headache', 'visual change', 'visual disturbance', 'shortness of breath', 'pale and limp', 'pale or limp', 'not breathing', 'poor tone', 'newborn becomes', 'newborn is limp', 'fetal distress', 'cord prolapse', 'seizure'])) {
        $categories[] = 'MATERNAL_NEWBORN_EMERGENCY';
    }
    $categories = array_values(array_unique($categories));
    $p0 = array_intersect($categories, [
        'MEDICATION_ADMINISTRATION_CALCULATION', 'PSYCHIATRIC_SAFETY', 'DETERIORATION_TRIAGE',
        'INFECTION_CONTROL_EXPOSURE', 'EMERGENCY_RESPONSE_ESCALATION',
        'DELEGATION_SCOPE_ACCOUNTABILITY', 'MATERNAL_NEWBORN_EMERGENCY',
    ]) !== [];
    if ($p0) $priority = 'P0';
    elseif (in_array((string) ($question['cognitive_intent'] ?? ''), ['remember', 'recall', 'understand'], true)
        || ($conceptId !== '' && !str_starts_with($conceptId, 'nle-np-') && !str_starts_with($conceptId, 'science-'))) $priority = 'P2';
    else $priority = 'P1';
    return [$priority, $categories];
};

$records = [];
foreach ($allQuestions as $question) {
    if (!is_array($question) || ($question['status'] ?? '') !== 'active') continue;
    $placement = QuestionEligibilityService::forExam($question, 'nursing', $eligibility);
    if ($placement === null) continue;

    $id = (string) ($question['id'] ?? '');
    $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
    $conceptId = (string) ($taxonomy['concept_id'] ?? '');
    $concept = $conceptById[$conceptId] ?? [];
    $conceptIds = array_values(array_unique(array_filter(array_merge(
        $conceptId !== '' ? [$conceptId] : [],
        $requirementsByQuestion[$id] ?? []
    ), static fn(string $value): bool => $value !== '')));
    $questionLinks = $linksByQuestion[$id] ?? [];
    $sourceIds = [];
    $exactMappings = [];
    $nonNursingExact = [];
    foreach ($questionLinks as $link) {
        foreach (is_array($link['source_ids'] ?? null) ? $link['source_ids'] : [] as $sourceId) {
            if (is_scalar($sourceId)) $sourceIds[] = (string) $sourceId;
        }
        $lessonId = (string) ($link['lesson_id'] ?? '');
        $sectionId = (string) ($link['section_id'] ?? '');
        if ($lessonId === '' || $sectionId === '') continue;
        $owner = $lessonById[$lessonId] ?? null;
        if (!is_array($owner)) continue;
        $mapping = [
            'lesson_id' => $lessonId,
            'section_id' => $sectionId,
            'semantic_review_status' => (string) ($link['semantic_review_status'] ?? 'MISSING'),
            'source_ids' => array_values(array_map('strval', is_array($link['source_ids'] ?? null) ? $link['source_ids'] : [])),
        ];
        if (strtolower((string) ($owner['board_id'] ?? '')) === 'nursing') $exactMappings[] = $mapping;
        else $nonNursingExact[] = $mapping;
    }
    $sourceIds = array_values(array_unique($sourceIds));
    sort($sourceIds, SORT_STRING);
    [$priority, $riskCategories] = $riskFor($question, $conceptId, (string) ($concept['name'] ?? ''));

    $stem = (string) ($question['question'] ?? '');
    $choices = is_array($question['choices'] ?? null) ? array_values($question['choices']) : [];
    $answer = $question['answer'] ?? null;
    $explanation = (string) ($question['explanation'] ?? '');
    $screenFlags = [];
    if (trim($explanation) === '') $screenFlags[] = 'MISSING_RATIONALE';
    elseif (mb_strlen(trim($explanation)) < 80) $screenFlags[] = 'RATIONALE_SHORT_REVIEW';
    $normalize = static fn(mixed $value): string => mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $value)) ?? trim((string) $value));
    $normalizedChoices = array_map($normalize, $choices);
    if (count(array_unique($normalizedChoices)) < count($normalizedChoices)) $screenFlags[] = 'DUPLICATE_OPTION_REVIEW';
    if ($answer !== null && !in_array($normalize($answer), $normalizedChoices, true)) $screenFlags[] = 'ANSWER_KEY_NOT_IN_OPTIONS';
    if ($sourceIds === []) $screenFlags[] = 'NO_REGISTERED_SOURCE_ASSOCIATION';
    if (count($sourceIds) > 6) $screenFlags[] = 'BROAD_SOURCE_ASSOCIATION_REVIEW';
    if (preg_match('/\b(diagram|figure|image|graph|table|shown|pictured|labeled)\b/i', $stem) === 1
        || !empty($question['figure_dependent'])) $screenFlags[] = 'FIGURE_OR_DATA_DEPENDENCY_REVIEW';
    if (count($choices) < 2) $screenFlags[] = 'OPTION_SET_REVIEW';

    $npClassification = 'UNCLASSIFIED';
    if (preg_match('/^nle-np-(i|ii|iii|iv|v)-/', $conceptId, $match) === 1) $npClassification = 'NP_' . strtoupper($match[1]);
    elseif (str_starts_with($conceptId, 'science-')) $npClassification = 'SHARED_SCIENCE';

    $sourceRecords = [];
    foreach ($sourceIds as $sourceId) {
        $source = $sourceById[$sourceId] ?? null;
        $sourceRecords[] = [
            'source_id' => $sourceId,
            'title' => is_array($source) ? (string) ($source['title'] ?? '') : null,
            'status' => is_array($source) ? (string) ($source['status'] ?? 'MISSING_STATUS') : 'SOURCE_ID_NOT_REGISTERED',
        ];
    }
    $sectionIds = array_values(array_unique(array_map(static fn(array $mapping): string => $mapping['section_id'], $exactMappings)));
    $records[] = [
        'QUESTION_ID' => $id,
        'STATUS' => (string) ($question['status'] ?? ''),
        'CANONICAL_BOARD_ID' => strtolower((string) ($taxonomy['board_id'] ?? $question['board'] ?? '')),
        'SUBJECT' => (string) ($question['subject'] ?? ''),
        'RESOLVED_NURSING_SUBJECT_ID' => (string) ($placement['subject_id'] ?? ''),
        'NP_CLASSIFICATION' => $npClassification,
        'CONCEPT_IDS' => $conceptIds,
        'QUESTION_TYPE' => $question['question_type'] ?? $question['item_type'] ?? null,
        'STEM' => $stem,
        'OPTIONS' => $choices,
        'ANSWER_KEY' => $answer,
        'RATIONALE' => $explanation,
        'CANONICAL_SOURCE_FIELD' => $question['source'] ?? null,
        'SOURCE_IDS' => $sourceIds,
        'SOURCE_STATUS' => $sourceRecords,
        'EXACT_SECTION_ID' => count($sectionIds) === 1 ? $sectionIds[0] : null,
        'EXACT_SECTION_IDS' => $sectionIds,
        'EXACT_MAPPINGS' => $exactMappings,
        'NON_NURSING_EXACT_MAPPINGS' => $nonNursingExact,
        'EXACT_MAPPING_STATUS' => array_values(array_unique(array_map(static fn(array $mapping): string => $mapping['semantic_review_status'], $exactMappings))),
        'CLINICAL_RISK_CATEGORY' => $riskCategories,
        'POTENTIAL_DEFECTS' => $screenFlags,
        'REVIEW_PRIORITY' => $priority,
        'REVIEW_STATUS' => 'NOT_REVIEWED_AUTOMATED_SCREEN_ONLY',
        'DIFFICULTY' => $question['difficulty'] ?? null,
        'COGNITIVE_INTENT' => $question['cognitive_intent'] ?? null,
        'MULTISTEP' => $question['multistep'] ?? null,
    ];
}

usort($records, static function (array $a, array $b): int {
    $na = ctype_digit((string) $a['QUESTION_ID']) ? (int) $a['QUESTION_ID'] : PHP_INT_MAX;
    $nb = ctype_digit((string) $b['QUESTION_ID']) ? (int) $b['QUESTION_ID'] : PHP_INT_MAX;
    return $na <=> $nb ?: strcmp((string) $a['QUESTION_ID'], (string) $b['QUESTION_ID']);
});

$priorityCounts = ['P0' => 0, 'P1' => 0, 'P2' => 0];
$categoryCounts = [];
$flagCounts = [];
foreach ($records as $record) {
    $priorityCounts[$record['REVIEW_PRIORITY']]++;
    foreach ($record['CLINICAL_RISK_CATEGORY'] as $category) $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
    foreach ($record['POTENTIAL_DEFECTS'] as $flag) $flagCounts[$flag] = ($flagCounts[$flag] ?? 0) + 1;
}
ksort($categoryCounts);
ksort($flagCounts);

$output = [
    'report_type' => 'BATCH705_NURSING_QUESTION_QUALITY_INVENTORY',
    'generated_at' => date('Y-m-d'),
    'repository' => dirname(__DIR__),
    'tested_base_or_generation_sha' => trim((string) shell_exec('git rev-parse HEAD')),
    'methodology' => [
        'eligibility' => 'Active Nursing eligibility is calculated through QuestionEligibilityService::forExam using the production QuestionEligibilityIndex; canonical topic keywords never determine board ownership.',
        'risk' => 'P0/P1/P2 and risk categories are review-order flags. They are heuristic screens based on canonical concept, stem, options and rationale; they do not declare a question clinically unsafe or verified.',
        'source_status' => 'Status is copied from the current source registry for linked source IDs. A status is not proof that a source supports this question or that a claim is current.',
        'potential_defects' => 'Automated flags identify fields or patterns for human audit only. They are not adjudicated question defects.',
    ],
    'summary' => [
        'active_nursing_eligible_questions' => count($records),
        'priority_counts' => $priorityCounts,
        'risk_category_counts_overlap' => $categoryCounts,
        'automated_screen_flag_counts_overlap' => $flagCounts,
        'review_decision_count' => 0,
        'canonical_question_sha256' => hash_file('sha256', dirname(__DIR__) . '/storage/questions.json'),
    ],
    'questions' => $records,
];

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
