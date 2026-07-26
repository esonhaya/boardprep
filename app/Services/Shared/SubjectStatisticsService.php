<?php

class SubjectStatisticsService
{

    public static function summary(): array
    {

        $questions =
            QuestionRepository::all();

        $subjects = [];

        foreach ($questions as $question) {

            $subject =
                trim(
                    $question["subject"] ?? ""
                );

            if ($subject === "") {

                $subject =
                    "Unknown";

            }

            $subjects[$subject] =
                ($subjects[$subject] ?? 0) + 1;

        }

        ksort($subjects);

        return $subjects;

    }

}
