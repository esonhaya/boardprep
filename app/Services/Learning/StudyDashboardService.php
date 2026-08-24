<?php

declare(strict_types=1);

namespace App\Services\Learning;

final class StudyDashboardService
{
    public static function build(array $attempts): array
    {
        $topics =
            TopicPerformanceService::summarize($attempts);

        $weakest =
            TopicPerformanceService::weakest($attempts, 3);

        return [
            "progress" =>
                LearningProgressService::build($attempts),
            "topics" =>
                $topics,
            "weakestTopics" =>
                $weakest,
            "streak" =>
                LearningStreakService::current($attempts),
            "insight" =>
                StudyInsightService::build(
                    $attempts,
                    $weakest
                ),
            "recommendations" =>
                StudyRecommendationService::build(
                    $attempts,
                    $weakest
                ),
        ];
    }
}
