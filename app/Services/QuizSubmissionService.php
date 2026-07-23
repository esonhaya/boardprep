<?php

class QuizSubmissionService
{

    public static function submit(
        array $questions,
        array $answers
    ): array
    {

        $score = 0;

        foreach ($questions as $question) {

            $correct =
                QuizScoringService::checkAnswer(
                    $question,
                    $answers[$question["id"]] ?? null
                );

            if ($correct) {

                $score++;

            }

            
        }

        return [

            "score" => $score,

            "total" => count($questions)

        ];

    }

}
