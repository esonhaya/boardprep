<?php

declare(strict_types=1);

final class QuestionSelectionService
{
    public static function select(
        array $questions,
        QuizSpecification $specification
    ): array {

        $questions =
            self::filterDifficulty(
                $questions,
                $specification
            );

        $questions =
            self::filterTopics(
                $questions,
                $specification
            );

        $questions =
            self::filterConcepts(
                $questions,
                $specification
            );

        $questions =
            self::balanceTopics(
                self::groupByTopic(
                    $questions
                )
            );

        $questions =
            self::balanceConcepts(
                $questions
            );

        return
            self::recoverShortages(
                $questions,
                $specification
            );

    }

    private static function filterDifficulty(
        array $questions,
        QuizSpecification $specification
    ): array {

        if (
            $specification->difficulty === "mixed"
        ) {
            return $questions;
        }

        return array_values(

            array_filter(

                $questions,

                static fn(array $question): bool =>

                    strtolower(
                        $question["difficulty"] ?? ""
                    )

                    ===

                    strtolower(
                        $specification->difficulty
                    )

            )

        );

    }

    private static function filterTopics(
        array $questions,
        QuizSpecification $specification
    ): array {

        if (
            empty(
                $specification->topics
            )
        ) {
            return $questions;
        }

        $topics =
            array_map(
                "strtolower",
                $specification->topics
            );

        return array_values(

            array_filter(

                $questions,

                static fn(array $question): bool =>

                    in_array(

                        strtolower(
                            $question["topic"] ?? ""
                        ),

                        $topics,

                        true

                    )

            )

        );

    }

    private static function filterConcepts(
        array $questions,
        QuizSpecification $specification
    ): array {

        if (
            empty(
                $specification->concepts
            )
        ) {
            return $questions;
        }

        $concepts =
            array_map(
                "strtolower",
                $specification->concepts
            );

        return array_values(

            array_filter(

                $questions,

                static fn(array $question): bool =>

                    in_array(

                        strtolower(
                            $question["concept"] ?? ""
                        ),

                        $concepts,

                        true

                    )

            )

        );

    }

    private static function groupByTopic(
        array $questions
    ): array {

        $groups = [];

        foreach ($questions as $question) {

            $topic =
                strtolower(
                    trim(
                        $question["topic"]
                        ?? "__unknown__"
                    )
                );

            $groups[$topic][] =
                $question;

        }

        return $groups;

    }

    private static function balanceTopics(
        array $groups
    ): array {

        foreach ($groups as &$group) {
            shuffle($group);
        }

        unset($group);

        $balanced = [];

        while (!empty($groups)) {

            foreach (
                array_keys($groups)
                as $topic
            ) {

                if (
                    empty(
                        $groups[$topic]
                    )
                ) {

                    unset(
                        $groups[$topic]
                    );

                    continue;

                }

                $balanced[] =
                    array_shift(
                        $groups[$topic]
                    );

            }

        }

        return $balanced;

    }

    private static function balanceConcepts(
        array $questions
    ): array {

        $seen = [];
        $balanced = [];
        $remaining = [];

        foreach ($questions as $question) {

            $concept =
                strtolower(
                    trim(
                        $question["concept"]
                        ?? "__unknown__"
                    )
                );

            if (
                isset(
                    $seen[$concept]
                )
            ) {

                $remaining[] =
                    $question;

                continue;

            }

            $seen[$concept] = true;

            $balanced[] =
                $question;

        }

        return array_merge(
            $balanced,
            $remaining
        );

    }

    private static function recoverShortages(
        array $questions,
        QuizSpecification $specification
    ): array {

        return array_slice(

            $questions,

            0,

            $specification->questionCount

        );

    }
}
