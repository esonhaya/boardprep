<?php

declare(strict_types=1);

namespace App\Services\Learning;

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
                self::text($topic["subject"] ?? "English")
            );
        }

        foreach ($dashboard["recommendations"] ?? [] as $recommendation) {
            $name = self::text($recommendation["topic"] ?? "");
            $subject = self::text($recommendation["subject"] ?? "English") ?: "English";
            if ($name === "" || self::contains($plan, $name, $subject)) {
                continue;
            }

            $plan[] = self::item(
                $name,
                "Recommended",
                null,
                $subject
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
        string $subject
    ): array {
        $spec = StudyActionService::quizForTopic(
            $topic,
            $subject !== "" ? $subject : "English"
        );

        return [
            "topic" => $topic,
            "subject" => $spec["subject"],
            "type" => $type,
            "average" => $average,
            "action" => StudyActionService::url($spec),
            "label" => $topic === "General"
                ? "Start a practice quiz"
                : "Practice " . $topic,
        ];
    }

    private static function contains(array $plan, string $topic, string $subject): bool
    {
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
