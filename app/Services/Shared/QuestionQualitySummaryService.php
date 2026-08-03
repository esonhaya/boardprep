<?php

declare(strict_types=1);

use App\Core\App;
use App\Repositories\QuestionRepository;

class QuestionQualitySummaryService
{
    public static function summary(): array
    {

        $missingExplanation = 0;
        $missingOptions = 0;

        $questions =
            App::container()
                ->get(
                    QuestionRepository::class
                )
                ->all();

        foreach (
            $questions as $question
        ) {

            if (
                empty(
                    $question["explanation"]
                )
            ) {

                $missingExplanation++;

            }

            if (

                empty(
                    $question["options"]
                )

                ||

                count(
                    $question["options"]
                ) < 4

            ) {

                $missingOptions++;

            }

        }

        return [

            "missingExplanation" =>
                $missingExplanation,

            "missingOptions" =>
                $missingOptions

        ];

    }
}
