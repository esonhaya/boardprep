<?php

declare(strict_types=1);

final class QuizEngineService
{
    public static function generate(
        array $questions,
        array $options = []
    ): array {

        $specification =
            QuizSpecificationBuilder::build(
                $options
            );

        $questions =
            QuizHistoryService::filterUnused(
                $questions
            );

        $questions =
            QuestionSelectionService::select(
                $questions,
                $specification
            );

        $questions =
            AdaptiveQuizService::prioritize(
                $questions,
                $specification
            );

        if (
            $specification->shuffle
        ) {

            shuffle(
                $questions
            );

        }

        $questions =
            array_slice(
                $questions,
                0,
                $specification->questionCount
            );

        QuizHistoryService::remember(
            $questions
        );

        return array_values(
            $questions
        );

    }
}
