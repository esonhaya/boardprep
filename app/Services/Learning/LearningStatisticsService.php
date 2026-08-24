<?php

declare(strict_types=1);

namespace App\Services\Learning;

final class LearningStatisticsService
{
    public static function summary(): array
    {
        $attempts = LearningHistoryService::all();

        $total = count($attempts);
        $practice = 0;
        $exam = 0;
        $best = 0;
        $sum = 0;

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
            'totalAttempts' => $total,
            'practice' => $practice,
            'exam' => $exam,
            'average' =>
                $total > 0
                    ? (int) round($sum / $total)
                    : 0,
            'best' => $best,
            'latest' => $attempts[0] ?? null,
            'attempts' => $attempts,
        ];
    }
}
