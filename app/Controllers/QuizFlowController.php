<?php

require_once __DIR__ . "/QuizController.php";
require_once __DIR__ . "/../Repositories/AttemptRepository.php";


class QuizFlowController
{


public static function startQuiz($count, $difficulty, $mode)
{

$_SESSION["questions"] =
QuizController::getQuestions(
    $count,
    $difficulty
);


$_SESSION["mode"] = $mode;

$_SESSION["current"] = 0;

$_SESSION["answers"] = [];

$_SESSION["feedback"] = null;

}



public static function submitAnswer($answer)
{


$current = $_SESSION["current"];

$question =
$_SESSION["questions"][$current];


$_SESSION["answers"][$question["id"]]
=
$answer;



if($_SESSION["mode"] === "practice")
{


$_SESSION["feedback"] = [

"correct" =>
QuizController::checkAnswer(
    $question,
    $answer
)

];


return "feedback";


}



return self::nextQuestion();


}



public static function nextQuestion()
{


unset($_SESSION["feedback"]);


$_SESSION["current"]++;


if(
$_SESSION["current"]
<
count($_SESSION["questions"])
)
{

return "question";

}


return "finished";

}



public static function finishQuiz()
{


$questions = $_SESSION["questions"];

$answers = $_SESSION["answers"];


$score =
QuizController::calculateResult(
    $questions,
    $answers
);



$results = [];



foreach($questions as $question)
{


$userAnswer =
$answers[$question["id"]] ?? null;



$results[] = [

"question" =>
$question,


"userAnswer" =>
$userAnswer,


"correctAnswer" =>
$question["answer"],


"correct" =>
QuizController::checkAnswer(
    $question,
    $userAnswer
),


"explanation" =>
$question["explanation"] ?? "No explanation available."

];


}



AttemptRepository::save([

"date" => date("Y-m-d H:i:s"),

"mode" => $_SESSION["mode"],

"subject" => "English",

"topic" => "Grammar",

"score" => $score,

"total" => count($questions),

"percentage" =>
round(
($score / count($questions)) * 100
)

]);



return [

"score" => $score,

"total" => count($questions),

"mode" => $_SESSION["mode"],

"results" => $results

];


}


}
