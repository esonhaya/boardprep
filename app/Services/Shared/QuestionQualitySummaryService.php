<?php

class QuestionQualitySummaryService
{

    public static function summary(): array
    {

        $missingExplanation = 0;
        $missingChoices = 0;

        foreach (QuestionRepository::all() as $question) {

            if (empty($question["explanation"])) {
                $missingExplanation++;
            }

            if (
                empty($question["choices"])
                ||
                count($question["choices"]) < 4
            ) {
                $missingChoices++;
            }

        }

        return [

            "missingExplanation" =>
                $missingExplanation,

            "missingChoices" =>
                $missingChoices

        ];

    }

}
