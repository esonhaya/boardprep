<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Services\Learning\Recommendation\LearningAttemptContext;

final class SubjectPerformanceService
{
    private const RECENT_ATTEMPT_LIMIT = 5;
    private const MIN_ACCURACY_EVENTS = 5;

    public static function summarize(array $attempts): array
    {
        $rows = [];
        foreach (LearningHistoryService::ordered($attempts) as $attemptIndex => $attempt) {
            $baseContext = LearningAttemptContext::fromAttempt($attempt);
            $review = is_array($attempt['review'] ?? null) ? $attempt['review'] : [];
            $groups = [];

            if ($review !== []) {
                foreach ($review as $item) {
                    if (!is_array($item) || !is_bool($item['correct'] ?? null)) {
                        continue;
                    }
                    $question = is_array($item['question'] ?? null) ? $item['question'] : [];
                    $context = LearningAttemptContext::fromQuestion($question, $baseContext);
                    $subject = self::text($context['subject'] ?? '');
                    if ($subject === '' || $subject === '*') {
                        // A legacy mixed-board attempt without a saved,
                        // board-resolved placement is not safely attributable.
                        continue;
                    }
                    $key = self::contextKey($context, $subject);
                    $groups[$key] ??= ['context' => $context, 'total' => 0, 'answered' => 0, 'correct' => 0];
                    $groups[$key]['total']++;
                    if (($item['answered'] ?? false) === true) {
                        $groups[$key]['answered']++;
                        if ($item['correct']) {
                            $groups[$key]['correct']++;
                        }
                    }
                }
            }

            if ($groups === []) {
                $subject = self::text($baseContext['subject'] ?? '');
                if ($subject === '' || $subject === '*') {
                    continue;
                }
                $key = self::contextKey($baseContext, $subject);
                $groups[$key] = [
                    'context' => $baseContext,
                    'total' => max(0, (int) ($attempt['total'] ?? 0)),
                    'answered' => max(0, (int) ($attempt['answered'] ?? 0)),
                    'correct' => max(0, (int) ($attempt['correct'] ?? 0)),
                    'legacy_percentage' => (int) ($attempt['percentage'] ?? 0),
                ];
            }

            foreach ($groups as $key => $group) {
                $context = $group['context'];
                $rows[$key] ??= [
                    'subject' => $context['subject'],
                    'board' => $context['board'],
                    'let_level' => $context['let_level'],
                    'let_specialization' => $context['let_specialization'],
                    'civil_service_level' => $context['civil_service_level'],
                    'attempts' => 0,
                    'average' => 0,
                    'best' => 0,
                    'answered_questions' => 0,
                    'correct_answers' => 0,
                    'incorrect_answers' => 0,
                    'unanswered_questions' => 0,
                    'accuracy' => null,
                    'recent_accuracy' => null,
                    'recent_answered_questions' => 0,
                    'recent_attempts' => 0,
                    'recent_accuracy_status' => 'NO_ANSWERED_QUESTIONS',
                    'trend' => 'INSUFFICIENT_EVIDENCE',
                    'last_practiced_at' => null,
                    '_score_sum' => 0,
                    '_scores' => [],
                    '_events' => [],
                    '_attempt_keys' => [],
                ];
                $attemptKey = self::attemptKey($attempt, $attemptIndex);
                if (isset($rows[$key]['_attempt_keys'][$attemptKey])) {
                    continue;
                }
                $rows[$key]['_attempt_keys'][$attemptKey] = true;

                $total = max(0, (int) ($group['total'] ?? 0));
                $answered = min($total, max(0, (int) ($group['answered'] ?? 0)));
                $correct = min($answered, max(0, (int) ($group['correct'] ?? 0)));
                $incorrect = max(0, $answered - $correct);
                $unanswered = max(0, $total - $answered);
                $score = $total > 0
                    ? (int) round(100 * $correct / $total)
                    : max(0, min(100, (int) ($group['legacy_percentage'] ?? 0)));

                $rows[$key]['attempts']++;
                $rows[$key]['_score_sum'] += $score;
                $rows[$key]['_scores'][] = $score;
                $rows[$key]['best'] = max($rows[$key]['best'], $score);
                $rows[$key]['answered_questions'] += $answered;
                $rows[$key]['correct_answers'] += $correct;
                $rows[$key]['incorrect_answers'] += $incorrect;
                $rows[$key]['unanswered_questions'] += $unanswered;
                $date = LearningHistoryService::dateOf($attempt);
                if (is_string($date) && ($rows[$key]['last_practiced_at'] === null
                    || LearningHistoryService::timestampOf($attempt) > (int) strtotime((string) $rows[$key]['last_practiced_at']))) {
                    $rows[$key]['last_practiced_at'] = $date;
                }
                $rows[$key]['_events'][] = [
                    'answered' => $answered,
                    'correct' => $correct,
                    'timestamp' => LearningHistoryService::timestampOf($attempt),
                    'index' => $attemptIndex,
                ];
            }
        }

        foreach ($rows as &$row) {
            $row['average'] = (int) round($row['_score_sum'] / max(1, $row['attempts']));
            $row['accuracy'] = $row['answered_questions'] > 0
                ? (int) round(100 * $row['correct_answers'] / $row['answered_questions'])
                : null;
            $events = $row['_events']; // ordered newest-first by LearningHistoryService
            $recentEvents = array_slice($events, 0, self::RECENT_ATTEMPT_LIMIT);
            $row['recent_attempts'] = count($recentEvents);
            foreach ($recentEvents as $event) {
                $row['recent_answered_questions'] += (int) $event['answered'];
            }
            $recentCorrect = array_sum(array_column($recentEvents, 'correct'));
            $row['recent_accuracy_status'] = $row['recent_answered_questions'] === 0
                ? 'NO_ANSWERED_QUESTIONS'
                : ($row['recent_answered_questions'] < self::MIN_ACCURACY_EVENTS ? 'INSUFFICIENT_SAMPLE' : 'AVAILABLE');
            if ($row['recent_accuracy_status'] === 'AVAILABLE') {
                $row['recent_accuracy'] = (int) round(100 * $recentCorrect / $row['recent_answered_questions']);
            }
            $previousEvents = array_slice($events, self::RECENT_ATTEMPT_LIMIT, self::RECENT_ATTEMPT_LIMIT);
            $previousAnswered = array_sum(array_column($previousEvents, 'answered'));
            if ($row['recent_accuracy'] !== null && $previousAnswered >= self::MIN_ACCURACY_EVENTS) {
                $previousCorrect = array_sum(array_column($previousEvents, 'correct'));
                $previousAccuracy = (int) round(100 * $previousCorrect / $previousAnswered);
                $change = $row['recent_accuracy'] - $previousAccuracy;
                $row['trend'] = $change >= 5 ? 'IMPROVING' : ($change <= -5 ? 'DECLINING' : 'STEADY');
                $row['recent_change_points'] = $change;
                $row['previous_accuracy'] = $previousAccuracy;
                $row['previous_answered_questions'] = $previousAnswered;
            }
            unset($row['_score_sum'], $row['_scores'], $row['_events'], $row['_attempt_keys']);
        }
        unset($row);

        $rows = array_values($rows);
        usort($rows, static fn(array $a, array $b): int =>
            ($b['average'] <=> $a['average'])
            ?: strcasecmp((string) ($a['board'] ?? ''), (string) ($b['board'] ?? ''))
            ?: strcasecmp($a['subject'], $b['subject'])
        );
        return $rows;
    }

    private static function contextKey(array $context, string $subject): string
    {
        $context['subject'] = $subject;
        return implode("\0", array_map(
            static fn(string $field): string => strtolower((string) ($context[$field] ?? '')),
            ['board', 'let_level', 'let_specialization', 'civil_service_level', 'subject']
        ));
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

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
