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
        $count = max(5, min(20, self::integer($input["count"] ?? 5, 5)));
        $mode = self::text($input["mode"] ?? "practice");
        if (!in_array($mode, ["practice", "exam", "review"], true)) {
            $mode = "practice";
        }

        return [
            "topic" => self::text($input["topic"] ?? ""),
            "subject" => self::text($input["subject"] ?? "English") ?: "English",
            "count" => $count,
            "difficulty" => self::difficulty($input["difficulty"] ?? "mixed"),
            "mode" => $mode,
        ];
    }

    public static function startUrl(array $input = []): string
    {
        return "/quiz?" . http_build_query(array_merge(
            ["action" => "start"],
            self::normalize($input)
        ));
    }

    public static function isValid(array $session): bool
    {
        return self::normalize($session) === $session;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : "";
    }

    private static function integer(mixed $value, int $fallback): int
    {
        return is_numeric($value) ? (int) round((float) $value) : $fallback;
    }

    private static function difficulty(mixed $value): string
    {
        $difficulty = strtolower(self::text($value));
        return in_array($difficulty, ["easy", "medium", "hard", "mixed"], true)
            ? $difficulty
            : "mixed";
    }
}
