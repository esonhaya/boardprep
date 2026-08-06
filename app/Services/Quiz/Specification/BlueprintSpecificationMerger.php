<?php

declare(strict_types=1);

final class BlueprintSpecificationMerger
{
    public static function merge(
        QuizSpecification $specification,
        array $blueprints
    ): QuizSpecification {

        return new QuizSpecification(
            board: $specification->board,
            subject: $specification->subject,
            domain: $specification->domain,
            topics: !empty($specification->topics)
                ? $specification->topics
                : ($blueprints["subject"]["topics"] ?? []),
            concepts: !empty($specification->concepts)
                ? $specification->concepts
                : ($blueprints["subject"]["concepts"] ?? []),
            difficulty: $specification->difficulty,
            questionCount: $specification->questionCount,
            mode: $specification->mode,
            adaptive: $specification->adaptive,
            shuffle: $specification->shuffle,
            blueprintVersion: $specification->blueprintVersion,
        );

    }
}
