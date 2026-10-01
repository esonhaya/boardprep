<?php

declare(strict_types=1);

namespace App\Services\RepositoryHealth\Engine;

use App\Services\Flashcard\FlashcardDerivationService;
use App\Services\Flashcard\FlashcardExclusionRegistry;
use App\Services\Question\QuestionEligibilityIndex;
use App\Services\Question\CalculationMetadataContract;
use App\Services\RepositoryHealth\DTO\HealthIssue;
use App\Services\RepositoryHealth\DTO\HealthReport;
use App\Services\RepositoryHealth\DTO\RepositoryContext;
use App\Services\RepositoryHealth\DTO\RepositoryStatistics;
use App\Services\RepositoryHealth\Readiness\SourceReadinessService;
use App\Services\Shared\ContentIntegrityService;

/**
 * Canonical developer-facing repository health facade.
 *
 * Existing structural services remain the sources of truth. This class only
 * composes their one-snapshot results into a stable PASS/WARN/FAIL/REVIEW
 * contract for CLI, CI, batch audits, and the future developer dashboard.
 */
final class RepositoryHealthEngine
{
    private static ?HealthReport $lastReport = null;

    public static function analyze(): HealthReport
    {
        return self::$lastReport = self::build(RepositoryContextFactory::create());
    }

    /** @param array<string, array> $collections */
    public static function analyzeCollections(array $collections): HealthReport
    {
        return self::$lastReport = self::build(RepositoryContextFactory::fromCollections($collections));
    }

    public static function lastReport(): ?HealthReport
    {
        return self::$lastReport;
    }

    private static function build(RepositoryContext $context): HealthReport
    {
        $collections = $context->collections;
        $questions = array_values(array_filter($collections['questions'] ?? [], 'is_array'));
        $questionById = [];
        foreach ($questions as $question) {
            $id = self::id($question);
            if ($id !== '') {
                $questionById[$id] = $question;
            }
        }

        $deriver = new FlashcardDerivationService(
            null,
            $collections['study-materials'] ?? [],
            $collections['study-question-links'] ?? [],
            $collections['flashcard-exclusions'] ?? []
        );
        $integrity = ContentIntegrityService::analyze(
            $collections,
            static fn(array $question): ?array => $deriver->derive($question),
            static fn(array $question): array => $deriver->derivationStatus($question)
        );

        $issues = [];
        self::addIntegrityIssues($issues, $integrity, $questionById);
        self::addEligibilityIssues($issues, $collections, $questionById);
        self::addExclusionIssues($issues, $collections, $questionById);
        self::addSourceStudyIssues($issues, $collections, $integrity, $questionById);
        self::addMetadataIssues($issues, $questions, $collections, $questionById);

        $boardSummaries = self::boardSummaries($questions, $collections, $issues);
        $readinessValidation = SourceReadinessService::validateCollections($collections, $boardSummaries);
        self::addSourceReadinessIssues($issues, $readinessValidation);
        $readinessByBoard = [];
        foreach ((array) ($readinessValidation['by_board'] ?? []) as $boardId => $boardValidation) {
            $readinessByBoard[(string) $boardId] = SourceReadinessService::summary(
                (array) ($boardValidation['records'] ?? []),
                $boardValidation
            );
        }
        $readinessSummary = $readinessByBoard['criminologist'] ?? SourceReadinessService::summary(
            $readinessValidation['records'] ?? [],
            $readinessValidation
        );
        foreach ($readinessByBoard as $boardId => $boardReadiness) {
            if (isset($boardSummaries[$boardId])) {
                $boardSummaries[$boardId]['source_readiness'] = $boardReadiness;
            }
        }
        $flashcardMetrics = self::flashcardMetrics($questions, $collections, $deriver);
        self::addFlashcardMetricIssues($issues, $flashcardMetrics, $questionById);
        foreach ($flashcardMetrics['by_board'] as $boardId => $metrics) {
            if (isset($boardSummaries[$boardId])) {
                $boardSummaries[$boardId]['flashcards'] = $metrics;
            }
        }
        $checks = self::checks($issues);
        $summary = self::summary($checks, $issues);
        $summary['flashcards'] = $flashcardMetrics['portfolio'];
        $summary['source_readiness'] = $readinessSummary;
        $summary['source_readiness_by_board'] = $readinessByBoard;

        $report = new HealthReport();
        $report->issues = self::sortIssues($issues);
        $report->checks = $checks;
        $report->summary = $summary;
        $report->overallStatus = $summary['overall_status'];
        $report->boardSummaries = $boardSummaries;
        $report->generatedAt = gmdate(DATE_ATOM);
        $report->exclusions = array_values(array_filter(
            $integrity['portfolio']['justified_exclusions'] ?? [],
            'is_array'
        ));
        $report->affectedIds = self::affectedIds($report->issues);
        $report->actionQueue = array_values(array_filter(
            array_map(static fn(HealthIssue $issue): array => $issue->toArray(), $report->issues),
            static fn(array $issue): bool => in_array($issue['status'], ['FAIL', 'WARN', 'REVIEW'], true)
        ));
        $report->healthScore = self::healthScore($report->issues);
        $report->statistics = self::statistics($questions, $report->issues, $boardSummaries);

        return $report;
    }

    /** @param array<int, HealthIssue> $issues @param array<string, mixed> $validation */
    private static function addSourceReadinessIssues(array &$issues, array $validation): void
    {
        foreach ((array) ($validation['errors'] ?? []) as $error) {
            if (!is_array($error)) continue;
            $leafId = (string) ($error['leaf_id'] ?? '');
            $evidence = is_array($error['evidence'] ?? null) ? $error['evidence'] : [];
            $evidence['board_id'] = (string) ($error['board_id'] ?? $evidence['board_id'] ?? '');
            $evidence['entity_type'] = 'taxonomy_leaf';
            self::issue(
                $issues,
                'FAIL',
                'SOURCE_READINESS',
                (string) ($error['code'] ?? 'SOURCE_READINESS.INVALID_RECORD'),
                $leafId,
                [],
                (string) ($error['message'] ?? 'Source-readiness contract failure.'),
                'Repair the permanent source-readiness record or resolve the authoritative study/source boundary.',
                $evidence
            );
        }
    }

    /** @param array<int, HealthIssue> $issues @param array<string, array> $questionById */
    private static function addIntegrityIssues(array &$issues, array $integrity, array $questionById): void
    {
        foreach ((array) ($integrity['failures']['structured_content'] ?? []) as $id => $details) {
            $questionId = is_int($id) && !is_array($details) ? (string) $details : (string) $id;
            $details = is_int($id) && !is_array($details) ? [] : (array) $details;
            if ($questionId === '') {
                continue;
            }
            foreach ($details as $detail) {
                $detail = is_array($detail) ? $detail : [];
                $rawCode = strtoupper((string) ($detail['code'] ?? 'OTHER'));
                self::issue(
                    $issues,
                    'FAIL',
                    'STRUCTURED_CONTENT',
                    'STRUCTURED_CONTENT_' . $rawCode,
                    $questionId,
                    $questionById,
                    'Active structured content violates the production schema.',
                    'Repair the structured block using the canonical producer contract.',
                    ['path' => $detail['path'] ?? '', 'validation_failure' => $detail['message'] ?? '']
                );
            }
        }

        $failureMap = [
            'malformed_questions' => ['QUESTION', 'MALFORMED_QUESTION', 'Question fails production validation.', 'Repair the question through QuestionService validation.'],
            'invalid_answers' => ['QUESTION', 'INVALID_ANSWER_KEY', 'Answer key is not represented by the choices.', 'Repair the answer key or choices.'],
            'invalid_taxonomy' => ['TAXONOMY', 'INVALID_TAXONOMY_REFERENCE', 'Question taxonomy reference is invalid.', 'Use an existing taxonomy path.'],
            'broken_assets' => ['STRUCTURED_CONTENT', 'BROKEN_ASSET_REFERENCE', 'Structured asset reference is broken.', 'Repair the asset reference or replace the asset.'],
            'missing_alt_text' => ['STRUCTURED_CONTENT', 'MISSING_REQUIRED_FIELD', 'Figure is missing required alternative text.', 'Add meaningful alternative text.'],
            'accidental_non_derivable' => ['FLASHCARDS', 'FLASHCARD_NONDERIVABLE', 'Eligible question cannot produce a valid production flashcard.', 'Repair derivation inputs or the card contract.'],
        ];
        foreach ($failureMap as $key => [$category, $code, $message, $action]) {
            foreach ((array) ($integrity['failures'][$key] ?? []) as $id => $value) {
                $questionId = is_int($id) && (is_scalar($value) || $value === null) ? (string) $value : (string) $id;
                if ($questionId === '') {
                    continue;
                }
                self::issue($issues, 'FAIL', $category, $code, $questionId, $questionById, $message, $action, ['source_value' => $value]);
            }
        }

        foreach ((array) ($integrity['failures']['card_key_collisions'] ?? []) as $key => $ids) {
            self::issue(
                $issues,
                'FAIL',
                'FLASHCARDS',
                'DUPLICATE_CARD_KEY',
                (string) ($ids[0] ?? ''),
                $questionById,
                'Multiple questions derive the same stable flashcard key.',
                'Repair the card identity inputs so every card key is unique.',
                ['card_key' => $key, 'question_ids' => array_values(array_map('strval', (array) $ids))]
            );
        }

        foreach ((array) ($integrity['failures']['duplicate_ids'] ?? []) as $id) {
            self::issue($issues, 'FAIL', 'REPOSITORY', 'DUPLICATE_RECORD_ID', (string) $id, $questionById, 'A repository record identifier is duplicated.', 'Assign one canonical identifier.', []);
        }
        foreach ((array) ($integrity['failures']['duplicate_question_ids'] ?? []) as $id) {
            self::issue($issues, 'FAIL', 'QUESTION', 'DUPLICATE_QUESTION_ID', (string) $id, $questionById, 'A question identifier is duplicated.', 'Assign one canonical question identifier.', []);
        }

        foreach ((array) ($integrity['boards'] ?? []) as $board) {
            if (!is_array($board)) {
                continue;
            }
            $boardId = (string) ($board['board_id'] ?? '');
            foreach ((array) ($board['defects'] ?? []) as $key => $values) {
                $map = [
                    'missing_eligibility' => ['ELIGIBILITY', 'MISSING_ELIGIBILITY', 'Canonical question has no production eligibility mapping.', 'Add a valid eligibility mapping.'],
                    'invalid_eligibility' => ['ELIGIBILITY', 'INVALID_ELIGIBILITY', 'Eligibility mapping references an unknown entity.', 'Repair the eligibility record.'],
                    'unlinked_questions' => ['TRACEABILITY', 'MISSING_STUDY_LINK', 'Canonical question has no study-material link.', 'Link the question to a supported study material.'],
                    'invalid_study_links' => ['SOURCE_STUDY', 'INVALID_STUDY_LINK', 'Study link points to missing or invalid content.', 'Repair the study link and source chain.'],
                    'duplicate_study_links' => ['SOURCE_STUDY', 'DUPLICATE_STUDY_LINK_KEY', 'Duplicate question/material link exists.', 'Keep one canonical link key.'],
                    'invalid_taxonomy_references' => ['TAXONOMY', 'INVALID_TAXONOMY_REFERENCE', 'Question taxonomy path is invalid.', 'Repair the taxonomy reference.'],
                    'orphan_taxonomy_nodes' => ['TAXONOMY', 'BROKEN_TAXONOMY_PARENT', 'Taxonomy node has a broken parent relationship.', 'Repair the taxonomy parent relationship.'],
                    'structured_content_failures' => ['STRUCTURED_CONTENT', 'STRUCTURED_CONTENT_INVALID', 'Structured-content contract failure is active.', 'Repair the structured content.'],
                ];
                if (!isset($map[$key])) {
                    continue;
                }
                [$category, $code, $message, $action] = $map[$key];
                foreach ((array) $values as $value) {
                    $entity = (string) $value;
                    self::issue($issues, 'FAIL', $category, $code, $entity, $questionById, $message, $action, ['board_id' => $boardId, 'record' => $value]);
                }
            }
        }
    }

    /** @param array<int, HealthIssue> $issues */
    private static function addEligibilityIssues(array &$issues, array $collections, array $questionById): void
    {
        $boards = [];
        foreach ((array) ($collections['boards'] ?? []) as $board) {
            if (is_array($board)) {
                $id = self::normalizeBoard($board['id'] ?? '');
                if ($id !== '') {
                    $boards[$id] = true;
                }
            }
        }
        $directKeys = [];
        foreach ((array) ($collections['question-eligibility'] ?? []) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $entryId = (string) ($entry['id'] ?? '<missing>');
            $questionIds = is_array($entry['question_ids'] ?? null) ? $entry['question_ids'] : [];
            $mappings = is_array($entry['question_mappings'] ?? null) ? $entry['question_mappings'] : [];
            foreach ($questionIds as $questionIdValue) {
                $questionId = trim((string) $questionIdValue);
                $mapping = is_array($mappings[$questionId] ?? null) ? $mappings[$questionId] : [];
                $examIds = is_array($mapping['exam_ids'] ?? null) ? $mapping['exam_ids'] : (is_array($entry['exam_ids'] ?? null) ? $entry['exam_ids'] : []);
                foreach ($examIds as $examId) {
                    $boardId = self::normalizeBoard($examId);
                    if ($boardId === '' || !isset($boards[$boardId]) || $questionId === '' || !isset($questionById[$questionId])) {
                        continue;
                    }
                    $key = $boardId . '|' . $questionId;
                    if (isset($directKeys[$key])) {
                        self::issue($issues, 'FAIL', 'ELIGIBILITY', 'DUPLICATE_EFFECTIVE_CANDIDATE', $entryId, $questionById, 'Multiple direct eligibility rows produce the same effective board/question candidate.', 'Keep one canonical effective eligibility mapping to avoid double weighting.', ['board_id' => $boardId, 'question_id' => $questionId, 'first_entry_id' => $directKeys[$key]]);
                    } else {
                        $directKeys[$key] = $entryId;
                    }
                }
            }
        }
        $reuseKeys = [];
        foreach ((array) ($collections['question-reuse-overrides'] ?? []) as $override) {
            if (!is_array($override)) {
                continue;
            }
            $overrideId = (string) ($override['id'] ?? '<missing>');
            $questionId = trim((string) ($override['question_id'] ?? ''));
            $boardId = self::normalizeBoard($override['board_id'] ?? '');
            if ($questionId === '' || !isset($questionById[$questionId]) || $boardId === '' || !isset($boards[$boardId])) {
                self::issue($issues, 'FAIL', 'ELIGIBILITY', 'INVALID_OVERRIDE_REFERENCE', $overrideId, $questionById, 'Eligibility override references an unknown question or board.', 'Repair the override reference against canonical question and board records.', ['question_id' => $questionId, 'board_id' => $boardId]);
                continue;
            }
            $key = $questionId . '|' . $boardId;
            if (isset($reuseKeys[$key])) {
                self::issue($issues, 'FAIL', 'ELIGIBILITY', 'DUPLICATE_ELIGIBILITY_OVERRIDE', $overrideId, $questionById, 'Multiple eligibility overrides target the same question and board.', 'Keep one canonical override for the question and board.', ['question_id' => $questionId, 'board_id' => $boardId, 'first_override_id' => $reuseKeys[$key]]);
            } else {
                $reuseKeys[$key] = $overrideId;
            }
        }
        $derivedKeys = [];
        foreach ((array) ($collections['concept-derived-eligibility'] ?? []) as $relationship) {
            if (!is_array($relationship) || ($relationship['record_type'] ?? '') !== 'RELATIONSHIP') {
                continue;
            }
            $relationshipId = (string) ($relationship['id'] ?? '<missing>');
            $questionId = trim((string) ($relationship['question_id'] ?? ''));
            $boardId = self::normalizeBoard($relationship['target_board'] ?? '');
            if ($questionId === '' || !isset($questionById[$questionId]) || $boardId === '' || !isset($boards[$boardId])) {
                self::issue($issues, 'FAIL', 'ELIGIBILITY', 'INVALID_DERIVED_ELIGIBILITY_REFERENCE', $relationshipId, $questionById, 'Derived eligibility relationship references an unknown question or board.', 'Repair the derived eligibility relationship.', ['question_id' => $questionId, 'board_id' => $boardId]);
                continue;
            }
            $key = $questionId . '|' . $boardId;
            if (isset($derivedKeys[$key])) {
                self::issue($issues, 'FAIL', 'ELIGIBILITY', 'DUPLICATE_EFFECTIVE_CANDIDATE', $relationshipId, $questionById, 'Multiple derived eligibility relationships target the same effective candidate.', 'Keep one canonical derived relationship to prevent double weighting.', ['question_id' => $questionId, 'board_id' => $boardId, 'first_relationship_id' => $derivedKeys[$key]]);
            } else {
                $derivedKeys[$key] = $relationshipId;
            }
        }
    }

    /** @param array<int, HealthIssue> $issues */
    private static function addExclusionIssues(array &$issues, array $collections, array $questionById): void
    {
        $registry = new FlashcardExclusionRegistry($collections['flashcard-exclusions'] ?? []);
        foreach ($registry->validateAll() as $result) {
            if (($result['valid'] ?? false) === true) {
                continue;
            }
            $record = (array) ($result['record'] ?? []);
            $id = (string) ($record['entity_id'] ?? '');
            self::issue($issues, 'FAIL', 'FLASHCARDS', 'INVALID_EXCLUSION_RECORD', $id, $questionById, 'Flashcard exclusion record does not satisfy the explicit exclusion contract.', 'Repair the exclusion record or derive the card.', ['errors' => $result['errors'] ?? []]);
        }
    }

    /** @param array<int, HealthIssue> $issues */
    private static function addSourceStudyIssues(array &$issues, array $collections, array $integrity, array $questionById): void
    {
        $materials = self::indexed($collections['study-materials'] ?? []);
        $sources = self::indexed($collections['sources'] ?? []);
        foreach ((array) ($collections['sources'] ?? []) as $source) {
            if (!is_array($source)) {
                continue;
            }
            if (trim((string) ($source['id'] ?? '')) === '' || trim((string) ($source['title'] ?? '')) === '' || trim((string) ($source['authority'] ?? '')) === '') {
                self::issue($issues, 'FAIL', 'SOURCE_STUDY', 'INVALID_SOURCE_RECORD', (string) ($source['id'] ?? ''), $questionById, 'Source record is missing a deterministic required field.', 'Repair the source record before relying on its study chain.', []);
            }
        }
        foreach ($materials as $materialId => $material) {
            if (!is_array($material['source_ids'] ?? null) || $material['source_ids'] === []) {
                self::issue($issues, 'FAIL', 'SOURCE_STUDY', 'MISSING_SOURCE_ID', (string) $materialId, $questionById, 'Study material has no source ID.', 'Attach at least one valid source record.', []);
            }
            foreach ((array) ($material['source_ids'] ?? []) as $sourceId) {
                if (!isset($sources[(string) $sourceId])) {
                    self::issue($issues, 'FAIL', 'SOURCE_STUDY', 'MISSING_SOURCE_REFERENCE', (string) $materialId, $questionById, 'Study material references a missing source.', 'Repair the study material source chain.', ['source_id' => $sourceId]);
                }
            }
        }
        foreach ((array) ($collections['study-question-links'] ?? []) as $link) {
            if (!is_array($link)) {
                continue;
            }
            $linkId = (string) ($link['id'] ?? '');
            $questionId = (string) ($link['question_id'] ?? '');
            $materialId = (string) ($link['material_id'] ?? '');
            if ($questionId === '' || !isset($questionById[$questionId])) {
                self::issue($issues, 'FAIL', 'SOURCE_STUDY', 'BROKEN_STUDY_LINK_QUESTION', $linkId, $questionById, 'Study link points to a missing question.', 'Repair the internal study link.', ['question_id' => $questionId]);
            }
            if ($materialId === '' || !isset($materials[$materialId])) {
                self::issue($issues, 'FAIL', 'SOURCE_STUDY', 'BROKEN_STUDY_LINK_MATERIAL', $linkId, $questionById, 'Study link points to a missing material.', 'Repair the internal study link.', ['material_id' => $materialId]);
            }
            foreach ((array) ($link['source_ids'] ?? []) as $sourceId) {
                if (!isset($sources[(string) $sourceId])) {
                    self::issue($issues, 'FAIL', 'SOURCE_STUDY', 'BROKEN_CARD_SOURCE', $linkId, $questionById, 'Study link points to a missing source.', 'Repair the source chain.', ['source_id' => $sourceId]);
                }
            }
        }
        foreach ((array) ($integrity['warnings']['standalone_study_materials'] ?? []) as $materialId) {
            self::issue($issues, 'REVIEW', 'SOURCE_STUDY', 'ORPHAN_STUDY_MATERIAL', (string) $materialId, $questionById, 'Study material is not linked to a question; editorial intent is not deterministic.', 'Review whether the material should remain standalone.', []);
        }
        foreach ((array) ($collections['content-fit-audits'] ?? []) as $audit) {
            if (!is_array($audit)) {
                continue;
            }
            $fit = strtoupper((string) ($audit['fit_classification'] ?? ''));
            if ($fit !== '' && !in_array($fit, ['CORRECT', 'VERIFIED'], true)) {
                $id = (string) ($audit['question_id'] ?? '');
                self::issue($issues, 'REVIEW', 'SOURCE_STUDY', 'SOURCE_SEMANTIC_FIT_REVIEW', $id, $questionById, 'Stored source/topic fit requires editorial review.', 'Review source, study material, and question fit.', ['fit_classification' => $fit]);
            }
        }
    }

    /** @param array<int, HealthIssue> $issues */
    private static function addMetadataIssues(array &$issues, array $questions, array $collections, array $questionById): void
    {
        $domains = self::indexed($collections['taxonomy/domains'] ?? []);
        $topics = self::indexed($collections['taxonomy/topics'] ?? []);
        $concepts = self::indexed($collections['taxonomy/concepts'] ?? []);
        foreach ($questions as $question) {
            $id = self::id($question);
            if ($id === '') {
                continue;
            }
            $calculation = CalculationMetadataContract::inspect($question);
            if (($calculation['valid'] ?? false) !== true) {
                self::issue($issues, 'FAIL', 'CALCULATION', 'CALCULATION_METADATA_CONTRADICTION', $id, $questionById, (string) ($calculation['message'] ?? 'Calculation metadata violates the production contract.'), 'Add the canonical calculation metadata or correct the flag.', [
                    'contract_code' => $calculation['code'],
                    'current_family' => $question['calculation_family'] ?? null,
                    'current_payload_fields' => is_array($question['calculation'] ?? null) ? array_keys($question['calculation']) : [],
                    'accepted_forms' => ['payload', 'legacy_family'],
                ]);
            }
            if (($question['version_sensitive'] ?? false) === true
                && trim((string) ($question['version_review_status'] ?? $question['currentness_review_status'] ?? '')) === '') {
                self::issue($issues, 'REVIEW', 'CURRENTNESS', 'VERSION_REVIEW_REQUIRED', $id, $questionById, 'Version-sensitive content has no review-status metadata.', 'Review currentness before relying on the item as authoritative.', []);
            }
            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            $concept = (string) ($taxonomy['concept_id'] ?? '');
            $topic = (string) ($taxonomy['topic_id'] ?? '');
            $domain = (string) ($taxonomy['domain_id'] ?? '');
            if ($concept !== '' && isset($concepts[$concept]) && (string) ($concepts[$concept]['topic_id'] ?? '') !== $topic) {
                self::issue($issues, 'FAIL', 'TAXONOMY', 'BROKEN_TAXONOMY_PARENT', $id, $questionById, 'Question concept is attached to the wrong topic.', 'Repair the taxonomy path.', ['concept_id' => $concept, 'topic_id' => $topic]);
            }
            if ($topic !== '' && isset($topics[$topic]) && (string) ($topics[$topic]['domain_id'] ?? '') !== $domain) {
                self::issue($issues, 'FAIL', 'TAXONOMY', 'BROKEN_TAXONOMY_PARENT', $id, $questionById, 'Question topic is attached to the wrong domain.', 'Repair the taxonomy path.', ['topic_id' => $topic, 'domain_id' => $domain]);
            }
            if ($domain !== '' && isset($domains[$domain]) && (string) ($domains[$domain]['subject_id'] ?? '') !== (string) ($taxonomy['subject_id'] ?? '')) {
                self::issue($issues, 'FAIL', 'TAXONOMY', 'BROKEN_TAXONOMY_PARENT', $id, $questionById, 'Question domain is attached to the wrong subject.', 'Repair the taxonomy path.', ['domain_id' => $domain]);
            }
        }
        foreach ((array) ($collections['board-concept-coverage'] ?? []) as $coverage) {
            if (!is_array($coverage)) {
                continue;
            }
            $coverageId = (string) ($coverage['id'] ?? ($coverage['board_id'] ?? '') . ':' . ($coverage['concept_id'] ?? ''));
            $conceptId = trim((string) ($coverage['concept_id'] ?? ''));
            if ($conceptId !== '' && !isset($concepts[$conceptId])) {
                self::issue($issues, 'FAIL', 'DEPTH_COVERAGE', 'INVALID_COVERAGE_REFERENCE', $coverageId, $questionById, 'Coverage row references an unknown concept.', 'Repair the coverage reference.', ['concept_id' => $conceptId]);
            }
            $maxDepth = $coverage['max_depth'] ?? null;
            if ($maxDepth === null || strtoupper(trim((string) $maxDepth)) === 'UNKNOWN' || $maxDepth === '') {
                self::issue($issues, 'WARN', 'DEPTH_COVERAGE', 'UNKNOWN_COVERAGE', $coverageId, $questionById, 'Coverage row has unknown maximum depth.', 'Review coverage depth without inferring semantic correctness.', []);
            } elseif (!in_array((int) $maxDepth, [1, 2, 3, 4, 5], true)) {
                self::issue($issues, 'FAIL', 'DEPTH_COVERAGE', 'INVALID_COVERAGE_REFERENCE', $coverageId, $questionById, 'Coverage row has an invalid depth reference.', 'Use a supported depth value or explicit UNKNOWN.', ['max_depth' => $maxDepth]);
            }
        }
    }

    /** @param array<int, HealthIssue> $issues */
    private static function boardSummaries(array $questions, array $collections, array &$issues): array
    {
        $boardIds = [];
        foreach ((array) ($collections['boards'] ?? []) as $board) {
            $id = self::normalizeBoard($board['id'] ?? '');
            if ($id !== '') {
                $boardIds[$id] = true;
            }
        }
        foreach ($questions as $question) {
            $id = self::questionBoard($question);
            if ($id !== '') {
                $boardIds[$id] = true;
            }
        }
        $index = new QuestionEligibilityIndex(
            $collections['question-eligibility'] ?? [],
            $collections['blueprints'] ?? [],
            $collections['concept-derived-eligibility'] ?? [],
            $collections['question-reuse-overrides'] ?? []
        );
        $coverageByBoard = [];
        foreach ((array) ($collections['board-concept-coverage'] ?? []) as $coverage) {
            if (!is_array($coverage)) {
                continue;
            }
            $coverageBoard = self::normalizeBoard($coverage['board_id'] ?? $coverage['board'] ?? '');
            $coverageConcept = trim((string) ($coverage['concept_id'] ?? ''));
            if ($coverageBoard !== '' && $coverageConcept !== '') {
                $coverageByBoard[$coverageBoard][$coverageConcept] = $coverage;
            }
        }
        $concepts = self::indexed($collections['taxonomy/concepts'] ?? []);
        $directByBoard = [];
        $derivedByBoard = [];
        foreach ((array) ($collections['question-eligibility'] ?? []) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $baseBoards = array_map('strval', is_array($entry['exam_ids'] ?? null) ? $entry['exam_ids'] : []);
            foreach ((array) ($entry['question_ids'] ?? []) as $questionId) {
                $questionId = trim((string) $questionId);
                if ($questionId === '') {
                    continue;
                }
                foreach ($baseBoards as $board) {
                    $board = self::normalizeBoard($board);
                    if ($board !== '') {
                        $directByBoard[$board][$questionId] = true;
                    }
                }
            }
        }
        foreach ((array) ($collections['concept-derived-eligibility'] ?? []) as $entry) {
            if (!is_array($entry) || ($entry['record_type'] ?? '') !== 'RELATIONSHIP') {
                continue;
            }
            $board = self::normalizeBoard($entry['target_board'] ?? '');
            $questionId = trim((string) ($entry['question_id'] ?? ''));
            if ($board !== '' && $questionId !== '') {
                $derivedByBoard[$board][$questionId] = true;
            }
        }
        $result = [];
        foreach (array_keys($boardIds) as $boardId) {
            $owned = [];
            $leafCounts = [];
            $effective = $directByBoard[$boardId] ?? [];
            foreach (array_diff_key($derivedByBoard[$boardId] ?? [], $effective) as $questionId => $_) {
                $effective[$questionId] = true;
            }
            $depths = [];
            foreach ($questions as $question) {
                $id = self::id($question);
                if ($id === '') {
                    continue;
                }
                if (self::questionBoard($question) === $boardId) {
                    $owned[$id] = true;
                    $leaf = self::leafId($question);
                    if ($leaf !== '') {
                        $leafCounts[$leaf] = ($leafCounts[$leaf] ?? 0) + 1;
                    }
                }
                $depth = self::depth($question);
                if ($depth !== null && self::questionBoard($question) === $boardId) {
                    $depths[] = $depth;
                }
            }
            if ($effective === [] && $owned !== []) {
                // Fixture contexts without eligibility collections still get
                // a useful native count; production storage always uses the
                // direct-plus-derived declaration above.
                $effective = $owned;
            }
            $runtime = [];
            try {
                $runtime = \QuestionPoolFilter::filter(
                    $questions,
                    new \SelectionRequest('*', null, [], 1, null, null, $boardId),
                    $index
                );
            } catch (\Throwable $exception) {
                self::issue($issues, 'FAIL', 'ELIGIBILITY', 'PRODUCTION_REACHABILITY_ERROR', $boardId, [], 'Production selection could not evaluate this board.', 'Repair the eligibility mapping or selection contract.', ['exception' => $exception->getMessage()]);
            }
            $runtimeIds = [];
            foreach ($runtime as $question) {
                $id = self::id($question);
                if ($id !== '') {
                    $runtimeIds[$id] = true;
                }
            }
            $expectedLeaves = array_keys($coverageByBoard[$boardId] ?? []);
            $structuralLeaves = [];
            foreach ($expectedLeaves as $expectedLeaf) {
                if (self::isStructuralLeaf((string) $expectedLeaf, $leafCounts, $concepts)) {
                    $structuralLeaves[$expectedLeaf] = true;
                }
            }
            $activeExpectedLeaves = array_values(array_diff($expectedLeaves, array_keys($structuralLeaves)));
            $activeLeaves = [];
            foreach ($activeExpectedLeaves as $activeExpectedLeaf) {
                $activeLeaves[$activeExpectedLeaf] = (int) ($leafCounts[$activeExpectedLeaf] ?? 0);
            }
            foreach ($leafCounts as $leaf => $count) {
                if (!isset($structuralLeaves[$leaf])) {
                    $activeLeaves[$leaf] = $count;
                }
            }
            $counts = array_values($activeLeaves);
            sort($counts);
            $summary = [
                'board_id' => $boardId,
                'canonical' => count($owned),
                'effective_declared' => count($effective),
                'runtime' => count($runtimeIds),
                'active_leaves' => count($activeLeaves),
                'structural_administrative_leaves' => count($structuralLeaves),
                'structural_administrative_leaf_ids' => array_keys($structuralLeaves),
                'zero' => count(array_filter($activeLeaves, static fn(int $count): bool => $count === 0)),
                'under5' => count(array_filter($counts, static fn(int $n): bool => $n < 5)),
                'under10' => count(array_filter($counts, static fn(int $n): bool => $n < 10)),
                'under20' => count(array_filter($counts, static fn(int $n): bool => $n < 20)),
                'min' => $counts === [] ? 0 : min($counts),
                'median' => self::median($counts),
                'leaf_counts' => $activeLeaves,
                'repeat_risk' => self::repeatRisk($counts, count($owned)),
                'depth_known' => count($depths),
                'depth_unknown' => max(0, count($owned) - count($depths)),
                'official_area_balance' => self::areaBalance($boardId, $questions, $collections),
                'concentration' => self::concentration($boardId, $questions, $activeLeaves),
            ];
            $summary['taxonomy_granularity'] = self::taxonomyGranularity($activeLeaves);
            if (($summary['taxonomy_granularity']['status'] ?? '') === 'REVIEW') {
                self::issue(
                    $issues,
                    'REVIEW',
                    'TAXONOMY',
                    'TAXONOMY_GRANULARITY_REVIEW',
                    $boardId,
                    [],
                    'Taxonomy shape is a coarse/masked signal that requires editorial review.',
                    'Review board subcompetencies before selecting a large content expansion.',
                    ['board_id' => $boardId] + $summary['taxonomy_granularity']
                );
            }
            foreach ($activeLeaves as $leaf => $count) {
                if ($count === 0) {
                    self::issue($issues, 'WARN', 'BALANCE', 'ACTIVE_ZERO_LEAF', (string) $leaf, [], 'Active exam leaf has no canonical questions.', 'Prioritize bounded content planning for this active leaf; do not treat structural parent nodes as zero pools.', ['board_id' => $boardId, 'count' => 0]);
                }
            }
            $thresholds = [5 => 'ACTIVE_UNDER5_LEAF', 10 => 'ACTIVE_UNDER10_LEAF', 20 => 'ACTIVE_UNDER20_LEAF'];
            foreach ($thresholds as $threshold => $code) {
                foreach ($activeLeaves as $leaf => $count) {
                    if ($count < $threshold) {
                        self::issue($issues, 'WARN', 'BALANCE', $code, (string) $leaf, [], 'Active exam leaf is thin at the current repository size.', 'Consider the leaf in future content planning; this is not a content-integrity failure.', ['board_id' => $boardId, 'count' => $count, 'threshold' => $threshold]);
                    }
                }
            }
            if ($summary['depth_unknown'] > 0) {
                self::issue($issues, 'WARN', 'DEPTH_COVERAGE', 'UNKNOWN_DEPTH', $boardId, [], 'Some active questions do not carry deterministic depth metadata.', 'Review depth metadata in a bounded audit; do not infer mathematical correctness.', ['board_id' => $boardId, 'unknown_count' => $summary['depth_unknown']]);
            }
            foreach ($summary['official_area_balance'] as $area) {
                if (($area['warning'] ?? false) === true) {
                    self::issue($issues, 'WARN', 'BALANCE', 'OFFICIAL_AREA_IMBALANCE', (string) ($area['area_id'] ?? ''), [], 'Actual repository share differs materially from the documented target weight.', 'Use the delta for future planning; do not delete valid content to rebalance.', $area + ['board_id' => $boardId]);
                }
            }
            foreach ($summary['concentration'] as $family => $metric) {
                if (($metric['status'] ?? '') !== 'WARN') {
                    continue;
                }
                self::issue(
                    $issues,
                    'WARN',
                    'BALANCE',
                    'CONCENTRATION_' . strtoupper($family),
                    $boardId,
                    [],
                    'A reliable repository dimension is concentrated in one family or leaf.',
                    'Use the concentration metric for future content planning; do not rewrite valid questions solely to rebalance.',
                    $metric + ['board_id' => $boardId]
                );
            }
            $result[$boardId] = $summary;
        }
        ksort($result);
        return $result;
    }

    /** @param array<int, HealthIssue> $issues */
    private static function addFlashcardMetricIssues(array &$issues, array $metrics, array $questionById): void
    {
        foreach ((array) ($metrics['portfolio']['invalid_card_key_ids'] ?? []) as $id) {
            self::issue($issues, 'FAIL', 'FLASHCARDS', 'INVALID_CARD_KEY', (string) $id, $questionById, 'Derived flashcard has an invalid stable card key.', 'Repair the card identity inputs and preserve the canonical key format.', []);
        }
        foreach ((array) ($metrics['portfolio']['malformed_card_ids'] ?? []) as $id) {
            self::issue($issues, 'FAIL', 'FLASHCARDS', 'MALFORMED_CARD', (string) $id, $questionById, 'Production flashcard derivation produced a malformed card.', 'Repair the derivation inputs or card contract.', []);
        }
        foreach ((array) ($metrics['portfolio']['broken_card_source_ids'] ?? []) as $id) {
            self::issue($issues, 'FAIL', 'FLASHCARDS', 'BROKEN_CARD_SOURCE', (string) $id, $questionById, 'Derived flashcard has no valid linked study/source chain.', 'Repair the study material and source linkage.', []);
        }
    }

    /** @return array{portfolio: array, by_board: array<string, array>} */
    private static function flashcardMetrics(array $questions, array $collections, FlashcardDerivationService $deriver): array
    {
        $portfolio = ['eligible' => 0, 'derivable' => 0, 'justified_exclusion' => 0, 'nonderivable_defect' => 0, 'duplicate_card_key' => 0, 'malformed_card' => 0, 'broken_card_source' => 0, 'invalid_card_key' => 0, 'invalid_card_key_ids' => [], 'malformed_card_ids' => [], 'broken_card_source_ids' => []];
        $byBoard = [];
        $keys = [];
        foreach ($questions as $question) {
            $questionId = self::id($question);
            $board = self::questionBoard($question);
            $byBoard[$board] ??= ['eligible' => 0, 'derivable' => 0, 'justified_exclusion' => 0, 'nonderivable_defect' => 0, 'duplicate_card_key' => 0, 'malformed_card' => 0, 'broken_card_source' => 0, 'invalid_card_key' => 0];
            $status = $deriver->derivationStatus($question);
            if (($status['eligible'] ?? false) !== true) {
                continue;
            }
            $portfolio['eligible']++;
            $byBoard[$board]['eligible']++;
            $card = $deriver->derive($question);
            if (is_array($card)) {
                $portfolio['derivable']++;
                $byBoard[$board]['derivable']++;
                $key = (string) ($card['card_key'] ?? '');
                if ($key !== '') {
                    $keys[$key][] = [$questionId, $board];
                }
                $errors = \App\Services\Flashcard\FlashcardCardValidator::validate($card);
                if ($errors !== []) {
                    $portfolio['malformed_card']++;
                    $byBoard[$board]['malformed_card']++;
                    $portfolio['malformed_card_ids'][] = $questionId;
                    if (in_array('INVALID_CARD_KEY', $errors, true)) {
                        $portfolio['invalid_card_key']++;
                        $portfolio['invalid_card_key_ids'][] = $questionId;
                        $byBoard[$board]['invalid_card_key']++;
                    }
                    if (in_array('BROKEN_CARD_SOURCE', $errors, true)) {
                        $portfolio['broken_card_source']++;
                        $byBoard[$board]['broken_card_source']++;
                        $portfolio['broken_card_source_ids'][] = $questionId;
                    }
                }
            } elseif (($status['exclusion'] ?? null) !== null) {
                $portfolio['justified_exclusion']++;
                $byBoard[$board]['justified_exclusion']++;
            } else {
                $portfolio['nonderivable_defect']++;
                $byBoard[$board]['nonderivable_defect']++;
            }
        }
        foreach ($keys as $key => $records) {
            if (count($records) <= 1) {
                continue;
            }
            $portfolio['duplicate_card_key']++;
            foreach ($records as [, $board]) {
                $byBoard[$board]['duplicate_card_key']++;
            }
        }
        return ['portfolio' => $portfolio, 'by_board' => $byBoard];
    }

    private static function checks(array $issues): array
    {
        $definitions = RepositoryHealthCheckCatalog::definitions();
        $checks = [];
        foreach ($definitions as $code => $definition) {
            $category = $definition['category'];
            $matching = array_values(array_filter(
                $issues,
                static fn(HealthIssue $issue): bool => self::issueBelongsToCheck($issue, $code, $category)
            ));
            $status = 'PASS';
            foreach ($matching as $issue) {
                if ($issue->status === 'FAIL') {
                    $status = 'FAIL';
                    break;
                }
                if ($issue->status === 'REVIEW' && $status === 'PASS') {
                    $status = 'REVIEW';
                } elseif ($issue->status === 'WARN' && $status === 'PASS') {
                    $status = 'WARN';
                }
            }
            $checks[$code] = [
                'check_code' => $code,
                'category' => $category,
                'owner' => $definition['owner'],
                'default_severity' => $definition['default_severity'],
                'entity_type' => $definition['entity_type'],
                'status' => $status,
                'description' => $definition['description'],
                'fix_guidance' => $definition['fix_guidance'],
                'issue_count' => count($matching),
                'affected_ids' => self::issueIds($matching),
            ];
        }
        return $checks;
    }

    private static function issueBelongsToCheck(HealthIssue $issue, string $checkCode, string $category): bool
    {
        if ($category !== 'FLASHCARDS') {
            return $issue->category === $category;
        }

        return match ($checkCode) {
            'FLASHCARD_DERIVABILITY' => in_array($issue->code, ['FLASHCARD_NONDERIVABLE'], true),
            'FLASHCARD_EXCLUSIONS' => in_array($issue->code, ['INVALID_EXCLUSION_RECORD'], true),
            'CARD_KEYS' => in_array($issue->code, ['DUPLICATE_CARD_KEY', 'INVALID_CARD_KEY', 'MALFORMED_CARD', 'BROKEN_CARD_SOURCE'], true),
            default => false,
        };
    }

    /** @param array<int, HealthIssue> $issues */
    private static function summary(array $checks, array $issues): array
    {
        $counts = ['PASS' => 0, 'WARN' => 0, 'FAIL' => 0, 'REVIEW' => 0];
        foreach ($checks as $check) {
            $status = (string) ($check['status'] ?? 'PASS');
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }
        $overall = $counts['FAIL'] > 0 ? 'FAIL' : ($counts['REVIEW'] > 0 ? 'REVIEW' : ($counts['WARN'] > 0 ? 'WARN' : 'PASS'));
        $breakdown = [
            'board' => [],
            'category' => [],
            'entity_type' => [],
            'check_code' => [],
        ];
        foreach ($issues as $issue) {
            foreach ([
                'board' => $issue->boardId ?? 'UNSCOPED',
                'category' => $issue->category,
                'entity_type' => $issue->entityType ?? 'UNSPECIFIED',
                'check_code' => $issue->code,
            ] as $dimension => $value) {
                $value = (string) $value;
                $breakdown[$dimension][$value] = ($breakdown[$dimension][$value] ?? 0) + 1;
            }
        }
        foreach ($breakdown as &$values) {
            ksort($values);
        }
        unset($values);
        $issueCounts = ['FAIL' => 0, 'WARN' => 0, 'REVIEW' => 0, 'PASS' => 0];
        foreach ($issues as $issue) {
            $issueCounts[$issue->status] = ($issueCounts[$issue->status] ?? 0) + 1;
        }
        return [
            'overall_status' => $overall,
            'pass_count' => $counts['PASS'],
            'warn_count' => $counts['WARN'],
            'fail_count' => $counts['FAIL'],
            'review_count' => $counts['REVIEW'],
            'issue_count' => count($issues),
            'pass_entity_count' => $issueCounts['PASS'],
            'warn_entity_count' => $issueCounts['WARN'],
            'fail_entity_count' => $issueCounts['FAIL'],
            'review_entity_count' => $issueCounts['REVIEW'],
            'breakdown' => $breakdown,
        ];
    }

    /** @param array<int, HealthIssue> $issues */
    private static function sortIssues(array $issues): array
    {
        usort($issues, static fn(HealthIssue $a, HealthIssue $b): int => [
            $a->status,
            $a->category,
            $a->code,
            (string) ($a->entityId ?? ''),
        ] <=> [
            $b->status,
            $b->category,
            $b->code,
            (string) ($b->entityId ?? ''),
        ]);
        return $issues;
    }

    /** @param array<int, HealthIssue> $issues @param array<string, array> $questionById */
    private static function issue(
        array &$issues,
        string $status,
        string $category,
        string $code,
        string $entityId,
        array $questionById,
        string $message,
        string $action,
        array $evidence
    ): void {
        $issue = new HealthIssue();
        $issue->status = $status;
        $issue->severity = match ($status) {
            'FAIL' => 'error',
            'WARN' => 'warning',
            'REVIEW' => 'review',
            default => 'info',
        };
        $issue->priority = $status === 'FAIL' ? 'high' : ($status === 'REVIEW' ? 'review' : 'normal');
        $issue->validator = 'RepositoryHealthEngine';
        $issue->category = $category;
        $issue->code = $code;
        $issue->message = $message;
        $issue->recommendation = $action;
        $issue->suggestedAction = $action;
        $issue->sourceCheck = $category;
        $issue->repairable = $status === 'FAIL';
        $issue->entityType = isset($questionById[$entityId])
            ? 'question'
            : (isset($evidence['entity_type'])
                ? (string) $evidence['entity_type']
                : ($category === 'TAXONOMY' ? 'taxonomy_leaf' : 'repository_record'));
        $issue->entityId = $entityId !== '' ? $entityId : null;
        $issue->boardId = isset($questionById[$entityId]) ? self::questionBoard($questionById[$entityId]) : (isset($evidence['board_id']) ? self::normalizeBoard($evidence['board_id']) : null);
        $issue->evidence = $evidence;
        $issue->metadata = $evidence;
        $issues[] = $issue;
    }

    /** @param array<int, HealthIssue> $issues */
    private static function statistics(array $questions, array $issues, array $boards): RepositoryStatistics
    {
        $statistics = new RepositoryStatistics();
        $statistics->totalQuestions = count($questions);
        foreach ($issues as $issue) {
            $statistics->totalIssues++;
            if ($issue->status === 'FAIL') {
                $statistics->errors++;
            } elseif ($issue->status === 'WARN') {
                $statistics->warnings++;
            } else {
                $statistics->infos++;
            }
            $statistics->issuesByCategory[$issue->category] = ($statistics->issuesByCategory[$issue->category] ?? 0) + 1;
            $statistics->issuesByValidator[$issue->validator] = ($statistics->issuesByValidator[$issue->validator] ?? 0) + 1;
        }
        foreach ($questions as $question) {
            foreach (['difficulty' => 'questionsPerDifficulty', 'status' => 'questionsPerStatus'] as $field => $property) {
                $value = (string) ($question[$field] ?? 'Unknown');
                $statistics->{$property}[$value] = ($statistics->{$property}[$value] ?? 0) + 1;
            }
            $board = self::questionBoard($question);
            if ($board !== '') {
                $statistics->questionsPerBoard[$board] = ($statistics->questionsPerBoard[$board] ?? 0) + 1;
            }
        }
        $statistics->boards = $boards;
        foreach ($boards as $board) {
            $statistics->totals['canonical'] += (int) ($board['canonical'] ?? 0);
            $statistics->totals['effective_declared'] += (int) ($board['effective_declared'] ?? 0);
            $statistics->totals['runtime'] += (int) ($board['runtime'] ?? 0);
        }
        return $statistics;
    }

    /** @param array<int, HealthIssue> $issues */
    private static function healthScore(array $issues): float
    {
        $score = 100.0;
        foreach ($issues as $issue) {
            $score -= match ($issue->status) {
                'FAIL' => 5.0,
                'WARN' => 1.0,
                'REVIEW' => .25,
                default => 0.0,
            };
        }
        return max(0.0, round($score, 1));
    }

    private static function repeatRisk(array $counts, int $total): string
    {
        if ($counts === [] || $total === 0) {
            return 'UNKNOWN';
        }
        $under10 = count(array_filter($counts, static fn(int $count): bool => $count < 10));
        $maxShare = max($counts) / $total;
        if ($under10 > count($counts) / 2 || $maxShare > .35) {
            return 'HIGH';
        }
        if ($under10 > 0 || count(array_filter($counts, static fn(int $count): bool => $count < 20)) > 0 || $maxShare > .20) {
            return 'MODERATE';
        }
        return 'LOW';
    }

    private static function median(array $values): int|float
    {
        if ($values === []) {
            return 0;
        }
        sort($values);
        $middle = intdiv(count($values), 2);
        return count($values) % 2 === 1 ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;
    }

    private static function areaBalance(string $boardId, array $questions, array $collections): array
    {
        $blueprint = null;
        foreach ((array) ($collections['blueprints'] ?? []) as $candidate) {
            if (is_array($candidate) && self::normalizeBoard($candidate['board_id'] ?? $candidate['board'] ?? '') === $boardId && ($candidate['status'] ?? 'active') === 'active') {
                $weights = is_array($candidate['official_weights'] ?? null) ? $candidate['official_weights'] : [];
                if ($weights !== [] && (($candidate['official_weighting'] ?? false) === true)) {
                    $blueprint = $candidate;
                    break;
                }
            }
        }
        if ($blueprint === null) {
            return [];
        }
        $weights = $blueprint['official_weights'];
        $areaSources = [];
        foreach ((array) ($blueprint['sections'] ?? []) as $section) {
            if (!is_array($section)) {
                continue;
            }
            $sectionId = (string) ($section['id'] ?? '');
            $target = $section['target_percent'] ?? ($weights[$sectionId] ?? null);
            if ($sectionId === '' || !is_numeric($target)) {
                continue;
            }
            $areaSources[$sectionId] = (string) ($section['domain_id'] ?? $sectionId);
        }
        if ($areaSources === []) {
            foreach (array_keys($weights) as $area) {
                $areaSources[(string) $area] = (string) $area;
            }
        }
        $counts = array_fill_keys(array_keys($areaSources), 0);
        foreach ($questions as $question) {
            if (self::questionBoard($question) !== $boardId) {
                continue;
            }
            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            $area = (string) ($taxonomy['domain_id'] ?? $taxonomy['subject_id'] ?? '');
            foreach ($areaSources as $outputArea => $sourceArea) {
                if ($area === $sourceArea) {
                    $counts[$outputArea]++;
                    break;
                }
            }
        }
        $total = array_sum($counts);
        $result = [];
        foreach ($areaSources as $area => $sourceArea) {
            $target = $blueprint['sections'] ?? [];
            foreach ($target as $section) {
                if (is_array($section) && (string) ($section['id'] ?? '') === (string) $area && is_numeric($section['target_percent'] ?? null)) {
                    $target = $section['target_percent'];
                    break;
                }
            }
            if (is_array($target)) {
                $target = $weights[(string) $area] ?? 0;
            }
            $actual = $total > 0 ? ($counts[(string) $area] ?? 0) / $total * 100 : 0;
            $delta = $actual - (float) $target;
            $result[] = ['area_id' => (string) $area, 'actual_share' => round($actual, 2), 'target_share' => (float) $target, 'delta' => round($delta, 2), 'warning' => abs($delta) > 10];
        }
        return $result;
    }

    /**
     * Expose concentration without treating semantic family labels as proof of
     * quality.  A family metric is only populated when the record actually
     * carries that field; absent metadata is reported as unavailable rather
     * than inferred from wording or taxonomy.
     *
     * @param array<string, int> $activeLeaves
     * @return array<string, array<string, mixed>>
     */
    private static function concentration(string $boardId, array $questions, array $activeLeaves): array
    {
        $result = [];
        foreach (['scenario_family', 'misconception_family', 'calculation_family'] as $field) {
            $counts = [];
            foreach ($questions as $question) {
                if (self::questionBoard($question) !== $boardId) {
                    continue;
                }
                $value = trim((string) ($question[$field] ?? ''));
                if ($value !== '') {
                    $counts[$value] = ($counts[$value] ?? 0) + 1;
                }
            }
            $result[$field] = self::concentrationMetric($counts, $field, 'question_field');
        }

        $result['single_leaf'] = self::concentrationMetric($activeLeaves, 'single_leaf', 'taxonomy');
        return $result;
    }

    /** @param array<string, int> $counts */
    private static function concentrationMetric(array $counts, string $dimension, string $source): array
    {
        if ($counts === []) {
            return [
                'available' => false,
                'status' => 'REVIEW',
                'dimension' => $dimension,
                'source' => $source,
                'observed' => 0,
                'distinct' => 0,
                'top_value' => null,
                'top_count' => 0,
                'top_share' => null,
                'warning_threshold' => 0.35,
                'note' => 'No reliable field-level metadata is present; semantic concentration is not inferred.',
            ];
        }

        arsort($counts);
        $total = array_sum($counts);
        $topCount = (int) reset($counts);
        $topValue = (string) key($counts);
        $topShare = $total > 0 ? $topCount / $total : 0.0;

        return [
            'available' => true,
            'status' => $topShare > 0.35 ? 'WARN' : 'PASS',
            'dimension' => $dimension,
            'source' => $source,
            'observed' => $total,
            'distinct' => count($counts),
            'top_value' => $topValue,
            'top_count' => $topCount,
            'top_share' => round($topShare, 4),
            'warning_threshold' => 0.35,
        ];
    }

    /**
     * A planning signal only: a small active taxonomy or generic concept
     * leaves may indicate a masked/coarse board model, but does not prove
     * semantic taxonomy quality. It therefore produces REVIEW, not FAIL.
     *
     * @param array<string, int> $activeLeaves
     * @return array<string, mixed>
     */
    private static function taxonomyGranularity(array $activeLeaves): array
    {
        $leafIds = array_keys($activeLeaves);
        $generic = array_values(array_filter($leafIds, static fn(string $id): bool => preg_match('/(?:^|-)concept$/i', $id) === 1));
        if (count($leafIds) <= 4) {
            return [
                'status' => 'REVIEW',
                'signal' => 'SEVERELY_COARSE_OR_MASKED',
                'active_leaf_count' => count($leafIds),
                'generic_leaf_count' => count($generic),
                'note' => 'Very few active leaves are available; editorial taxonomy review is required.',
            ];
        }
        if ($leafIds !== [] && count($generic) / count($leafIds) >= .8) {
            return [
                'status' => 'REVIEW',
                'signal' => 'COARSE_OR_MASKED',
                'active_leaf_count' => count($leafIds),
                'generic_leaf_count' => count($generic),
                'note' => 'Most active leaf identifiers use a generic concept shape; review subcompetency granularity.',
            ];
        }
        return [
            'status' => 'PASS',
            'signal' => 'NO_CHEAP_COARSENESS_SIGNAL',
            'active_leaf_count' => count($leafIds),
            'generic_leaf_count' => count($generic),
            'note' => 'No cheap deterministic coarseness signal was found; this is not a semantic quality proof.',
        ];
    }

    private static function depth(array $question): ?int
    {
        foreach ([$question['depth'] ?? null, $question['required_depth'] ?? null, is_array($question['reuse'] ?? null) ? ($question['reuse']['required_depth'] ?? null) : null] as $value) {
            if (is_int($value) || (is_string($value) && ctype_digit(trim($value)))) {
                $depth = (int) $value;
                if ($depth > 0) {
                    return $depth;
                }
            }
        }
        return null;
    }

    private static function leafId(array $question): string
    {
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        return trim((string) ($taxonomy['subcompetency_id'] ?? $taxonomy['concept_id'] ?? $taxonomy['topic_id'] ?? ''));
    }

    private static function questionBoard(array $question): string
    {
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        return self::normalizeBoard($taxonomy['board_id'] ?? $question['board'] ?? '');
    }

    private static function normalizeBoard(mixed $value): string
    {
        $id = strtolower(trim((string) $value));
        return ['cse' => 'civil-service', 'cle' => 'criminologist', 'nle' => 'nursing', 'ple' => 'psychometrician'][$id] ?? $id;
    }

    private static function id(array $record): string
    {
        return is_scalar($record['id'] ?? null) ? trim((string) $record['id']) : '';
    }

    private static function conceptParentId(array $concept): string
    {
        foreach (['parent_concept_id', 'batch633', 'batch634', 'batch635', 'batch636', 'batch637_foundation'] as $field) {
            if ($field === 'parent_concept_id') {
                $value = $concept[$field] ?? null;
            } else {
                $metadata = is_array($concept[$field] ?? null) ? $concept[$field] : [];
                $value = $metadata['parent_concept_id'] ?? null;
            }
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }
        return '';
    }

    /**
     * A coverage row can retain a legacy umbrella concept after its active
     * subcompetency has been migrated.  Most such rows declare the parent
     * explicitly; a small number of historical taxonomy records only retain
     * the shared name and B633 metadata.  Treat the latter as structural only
     * when it is empty in the current board and the replacement evidence is
     * deterministic, so a genuinely active legacy leaf is not hidden.
     *
     * @param array<string, int> $leafCounts
     * @param array<string, array> $concepts
     */
    private static function isStructuralLeaf(string $expectedLeaf, array $leafCounts, array $concepts): bool
    {
        foreach ($concepts as $concept) {
            if (self::conceptParentId($concept) === $expectedLeaf) {
                return true;
            }
        }

        if (($leafCounts[$expectedLeaf] ?? 0) !== 0 || !preg_match('/(?:^|-)concept-/', $expectedLeaf)) {
            return false;
        }

        $legacy = $concepts[$expectedLeaf] ?? null;
        if (!is_array($legacy)) {
            return false;
        }
        $legacyName = self::normalizedConceptName($legacy['name'] ?? '');
        if ($legacyName === '') {
            return false;
        }

        foreach ($concepts as $conceptId => $concept) {
            if (!is_array($concept) || !str_starts_with((string) $conceptId, 'b633-')) {
                continue;
            }
            $metadata = is_array($concept['batch633'] ?? null) ? $concept['batch633'] : [];
            if ($metadata === [] || self::normalizedConceptName($concept['name'] ?? '') !== $legacyName) {
                continue;
            }
            return true;
        }

        return false;
    }

    private static function normalizedConceptName(mixed $name): string
    {
        $value = strtolower(trim((string) $name));
        $value = preg_replace('/\bapplications?\b/', '', $value) ?? $value;
        return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
    }

    /** @return array<string, array> */
    private static function indexed(array $records): array
    {
        $result = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }
            $id = self::id($record);
            if ($id !== '') {
                $result[$id] = $record;
            }
        }
        return $result;
    }

    /** @param array<int, HealthIssue> $issues @return string[] */
    private static function affectedIds(array $issues): array
    {
        $ids = [];
        foreach ($issues as $issue) {
            if ($issue->entityId !== null) {
                $ids[(string) $issue->entityId] = true;
            }
        }
        return array_keys($ids);
    }

    /** @param array<int, HealthIssue> $issues @return string[] */
    private static function issueIds(array $issues): array
    {
        $ids = [];
        foreach ($issues as $issue) {
            if ($issue->entityId !== null) {
                $ids[(string) $issue->entityId] = true;
            }
        }
        return array_keys($ids);
    }
}
