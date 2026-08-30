<?php

declare(strict_types=1);

namespace App\Services\Quiz\Result\Attempt;

final class AttemptSessionContext
{
    public static function fromSession(array $session): array
    {
        $sessionId = AttemptValueReader::text($session["id"] ?? null);

        return [
            "session_id" => $sessionId,
            "user_id" => $sessionId !== "" ? "session:" . $sessionId : "",
            "board" => AttemptValueReader::text($session["board"] ?? null),
            "subject" => AttemptValueReader::text($session["subject"] ?? null),
            "domain" => AttemptValueReader::text($session["domain"] ?? null),
            "mode" => AttemptValueReader::text($session["mode"] ?? null),
            "difficulty" => AttemptValueReader::text($session["difficulty"] ?? null),
            "started_at" => AttemptValueReader::text($session["started_at"] ?? null),
        ];
    }
}
