<?php

declare(strict_types=1);

namespace App\Services\Question;

use App\Services\Question\Quality\QuestionQualityReportCacheService;
use App\Services\Question\Quality\QuestionQualityReportPresenter;

final class QuestionQualityService
{
    /** @return array<string, mixed> */
    public static function analyze(): array
    {
        [$report, $evidenceState] = QuestionQualityReportCacheService::currentReport();
        return QuestionQualityReportPresenter::present($report) + [
            'qualityEvidenceState' => $evidenceState,
            'qualityEvidenceGeneratedAt' => $report->generatedAt,
        ];
    }
}
