<?php

require_once "../app/Controllers/QuizFlowController.php";
require_once "../app/Controllers/ProgressController.php";

session_start();

$content = "";

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



case "progress":

$pageTitle = "My Progress";

$stats = ProgressController::getStats();

ob_start();
include "../app/Views/progress/index.php";
$content = ob_get_clean();

break;



case "quiz":

$pageTitle = "Quiz";

if(isset($_GET["setup"]))
{

    ob_start();
    include "../app/Views/quiz/settings.php";
    $content = ob_get_clean();

}

else if(isset($_GET["count"]))
{

    QuizFlowController::startQuiz(
        $_GET["count"],
        $_GET["difficulty"],
        $_GET["mode"] ?? "practice"
    );

    ob_start();
    include "../app/Views/quiz/index.php";
    $content = ob_get_clean();

}

else if($_SERVER["REQUEST_METHOD"] === "POST")
{

    if(isset($_POST["next"]))
    {
        $status = QuizFlowController::nextQuestion();
    }
    else
    {
        $status = QuizFlowController::submitAnswer(
            $_POST["answer"] ?? null
        );
    }

    if($status === "finished")
    {

        $result = QuizFlowController::finishQuiz();

        ob_start();
        include "../app/Views/quiz/result.php";
        $content = ob_get_clean();

    }
    else
    {

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

break;

}

include "../app/Views/layouts/main.php";
