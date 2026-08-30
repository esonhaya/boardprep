<?php

declare(strict_types=1);

namespace App\Services\Shared;

use App\Core\App;
use App\Repositories\QuestionRepository;

final class QuestionCoverageService
{
    private const DIFFICULTIES = ['easy', 'medium', 'hard'];

    public static function analyzeRepository(): array
    {
        $storage = App::storage();

        return self::analyze(
            (new QuestionRepository($storage))->all(),
            $storage->all('boards'),
            $storage->all('subjects'),
            $storage->all('board-subjects'),
            $storage->all('taxonomy/domains'),
            $storage->all('taxonomy/topics'),
            $storage->all('taxonomy/concepts')
        );
    }

    public static function analyze(
        array $questions,
        array $boards,
        array $subjects,
        array $boardSubjects,
        array $domains,
        array $topics,
        array $concepts
    ): array {
        $catalog = [
            'board' => self::catalog($boards),
            'subject' => self::catalog($subjects),
            'domain' => self::catalog($domains),
            'topic' => self::catalog($topics),
            'concept' => self::catalog($concepts),
        ];
        $inventory = [
            'total' => count($questions),
            'eligible' => 0,
            'by_subject' => [],
            'by_topic' => [],
            'by_difficulty' => [],
            'by_status' => [],
        ];
        $issues = [
            'unknown_taxonomy' => [],
            'aliases' => [],
            'invalid_difficulty' => [],
            'legacy_metadata' => [],
            'ineligible' => [],
            'taxonomy_orphans' => self::taxonomyOrphans($boards, $subjects, $boardSubjects, $domains, $topics, $concepts),
        ];

        foreach ($questions as $question) {
            if (!is_array($question)) {
                continue;
            }
            $id = is_scalar($question['id'] ?? null) ? (string) $question['id'] : '<unknown>';
            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            if ($taxonomy === []) {
                $issues['legacy_metadata'][] = $id;
            }
            foreach (['board', 'subject', 'domain', 'topic', 'concept'] as $dimension) {
                $value = $question[$dimension] ?? $taxonomy[$dimension . '_id'] ?? null;
                $match = self::canonical($value, $catalog[$dimension]);
                if ($match === null) {
                    $issues['unknown_taxonomy'][] = [
                        'question' => $id,
                        'dimension' => $dimension,
                        'value' => is_scalar($value) ? trim((string) $value) : '',
                    ];
                    continue;
                }
                if (is_scalar($value) && trim((string) $value) !== $match['id']) {
                    $issues['aliases'][] = [
                        'question' => $id,
                        'dimension' => $dimension,
                        'value' => trim((string) $value),
                        'canonical' => $match['id'],
                    ];
                }
                if ($dimension === 'subject') {
                    self::increment($inventory['by_subject'], $match['id']);
                } elseif ($dimension === 'topic') {
                    self::increment($inventory['by_topic'], $match['id']);
                }
            }

            $difficulty = is_scalar($question['difficulty'] ?? null)
                ? strtolower(trim((string) $question['difficulty'])) : '';
            if (!in_array($difficulty, self::DIFFICULTIES, true)) {
                $issues['invalid_difficulty'][] = $id;
            } else {
                self::increment($inventory['by_difficulty'], $difficulty);
            }
            $status = is_scalar($question['status'] ?? null)
                ? strtolower(trim((string) $question['status'])) : '';
            self::increment($inventory['by_status'], $status === '' ? '<missing>' : $status);

            $subject = self::fieldName($question, 'subject', $catalog['subject']);
            if ($subject !== null && self::eligible($questions, $question, $subject)) {
                $inventory['eligible']++;
            } else {
                $issues['ineligible'][] = $id;
            }
        }

        foreach ($issues as &$entries) {
            $entries = array_values($entries);
        }
        unset($entries);
        foreach (['by_subject', 'by_topic', 'by_difficulty', 'by_status'] as $key) {
            ksort($inventory[$key]);
        }

        return [
            'inventory' => $inventory,
            'issues' => $issues,
            'blueprints' => self::blueprints($questions, $boards, $subjects, $boardSubjects),
        ];
    }

    private static function blueprints(
        array $questions,
        array $boards,
        array $subjects,
        array $relations
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
            $categories = [];
            foreach ($allocations as $subjectId => $required) {
                $name = $subjectNames[$subjectId];
                $available = count(\QuestionPoolFilter::filter(
                    $questions,
                    new \SelectionRequest($name, null, [], $required)
                ));
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

    private static function eligible(array $questions, array $question, string $subject): bool
    {
        $id = is_scalar($question['id'] ?? null) ? (string) $question['id'] : '';
        foreach (\QuestionPoolFilter::filter(
            $questions,
            new \SelectionRequest($subject, null, [], 1)
        ) as $eligible) {
            if (is_scalar($eligible['id'] ?? null) && (string) $eligible['id'] === $id) {
                return true;
            }
        }
        return false;
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
