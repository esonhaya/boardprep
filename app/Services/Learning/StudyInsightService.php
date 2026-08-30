<?php

declare(strict_types=1);

namespace App\Services\Learning;

final class StudyInsightService
{
    public static function build(
        array $attempts,
        array $weakestTopics = []
    ): array {
        $attempts = LearningAttemptNormalizer::all($attempts);
        $insights = [];

        if (empty($attempts)) {
            return [
                "headline" => "Start your first quiz",
                "message" => "Complete a quiz to begin building personalized study insights.",
                "actions" => [
                    "Take a practice quiz",
                ],
            ];
        }

        $average = self::average($attempts);
        $best = self::best($attempts);

        if ($average < 50) {
            $insights[] = "Focus on core concepts before increasing quiz difficulty.";
        } elseif ($average < 75) {
            $insights[] = "Keep practicing consistently and target your weakest topics.";
        } else {
            $insights[] = "Your overall performance is strong; maintain consistency and challenge yourself.";
        }

        if ($best >= 90) {
            $insights[] = "You have demonstrated high performance on at least one quiz.";
        }

        foreach (array_slice($weakestTopics, 0, 2) as $topic) {
            if (!empty($topic["topic"])) {
                $insights[] =
                    "Prioritize " . $topic["topic"] .
                    " (" . (int) ($topic["average"] ?? 0) . "% average).";
            }
        }

        return [
            "headline" => self::headline($average),
            "message" => $insights[0] ?? "Keep building your learning history.",
            "actions" => array_values(array_unique($insights)),
        ];
    }

    private static function average(array $attempts): int
    {
        if (empty($attempts)) {
            return 0;
        }

        $sum = 0;

        foreach ($attempts as $attempt) {
            $sum += (int) ($attempt["percentage"] ?? 0);
        }

        return (int) round($sum / count($attempts));
    }

    private static function best(array $attempts): int
    {
        $best = 0;

        foreach ($attempts as $attempt) {
            $best = max(
                $best,
                (int) ($attempt["percentage"] ?? 0)
            );
        }

        return $best;
    }

    private static function headline(int $average): string
    {
        if ($average >= 80) {
            return "You're on a strong track";
        }

        if ($average >= 60) {
            return "You're making progress";
        }

        return "Build your foundation";
    }
}
