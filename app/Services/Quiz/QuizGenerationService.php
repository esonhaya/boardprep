<?php

declare(strict_types=1);

use App\Services\Quiz\LET\LETVariantExamService;

final class QuizGenerationService
{
    public static function generate(
        array $questions,
        QuizSpecification $specification
    ): BlueprintExecutionResult {

        if (strtolower(trim($specification->board)) === 'let'
            && $specification->mode === 'exam'
            && $specification->letVariant !== null) {
            return LETVariantExamService::execute($questions, $specification);
        }

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
