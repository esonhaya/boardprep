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

        $requests =
            BlueprintDistributionService::distribution(

                $boardBlueprint,

                $subjectBlueprints,

                $specification->questionCount

            );

        $requests =
            RequestExecutionPlanService::build(
                $requests
            );

        $session =
            new SelectionSession();

        $selected = [];

        foreach ($requests as $request) {

            $result =
                QuestionSelectionService::fulfillRequest(

                    $session->available($questions),

                    $request

                );

            $chunk =
                ShortageRecoveryService::recover(
                    $result,
                    $questions
                );

            $session->reserve($chunk);

            $selected = array_merge(
                $selected,
                $chunk
            );

        }

        $coverage =
            BlueprintCoverageAnalyzer::analyze(

                $selected,

                $boardBlueprint,

                $subjectBlueprints,

                $requests

            );

        $issues =
            BlueprintCoverageValidator::validate(
                $coverage
            );

        return new BlueprintExecutionResult(

            questions:
                $selected,

            requests:
                $requests,

            coverage:
                $coverage,

            issues:
                $issues,

            boardBlueprintVersion:
                isset($boardBlueprint["version"])
                    ? (int) $boardBlueprint["version"]
                    : null,

            subjectBlueprintVersion:
                isset($subjectBlueprints[$specification->subject]["version"])
                    ? (int) $subjectBlueprints[$specification->subject]["version"]
                    : null

        );

    }
}
