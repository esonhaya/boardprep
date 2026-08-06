<?php

declare(strict_types=1);

namespace App\Services\Question;

use App\Core\App;
use App\Repositories\QuestionRepository;
use App\Services\Question\QuestionSearchService;

class QuestionQueryService
{
    public static function getQuestions(
        array $filters
    ): array {

        $questions =
            App::container()
                ->get(
                    QuestionRepository::class
                )
                ->all();

        $domainId =
            trim(
                $filters["domain"] ?? ""
            );

        $difficulty =
            trim(
                $filters["difficulty"] ?? ""
            );

        $topicId =
            trim(
                $filters["topic"] ?? ""
            );

        $search =
            trim(
                $filters["search"] ?? ""
            );

        if (

            $domainId !== ""

            ||

            $difficulty !== ""

            ||

            $topicId !== ""

        ) {

            $questions =
                QuestionSearchService::filter(
                    $domainId,
                    $difficulty,
                    $topicId
                );

        }

        if (
            $search !== ""
        ) {

            $questions =
                array_values(

                    array_filter(

                        $questions,

                        static function (
                            array $question
                        ) use (
                            $search
                        ): bool {

                            return in_array(
                                $question,
                                QuestionSearchService::search(
                                    $search
                                ),
                                true
                            );

                        }

                    )

                );

        }

        return $questions;

    }
}
