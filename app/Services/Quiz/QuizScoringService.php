<?php

class QuizScoringService
{

    public static function calculate(
        array $questions,
        array $answers
    ): array
    {

        $score = 0;
        $results = [];


        foreach ($questions as $question) {


            $userAnswer =
                $answers[$question["id"]] ?? null;


            $correctAnswer =
                $question["answer"];


            // Convert A/B/C/D into actual choice text
            if (
                is_string($userAnswer)
                &&
                strlen($userAnswer) === 1
                &&
                ctype_alpha($userAnswer)
            ) {

                $index =
                    ord(strtoupper($userAnswer)) - 65;


                if (
                    isset($question["choices"][$index])
                ) {

                    $userAnswer =
                        $question["choices"][$index];

                }

            }



            $correct =
                $userAnswer === $correctAnswer;



            if ($correct) {
                $score++;
            }



            $results[] = [

                "question" =>
                    $question["question"],

                "choices" =>
                    $question["choices"],

                "userAnswer" =>
                    $userAnswer,

                "correctAnswer" =>
                    $correctAnswer,

                "correct" =>
                    $correct,

                "explanation" =>
                    $question["explanation"] ?? ""

            ];

        }



        return [

            "score" =>
                $score,

            "total" =>
                count($questions),

            "percentage" =>
                count($questions)
                    ? round(
                        ($score / count($questions)) * 100
                    )
                    : 0,

            "results" =>
                $results

        ];

    }

}
