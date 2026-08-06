<?php

declare(strict_types=1);

final class SubjectAssemblyService
{
    public static function assemble(
        array $questions,
        QuizSpecification $specification
    ): array {

        $blueprints =
            BlueprintResolverService::resolve(
                $specification
            );

        return BlueprintExecutor::execute(

            questions:
                $questions,

            boardBlueprint:
                $blueprints["board"],

            subjectBlueprints:
                $blueprints["subjects"],

            specification:
                $specification

        )->questions;

    }
}
