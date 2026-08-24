<?php

declare(strict_types=1);

namespace App\Services\Learning;

final class PerformanceAnalyticsService
{
    public static function summary(array $attempts): array
    {
        $total = count($attempts);

        if ($total === 0) {
            return [
                'totalQuizzes' => 0,
                'averageScore' => 0,
                'bestScore' => 0,
                'latestScore' => 0,
                'practiceQuizzes' => 0,
                'examQuizzes' => 0,
            ];
        }

        $sum = 0;
        $best = 0;
        $practice = 0;
        $exam = 0;

        foreach ($attempts as $attempt) {
            $percentage =
                (int) ($attempt['percentage'] ?? 0);

            $sum += $percentage;
            $best = max($best, $percentage);

            if (($attempt['mode'] ?? '') === 'practice') {
                $practice++;
            }

            if (($attempt['mode'] ?? '') === 'exam') {
                $exam++;
            }
        }

        return [
            'totalQuizzes' => $total,
            'averageScore' =>
                (int) round($sum / $total),
            'bestScore' => $best,
            'latestScore' =>
                (int) ($attempts[0]['percentage'] ?? 0),
            'practiceQuizzes' => $practice,
            'examQuizzes' => $exam,
        ];
    }
}
