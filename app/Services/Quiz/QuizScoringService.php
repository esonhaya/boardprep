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


        foreach (
            $questions
            as $question
        ) {

            $userAnswer =
                self::normalizeAnswer(
                    $question,
                    $answers[$question["id"]] ?? null
                );


            $correctAnswer =
                $question["answer"] ?? "";


            $correct =
                strtoupper(
                    trim($userAnswer)
                )
                ===
                strtoupper(
                    trim($correctAnswer)
                );


            if ($correct) {

                $score++;

            }


            $results[] = [

                "question" =>
                    $question["question"],

                "choices" =>
                    $question["choices"] ?? [],

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



        $total =
            count($questions);


        return [

            "score" =>
                $score,

            "total" =>
                $total,

            "percentage" =>
                $total > 0
                ?
                round(
                    ($score / $total) * 100
                )
                :
                0,

            "results" =>
                $results

        ];

    }



    public static function checkAnswer(
        array $question,
        ?string $answer
    ): bool
    {

        $userAnswer =
            self::normalizeAnswer(
                $question,
                $answer
            );


        return
            strtoupper(
                trim($userAnswer)
            )
            ===
            strtoupper(
                trim(
                    $question["answer"] ?? ""
                )
            );

    }



    private static function normalizeAnswer(
        array $question,
        ?string $answer
    ): string
    {

        $answer =
            trim(
                $answer ?? ""
            );


        if (
            preg_match(
                '/^[A-D]$/i',
                $answer
            )
        ) {

            $index =
                ord(
                    strtoupper($answer)
                )
                - 65;


            return
                $question["choices"][$index]
                ??
                "";

        }


        return $answer;

    }

}
