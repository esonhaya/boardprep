<?php

declare(strict_types=1);

namespace App\Services\Quiz\Result\Attempt;

final class AttemptScoreSummary
{
    public static function fromSummary(array $summary, int $fallbackTotal): array
    {
        $total = AttemptValueReader::nonNegativeInt(
            $summary["total"] ?? null,
            $fallbackTotal
        );

        $score = AttemptValueReader::nonNegativeInt($summary["score"] ?? null);
        $score = min($score, $total);

        $percentage = $total > 0
            ? round(($score / $total) * 100, 2)
            : 0.0;

        return [
            "score" => $score,
            "total" => $total,
            "percentage" => $percentage,
        ];
    }
}
