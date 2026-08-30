<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Services\Learning\Recommendation\TopicPerformanceAccumulator;

final class TopicPerformanceService
{
    public static function summarize(array $attempts): array
    {
        $attempts = LearningAttemptNormalizer::all($attempts);
        $topics = TopicPerformanceAccumulator::summarize($attempts);
        usort($topics, static function (array $a, array $b): int {
            $average = $b['average'] <=> $a['average'];
            return $average !== 0 ? $average : self::tieBreak($a, $b);
        });
        return array_values($topics);
    }

    public static function weakest(array $attempts, int $limit = 3): array
    {
        $attempts = LearningAttemptNormalizer::all($attempts);
        $topics = TopicPerformanceAccumulator::summarize($attempts);
        usort($topics, static function (array $a, array $b): int {
            $average = $a['average'] <=> $b['average'];
            return $average !== 0 ? $average : self::tieBreak($a, $b);
        });
        return array_slice($topics, 0, max(0, $limit));
    }

    private static function tieBreak(array $a, array $b): int
    {
        $subject = strcasecmp(
            (string) ($a['subject'] ?? ''),
            (string) ($b['subject'] ?? '')
        );

        return $subject !== 0
            ? $subject
            : strcasecmp(
                (string) ($a['topic'] ?? ''),
                (string) ($b['topic'] ?? '')
            );
    }
}
