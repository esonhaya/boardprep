<?php

declare(strict_types=1);

use App\Core\App;
use App\Repositories\QuestionRepository;

class SubjectStatisticsService
{
    public static function summary(): array
    {

        $questions =
            App::container()
                ->get(
                    QuestionRepository::class
                )
                ->all();

        $subjects = [];

        foreach (
            $questions as $question
        ) {

            $subject =
                trim(

                    $question["taxonomy"]["subject_id"]

                    ?? ""

                );

            if (
                $subject === ""
            ) {

                $subject =
                    "Unknown";

            }

            $subjects[$subject] =
                ($subjects[$subject] ?? 0)
                + 1;

        }

        ksort(
            $subjects
        );

        return $subjects;

    }
}
