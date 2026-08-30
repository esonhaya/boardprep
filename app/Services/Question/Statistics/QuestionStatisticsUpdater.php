<?php

declare(strict_types=1);

namespace App\Services\Question\Statistics;

final class QuestionStatisticsUpdater
{
    public static function apply(
        array $question,
        ?bool $correct,
        ?string $updatedAt = null
    ): array {
        $question["timesUsed"] =
            QuestionStatisticsCounter::read($question["timesUsed"] ?? null) + 1;

        $question["timesCorrect"] =
            QuestionStatisticsCounter::read($question["timesCorrect"] ?? null);

        $question["timesIncorrect"] =
            QuestionStatisticsCounter::read($question["timesIncorrect"] ?? null);

        if ($correct === true) {
            $question["timesCorrect"]++;
        } elseif ($correct === false) {
            $question["timesIncorrect"]++;
        }

        $question["updatedAt"] = $updatedAt ?? date("c");

        return $question;
    }
}
