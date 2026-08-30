<?php

declare(strict_types=1);

namespace App\Services\Question;

use App\Services\Question\Quality\QuestionQualityReportPresenter;
use App\Services\RepositoryHealth\Engine\RepositoryHealthEngine;

class QuestionQualityService
{
    public static function analyze(): array
    {
        return QuestionQualityReportPresenter::present(
            RepositoryHealthEngine::analyze()
        );
    }
}
