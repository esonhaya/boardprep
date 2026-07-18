<?php

require_once "../app/Controllers/QuizController.php";

session_start();

$page = $_GET["page"] ?? "home";


switch($page)
{


case "let":

$pageTitle = "LET";

ob_start();

include "../app/Views/let/index.php";

$content = ob_get_clean();

break;



case "english":

$pageTitle = "English Major";

ob_start();

include "../app/Views/english/index.php";

$content = ob_get_clean();

break;



case "grammar":

$pageTitle = "Grammar";

ob_start();

include "../app/Views/grammar/index.php";

$content = ob_get_clean();

break;



case "quiz":

$pageTitle = "Quiz";



// Open quiz settings

if(isset($_GET["setup"]))
{


ob_start();

include "../app/Views/quiz/settings.php";

$content = ob_get_clean();


}




// Generate quiz

else if(isset($_GET["count"]))
{


$_SESSION["questions"] =
QuizController::getQuestions(
    $_GET["count"],
    $_GET["difficulty"]
);



$_SESSION["mode"] =
$_GET["mode"] ?? "practice";


$_SESSION["current"] = 0;

$_SESSION["answers"] = [];

$_SESSION["feedback"] = null;



ob_start();

include "../app/Views/quiz/index.php";

$content = ob_get_clean();


}




// Quiz submission

else if($_SERVER["REQUEST_METHOD"] === "POST")
{


$current = $_SESSION["current"];


$question =
$_SESSION["questions"][$current];




// Moving to next question after feedback

if(isset($_POST["next"]))
{


unset($_SESSION["feedback"]);



if($current + 1 < count($_SESSION["questions"]))
{

$_SESSION["current"]++;


ob_start();

include "../app/Views/quiz/index.php";

$content = ob_get_clean();


}

else
{


$score =
QuizController::calculateResult(
    $_SESSION["questions"],
    $_SESSION["answers"]
);



$result = [

"score" => $score,

"total" => count($_SESSION["questions"]),

"results" => []

];



foreach($_SESSION["questions"] as $q)
{


$result["results"][] = [

"question" => $q,

"userAnswer" =>
$_SESSION["answers"][$q["id"]] ?? null,


"correctAnswer" =>
$q["answer"],


"correct" =>
QuizController::checkAnswer(
    $q,
    $_SESSION["answers"][$q["id"]] ?? null
)

];


}




ob_start();

include "../app/Views/quiz/result.php";

$content = ob_get_clean();


}



}




// Submit answer

else
{


$answer = $_POST["answer"] ?? null;



$_SESSION["answers"][$question["id"]]
=
$answer;



// Practice mode feedback

if($_SESSION["mode"] === "practice")
{


$_SESSION["feedback"] = [

"correct" =>
QuizController::checkAnswer(
    $question,
    $answer
)

];


}


// Exam mode skips feedback

else
{


if($current + 1 < count($_SESSION["questions"]))
{

$_SESSION["current"]++;


}

}



ob_start();

include "../app/Views/quiz/index.php";

$content = ob_get_clean();


}



}


break;



default:

$pageTitle = "BoardPrep";

ob_start();

include "../app/Views/home/index.php";

$content = ob_get_clean();


}



include "../app/Views/layouts/main.php";
