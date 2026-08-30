<?php

declare(strict_types=1);

namespace App\Services\Quiz\Result;

use App\Services\Learning\WeaknessService;
use App\Services\Question\QuestionStatisticsService;

final class QuizAnswerStatisticsRecorder
{
    public static function record(array $questions, array $answers): void
    {
        $plan = QuizAnswerStatisticsPlan::build($questions, $answers);

        QuestionStatisticsService::recordAnswers($plan);

        WeaknessService::analyze(self::weaknessAnswers($questions, $plan));
    }

    private static function weaknessAnswers(array $questions, array $plan): array
    {
        $topics = [];
        foreach ($questions as $question) {
            if (!is_array($question) || !is_scalar($question["id"] ?? null)) {
                continue;
            }
            $topics[trim((string) $question["id"])] = $question["topic"] ?? "General";
        }

        return array_map(
            static fn(array $entry): array => [
                "topic" => $topics[$entry["question_id"]] ?? "General",
                "correct" => $entry["correct"] === true,
            ],
            array_values(array_filter(
                $plan,
                static fn(array $entry): bool => is_bool($entry["correct"] ?? null)
            ))
        );
    }
}
