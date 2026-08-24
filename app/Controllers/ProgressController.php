<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Services\Learning\LearningHistoryService;
use App\Services\Learning\LearningProgressService;
use App\Services\Learning\LearningStreakService;
use App\Services\Learning\TopicPerformanceService;

final class ProgressController
{
    public static function index(): void
    {
        $attempts = LearningHistoryService::all();

        View::render(
            "progress/index",
            [
                "summary" =>
                    LearningProgressService::build($attempts),
                "history" =>
                    LearningHistoryService::recent(10),
                "topics" =>
                    TopicPerformanceService::summarize($attempts),
                "weakestTopics" =>
                    TopicPerformanceService::weakest($attempts, 3),
                "streak" =>
                    LearningStreakService::current($attempts),
            ]
        );
    }

    public static function data(): array
    {
        $attempts = LearningHistoryService::all();

        return [
            "summary" =>
                LearningProgressService::build($attempts),
            "topics" =>
                TopicPerformanceService::summarize($attempts),
            "streak" =>
                LearningStreakService::current($attempts),
        ];
    }
}
