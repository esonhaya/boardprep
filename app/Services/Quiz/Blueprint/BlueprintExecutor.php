<?php

declare(strict_types=1);

final class BlueprintExecutor
{
    public static function execute(
        array $questions,
        array $blueprint,
        QuizSpecification $specification
    ): BlueprintExecutionResult {

        $session =
            new SelectionSession();

        $requests =
            RequestPriorityService::sort(

                BlueprintDistributionService::distribution(
                    $blueprint,
                    $specification->questionCount
                )

            );

        $selected = [];

        foreach ($requests as $request) {

            $result =
                QuestionSelectionService::fulfillRequest(

                    $session->available(
                        $questions
                    ),

                    $request

                );

            $chunk =
                ShortageRecoveryService::recover(
                    $result,
                    $questions
                );

            $session->reserve(
                $chunk
            );

            $selected = array_merge(
                $selected,
                $chunk
            );

        }

        $coverage =
            BlueprintCoverageAnalyzer::analyze(
                $selected,
                $blueprint
            );

        return new BlueprintExecutionResult(

            questions:
                $selected,

            requests:
                $requests,

            coverage:
                $coverage,

            issues:
                BlueprintCoverageValidator::validate(
                    $coverage
                )

        );

    }
}
