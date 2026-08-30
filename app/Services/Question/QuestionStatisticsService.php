<?php

declare(strict_types=1);

namespace App\Services\Question;

use App\Core\App;
use App\Repositories\QuestionRepository;
use App\Services\Question\Statistics\QuestionStatisticsUpdater;

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

        $repository = App::container()->get(QuestionRepository::class);
        $question = $repository->find($questionId);

        if (!is_array($question)) {
            return;
        }

        $repository->update(
            $questionId,
            QuestionStatisticsUpdater::apply($question, $correct)
        );
    }
}
