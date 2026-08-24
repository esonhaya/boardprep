<?php

declare(strict_types=1);

namespace App\Services\Quiz;

final class QuizLearningContextService
{
    public static function enrichAttempt(
        array $attempt,
        array $session,
        array $questions = []
    ): array {
        $topics = self::topics($session, $questions);

        if (!isset($attempt["topic"]) || trim((string) $attempt["topic"]) === "") {
            $attempt["topic"] = $topics[0] ?? "General";
        }

        $attempt["topics"] = $topics;

        if (
            (!isset($attempt["domain"]) || trim((string) $attempt["domain"]) === "")
            && isset($session["domain"])
            && is_string($session["domain"])
        ) {
            $attempt["domain"] = trim($session["domain"]);
        }

        $attempt["learning_context"] = [
            "topic" => $attempt["topic"],
            "topics" => $topics,
            "subject" => $attempt["subject"] ?? ($session["subject"] ?? ""),
            "mode" => $attempt["mode"] ?? ($session["mode"] ?? "practice"),
            "difficulty" =>
                $attempt["difficulty"] ?? ($session["difficulty"] ?? "mixed"),
        ];

        return $attempt;
    }

    public static function topics(
        array $session,
        array $questions = []
    ): array {
        $topics = [];

        if (isset($session["topics"]) && is_array($session["topics"])) {
            foreach ($session["topics"] as $topic) {
                $topic = trim((string) $topic);
                if ($topic !== "" && !in_array($topic, $topics, true)) {
                    $topics[] = $topic;
                }
            }
        }

        if (empty($topics) && isset($session["topic"])) {
            $topic = trim((string) $session["topic"]);
            if ($topic !== "") {
                $topics[] = $topic;
            }
        }

        if (empty($topics)) {
            foreach ($questions as $question) {
                $topic = trim((string) ($question["topic"] ?? ""));
                if ($topic !== "" && !in_array($topic, $topics, true)) {
                    $topics[] = $topic;
                }
            }
        }

        return array_values($topics);
    }
}
