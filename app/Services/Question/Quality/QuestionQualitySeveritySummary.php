<?php

declare(strict_types=1);

namespace App\Services\Question\Quality;

final class QuestionQualitySeveritySummary
{
    /** @param array<int,object> $issues */
    public static function build(array $issues): array
    {
        $summary = ['error' => 0, 'warning' => 0, 'info' => 0];

        foreach ($issues as $issue) {
            $severity = strtolower((string) ($issue->severity ?? 'info'));
            $summary[$severity] = ($summary[$severity] ?? 0) + 1;
        }

        return $summary;
    }
}
