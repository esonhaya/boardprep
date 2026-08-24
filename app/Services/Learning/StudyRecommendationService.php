<?php

declare(strict_types=1);

namespace App\Services\Learning;

final class StudyRecommendationService
{
    public static function build(
        array $attempts,
        array $weakestTopics = [],
        int $limit = 3
    ): array {
        $recommendations = [];

        foreach (array_slice($weakestTopics, 0, max(0, $limit)) as $topic) {
            $name = trim((string) ($topic["topic"] ?? ""));

            if ($name === "") {
                continue;
            }

            $recommendations[] = [
                "type" => "topic",
                "title" => "Review {$name}",
                "reason" =>
                    "Current average: " .
                    (int) ($topic["average"] ?? 0) . "%",
                "topic" => $name,
            ];
        }

        if (count($attempts) < 3) {
            $recommendations[] = [
                "type" => "practice",
                "title" => "Build more history",
                "reason" => "Complete a few more quizzes to make your insights more reliable.",
            ];
        }

        if (empty($recommendations)) {
            $recommendations[] = [
                "type" => "practice",
                "title" => "Take another practice quiz",
                "reason" => "Use another attempt to measure your current performance.",
            ];
        }

        return array_slice($recommendations, 0, max(1, $limit));
    }
}
