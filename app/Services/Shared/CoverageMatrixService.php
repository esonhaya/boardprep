<?php

declare(strict_types=1);

use App\Core\App;
use App\Repositories\QuestionRepository;
use App\Repositories\TaxonomyStorageService;

class CoverageMatrixService
{
    public static function build(): array
    {

        $questions =
            App::container()
                ->get(
                    QuestionRepository::class
                )
                ->all();

        $domains =
            TaxonomyStorageService::domains();

        $topics =
            TaxonomyStorageService::topics();

        $matrix = [];

        foreach (
            $domains as $domain
        ) {

            $domainId =
                $domain["id"];

            $matrix[$domainId] = [

                "domain" =>
                    $domain,

                "topics" =>
                    []

            ];

            foreach (
                $topics as $topic
            ) {

                if (

                    ($topic["domain"] ?? "")

                    !==

                    $domainId

                ) {

                    continue;

                }

                $count = 0;

                foreach (
                    $questions as $question
                ) {

                    $taxonomy =
                        $question["taxonomy"] ?? [];

                    if (

                        ($taxonomy["domain_id"] ?? "")

                        ===

                        $domainId

                        &&

                        ($taxonomy["topic_id"] ?? "")

                        ===

                        ($topic["id"] ?? "")

                    ) {

                        $count++;

                    }

                }

                $matrix[$domainId]["topics"][] = [

                    "topic" =>
                        $topic,

                    "count" =>
                        $count,

                    "status" =>
                        self::status(
                            $count
                        )

                ];

            }

        }       

 return $matrix;

    }

    private static function status(
        int $count
    ): string
    {

        if (
            $count === 0
        ) {

            return "missing";

        }

        if (
            $count < 5
        ) {

            return "needs-work";

        }

        return "healthy";

    }

}
