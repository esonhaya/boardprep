<?php

declare(strict_types=1);

final class ExamAssemblyService
{
    public static function assemble(
        array $questions,
        array $options = []
    ): array {

        $specification =
            QuizSpecificationBuilder::build(
                $options
            );

        $tracker =
            new CoverageTracker();

        $exam = [];

        $blueprints =
            BlueprintResolverService::resolve(
                $specification
            );

        $requests =
            BlueprintFulfillmentService::requests(

                $blueprints["subject"] ?? [],

                $specification->questionCount

            );

        foreach ($requests as $request) {

            $result =
                QuestionSelectionService::fulfillRequest(
                    $questions,
                    $request
                );

            $chunk =
                ShortageRecoveryService::recover(
                    $result,
                    $questions
                );

            $tracker->add(
                $chunk
            );

            $exam = array_merge(
                $exam,
                $chunk
            );

        }

        return array_slice(
            $exam,
            0,
            $specification->questionCount
        );

    }
}
