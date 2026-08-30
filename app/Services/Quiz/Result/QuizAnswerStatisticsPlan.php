<?php

declare(strict_types=1);

namespace App\Services\Quiz\Result;

final class QuizAnswerStatisticsPlan
{
    public static function build(array $questions, array $answers): array
    {
        $plan = [];

        foreach ($questions as $question) {
            if (!is_array($question)) {
                continue;
            }

            $id = self::questionId($question);
            if ($id === "") {
                continue;
            }

            if (!array_key_exists($id, $answers)) {
                $plan[] = ["question_id" => $id, "correct" => null];
                continue;
            }

            $answer = $answers[$id];
            $plan[] = [
                "question_id" => $id,
                "correct" => is_scalar($answer)
                    ? \QuizScoringService::checkAnswer($question, (string) $answer)
                    : false,
            ];
        }

        return $plan;
    }

    private static function questionId(array $question): string
    {
        $value = $question["id"] ?? null;

        return is_scalar($value) ? trim((string) $value) : "";
    }
}
