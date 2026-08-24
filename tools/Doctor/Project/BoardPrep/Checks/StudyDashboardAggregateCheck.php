<?php
declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

use App\Services\Learning\LearningProgressService;
use App\Services\Learning\LearningStreakService;
use App\Services\Learning\StudyDashboardService;
use App\Services\Learning\StudyInsightService;
use App\Services\Learning\StudyRecommendationService;
use App\Services\Learning\TopicPerformanceService;

final class StudyDashboardAggregateCheck
{
    public static function run(): array
    {
        $attempts = [];

        $progress = LearningProgressService::build($attempts);
        $topics = TopicPerformanceService::summarize($attempts);
        $weakest = TopicPerformanceService::weakest($attempts, 3);
        $streak = LearningStreakService::current($attempts);
        $insight = StudyInsightService::build($attempts, $weakest);
        $recommendations =
            StudyRecommendationService::build($attempts, $weakest, 3);
        $dashboard = StudyDashboardService::build($attempts);

        return [
            "progress_api" => is_array($progress),
            "topic_summary_api" => is_array($topics),
            "topic_weakest_api" => is_array($weakest),
            "streak_api" => is_int($streak),
            "insight_api" => is_array($insight),
            "recommendation_api" => is_array($recommendations),
            "dashboard" => is_array($dashboard),
            "dashboard_progress" => isset($dashboard["progress"]),
            "dashboard_topics" => isset($dashboard["topics"]),
            "dashboard_plan" => !empty($dashboard["studyPlan"]),
            "plan_actionable" =>
                str_starts_with(
                    (string) ($dashboard["studyPlan"][0]["action"] ?? ""),
                    "/quiz?"
                ),
        ];
    }
}
