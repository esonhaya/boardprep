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

        $blueprints =
            BlueprintResolverService::resolve(
                $specification
            );

        $requests =
            BlueprintFulfillmentService::requests(
                $blueprints["subject"] ?? [],
                $specification->questionCount
            );

        $selected = [];

        foreach ($requests as $request) {

            $chunk =
                SubjectAssemblyService::assemble(

                    $questions,

                    new QuizSpecification(

                        board:
                            $specification->board,

                        subject:
                            $specification->subject,

                        domain:
                            $request->domain,

                        topics:
                            $request->topic
                                ? [$request->topic]
                                : [],

                        concepts:
                            $request->concept
                                ? [$request->concept]
                                : [],

                        difficulty:
                            $request->difficulty,

                        questionCount:
                            $request->questionCount,

                        mode:
                            $specification->mode,

                        adaptive:
                            false,

                        shuffle:
                            false,

                        blueprintVersion:
                            $specification->blueprintVersion

                    )

                );

            $selected = array_merge(
                $selected,
                $chunk
            );

        }

        return ShortageRecoveryService::recover(
            $selected,
            $questions,
            $specification->questionCount
        );

    }
}
