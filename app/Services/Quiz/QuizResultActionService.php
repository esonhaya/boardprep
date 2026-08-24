<?php

declare(strict_types=1);

namespace App\Services\Quiz;

final class QuizResultActionService
{
    public static function build(array $session = [], array $summary = []): array
    {
        $topic = trim((string) ($session["topics"][0] ?? ""));
        $subject = trim((string) ($session["subject"] ?? ""));
        $mode = trim((string) ($session["mode"] ?? "practice"));
        $difficulty = trim((string) ($session["difficulty"] ?? "mixed"));
        $count = max(1, (int) ($session["question_count"] ?? 10));

        $params = [
            "action" => "start",
            "subject" => $subject,
            "mode" => $mode !== "" ? $mode : "practice",
            "count" => $count,
            "difficulty" => $difficulty !== "" ? $difficulty : "mixed",
        ];

        if ($topic !== "") {
            $params["topic"] = $topic;
        }

        $retake = "/quiz?" . http_build_query($params);

        $percentage = (float) ($summary["percentage"] ?? 0);

        if ($percentage < 60) {
            $primaryLabel = "Practice this again";
            $primaryReason = "Your score shows this area needs more practice.";
        } elseif ($percentage < 80) {
            $primaryLabel = "Practice again";
            $primaryReason = "A short repeat session can help reinforce this material.";
        } else {
            $primaryLabel = "Keep practicing";
            $primaryReason = "Reviewing this topic will help keep the skill strong.";
        }

        return [
            [
                "label" => $primaryLabel,
                "reason" => $primaryReason,
                "url" => $retake,
            ],
            [
                "label" => "Back to Study Dashboard",
                "reason" => "Use your study plan and recommendations for the next step.",
                "url" => "/study",
            ],
            [
                "label" => "View Progress",
                "reason" => "See how this result changed your learning history.",
                "url" => "/progress",
            ],
        ];
    }
}
