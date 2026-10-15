<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Services\Learning\LearningAttemptNormalizer;
use App\Services\Learning\Recommendation\StudyRecommendationFactory;

final class StudyRecommendationService
{
    public static function build(array $attempts, array $weakestTopics = [], int $limit = 3): array
    {
        $recommendations = [];
        $seen = [];

        foreach (array_slice($weakestTopics, 0, max(0, $limit)) as $topic) {
            if (!is_array($topic)) {
                continue;
            }
            $recommendation = StudyRecommendationFactory::forTopic($topic);
            if ($recommendation !== null) {
                $key = strtolower(
                    (string) ($recommendation['board'] ?? '') . "\0"
                    . (string) ($recommendation['let_level'] ?? '') . "\0"
                    . (string) ($recommendation['let_specialization'] ?? '') . "\0"
                    . (string) ($recommendation['civil_service_level'] ?? '') . "\0"
                    . (string) ($recommendation['subject'] ?? '') . "\0"
                    . (string) ($recommendation['topic'] ?? '')
                );
                if (isset($seen[$key])) {
                    continue;
                }
                $recommendations[] = $recommendation;
                $seen[$key] = true;
            }
        }

        $completedAttemptCount = LearningAttemptNormalizer::total($attempts);
        if ($completedAttemptCount < 3) {
            $recommendations[] = StudyRecommendationFactory::general(
                'Build more history',
                'Complete a few more quizzes to make topic trends more reliable. Recommendations use completed attempts only.'
            );
        }

        if ($recommendations === []) {
            $recommendations[] = StudyRecommendationFactory::general(
                'Take another practice quiz',
                'Use another attempt to measure your current performance.'
            );
        }

        return array_slice($recommendations, 0, max(1, $limit));
    }
}
