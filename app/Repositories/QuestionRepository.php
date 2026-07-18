<?php


class QuestionRepository
{


    public static function getQuestions(
        $exam,
        $subject,
        $topic
    )
    {


        $path =
        __DIR__
        . "/../../database/questions/"
        . $exam
        . "/"
        . $subject
        . "/"
        . $topic
        . "/questions.php";



        if(file_exists($path))
        {

            return require $path;

        }


        return [];

    }


}
