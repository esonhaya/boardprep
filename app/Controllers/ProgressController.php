<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Learning\LearningStatisticsService;

class ProgressController
{
    public static function index(): void
    {
        $stats =
            LearningStatisticsService::summary();

        \App\Core\View::render(
            "progress/index",
            [
                "stats" => $stats
            ]
        );
    }

    public static function getStats(): array
    {
        return LearningStatisticsService::summary();
    }
}
