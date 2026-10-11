<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Services\Shared\TaxonomyStorageService;

final class StudyPlanService
{
    public static function build(array $dashboard): array
    {
        $plan = [];

        foreach ($dashboard["weakestTopics"] ?? [] as $topic) {
            $name = self::text($topic["topic"] ?? "");
            if ($name === "") {
                continue;
            }

            $plan[] = self::item(
                $name,
                "Focus",
                (int) ($topic["average"] ?? 0),
                self::text($topic["subject"] ?? "English"),
                $topic
            );
        }

        foreach ($dashboard["recommendations"] ?? [] as $recommendation) {
            $name = self::text($recommendation["topic"] ?? "");
            $subject = self::text($recommendation["subject"] ?? "English") ?: "English";
            if ($name === "" || self::contains($plan, $name, $subject, $recommendation)) {
                continue;
            }

            $plan[] = self::item(
                $name,
                "Recommended",
                null,
                $subject,
                $recommendation
            );
        }

        if (empty($plan)) {
            $plan[] = self::item("General", "Start", null, "English");
        }

        return array_slice($plan, 0, 5);
    }

    private static function item(
        string $topic,
        string $type,
        ?int $average,
        string $subject,
        array $context = []
    ): array {
        $board = self::text($context['board'] ?? '');
        $letLevel = self::text($context['let_level'] ?? '');
        $letSpecialization = self::text($context['let_specialization'] ?? '');
        $civilServiceLevel = self::text($context['civil_service_level'] ?? '');
        $spec = StudyActionService::quizForTopic(
            $topic,
            $subject !== "" ? $subject : "English",
            'practice',
            5,
            'mixed',
            $board,
            $letLevel,
            $letSpecialization,
            $civilServiceLevel
        );

        return [
            "topic" => $topic,
            "subject" => $spec["subject"],
            "board" => $board,
            "let_level" => $letLevel,
            "let_specialization" => $letSpecialization,
            "civil_service_level" => $civilServiceLevel,
            "type" => $type,
            "average" => $average,
            "action" => StudyActionService::url($spec),
            "label" => $topic === "General"
                ? "Start a practice quiz"
                : "Practice " . TaxonomyStorageService::label('taxonomy/topics', $topic),
        ];
    }

    private static function contains(array $plan, string $topic, string $subject, array $context): bool
    {
        $sameContext = static function (array $item) use ($context): bool {
            foreach (['board', 'let_level', 'let_specialization', 'civil_service_level'] as $field) {
                if (strcasecmp(
                    (string) ($item[$field] ?? ''),
                    (string) ($context[$field] ?? '')
                ) !== 0) {
                    return false;
                }
            }

            return true;
        };

        foreach ($plan as $item) {
            if (
                strcasecmp(
                    (string) ($item["topic"] ?? ""),
                    $topic
                ) === 0
                && strcasecmp(
                    (string) ($item["subject"] ?? ""),
                    $subject
                ) === 0
                && $sameContext($item)
            ) {
                return true;
            }
        }

        return false;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : "";
    }
}
