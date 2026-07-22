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



    case "profile":

        LearningProfileController::index();

        break;



    case "progress":

        ProgressController::index();

        break;



    case "developer":

        DeveloperToolsController::index();

        break;

 case "question-editor":

    $action =
        $_GET["action"] ?? "index";

    if ($action === "create") {

        QuestionEditorController::create();

    }
    elseif ($action === "edit") {

    QuestionEditorController::edit();

    }
    elseif ($action === "save") {

        QuestionEditorController::save();

    }

elseif ($action === "update") {

    QuestionEditorController::update();

}

elseif ($action === "archive") {

    QuestionEditorController::archive();

}

elseif ($action === "restore") {

    QuestionEditorController::restore();

}

    else {

        QuestionEditorController::index();

    }

    break;

case "question-export":

    QuestionExportController::export();

    break;

case "question-import":

    if (
        ($_GET["action"] ?? "") === "import"
    ) {

        QuestionImportController::import();

    }
    else {

        QuestionImportController::index();

    }

    break;

case "question-quality":

    QuestionQualityController::index();

    break;

case "question-inspector":

    QuestionInspectorController::index();

    break;






    default:

        View::render(
            "home/index",
            [
                "pageTitle" => "BoardPrep"
            ]
        );

        break;

}
