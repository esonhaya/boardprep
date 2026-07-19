<?php

session_start();

require_once "../app/Core/Autoloader.php";

Autoloader::register();

$page = $_GET["page"] ?? "home";

switch ($page) {

    case "let":

        View::render(
            "let/index",
            [
                "pageTitle" => "LET"
            ]
        );

        break;

    case "english":

        View::render(
            "english/index",
            [
                "pageTitle" => "English Major"
            ]
        );

        break;

    case "grammar":

        View::render(
            "grammar/index",
            [
                "pageTitle" => "Grammar"
            ]
        );

        break;

    case "quiz":

        QuizFlowController::handle();

        break;
case "dashboard":

    DashboardController::index();

    break;
    default:

        View::render(
            "home/index",
            [
                "pageTitle" => "BoardPrep"
            ]
        );

        break;
case "profile":

   LearningProfileController::index();

    break;

}
