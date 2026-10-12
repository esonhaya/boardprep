<?php
declare(strict_types=1);

namespace App\Services\Learning;

final class StudyDashboardService
{
    public static function build(array $attempts = []): array
    {
        $progress = LearningProgressService::build($attempts);
        $attempts = LearningHistoryService::ordered($attempts);
        $topics = TopicPerformanceService::summarize($attempts);
        $subjects = SubjectPerformanceService::summarize($attempts);
        $weakestTopics = TopicPerformanceService::weakest($attempts, 3);
        $focusTopics = RelativePerformanceComparisonService::belowContextAverage($topics, 3);
        $strongestSubjects = RelativePerformanceComparisonService::aboveContextAverage($subjects, 3);
        $streak = LearningStreakService::current($attempts);
        $insight = StudyInsightService::build($attempts, $focusTopics);
        $recommendations = StudyRecommendationService::build($attempts, $weakestTopics, 3);

        $dashboard = [
            "progress" => $progress,
            "topics" => $topics,
            "subjects" => $subjects,
            "weakestTopics" => $weakestTopics,
            "focusTopics" => $focusTopics,
            "strongestSubjects" => $strongestSubjects,
            "streak" => $streak,
            "insight" => $insight,
            "recommendations" => $recommendations,
        ];

        $dashboard["studyPlan"] = StudyPlanService::build($dashboard);

        return $dashboard;
    }
}
