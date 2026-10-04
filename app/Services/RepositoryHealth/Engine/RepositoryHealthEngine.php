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
use App\Services\RepositoryHealth\CivilService\CivilServiceCoverageModel;
use App\Services\RepositoryHealth\Readiness\SourceReadinessService;
use App\Services\RepositoryHealth\Readiness\ReadinessClosureService;
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

    public static function analyze(bool $profile = false): HealthReport
    {
        $profiler = $profile ? new RepositoryHealthProfiler() : null;
        $context = $profiler === null
            ? RepositoryContextFactory::create()
            : $profiler->measure('shared_initialization.context_and_repository_loads', static fn(): RepositoryContext => RepositoryContextFactory::create($profiler));
        return self::$lastReport = self::build($context, $profiler);
    }

    /** @param array<string, array> $collections */
    public static function analyzeCollections(array $collections, bool $profile = false): HealthReport
    {
        $profiler = $profile ? new RepositoryHealthProfiler() : null;
        return self::$lastReport = self::build(RepositoryContextFactory::fromCollections($collections), $profiler);
    }

    public static function lastReport(): ?HealthReport
    {
        return self::$lastReport;
    }

    /**
     * Return one board's readiness view from an already-built health snapshot.
     * This is intentionally a projection over the global indexes; it does not
     * initialize or rescan repository collections for each leaf.
     *
     * @return array<string, mixed>
     */
    public static function readinessAudit(string $boardId, ?HealthReport $report = null): array
    {
        $boardId = self::normalizeBoard($boardId);
        $report ??= self::$lastReport ?? self::analyze();
        $board = is_array($report->boardSummaries[$boardId] ?? null) ? $report->boardSummaries[$boardId] : null;
        $readiness = is_array($report->summary['source_readiness_by_board'][$boardId] ?? null)
            ? $report->summary['source_readiness_by_board'][$boardId]
            : null;
        $issues = [];
        foreach ($report->issues as $issue) {
            $data = $issue->toArray();
            $evidence = is_array($data['evidence'] ?? null) ? $data['evidence'] : [];
            if (($data['board_id'] ?? null) === $boardId || ($evidence['board_id'] ?? null) === $boardId || (($data['entity_id'] ?? '') === $boardId && ($data['category'] ?? '') !== '')) {
                $issues[] = $data;
            }
        }
        return [
            'audit_level' => 'DEEP_READINESS_AUDIT',
            'board_id' => $boardId,
            'board_summary' => $board,
            'readiness' => $readiness,
            'issues' => $issues,
            'deterministic_status' => [
                'fail_count' => count(array_filter($issues, static fn(array $issue): bool => ($issue['status'] ?? '') === 'FAIL')),
                'warn_count' => count(array_filter($issues, static fn(array $issue): bool => ($issue['status'] ?? '') === 'WARN')),
                'review_count' => count(array_filter($issues, static fn(array $issue): bool => ($issue['status'] ?? '') === 'REVIEW')),
            ],
            'portfolio_state' => self::portfolioOperationalState($boardId, $board, $readiness, []),
            'semantic_review_boundary' => 'Stored editorial metadata is summarized; proposition novelty, source meaning, question equivalence, and visual suitability are not inferred.',
        ];
    }

    /**
     * Project the already-built health snapshot into one compact row per
     * board.  This is deliberately an index projection: it does not run a
     * readiness audit once per board or re-read any collection.
     *
     * @param string[]|null $boardIds
     * @return array{audit_level:string,boards:array<int,array<string,mixed>>,semantic_review_boundary:string}
     */
    public static function portfolioReadinessAudit(?array $boardIds = null, ?HealthReport $report = null): array
    {
        $report ??= self::$lastReport ?? self::analyze();
        $available = array_keys($report->boardSummaries);
        $requested = $boardIds === null ? $available : array_map([self::class, 'normalizeBoard'], $boardIds);
        $requested = array_values(array_unique(array_filter($requested, static fn(string $id): bool => $id !== '')));
        sort($requested, SORT_STRING);

        $issueCounts = [];
        foreach ($report->issues as $issue) {
            $data = $issue->toArray();
            $evidence = is_array($data['evidence'] ?? null) ? $data['evidence'] : [];
            $boardId = self::normalizeBoard($data['board_id'] ?? $evidence['board_id'] ?? '');
            if ($boardId === '' || !in_array($boardId, $requested, true)) continue;
            $status = strtoupper((string) ($data['status'] ?? ''));
            $issueCounts[$boardId][$status] = ($issueCounts[$boardId][$status] ?? 0) + 1;
        }

        $rows = [];
        foreach ($requested as $boardId) {
            $board = is_array($report->boardSummaries[$boardId] ?? null)
                ? $report->boardSummaries[$boardId]
                : [];
            $readiness = is_array($report->summary['source_readiness_by_board'][$boardId] ?? null)
                ? $report->summary['source_readiness_by_board'][$boardId]
                : null;
            $readinessSummary = $readiness;
            if (is_array($readinessSummary)) unset($readinessSummary['records']);
            $recordCount = is_array($readiness) ? (int) ($readiness['record_count'] ?? 0) : 0;
            $activeLeaves = array_key_exists('active_leaves', $board) ? (int) $board['active_leaves'] : null;
            $operationalState = self::portfolioOperationalState($boardId, $board, $readinessSummary, $issueCounts[$boardId] ?? []);
            $rows[] = [
                'board_id' => $boardId,
                'native' => array_key_exists('canonical', $board) ? (int) $board['canonical'] : null,
                'effective' => array_key_exists('effective_declared', $board) ? (int) $board['effective_declared'] : null,
                'runtime' => array_key_exists('runtime', $board) ? (int) $board['runtime'] : null,
                'active_leaves' => $activeLeaves,
                'depth_known' => array_key_exists('depth_known', $board) ? (int) $board['depth_known'] : null,
                'depth_unknown' => array_key_exists('depth_unknown', $board) ? (int) $board['depth_unknown'] : null,
                'taxonomy_granularity_status' => $board['taxonomy_granularity'] ?? null,
                'repeat_risk' => $board['repeat_risk'] ?? null,
                'readiness_model_status' => $readiness === null
                    ? 'LEGACY_NO_READINESS_MODEL'
                    : ($recordCount === $activeLeaves ? 'FULL_READINESS_MODEL' : 'PARTIAL_READINESS_MODEL'),
                'operational_state' => $operationalState,
                'closure_state' => is_array($readinessSummary) ? ($readinessSummary['closure_state'] ?? null) : null,
                'high_value_headroom' => is_array($readinessSummary) ? ($readinessSummary['high_value_headroom'] ?? null) : null,
                'evidence_exhausted' => is_array($readinessSummary) ? ($readinessSummary['evidence_exhausted'] ?? null) : null,
                'visual_limited' => is_array($readinessSummary) ? self::visualLimitedCount($readinessSummary) : null,
                'source_readiness' => $readinessSummary,
                'deterministic_issue_counts' => [
                    'FAIL' => (int) ($issueCounts[$boardId]['FAIL'] ?? 0),
                    'WARN' => (int) ($issueCounts[$boardId]['WARN'] ?? 0),
                    'REVIEW' => (int) ($issueCounts[$boardId]['REVIEW'] ?? 0),
                ],
            ];
        }

        return [
            'audit_level' => 'PORTFOLIO_READINESS_SUMMARY',
            'boards' => $rows,
            'semantic_review_boundary' => 'Stored readiness and health metadata are summarized; proposition novelty, source meaning, question equivalence, and visual suitability are not inferred.',
        ];
    }

    /** @param array<string, mixed> $board @param array<string, mixed>|null $readiness @param array<string, int> $issueCounts */
    private static function portfolioOperationalState(string $boardId, array $board, ?array $readiness, array $issueCounts): string
    {
        if ($readiness === null) {
            return ((int) ($issueCounts['FAIL'] ?? 0) > 0) ? 'DETERMINISTIC_FAIL' : 'FOUNDATION_SUFFICIENT';
        }
        $defects = (array) ($readiness['source_study_defects'] ?? []);
        $hardReadinessDefects = array_sum(array_map('intval', $defects));
        if ((int) ($issueCounts['FAIL'] ?? 0) > 0 || $hardReadinessDefects > 0 || (int) ($readiness['error_count'] ?? 0) > 0) {
            return 'DETERMINISTIC_FAIL';
        }
        if ((int) ($defects['source'] ?? 0) > 0 || (int) ($defects['study'] ?? 0) > 0 || (int) ($defects['traceability'] ?? 0) > 0) {
            return 'SOURCE_READINESS_NEEDED';
        }
        if ((int) ($defects['currentness'] ?? 0) > 0) {
            return 'CURRENTNESS_REVIEW_NEEDED';
        }
        $reviews = (array) ($readiness['source_study_reviews'] ?? []);
        if ((int) ($reviews['currentness_unknown'] ?? 0) > 0) {
            return 'CURRENTNESS_REVIEW_NEEDED';
        }
        $taxonomyStatus = strtoupper((string) ($board['taxonomy_granularity']['status'] ?? ''));
        if ($taxonomyStatus === 'REVIEW') {
            return 'TAXONOMY_MODERNIZATION_NEEDED';
        }
        $highValue = $readiness['high_value_headroom'] ?? null;
        $closureState = strtoupper((string) ($readiness['operational_state'] ?? $readiness['closure_state'] ?? ''));
        if (is_int($highValue) && $highValue > 0 && (int) ($readiness['authorable_thinness']['under20'] ?? 0) >= 0) {
            return 'SELECTIVE_PRODUCTION_ONLY';
        }
        if ($closureState === 'VISUAL_LIMITED' || (self::visualLimitedCount($readiness) > 0 && is_int($highValue) && $highValue === 0)) {
            return 'VISUAL_LIMITED';
        }
        if ($closureState === 'FOUNDATION_SUFFICIENT' || $closureState === 'EVIDENCE_EXHAUSTED' || ($readiness['evidence_exhausted'] ?? false) === true) {
            return 'FOUNDATION_SUFFICIENT';
        }
        if (strtoupper((string) ($board['taxonomy_granularity']['status'] ?? '')) === 'UNKNOWN') {
            return 'UNKNOWN_REQUIRES_MANUAL_REVIEW';
        }
        return 'MAINTENANCE_ONLY';
    }

    /** @param array<string, mixed> $readiness */
    private static function visualLimitedCount(array $readiness): int
    {
        $counts = (array) ($readiness['visual_suitability_counts'] ?? []);
        return (int) ($counts['VISUAL_PREFERRED'] ?? 0)
            + (int) ($counts['VISUAL_REQUIRED'] ?? 0)
            + (int) ($counts['MIXED'] ?? 0);
    }

    private static function build(RepositoryContext $context, ?RepositoryHealthProfiler $profiler = null): HealthReport
    {
        $collections = $context->collections;
        $questions = $profiler === null
            ? array_values(array_filter($collections['questions'] ?? [], 'is_array'))
            : $profiler->measure('shared_question_snapshot_projection', static fn(): array => array_values(array_filter($collections['questions'] ?? [], 'is_array')));
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
        $integrityHook = $profiler === null ? null : static function (string $operation, int $startedAt, array $checkCodes = []) use ($profiler): void {
            $profiler->recordDuration($operation, $startedAt, $checkCodes);
        };
        $integrityCounterHook = $profiler === null ? null : static function (string $counter, int $amount) use ($profiler): void {
            $profiler->increment($counter, $amount);
        };
        $integrity = $profiler === null
            ? ContentIntegrityService::analyze(
                $collections,
                static fn(array $question): ?array => $deriver->derive($question),
                static fn(array $question): array => $deriver->derivationStatus($question)
            )
            : $profiler->measure('shared_content_integrity_scan', static fn(): array => ContentIntegrityService::analyze(
                $collections,
                static fn(array $question): ?array => $deriver->derive($question),
                static fn(array $question): array => $deriver->derivationStatus($question),
                $integrityHook,
                $integrityCounterHook
            ));

        $issues = [];
        $run = static function (string $operation, callable $callback, array $checkCodes = []) use ($profiler): mixed {
            return $profiler === null ? $callback() : $profiler->measure($operation, $callback, $checkCodes);
        };
        $run('issue_mapping.integrity', static function () use (&$issues, $integrity, $questionById): void { self::addIntegrityIssues($issues, $integrity, $questionById); });
        $run('check.eligibility_validation', static function () use (&$issues, $collections, $questionById): void { self::addEligibilityIssues($issues, $collections, $questionById); }, ['ELIGIBILITY']);
        $run('check.flashcard_exclusion_validation', static function () use (&$issues, $collections, $questionById): void { self::addExclusionIssues($issues, $collections, $questionById); }, ['FLASHCARD_EXCLUSIONS']);
        $run('check.source_study_validation', static function () use (&$issues, $collections, $integrity, $questionById): void { self::addSourceStudyIssues($issues, $collections, $integrity, $questionById); }, ['SOURCE_STUDY']);
        $run('check.metadata_validation', static function () use (&$issues, $questions, $collections, $questionById): void { self::addMetadataIssues($issues, $questions, $collections, $questionById); }, ['CALCULATION_METADATA']);

        $boardSummaries = $run('check.board_summaries_and_balance', static function () use ($questions, $collections, &$issues, $profiler): array { return self::boardSummaries($questions, $collections, $issues, $profiler); });
        $readinessValidation = $run('check.source_readiness_validation', static fn(): array => SourceReadinessService::validateCollections($collections, $boardSummaries), ['SOURCE_READINESS']);
        $readinessClosures = ReadinessClosureService::byBoard((array) ($collections['readiness-closures'] ?? []));
        $run('issue_mapping.source_readiness', static function () use (&$issues, $readinessValidation): void { self::addSourceReadinessIssues($issues, $readinessValidation); });
        $readinessByBoard = [];
        foreach ((array) ($readinessValidation['by_board'] ?? []) as $boardId => $boardValidation) {
            $readinessByBoard[(string) $boardId] = SourceReadinessService::summary(
                (array) ($boardValidation['records'] ?? []),
                $boardValidation,
                $boardSummaries[(string) $boardId] ?? null,
                $readinessClosures[(string) $boardId] ?? null
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
        $flashcardMetrics = $run('check.flashcard_metrics', static fn(): array => self::flashcardMetrics($questions, $collections, $deriver, $profiler), ['FLASHCARD_DERIVABILITY']);
        $run('issue_mapping.flashcard_metrics', static function () use (&$issues, $flashcardMetrics, $questionById): void { self::addFlashcardMetricIssues($issues, $flashcardMetrics, $questionById); });
        foreach ($flashcardMetrics['by_board'] as $boardId => $metrics) {
            if (isset($boardSummaries[$boardId])) {
                $boardSummaries[$boardId]['flashcards'] = $metrics;
            }
        }
        $checks = self::checks($issues, $profiler);
        $summary = $run('shared_result_aggregation', static fn(): array => self::summary($checks, $issues));
        $summary['flashcards'] = $flashcardMetrics['portfolio'];
        $summary['source_readiness'] = $readinessSummary;
        $summary['source_readiness_by_board'] = $readinessByBoard;
        $summary['currentness'] = self::currentnessSummary($questions);

        $report = new HealthReport();
        $report->issues = $run('shared_issue_sorting', static fn(): array => self::sortIssues($issues));
        $report->checks = $checks;
        $report->summary = $summary;
        $report->overallStatus = $summary['overall_status'];
        $report->boardSummaries = $boardSummaries;
        $report->generatedAt = gmdate(DATE_ATOM);
        $report->exclusions = array_values(array_filter(
            $integrity['portfolio']['justified_exclusions'] ?? [],
            'is_array'
        ));
        $report->affectedIds = $run('shared_affected_id_aggregation', static fn(): array => self::affectedIds($report->issues));
        $report->actionQueue = array_values(array_filter(
            array_map(static fn(HealthIssue $issue): array => $issue->toArray(), $report->issues),
            static fn(array $issue): bool => in_array($issue['status'], ['FAIL', 'WARN', 'REVIEW'], true)
        ));
        $report->healthScore = $run('shared_health_score_aggregation', static fn(): float => self::healthScore($report->issues));
        $report->statistics = $run('shared_statistics_aggregation', static fn(): RepositoryStatistics => self::statistics($questions, $report->issues, $boardSummaries));
        if ($profiler !== null) {
            $report->profiling = $profiler->report();
        }

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
    private static function boardSummaries(array $questions, array $collections, array &$issues, ?RepositoryHealthProfiler $profiler = null): array
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
        if ($profiler !== null) $profiler->increment('eligibility_index_builds');
        $index = $profiler === null
            ? new QuestionEligibilityIndex(
                $collections['question-eligibility'] ?? [],
                $collections['blueprints'] ?? [],
                $collections['concept-derived-eligibility'] ?? [],
                $collections['question-reuse-overrides'] ?? []
            )
            : $profiler->measure('eligibility_index_build', static fn(): QuestionEligibilityIndex => new QuestionEligibilityIndex(
                $collections['question-eligibility'] ?? [],
                $collections['blueprints'] ?? [],
                $collections['concept-derived-eligibility'] ?? [],
                $collections['question-reuse-overrides'] ?? []
            ), ['ELIGIBILITY']);
        $potentialIdsByBoard = $index->potentialQuestionIdsByExam();
        $questionRowsById = [];
        foreach ($questions as $position => $question) {
            $questionId = self::id($question);
            if ($questionId === '') {
                continue;
            }
            $questionRowsById[$questionId][] = ['position' => $position, 'question' => $question];
            $nativeBoard = self::questionBoard($question);
            if ($nativeBoard !== '') {
                $potentialIdsByBoard[$nativeBoard][$questionId] = true;
            }
            if (!array_key_exists('board', $question) && !array_key_exists('taxonomy', $question)) {
                foreach (array_keys($boardIds) as $candidateBoard) {
                    $potentialIdsByBoard[$candidateBoard][$questionId] = true;
                }
            }
        }
        $candidateQuestionsByBoard = [];
        foreach (array_keys($boardIds) as $candidateBoard) {
            $candidateRows = [];
            foreach (array_keys($potentialIdsByBoard[$candidateBoard] ?? []) as $questionId) {
                foreach ($questionRowsById[$questionId] ?? [] as $row) {
                    $candidateRows[] = $row;
                }
            }
            usort($candidateRows, static fn(array $a, array $b): int => $a['position'] <=> $b['position']);
            $candidateQuestionsByBoard[$candidateBoard] = array_column($candidateRows, 'question');
        }
        if ($profiler !== null) {
            $profiler->increment('eligibility_candidate_question_memberships', array_sum(array_map('count', $candidateQuestionsByBoard)));
            $profiler->increment('eligibility_candidate_id_sets', count($candidateQuestionsByBoard));
        }
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
        // Build native placement and depth indexes once. The previous path
        // rescanned the complete question bank once per board and ignored the
        // canonical question-reuse metadata when a question did not duplicate
        // its required depth inline.
        $reuseDepthByQuestion = [];
        foreach ((array) ($collections['question-reuse-metadata'] ?? []) as $metadata) {
            if (!is_array($metadata)) continue;
            $questionId = trim((string) ($metadata['question_id'] ?? ''));
            $depth = $metadata['reuse']['required_depth'] ?? $metadata['required_depth'] ?? null;
            if ($questionId !== '' && (is_int($depth) || (is_string($depth) && ctype_digit(trim($depth)))) && (int) $depth > 0) {
                $reuseDepthByQuestion[$questionId] = (int) $depth;
            }
        }
        $ownedByBoard = [];
        $leafCountsByBoard = [];
        $depthsByBoard = [];
        $countIndexStartedAt = $profiler === null ? 0 : hrtime(true);
        foreach ($questions as $question) {
            $id = self::id($question);
            $board = self::questionBoard($question);
            if ($id === '' || $board === '') continue;
            $ownedByBoard[$board][$id] = true;
            $leaf = self::leafId($question);
            if ($leaf !== '') $leafCountsByBoard[$board][$leaf] = ($leafCountsByBoard[$board][$leaf] ?? 0) + 1;
            $depth = self::depth($question, $reuseDepthByQuestion);
            if ($depth !== null) $depthsByBoard[$board][] = $depth;
        }
        if ($profiler !== null) {
            $profiler->increment('global_question_count_index_builds');
            $profiler->recordDuration('global.native_question_leaf_and_depth_index', $countIndexStartedAt, ['QUESTION_COUNTS']);
        }
        $result = [];
        foreach (array_keys($boardIds) as $boardId) {
            $owned = $ownedByBoard[$boardId] ?? [];
            $leafCounts = $leafCountsByBoard[$boardId] ?? [];
            $effective = $directByBoard[$boardId] ?? [];
            foreach (array_diff_key($derivedByBoard[$boardId] ?? [], $effective) as $questionId => $_) {
                $effective[$questionId] = true;
            }
            $depths = $depthsByBoard[$boardId] ?? [];
            if ($effective === [] && $owned !== []) {
                // Fixture contexts without eligibility collections still get
                // a useful native count; production storage always uses the
                // direct-plus-derived declaration above.
                $effective = $owned;
            }
            $runtime = [];
            $filterStartedAt = $profiler === null ? 0 : hrtime(true);
            try {
                $runtime = \QuestionPoolFilter::filter(
                    $candidateQuestionsByBoard[$boardId] ?? [],
                    new \SelectionRequest('*', null, [], 1, null, null, $boardId),
                    $index
                );
            } catch (\Throwable $exception) {
                self::issue($issues, 'FAIL', 'ELIGIBILITY', 'PRODUCTION_REACHABILITY_ERROR', $boardId, [], 'Production selection could not evaluate this board.', 'Repair the eligibility mapping or selection contract.', ['exception' => $exception->getMessage()]);
            }
            if ($profiler !== null) {
                $profiler->increment('eligibility_filter_executions');
                $profiler->increment('eligibility_filter_questions', count($candidateQuestionsByBoard[$boardId] ?? []));
                $profiler->recordDuration('board.production_reachability_filter', $filterStartedAt, ['ELIGIBILITY']);
            }
            $runtimeIds = [];
            foreach ($runtime as $question) {
                $id = self::id($question);
                if ($id !== '') {
                    $runtimeIds[$id] = true;
                }
            }
            $allExpectedLeaves = array_keys($coverageByBoard[$boardId] ?? []);
            $expectedLeaves = $allExpectedLeaves;
            $cseScopeReconciliation = null;
            if ($boardId === 'civil-service') {
                $classCounts = [
                    'CORE_EXAM_LEAF' => 0,
                    'VALID_SHARED_COMPETENCY' => 0,
                    'STRUCTURAL_CONTAINER' => 0,
                    'LEGACY_FLAT_PLACEMENT' => 0,
                    'DUPLICATE_SEMANTIC_PLACEMENT' => 0,
                    'AMBIGUOUS_PLACEMENT' => 0,
                    'OUT_OF_SCOPE' => 0,
                    'OTHER_JUSTIFIED' => 0,
                ];
                $coreLeaves = [];
                foreach ($coverageByBoard[$boardId] ?? [] as $conceptId => $coverageRow) {
                    $class = CivilServiceCoverageModel::classifyRow($coverageRow);
                    $classCounts[$class] = ($classCounts[$class] ?? 0) + 1;
                    if ($class === 'CORE_EXAM_LEAF') $coreLeaves[] = (string) $conceptId;
                }
                sort($coreLeaves, SORT_STRING);
                $expectedLeaves = $coreLeaves;
                $rawCounts = [];
                foreach ($allExpectedLeaves as $leaf) $rawCounts[] = (int) ($leafCounts[$leaf] ?? 0);
                sort($rawCounts, SORT_NUMERIC);
                $cseScopeReconciliation = [
                    'coverage_rows' => count($allExpectedLeaves),
                    'class_counts' => $classCounts,
                    'core_exam_leaf_ids' => $coreLeaves,
                    'core_exam_leaf_count' => count($coreLeaves),
                    'raw_coverage_leaf_health' => [
                        'zero' => count(array_filter($rawCounts, static fn(int $n): bool => $n === 0)),
                        'under5' => count(array_filter($rawCounts, static fn(int $n): bool => $n < 5)),
                        'under10' => count(array_filter($rawCounts, static fn(int $n): bool => $n < 10)),
                        'under20' => count(array_filter($rawCounts, static fn(int $n): bool => $n < 20)),
                        'min' => $rawCounts === [] ? 0 : min($rawCounts),
                        'median' => self::median($rawCounts),
                    ],
                    'actionable_health_basis' => 'CORE_EXAM_LEAF only; flat/shared and out-of-scope rows remain visible but do not create native zero/under-threshold warnings.',
                ];
                $coreLeafSet = array_fill_keys($coreLeaves, true);
                $leafCounts = array_intersect_key($leafCounts, $coreLeafSet);
            }
            $balanceStartedAt = $profiler === null ? 0 : hrtime(true);
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
            if ($cseScopeReconciliation !== null) {
                $cseScopeReconciliation['core_leaf_health'] = [
                    'zero' => $summary['zero'],
                    'under5' => $summary['under5'],
                    'under10' => $summary['under10'],
                    'under20' => $summary['under20'],
                    'min' => $summary['min'],
                    'median' => $summary['median'],
                ];
                $summary['scope_reconciliation'] = $cseScopeReconciliation;
            }
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
            if ($profiler !== null) {
                $profiler->increment('balance_summary_executions');
                $profiler->recordDuration('board.balance_depth_concentration_metrics', $balanceStartedAt, ['BALANCE_DIVERSITY']);
            }
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
    private static function flashcardMetrics(array $questions, array $collections, FlashcardDerivationService $deriver, ?RepositoryHealthProfiler $profiler = null): array
    {
        $portfolio = ['eligible' => 0, 'derivable' => 0, 'justified_exclusion' => 0, 'nonderivable_defect' => 0, 'duplicate_card_key' => 0, 'malformed_card' => 0, 'broken_card_source' => 0, 'invalid_card_key' => 0, 'invalid_card_key_ids' => [], 'malformed_card_ids' => [], 'broken_card_source_ids' => []];
        $byBoard = [];
        $keys = [];
        $statusChecks = 0;
        $derivationCalls = 0;
        $cardValidations = 0;
        foreach ($questions as $question) {
            $questionId = self::id($question);
            $board = self::questionBoard($question);
            $byBoard[$board] ??= ['eligible' => 0, 'derivable' => 0, 'justified_exclusion' => 0, 'nonderivable_defect' => 0, 'duplicate_card_key' => 0, 'malformed_card' => 0, 'broken_card_source' => 0, 'invalid_card_key' => 0];
            if ($profiler !== null) $statusChecks++;
            $status = $deriver->derivationStatus($question);
            if (($status['eligible'] ?? false) !== true) {
                continue;
            }
            $portfolio['eligible']++;
            $byBoard[$board]['eligible']++;
            if ($profiler !== null) $derivationCalls++;
            $card = $deriver->derive($question);
            if (is_array($card)) {
                $portfolio['derivable']++;
                $byBoard[$board]['derivable']++;
                $key = (string) ($card['card_key'] ?? '');
                if ($key !== '') {
                    $keys[$key][] = [$questionId, $board];
                }
                if ($profiler !== null) $cardValidations++;
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
        if ($profiler !== null) {
            $profiler->increment('flashcard_status_checks', $statusChecks);
            $profiler->increment('flashcard_derivation_calls', $derivationCalls);
            $profiler->increment('flashcard_card_validations', $cardValidations);
            $profiler->increment('duplicate_check_passes');
        }
        return ['portfolio' => $portfolio, 'by_board' => $byBoard];
    }

    /** @param array<int, array<string, mixed>> $questions @return array<string, mixed> */
    private static function currentnessSummary(array $questions): array
    {
        $versionSensitive = 0;
        $reviewed = 0;
        $reviewRequired = 0;
        $futureOnly = 0;
        $futureLeakage = 0;
        $reviewedStatusCounts = [];
        $reviewedByBoard = [];
        $reviewRequiredByBoard = [];
        foreach ($questions as $question) {
            if (!is_array($question)) continue;
            $batch636 = is_array($question['batch636'] ?? null) ? $question['batch636'] : [];
            $versionReview = is_array($question['version_review'] ?? null)
                ? $question['version_review']
                : (is_array($batch636['version_review'] ?? null) ? $batch636['version_review'] : []);
            $sensitive = ($question['version_sensitive'] ?? false) === true || ($batch636['version_sensitive'] ?? false) === true;
            if ($sensitive) $versionSensitive++;
            if ($versionReview !== []) {
                $reviewed++;
                if (strtoupper((string) ($versionReview['cy2029'] ?? '')) === 'FUTURE_ONLY') $futureOnly++;
                if (($versionReview['future_rule_used'] ?? false) === true || ($versionReview['future_effective_used'] ?? false) === true) $futureLeakage++;
                $reviewStatus = 'STORED_REVIEW_OBJECT';
            } elseif ($sensitive && self::storedCurrentnessStatus($question) !== null) {
                // Older modernization batches stored the normalized review
                // outcome directly on the question. Treat that explicit
                // metadata as evidence; do not require a second nested
                // version_review object and falsely reopen currentness debt.
                $reviewed++;
                $reviewStatus = self::storedCurrentnessStatus($question);
            } elseif ($sensitive) {
                $reviewRequired++;
                $board = self::questionBoardId($question);
                if ($board !== '') $reviewRequiredByBoard[$board] = ($reviewRequiredByBoard[$board] ?? 0) + 1;
                continue;
            } else {
                continue;
            }
            $reviewedStatusCounts[$reviewStatus] = ($reviewedStatusCounts[$reviewStatus] ?? 0) + 1;
            $board = self::questionBoardId($question);
            if ($board !== '') $reviewedByBoard[$board] = ($reviewedByBoard[$board] ?? 0) + 1;
        }
        ksort($reviewedStatusCounts, SORT_STRING);
        ksort($reviewedByBoard, SORT_STRING);
        ksort($reviewRequiredByBoard, SORT_STRING);
        return [
            'status' => $futureLeakage > 0 ? 'FAIL' : ($reviewRequired > 0 ? 'REVIEW_REQUIRED' : 'CURRENT'),
            'version_sensitive' => $versionSensitive,
            'reviewed' => $reviewed,
            'review_required' => $reviewRequired,
            'future_effective_only' => $futureOnly,
            'future_effective_leakage' => $futureLeakage,
            'reviewed_status_counts' => $reviewedStatusCounts,
            'reviewed_by_board' => $reviewedByBoard,
            'review_required_by_board' => $reviewRequiredByBoard,
        ];
    }

    private static function storedCurrentnessStatus(array $question): ?string
    {
        $status = strtoupper(trim((string) ($question['version_review_status'] ?? $question['currentness_review_status'] ?? '')));
        return in_array($status, ['PASS', 'CURRENT', 'CURRENT_WITH_LIMITS', 'NOT_TIME_SENSITIVE'], true)
            ? $status
            : null;
    }

    private static function questionBoardId(array $question): string
    {
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        return strtolower(trim((string) ($question['board_id'] ?? $question['board'] ?? $taxonomy['board_id'] ?? '')));
    }

    private static function checks(array $issues, ?RepositoryHealthProfiler $profiler = null): array
    {
        $definitions = RepositoryHealthCheckCatalog::definitions();
        $checks = [];
        foreach ($definitions as $code => $definition) {
            $checkStartedAt = hrtime(true);
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
            $profiler?->recordCheckExecution((string) $code, $checkStartedAt, $matching);
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
        foreach ((array) ($blueprint['official_areas'] ?? []) as $officialArea) {
            if (!is_array($officialArea)) continue;
            $officialAreaId = trim((string) ($officialArea['id'] ?? ''));
            if ($officialAreaId !== '' && !isset($areaSources[$officialAreaId]) && isset($weights[$officialAreaId])) {
                $areaSources[$officialAreaId] = $officialAreaId;
            }
        }
        // Granular coverage rows may carry the official weighting component
        // even when the question taxonomy retains an internal domain/topic.
        // This is a deterministic placement mapping, not a semantic area
        // inference; one leaf resolves to one stored blueprint node.
        $coverageAreaByLeaf = [];
        foreach ((array) ($collections['board-concept-coverage'] ?? []) as $coverage) {
            if (!is_array($coverage) || self::normalizeBoard($coverage['board_id'] ?? $coverage['board'] ?? '') !== $boardId) continue;
            $leafId = trim((string) ($coverage['concept_id'] ?? ''));
            $areaId = trim((string) ($coverage['blueprint_node_id'] ?? $coverage['official_area_id'] ?? ''));
            if ($leafId !== '' && $areaId !== '' && isset($areaSources[$areaId])) $coverageAreaByLeaf[$leafId] = $areaId;
        }
        $counts = array_fill_keys(array_keys($areaSources), 0);
        foreach ($questions as $question) {
            if (self::questionBoard($question) !== $boardId) {
                continue;
            }
            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            $leafId = self::leafId($question);
            $candidates = array_values(array_filter([
                $coverageAreaByLeaf[$leafId] ?? null,
                $taxonomy['official_area_id'] ?? null,
                $taxonomy['blueprint_node_id'] ?? null,
                $taxonomy['domain_id'] ?? null,
                $taxonomy['subject_id'] ?? null,
            ], static fn(mixed $value): bool => trim((string) $value) !== ''));
            foreach ($areaSources as $outputArea => $sourceArea) {
                if (in_array((string) $outputArea, array_map('strval', $candidates), true) || in_array((string) $sourceArea, array_map('strval', $candidates), true)) {
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

    /** @param array<string, int> $reuseDepthByQuestion */
    private static function depth(array $question, array $reuseDepthByQuestion = []): ?int
    {
        foreach ([$question['depth'] ?? null, $question['required_depth'] ?? null, is_array($question['reuse'] ?? null) ? ($question['reuse']['required_depth'] ?? null) : null] as $value) {
            if (is_int($value) || (is_string($value) && ctype_digit(trim($value)))) {
                $depth = (int) $value;
                if ($depth > 0) {
                    return $depth;
                }
            }
        }
        $questionId = self::id($question);
        if ($questionId !== '' && isset($reuseDepthByQuestion[$questionId]) && $reuseDepthByQuestion[$questionId] > 0) {
            return (int) $reuseDepthByQuestion[$questionId];
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
