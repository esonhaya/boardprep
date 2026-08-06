<?php

declare(strict_types=1);

final class AdaptiveQuizService
{
    public static function prioritize(
        array $questions,
        QuizSpecification $specification
    ): array {

        if (
            !$specification->adaptive
        ) {

            return $questions;

        }

        $weaknesses =
            WeaknessService::all();

        $priorityTopics = [];

        foreach (
            $weaknesses
            as $weakness
        ) {

            if (
                !isset(
                    $weakness["topic"]
                )
            ) {
                continue;
            }

            $priorityTopics[] =
                strtolower(
                    trim(
                        $weakness["topic"]
                    )
                );

        }

        $priorityTopics =
            array_unique(
                $priorityTopics
            );

        $priority = [];
        $normal = [];

        foreach (
            $questions
            as $question
        ) {

            $topic =
                strtolower(
                    trim(
                        $question["topic"]
                        ?? ""
                    )
                );

            if (
                in_array(
                    $topic,
                    $priorityTopics,
                    true
                )
            ) {

                $priority[] =
                    $question;

            } else {

                $normal[] =
                    $question;

            }

        }

        shuffle(
            $priority
        );

        shuffle(
            $normal
        );

        return array_values(

            array_merge(
                $priority,
                $normal
            )

        );

    }
}
