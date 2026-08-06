<?php

declare(strict_types=1);

final class BaseSpecificationFactory
{
    public static function create(
        array $options
    ): QuizSpecification {

        return new QuizSpecification(
            board: $options["board"] ?? "LET",
            subject: $options["subject"] ?? "",
            domain: $options["domain"] ?? null,
            topics: $options["topics"] ?? [],
            concepts: $options["concepts"] ?? [],
            difficulty: $options["difficulty"] ?? "mixed",
            questionCount: (int) ($options["limit"] ?? 10),
            mode: $options["mode"] ?? "practice",
            adaptive: (bool) ($options["adaptive"] ?? false),
            shuffle: (bool) ($options["shuffle"] ?? true),
            blueprintVersion: $options["blueprintVersion"] ?? null,
        );

    }
}
