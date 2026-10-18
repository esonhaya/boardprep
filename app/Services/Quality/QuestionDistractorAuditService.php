<?php

declare(strict_types=1);

namespace App\Services\Quality;

use App\Services\Quality\Validators\Choice\ChoiceDuplicateDetector;

/** Conservative option-shape checks. Heuristics are review cues, not content decisions. */
final class QuestionDistractorAuditService
{
    /** @return array{summary:array{ERROR:int,REVIEW:int},findings:list<array<string,mixed>>} */
    public static function analyze(array $questions): array
    {
        $findings = [];
        $positionGroups = [];
        $repeatedDistractors = [];
        foreach ($questions as $question) {
            if (!is_array($question)) continue;
            $id = trim((string) ($question['id'] ?? ''));
            $choices = is_array($question['choices'] ?? null)
                ? array_values(array_filter($question['choices'], 'is_scalar'))
                : [];
            if ($id === '' || count($choices) < 2) continue;

            if (ChoiceDuplicateDetector::validate($choices) !== []) {
                $findings[] = self::finding($id, 'ERROR', 'EXACT_OR_NORMALIZED_DUPLICATE', 'At least two options are equal after lower-case, whitespace, and terminal-punctuation normalization.', ['choices' => array_map('strval', $choices)]);
            }

            $normalized = array_map([self::class, 'normalize'], array_map('strval', $choices));
            for ($left = 0; $left < count($normalized); $left++) {
                for ($right = $left + 1; $right < count($normalized); $right++) {
                    $a = $normalized[$left];
                    $b = $normalized[$right];
                    if (min(strlen($a), strlen($b)) < 18 || $a === '' || $b === '') continue;
                    similar_text($a, $b, $percent);
                    if ($percent >= 91.0) {
                        $findings[] = self::finding($id, 'REVIEW', 'NEAR_DUPLICATE_OPTIONS', 'Two options have highly similar normalized wording; a reviewer should decide whether they are meaningfully distinct.', ['option_indexes' => [$left, $right], 'similarity_percent' => round($percent, 1)]);
                    }
                }
            }

            $lengths = array_map(static fn(string $choice): int => mb_strlen(trim($choice)), array_map('strval', $choices));
            $sortedLengths = $lengths;
            sort($sortedLengths, SORT_NUMERIC);
            $median = $sortedLengths[(int) floor((count($sortedLengths) - 1) / 2)] ?? 0;
            $maxLength = max($lengths);
            $maxIndex = array_search($maxLength, $lengths, true);
            if ($median >= 20 && $maxLength >= 100 && $maxLength >= $median * 2.4) {
                $findings[] = self::finding($id, 'REVIEW', 'OPTION_LENGTH_OUTLIER', 'One option is much longer than the other options; this can cue an answer but may also be justified by the content.', ['option_index' => $maxIndex, 'maximum_characters' => $maxLength, 'median_characters' => $median]);
            }

            $punctuation = array_map(static fn(string $choice): string => preg_match('/[.!?;:]\s*$/u', trim($choice)) === 1 ? 'terminal_punctuation' : 'no_terminal_punctuation', array_map('strval', $choices));
            if (count(array_unique($punctuation)) > 1) {
                $answer = trim((string) ($question['answer'] ?? ''));
                $answerIndex = array_search($answer, array_map('strval', $choices), true);
                if ($answerIndex !== false && count(array_filter($punctuation, static fn(string $value): bool => $value === $punctuation[$answerIndex])) === 1) {
                    $findings[] = self::finding($id, 'REVIEW', 'ANSWER_FORMATTING_OUTLIER', 'The keyed answer is the only option with a terminal punctuation pattern.', ['answer_index' => $answerIndex]);
                }
            }

            $answer = trim((string) ($question['answer'] ?? ''));
            $answerIndex = array_search($answer, array_map('strval', $choices), true);
            $negativeLeadCount = 0;
            $negativeLeadIndexes = [];
            foreach ($choices as $choiceIndex => $choice) {
                if (preg_match('/^\s*(?:ignore|avoid|dismiss|exclude|refuse|conceal|hide|withhold|silence|punish|blame|skip|cancel|wait for|do nothing|promise absolute secrecy)\b/iu', (string) $choice) === 1) {
                    $negativeLeadCount++;
                    $negativeLeadIndexes[] = $choiceIndex;
                }
            }
            if ($answerIndex !== false && count($choices) === 4 && $negativeLeadCount >= 3 && !in_array($answerIndex, $negativeLeadIndexes, true)) {
                $findings[] = self::finding($id, 'REVIEW', 'OBVIOUS_POLARITY_CUE', 'At least three distractors begin with an overtly avoidant or punitive action while the keyed option does not; review whether tone reveals the answer.', ['answer_index' => $answerIndex, 'negative_lead_indexes' => $negativeLeadIndexes]);
            }

            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            $group = strtolower(trim((string) ($taxonomy['board_id'] ?? $question['board'] ?? ''))) . '|' . strtolower(trim((string) ($taxonomy['subject_id'] ?? $question['subject'] ?? '')));
            $answerIndex = array_search(trim((string) ($question['answer'] ?? '')), array_map('strval', $choices), true);
            if ($group !== '|' && $answerIndex !== false) $positionGroups[$group][(int) $answerIndex][] = $id;
            $answerNormalized = self::normalize(trim((string) ($question['answer'] ?? '')));
            foreach ($choices as $choice) {
                $choiceText = trim((string) $choice);
                $choiceNormalized = self::normalize($choiceText);
                if ($choiceNormalized === '' || $choiceNormalized === $answerNormalized || $group === '|') continue;
                $repeatedDistractors[$group][$choiceNormalized]['text'] ??= $choiceText;
                $repeatedDistractors[$group][$choiceNormalized]['question_ids'][$id] = true;
            }
        }

        foreach ($positionGroups as $group => $positions) {
            $total = array_sum(array_map('count', $positions));
            if ($total < 12) continue;
            arsort($positions);
            $top = reset($positions);
            if (is_array($top) && count($top) / $total > .80) {
                $findings[] = self::finding('', 'REVIEW', 'ANSWER_POSITION_PATTERN', 'Correct-answer positions are concentrated within this board/subject group; inspect for unintended test-taking cues.', ['group' => $group, 'position_count' => count($top), 'total' => $total, 'question_ids' => array_slice($top, 0, 100)]);
            }
        }

        foreach ($repeatedDistractors as $group => $options) {
            foreach ($options as $option) {
                $questionIds = array_keys((array) ($option['question_ids'] ?? []));
                if (count($questionIds) < 3) continue;
                $findings[] = self::finding('', 'REVIEW', 'REPEATED_DISTRACTOR_TEXT', 'The same distractor wording appears in several questions within one board/subject group; inspect whether repeated patterns make options predictable.', [
                    'group' => $group,
                    'option_text' => (string) ($option['text'] ?? ''),
                    'question_count' => count($questionIds),
                    'question_ids' => array_slice($questionIds, 0, 100),
                ]);
            }
        }

        usort($findings, static fn(array $a, array $b): int => [$a['severity'], $a['question_id'], $a['code']] <=> [$b['severity'], $b['question_id'], $b['code']]);
        return [
            'summary' => [
                'ERROR' => count(array_filter($findings, static fn(array $item): bool => $item['severity'] === 'ERROR')),
                'REVIEW' => count(array_filter($findings, static fn(array $item): bool => $item['severity'] === 'REVIEW')),
            ],
            'findings' => $findings,
        ];
    }

    private static function normalize(string $value): string
    {
        $value = mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value));
        return preg_replace('/[.!?,;:]+$/u', '', $value) ?? $value;
    }

    private static function finding(string $questionId, string $severity, string $code, string $reason, array $details): array
    {
        return ['question_id' => $questionId, 'severity' => $severity, 'code' => $code, 'reason' => $reason, 'details' => $details];
    }
}
