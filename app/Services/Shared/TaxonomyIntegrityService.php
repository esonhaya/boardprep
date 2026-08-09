<?php

declare(strict_types=1);

namespace App\Services\Shared;

use App\Core\App;
use App\Repositories\QuestionRepository;

class TaxonomyIntegrityService
{
    private static function repository(): QuestionRepository
    {
        return App::container()
            ->get(
                QuestionRepository::class
            );
    }

    public static function analyze(): array
    {
        return self::rebuild();
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
                    TaxonomyStorageService::boards()
                ),

            "subjects" =>
                count(
                    TaxonomyStorageService::subjects()
                ),

            "domains" =>
                count(
                    TaxonomyStorageService::domains()
                ),

            "topics" =>
                count(
                    TaxonomyStorageService::topics()
                ),

            "concepts" =>
                count(
                    TaxonomyStorageService::concepts()
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
