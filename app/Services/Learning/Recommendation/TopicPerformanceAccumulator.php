<?php

declare(strict_types=1);

namespace App\Services\Learning\Recommendation;

final class TopicPerformanceAccumulator
{
    public static function summarize(array $attempts): array
    {
        $rows = [];

        foreach ($attempts as $attempt) {
            if (!is_array($attempt)) {
                continue;
            }

            $context = LearningAttemptContext::fromAttempt($attempt);
            $key = strtolower($context['subject']) . "\0" . strtolower($context['topic']);

            if (!isset($rows[$key])) {
                $rows[$key] = [
                    'topic' => $context['topic'],
                    'subject' => $context['subject'],
                    'attempts' => 0,
                    'average' => 0,
                    'best' => 0,
                    '_sum' => 0,
                ];
            }

            $percentage = self::percentage($attempt['percentage'] ?? 0);
            $rows[$key]['attempts']++;
            $rows[$key]['_sum'] += $percentage;
            $rows[$key]['best'] = max($rows[$key]['best'], $percentage);
        }

        foreach ($rows as &$row) {
            $row['average'] = (int) round($row['_sum'] / max(1, $row['attempts']));
            unset($row['_sum']);
        }
        unset($row);

        return array_values($rows);
    }

    private static function percentage(mixed $value): int
    {
        return max(0, min(100, is_numeric($value) ? (int) round((float) $value) : 0));
    }
}
