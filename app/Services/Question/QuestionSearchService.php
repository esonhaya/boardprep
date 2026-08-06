<?php

declare(strict_types=1);

namespace App\Services\Question;

use App\Core\App;
use App\Repositories\QuestionRepository;

final class QuestionSearchService
{
    private static function repository(): QuestionRepository
    {
        return App::container()
            ->get(QuestionRepository::class);
    }

    public static function search(
        string $keyword
    ): array {

        $keyword = strtolower(trim($keyword));

        if ($keyword === "") {
            return self::repository()->all();
        }

        return array_values(

            array_filter(

                self::repository()->all(),

                static function (
                    array $question
                ) use (
                    $keyword
                ): bool {

                    return

                        str_contains(
                            strtolower($question["question"] ?? ""),
                            $keyword
                        )

                        ||

                        str_contains(
                            strtolower($question["taxonomy"]["subject_id"] ?? ""),
                            $keyword
                        )

                        ||

                        str_contains(
                            strtolower($question["taxonomy"]["domain_id"] ?? ""),
                            $keyword
                        )

                        ||

                        str_contains(
                            strtolower($question["taxonomy"]["topic_id"] ?? ""),
                            $keyword
                        )

                        ||

                        str_contains(
                            strtolower($question["taxonomy"]["concept_id"] ?? ""),
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

                self::repository()->all(),

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
                            !== $domainId
                    ) {
                        return false;
                    }

                    if (
                        $difficulty !== ""
                        &&
                        ($question["difficulty"] ?? "")
                            !== $difficulty
                    ) {
                        return false;
                    }

                    if (
                        $topicId !== ""
                        &&
                        ($question["taxonomy"]["topic_id"] ?? "")
                            !== $topicId
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

                self::repository()->all(),

                static fn(array $question): bool =>
                    ($question["taxonomy"]["subject_id"] ?? "")
                        === $subjectId

            )

        );

    }

    public static function byTopic(
        string $topicId
    ): array {

        return array_values(

            array_filter(

                self::repository()->all(),

                static fn(array $question): bool =>
                    ($question["taxonomy"]["topic_id"] ?? "")
                        === $topicId

            )

        );

    }
}
