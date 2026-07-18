<?php

require_once "../app/Controllers/QuizController.php";

session_start();


$page=$_GET["page"] ?? "home";


switch($page)
{


case "quiz":

$pageTitle="Quiz";


if(isset($_GET["count"]))
{

$_SESSION["questions"] =
QuizController::getQuestions(
$_GET["count"],
$_GET["difficulty"]
);


$_SESSION["mode"]=$_GET["mode"];

$_SESSION["current"]=0;

$_SESSION["answers"]=[];


}


else if($_SERVER["REQUEST_METHOD"]==="POST")
{


$current=$_SESSION["current"];

$question=$_SESSION["questions"][$current];


$_SESSION["answers"][$question["id"]]
=$_POST["answer"] ?? null;



if($current + 1 < count($_SESSION["questions"]))
{

$_SESSION["current"]++;

}

else
{

$score =
QuizController::calculateResult(
$_SESSION["questions"],
$_SESSION["answers"]
);


$result=[
"score"=>$score,
"total"=>count($_SESSION["questions"])
];


ob_start();

include "../app/Views/quiz/result.php";

$content=ob_get_clean();


include "../app/Views/layouts/main.php";

exit;

}


}



ob_start();

include "../app/Views/quiz/index.php";

$content=ob_get_clean();


break;



case "grammar":

$pageTitle="Grammar";

ob_start();

include "../app/Views/grammar/index.php";

$content=ob_get_clean();

break;



default:

$pageTitle="BoardPrep";

ob_start();

include "../app/Views/home/index.php";

$content=ob_get_clean();

}



include "../app/Views/layouts/main.php";
