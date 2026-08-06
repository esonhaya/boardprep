<?php

declare(strict_types=1);

final class QuizSpecificationBuilder
{
    public static function build(
        array $options = []
    ): QuizSpecification {

        $specification =
            new QuizSpecification(

                board:
                    $options["board"]
                    ?? "LET",

                subject:
                    $options["subject"]
                    ?? "",

                domain:
                    $options["domain"]
                    ?? null,

                topics:
                    $options["topics"]
                    ?? [],

                concepts:
                    $options["concepts"]
                    ?? [],

                difficulty:
                    $options["difficulty"]
                    ?? "mixed",

                questionCount:
                    (int) (
                        $options["limit"]
                        ?? 10
                    ),

                mode:
                    $options["mode"]
                    ?? "practice",

                adaptive:
                    (bool) (
                        $options["adaptive"]
                        ?? false
                    ),

                shuffle:
                    (bool) (
                        $options["shuffle"]
                        ?? true
                    ),

                blueprintVersion:
                    $options["blueprintVersion"]
                    ?? null

            );

        $blueprints =
            BlueprintResolverService::resolve(
                $specification
            );

        return new QuizSpecification(

            board:
                $specification->board,

            subject:
                $specification->subject,

            domain:
                $specification->domain,

            topics:
                !empty($specification->topics)
                    ? $specification->topics
                    : (
                        $blueprints["subject"]["topics"]
                        ?? []
                    ),

            concepts:
                !empty($specification->concepts)
                    ? $specification->concepts
                    : (
                        $blueprints["subject"]["concepts"]
                        ?? []
                    ),

            difficulty:
                $specification->difficulty,

            questionCount:
                $specification->questionCount,

            mode:
                $specification->mode,

            adaptive:
                $specification->adaptive,

            shuffle:
                $specification->shuffle,

            blueprintVersion:
                $specification->blueprintVersion

        );

    }
}
