<?php

declare(strict_types=1);

final class SubjectAssemblyService
{
    public static function assemble(
        array $questions,
        QuizSpecification $specification
    ): array {

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

        )->questions;

    }
}
