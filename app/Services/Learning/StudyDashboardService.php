<?php
declare(strict_types=1);

namespace App\Services\Learning;

final class StudyDashboardService
{
    public static function build(array $attempts = []): array
    {
        $attempts = LearningAttemptNormalizer::all($attempts);
        $progress = LearningProgressService::build($attempts);
        $topics = TopicPerformanceService::summarize($attempts);
        $weakestTopics = TopicPerformanceService::weakest($attempts, 3);
        $streak = LearningStreakService::current($attempts);
        $insight = StudyInsightService::build($attempts, $weakestTopics);
        $recommendations = StudyRecommendationService::build($attempts, $weakestTopics, 3);

        $dashboard = [
            "progress" => $progress,
            "topics" => $topics,
            "weakestTopics" => $weakestTopics,
            "streak" => $streak,
            "insight" => $insight,
            "recommendations" => $recommendations,
        ];

        $dashboard["studyPlan"] = StudyPlanService::build($dashboard);

        return $dashboard;
    }
}
