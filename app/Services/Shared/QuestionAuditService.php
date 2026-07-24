<?php

class QuestionAuditService
{

    public static function summary(): array
    {

        $questions =
            QuestionRepository::all();

        $taxonomy =
            TaxonomyStatisticsService::summary();

        $quality =
            QuestionQualitySummaryService::summary();

        $coverage =
            CoverageSummaryService::summary();

        return [

            "questions" => [

                "total" =>
                    count($questions),

                "duplicates" =>
                    DuplicateQuestionSummaryService::summary()

            ],

            "taxonomy" =>
                $taxonomy,

            "quality" =>
                $quality,

            "coverage" =>
                $coverage

        ];

    }

}
