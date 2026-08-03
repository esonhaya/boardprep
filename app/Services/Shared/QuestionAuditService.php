<?php

declare(strict_types=1);

use App\Core\App;
use App\Repositories\QuestionRepository;

class QuestionAuditService
{
    public static function summary(): array
    {

        $questions =
            App::container()
                ->get(
                    QuestionRepository::class
                )
                ->all();

        return [

            "questions" => [

                "total" =>
                    count($questions),

                "duplicates" =>
                    DuplicateQuestionSummaryService::summary()

            ],

            "taxonomy" =>
                TaxonomyStatisticsService::summary(),

            "quality" =>
                QuestionQualitySummaryService::summary(),

            "coverage" =>
                CoverageSummaryService::summary()

        ];

    }
}
