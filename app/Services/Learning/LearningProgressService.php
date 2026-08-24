<?php

declare(strict_types=1);

namespace App\Services\Learning;

final class LearningProgressService
{
    public static function build(array $attempts): array
    {
        $total = count($attempts);
        $completed = 0;
        $sum = 0;
        $best = 0;
        $practice = 0;
        $exam = 0;

        foreach ($attempts as $attempt) {
            if (($attempt["completed"] ?? true) === true) {
                $completed++;
            }

            $percentage = (int) ($attempt["percentage"] ?? 0);
            $sum += $percentage;
            $best = max($best, $percentage);

            if (($attempt["mode"] ?? "") === "practice") {
                $practice++;
            }

            if (($attempt["mode"] ?? "") === "exam") {
                $exam++;
            }
        }

        return [
            "totalAttempts" => $total,
            "completedAttempts" => $completed,
            "averageScore" => $total > 0 ? (int) round($sum / $total) : 0,
            "bestScore" => $best,
            "practiceAttempts" => $practice,
            "examAttempts" => $exam,
            "latest" => $attempts[0] ?? null,
        ];
    }
}
