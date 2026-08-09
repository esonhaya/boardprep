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

        $boardBlueprint =
            $blueprints['board']
            ?? [];

        $subjectBlueprints =
            $blueprints['subjects']
            ?? [];

        if (!is_array($boardBlueprint)) {
            $boardBlueprint = [];
        }

        if (!is_array($subjectBlueprints)) {
            $subjectBlueprints = [];
        }

        return BlueprintExecutor::execute(
            questions:
                $questions,

            boardBlueprint:
                $boardBlueprint,

            subjectBlueprints:
                $subjectBlueprints,

            specification:
                $specification
        );
    }
}
