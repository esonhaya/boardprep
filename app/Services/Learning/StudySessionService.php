<?php

declare(strict_types=1);

namespace App\Services\Learning;

final class StudySessionService
{
    public static function fromTopic(
        string $topic,
        string $subject = "English",
        int $count = 5,
        string $difficulty = "mixed"
    ): array {
        return self::normalize([
            "topic" => $topic,
            "subject" => $subject,
            "count" => $count,
            "difficulty" => $difficulty,
        ]);
    }

    public static function normalize(array $input = []): array
    {
        $count = max(5, min(20, (int) ($input["count"] ?? 5)));
        $mode = (string) ($input["mode"] ?? "practice");
        if (!in_array($mode, ["practice", "exam", "review"], true)) {
            $mode = "practice";
        }

        return [
            "topic" => trim((string) ($input["topic"] ?? "")),
            "subject" => trim((string) ($input["subject"] ?? "English")) ?: "English",
            "count" => $count,
            "difficulty" => (string) ($input["difficulty"] ?? "mixed"),
            "mode" => $mode,
            "action" => (string) ($input["action"] ?? "start"),
        ];
    }

    public static function startUrl(array $input = []): string
    {
        return "/quiz?" . http_build_query(self::normalize($input));
    }

    public static function isValid(array $session): bool
    {
        return self::normalize($session) === $session;
    }
}
