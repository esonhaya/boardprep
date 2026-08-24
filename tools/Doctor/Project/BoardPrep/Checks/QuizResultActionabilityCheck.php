<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

use App\Services\Quiz\QuizResultActionService;

final class QuizResultActionabilityCheck
{
    public static function run(): array
    {
        $actions = QuizResultActionService::build(
            [
                "topics" => ["Grammar"],
                "subject" => "English",
                "mode" => "practice",
                "difficulty" => "mixed",
                "question_count" => 5,
            ],
            [
                "score" => 2,
                "total" => 5,
                "percentage" => 40,
            ]
        );

        return [
            "bounded_actions" => count($actions) === 3,
            "labels_present" =>
                count(array_filter(
                    $actions,
                    static fn(array $a): bool => !empty($a["label"])
                )) === 3,
            "reasons_present" =>
                count(array_filter(
                    $actions,
                    static fn(array $a): bool => !empty($a["reason"])
                )) === 3,
            "urls_present" =>
                count(array_filter(
                    $actions,
                    static fn(array $a): bool => !empty($a["url"])
                )) === 3,
            "retake_targets_quiz" =>
                str_starts_with((string) ($actions[0]["url"] ?? ""), "/quiz?"),
            "study_target" => ($actions[1]["url"] ?? "") === "/study",
            "progress_target" => ($actions[2]["url"] ?? "") === "/progress",
        ];
    }
}
