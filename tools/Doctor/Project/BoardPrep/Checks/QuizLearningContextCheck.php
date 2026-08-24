<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

use App\Services\Quiz\QuizLearningContextService;

final class QuizLearningContextCheck
{
    public static function run(): bool
    {
        $contract = ServiceContractCheck::run(
            QuizLearningContextService::class,
            ["enrichAttempt", "topics"]
        );

        foreach ($contract as $label => $passed) {
            if (!$passed) {
                fwrite(STDERR, "[FAIL] {$label}\n");
                return false;
            }
        }

        $attempt = QuizLearningContextService::enrichAttempt(
            [],
            ["topics" => ["Grammar"], "subject" => "English"],
            []
        );

        $checks = [
            "topic" => ($attempt["topic"] ?? "") === "Grammar",
            "topics" => ($attempt["topics"] ?? []) === ["Grammar"],
            "learning_context" => isset($attempt["learning_context"]),
            "subject" =>
                ($attempt["learning_context"]["subject"] ?? "") === "English",
            "fallback" =>
                (QuizLearningContextService::enrichAttempt([], [], [])["topic"] ?? "")
                === "General",
        ];

        foreach ($checks as $label => $passed) {
            if (!$passed) {
                fwrite(STDERR, "[FAIL] {$label}\n");
                return false;
            }
            echo "[PASS] {$label}\n";
        }

        echo "[PASS] Quiz learning context Doctor contract verified. Assertions: "
            . (count($contract) + count($checks)) . "\n";

        return true;
    }
}
