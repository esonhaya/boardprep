<?php

declare(strict_types=1);

use App\Core\App;
use App\Repositories\QuestionRepository;
use App\Repositories\TaxonomyRepository;

class TaxonomyIntegrityService
{
    private static function repository(): QuestionRepository
    {

        return App::container()
            ->get(
                QuestionRepository::class
            );

    }

    public static function rebuild(): array
    {

        return [

            "questions" =>
                self::questionIntegrity(),

            "taxonomy" =>
                self::taxonomyIntegrity(),

            "unused" =>
                self::unusedTaxonomy()

        ];

    }

    private static function questionIntegrity(): array
    {

        $issues = [];

        foreach (

            self::repository()->all()

            as $question

        ) {

            $taxonomy =
                $question["taxonomy"] ?? [];

            foreach (

                [

                    "board_id",
                    "subject_id",
                    "domain_id",
                    "topic_id",
                    "concept_id"

                ]

                as $field

            ) {

                if (

                    empty(
                        $taxonomy[$field]
                    )

                ) {

                    $issues[] = [

                        "question" =>
                            $question["id"] ?? "",

                        "issue" =>
                            "Missing {$field}"

                    ];

                }

            }

        }

        return $issues;

    }

    private static function taxonomyIntegrity(): array
    {

        return [

            "boards" =>
                count(
                    TaxonomyRepository::boards()
                ),

            "subjects" =>
                count(
                    TaxonomyRepository::subjects()
                ),

            "domains" =>
                count(
                    TaxonomyRepository::domains()
                ),

            "topics" =>
                count(
                    TaxonomyRepository::topics()
                ),

            "concepts" =>
                count(
                    TaxonomyRepository::concepts()
                )

        ];

    }

    private static function unusedTaxonomy(): array
    {

        return [

            "boards" => [],
            "subjects" => [],
            "domains" => [],
            "topics" => [],
            "concepts" => []

        ];

    }

}
