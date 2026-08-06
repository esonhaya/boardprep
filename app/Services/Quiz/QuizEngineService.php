<?php

declare(strict_types=1);

final class QuizEngineService
{
    public static function generate(
        array $questions,
        array $options = []
    ): array {

        $questions =
            ExamAssemblyService::assemble(
                $questions,
                $options
            );

        $specification =
            QuizSpecificationBuilder::build(
                $options
            );

        $questions =
            AdaptiveQuizService::prioritize(
                $questions,
                $specification
            );

        $questions =
            QuizHistoryService::filterUnused(
                $questions
            );

        if ($specification->shuffle) {
            shuffle($questions);
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
