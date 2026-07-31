<?php

declare(strict_types=1);

namespace App\Services\RepositoryHealth\Engine;

use App\Services\RepositoryHealth\DTO\ValidationResult;

class HealthScoreCalculator
{
    public static function calculate(
        array $results
    ): float {
        $score = 100.0;

        foreach ($results as $result) {

            if (!$result instanceof ValidationResult) {
                continue;
            }

            foreach ($result->issues as $issue) {

                switch (strtolower($issue->severity)) {

                    case "error":
                        $score -= 5;
                        break;

                    case "warning":
                        $score -= 2;
                        break;

                    case "info":
                        $score -= 0.5;
                        break;
                }
            }
        }

        return max(0.0, round($score, 1));
    }
}
