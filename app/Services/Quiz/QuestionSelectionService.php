<?php

declare(strict_types=1);

final class QuestionSelectionService
{
    public static function fulfillRequest(
        array $questions,
        SelectionRequest $request
    ): SelectionResult {

        $pool = array_values(

            array_filter(

                $questions,

                static function (
                    array $question
                ) use (
                    $request
                ): bool {

                    return
                        ($question["subject"] ?? null) === $request->subject
                        &&
                        ($question["domain"] ?? null) === $request->domain;

                }

            )

        );

        $selected =
            SelectionDeduplicator::unique(

                WeightedShuffleService::shuffle(

                    DifficultySelectionService::select(

                        $pool,

                        $request->difficultyDistribution,

                        $request->questionCount

                    )

                )

            );

        return new SelectionResult(

            questions: $selected,

            fulfilled:
                BlueprintQuotaValidator::validate(
                    $selected,
                    $request
                ),

            request: $request

        );

    }

    public static function select(
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
