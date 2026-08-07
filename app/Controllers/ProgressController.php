<?php

declare(strict_types=1);

use App\Services\Learning\LearningStatisticsService;

class ProgressController
{
    public static function getStats(): array
    {
        return LearningStatisticsService::summary();
    }
}
