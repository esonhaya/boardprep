<?php

declare(strict_types=1);

final class BoardExamAssemblyService
{
    public static function assemble(
        array $questions,
        QuizSpecification $specification,
        array $boardBlueprint
    ): array {

        $allocations =
            SubjectAllocationService::allocate(

                BoardBlueprintResolver::subjects(
                    $boardBlueprint
                ),

                $specification->questionCount

            );

        $exam = [];

        foreach ($allocations as $allocation) {

            $subjectSpecification =
                BlueprintSpecificationMerger::subject(

                    $specification,

                    $allocation["subject"],

                    $allocation["questions"]

                );

            $exam = array_merge(

                $exam,

                SubjectAssemblyService::assemble(

                    $questions,

                    $subjectSpecification

                )

            );

        }

        return $exam;

    }
}
