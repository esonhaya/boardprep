<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Services\Learning\LearningHistoryService;
use App\Services\Learning\LearningProgressService;

final class HistoryController
{
    public static function index(): void
    {
        $attempts = LearningHistoryService::all();
        View::render("history/index", [
            "pageTitle" => "Quiz History",
            "history" => $attempts,
            "summary" => LearningProgressService::build($attempts),
        ]);
    }
}
