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
            $specification->questionCount,
            $specification->topics[0] ?? null,
            $specification->difficulty
        );
        $requests = array_map(
            static fn(SelectionRequest $request): SelectionRequest => new SelectionRequest(
                subject: $request->subject,
                domain: $request->domain,
                difficultyDistribution: $request->difficultyDistribution,
                questionCount: $request->questionCount,
                topic: $request->topic,
                concept: $request->concept,
                exam: $specification->board
            ),
            $requests
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
