<?php

declare(strict_types=1);

use App\Core\App;
use App\Repositories\QuestionRepository;

class DuplicateQuestionSummaryService
{
    public static function summary(): array
    {

        $duplicates = [];

        $ids = [];

        $questions =
            App::container()
                ->get(
                    QuestionRepository::class
                )
                ->all();

        foreach (
            $questions as $question
        ) {

            $id =
                $question["id"] ?? "";

            if (
                $id === ""
            ) {

                continue;

            }

            if (
                isset(
                    $ids[$id]
                )
            ) {

                $duplicates[] =
                    $id;

            }

            $ids[$id] = true;

        }

        return $duplicates;

    }
}
