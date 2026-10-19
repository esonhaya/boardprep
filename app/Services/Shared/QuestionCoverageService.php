<?php

declare(strict_types=1);

namespace App\Services\Shared;

use App\Core\App;
use App\Repositories\QuestionRepository;
use App\Services\Question\QuestionEligibilityIndex;
use App\Services\Question\QuestionReuseEligibilityResolver;

final class QuestionCoverageService
{
    private const DIFFICULTIES = ['easy', 'medium', 'hard'];

    public static function analyzeRepository(): array
    {
        $storage = App::storage();
        $eligibilityIndex = new QuestionEligibilityIndex(
            $storage->all('question-eligibility'),
            $storage->all('blueprints'),
            QuestionReuseEligibilityResolver::productionDerivedRelationships(),
            $storage->all('question-reuse-overrides')
        );

        return self::analyze(
            (new QuestionRepository($storage))->all(),
            $storage->all('boards'),
            $storage->all('subjects'),
            $storage->all('board-subjects'),
            $storage->all('taxonomy/domains'),
            $storage->all('taxonomy/topics'),
            $storage->all('taxonomy/concepts'),
            $eligibilityIndex
        );
    }

    public static function analyze(
        array $questions,
        array $boards,
        array $subjects,
        array $boardSubjects,
        array $domains,
        array $topics,
        array $concepts,
        ?QuestionEligibilityIndex $eligibilityIndex = null,
        array $candidateQuestionsByBoard = [],
        ?callable $profileHook = null,
        ?callable $counterHook = null
    ): array {
        $catalog = self::catalogs($boards, $subjects, $domains, $topics, $concepts);
        $inventory = self::emptyInventory(count($questions));
        $issues = self::emptyIssues(
            $boards,
            $subjects,
            $boardSubjects,
            $domains,
            $topics,
            $concepts
        );
        $phaseStartedAt = $profileHook === null ? 0 : hrtime(true);
        // The previous implementation called QuestionPoolFilter once for
        // every question. That made the normal Doctor path quadratic over the
        // repository. The production filter is still the source of truth; we
        // simply materialize its no-exam eligible ID set once.
        $eligibleQuestionIds = [];
        foreach (\QuestionPoolFilter::filter(
            $questions,
            new \SelectionRequest('*', null, [], 1)
        ) as $eligibleQuestion) {
            if (is_array($eligibleQuestion) && is_scalar($eligibleQuestion['id'] ?? null)) {
                $eligibleQuestionIds[(string) $eligibleQuestion['id']] = true;
            }
        }
        self::finishProfilePhase($profileHook, 'coverage.inventory_eligibility_filter', $phaseStartedAt);
        if ($counterHook !== null) {
            $counterHook('coverage_inventory_filter_executions', 1);
            $counterHook('coverage_inventory_filter_question_visits', count($questions));
        }
        foreach ($questions as $question) {
            self::inspectQuestion($question, $catalog, $eligibleQuestionIds, $inventory, $issues);
        }
        self::finalizeReport($inventory, $issues);
        self::finishProfilePhase($profileHook, 'coverage.inventory_taxonomy_scan', $phaseStartedAt);
        $subjectDepth = self::subjectDepth(
            $questions,
            $boards,
            $eligibilityIndex,
            $candidateQuestionsByBoard,
            $counterHook
        );
        self::finishProfilePhase($profileHook, 'coverage.subject_depth', $phaseStartedAt);
        $blueprints = self::blueprints(
            $questions,
            $boards,
            $subjects,
            $boardSubjects,
            $eligibilityIndex,
            $candidateQuestionsByBoard,
            $counterHook
        );
        self::finishProfilePhase($profileHook, 'coverage.blueprint_availability', $phaseStartedAt);
        return [
            'inventory' => $inventory,
            'issues' => $issues,
            'subject_depth' => $subjectDepth,
            'blueprints' => $blueprints,
        ];
    }

    private static function finishProfilePhase(?callable $profileHook, string $operation, int &$phaseStartedAt): void
    {
        if ($profileHook === null) {
            return;
        }
        $profileHook($operation, $phaseStartedAt, []);
        $phaseStartedAt = hrtime(true);
    }

    /**
     * Compute the learner-facing subject depth through the same effective
     * eligibility resolver used for board practice. This is a planning signal,
     * not a claim that every board subject ought to have exactly 100 items.
     */
    private static function subjectDepth(
        array $questions,
        array $boards,
        ?QuestionEligibilityIndex $index,
        array $candidateQuestionsByBoard,
        ?callable $counterHook
    ): array
    {
        $target = 100;
        if ($index === null) {
            return ['target' => $target, 'active_board_subjects' => 0, 'below_target' => [], 'review_candidates' => []];
        }

        $byScope = self::subjectDepthByScope($questions, $boards, $index, $candidateQuestionsByBoard, $counterHook);
        return self::subjectDepthSummary($byScope, $target);
    }

    private static function subjectDepthByScope(
        array $questions,
        array $boards,
        QuestionEligibilityIndex $index,
        array $candidateQuestionsByBoard,
        ?callable $counterHook
    ): array {
        $byScope = [];
        foreach ($boards as $board) {
            if (!is_array($board)
                || !is_scalar($board['id'] ?? null)
                || strtolower(trim((string) ($board['status'] ?? 'active'))) !== 'active') {
                continue;
            }
            $boardId = strtolower(trim((string) $board['id']));
            $boardCode = strtolower(trim((string) ($board['code'] ?? '')));
            $boardQuestions = $candidateQuestionsByBoard[$boardId] ?? $questions;
            if ($counterHook !== null) {
                $counterHook('coverage_subject_depth_question_visits', count($boardQuestions));
                if (!array_key_exists($boardId, $candidateQuestionsByBoard)) {
                    $counterHook('coverage_candidate_fallbacks', 1);
                }
            }
            self::collectBoardSubjectDepth(
                $byScope,
                $boardQuestions,
                $boardId,
                $boardCode,
                $index
            );
        }

        return $byScope;
    }

    private static function collectBoardSubjectDepth(
        array &$byScope,
        array $questions,
        string $boardId,
        string $boardCode,
        QuestionEligibilityIndex $index
    ): void {
        foreach ($questions as $question) {
            if (!is_array($question)
                || strtolower(trim((string) ($question['status'] ?? 'active'))) !== 'active') {
                continue;
            }

            $placement = self::subjectDepthPlacement($question, $boardId, $boardCode, $index);
            if (!is_array($placement) || !is_scalar($placement['subject_id'] ?? null)) {
                continue;
            }
            self::recordSubjectDepthQuestion($byScope, $question, $boardId, (string) $placement['subject_id']);
        }
    }

    private static function subjectDepthPlacement(
        array $question,
        string $boardId,
        string $boardCode,
        QuestionEligibilityIndex $index
    ): ?array {
        $placement = $index->mayBeEligibleForExam($question, $boardId)
            ? $index->forExam($question, $boardId)
            : null;
        if ($placement === null && $boardCode !== ''
            && $index->mayBeEligibleForExam($question, $boardCode)) {
            $placement = $index->forExam($question, $boardCode);
        }

        return $placement;
    }

    private static function recordSubjectDepthQuestion(
        array &$byScope,
        array $question,
        string $boardId,
        string $subjectId
    ): void {
        $subjectId = strtolower(trim($subjectId));
        $questionId = is_scalar($question['id'] ?? null) ? trim((string) $question['id']) : '';
        if ($subjectId === '' || $questionId === '') {
            return;
        }

        $scope = $boardId . '|' . $subjectId;
        if (isset($byScope[$scope]['question_ids'][$questionId])) {
            return;
        }
        $byScope[$scope]['board_id'] = $boardId;
        $byScope[$scope]['subject_id'] = $subjectId;
        $byScope[$scope]['question_ids'][$questionId] = true;
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        $topic = trim((string) ($taxonomy['topic_id'] ?? $question['topic'] ?? ''));
        $concept = trim((string) ($taxonomy['concept_id'] ?? $question['concept'] ?? ''));
        if ($topic !== '') $byScope[$scope]['topics'][$topic] = true;
        if ($concept !== '') {
            $byScope[$scope]['concepts'][$concept] = true;
            $byScope[$scope]['concept_counts'][$concept] = ($byScope[$scope]['concept_counts'][$concept] ?? 0) + 1;
        }
    }

    private static function subjectDepthSummary(array $byScope, int $target): array
    {
        $below = [];
        $reviews = [];
        foreach ($byScope as $scope) {
            $count = count($scope['question_ids'] ?? []);
            $topicCount = count($scope['topics'] ?? []);
            $conceptCounts = (array) ($scope['concept_counts'] ?? []);
            $largestConcept = $conceptCounts === [] ? 0 : max($conceptCounts);
            $row = [
                'board_id' => $scope['board_id'],
                'subject_id' => $scope['subject_id'],
                'effective_eligible_question_count' => $count,
                'unique_concept_count' => count($scope['concepts'] ?? []),
                'unique_topic_count' => $topicCount,
                'deficit_to_target' => max(0, $target - $count),
                'largest_concept_count' => $largestConcept,
            ];
            if ($count < $target) $below[] = $row;
            if (($count >= 20 && $topicCount < 3) || ($count >= 20 && $largestConcept > $count * .4)) {
                $reviews[] = $row + ['review_type' => $topicCount < 3 && $count >= 20 ? 'SUBJECT_TOPIC_COVERAGE_GAP' : 'SUBJECT_CONCEPT_CONCENTRATION'];
            }
        }
        usort($below, static fn(array $a,array $b): int => [$a['deficit_to_target'] * -1,$a['board_id'],$a['subject_id']] <=> [$b['deficit_to_target'] * -1,$b['board_id'],$b['subject_id']]);
        usort($reviews, static fn(array $a,array $b): int => [$a['board_id'],$a['subject_id'],$a['review_type']] <=> [$b['board_id'],$b['subject_id'],$b['review_type']]);
        return ['target' => $target, 'active_board_subjects' => count($byScope), 'below_target' => $below, 'review_candidates' => $reviews];
    }

    private static function catalogs(array ...$groups): array
    {
        return array_combine(
            ['board', 'subject', 'domain', 'topic', 'concept'],
            array_map(static fn (array $records): array => self::catalog($records), $groups)
        );
    }

    private static function emptyInventory(int $total): array
    {
        return ['total' => $total, 'eligible' => 0, 'by_subject' => [], 'by_topic' => [], 'by_difficulty' => [], 'by_status' => []];
    }

    private static function emptyIssues(array ...$groups): array
    {
        return ['unknown_taxonomy' => [], 'aliases' => [], 'invalid_difficulty' => [], 'legacy_metadata' => [], 'ineligible' => [], 'taxonomy_orphans' => self::taxonomyOrphans(...$groups)];
    }

    private static function inspectQuestion(mixed $question, array $catalog, array $eligibleQuestionIds, array &$inventory, array &$issues): void
    {
        if (!is_array($question)) return;
        $id = is_scalar($question['id'] ?? null) ? (string) $question['id'] : '<unknown>';
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        if ($taxonomy === []) $issues['legacy_metadata'][] = $id;
        $canonical = [];
        foreach (['board', 'subject', 'domain', 'topic', 'concept'] as $dimension) {
            $value = $question[$dimension] ?? $taxonomy[$dimension . '_id'] ?? null;
            $match = self::canonical($value, $catalog[$dimension]);
            if ($match === null) {
                $issues['unknown_taxonomy'][] = ['question' => $id, 'dimension' => $dimension, 'value' => is_scalar($value) ? trim((string) $value) : ''];
                continue;
            }
            if (is_scalar($value) && trim((string) $value) !== $match['id']) $issues['aliases'][] = ['question' => $id, 'dimension' => $dimension, 'value' => trim((string) $value), 'canonical' => $match['id']];
            $canonical[$dimension] = $match;
        }
        $difficulty = is_scalar($question['difficulty'] ?? null) ? strtolower(trim((string) $question['difficulty'])) : '';
        if (!in_array($difficulty, self::DIFFICULTIES, true)) $issues['invalid_difficulty'][] = $id;
        $status = is_scalar($question['status'] ?? null) ? strtolower(trim((string) $question['status'])) : '';
        self::increment($inventory['by_status'], $status === '' ? '<missing>' : $status);
        $subject = self::fieldName($question, 'subject', $catalog['subject']);
        if ($subject === null || !self::eligible($question, $eligibleQuestionIds)) {
            $issues['ineligible'][] = $id;
            return;
        }
        $inventory['eligible']++;
        if (isset($canonical['subject'])) self::increment($inventory['by_subject'], $canonical['subject']['id']);
        if (isset($canonical['topic'])) self::increment($inventory['by_topic'], $canonical['topic']['id']);
        if (in_array($difficulty, self::DIFFICULTIES, true)) self::increment($inventory['by_difficulty'], $difficulty);
    }

    private static function finalizeReport(array &$inventory, array &$issues): void
    {
        foreach ($issues as &$entries) $entries = array_values($entries);
        unset($entries);
        foreach (['by_subject', 'by_topic', 'by_difficulty', 'by_status'] as $key) ksort($inventory[$key]);
    }

    private static function blueprints(
        array $questions,
        array $boards,
        array $subjects,
        array $relations,
        ?QuestionEligibilityIndex $eligibilityIndex = null,
        array $candidateQuestionsByBoard = [],
        ?callable $counterHook = null
    ): array {
        $subjectNames = [];
        foreach ($subjects as $subject) {
            if (is_array($subject) && is_scalar($subject['id'] ?? null) && is_scalar($subject['name'] ?? null)) {
                $subjectNames[trim((string) $subject['id'])] = trim((string) $subject['name']);
            }
        }
        $boardIds = [];
        foreach ($boards as $board) {
            if (is_array($board) && is_scalar($board['id'] ?? null)) {
                $boardIds[] = strtolower(trim((string) $board['id']));
            }
        }
        $result = [];
        foreach ($boardIds as $boardId) {
            $weights = [];
            foreach ($relations as $relation) {
                if (!is_array($relation)
                    || strtolower(trim((string) ($relation['board_id'] ?? ''))) !== $boardId) {
                    continue;
                }
                $settings = is_array($relation['settings'] ?? null) ? $relation['settings'] : [];
                $subjectId = trim((string) ($relation['subject_id'] ?? ''));
                $weight = is_numeric($settings['blueprint_weight'] ?? null)
                    ? (float) $settings['blueprint_weight'] : 0.0;
                if ($weight > 0 && isset($subjectNames[$subjectId])) {
                    $weights[$subjectId] = $weight;
                }
            }
            if ($weights === []) {
                continue;
            }
            $allocations = \RuntimeAllocationService::allocate(100, $weights);
            $subjectsForBoard = [];
            foreach ($weights as $subjectId => $_weight) {
                $subjectsForBoard[$subjectId] = $subjectNames[$subjectId];
            }
            $boardQuestions = $candidateQuestionsByBoard[$boardId] ?? $questions;
            if ($counterHook !== null) {
                $counterHook('coverage_blueprint_batch_executions', 1);
                $counterHook('coverage_blueprint_batch_question_visits', count($boardQuestions));
                $counterHook('coverage_blueprint_subject_pools', count($subjectsForBoard));
                if (!array_key_exists($boardId, $candidateQuestionsByBoard)) {
                    $counterHook('coverage_candidate_fallbacks', 1);
                }
            }
            $availableBySubject = \QuestionPoolFilter::countBySubject(
                $boardQuestions,
                $subjectsForBoard,
                new \SelectionRequest('*', null, [], 1, null, null, $boardId),
                $eligibilityIndex
            );
            $categories = [];
            foreach ($allocations as $subjectId => $required) {
                $available = (int) ($availableBySubject[$subjectId] ?? 0);
                $categories[] = [
                    'subject' => $subjectId,
                    'required_per_100' => $required,
                    'available' => $available,
                    'shortage_per_100' => max(0, $required - $available),
                ];
            }
            $result[] = [
                'board' => $boardId,
                'weight_total' => array_sum($weights),
                'allocation_total' => array_sum($allocations),
                'valid_weight_total' => abs(array_sum($weights) - 100.0) < 0.00001,
                'categories' => $categories,
            ];
        }

        return $result;
    }

    private static function eligible(array $question, array $eligibleQuestionIds): bool
    {
        $id = is_scalar($question['id'] ?? null) ? (string) $question['id'] : '';
        return $id !== '' && isset($eligibleQuestionIds[$id]);
    }

    private static function fieldName(array $question, string $field, array $catalog): ?string
    {
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        $match = self::canonical($question[$field] ?? $taxonomy[$field . '_id'] ?? null, $catalog);
        return $match['name'] ?? null;
    }

    private static function catalog(array $records): array
    {
        $catalog = [];
        foreach ($records as $record) {
            if (!is_array($record) || !is_scalar($record['id'] ?? null)) {
                continue;
            }
            $id = trim((string) $record['id']);
            $name = is_scalar($record['name'] ?? null) ? trim((string) $record['name']) : $id;
            $catalog[strtolower($id)] = ['id' => $id, 'name' => $name];
            $catalog[strtolower($name)] = ['id' => $id, 'name' => $name];
        }
        return $catalog;
    }

    private static function taxonomyOrphans(
        array $boards,
        array $subjects,
        array $relations,
        array $domains,
        array $topics,
        array $concepts
    ): array {
        $ids = static function (array $records): array {
            $result = [];
            foreach ($records as $record) {
                if (is_array($record) && is_scalar($record['id'] ?? null)) {
                    $result[trim((string) $record['id'])] = true;
                }
            }
            return $result;
        };
        $boardIds = $ids($boards);
        $subjectIds = $ids($subjects);
        $domainIds = $ids($domains);
        $topicIds = $ids($topics);
        $orphans = [];
        foreach ($relations as $record) {
            if (!is_array($record)
                || !isset($boardIds[trim((string) ($record['board_id'] ?? ''))])
                || !isset($subjectIds[trim((string) ($record['subject_id'] ?? ''))])) {
                $orphans[] = ['type' => 'board-subject', 'id' => (string) ($record['id'] ?? '')];
            }
        }
        foreach ([
            ['records' => $domains, 'parent' => 'subject_id', 'parents' => $subjectIds, 'type' => 'domain'],
            ['records' => $topics, 'parent' => 'domain_id', 'parents' => $domainIds, 'type' => 'topic'],
            ['records' => $concepts, 'parent' => 'topic_id', 'parents' => $topicIds, 'type' => 'concept'],
        ] as $group) {
            foreach ($group['records'] as $record) {
                if (!is_array($record)
                    || !isset($group['parents'][trim((string) ($record[$group['parent']] ?? ''))])) {
                    $orphans[] = ['type' => $group['type'], 'id' => (string) ($record['id'] ?? '')];
                }
            }
        }
        return $orphans;
    }

    private static function canonical(mixed $value, array $catalog): ?array
    {
        if (!is_scalar($value)) {
            return null;
        }
        return $catalog[strtolower(trim((string) $value))] ?? null;
    }

    private static function increment(array &$counts, string $key): void
    {
        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }
}
