<?php

declare(strict_types=1);

namespace App\Services\Learning;

final class RecommendationService
{
    public static function generate(array $analytics, array $weaknesses): array
    {
        $recommendations = self::weaknessRecommendations($weaknesses);
        $performance = self::performanceRecommendation($analytics);
        if ($performance !== null) {
            array_unshift($recommendations, $performance);
        }

        return $recommendations !== []
            ? array_values(array_unique($recommendations))
            : ["Keep taking quizzes to build your learning profile."];
    }

    private static function performanceRecommendation(array $analytics): ?string
    {
        if (($analytics["totalQuizzes"] ?? 0) <= 0) {
            return null;
        }

        $average = (int) ($analytics["averageScore"] ?? 0);
        if ($average < 60) {
            return "Focus on fundamentals before taking another exam.";
        }
        if ($average < 80) {
            return "Keep practicing. You're making steady progress.";
        }

        return "Excellent progress. Try harder quizzes.";
    }

    private static function weaknessRecommendations(array $weaknesses): array
    {
        $unique = [];
        foreach (array_filter($weaknesses, "is_array") as $weakness) {
            $topic = trim((string) ($weakness["topic"] ?? ""));
            if ($topic === "") {
                continue;
            }

            $key = strtolower($topic);
            if (!isset($unique[$key])) {
                $unique[$key] = $weakness;
                continue;
            }
            $unique[$key]["accuracy"] = min(
                (int) ($unique[$key]["accuracy"] ?? 100),
                (int) ($weakness["accuracy"] ?? 100)
            );
        }

        usort($unique, static fn(array $a, array $b): int =>
            ((int) ($a["accuracy"] ?? 100) <=> (int) ($b["accuracy"] ?? 100))
            ?: strcasecmp((string) ($a["topic"] ?? ""), (string) ($b["topic"] ?? ""))
        );

        $recommendations = [];
        foreach ($unique as $weakness) {
            if ((int) ($weakness["accuracy"] ?? 100) < 70) {
                $recommendations[] = "Review: " . $weakness["topic"];
            }
        }

        return $recommendations;
    }
}
