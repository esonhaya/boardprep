<?php

declare(strict_types=1);

namespace App\Services\Learning;

final class StudyPlanService
{
    public static function build(array $dashboard): array
    {
        $plan = [];

        foreach ($dashboard["weakestTopics"] ?? [] as $topic) {
            $name = trim((string) ($topic["topic"] ?? ""));
            if ($name === "") {
                continue;
            }

            $plan[] = self::item(
                $name,
                "Focus",
                (int) ($topic["average"] ?? 0)
            );
        }

        foreach ($dashboard["recommendations"] ?? [] as $recommendation) {
            $name = trim((string) ($recommendation["topic"] ?? ""));
            if ($name === "" || self::contains($plan, $name)) {
                continue;
            }

            $plan[] = self::item($name, "Recommended", null);
        }

        if (empty($plan)) {
            $plan[] = self::item("General", "Start", null);
        }

        return array_slice($plan, 0, 5);
    }

    private static function item(
        string $topic,
        string $type,
        ?int $average
    ): array {
        $action = StudyActionService::quizForTopic($topic);

        return [
            "topic" => $topic,
            "type" => $type,
            "average" => $average,
            "action" => StudyActionService::url($action),
        ];
    }

    private static function contains(array $plan, string $topic): bool
    {
        foreach ($plan as $item) {
            if (
                strcasecmp(
                    (string) ($item["topic"] ?? ""),
                    $topic
                ) === 0
            ) {
                return true;
            }
        }

        return false;
    }
}
