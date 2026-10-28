<?php
declare(strict_types=1);

namespace App\Services\Learning;

final class StudyDashboardService
{
    public static function build(array $attempts = [], array $preferences = [], bool $includeReadiness = true): array
    {
        $attemptTotal = LearningAttemptNormalizer::total($attempts);
        $attempts = LearningHistoryService::orderedNormalized(LearningAttemptNormalizer::all($attempts));
        $progress = LearningProgressService::buildFromOrdered($attempts, $attemptTotal);
        $topics = TopicPerformanceService::summarizeFromOrdered($attempts);
        $subjects = SubjectPerformanceService::summarizeFromOrdered($attempts);
        $weakestTopics = TopicPerformanceService::weakestFromSummary($topics, 3);
        $focusTopics = RelativePerformanceComparisonService::belowContextAverage($topics, 3);
        $strongestSubjects = RelativePerformanceComparisonService::aboveContextAverage($subjects, 3);
        $streak = LearningStreakService::current($attempts);
        $insight = StudyInsightService::buildFromMetrics($attempts, $focusTopics, $progress);
        $readiness = $includeReadiness ? LearningReadinessService::buildFromOrdered($attempts, $preferences) : null;
        $recommendations = StudyRecommendationService::build($attempts, $weakestTopics, 3, count($attempts));

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

        if ($readiness !== null) {
            $dashboard["readiness"] = $readiness;
        }

        $dashboard["studyPlan"] = StudyPlanService::build($dashboard);

        return $dashboard;
    }
}
