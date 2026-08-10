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

            /*
             * Both normal selection and shortage recovery must work
             * against the same remaining pool. This prevents a
             * recovery pass from reusing questions reserved by an
             * earlier blueprint request.
             */
            $available =
                $session->available(
                    $questions
                );

            $result =
                QuestionSelectionService::fulfillRequest(
                    $available,
                    $request
                );

            $chunk =
                ShortageRecoveryService::recover(
                    $result,
                    $available
                );

            $chunk =
                SelectionDeduplicator::unique(
                    $chunk
                );

            $session->reserve(
                $chunk
            );

            $selected =
                array_merge(
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
                isset($boardBlueprint['version'])
                    ? (int) $boardBlueprint['version']
                    : null,

            subjectBlueprintVersion:
                isset(
                    $subjectBlueprints[
                        $specification->subject
                    ]['version']
                )
                    ? (int) $subjectBlueprints[
                        $specification->subject
                    ]['version']
                    : null
        );
    }
}
