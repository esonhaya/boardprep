<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Core\App;
use App\Services\Question\QuestionReuseEligibilityResolver;

$storage = App::storage();
$questions = array_values(array_filter($storage->all('questions'), 'is_array'));
$coverage = array_values(array_filter($storage->all('board-concept-coverage'), 'is_array'));
$equivalence = array_values(array_filter($storage->all('concept-equivalence'), 'is_array'));
$metadata = array_values(array_filter($storage->all('question-reuse-metadata'), 'is_array'));
$required = array_values(array_filter($storage->all('question-required-concepts'), 'is_array'));

$metadataByQuestion = [];
foreach ($metadata as $record) {
    if (is_scalar($record['question_id'] ?? null) && is_array($record['reuse'] ?? null)) {
        $metadataByQuestion[(string)$record['question_id']] = $record['reuse'];
    }
}

$directBoardsByQuestion = [];
foreach ($storage->all('question-eligibility') as $entry) {
    if (!is_array($entry)) continue;
    $entryExams = is_array($entry['exam_ids'] ?? null) ? $entry['exam_ids'] : [];
    foreach (($entry['question_ids'] ?? []) as $questionId) {
        if (!is_scalar($questionId)) continue;
        $id = (string)$questionId;
        $mapping = is_array($entry['question_mappings'][$id] ?? null) ? $entry['question_mappings'][$id] : [];
        $exams = is_array($mapping['exam_ids'] ?? null) ? $mapping['exam_ids'] : $entryExams;
        foreach ($exams as $exam) {
            if (!is_scalar($exam)) continue;
            $board = strtolower(trim((string)$exam));
            $board = ['cse' => 'civil-service', 'civilservice' => 'civil-service'][$board] ?? $board;
            if ($board !== '') $directBoardsByQuestion[$id][$board] = true;
        }
    }
}

$coverageByConcept = [];
foreach ($coverage as $row) {
    $concept = (string)($row['concept_id'] ?? '');
    $board = strtolower(trim((string)($row['board_id'] ?? '')));
    if ($concept !== '' && $board !== '' && QuestionReuseEligibilityResolver::validateCoverage($row) === []) {
        $coverageByConcept[$concept][$board] = $row;
    }
}

$requiredByQuestion = [];
foreach ($required as $row) {
    if (is_scalar($row['question_id'] ?? null)) {
        $requiredByQuestion[(string)$row['question_id']][] = $row;
    }
}

$equivalenceBySource = [];
foreach ($equivalence as $row) {
    if (QuestionReuseEligibilityResolver::validateEquivalence($row) === []) {
        $equivalenceBySource[(string)($row['source_concept_id'] ?? '')][] = $row;
    }
}

$questionsWithReuse = 0;
$autoEligible = 0;
$reviewRequired = 0;
$blockedUnknownDepth = 0;
$blockedRequiredConcepts = 0;
$blockedContext = 0;
$blockedNoCoverage = 0;
$blockedManualPolicy = 0;
$blockedInvalidPlacement = 0;
$sameConceptCandidates = 0;
$equivalenceCandidates = 0;
$derivedRelationships = 0;
$derivedQuestionIds = [];
$sameConceptCandidateKeys = [];
$equivalenceCandidateKeys = [];
$invalidCoverageRows = 0;
foreach ($coverage as $row) {
    if (QuestionReuseEligibilityResolver::validateCoverage($row) !== []) $invalidCoverageRows++;
}

foreach ($questions as $question) {
    $questionId = (string)($question['id'] ?? '');
    $reuse = $metadataByQuestion[$questionId] ?? [];
    if ($reuse !== []) $questionsWithReuse++;
    $directBoards = array_keys($directBoardsByQuestion[$questionId] ?? []);
    if ($directBoards === [] && is_array($question['taxonomy'] ?? null)) {
        $directBoards[] = strtolower(trim((string)($question['taxonomy']['board_id'] ?? '')));
    }
    $concept = (string)($question['taxonomy']['concept_id'] ?? '');
    $candidateBoards = array_values(array_diff(array_keys($coverageByConcept[$concept] ?? []), $directBoards));
    $sameConceptCandidates += count($candidateBoards);
    foreach ($candidateBoards as $board) $sameConceptCandidateKeys[$questionId . '|' . $board] = true;

    $equivalenceBoards = [];
    foreach ($equivalenceBySource[$concept] ?? [] as $relation) {
        $targetConcept = (string)($relation['target_concept_id'] ?? '');
        foreach (array_keys($coverageByConcept[$targetConcept] ?? []) as $board) {
            if (!in_array($board, $directBoards, true)) $equivalenceBoards[$board] = true;
        }
    }
    foreach (array_keys($equivalenceBoards) as $board) $equivalenceCandidateKeys[$questionId . '|' . $board] = true;
    $equivalenceCandidates += count($equivalenceBoards);

    if ($candidateBoards === [] && $equivalenceBoards === []) {
        $blockedNoCoverage++;
    }
    foreach ($candidateBoards as $board) {
        $row = $coverageByConcept[$concept][$board];
        if (($reuse['context_mode'] ?? 'UNKNOWN') !== 'NEUTRAL') {
            $blockedContext++;
        } elseif (($reuse['reuse_policy'] ?? 'REVIEW') === 'MANUAL_ONLY') {
            $blockedManualPolicy++;
        } elseif (($requiredByQuestion[$questionId] ?? []) !== []) {
            $blockedRequiredConcepts++;
        } elseif (($reuse['required_depth'] ?? 'UNKNOWN') === 'UNKNOWN'
            || ($row['max_depth'] ?? 'UNKNOWN') === 'UNKNOWN') {
            $blockedUnknownDepth++;
        } elseif (($reuse['reuse_policy'] ?? 'REVIEW') !== 'AUTO'
            || ($row['reuse_policy'] ?? '') !== 'AUTO'
            || ($row['status'] ?? '') !== 'VERIFIED') {
            $reviewRequired++;
        } else {
            $derivedRelationships++;
            $derivedQuestionIds[$questionId] = true;
            $autoEligible++;
        }
    }
}
$derivedQuestions = count($derivedQuestionIds);

echo json_encode([
    'questions_analyzed' => count($questions),
    'questions_with_reuse_metadata' => $questionsWithReuse,
    'auto_eligible_safe' => $autoEligible,
    'review_required' => $reviewRequired,
    'blocked_unknown_depth' => $blockedUnknownDepth,
    'blocked_required_concepts' => $blockedRequiredConcepts,
    'blocked_context' => $blockedContext,
    'blocked_no_coverage' => $blockedNoCoverage,
    'blocked_manual_policy' => $blockedManualPolicy,
    'blocked_invalid_placement' => $blockedInvalidPlacement,
    'same_concept_cross_board_candidates' => $sameConceptCandidates,
    'equivalence_based_candidates' => $equivalenceCandidates,
    'derived_new_board_relationships' => $derivedRelationships,
    'derived_new_questions_with_reuse' => $derivedQuestions,
    'invalid_coverage_rows' => $invalidCoverageRows,
    'duplicate_candidate_keys' => count($sameConceptCandidateKeys) + count($equivalenceCandidateKeys) - count(array_unique(array_merge(array_keys($sameConceptCandidateKeys), array_keys($equivalenceCandidateKeys)))),
    'production_auto_inheritance_enabled' => false,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
