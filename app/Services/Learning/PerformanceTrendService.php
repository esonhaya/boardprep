<?php

declare(strict_types=1);

namespace App\Services\Learning;

final class PerformanceTrendService
{
    public static function summarize(array $attempts): array
    {
        return self::summarizeOrdered(LearningHistoryService::ordered($attempts));
    }

    public static function summarizeOrdered(array $ordered): array
    {
        $latest = $ordered[0]['percentage'] ?? null;
        $previous = $ordered[1]['percentage'] ?? null;
        $direction = 'insufficient_history';

        if ($latest !== null && $previous !== null) {
            $direction = $latest > $previous
                ? 'improving'
                : ($latest < $previous ? 'declining' : 'stable');
        }

        return [
            'direction' => $direction,
            'latestScore' => $latest === null ? null : (int) $latest,
            'previousScore' => $previous === null ? null : (int) $previous,
            'change' => $latest === null || $previous === null
                ? null
                : (int) $latest - (int) $previous,
        ];
    }
}
