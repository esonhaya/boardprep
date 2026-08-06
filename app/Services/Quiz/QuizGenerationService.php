<?php

declare(strict_types=1);

final class QuizGenerationService
{
    public static function generate(
        array $questions,
        QuizSpecification $specification
    ): BlueprintExecutionResult {

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

        );

    }
}
