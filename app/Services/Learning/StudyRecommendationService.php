<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Services\Learning\Recommendation\StudyRecommendationFactory;

final class StudyRecommendationService
{
    public static function build(array $attempts, array $weakestTopics = [], int $limit = 3): array
    {
        $recommendations = [];

        foreach (array_slice($weakestTopics, 0, max(0, $limit)) as $topic) {
            if (!is_array($topic)) {
                continue;
            }
            $recommendation = StudyRecommendationFactory::forTopic($topic);
            if ($recommendation !== null) {
                $recommendations[] = $recommendation;
            }
        }

        if (count($attempts) < 3) {
            $recommendations[] = StudyRecommendationFactory::general(
                'Build more history',
                'Complete a few more quizzes to make your insights more reliable.'
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
