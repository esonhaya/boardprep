<?php

class QuizController
{


    public static function getQuestions($count = 10, $difficulty = "mixed")
    {

        $questions = require __DIR__ . "/../../database/modules/let/english/grammar/questions.php";


        if ($difficulty !== "mixed") {

            $filtered = [];

            foreach ($questions as $question) {

                if (strtolower($question["difficulty"]) === strtolower($difficulty)) {

                    $filtered[] = $question;

                }

            }

            $questions = $filtered;

        }



        $conceptGroups = [];


        foreach ($questions as $question) {

            $concept = $question["concept"];

            $conceptGroups[$concept][] = $question;

        }



        $selected = [];


        foreach ($conceptGroups as $group) {

            shuffle($group);

            $selected[] = $group[0];

        }


        shuffle($selected);


        return array_slice($selected,0,$count);

    }



    public static function checkAnswer($question,$answer)
    {

        return strtoupper(trim($answer))
            === strtoupper(trim($question["answer"]));

    }



    public static function calculateResult($questions,$answers)
    {

        $score = 0;


        foreach($questions as $question)
        {

            $answer =
            $answers[$question["id"]] ?? null;


            if(self::checkAnswer($question,$answer))
            {
                $score++;
            }

        }


        return $score;

    }


}
