<?php

declare(strict_types=1);

namespace App\Services\Learning;

final class StudyActionService
{
    public static function quizForTopic(
        string $topic,
        string $subject = "",
        string $mode = "practice",
        int $count = 5,
        string $difficulty = "mixed"
    ): array {
        $session = StudySessionService::normalize([
            "topic" => $topic,
            "subject" => $subject,
            "mode" => $mode,
            "count" => $count,
            "difficulty" => $difficulty,
        ]);

        return [
            "action" => "start",
            "topic" => $session["topic"],
            "subject" => $session["subject"],
            "mode" => $session["mode"],
            "count" => $session["count"],
            "difficulty" => $session["difficulty"],
        ];
    }

    public static function query(array $spec): string
    {
        return http_build_query([
            "action" => $spec["action"] ?? "start",
            "topic" => $spec["topic"] ?? "",
            "subject" => $spec["subject"] ?? "",
            "mode" => $spec["mode"] ?? "practice",
            "count" => $spec["count"] ?? 5,
            "difficulty" => $spec["difficulty"] ?? "mixed",
        ]);
    }

    public static function url(array|string $input = []): string
    {
        if (is_string($input)) {
            return $input;
        }

        return "/quiz?" . self::query($input);
    }

    public static function create(array $input = []): array
    {
        $session = StudySessionService::normalize($input);

        $action = self::quizForTopic(
            $session["topic"],
            $session["subject"],
            $session["mode"],
            $session["count"],
            $session["difficulty"]
        );

        $action["url"] = self::url($action);

        return $action;
    }

    public static function isValid(array $action): bool
    {
        return
            ($action["action"] ?? "") === "start"
            && is_string($action["url"] ?? null)
            && str_starts_with($action["url"], "/quiz?");
    }
}
