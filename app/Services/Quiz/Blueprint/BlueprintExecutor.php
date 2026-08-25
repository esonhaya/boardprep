<?php

declare(strict_types=1);

final class BlueprintExecutor
{
    public static function execute(
        array $questions,
        array $boardBlueprint,
        array $subjectBlueprints,
        QuizSpecification $specification
    ): BlueprintExecutionResult {
        $requests = BlueprintRequestPlanBuilder::build(
            $boardBlueprint,
            $subjectBlueprints,
            $specification->questionCount
        );

        $selected = BlueprintRequestExecutor::execute(
            $questions,
            $requests
        );

        $coverage = BlueprintCoverageFinalizer::analyze(
            $selected,
            $boardBlueprint,
            $subjectBlueprints,
            $requests
        );

        $issues = BlueprintCoverageFinalizer::validate($coverage);

        return BlueprintExecutionResultFactory::create(
            $selected,
            $requests,
            $coverage,
            $issues,
            $boardBlueprint,
            $subjectBlueprints,
            $specification->subject
        );
    }
}
