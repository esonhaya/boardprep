<?php

declare(strict_types=1);

namespace App\Services\Learning;

final class StudySessionService
{
    public static function normalize(array $input = []): array
    {
        $count = (int) ($input["count"] ?? 5);
        $count = max(5, min(20, $count));

        $mode = (string) ($input["mode"] ?? "practice");
        if (!in_array($mode, ["practice", "exam", "review"], true)) {
            $mode = "practice";
        }

        $difficulty = (string) ($input["difficulty"] ?? "mixed");
        if (!in_array($difficulty, ["mixed", "easy", "medium", "hard"], true)) {
            $difficulty = "mixed";
        }

        return [
            "topic" => trim((string) ($input["topic"] ?? "")),
            "subject" => trim((string) ($input["subject"] ?? "English")) ?: "English",
            "count" => $count,
            "difficulty" => $difficulty,
            "mode" => $mode,
        ];
    }

    public static function startUrl(array $input = []): string
    {
        $session = self::normalize($input);

        $query = [
            "action" => "start",
            "topic" => $session["topic"],
            "subject" => $session["subject"],
            "count" => (string) $session["count"],
            "difficulty" => $session["difficulty"],
            "mode" => $session["mode"],
        ];

        return "/quiz?" . http_build_query($query);
    }

    public static function isValid(array $session): bool
    {
        $normalized = self::normalize($session);

        return
            ($session["topic"] ?? "") === $normalized["topic"]
            && ($session["subject"] ?? "") === $normalized["subject"]
            && (int) ($session["count"] ?? 0) === $normalized["count"]
            && ($session["difficulty"] ?? "") === $normalized["difficulty"]
            && ($session["mode"] ?? "") === $normalized["mode"];
    }
}
