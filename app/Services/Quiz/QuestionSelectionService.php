<?php

declare(strict_types=1);

final class QuestionSelectionService
{
    public static function fulfillRequest(
        array $questions,
        SelectionRequest $request
    ): SelectionResult {

        $selected =
            SelectionDeduplicator::unique(

                WeightedShuffleService::shuffle(

                    DifficultySelectionService::select(

                        SelectionPool::create(
                            $questions,
                            $request
                        ),

                        $request->difficultyDistribution,

                        $request->questionCount

                    )

                )

            );

        return new SelectionResult(

            questions:
                $selected,

            fulfilled:
                BlueprintQuotaValidator::validate(
                    $selected,
                    $request
                ),

            request:
                $request

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
