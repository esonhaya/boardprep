<?php

session_start();

require_once "../app/Core/Autoloader.php";

Autoloader::register();


$page =
    $_GET["page"] ?? "home";


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



    case "taxonomy":

        $action =
            $_GET["action"] ?? "index";


        switch ($action) {


            case "add-domain":

                TaxonomyController::addDomain();

                break;


            case "add-topic":

                TaxonomyController::addTopic();

                break;


            case "add-concept":

                TaxonomyController::addConcept();

                break;


            default:

                TaxonomyController::index();

                break;

        }

        break;



    case "question-editor":

        $action =
            $_GET["action"] ?? "index";


        switch ($action) {


            case "create":

                QuestionEditorController::create();

                break;


            case "edit":

                QuestionEditorController::edit();

                break;


            case "save":

                QuestionEditorController::save();

                break;


            case "update":

                QuestionEditorController::update();

                break;


            case "archive":

                QuestionEditorController::archive();

                break;


            case "restore":

                QuestionEditorController::restore();

                break;


            default:

                QuestionEditorController::index();

                break;

        }

        break;



    case "question-export":

        QuestionExportController::export();

        break;



    case "question-import":

        if (
            ($_GET["action"] ?? "")
            ===
            "import"
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
