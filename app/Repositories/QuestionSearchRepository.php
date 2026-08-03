<?php

declare(strict_types=1);

use App\Core\App;
use App\Repositories\QuestionRepository;

class QuestionSearchRepository
{
    private static function questions(): array
    {

        return App::container()
            ->get(
                QuestionRepository::class
            )
            ->all();

    }

    public static function search(
        string $keyword
    ): array {

        $keyword =
            strtolower(
                trim($keyword)
            );

        if (
            $keyword === ""
        ) {

            return self::questions();

        }

        return array_values(

            array_filter(

                self::questions(),

                static function (
                    array $question
                ) use (
                    $keyword
                ): bool {

                    return

                        str_contains(
                            strtolower(
                                $question["question"] ?? ""
                            ),
                            $keyword
                        )

                        ||

                        str_contains(
                            strtolower(
                                $question["taxonomy"]["subject_id"] ?? ""
                            ),
                            $keyword
                        )

                        ||

                        str_contains(
                            strtolower(
                                $question["taxonomy"]["domain_id"] ?? ""
                            ),
                            $keyword
                        )

                        ||

                        str_contains(
                            strtolower(
                                $question["taxonomy"]["topic_id"] ?? ""
                            ),
                            $keyword
                        )

                        ||

                        str_contains(
                            strtolower(
                                $question["taxonomy"]["concept_id"] ?? ""
                            ),
                            $keyword
                        );

                }

            )

        );

    }

    public static function filter(
        string $domainId,
        string $difficulty,
        string $topicId
    ): array {

        return array_values(

            array_filter(

                self::questions(),

                static function (
                    array $question
                ) use (
                    $domainId,
                    $difficulty,
                    $topicId
                ): bool {

                    if (

                        $domainId !== ""

                        &&

                        ($question["taxonomy"]["domain_id"] ?? "")

                        !==

                        $domainId

                    ) {

                        return false;

                    }

                    if (

                        $difficulty !== ""

                        &&

                        ($question["difficulty"] ?? "")

                        !==

                        $difficulty

                    ) {

                        return false;

                    }

                    if (

                        $topicId !== ""

                        &&

                        ($question["taxonomy"]["topic_id"] ?? "")

                        !==

                        $topicId

                    ) {

                        return false;

                    }

                    return true;

                }

            )

        );

    }

    public static function bySubject(
        string $subjectId
    ): array {

        return array_values(

            array_filter(

                self::questions(),

                static fn(
                    array $question
                ): bool =>

                    ($question["taxonomy"]["subject_id"] ?? "")

                    ===

                    $subjectId

            )

        );

    }

    public static function byTopic(
        string $topicId
    ): array {

        return array_values(

            array_filter(

                self::questions(),

                static fn(
                    array $question
                ): bool =>

                    ($question["taxonomy"]["topic_id"] ?? "")

                    ===

                    $topicId

            )

        );

    }

}
