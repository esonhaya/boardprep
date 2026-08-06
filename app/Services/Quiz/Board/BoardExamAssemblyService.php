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

            $exam = array_merge(

                $exam,

                SubjectAssemblyService::assemble(

                    $questions,

                    new QuizSpecification(

                        board:
                            $specification->board,

                        subject:
                            $allocation["subject"],

                        domain:
                            null,

                        topics: [],

                        concepts: [],

                        difficulty:
                            "mixed",

                        questionCount:
                            $allocation["questions"],

                        mode:
                            $specification->mode,

                        adaptive:
                            false,

                        shuffle:
                            false,

                        blueprintVersion:
                            $specification->blueprintVersion

                    )

                )

            );

        }

        return $exam;

    }
}
