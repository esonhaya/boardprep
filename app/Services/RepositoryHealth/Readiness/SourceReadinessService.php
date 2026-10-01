<?php

declare(strict_types=1);

namespace App\Services\RepositoryHealth\Readiness;

use App\Core\App;

/** Permanent source-readiness facade used by health, authoring, and UI. */
final class SourceReadinessService
{
    /** @return array<int, array<string, mixed>> */
    public static function records(?array $collections = null): array
    {
        if ($collections !== null) {
            return array_values(array_filter($collections['source-readiness'] ?? [], 'is_array'));
        }
        return array_values(array_filter(App::storage()->all('source-readiness'), 'is_array'));
    }

    /** @param array<string, array> $collections @param array<string, array> $boardSummaries */
    public static function validateCollections(array $collections, array $boardSummaries = []): array
    {
        // Older isolated health fixtures may not opt into the Batch641
        // contract yet. Production contexts always include this collection
        // through RepositoryContextFactory once the snapshot is initialized.
        if (!array_key_exists('source-readiness', $collections)) {
            return ['valid' => true, 'errors' => [], 'records' => []];
        }
        $nativeCounts = self::nativeLeafCounts($collections, 'criminologist');
        $activeLeaves = array_keys($nativeCounts);
        if (isset($boardSummaries['criminologist']['leaf_counts']) && is_array($boardSummaries['criminologist']['leaf_counts'])) {
            $activeLeaves = array_keys($boardSummaries['criminologist']['leaf_counts']);
            foreach ($activeLeaves as $leafId) {
                $nativeCounts[$leafId] = (int) ($boardSummaries['criminologist']['leaf_counts'][$leafId] ?? 0);
            }
        }

        return SourceReadinessValidator::validate(
            self::records($collections),
            [
                'sources' => $collections['sources'] ?? [],
                'study_materials' => $collections['study-materials'] ?? [],
                'native_counts' => $nativeCounts,
                'active_leaf_ids' => $activeLeaves,
            ]
        );
    }

    /** @param array<int, array<string, mixed>> $records @param array<string, mixed> $validation */
    public static function summary(array $records, array $validation = []): array
    {
        $counts = ['READY' => 0, 'READY_WITH_LIMITS' => 0, 'REVIEW' => 0, 'DEFER' => 0];
        $authorities = array_fill_keys(SourceReadinessRecord::AUTHORITIES, 0);
        $coverage = array_fill_keys(SourceReadinessRecord::STUDY_COVERAGE, 0);
        $byLeaf = [];
        foreach ($records as $record) {
            if (!is_array($record)) continue;
            $readiness = (string) ($record['readiness'] ?? '');
            if (isset($counts[$readiness])) $counts[$readiness]++;
            $authority = (string) ($record['source_authority'] ?? '');
            if (isset($authorities[$authority])) $authorities[$authority]++;
            $studyCoverage = (string) ($record['study_coverage'] ?? '');
            if (isset($coverage[$studyCoverage])) $coverage[$studyCoverage]++;
            $leafId = (string) ($record['leaf_id'] ?? '');
            if ($leafId !== '') $byLeaf[$leafId] = $record;
        }
        ksort($byLeaf, SORT_STRING);
        return [
            'record_count' => count($records),
            'valid' => ($validation['valid'] ?? false) === true,
            'error_count' => count($validation['errors'] ?? []),
            'readiness_counts' => $counts,
            'source_authority_counts' => $authorities,
            'study_coverage_counts' => $coverage,
            'records' => array_values($byLeaf),
        ];
    }

    /** @param array<string, array> $collections */
    public static function validateQuestionForCreate(array $question, ?array $collections = null): array
    {
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        if (strtolower(trim((string) ($taxonomy['board_id'] ?? $question['board'] ?? ''))) !== 'criminologist') {
            return ['valid' => true, 'errors' => []];
        }

        $collections ??= self::collections();
        $records = self::records($collections);
        $byLeaf = [];
        foreach ($records as $record) {
            if (is_array($record)) $byLeaf[(string) ($record['leaf_id'] ?? '')] = $record;
        }
        $leafId = (string) ($taxonomy['concept_id'] ?? '');
        $record = $byLeaf[$leafId] ?? null;
        $errors = [];
        if (!is_array($record)) {
            return ['valid' => false, 'errors' => ['SOURCE_READINESS_MISSING_RECORD']];
        }
        if (!in_array((string) ($record['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true)) {
            $errors[] = 'SOURCE_READINESS_NOT_AUTHORABLE';
        }
        $currentCount = self::nativeLeafCounts($collections, 'criminologist')[$leafId] ?? 0;
        $maxNew = (int) ($record['max_new_questions'] ?? 0);
        $recordCount = (int) ($record['current_question_count'] ?? 0);
        if ($currentCount >= $recordCount + $maxNew) {
            $errors[] = 'SOURCE_READINESS_HEADROOM_EXHAUSTED';
        }

        $depth = $question['reuse']['required_depth'] ?? $question['depth'] ?? null;
        if (!is_int($depth) || $depth < (int) ($record['supported_depth_min'] ?? 1) || $depth > (int) ($record['supported_depth_max'] ?? 5)) {
            $errors[] = 'SOURCE_READINESS_DEPTH_OUT_OF_RANGE';
        }
        $scope = $question['source_scope'] ?? $question['scope'] ?? null;
        if (!is_array($scope) || $scope === []) {
            $errors[] = 'SOURCE_READINESS_SCOPE_REQUIRED';
        } else {
            foreach ($scope as $value) {
                $value = (string) $value;
                if (!in_array($value, (array) ($record['allowed_scope'] ?? []), true) || in_array($value, (array) ($record['excluded_scope'] ?? []), true)) {
                    $errors[] = 'SOURCE_READINESS_SCOPE_OUTSIDE_BOUNDARY';
                    break;
                }
            }
        }
        $sourceIds = is_array($question['source_ids'] ?? null) ? array_map('strval', $question['source_ids']) : [];
        if ($sourceIds === []) {
            $errors[] = 'SOURCE_READINESS_SOURCE_CHAIN_REQUIRED';
        } elseif (array_diff($sourceIds, array_map('strval', (array) ($record['source_ids'] ?? []))) !== []) {
            $errors[] = 'SOURCE_READINESS_SOURCE_OUTSIDE_BOUNDARY';
        }
        $studyIds = $question['study_material_ids'] ?? null;
        if ($studyIds === null && isset($question['study_material_id'])) $studyIds = [$question['study_material_id']];
        if (!is_array($studyIds) || $studyIds === []) {
            $errors[] = 'SOURCE_READINESS_STUDY_CHAIN_REQUIRED';
        } elseif (array_diff(array_map('strval', $studyIds), array_map('strval', (array) ($record['study_material_ids'] ?? []))) !== []) {
            $errors[] = 'SOURCE_READINESS_STUDY_OUTSIDE_BOUNDARY';
        }

        return ['valid' => $errors === [], 'errors' => array_values(array_unique($errors))];
    }

    /** @param array<string, array> $collections */
    public static function nativeLeafCounts(array $collections, string $boardId): array
    {
        $counts = [];
        foreach ((array) ($collections['board-concept-coverage'] ?? []) as $coverage) {
            if (!is_array($coverage) || strtolower((string) ($coverage['board_id'] ?? '')) !== strtolower($boardId)) continue;
            $leafId = trim((string) ($coverage['concept_id'] ?? ''));
            if ($leafId !== '') $counts[$leafId] = 0;
        }
        foreach ((array) ($collections['questions'] ?? []) as $question) {
            if (!is_array($question) || strtolower((string) ($question['taxonomy']['board_id'] ?? '')) !== strtolower($boardId)) continue;
            if (strtolower((string) ($question['status'] ?? 'active')) === 'archived') continue;
            $leafId = trim((string) ($question['taxonomy']['concept_id'] ?? ''));
            if ($leafId !== '') $counts[$leafId] = ($counts[$leafId] ?? 0) + 1;
        }
        ksort($counts, SORT_STRING);
        return $counts;
    }

    /** @return array<string, array> */
    private static function collections(): array
    {
        $names = ['questions', 'boards', 'subjects', 'board-subjects', 'blueprints', 'taxonomy/domains', 'taxonomy/topics', 'taxonomy/concepts', 'question-eligibility', 'concept-derived-eligibility', 'question-reuse-overrides', 'question-reuse-metadata', 'board-concept-coverage', 'study-materials', 'study-question-links', 'sources', 'content-fit-audits', 'flashcard-exclusions', 'source-readiness'];
        $collections = [];
        foreach ($names as $name) $collections[$name] = App::storage()->all($name);
        return $collections;
    }
}
