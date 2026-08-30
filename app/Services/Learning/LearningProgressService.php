<?php

declare(strict_types=1);

namespace App\Services\Learning;

final class LearningProgressService
{
    public static function build(array $attempts): array
    {
        $attemptTotal = LearningAttemptNormalizer::total($attempts);
        $attempts = LearningHistoryService::ordered($attempts);
        $total = count($attempts);
        $completed = 0;
        $sum = 0;
        $best = 0;
        $practice = 0;
        $exam = 0;
        $answered = 0;
        $unanswered = 0;
        $correct = 0;
        $incorrect = 0;

        foreach ($attempts as $attempt) {
            if (($attempt["completed"] ?? true) === true) {
                $completed++;
            }

            $percentage = (int) ($attempt["percentage"] ?? 0);
            $sum += $percentage;
            $best = max($best, $percentage);
            $answered += (int) $attempt['answered'];
            $unanswered += (int) $attempt['unanswered'];
            $correct += (int) $attempt['correct'];
            $incorrect += (int) $attempt['incorrect'];

            if (($attempt["mode"] ?? "") === "practice") {
                $practice++;
            }

            if (($attempt["mode"] ?? "") === "exam") {
                $exam++;
            }
        }

        return [
            "totalAttempts" => $attemptTotal,
            "completedAttempts" => $completed,
            "averageScore" => $total > 0 ? (int) round($sum / $total) : 0,
            "bestScore" => $best,
            "practiceAttempts" => $practice,
            "examAttempts" => $exam,
            "answeredCount" => $answered,
            "unansweredCount" => $unanswered,
            "correctCount" => $correct,
            "incorrectCount" => $incorrect,
            "accuracy" => $answered > 0 ? (int) round(($correct / $answered) * 100) : 0,
            "trend" => PerformanceTrendService::summarizeOrdered($attempts),
            "latest" => $attempts[0] ?? null,
        ];
    }
}
