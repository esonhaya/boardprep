<?php

declare(strict_types=1);

final class BaseSpecificationFactory
{
    public static function create(
        array $options
    ): QuizSpecification {

        return new QuizSpecification(

            board:
                $options["board"] ?? "LET",

            subject:
                $options["subject"] ?? "",

            domain:
                $options["domain"] ?? null,

            topics:
                $options["topics"] ?? [],

            concepts:
                $options["concepts"] ?? [],

            difficulty:
                $options["difficulty"] ?? "mixed",

            questionCount:
                (int)(
                    $options["questionCount"]
                    ??
                    $options["count"]
                    ??
                    $options["limit"]
                    ??
                    10
                ),

            mode:
                $options["mode"] ?? "practice",

            adaptive:
                (bool)($options["adaptive"] ?? false),

            shuffle:
                (bool)($options["shuffle"] ?? true),

            boardBlueprintVersion:
                null,

            subjectBlueprintVersion:
                null,

            letVariant:
                is_array($options['let_variant'] ?? null)
                    ? $options['let_variant']
                    : null,

            civilServiceLevel:
                is_string($options['civil_service_level'] ?? $options['civilServiceLevel'] ?? null)
                    ? strtoupper(trim((string) ($options['civil_service_level'] ?? $options['civilServiceLevel'])))
                    : null,

            timeLimitMinutes:
                max(0, (int) ($options['time_limit_minutes'] ?? 0)),

            subjectExplicit:
                (bool) ($options['subject_explicit'] ?? false),

        );

    }
}
