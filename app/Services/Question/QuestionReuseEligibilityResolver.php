<?php

declare(strict_types=1);

namespace App\Services\Question;

use App\Core\App;
use App\Services\Shared\TaxonomyStorageService;

final class QuestionReuseEligibilityResolver
{
    /**
     * Resolve the current direct result and, optionally, future derived paths.
     * This is intentionally not wired into board selection until AUTO metadata
     * and provenance are available.
     */
    public static function resolve(
        array $question,
        bool $dryRun = true,
        ?array $directMappings = null,
        ?array $overrides = null,
        ?array $coverageRows = null
    ): array
    {
        $questionId = (string)($question['id'] ?? '');
        $direct = [];
        foreach ($directMappings ?? QuestionEligibilityService::editorMappings($question) as $mapping) {
            $board = (string)($mapping['board_id'] ?? '');
            if ($board !== '') $direct[self::board($board)] = $mapping;
        }

        $questionOverrides = [];
        foreach ($overrides ?? App::storage()->all('question-reuse-overrides') as $override) {
            if (is_array($override) && (string)($override['question_id'] ?? '') === $questionId) {
                $questionOverrides[] = $override;
            }
        }

        $excluded = [];
        $included = [];
        foreach ($questionOverrides as $override) {
            $board = self::board((string)($override['board_id'] ?? ''));
            if (($override['override_type'] ?? '') === 'EXCLUDE') $excluded[$board] = true;
            if (($override['override_type'] ?? '') === 'INCLUDE') $included[$board] = $override;
        }

        $effective = [];
        foreach ($direct as $board => $mapping) {
            if (!isset($excluded[$board])) $effective[$board] = ['path' => 'DIRECT', 'mapping' => $mapping];
        }
        foreach ($included as $board => $override) {
            if (!isset($excluded[$board]) && self::validOverride($override)) {
                $effective[$board] = ['path' => 'MANUAL_INCLUDE', 'mapping' => $override['placement'] ?? []];
            }
        }

        $derived = [];
        $coverage = $coverageRows ?? App::storage()->all('board-concept-coverage');
        if ($dryRun && (($question['reuse']['reuse_policy'] ?? '') === 'AUTO')
            && ($question['reuse']['required_depth'] ?? 'UNKNOWN') !== 'UNKNOWN') {
            $conceptId = (string)(($question['taxonomy']['concept_id'] ?? ''));
            foreach ($coverage as $row) {
                if (!is_array($row)
                    || (string)($row['concept_id'] ?? '') !== $conceptId
                    || ($row['reuse_policy'] ?? '') !== 'AUTO'
                    || ($row['max_depth'] ?? 'UNKNOWN') === 'UNKNOWN'
                    || ($row['status'] ?? '') !== 'VERIFIED'
                    || self::validateCoverage($row) !== []) {
                    continue;
                }
                $board = self::board((string)($row['board_id'] ?? ''));
                if (!isset($excluded[$board]) && !isset($effective[$board])) {
                    $derived[$board] = ['path' => 'CONCEPT_INHERITED', 'mapping' => $row];
                }
            }
        }

        return [
            'question_id' => $questionId,
            'direct' => $direct,
            'overrides' => $questionOverrides,
            'effective' => $effective,
            'derived_dry_run' => $derived,
            'production_auto_inheritance_enabled' => false,
        ];
    }

    public static function validateCoverage(array $coverage): array
    {
        $errors = [];
        $board = self::board((string)($coverage['board_id'] ?? ''));
        $subject = (string)($coverage['subject_id'] ?? '');
        $domain = (string)($coverage['domain_id'] ?? '');
        $topic = (string)($coverage['topic_id'] ?? '');
        $concept = (string)($coverage['concept_id'] ?? '');
        if ($board === '' || TaxonomyStorageService::find('boards', $board) === null) $errors[] = 'INVALID_COVERAGE_BOARD';
        if ($subject === '' || !TaxonomyStorageService::hasBoardSubject($board, $subject)) $errors[] = 'INVALID_COVERAGE_SUBJECT';
        $domainRecord = $domain === '' ? null : TaxonomyStorageService::find('taxonomy/domains', $domain);
        $topicRecord = $topic === '' ? null : TaxonomyStorageService::find('taxonomy/topics', $topic);
        $conceptRecord = $concept === '' ? null : TaxonomyStorageService::find('taxonomy/concepts', $concept);
        if ($domain !== '' && ($domainRecord === null || (string)($domainRecord['subject_id'] ?? '') !== $subject)) {
            $errors[] = 'INVALID_COVERAGE_DOMAIN';
        }
        if ($topic !== '' && ($topicRecord === null || $domain === '' || (string)($topicRecord['domain_id'] ?? '') !== $domain)) {
            $errors[] = 'INVALID_COVERAGE_TOPIC';
        }
        if ($conceptRecord === null || ($topic !== '' && (string)($conceptRecord['topic_id'] ?? '') !== $topic)) {
            $errors[] = 'INVALID_COVERAGE_CONCEPT';
        }
        $blueprintNode = (string)($coverage['blueprint_node_id'] ?? '');
        if ($blueprintNode !== '' && !self::blueprintContains($board, $blueprintNode, $subject)) {
            $errors[] = 'INVALID_COVERAGE_PLACEMENT';
        }
        if (!in_array($coverage['max_depth'] ?? null, [1, 2, 3, 4, 5, 'UNKNOWN'], true)) $errors[] = 'INVALID_COVERAGE_DEPTH';
        if (!in_array($coverage['reuse_policy'] ?? null, QuestionReuseMetadataService::POLICIES, true)) $errors[] = 'INVALID_COVERAGE_POLICY';
        if (($coverage['reuse_policy'] ?? '') === 'AUTO' && ($coverage['max_depth'] ?? 'UNKNOWN') === 'UNKNOWN') $errors[] = 'AUTO_COVERAGE_WITH_UNKNOWN_DEPTH';
        if (!in_array($coverage['status'] ?? null, ['VERIFIED', 'INFERRED', 'REVIEW_REQUIRED'], true)) $errors[] = 'INVALID_COVERAGE_STATUS';
        foreach (QuestionReuseMetadataService::validateProvenance($coverage['provenance'] ?? null) as $error) {
            $errors[] = 'COVERAGE_' . $error;
        }
        return array_values(array_unique($errors));
    }

    public static function validateEquivalence(array $equivalence): array
    {
        $errors = [];
        $source = (string)($equivalence['source_concept_id'] ?? '');
        $target = (string)($equivalence['target_concept_id'] ?? '');
        if ($source === '' || TaxonomyStorageService::find('taxonomy/concepts', $source) === null) $errors[] = 'INVALID_EQUIVALENCE_SOURCE';
        if ($target === '' || TaxonomyStorageService::find('taxonomy/concepts', $target) === null) $errors[] = 'INVALID_EQUIVALENCE_TARGET';
        if ($source !== '' && $source === $target) $errors[] = 'SELF_EQUIVALENCE';
        if (!in_array($equivalence['relation_type'] ?? null, ['EXACT', 'COMPATIBLE', 'PARTIAL', 'RELATED', 'CONFLICTING'], true)) $errors[] = 'INVALID_EQUIVALENCE_RELATION';
        if (!in_array($equivalence['status'] ?? null, ['VERIFIED', 'INFERRED', 'REVIEW_REQUIRED'], true)) $errors[] = 'INVALID_EQUIVALENCE_STATUS';
        if (in_array($equivalence['relation_type'] ?? null, ['PARTIAL', 'RELATED', 'CONFLICTING'], true)
            && ($equivalence['reuse_policy'] ?? 'REVIEW') === 'AUTO') {
            $errors[] = 'AUTO_UNSAFE_EQUIVALENCE';
        }
        foreach (QuestionReuseMetadataService::validateProvenance($equivalence['provenance'] ?? null) as $error) {
            $errors[] = 'EQUIVALENCE_' . $error;
        }
        return array_values(array_unique($errors));
    }

    public static function validateOverride(array $override): array
    {
        $errors = [];
        $questionId = (string)($override['question_id'] ?? '');
        $board = self::board((string)($override['board_id'] ?? ''));
        $type = $override['override_type'] ?? null;
        if ($questionId === '' || App::storage()->find('questions', $questionId) === null) {
            $errors[] = 'INVALID_OVERRIDE_QUESTION';
        }
        if ($board === '' || TaxonomyStorageService::find('boards', $board) === null) {
            $errors[] = 'INVALID_OVERRIDE_BOARD';
        }
        if (!in_array($type, ['INCLUDE', 'EXCLUDE'], true)) {
            $errors[] = 'INVALID_OVERRIDE_TYPE';
        }
        if ($type === 'INCLUDE') {
            foreach (self::validatePlacement($board, is_array($override['placement'] ?? null) ? $override['placement'] : []) as $error) {
                $errors[] = 'OVERRIDE_' . $error;
            }
        }
        if (!is_string($override['reason'] ?? null) || trim($override['reason']) === '') {
            $errors[] = 'MISSING_OVERRIDE_REASON';
        }
        foreach (QuestionReuseMetadataService::validateProvenance($override['provenance'] ?? null) as $error) {
            $errors[] = 'OVERRIDE_' . $error;
        }
        return array_values(array_unique($errors));
    }

    public static function validateRequiredConcept(array $requirement): array
    {
        $errors = [];
        $questionId = (string)($requirement['question_id'] ?? '');
        $conceptId = (string)($requirement['concept_id'] ?? '');
        if ($questionId === '' || App::storage()->find('questions', $questionId) === null) {
            $errors[] = 'INVALID_REQUIRED_QUESTION';
        }
        if ($conceptId === '' || TaxonomyStorageService::find('taxonomy/concepts', $conceptId) === null) {
            $errors[] = 'INVALID_REQUIRED_CONCEPT';
        }
        if (!in_array($requirement['requirement_type'] ?? null, ['REQUIRED', 'ASSUMED_FOUNDATION'], true)) {
            $errors[] = 'INVALID_REQUIRED_TYPE';
        }
        return array_values(array_unique($errors));
    }

    public static function validBlueprintPlacement(string $board, string $node, string $subject): bool
    {
        return self::blueprintContains(self::board($board), $node, $subject);
    }

    /** Persist one explicit override without touching legacy eligibility rows. */
    public static function saveOverride(array $override): array
    {
        $errors = self::validateOverride($override);
        if ($errors !== []) {
            return ['valid' => false, 'errors' => $errors];
        }

        $questionId = (string)$override['question_id'];
        $board = self::board((string)$override['board_id']);
        $override['question_id'] = $questionId;
        $override['board_id'] = $board;
        $records = [];
        $replaced = false;
        foreach (App::storage()->all('question-reuse-overrides') as $record) {
            if (!is_array($record)) continue;
            if ((string)($record['question_id'] ?? '') === $questionId
                && self::board((string)($record['board_id'] ?? '')) === $board) {
                if (!$replaced) {
                    $records[] = $override;
                    $replaced = true;
                }
                continue;
            }
            $records[] = $record;
        }
        if (!$replaced) {
            $override['id'] = (string)($override['id'] ?? 'override-' . hash('sha256', $questionId . '|' . $board));
            $records[] = $override;
        }
        App::storage()->replace('question-reuse-overrides', $records);
        return ['valid' => true, 'override' => $override];
    }

    public static function removeOverride(string $questionId, string $board): bool
    {
        $questionId = trim($questionId);
        $board = self::board($board);
        $before = App::storage()->all('question-reuse-overrides');
        $after = array_values(array_filter($before, static fn(mixed $record): bool =>
            !is_array($record)
            || (string)($record['question_id'] ?? '') !== $questionId
            || self::board((string)($record['board_id'] ?? '')) !== $board
        ));
        if (count($after) === count($before)) return false;
        App::storage()->replace('question-reuse-overrides', $after);
        return true;
    }

    private static function validOverride(array $override): bool
    {
        return self::validateOverride($override) === [];
    }

    private static function validatePlacement(string $board, array $placement): array
    {
        $subject = (string)($placement['subject_id'] ?? '');
        $domain = (string)($placement['domain_id'] ?? '');
        $topic = (string)($placement['topic_id'] ?? '');
        $concept = (string)($placement['concept_id'] ?? '');
        $node = (string)($placement['blueprint_node'] ?? '');
        $errors = [];
        if ($board === '' || TaxonomyStorageService::find('boards', $board) === null) $errors[] = 'INVALID_TARGET_BOARD';
        if ($subject === '' || !TaxonomyStorageService::hasBoardSubject($board, $subject)) $errors[] = 'INVALID_TARGET_SUBJECT';
        $domainRecord = TaxonomyStorageService::find('taxonomy/domains', $domain);
        $topicRecord = TaxonomyStorageService::find('taxonomy/topics', $topic);
        $conceptRecord = TaxonomyStorageService::find('taxonomy/concepts', $concept);
        if ($domain !== '' && ($domainRecord === null || (string)($domainRecord['subject_id'] ?? '') !== $subject)) $errors[] = 'INVALID_TARGET_DOMAIN';
        if ($topic !== '' && ($topicRecord === null || $domain === '' || (string)($topicRecord['domain_id'] ?? '') !== $domain)) $errors[] = 'INVALID_TARGET_TOPIC';
        if ($concept !== '' && ($conceptRecord === null || $topic === '' || (string)($conceptRecord['topic_id'] ?? '') !== $topic)) $errors[] = 'INVALID_TARGET_CONCEPT';
        if (!self::blueprintContains($board, $node, $subject)) $errors[] = 'INVALID_TARGET_PLACEMENT';
        return array_values(array_unique($errors));
    }

    private static function blueprintContains(string $board, string $node, string $subject): bool
    {
        if ($node === '') return false;
        foreach (App::storage()->all('blueprints') as $blueprint) {
            if (!is_array($blueprint)
                || self::board((string)($blueprint['board_id'] ?? $blueprint['board'] ?? '')) !== $board
                || (($blueprint['status'] ?? 'active') !== 'active')) continue;
            foreach (array_merge((array)($blueprint['sections'] ?? []), (array)($blueprint['official_areas'] ?? [])) as $item) {
                if (!is_array($item)) continue;
                if (($node === (string)($item['id'] ?? '') || $node === (string)($item['taxonomy_subject'] ?? ''))
                    && (!is_array($item['subjects'] ?? null) || in_array($subject, $item['subjects'], true))) return true;
            }
        }
        return false;
    }

    private static function board(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace(['_', ' '], '-', $value);
        return ['cse' => 'civil-service', 'civilservice' => 'civil-service'][$value] ?? $value;
    }
}
