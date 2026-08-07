<?php

declare(strict_types=1);

use App\Services\Question\QuestionExportService;

class QuestionExportController
{
    public static function export(): void
    {
        QuestionExportService::export();
    }
}
