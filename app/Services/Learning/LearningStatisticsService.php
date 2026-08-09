<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Core\App;
use App\Repositories\AttemptRepository;

final class LearningStatisticsService
{
    public static function summary(): array
    {
        $attempts =
            App::container()
                ->get(AttemptRepository::class)
                ->all();

        $total = count($attempts);

        $practice = 0;
        $exam = 0;
        $best = 0;
        $latest = null;
        $sum = 0;

        foreach ($attempts as $attempt) {

            $sum += $attempt["percentage"] ?? 0;

            if (($attempt["mode"] ?? "") === "practice") {
                $practice++;
            }

            if (($attempt["mode"] ?? "") === "exam") {
                $exam++;
            }

            $best = max(
                $best,
                (int) ($attempt["percentage"] ?? 0)
            );

            $latest = $attempt;
        }

        return [
            "totalAttempts" => $total,
            "practice" => $practice,
            "exam" => $exam,
            "average" => $total > 0 ? round($sum / $total) : 0,
            "best" => $best,
            "latest" => $latest,
            "attempts" => array_reverse($attempts),
        ];
    }
}
