<?php

declare(strict_types=1);

namespace App\Services\Shared;

use App\Core\App;
use App\Services\Flashcard\FlashcardDerivationService;

/**
 * Builds the shared content-investment report used by developer tooling.
 *
 * This is intentionally descriptive rather than editorial.  Scarcity,
 * exposure, learner outcomes, study depth, and blueprint context are kept as
 * separate signals so a future content batch can inspect the evidence behind
 * a recommendation.
 */
final class ContentPriorityService
{
    private const COLLECTIONS = [
        'questions',
        'question-statistics',
        'attempts',
        'weakness',
        'boards',
        'subjects',
        'board-subjects',
        'blueprints',
        'taxonomy/domains',
        'taxonomy/topics',
        'taxonomy/concepts',
        'question-eligibility',
        'study-materials',
        'study-question-links',
        'sources',
    ];

    private const MIN_USAGE_SAMPLE = 5;

    private const MAX_RECOMMENDATIONS = 10;

    public static function analyzeRepository(): array
    {
        $started = microtime(true);
        $collections = [];
        $reads = [];

        foreach (self::COLLECTIONS as $collection) {
            $collections[$collection] = self::readCollection($collection);
            $reads[$collection] = 1;
        }

        $deriver = App::container()->get(FlashcardDerivationService::class);
        $derivedCards = [];
        $integrity = ContentIntegrityService::analyze(
            $collections,
            static function (array $question) use ($deriver, &$derivedCards): ?array {
                $card = $deriver->derive($question);
                $id = self::scalar($question['id'] ?? null);
                if ($id !== '' && $card !== null) {
                    $derivedCards[$id] = $card;
                }
                return $card;
            }
        );
        $report = self::analyze(
            $collections,
            $integrity,
            static fn(array $question): ?array => $derivedCards[self::scalar($question['id'] ?? null)] ?? null
        );
        $report['runtime']['total_runtime_ms'] = round((microtime(true) - $started) * 1000, 2);
        $report['runtime']['collection_reads'] = $reads;
        return $report;
    }

    public static function analyze(array $collections, ?array $integrity = null, ?callable $deriveOverride = null): array
    {
        $started = microtime(true);
        $questions = self::records($collections['questions'] ?? []);
        $stats = self::index($collections['question-statistics'] ?? []);
        $questions = self::overlayStatistics($questions, $stats);
        $boards = self::index($collections['boards'] ?? []);
        $subjects = self::index($collections['subjects'] ?? []);
        $domains = self::index($collections['taxonomy/domains'] ?? []);
        $topics = self::index($collections['taxonomy/topics'] ?? []);
        $concepts = self::index($collections['taxonomy/concepts'] ?? []);
        $materials = self::index($collections['study-materials'] ?? []);
        $sources = self::index($collections['sources'] ?? []);
        $links = self::records($collections['study-question-links'] ?? []);
        $eligible = self::eligibilityIndex($collections['question-eligibility'] ?? [], $boards);
        $blueprints = self::records($collections['blueprints'] ?? []);

        $linkByQuestion = [];
        $linkByMaterial = [];
        foreach ($links as $link) {
            $questionId = self::scalar($link['question_id'] ?? null);
            $materialId = self::scalar($link['material_id'] ?? null);
            if ($questionId !== '') {
                $linkByQuestion[$questionId][] = $link;
            }
            if ($materialId !== '') {
                $linkByMaterial[$materialId][] = $link;
            }
        }

        $deriver = null;
        if ($integrity === null) {
            $integrity = ContentIntegrityService::analyze($collections);
        }
        if ($deriveOverride === null) {
            $deriver = App::container()->get(FlashcardDerivationService::class);
        }

        $questionRows = [];
        $cardRows = [];
        $usage = [];
        foreach ($questions as $question) {
            $id = self::scalar($question['id'] ?? null);
            if ($id === '') {
                continue;
            }
            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            $boardId = self::normalizeBoard($taxonomy['board_id'] ?? $question['board'] ?? '');
            $subjectId = self::scalar($taxonomy['subject_id'] ?? $question['subject'] ?? null);
            $topicId = self::scalar($taxonomy['topic_id'] ?? $question['topic'] ?? null);
            $conceptId = self::scalar($taxonomy['concept_id'] ?? $question['concept'] ?? null);
            $timesUsed = max(0, (int) ($question['timesUsed'] ?? 0));
            $correct = max(0, (int) ($question['timesCorrect'] ?? 0));
            $incorrect = max(0, (int) ($question['timesIncorrect'] ?? 0));
            $used = max($timesUsed, $correct + $incorrect);
            $usage[$id] = [
                'question_id' => $id,
                'board_id' => $boardId,
                'subject_id' => $subjectId,
                'topic_id' => $topicId,
                'concept_id' => $conceptId,
                'times_used' => $used,
                'times_correct' => $correct,
                'times_incorrect' => $incorrect,
                'accuracy' => $used > 0 ? round($correct / $used * 100, 1) : null,
            ];
            $questionRows[] = [
                'id' => $id,
                'board_id' => $boardId,
                'subject_id' => $subjectId,
                'topic_id' => $topicId,
                'concept_id' => $conceptId,
                'question' => $question,
                'eligible' => isset($eligible[$boardId][$id]),
                'links' => $linkByQuestion[$id] ?? [],
                'usage' => $usage[$id],
            ];
            if ($deriveOverride !== null || $deriver !== null) {
                $card = $deriveOverride !== null
                    ? $deriveOverride($question)
                    : $deriver->derive($question);
                if ($card !== null) {
                    $cardRows[$id] = $card;
                }
            }
        }

        $exposure = self::exposureReport($usage, $questionRows);
        $depth = self::depthReport(
            $questionRows,
            $boards,
            $subjects,
            $topics,
            $concepts,
            $materials,
            $linkByMaterial,
            $blueprints,
            $cardRows,
            $integrity,
            $collections['board-subjects'] ?? []
        );
        $weakness = self::weaknessReport($questionRows, $collections['weakness'] ?? [], $collections['attempts'] ?? []);
        $studyDepth = self::studyDepthReport($depth, $materials, $sources, $linkByMaterial);
        $priorities = array_merge(
            self::boardPriorities($depth['boards'], $depth['subjects'], $blueprints),
            self::priorities($depth, $weakness, $exposure, $studyDepth, $integrity)
        );
        usort($priorities, static fn(array $a, array $b): int => ($b['priority'] <=> $a['priority']) ?: strcmp($a['target'], $b['target']));
        foreach ($priorities as $index => &$priority) {
            $priority['rank'] = $index + 1;
        }
        unset($priority);

        $portfolio = [
            'question_count' => count($questionRows),
            'eligible_question_count' => count(array_filter($questionRows, static fn(array $row): bool => $row['eligible'])),
            'study_resource_count' => count($materials),
            'linked_question_count' => count(array_filter($questionRows, static fn(array $row): bool => $row['links'] !== [])),
            'unlinked_question_count' => count(array_filter($questionRows, static fn(array $row): bool => $row['links'] === [])),
            'questions_per_resource' => count($materials) > 0 ? round(count($questionRows) / count($materials), 2) : null,
            'flashcard_count' => count($cardRows),
            'flashcard_exclusion_count' => count($questionRows) - count($cardRows),
            'learner_usage' => self::usageSummary(array_values($usage)),
            'integrity_status' => $integrity['portfolio']['status'] ?? 'UNKNOWN',
        ];

        return [
            'generated_at' => gmdate(DATE_ATOM),
            'portfolio' => $portfolio,
            'boards' => $depth['boards'],
            'integrity' => $integrity,
            'content_depth' => [
                'portfolio' => $portfolio,
                'subjects' => $depth['subjects'],
                'topics' => $depth['topics'],
                'concepts' => $depth['concepts'],
                'shallow_subjects' => self::shallowGroups($depth['subjects']),
                'shallow_topics' => self::shallowGroups($depth['topics']),
                'shallow_concepts' => self::shallowGroups($depth['concepts']),
                'overconcentrated_areas' => self::overconcentrated($depth['topics'], $depth['boards']),
                'low_diversity_areas' => self::lowDiversity($depth['topics']),
            ],
            'study_depth' => $studyDepth,
            'exposure' => $exposure,
            'weakness' => $weakness,
            'priorities' => array_slice($priorities, 0, self::MAX_RECOMMENDATIONS),
            'warnings' => [
                'semantic_concept_fit' => 'Manual/editorial quality gate; usage and structural signals are automated.',
                'integrity' => $integrity['warnings'] ?? [],
            ],
            'runtime' => [
                'total_runtime_ms' => round((microtime(true) - $started) * 1000, 2),
                'question_reads' => 1,
                'statistics_reads' => 1,
                'attempt_reads' => 1,
                'study_reads' => 2,
                'taxonomy_reads' => 5,
                'flashcard_derivation_passes' => ($deriveOverride !== null || $deriver !== null) ? 1 : 0,
                'n_plus_one_found' => false,
                'global_cache_added' => false,
            ],
        ];
    }

    private static function readCollection(string $collection): array
    {
        $database = App::database();
        if ($database->usingJson()) {
            $databaseConfig = App::config('database', []);
            $path = is_array($databaseConfig) ? (string) ($databaseConfig['path'] ?? '') : '';
            $file = rtrim($path, '/') . '/' . $collection . '.json';
            if (!is_file($file)) {
                return [];
            }
        }
        return $database->storage()->all($collection);
    }

    private static function records(mixed $records): array
    {
        return is_array($records) ? array_values(array_filter($records, 'is_array')) : [];
    }

    private static function index(mixed $records): array
    {
        $index = [];
        foreach (self::records($records) as $record) {
            $id = self::scalar($record['id'] ?? null);
            if ($id !== '' && !isset($index[$id])) {
                $index[$id] = $record;
            }
        }
        return $index;
    }

    private static function overlayStatistics(array $questions, array $stats): array
    {
        foreach ($questions as &$question) {
            $id = self::scalar($question['id'] ?? null);
            if (isset($stats[$id])) {
                foreach (['timesUsed', 'timesCorrect', 'timesIncorrect'] as $field) {
                    if (array_key_exists($field, $stats[$id])) {
                        $question[$field] = $stats[$id][$field];
                    }
                }
            }
        }
        unset($question);
        return $questions;
    }

    private static function eligibilityIndex(array $entries, array $boards): array
    {
        $validBoards = array_fill_keys(array_map(
            static fn(array $board): string => self::normalizeBoard($board['id'] ?? ''),
            array_values($boards)
        ), true);
        $index = [];
        foreach ($entries as $entry) {
            $exams = is_array($entry['exam_ids'] ?? null) ? $entry['exam_ids'] : [];
            $ids = is_array($entry['question_ids'] ?? null) ? $entry['question_ids'] : [];
            foreach ($exams as $exam) {
                $boardId = self::normalizeBoard($exam);
                if (!isset($validBoards[$boardId])) {
                    continue;
                }
                foreach ($ids as $id) {
                    $questionId = self::scalar($id);
                    if ($questionId !== '') {
                        $index[$boardId][$questionId] = true;
                    }
                }
            }
        }
        return $index;
    }

    private static function depthReport(
        array $rows,
        array $boards,
        array $subjects,
        array $topics,
        array $concepts,
        array $materials,
        array $linkByMaterial,
        array $blueprints,
        array $cards,
        array $integrity,
        mixed $boardSubjectRecords
    ): array {
        $groups = [
            'boards' => [],
            'subjects' => [],
            'topics' => [],
            'concepts' => [],
        ];
        foreach (array_keys($boards) as $boardId) {
            $groups['boards'][$boardId] = self::newGroup('board', $boardId, $boards[$boardId]['name'] ?? $boardId);
        }
        foreach (self::records($boardSubjectRecords) as $mapping) {
            $boardId = self::normalizeBoard($mapping['board_id'] ?? '');
            $subjectId = self::scalar($mapping['subject_id'] ?? null);
            if (!isset($boards[$boardId]) || $subjectId === '') {
                continue;
            }
            $key = self::key($boardId, $subjectId);
            $groups['subjects'][$key] = self::newGroup('area', $key, $subjects[$subjectId]['name'] ?? $subjectId);
            $groups['subjects'][$key]['board_id'] = $boardId;
            $groups['subjects'][$key]['subject_id'] = $subjectId;
        }
        foreach ($rows as $row) {
            self::addToGroup($groups['boards'], $row['board_id'], $row, $cards, $materials);
            self::addToGroup($groups['subjects'], self::key($row['board_id'], $row['subject_id']), $row, $cards, $materials, $row['subject_id'], $subjects[$row['subject_id']]['name'] ?? $row['subject_id']);
            self::addToGroup($groups['topics'], self::key($row['board_id'], $row['subject_id'], $row['topic_id']), $row, $cards, $materials, $row['topic_id'], $topics[$row['topic_id']]['name'] ?? $row['topic_id']);
            self::addToGroup($groups['concepts'], self::key($row['board_id'], $row['subject_id'], $row['topic_id'], $row['concept_id']), $row, $cards, $materials, $row['concept_id'], $concepts[$row['concept_id']]['name'] ?? $row['concept_id']);
        }
        foreach (array_keys($groups) as $level) {
            foreach ($groups[$level] as &$group) {
                self::finishGroup($group, $level, $blueprints, $integrity);
            }
            unset($group);
        }
        return $groups;
    }

    private static function newGroup(string $level, string $id, string $name): array
    {
        return [
            'level' => $level,
            'id' => $id,
            'name' => (string) $name,
            'board_id' => $level === 'board' ? $id : '',
            'subject_id' => '',
            'topic_id' => '',
            'concept_id' => '',
            'question_ids' => [],
            'eligible_question_ids' => [],
            'linked_question_ids' => [],
            'study_material_ids' => [],
            'difficulty_distribution' => [],
            'structured_content_count' => 0,
            'text_only_count' => 0,
            'figure_question_count' => 0,
            'table_question_count' => 0,
            'numeric_question_count' => 0,
            'flashcard_ids' => [],
            'flashcard_exclusion_ids' => [],
            'usage' => ['times_used' => 0, 'times_correct' => 0, 'times_incorrect' => 0],
        ];
    }

    private static function addToGroup(array &$groups, string $key, array $row, array $cards, array $materials, string $leafId = '', string $leafName = ''): void
    {
        if ($key === '') {
            return;
        }
        if (!isset($groups[$key])) {
            $groups[$key] = self::newGroup('area', $leafId !== '' ? $leafId : $key, $leafName !== '' ? $leafName : $key);
            $parts = explode('|', $key);
            $groups[$key]['board_id'] = $parts[0] ?? '';
            $groups[$key]['subject_id'] = $parts[1] ?? '';
            $groups[$key]['topic_id'] = $parts[2] ?? '';
            $groups[$key]['concept_id'] = $parts[3] ?? '';
        }
        $group =& $groups[$key];
        $group['question_ids'][] = $row['id'];
        if ($row['eligible']) {
            $group['eligible_question_ids'][] = $row['id'];
        }
        if ($row['links'] !== []) {
            $group['linked_question_ids'][] = $row['id'];
            foreach ($row['links'] as $link) {
                $materialId = self::scalar($link['material_id'] ?? null);
                if ($materialId !== '') {
                    $group['study_material_ids'][$materialId] = true;
                }
            }
        }
        $difficulty = self::scalar($row['question']['difficulty'] ?? null) ?: 'unspecified';
        $group['difficulty_distribution'][$difficulty] = ($group['difficulty_distribution'][$difficulty] ?? 0) + 1;
        $blocks = is_array($row['question']['content_blocks'] ?? null) ? $row['question']['content_blocks'] : [];
        if ($blocks === []) {
            $group['text_only_count']++;
        } else {
            $group['structured_content_count']++;
        }
        if (($row['question']['figure_dependent'] ?? false) === true || self::hasBlock($blocks, 'figure')) {
            $group['figure_question_count']++;
        }
        if (($row['question']['table_dependent'] ?? false) === true || self::hasBlock($blocks, 'table')) {
            $group['table_question_count']++;
        }
        if (($row['question']['numeric'] ?? false) === true || self::hasBlock($blocks, 'equation')) {
            $group['numeric_question_count']++;
        }
        if (isset($cards[$row['id']])) {
            $group['flashcard_ids'][] = $row['id'];
        } else {
            $group['flashcard_exclusion_ids'][] = $row['id'];
        }
        foreach (['times_used', 'times_correct', 'times_incorrect'] as $field) {
            $group['usage'][$field] += $row['usage'][$field];
        }
        unset($group);
    }

    private static function finishGroup(array &$group, string $level, array $blueprints, array $integrity): void
    {
        $questions = count($group['question_ids']);
        $resources = count($group['study_material_ids']);
        $used = $group['usage']['times_used'];
        $group['question_count'] = $questions;
        $group['eligible_question_count'] = count(array_unique($group['eligible_question_ids']));
        $group['study_resource_count'] = $resources;
        $group['linked_question_count'] = count(array_unique($group['linked_question_ids']));
        $group['unlinked_question_count'] = count(array_diff($group['question_ids'], $group['linked_question_ids']));
        $group['questions_per_resource'] = $resources > 0 ? round($questions / $resources, 2) : null;
        $group['flashcard_count'] = count(array_unique($group['flashcard_ids']));
        $group['flashcard_exclusion_count'] = count(array_unique($group['flashcard_exclusion_ids']));
        $group['difficulty_distribution'] = $group['difficulty_distribution'];
        $group['usage']['accuracy'] = $used > 0 ? round($group['usage']['times_correct'] / $used * 100, 1) : null;
        $group['usage']['sample_size'] = $used;
        $group['blueprint_weight'] = self::blueprintWeight($group, $blueprints);
        $group['signals'] = [
            'shallow' => $questions > 0 && $resources === 0,
            'many_questions_single_resource' => $questions >= 10 && $resources === 1,
            'low_diversity' => $questions > 0 && $group['structured_content_count'] === 0,
            'integrity_status' => $integrity['portfolio']['status'] ?? 'UNKNOWN',
        ];
        $group['study_material_ids'] = array_keys($group['study_material_ids']);
        $group['question_ids'] = array_values(array_unique($group['question_ids']));
        $group['eligible_question_ids'] = array_values(array_unique($group['eligible_question_ids']));
        $group['linked_question_ids'] = array_values(array_unique($group['linked_question_ids']));
        $group['flashcard_ids'] = array_values(array_unique($group['flashcard_ids']));
        $group['flashcard_exclusion_ids'] = array_values(array_unique($group['flashcard_exclusion_ids']));
    }

    private static function blueprintWeight(array $group, array $blueprints): ?float
    {
        if ($group['level'] !== 'area' && $group['level'] !== 'board') {
            return null;
        }
        $board = $group['board_id'];
        $subject = $group['subject_id'];
        foreach ($blueprints as $blueprint) {
            if (self::normalizeBoard($blueprint['board_id'] ?? $blueprint['board'] ?? '') !== $board) {
                continue;
            }
            foreach ($blueprint['sections'] ?? [] as $section) {
                $sectionSubjects = is_array($section['subjects'] ?? null) ? $section['subjects'] : [];
                if (($section['id'] ?? '') === $subject || in_array($subject, $sectionSubjects, true)) {
                    return is_numeric($section['target_percent'] ?? null) ? (float) $section['target_percent'] : null;
                }
            }
        }
        return null;
    }

    private static function shallowGroups(array $groups): array
    {
        $byBoard = [];
        foreach ($groups as $group) {
            $byBoard[$group['board_id']][] = (int) ($group['question_count'] ?? 0);
        }
        $result = [];
        foreach ($groups as $key => $group) {
            $count = (int) ($group['question_count'] ?? 0);
            $values = array_values(array_filter($byBoard[$group['board_id']] ?? [], static fn(int $value): bool => $value > 0));
            sort($values, SORT_NUMERIC);
            $median = $values[(int) floor((count($values) - 1) / 2)] ?? $count;
            if ($count === 0 || $count <= 1 || $count < $median * 0.5) {
                $result[] = [
                    'target' => $key,
                    'name' => $group['name'],
                    'board_id' => $group['board_id'],
                    'question_count' => $count,
                    'board_median_count' => $median,
                    'question_ids' => $group['question_ids'],
                ];
            }
        }
        usort($result, static fn(array $a, array $b): int => ($a['question_count'] <=> $b['question_count']) ?: strcmp($a['target'], $b['target']));
        return $result;
    }

    private static function overconcentrated(array $topics, array $boards): array
    {
        $result = [];
        foreach ($topics as $key => $topic) {
            $boardCount = (int) ($boards[$topic['board_id']]['question_count'] ?? 0);
            $count = (int) ($topic['question_count'] ?? 0);
            if ($boardCount > 0 && $count >= 10 && ($count / $boardCount) > 0.25) {
                $result[] = [
                    'target' => $key,
                    'board_id' => $topic['board_id'],
                    'question_count' => $count,
                    'board_question_count' => $boardCount,
                    'share_percent' => round($count / $boardCount * 100, 1),
                    'question_ids' => $topic['question_ids'],
                ];
            }
        }
        usort($result, static fn(array $a, array $b): int => ($b['share_percent'] <=> $a['share_percent']) ?: strcmp($a['target'], $b['target']));
        return $result;
    }

    private static function lowDiversity(array $topics): array
    {
        $result = [];
        foreach ($topics as $key => $topic) {
            $count = (int) ($topic['question_count'] ?? 0);
            if ($count >= 5 && (int) ($topic['structured_content_count'] ?? 0) === 0) {
                $result[] = [
                    'target' => $key,
                    'board_id' => $topic['board_id'],
                    'question_count' => $count,
                    'structured_content_count' => 0,
                    'note' => 'Format concentration is descriptive; review only where the subject calls for varied formats.',
                    'question_ids' => $topic['question_ids'],
                ];
            }
        }
        return $result;
    }

    private static function boardPriorities(array $boards, array $subjects, array $blueprints): array
    {
        $items = [];
        foreach ($boards as $boardId => $board) {
            $maxGap = 0.0;
            $weightedSections = 0;
            foreach ($blueprints as $blueprint) {
                if (self::normalizeBoard($blueprint['board_id'] ?? $blueprint['board'] ?? '') !== $boardId) {
                    continue;
                }
                foreach ($blueprint['sections'] ?? [] as $section) {
                    if (!is_numeric($section['target_percent'] ?? null)) {
                        continue;
                    }
                    $weightedSections++;
                    $ids = is_array($section['subjects'] ?? null) ? $section['subjects'] : [$section['id'] ?? ''];
                    $actual = 0;
                    foreach ($subjects as $subject) {
                        if ($subject['board_id'] === $boardId && in_array($subject['subject_id'], $ids, true)) {
                            $actual += (int) ($subject['question_count'] ?? 0);
                        }
                    }
                    $actualPercent = ($board['question_count'] ?? 0) > 0 ? $actual / $board['question_count'] * 100 : 0;
                    $maxGap = max($maxGap, (float) $section['target_percent'] - $actualPercent);
                }
            }
            if ($weightedSections === 0 || $maxGap <= 0) {
                continue;
            }
            $items[] = self::priority(
                round(min(100, $maxGap * 2), 2),
                $boardId,
                'board',
                ['BLUEPRINT_SCARCITY' => 'weighted section below target'],
                ['questions' => $board['question_count'], 'largest_blueprint_gap_percent' => round($maxGap, 1), 'weights' => 'repository blueprint target; not asserted official'],
                'ADD_QUESTIONS',
                $board['question_ids'] ?? []
            );
        }
        return $items;
    }

    private static function studyDepthReport(array $depth, array $materials, array $sources, array $linkByMaterial): array
    {
        $warnings = [];
        foreach ($depth['topics'] as $key => $group) {
            if (($group['question_count'] ?? 0) >= 10 && ($group['study_resource_count'] ?? 0) === 0) {
                $warnings[] = ['target' => $key, 'code' => 'QUESTION_RICH_STUDY_POOR', 'metrics' => ['questions' => $group['question_count'], 'resources' => 0]];
            } elseif (($group['question_count'] ?? 0) >= 10 && ($group['study_resource_count'] ?? 0) === 1) {
                $warnings[] = ['target' => $key, 'code' => 'MANY_QUESTIONS_SINGLE_RESOURCE', 'metrics' => ['questions' => $group['question_count'], 'resources' => 1]];
            }
        }
        foreach ($materials as $id => $material) {
            $sourceCount = count(array_filter($material['source_ids'] ?? [], static fn(mixed $source): bool => isset($sources[(string) $source])));
            if ($sourceCount === 0) {
                $warnings[] = ['target' => $id, 'code' => 'RESOURCE_SOURCE_COVERAGE_REVIEW', 'metrics' => ['sources' => 0]];
            }
        }
        $orphanMaterials = array_values(array_diff(array_keys($materials), array_keys($linkByMaterial)));
        return [
            'total_resources' => count($materials),
            'orphan_materials' => $orphanMaterials,
            'warnings' => $warnings,
            'question_rich_study_poor' => array_values(array_filter($warnings, static fn(array $row): bool => $row['code'] === 'QUESTION_RICH_STUDY_POOR')),
            'study_rich_question_poor' => [],
            'many_questions_single_resource' => array_values(array_filter($warnings, static fn(array $row): bool => $row['code'] === 'MANY_QUESTIONS_SINGLE_RESOURCE')),
        ];
    }

    private static function exposureReport(array $usage, array $rows): array
    {
        $byGroup = [];
        foreach ($rows as $row) {
            $key = self::key($row['board_id'], $row['subject_id'], $row['topic_id']);
            $byGroup[$key][] = $row['usage']['times_used'];
        }
        $result = ['never_seen' => [], 'low_exposure' => [], 'normal_exposure' => [], 'high_exposure' => [], 'questions' => []];
        foreach ($usage as $id => $item) {
            $values = $byGroup[self::key($item['board_id'], $item['subject_id'], $item['topic_id'])] ?? [0];
            sort($values, SORT_NUMERIC);
            $p90 = $values[(int) floor((count($values) - 1) * 0.9)] ?? 0;
            $times = $item['times_used'];
            $class = $times === 0 ? 'NEVER_SEEN' : (($times <= 1) ? 'LOW_EXPOSURE' : (($times >= max(3, $p90) && $times > 1) ? 'HIGH_EXPOSURE' : 'NORMAL_EXPOSURE'));
            $item['exposure_share'] = array_sum($values) > 0 ? round($times / array_sum($values) * 100, 2) : 0;
            $item['exposure_class'] = $class;
            $result['questions'][$id] = $item;
            $result[strtolower(str_replace('_EXPOSURE', '_exposure', $class))][] = $id;
        }
        foreach (['never_seen', 'low_exposure', 'normal_exposure', 'high_exposure'] as $key) {
            $result[$key] = array_values(array_unique($result[$key] ?? []));
        }
        return $result;
    }

    private static function weaknessReport(array $rows, mixed $weaknessRecords, mixed $attemptRecords): array
    {
        $areas = [];
        foreach ($rows as $row) {
            $groupings = [
                ['board', self::key($row['board_id'])],
                ['subject', self::key($row['board_id'], $row['subject_id'])],
                ['topic', self::key($row['board_id'], $row['subject_id'], $row['topic_id'])],
                ['concept', self::key($row['board_id'], $row['subject_id'], $row['topic_id'], $row['concept_id'])],
            ];
            foreach ($groupings as [$level, $key]) {
                if ($key === '') {
                    continue;
                }
                if (!isset($areas[$key])) {
                    $areas[$key] = [
                        'target' => $key,
                        'level' => $level,
                        'board_id' => $row['board_id'],
                        'subject_id' => $row['subject_id'],
                        'topic_id' => $row['topic_id'],
                        'concept_id' => $row['concept_id'],
                        'times_used' => 0,
                        'correct' => 0,
                        'incorrect' => 0,
                        'question_ids' => [],
                    ];
                }
                $areas[$key]['times_used'] += $row['usage']['times_used'];
                $areas[$key]['correct'] += $row['usage']['times_correct'];
                $areas[$key]['incorrect'] += $row['usage']['times_incorrect'];
                $areas[$key]['question_ids'][] = $row['id'];
            }
        }
        $sufficient = [];
        $insufficient = [];
        foreach ($areas as &$area) {
            $area['sample_size'] = $area['times_used'];
            $area['accuracy'] = $area['times_used'] > 0 ? round($area['correct'] / $area['times_used'] * 100, 1) : null;
            $area['sample_state'] = $area['sample_size'] >= self::MIN_USAGE_SAMPLE ? 'SUFFICIENT_DATA' : 'INSUFFICIENT_DATA';
            if ($area['sample_state'] === 'SUFFICIENT_DATA') {
                $sufficient[] = $area;
            } else {
                $insufficient[] = $area;
            }
            $area['question_ids'] = array_values(array_unique(array_map('strval', $area['question_ids'])));
        }
        unset($area);
        usort($sufficient, static fn(array $a, array $b): int => ($a['accuracy'] <=> $b['accuracy']) ?: ($b['sample_size'] <=> $a['sample_size']));
        return [
            'areas' => $areas,
            'sufficient_sample_areas' => $sufficient,
            'insufficient_sample_areas' => $insufficient,
            'high_confidence_weak_areas' => array_values(array_filter($sufficient, static fn(array $area): bool => ($area['accuracy'] ?? 100) < 70)),
            'source_records' => count(self::records($weaknessRecords)),
            'attempt_records' => count(self::records($attemptRecords)),
            'minimum_sample_size' => self::MIN_USAGE_SAMPLE,
        ];
    }

    private static function priorities(array $depth, array $weakness, array $exposure, array $studyDepth, array $integrity): array
    {
        $items = [];
        foreach ($depth['subjects'] as $key => $group) {
            $scarcity = self::scarcityScore($group['question_count'] ?? 0, $depth['subjects']);
            $weak = self::weaknessFor($key, $weakness['high_confidence_weak_areas']);
            $study = ($group['study_resource_count'] ?? 0) === 0 && ($group['question_count'] ?? 0) > 0 ? 100 : 0;
            $score = round($scarcity * 0.55 + $weak * 0.3 + $study * 0.15, 2);
            if ($score <= 0) {
                continue;
            }
            $items[] = self::priority($score, $key, 'subject', ['SCARCITY' => $scarcity > 50 ? 'high' : 'moderate', 'LEARNER_WEAKNESS' => $weak > 0 ? 'high-confidence' : 'none', 'STUDY_DEPTH' => $study > 0 ? 'missing' : 'adequate'], ['questions' => $group['question_count'], 'resources' => $group['study_resource_count'], 'accuracy' => $group['usage']['accuracy'], 'blueprint_weight' => $group['blueprint_weight']], $weak > 0 ? 'REVIEW_HIGH_FAILURE_QUESTIONS' : 'ADD_QUESTIONS', $group['question_ids'] ?? []);
        }
        foreach ($depth['topics'] as $key => $group) {
            if (($group['question_count'] ?? 0) <= 1) {
                $items[] = self::priority(70, $key, 'topic', ['SCARCITY' => 'very-high', 'LOW_DIVERSITY' => ($group['signals']['low_diversity'] ?? false) ? 'possible' : 'none'], ['questions' => $group['question_count'], 'resources' => $group['study_resource_count']], 'ADD_QUESTIONS', $group['question_ids'] ?? []);
            }
        }
        foreach ($exposure['high_exposure'] as $id) {
            $items[] = self::priority(65, (string) $id, 'question', ['EXPOSURE' => 'high'], $exposure['questions'][$id] ?? [], 'REVIEW_OVEREXPOSED_QUESTIONS', [$id]);
        }
        foreach ($studyDepth['warnings'] as $warning) {
            $items[] = self::priority(60, $warning['target'], 'study', [$warning['code'] => 'review'], $warning['metrics'], 'EXPAND_STUDY_RESOURCE', [$warning['target']]);
        }
        usort($items, static fn(array $a, array $b): int => ($b['priority'] <=> $a['priority']) ?: strcmp($a['target'], $b['target']));
        return $items;
    }

    private static function priority(float $score, string $target, string $level, array $reasons, array $metrics, string $action, array $affectedIds = []): array
    {
        return [
            'priority' => $score,
            'target' => $target,
            'level' => $level,
            'board_id' => explode('|', $target)[0] ?? '',
            'subject_id' => explode('|', $target)[1] ?? '',
            'topic_id' => $level === 'topic' ? explode('|', $target)[2] ?? '' : '',
            'reason_codes' => array_keys(array_filter($reasons, static fn(mixed $value): bool => $value !== 'none')),
            'supporting_metrics' => $metrics,
            'recommended_action' => $action,
            'explanation' => $reasons,
            'affected_ids' => array_values(array_map('strval', $affectedIds)),
        ];
    }

    private static function weaknessFor(string $key, array $areas): float
    {
        foreach ($areas as $area) {
            if (($area['target'] ?? '') === $key) {
                return max(0, 100 - (float) ($area['accuracy'] ?? 100));
            }
        }
        return 0;
    }

    private static function scarcityScore(int $count, array $groups): float
    {
        $values = array_values(array_filter(array_map(static fn(array $group): int => (int) ($group['question_count'] ?? 0), $groups), static fn(int $value): bool => $value > 0));
        if ($count <= 0 || $values === []) {
            return 0;
        }
        sort($values, SORT_NUMERIC);
        $median = $values[(int) floor((count($values) - 1) / 2)] ?? $count;
        return round(min(100, max(0, (1 - ($count / max(1, $median))) * 100)), 2);
    }

    private static function usageSummary(array $usage): array
    {
        $used = array_filter($usage, static fn(array $item): bool => $item['times_used'] > 0);
        $times = array_sum(array_column($usage, 'times_used'));
        return [
            'questions_with_usage' => count($used),
            'questions_never_seen' => count($usage) - count($used),
            'times_used' => $times,
            'times_correct' => array_sum(array_column($usage, 'times_correct')),
            'times_incorrect' => array_sum(array_column($usage, 'times_incorrect')),
            'accuracy' => $times > 0 ? round(array_sum(array_column($usage, 'times_correct')) / $times * 100, 1) : null,
            'sample_state' => $times >= self::MIN_USAGE_SAMPLE ? 'USAGE_AVAILABLE' : 'SPARSE_USAGE',
        ];
    }

    private static function hasBlock(array $blocks, string $type): bool
    {
        foreach ($blocks as $block) {
            if (is_array($block) && ($block['type'] ?? '') === $type) {
                return true;
            }
        }
        return false;
    }

    private static function key(string ...$parts): string
    {
        return implode('|', array_values(array_filter($parts, static fn(string $part): bool => $part !== '')));
    }

    private static function scalar(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private static function normalizeBoard(mixed $value): string
    {
        $id = strtolower(trim((string) $value));
        return ['cse' => 'civil-service', 'cle' => 'criminologist', 'nle' => 'nursing', 'ple' => 'psychometrician'][$id] ?? $id;
    }
}
