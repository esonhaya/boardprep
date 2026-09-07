<?php

declare(strict_types=1);

namespace App\Services\Question;

use App\Core\App;
use App\Repositories\QuestionRepository;
use App\Repositories\QuestionStatisticsRepository;

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
        App::container()
            ->get(QuestionStatisticsRepository::class)
            ->recordAnswers(
                $entries,
                App::container()->get(QuestionRepository::class)->all()
            );
    }
}
