<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

/**
 * Reports editorial evidence gaps without treating missing metadata or a
 * suspicious rationale template as proof that a question is incorrect.
 */
final class QuestionEditorialEvidenceAudit
{
    private const DIFFICULTIES = ['easy', 'medium', 'hard'];

    public static function analyze(array $questions): array
    {
        $byBoard = [];
        $findings = [];
        $stemsByBoard = [];
        $punctuationVariantsByBoard = [];

        foreach ($questions as $question) {
            if (!is_array($question) || !in_array(
                strtolower(self::text($question['status'] ?? 'active')),
                ['active', 'approved'],
                true
            )) {
                continue;
            }

            $id = self::text($question['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            $board = strtolower(self::text($taxonomy['board_id'] ?? $question['board'] ?? 'unknown'));
            $board = $board !== '' ? $board : 'unknown';
            $difficulty = strtolower(self::text($question['difficulty'] ?? ''));
            $row = &$byBoard[$board];
            if (!isset($row)) {
                $row = [
                    'question_ids' => [],
                    'concept_ids' => [],
                    'difficulty' => array_fill_keys(self::DIFFICULTIES, 0),
                    'missing_cognitive_intent_ids' => [],
                ];
            }
            $row['question_ids'][] = $id;
            $concept = self::text($taxonomy['concept_id'] ?? '');
            if ($concept !== '') {
                $row['concept_ids'][$concept] = ($row['concept_ids'][$concept] ?? 0) + 1;
            }
            if (isset($row['difficulty'][$difficulty])) {
                $row['difficulty'][$difficulty]++;
            } else {
                $findings[] = self::finding(
                    'HARD_DEFECT',
                    'INVALID_DIFFICULTY_LABEL',
                    $board,
                    $id,
                    'Difficulty must be easy, medium, or hard.'
                );
            }

            if (self::text($question['cognitive_intent'] ?? '') === '') {
                $row['missing_cognitive_intent_ids'][] = $id;
            }

            $explanation = self::text($question['explanation'] ?? '');
            if (preg_match('/\AThe key grammatical relationship is shown by\b/i', $explanation) === 1) {
                $findings[] = self::finding(
                    'REVIEW_REQUIRED',
                    'GENERIC_EXPLANATION_TEMPLATE',
                    $board,
                    $id,
                    'The rationale uses a generic grammar template; check it against the stem, answer, and canonical taxonomy.'
                );
            }
            $stem = self::text($question['question'] ?? '');
            $normalizedStem = strtolower(preg_replace('/\s+/u', ' ', trim($stem)) ?? trim($stem));
            if ($normalizedStem !== '') {
                $stemsByBoard[$board][$normalizedStem][] = $id;
                $punctuationKey = self::punctuationNormalizedStem($normalizedStem);
                if ($punctuationKey !== '') {
                    $punctuationVariantsByBoard[$board][$punctuationKey][$normalizedStem][] = $id;
                }
            }
            if (preg_match('/\bThe competency focus is\b|\(Review item\s+[^)]*\)/i', $stem) === 1) {
                $findings[] = self::finding(
                    'HARD_DEFECT',
                    'EDITORIAL_METADATA_LEAK_IN_STEM',
                    $board,
                    $id,
                    'The learner-facing stem contains an authoring label; remove metadata that does not belong to the question.'
                );
            }
            unset($row);
        }

        foreach ($stemsByBoard as $board => $stemGroups) {
            foreach ($stemGroups as $ids) {
                if (count($ids) < 2) {
                    continue;
                }
                sort($ids, SORT_NATURAL);
                $findings[] = [
                    'class' => 'REVIEW_REQUIRED',
                    'code' => 'EXACT_DUPLICATE_STEM',
                    'board' => $board,
                    'entity_type' => 'question_set',
                    'entity_id' => implode(',', $ids),
                    'note' => 'Active questions share the same normalized learner-facing stem; review their answer choices, key, rationale, and concept before treating them as distinct practice.',
                ];
            }
        }
        foreach ($punctuationVariantsByBoard as $board => $variants) {
            foreach ($variants as $surfaceForms) {
                if (count($surfaceForms) < 2) {
                    continue;
                }
                $ids = [];
                foreach ($surfaceForms as $surfaceIds) {
                    array_push($ids, ...$surfaceIds);
                }
                $ids = array_values(array_unique($ids));
                sort($ids, SORT_NATURAL);
                $findings[] = [
                    'class' => 'REVIEW_REQUIRED',
                    'code' => 'PUNCTUATION_ONLY_STEM_VARIANT',
                    'board' => $board,
                    'entity_type' => 'question_set',
                    'entity_id' => implode(',', $ids),
                    'note' => 'These stems differ only in punctuation and spacing; review the wording, choices, key, and concept for a near-duplicate before using them as separate items.',
                ];
            }
        }

        $profiles = [];
        foreach ($byBoard as $board => $row) {
            $leafCounts = array_values($row['concept_ids']);
            sort($leafCounts, SORT_NUMERIC);
            $leafCount = count($leafCounts);
            $median = 0;
            if ($leafCount > 0) {
                $middle = intdiv($leafCount, 2);
                $median = $leafCount % 2 === 0
                    ? ($leafCounts[$middle - 1] + $leafCounts[$middle]) / 2
                    : $leafCounts[$middle];
            }
            $profiles[] = sprintf(
                'BOARD_QUESTION_DEPTH board=%s questions=%d concepts=%d concept_counts[min/median/max]=%s/%s/%s concepts_under_5=%d under_10=%d under_20=%d difficulty[easy/medium/hard]=%d/%d/%d cognitive_intent_missing=%d; counts are descriptive signals, not official blueprint weights or psychometric calibration.',
                $board,
                count($row['question_ids']),
                $leafCount,
                $leafCount > 0 ? (string) min($leafCounts) : 'n/a',
                $leafCount > 0 ? rtrim(rtrim(number_format((float) $median, 1, '.', ''), '0'), '.') : 'n/a',
                $leafCount > 0 ? (string) max($leafCounts) : 'n/a',
                count(array_filter($leafCounts, static fn(int $count): bool => $count < 5)),
                count(array_filter($leafCounts, static fn(int $count): bool => $count < 10)),
                count(array_filter($leafCounts, static fn(int $count): bool => $count < 20)),
                $row['difficulty']['easy'],
                $row['difficulty']['medium'],
                $row['difficulty']['hard'],
                count($row['missing_cognitive_intent_ids'])
            );

            if ($row['missing_cognitive_intent_ids'] !== []) {
                sort($row['missing_cognitive_intent_ids'], SORT_NATURAL);
                $findings[] = [
                    'class' => 'INSUFFICIENT_EVIDENCE',
                    'code' => 'MISSING_COGNITIVE_INTENT',
                    'board' => $board,
                    'entity_type' => 'question_set',
                    'entity_id' => implode(',', $row['missing_cognitive_intent_ids']),
                    'note' => count($row['missing_cognitive_intent_ids'])
                        . ' active questions have no recorded cognitive-intent label; the missing label does not establish an incorrect item.',
                ];
            }
        }

        sort($profiles, SORT_STRING);
        return ['findings' => $findings, 'profiles' => $profiles];
    }

    private static function finding(string $class, string $code, string $board, string $id, string $note): array
    {
        return [
            'class' => $class,
            'code' => $code,
            'board' => $board,
            'entity_type' => 'question',
            'entity_id' => $id,
            'note' => $note,
        ];
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private static function punctuationNormalizedStem(string $stem): string
    {
        $stem = str_replace(
            ['.', ',', ';', ':', '!', '?', '"', "'", '“', '”', '‘', '’', '(', ')', '[', ']', '{', '}'],
            ' ',
            $stem
        );
        return strtolower(preg_replace('/\s+/u', ' ', trim($stem)) ?? trim($stem));
    }
}
