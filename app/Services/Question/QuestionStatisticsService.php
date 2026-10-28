<?php

declare(strict_types=1);

namespace App\Services\Question;

use App\Core\App;
use App\Repositories\QuestionRepository;
use App\Repositories\QuestionStatisticsRepository;
use App\Services\Accounts\AccountLifecycleLock;

class QuestionStatisticsService
{
    public static function recordAnswer(
        string $questionId,
        ?bool $correct
    ): void {
        $questionId = trim($questionId);

        if ($questionId === "") {
            return;
        }

        self::recordAnswers([[
            'question_id' => $questionId,
            'correct' => $correct,
        ]]);
    }

    public static function recordAnswers(array $entries): void
    {
        $statistics = App::container()->get(QuestionStatisticsRepository::class);
        $questions = App::container()->get(QuestionRepository::class)->all();

        // The repository reads then replaces the aggregate collection. Hold a
        // process-wide learner-data lock across that full cycle so two exam
        // finalizations cannot overwrite each other's increments.
        AccountLifecycleLock::withLock(static function () use ($statistics, $entries, $questions): void {
            $statistics->recordAnswers($entries, $questions);
        });
    }
}
