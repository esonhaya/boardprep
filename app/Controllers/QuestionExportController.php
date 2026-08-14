<?php

declare(strict_types=1);

namespace App\Controllers;
use App\Services\Question\QuestionExportService;

class QuestionExportController
{
    public static function export(): void
    {
        QuestionExportService::export();
    }
}
