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
        usort($topics, static fn(array $a, array $b): int => $b['average'] <=> $a['average']);
        return array_values($topics);
    }

    public static function weakest(array $attempts, int $limit = 3): array
    {
        $attempts = LearningAttemptNormalizer::all($attempts);
        $topics = TopicPerformanceAccumulator::summarize($attempts);
        usort($topics, static fn(array $a, array $b): int => $a['average'] <=> $b['average']);
        return array_slice($topics, 0, max(0, $limit));
    }
}
