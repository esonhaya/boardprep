<?php


require_once __DIR__ . "/../Repositories/QuestionRepository.php";



class QuizController
{


public static function getQuestions(
$count = 10,
$difficulty = "mixed"
)
{


$questions =
QuestionRepository::getQuestions(
"let",
"english",
"grammar"
);



if($difficulty !== "mixed")
{


$filtered = [];


foreach($questions as $question)
{


if(
strtolower($question["difficulty"])
===
strtolower($difficulty)
)
{

$filtered[] = $question;

}


}


$questions = $filtered;


}



$conceptGroups = [];


foreach($questions as $question)
{


$concept =
$question["concept"];


$conceptGroups[$concept][] =
$question;


}



$selected = [];


foreach($conceptGroups as $group)
{


shuffle($group);


$selected[] =
$group[0];


}



shuffle($selected);



return array_slice(
$selected,
0,
$count
);


}



public static function checkAnswer(
$question,
$answer
)
{


return strtoupper(trim($answer))
===
strtoupper(trim($question["answer"]));


}



public static function calculateResult(
$questions,
$answers
)
{


$score = 0;


foreach($questions as $question)
{


if(
self::checkAnswer(
$question,
$answers[$question["id"]] ?? null
)
)
{

$score++;

}


}


return $score;


}



}
