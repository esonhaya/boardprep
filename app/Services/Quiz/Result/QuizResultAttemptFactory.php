<?php

declare(strict_types=1);

final class QuizResultAttemptFactory
{
    /**
     * @param array<string,mixed> $session
     * @param array<int,array<string,mixed>> $questions
     * @param array<string,mixed> $summary
     * @return array<string,mixed>
     */
    public static function create(
        array $session,
        array $questions,
        array $summary
    ): array {
        return [
            "id" => "attempt-" . bin2hex(random_bytes(8)),
            "session_id" => $session["id"],
            "user_id" => "session:" . $session["id"],
            "board" => $session["board"] ?? null,
            "subject" => $session["subject"] ?? null,
            "domain" => $session["domain"] ?? null,
            "mode" => $session["mode"] ?? null,
            "difficulty" => $session["difficulty"] ?? null,
            "question_count" => $session["question_count"] ?? count($questions),
            "question_ids" => $session["question_ids"] ?? [],
            "score" => $summary["score"] ?? 0,
            "total" => $summary["total"] ?? count($questions),
            "percentage" => $summary["percentage"] ?? 0,
            "completed" => true,
            "started_at" => $session["started_at"] ?? null,
            "completed_at" => date("c"),
        ];
    }
}
