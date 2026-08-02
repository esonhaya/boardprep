<?php

declare(strict_types=1);

namespace App\Services\Question;

use App\Core\App;
use App\Repositories\QuestionRepository;

class QuestionStatisticsService
{
    public static function recordAnswer(
        string $questionId,
        bool $correct
    ): void {

        $repository =
            App::container()
                ->get(
                    QuestionRepository::class
                );

        $question =
            $repository->find(
                $questionId
            );

        if (
            $question === null
        ) {

            return;

        }

        $question["timesUsed"] =
            ($question["timesUsed"] ?? 0)
            + 1;

        if (
            $correct
        ) {

            $question["timesCorrect"] =
                ($question["timesCorrect"] ?? 0)
                + 1;

        } else {

            $question["timesIncorrect"] =
                ($question["timesIncorrect"] ?? 0)
                + 1;

        }

        $question["updatedAt"] =
            date("c");

        $repository->update(
            $questionId,
            $question
        );

    }
}
