<?php

declare(strict_types=1);

namespace App\Services\Quiz\Result;

use App\Services\Question\QuestionStatisticsService;

final class QuizAnswerStatisticsRecorder
{
    public static function record(array $questions, array $answers): void
    {
        foreach (QuizAnswerStatisticsPlan::build($questions, $answers) as $entry) {
            QuestionStatisticsService::recordAnswer(
                $entry["question_id"],
                $entry["correct"]
            );
        }
    }
}
