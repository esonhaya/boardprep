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
use App\Services\Shared\QuestionCoverageService;

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
        $run = static function (string $operation, callable $callback, array $checkCodes = []) use ($profiler): mixed {
            return $profiler === null ? $callback() : $profiler->measure($operation, $callback, $checkCodes);
        };
        $snapshot = self::contentIntegritySnapshot($collections, $profiler);
        $issues = self::baseHealthIssues($collections, $snapshot, $run);
        $readiness = self::boardReadinessSnapshot($snapshot['questions'], $collections, $issues, $profiler, $run);
        $flashcards = $run('check.flashcard_metrics', static fn(): array => self::flashcardMetrics(
            $snapshot['questions'],
            $collections,
            $snapshot['deriver'],
            $profiler
        ), ['FLASHCARD_DERIVABILITY']);
        $run('issue_mapping.flashcard_metrics', static function () use (&$issues, $flashcards, $snapshot): void {
            self::addFlashcardMetricIssues($issues, $flashcards, $snapshot['question_by_id']);
        });
        foreach ($flashcards['by_board'] as $boardId => $metrics) {
            if (isset($readiness['board_summaries'][$boardId])) {
                $readiness['board_summaries'][$boardId]['flashcards'] = $metrics;
            }
        }

        return self::healthReport(
            $snapshot,
            $collections,
            $issues,
            $readiness,
            $flashcards,
            $profiler,
            $run
        );
    }

    private static function contentIntegritySnapshot(array $collections, ?RepositoryHealthProfiler $profiler): array
    {
        $projectQuestions = static fn(): array => array_values(array_filter($collections['questions'] ?? [], 'is_array'));
        $questions = $profiler === null
            ? $projectQuestions()
            : $profiler->measure('shared_question_snapshot_projection', $projectQuestions);
        $questionById = [];
        foreach ($questions as $question) {
            $id = self::id($question);
            if ($id !== '') $questionById[$id] = $question;
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
        $counterHook = $profiler === null ? null : static function (string $counter, int $amount) use ($profiler): void {
            $profiler->increment($counter, $amount);
        };
        $scan = static fn(): array => ContentIntegrityService::analyze(
            $collections,
            static fn(array $question): ?array => $deriver->derive($question),
            static fn(array $question): array => $deriver->derivationStatus($question),
            $integrityHook,
            $counterHook
        );
        $integrity = $profiler === null ? $scan() : $profiler->measure('shared_content_integrity_scan', $scan);
        return [
            'questions' => $questions,
            'question_by_id' => $questionById,
            'deriver' => $deriver,
            'integrity' => $integrity,
        ];
    }

    private static function baseHealthIssues(array $collections, array $snapshot, callable $run): array
    {
        $issues = [];
        $run('issue_mapping.integrity', static function () use (&$issues, $snapshot): void {
            self::addIntegrityIssues($issues, $snapshot['integrity'], $snapshot['question_by_id']);
        });
        $run('check.eligibility_validation', static function () use (&$issues, $collections, $snapshot): void {
            self::addEligibilityIssues($issues, $collections, $snapshot['question_by_id']);
        }, ['ELIGIBILITY']);
        $run('check.flashcard_exclusion_validation', static function () use (&$issues, $collections, $snapshot): void {
            self::addExclusionIssues($issues, $collections, $snapshot['question_by_id']);
        }, ['FLASHCARD_EXCLUSIONS']);
        $run('check.source_study_validation', static function () use (&$issues, $collections, $snapshot): void {
            self::addSourceStudyIssues($issues, $collections, $snapshot['integrity'], $snapshot['question_by_id']);
        }, ['SOURCE_STUDY']);
        $run('check.metadata_validation', static function () use (&$issues, $collections, $snapshot): void {
            self::addMetadataIssues($issues, $snapshot['questions'], $collections, $snapshot['question_by_id']);
        }, ['CALCULATION_METADATA']);
        return $issues;
    }

    private static function boardReadinessSnapshot(
        array $questions,
        array $collections,
        array &$issues,
        ?RepositoryHealthProfiler $profiler,
        callable $run
    ): array {
        if ($profiler !== null) $profiler->increment('eligibility_index_builds');
        $eligibilityIndex = $run('eligibility_index_build', static fn(): QuestionEligibilityIndex => new QuestionEligibilityIndex(
            $collections['question-eligibility'] ?? [],
            $collections['blueprints'] ?? [],
            $collections['concept-derived-eligibility'] ?? [],
            $collections['question-reuse-overrides'] ?? []
        ), ['ELIGIBILITY']);
        $boardResults = $run('check.board_summaries_and_balance', static function () use ($questions, $collections, &$issues, $eligibilityIndex, $profiler): array {
            return self::boardSummaries($questions, $collections, $issues, $eligibilityIndex, $profiler);
        });
        $boardSummaries = $boardResults['summaries'];
        $validation = $run('check.source_readiness_validation', static fn(): array => SourceReadinessService::validateCollections($collections, $boardSummaries), ['SOURCE_READINESS']);
        $closures = ReadinessClosureService::byBoard((array) ($collections['readiness-closures'] ?? []));
        $run('issue_mapping.source_readiness', static function () use (&$issues, $validation): void {
            self::addSourceReadinessIssues($issues, $validation);
        });
        $byBoard = self::readinessSummariesByBoard($validation, $boardSummaries, $closures);
        foreach ($byBoard as $boardId => $summary) {
            if (isset($boardSummaries[$boardId])) $boardSummaries[$boardId]['source_readiness'] = $summary;
        }
        return [
            'eligibility_index' => $eligibilityIndex,
            'board_summaries' => $boardSummaries,
            'candidate_questions' => $boardResults['candidate_questions'],
            'readiness_by_board' => $byBoard,
            'readiness_summary' => $byBoard['criminologist'] ?? SourceReadinessService::summary(
                $validation['records'] ?? [],
                $validation
            ),
        ];
    }

    private static function readinessSummariesByBoard(array $validation, array $boardSummaries, array $closures): array
    {
        $byBoard = [];
        foreach ((array) ($validation['by_board'] ?? []) as $boardId => $boardValidation) {
            $boardId = (string) $boardId;
            $byBoard[$boardId] = SourceReadinessService::summary(
                (array) ($boardValidation['records'] ?? []),
                $boardValidation,
                $boardSummaries[$boardId] ?? null,
                $closures[$boardId] ?? null
            );
        }
        return $byBoard;
    }

    private static function healthReport(
        array $snapshot,
        array $collections,
        array $issues,
        array $readiness,
        array $flashcards,
        ?RepositoryHealthProfiler $profiler,
        callable $run
    ): HealthReport {
        $checks = self::checks($issues, $profiler);
        $summary = $run('shared_result_aggregation', static fn(): array => self::summary($checks, $issues));
        $summary['flashcards'] = $flashcards['portfolio'];
        $summary['source_readiness'] = $readiness['readiness_summary'];
        $summary['source_readiness_by_board'] = $readiness['readiness_by_board'];
        $summary['currentness'] = self::currentnessSummary($snapshot['questions']);

        $report = new HealthReport();
        $report->issues = $run('shared_issue_sorting', static fn(): array => self::sortIssues($issues));
        $report->checks = $checks;
        $report->summary = $summary;
        $report->overallStatus = $summary['overall_status'];
        $report->boardSummaries = $readiness['board_summaries'];
        $report->generatedAt = gmdate(DATE_ATOM);
        $report->exclusions = array_values(array_filter(
            $snapshot['integrity']['portfolio']['justified_exclusions'] ?? [],
            'is_array'
        ));
        $report->affectedIds = $run('shared_affected_id_aggregation', static fn(): array => self::affectedIds($report->issues));
        $report->actionQueue = array_values(array_filter(
            array_map(static fn(HealthIssue $issue): array => $issue->toArray(), $report->issues),
            static fn(array $issue): bool => in_array($issue['status'], ['FAIL', 'WARN', 'REVIEW'], true)
        ));
        $report->healthScore = $run('shared_health_score_aggregation', static fn(): float => self::healthScore($report->issues));
        $report->statistics = $run('shared_statistics_aggregation', static fn(): RepositoryStatistics => self::statistics(
            $snapshot['questions'],
            $report->issues,
            $readiness['board_summaries'],
            $readiness['candidate_questions'],
            $collections,
            $readiness['eligibility_index'],
            $profiler
        ));
        if ($profiler !== null) $report->profiling = $profiler->report();
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
        self::addStructuredIntegrityIssues($issues, $integrity, $questionById);
        self::addMappedIntegrityFailures($issues, $integrity, $questionById);
        self::addDuplicateIntegrityIssues($issues, $integrity, $questionById);
        self::addBoardIntegrityDefects($issues, $integrity, $questionById);
    }

    private static function addStructuredIntegrityIssues(array &$issues, array $integrity, array $questionById): void
    {
        foreach ((array) ($integrity['failures']['structured_content'] ?? []) as $id => $details) {
            $questionId = is_int($id) && !is_array($details) ? (string) $details : (string) $id;
            $details = is_int($id) && !is_array($details) ? [] : (array) $details;
            if ($questionId === '') continue;
            foreach ($details as $detail) {
                $detail = is_array($detail) ? $detail : [];
                $code = strtoupper((string) ($detail['code'] ?? 'OTHER'));
                self::issue(
                    $issues,
                    'FAIL',
                    'STRUCTURED_CONTENT',
                    'STRUCTURED_CONTENT_' . $code,
                    $questionId,
                    $questionById,
                    'Active structured content violates the production schema.',
                    'Repair the structured block using the canonical producer contract.',
                    ['path' => $detail['path'] ?? '', 'validation_failure' => $detail['message'] ?? '']
                );
            }
        }
    }

    private static function addMappedIntegrityFailures(array &$issues, array $integrity, array $questionById): void
    {
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
                if ($questionId !== '') self::issue($issues, 'FAIL', $category, $code, $questionId, $questionById, $message, $action, ['source_value' => $value]);
            }
        }
    }

    private static function addDuplicateIntegrityIssues(array &$issues, array $integrity, array $questionById): void
    {
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
    }

    private static function addBoardIntegrityDefects(array &$issues, array $integrity, array $questionById): void
    {
        $mapping = [
            'missing_eligibility' => ['ELIGIBILITY', 'MISSING_ELIGIBILITY', 'Canonical question has no production eligibility mapping.', 'Add a valid eligibility mapping.'],
            'invalid_eligibility' => ['ELIGIBILITY', 'INVALID_ELIGIBILITY', 'Eligibility mapping references an unknown entity.', 'Repair the eligibility record.'],
            'unlinked_questions' => ['TRACEABILITY', 'MISSING_STUDY_LINK', 'Canonical question has no study-material link.', 'Link the question to a supported study material.'],
            'invalid_study_links' => ['SOURCE_STUDY', 'INVALID_STUDY_LINK', 'Study link points to missing or invalid content.', 'Repair the study link and source chain.'],
            'duplicate_study_links' => ['SOURCE_STUDY', 'DUPLICATE_STUDY_LINK_KEY', 'Duplicate question/material link exists.', 'Keep one canonical link key.'],
            'invalid_taxonomy_references' => ['TAXONOMY', 'INVALID_TAXONOMY_REFERENCE', 'Question taxonomy path is invalid.', 'Repair the taxonomy reference.'],
            'orphan_taxonomy_nodes' => ['TAXONOMY', 'BROKEN_TAXONOMY_PARENT', 'Taxonomy node has a broken parent relationship.', 'Repair the taxonomy parent relationship.'],
            'structured_content_failures' => ['STRUCTURED_CONTENT', 'STRUCTURED_CONTENT_INVALID', 'Structured-content contract failure is active.', 'Repair the structured content.'],
        ];
        foreach ((array) ($integrity['boards'] ?? []) as $board) {
            if (!is_array($board)) continue;
            $boardId = (string) ($board['board_id'] ?? '');
            foreach ((array) ($board['defects'] ?? []) as $key => $values) {
                if (!isset($mapping[$key])) continue;
                [$category, $code, $message, $action] = $mapping[$key];
                foreach ((array) $values as $value) {
                    self::issue($issues, 'FAIL', $category, $code, (string) $value, $questionById, $message, $action, ['board_id' => $boardId, 'record' => $value]);
                }
            }
        }
    }
    /** @param array<int, HealthIssue> $issues */
    private static function addEligibilityIssues(array &$issues, array $collections, array $questionById): void
    {
        $boards = self::eligibilityBoardSet($collections);
        self::addDirectEligibilityIssues($issues, $collections, $questionById, $boards);
        self::addEligibilityOverrideIssues($issues, $collections, $questionById, $boards);
        self::addDerivedEligibilityIssues($issues, $collections, $questionById, $boards);
    }

    private static function eligibilityBoardSet(array $collections): array
    {
        $boards = [];
        foreach ((array) ($collections['boards'] ?? []) as $board) {
            if (!is_array($board)) continue;
            $id = self::normalizeBoard($board['id'] ?? '');
            if ($id !== '') $boards[$id] = true;
        }
        return $boards;
    }

    private static function addDirectEligibilityIssues(
        array &$issues,
        array $collections,
        array $questionById,
        array $boards
    ): void {
        $directKeys = [];
        foreach ((array) ($collections['question-eligibility'] ?? []) as $entry) {
            if (!is_array($entry)) continue;
            $entryId = (string) ($entry['id'] ?? '<missing>');
            $questionIds = is_array($entry['question_ids'] ?? null) ? $entry['question_ids'] : [];
            $mappings = is_array($entry['question_mappings'] ?? null) ? $entry['question_mappings'] : [];
            foreach ($questionIds as $questionIdValue) {
                $questionId = trim((string) $questionIdValue);
                $mapping = is_array($mappings[$questionId] ?? null) ? $mappings[$questionId] : [];
                $examIds = is_array($mapping['exam_ids'] ?? null) ? $mapping['exam_ids'] : (is_array($entry['exam_ids'] ?? null) ? $entry['exam_ids'] : []);
                foreach ($examIds as $examId) {
                    $boardId = self::normalizeBoard($examId);
                    if ($boardId === '' || !isset($boards[$boardId]) || $questionId === '' || !isset($questionById[$questionId])) continue;
                    $key = $boardId . '|' . $questionId;
                    if (isset($directKeys[$key])) {
                        self::issue($issues, 'FAIL', 'ELIGIBILITY', 'DUPLICATE_EFFECTIVE_CANDIDATE', $entryId, $questionById, 'Multiple direct eligibility rows produce the same effective board/question candidate.', 'Keep one canonical effective eligibility mapping to avoid double weighting.', ['board_id' => $boardId, 'question_id' => $questionId, 'first_entry_id' => $directKeys[$key]]);
                    } else {
                        $directKeys[$key] = $entryId;
                    }
                }
            }
        }
    }

    private static function addEligibilityOverrideIssues(
        array &$issues,
        array $collections,
        array $questionById,
        array $boards
    ): void {
        $reuseKeys = [];
        foreach ((array) ($collections['question-reuse-overrides'] ?? []) as $override) {
            if (!is_array($override)) continue;
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
    }

    private static function addDerivedEligibilityIssues(
        array &$issues,
        array $collections,
        array $questionById,
        array $boards
    ): void {
        $derivedKeys = [];
        foreach ((array) ($collections['concept-derived-eligibility'] ?? []) as $relationship) {
            if (!is_array($relationship) || ($relationship['record_type'] ?? '') !== 'RELATIONSHIP') continue;
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
    private static function boardSummaries(
        array $questions,
        array $collections,
        array &$issues,
        QuestionEligibilityIndex $index,
        ?RepositoryHealthProfiler $profiler = null
    ): array {
        $indexes = self::boardSummaryIndexes($questions, $collections, $index, $profiler);
        $result = [];
        foreach ($indexes['board_ids'] as $boardId) {
            $result[$boardId] = self::boardSummaryForHealth(
                $boardId,
                $questions,
                $collections,
                $indexes,
                $issues,
                $index,
                $profiler
            );
        }
        ksort($result);
        return [
            'summaries' => $result,
            'candidate_questions' => $indexes['candidate_questions'],
        ];
    }

    private static function boardSummaryIndexes(
        array $questions,
        array $collections,
        QuestionEligibilityIndex $index,
        ?RepositoryHealthProfiler $profiler
    ): array {
        $boardIds = self::healthBoardIds($questions, $collections);
        [$directByBoard, $derivedByBoard] = self::declaredEligibilityIndexes($collections);
        $reuseDepthByQuestion = self::reuseDepthIndex($collections);
        $nativeIndexes = self::nativeQuestionIndexes($questions, $reuseDepthByQuestion, $profiler);

        return [
            'board_ids' => $boardIds,
            'candidate_questions' => self::candidateQuestionsByBoard($questions, $boardIds, $index, $profiler),
            'coverage_by_board' => self::coverageRowsByBoard($collections),
            'direct_by_board' => $directByBoard,
            'derived_by_board' => $derivedByBoard,
            'concepts' => self::indexed($collections['taxonomy/concepts'] ?? []),
            'owned_by_board' => $nativeIndexes['owned_by_board'],
            'leaf_counts_by_board' => $nativeIndexes['leaf_counts_by_board'],
            'depths_by_board' => $nativeIndexes['depths_by_board'],
        ];
    }

    private static function healthBoardIds(array $questions, array $collections): array
    {
        $boardIds = [];
        foreach ((array) ($collections['boards'] ?? []) as $board) {
            $id = self::normalizeBoard($board['id'] ?? '');
            if ($id !== '') $boardIds[$id] = true;
        }
        foreach ($questions as $question) {
            $id = self::questionBoard($question);
            if ($id !== '') $boardIds[$id] = true;
        }
        return array_keys($boardIds);
    }

    private static function candidateQuestionsByBoard(
        array $questions,
        array $boardIds,
        QuestionEligibilityIndex $index,
        ?RepositoryHealthProfiler $profiler
    ): array {
        $potentialIdsByBoard = $index->potentialQuestionIdsByExam();
        $questionRowsById = [];
        foreach ($questions as $position => $question) {
            $questionId = self::id($question);
            if ($questionId === '') continue;
            $questionRowsById[$questionId][] = ['position' => $position, 'question' => $question];
            self::addNativeAndUnscopedCandidates($potentialIdsByBoard, $boardIds, $question, $questionId);
        }

        $candidateQuestionsByBoard = [];
        foreach ($boardIds as $boardId) {
            $candidateQuestionsByBoard[$boardId] = self::orderedCandidateQuestions(
                $questionRowsById,
                $potentialIdsByBoard[$boardId] ?? []
            );
        }
        if ($profiler !== null) {
            $profiler->increment('eligibility_candidate_question_memberships', array_sum(array_map('count', $candidateQuestionsByBoard)));
            $profiler->increment('eligibility_candidate_id_sets', count($candidateQuestionsByBoard));
        }
        return $candidateQuestionsByBoard;
    }

    private static function addNativeAndUnscopedCandidates(
        array &$potentialIdsByBoard,
        array $boardIds,
        array $question,
        string $questionId
    ): void {
        $nativeBoard = self::questionBoard($question);
        if ($nativeBoard !== '') $potentialIdsByBoard[$nativeBoard][$questionId] = true;
        if (array_key_exists('board', $question) || array_key_exists('taxonomy', $question)) return;
        foreach ($boardIds as $candidateBoard) {
            $potentialIdsByBoard[$candidateBoard][$questionId] = true;
        }
    }

    private static function orderedCandidateQuestions(array $questionRowsById, array $candidateIds): array
    {
        $candidateRows = [];
        foreach (array_keys($candidateIds) as $questionId) {
            foreach ($questionRowsById[$questionId] ?? [] as $row) {
                $candidateRows[] = $row;
            }
        }
        usort($candidateRows, static fn(array $a, array $b): int => $a['position'] <=> $b['position']);
        return array_column($candidateRows, 'question');
    }

    private static function coverageRowsByBoard(array $collections): array
    {
        $coverageByBoard = [];
        foreach ((array) ($collections['board-concept-coverage'] ?? []) as $coverage) {
            if (!is_array($coverage)) continue;
            $board = self::normalizeBoard($coverage['board_id'] ?? $coverage['board'] ?? '');
            $concept = trim((string) ($coverage['concept_id'] ?? ''));
            if ($board !== '' && $concept !== '') $coverageByBoard[$board][$concept] = $coverage;
        }
        return $coverageByBoard;
    }

    private static function declaredEligibilityIndexes(array $collections): array
    {
        $directByBoard = [];
        foreach ((array) ($collections['question-eligibility'] ?? []) as $entry) {
            if (!is_array($entry)) continue;
            $boards = array_map('strval', is_array($entry['exam_ids'] ?? null) ? $entry['exam_ids'] : []);
            foreach ((array) ($entry['question_ids'] ?? []) as $questionId) {
                $questionId = trim((string) $questionId);
                if ($questionId === '') continue;
                foreach ($boards as $board) {
                    $board = self::normalizeBoard($board);
                    if ($board !== '') $directByBoard[$board][$questionId] = true;
                }
            }
        }

        $derivedByBoard = [];
        foreach ((array) ($collections['concept-derived-eligibility'] ?? []) as $entry) {
            if (!is_array($entry) || ($entry['record_type'] ?? '') !== 'RELATIONSHIP') continue;
            $board = self::normalizeBoard($entry['target_board'] ?? '');
            $questionId = trim((string) ($entry['question_id'] ?? ''));
            if ($board !== '' && $questionId !== '') $derivedByBoard[$board][$questionId] = true;
        }
        return [$directByBoard, $derivedByBoard];
    }

    private static function reuseDepthIndex(array $collections): array
    {
        $reuseDepthByQuestion = [];
        foreach ((array) ($collections['question-reuse-metadata'] ?? []) as $metadata) {
            if (!is_array($metadata)) continue;
            $questionId = trim((string) ($metadata['question_id'] ?? ''));
            $depth = $metadata['reuse']['required_depth'] ?? $metadata['required_depth'] ?? null;
            if ($questionId !== ''
                && (is_int($depth) || (is_string($depth) && ctype_digit(trim($depth))))
                && (int) $depth > 0) {
                $reuseDepthByQuestion[$questionId] = (int) $depth;
            }
        }
        return $reuseDepthByQuestion;
    }

    private static function nativeQuestionIndexes(
        array $questions,
        array $reuseDepthByQuestion,
        ?RepositoryHealthProfiler $profiler
    ): array {
        $ownedByBoard = [];
        $leafCountsByBoard = [];
        $depthsByBoard = [];
        $startedAt = $profiler === null ? 0 : hrtime(true);
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
            $profiler->recordDuration('global.native_question_leaf_and_depth_index', $startedAt, ['QUESTION_COUNTS']);
        }
        return [
            'owned_by_board' => $ownedByBoard,
            'leaf_counts_by_board' => $leafCountsByBoard,
            'depths_by_board' => $depthsByBoard,
        ];
    }

    private static function boardSummaryForHealth(
        string $boardId,
        array $questions,
        array $collections,
        array $indexes,
        array &$issues,
        QuestionEligibilityIndex $index,
        ?RepositoryHealthProfiler $profiler
    ): array {
        $owned = $indexes['owned_by_board'][$boardId] ?? [];
        $leafCounts = $indexes['leaf_counts_by_board'][$boardId] ?? [];
        $depths = $indexes['depths_by_board'][$boardId] ?? [];
        $effective = $indexes['direct_by_board'][$boardId] ?? [];
        foreach (array_diff_key($indexes['derived_by_board'][$boardId] ?? [], $effective) as $id => $_) {
            $effective[$id] = true;
        }
        if ($effective === [] && $owned !== []) $effective = $owned;

        $runtimeIds = self::runtimeQuestionIds($boardId, $indexes['candidate_questions'], $index, $issues, $profiler);
        [$expectedLeaves, $leafCounts, $scopeReconciliation] = self::expectedBoardLeaves(
            $boardId,
            $indexes['coverage_by_board'],
            $leafCounts
        );
        $balanceStartedAt = $profiler === null ? 0 : hrtime(true);
        $leafMetrics = self::leafBalanceMetrics($expectedLeaves, $leafCounts, $indexes['concepts']);
        $summary = self::boardHealthSummary(
            $boardId,
            $questions,
            $collections,
            $owned,
            $effective,
            $runtimeIds,
            $depths,
            $leafMetrics
        );
        if ($scopeReconciliation !== null) {
            $scopeReconciliation['core_leaf_health'] = self::coreLeafHealth($summary);
            $summary['scope_reconciliation'] = $scopeReconciliation;
        }
        self::addBoardBalanceIssues($issues, $boardId, $summary);
        if ($profiler !== null) {
            $profiler->increment('balance_summary_executions');
            $profiler->recordDuration('board.balance_depth_concentration_metrics', $balanceStartedAt, ['BALANCE_DIVERSITY']);
        }
        return $summary;
    }

    private static function runtimeQuestionIds(
        string $boardId,
        array $candidateQuestionsByBoard,
        QuestionEligibilityIndex $index,
        array &$issues,
        ?RepositoryHealthProfiler $profiler
    ): array {
        $startedAt = $profiler === null ? 0 : hrtime(true);
        try {
            $runtime = \QuestionPoolFilter::filter(
                $candidateQuestionsByBoard[$boardId] ?? [],
                new \SelectionRequest('*', null, [], 1, null, null, $boardId),
                $index
            );
        } catch (\Throwable $exception) {
            self::issue($issues, 'FAIL', 'ELIGIBILITY', 'PRODUCTION_REACHABILITY_ERROR', $boardId, [], 'Production selection could not evaluate this board.', 'Repair the eligibility mapping or selection contract.', ['exception' => $exception->getMessage()]);
            $runtime = [];
        }
        if ($profiler !== null) {
            $profiler->increment('eligibility_filter_executions');
            $profiler->increment('eligibility_filter_questions', count($candidateQuestionsByBoard[$boardId] ?? []));
            $profiler->recordDuration('board.production_reachability_filter', $startedAt, ['ELIGIBILITY']);
        }
        $ids = [];
        foreach ($runtime as $question) {
            $id = self::id($question);
            if ($id !== '') $ids[$id] = true;
        }
        return $ids;
    }

    private static function expectedBoardLeaves(string $boardId, array $coverageByBoard, array $leafCounts): array
    {
        $allLeaves = array_keys($coverageByBoard[$boardId] ?? []);
        $expectedLeaves = $allLeaves;
        $scopeReconciliation = null;
        if ($boardId !== 'civil-service') return [$expectedLeaves, $leafCounts, $scopeReconciliation];

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
        $rawCounts = [];
        foreach ($allLeaves as $leaf) $rawCounts[] = (int) ($leafCounts[$leaf] ?? 0);
        sort($rawCounts, SORT_NUMERIC);
        $scopeReconciliation = [
            'coverage_rows' => count($allLeaves),
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
        $expectedLeaves = $coreLeaves;
        $leafCounts = array_intersect_key($leafCounts, array_fill_keys($coreLeaves, true));
        return [$expectedLeaves, $leafCounts, $scopeReconciliation];
    }

    private static function leafBalanceMetrics(array $expectedLeaves, array $leafCounts, array $concepts): array
    {
        $structuralLeaves = [];
        foreach ($expectedLeaves as $leaf) {
            if (self::isStructuralLeaf((string) $leaf, $leafCounts, $concepts)) $structuralLeaves[$leaf] = true;
        }
        $activeExpectedLeaves = array_values(array_diff($expectedLeaves, array_keys($structuralLeaves)));
        $activeLeaves = [];
        foreach ($activeExpectedLeaves as $leaf) $activeLeaves[$leaf] = (int) ($leafCounts[$leaf] ?? 0);
        foreach ($leafCounts as $leaf => $count) {
            if (!isset($structuralLeaves[$leaf])) $activeLeaves[$leaf] = $count;
        }
        $counts = array_values($activeLeaves);
        sort($counts);
        return [
            'active_leaves' => $activeLeaves,
            'structural_leaves' => $structuralLeaves,
            'counts' => $counts,
        ];
    }

    private static function boardHealthSummary(
        string $boardId,
        array $questions,
        array $collections,
        array $owned,
        array $effective,
        array $runtimeIds,
        array $depths,
        array $leafMetrics
    ): array {
        $activeLeaves = $leafMetrics['active_leaves'];
        $counts = $leafMetrics['counts'];
        return [
            'board_id' => $boardId,
            'canonical' => count($owned),
            'effective_declared' => count($effective),
            'runtime' => count($runtimeIds),
            'active_leaves' => count($activeLeaves),
            'structural_administrative_leaves' => count($leafMetrics['structural_leaves']),
            'structural_administrative_leaf_ids' => array_keys($leafMetrics['structural_leaves']),
            'zero' => count(array_filter($activeLeaves, static fn(int $count): bool => $count === 0)),
            'under5' => count(array_filter($counts, static fn(int $count): bool => $count < 5)),
            'under10' => count(array_filter($counts, static fn(int $count): bool => $count < 10)),
            'under20' => count(array_filter($counts, static fn(int $count): bool => $count < 20)),
            'min' => $counts === [] ? 0 : min($counts),
            'median' => self::median($counts),
            'leaf_counts' => $activeLeaves,
            'repeat_risk' => self::repeatRisk($counts, count($owned)),
            'depth_known' => count($depths),
            'depth_unknown' => max(0, count($owned) - count($depths)),
            'official_area_balance' => self::areaBalance($boardId, $questions, $collections),
            'concentration' => self::concentration($boardId, $questions, $activeLeaves),
        ];
    }

    private static function coreLeafHealth(array $summary): array
    {
        return [
            'zero' => $summary['zero'],
            'under5' => $summary['under5'],
            'under10' => $summary['under10'],
            'under20' => $summary['under20'],
            'min' => $summary['min'],
            'median' => $summary['median'],
        ];
    }

    private static function addBoardBalanceIssues(array &$issues, string $boardId, array &$summary): void
    {
        $summary['taxonomy_granularity'] = self::taxonomyGranularity($summary['leaf_counts']);
        self::addTaxonomyGranularityIssue($issues, $boardId, $summary['taxonomy_granularity']);
        self::addLeafBalanceIssues($issues, $boardId, $summary['leaf_counts']);
        self::addUnknownDepthIssue($issues, $boardId, $summary['depth_unknown']);
        self::addAreaBalanceIssues($issues, $boardId, $summary['official_area_balance']);
        self::addConcentrationIssues($issues, $boardId, $summary['concentration']);
    }

    private static function addTaxonomyGranularityIssue(array &$issues, string $boardId, array $granularity): void
    {
        if (($granularity['status'] ?? '') !== 'REVIEW') return;
        self::issue(
            $issues,
            'REVIEW',
            'TAXONOMY',
            'TAXONOMY_GRANULARITY_REVIEW',
            $boardId,
            [],
            'Taxonomy shape is a coarse/masked signal that requires editorial review.',
            'Review board subcompetencies before selecting a large content expansion.',
            ['board_id' => $boardId] + $granularity
        );
    }

    private static function addLeafBalanceIssues(array &$issues, string $boardId, array $activeLeaves): void
    {
        foreach ($activeLeaves as $leaf => $count) {
            if ($count === 0) {
                self::issue($issues, 'WARN', 'BALANCE', 'ACTIVE_ZERO_LEAF', (string) $leaf, [], 'Active exam leaf has no canonical questions.', 'Prioritize bounded content planning for this active leaf; do not treat structural parent nodes as zero pools.', ['board_id' => $boardId, 'count' => 0]);
            }
        }
        foreach ([5 => 'ACTIVE_UNDER5_LEAF', 10 => 'ACTIVE_UNDER10_LEAF', 20 => 'ACTIVE_UNDER20_LEAF'] as $threshold => $code) {
            foreach ($activeLeaves as $leaf => $count) {
                if ($count < $threshold) {
                    self::issue($issues, 'WARN', 'BALANCE', $code, (string) $leaf, [], 'Active exam leaf is thin at the current repository size.', 'Consider the leaf in future content planning; this is not a content-integrity failure.', ['board_id' => $boardId, 'count' => $count, 'threshold' => $threshold]);
                }
            }
        }
    }

    private static function addUnknownDepthIssue(array &$issues, string $boardId, int $unknownDepth): void
    {
        if ($unknownDepth > 0) {
            self::issue($issues, 'WARN', 'DEPTH_COVERAGE', 'UNKNOWN_DEPTH', $boardId, [], 'Some active questions do not carry deterministic depth metadata.', 'Review depth metadata in a bounded audit; do not infer mathematical correctness.', ['board_id' => $boardId, 'unknown_count' => $unknownDepth]);
        }
    }

    private static function addAreaBalanceIssues(array &$issues, string $boardId, array $areas): void
    {
        foreach ($areas as $area) {
            if (($area['warning'] ?? false) === true) {
                self::issue($issues, 'WARN', 'BALANCE', 'OFFICIAL_AREA_IMBALANCE', (string) ($area['area_id'] ?? ''), [], 'Actual repository share differs materially from the documented target weight.', 'Use the delta for future planning; do not delete valid content to rebalance.', $area + ['board_id' => $boardId]);
            }
        }
    }

    private static function addConcentrationIssues(array &$issues, string $boardId, array $concentration): void
    {
        foreach ($concentration as $family => $metric) {
            if (($metric['status'] ?? '') !== 'WARN') continue;
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
        $state = [
            'portfolio' => ['eligible' => 0, 'derivable' => 0, 'justified_exclusion' => 0, 'nonderivable_defect' => 0, 'duplicate_card_key' => 0, 'malformed_card' => 0, 'broken_card_source' => 0, 'invalid_card_key' => 0, 'invalid_card_key_ids' => [], 'malformed_card_ids' => [], 'broken_card_source_ids' => []],
            'by_board' => [],
            'keys' => [],
            'status_checks' => 0,
            'derivation_calls' => 0,
            'card_validations' => 0,
        ];
        foreach ($questions as $question) {
            self::accumulateFlashcardQuestion($state, $question, $deriver, $profiler);
        }
        self::accumulateDuplicateCardKeys($state);
        if ($profiler !== null) {
            $profiler->increment('flashcard_status_checks', $state['status_checks']);
            $profiler->increment('flashcard_derivation_calls', $state['derivation_calls']);
            $profiler->increment('flashcard_card_validations', $state['card_validations']);
            $profiler->increment('duplicate_check_passes');
        }
        return ['portfolio' => $state['portfolio'], 'by_board' => $state['by_board']];
    }

    private static function accumulateFlashcardQuestion(
        array &$state,
        array $question,
        FlashcardDerivationService $deriver,
        ?RepositoryHealthProfiler $profiler
    ): void {
        $questionId = self::id($question);
        $board = self::questionBoard($question);
        $state['by_board'][$board] ??= self::emptyFlashcardBoardMetrics();
        if ($profiler !== null) $state['status_checks']++;
        $derivation = $deriver->deriveWithStatus($question);
        $status = $derivation['status'];
        if (($status['eligible'] ?? false) !== true) return;

        $state['portfolio']['eligible']++;
        $state['by_board'][$board]['eligible']++;
        if ($profiler !== null) $state['derivation_calls']++;
        $card = $derivation['card'];
        if (is_array($card)) {
            if ($profiler !== null) $state['card_validations']++;
            self::accumulateFlashcardCard($state, $questionId, $board, $card);
        } elseif (($status['exclusion'] ?? null) !== null) {
            $state['portfolio']['justified_exclusion']++;
            $state['by_board'][$board]['justified_exclusion']++;
        } else {
            $state['portfolio']['nonderivable_defect']++;
            $state['by_board'][$board]['nonderivable_defect']++;
        }
    }

    private static function emptyFlashcardBoardMetrics(): array
    {
        return ['eligible' => 0, 'derivable' => 0, 'justified_exclusion' => 0, 'nonderivable_defect' => 0, 'duplicate_card_key' => 0, 'malformed_card' => 0, 'broken_card_source' => 0, 'invalid_card_key' => 0];
    }

    private static function accumulateFlashcardCard(array &$state, string $questionId, string $board, array $card): void
    {
        $state['portfolio']['derivable']++;
        $state['by_board'][$board]['derivable']++;
        $key = (string) ($card['card_key'] ?? '');
        if ($key !== '') $state['keys'][$key][] = [$questionId, $board];
        $errors = \App\Services\Flashcard\FlashcardCardValidator::validate($card);
        if ($errors !== []) self::recordFlashcardCardErrors($state, $questionId, $board, $errors);
    }

    private static function recordFlashcardCardErrors(array &$state, string $questionId, string $board, array $errors): void
    {
        $state['portfolio']['malformed_card']++;
        $state['by_board'][$board]['malformed_card']++;
        $state['portfolio']['malformed_card_ids'][] = $questionId;
        if (in_array('INVALID_CARD_KEY', $errors, true)) {
            $state['portfolio']['invalid_card_key']++;
            $state['portfolio']['invalid_card_key_ids'][] = $questionId;
            $state['by_board'][$board]['invalid_card_key']++;
        }
        if (in_array('BROKEN_CARD_SOURCE', $errors, true)) {
            $state['portfolio']['broken_card_source']++;
            $state['portfolio']['broken_card_source_ids'][] = $questionId;
            $state['by_board'][$board]['broken_card_source']++;
        }
    }

    private static function accumulateDuplicateCardKeys(array &$state): void
    {
        foreach ($state['keys'] as $records) {
            if (count($records) <= 1) continue;
            $state['portfolio']['duplicate_card_key']++;
            foreach ($records as [, $board]) $state['by_board'][$board]['duplicate_card_key']++;
        }
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
    private static function statistics(
        array $questions,
        array $issues,
        array $boards,
        array $candidateQuestionsByBoard,
        array $collections,
        QuestionEligibilityIndex $eligibilityIndex,
        ?RepositoryHealthProfiler $profiler
    ): RepositoryStatistics
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
        $coverageProfileHook = $profiler === null ? null : static function (string $operation, int $startedAt, array $checkCodes = []) use ($profiler): void {
            $profiler->recordDuration($operation, $startedAt, $checkCodes);
        };
        $coverageCounterHook = $profiler === null ? null : static function (string $counter, int $amount) use ($profiler): void {
            $profiler->increment($counter, $amount);
        };
        $statistics->coverage = QuestionCoverageService::analyze(
            $questions,
            $collections['boards'] ?? [],
            $collections['subjects'] ?? [],
            $collections['board-subjects'] ?? [],
            $collections['taxonomy/domains'] ?? [],
            $collections['taxonomy/topics'] ?? [],
            $collections['taxonomy/concepts'] ?? [],
            $eligibilityIndex,
            $candidateQuestionsByBoard,
            $coverageProfileHook,
            $coverageCounterHook
        );
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
        $blueprint = self::officialBlueprintForBoard($boardId, $collections);
        if ($blueprint === null) return [];
        $weights = $blueprint['official_weights'];
        $areaSources = self::officialAreaSources($blueprint, $weights);
        if ($areaSources === []) return [];
        $coverageAreas = self::coverageAreaSources($boardId, $areaSources, $collections);
        $counts = self::officialAreaQuestionCounts($boardId, $questions, $areaSources, $coverageAreas);
        return self::officialAreaBalanceResults($blueprint, $weights, $areaSources, $counts);
    }

    private static function officialBlueprintForBoard(string $boardId, array $collections): ?array
    {
        foreach ((array) ($collections['blueprints'] ?? []) as $candidate) {
            if (!is_array($candidate)
                || self::normalizeBoard($candidate['board_id'] ?? $candidate['board'] ?? '') !== $boardId
                || ($candidate['status'] ?? 'active') !== 'active') {
                continue;
            }
            $weights = is_array($candidate['official_weights'] ?? null) ? $candidate['official_weights'] : [];
            if ($weights !== [] && ($candidate['official_weighting'] ?? false) === true) return $candidate;
        }
        return null;
    }

    private static function officialAreaSources(array $blueprint, array $weights): array
    {
        $areaSources = [];
        foreach ((array) ($blueprint['sections'] ?? []) as $section) {
            if (!is_array($section)) continue;
            $sectionId = (string) ($section['id'] ?? '');
            $target = $section['target_percent'] ?? ($weights[$sectionId] ?? null);
            if ($sectionId !== '' && is_numeric($target)) {
                $areaSources[$sectionId] = (string) ($section['domain_id'] ?? $sectionId);
            }
        }
        if ($areaSources === []) {
            foreach (array_keys($weights) as $area) $areaSources[(string) $area] = (string) $area;
        }
        foreach ((array) ($blueprint['official_areas'] ?? []) as $area) {
            if (!is_array($area)) continue;
            $areaId = trim((string) ($area['id'] ?? ''));
            if ($areaId !== '' && !isset($areaSources[$areaId]) && isset($weights[$areaId])) {
                $areaSources[$areaId] = $areaId;
            }
        }
        return $areaSources;
    }

    private static function coverageAreaSources(string $boardId, array $areaSources, array $collections): array
    {
        $coverageAreaByLeaf = [];
        foreach ((array) ($collections['board-concept-coverage'] ?? []) as $coverage) {
            if (!is_array($coverage)
                || self::normalizeBoard($coverage['board_id'] ?? $coverage['board'] ?? '') !== $boardId) {
                continue;
            }
            $leafId = trim((string) ($coverage['concept_id'] ?? ''));
            $areaId = trim((string) ($coverage['blueprint_node_id'] ?? $coverage['official_area_id'] ?? ''));
            if ($leafId !== '' && $areaId !== '' && isset($areaSources[$areaId])) {
                $coverageAreaByLeaf[$leafId] = $areaId;
            }
        }
        return $coverageAreaByLeaf;
    }

    private static function officialAreaQuestionCounts(
        string $boardId,
        array $questions,
        array $areaSources,
        array $coverageAreas
    ): array {
        $counts = array_fill_keys(array_keys($areaSources), 0);
        foreach ($questions as $question) {
            if (self::questionBoard($question) !== $boardId) continue;
            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            $leafId = self::leafId($question);
            $candidates = array_values(array_filter([
                $coverageAreas[$leafId] ?? null,
                $taxonomy['official_area_id'] ?? null,
                $taxonomy['blueprint_node_id'] ?? null,
                $taxonomy['domain_id'] ?? null,
                $taxonomy['subject_id'] ?? null,
            ], static fn(mixed $value): bool => trim((string) $value) !== ''));
            $candidateSet = array_fill_keys(array_map('strval', $candidates), true);
            foreach ($areaSources as $outputArea => $sourceArea) {
                if (isset($candidateSet[(string) $outputArea]) || isset($candidateSet[(string) $sourceArea])) {
                    $counts[$outputArea]++;
                    break;
                }
            }
        }
        return $counts;
    }

    private static function officialAreaBalanceResults(
        array $blueprint,
        array $weights,
        array $areaSources,
        array $counts
    ): array {
        $total = array_sum($counts);
        $result = [];
        foreach ($areaSources as $area => $_sourceArea) {
            $target = self::officialAreaTarget($blueprint, $weights, (string) $area);
            $actual = $total > 0 ? ($counts[(string) $area] ?? 0) / $total * 100 : 0;
            $delta = $actual - (float) $target;
            $result[] = ['area_id' => (string) $area, 'actual_share' => round($actual, 2), 'target_share' => (float) $target, 'delta' => round($delta, 2), 'warning' => abs($delta) > 10];
        }
        return $result;
    }

    private static function officialAreaTarget(array $blueprint, array $weights, string $area): mixed
    {
        foreach ((array) ($blueprint['sections'] ?? []) as $section) {
            if (is_array($section)
                && (string) ($section['id'] ?? '') === $area
                && is_numeric($section['target_percent'] ?? null)) {
                return $section['target_percent'];
            }
        }
        return $weights[$area] ?? 0;
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
