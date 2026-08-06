<?php

declare(strict_types=1);

final class QuizGenerationService
{
    public static function generate(
        array $questions,
        QuizSpecification $specification
    ): BlueprintExecutionResult {

        $boardBlueprint =
            BlueprintResolutionService::board(
                $specification->board
            );

        $subjectBlueprint =
            BlueprintResolutionService::subject(
                $specification->board,
                $specification->subject
            );

        return BlueprintExecutor::execute(

            questions:
                $questions,

            boardBlueprint:
                $boardBlueprint,

            subjectBlueprints: [
                $specification->subject =>
                    $subjectBlueprint
            ],

            specification:
                $specification

        );

    }
}
