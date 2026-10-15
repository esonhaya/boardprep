<?php

declare(strict_types=1);

namespace App\Services\Learning\Recommendation;

final class TopicPerformanceAccumulator
{
    public static function summarize(array $attempts): array
    {
        $rows = [];
        foreach ($attempts as $index => $attempt) {
            if (!is_array($attempt)) {
                continue;
            }
            $context = LearningAttemptContext::fromAttempt($attempt);
            $attemptKey = self::attemptKey($attempt, $index);
            $reviewGroups = self::reviewGroups($attempt, $context);

            if ($reviewGroups === []) {
                $reviewGroups[$context['topic']] = [
                    'context' => $context,
                    'correct' => null,
                    'total' => 0,
                    'mistakes' => [],
                    'basis' => 'QUIZ_CONTEXT',
                ];
            }

            foreach ($reviewGroups as $group) {
                $topicContext = $group['context'];
                $key = self::key($topicContext);
                if (!isset($rows[$key])) {
                    $rows[$key] = [
                        'topic' => $topicContext['topic'],
                        'subject' => $topicContext['subject'],
                        'board' => $topicContext['board'],
                        'let_level' => $topicContext['let_level'],
                        'let_specialization' => $topicContext['let_specialization'],
                        'civil_service_level' => $topicContext['civil_service_level'],
                        'attempts' => 0,
                        'average' => 0,
                        'best' => 0,
                        'reviewed_attempts' => 0,
                        'question_review_count' => 0,
                        'repeated_mistakes' => 0,
                        'recent_average' => null,
                        'recent_change_points' => null,
                        'trend' => 'INSUFFICIENT_EVIDENCE',
                        '_sum' => 0,
                        '_scores' => [],
                        '_reviewed_attempts' => 0,
                        '_question_review_count' => 0,
                        '_mistakes' => [],
                        '_bases' => [],
                    ];
                }

                $percentage = $group['basis'] === 'QUESTION_LEVEL_REVIEW'
                    ? (int) round(100 * (int) $group['correct'] / max(1, (int) $group['total']))
                    : self::percentage($attempt['percentage'] ?? 0);
                $rows[$key]['attempts']++;
                $rows[$key]['_sum'] += $percentage;
                $rows[$key]['_scores'][] = $percentage;
                $rows[$key]['best'] = max($rows[$key]['best'], $percentage);
                $rows[$key]['_bases'][$group['basis']] = true;
                if ($group['basis'] === 'QUESTION_LEVEL_REVIEW') {
                    $rows[$key]['reviewed_attempts']++;
                    $rows[$key]['_reviewed_attempts']++;
                    $rows[$key]['question_review_count'] += (int) $group['total'];
                    $rows[$key]['_question_review_count'] += (int) $group['total'];
                    foreach ($group['mistakes'] as $questionId) {
                        $rows[$key]['_mistakes'][$questionId][$attemptKey] = true;
                    }
                }
            }
        }

        foreach ($rows as &$row) {
            $row['average'] = (int) round($row['_sum'] / max(1, $row['attempts']));
            $scores = $row['_scores']; // Attempts arrive newest first from the history service.
            $row['recent_average'] = count($scores) >= 2
                ? (int) round(array_sum(array_slice($scores, 0, 3)) / min(3, count($scores)))
                : null;
            if (count($scores) >= 2) {
                $priorScores = array_slice($scores, 1, 2);
                $priorAverage = array_sum($priorScores) / count($priorScores);
                $change = (int) round($scores[0] - $priorAverage);
                $row['recent_change_points'] = $change;
                $row['trend'] = $change >= 5
                    ? 'IMPROVING'
                    : ($change <= -5 ? 'DECLINING' : 'STEADY');
            }

            $row['repeated_mistakes'] = count(array_filter(
                $row['_mistakes'],
                static fn(array $attemptsForQuestion): bool => count($attemptsForQuestion) >= 2
            ));
            $row['repeated_mistake_question_ids'] = array_keys(array_filter(
                $row['_mistakes'],
                static fn(array $attemptsForQuestion): bool => count($attemptsForQuestion) >= 2
            ));
            sort($row['repeated_mistake_question_ids'], SORT_NATURAL);
            $row['accuracy_basis'] = match (count($row['_bases'])) {
                1 => (string) array_key_first($row['_bases']),
                default => 'MIXED_QUESTION_AND_QUIZ_EVIDENCE',
            };
            $row['evidence_status'] = $row['attempts'] >= 2
                ? 'MULTIPLE_ATTEMPTS'
                : 'INSUFFICIENT_EVIDENCE';
            unset(
                $row['_sum'],
                $row['_scores'],
                $row['_reviewed_attempts'],
                $row['_question_review_count'],
                $row['_mistakes'],
                $row['_bases']
            );
        }
        unset($row);

        return array_values($rows);
    }

    private static function reviewGroups(array $attempt, array $context): array
    {
        $review = is_array($attempt['review'] ?? null) ? $attempt['review'] : [];
        if ($review === []) {
            return [];
        }

        $groups = [];
        foreach ($review as $item) {
            if (!is_array($item) || !is_bool($item['correct'] ?? null)) {
                continue;
            }
            $question = is_array($item['question'] ?? null) ? $item['question'] : [];
            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            $topic = self::text($question['topic'] ?? $taxonomy['topic_id'] ?? $context['topic']);
            if ($topic === '') {
                $topic = 'General';
            }
            $topicContext = $context;
            $topicContext['topic'] = $topic;
            $key = strtolower($topic);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'context' => $topicContext,
                    'correct' => 0,
                    'total' => 0,
                    'mistakes' => [],
                    'basis' => 'QUESTION_LEVEL_REVIEW',
                ];
            }
            $groups[$key]['total']++;
            if ($item['correct']) {
                $groups[$key]['correct']++;
                continue;
            }
            $questionId = self::text($question['id'] ?? '');
            if (($item['answered'] ?? false) === true && $questionId !== '') {
                $groups[$key]['mistakes'][$questionId] = $questionId;
            }
        }

        return $groups;
    }

    private static function attemptKey(array $attempt, int|string $index): string
    {
        foreach (['session_id', 'id'] as $field) {
            $value = self::text($attempt[$field] ?? '');
            if ($value !== '') {
                return $field . ':' . $value;
            }
        }
        return 'row:' . (string) $index;
    }

    private static function key(array $context): string
    {
        return implode("\0", array_map(
            static fn(string $field): string => strtolower((string) ($context[$field] ?? '')),
            ['board', 'let_level', 'let_specialization', 'civil_service_level', 'subject', 'topic']
        ));
    }

    private static function percentage(mixed $value): int
    {
        return max(0, min(100, is_numeric($value) ? (int) round((float) $value) : 0));
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
