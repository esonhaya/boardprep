<?php

declare(strict_types=1);

use App\Controllers\BoardController;
use App\Controllers\BlueprintController;
use App\Controllers\BlueprintHealthController;
use App\Controllers\CoverageController;
use App\Controllers\DashboardController;
use App\Controllers\DeveloperToolsController;
use App\Controllers\HomeController;
use App\Controllers\LearningProfileController;
use App\Controllers\MetadataRepairController;
use App\Controllers\ProgressController;
use App\Controllers\QuestionEditorController;
use App\Controllers\QuestionExportController;
use App\Controllers\QuestionImportController;
use App\Controllers\QuestionInspectorController;
use App\Controllers\QuestionQualityController;
use App\Controllers\QuizFlowController;
use App\Controllers\SubjectController;
use App\Controllers\TaxonomyController;

$router->get(
    "/",
    [HomeController::class, "index"]
);

$router->get(
    "/grammar",
    function () {
        \App\Core\View::render(
            "grammar/index",
            [
                "pageTitle" => "Grammar"
            ]
        );
    }
);

$router->get(
    "/dashboard",
    [DashboardController::class, "index"]
);

$router->get(
    "/quiz",
    [QuizFlowController::class, "handle"]
);

$router->post(
    "/quiz",
    [QuizFlowController::class, "handle"]
);

$router->get(
    "/profile",
    [LearningProfileController::class, "index"]
);

$router->get(
    "/progress",
    [ProgressController::class, "index"]
);

$router->get(
    "/developer",
    [DeveloperToolsController::class, "index"]
);

$router->get(
    "/boards",
    [BoardController::class, "index"]
);

$router->get(
    "/board/create",
    [BoardController::class, "create"]
);

$router->post(
    "/board/save",
    [BoardController::class, "save"]
);

$router->get(
    "/board/archive",
    [BoardController::class, "archive"]
);

$router->get(
    "/board/activate",
    [BoardController::class, "activate"]
);

$router->get(
    "/subjects",
    [SubjectController::class, "index"]
);

$router->get(
    "/subject/create",
    [SubjectController::class, "create"]
);

$router->post(
    "/subject/save",
    [SubjectController::class, "save"]
);

$router->get(
    "/subject/edit",
    [SubjectController::class, "edit"]
);

$router->post(
    "/subject/update",
    [SubjectController::class, "update"]
);

$router->get(
    "/subject/view",
    [SubjectController::class, "show"]
);

$router->get(
    "/subject/archive",
    [SubjectController::class, "archive"]
);

$router->get(
    "/subject/activate",
    [SubjectController::class, "activate"]
);

$router->get(
    "/question-editor",
    [QuestionEditorController::class, "index"]
);

$router->get(
    "/question-editor/create",
    [QuestionEditorController::class, "create"]
);

$router->get(
    "/question-editor/edit",
    [QuestionEditorController::class, "edit"]
);

$router->post(
    "/question-editor/save",
    [QuestionEditorController::class, "save"]
);

$router->post(
    "/question-editor/update",
    [QuestionEditorController::class, "update"]
);

$router->get(
    "/question-editor/archive",
    [QuestionEditorController::class, "archive"]
);

$router->get(
    "/question-editor/restore",
    [QuestionEditorController::class, "restore"]
);

$router->get(
    "/question-export",
    [QuestionExportController::class, "export"]
);

$router->get(
    "/question-import",
    [QuestionImportController::class, "index"]
);

$router->post(
    "/question-import/import",
    [QuestionImportController::class, "import"]
);

$router->get(
    "/question-quality",
    [QuestionQualityController::class, "index"]
);

$router->get(
    "/question-inspector",
    [QuestionInspectorController::class, "index"]
);

$router->get(
    "/coverage",
    [CoverageController::class, "index"]
);

$router->get(
    "/taxonomy",
    [TaxonomyController::class, "index"]
);

$router->post(
    "/taxonomy/rebuild",
    [TaxonomyController::class, "rebuild"]
);

$router->get(
    "/metadata-repair",
    [MetadataRepairController::class, "index"]
);

$router->get(
    "/blueprints",
    [BlueprintController::class, "index"]
);

$router->get(
    "/blueprints/create",
    [BlueprintController::class, "create"]
);

$router->post(
    "/blueprints/save",
    [BlueprintController::class, "save"]
);

$router->get(
    "/blueprint-health",
    [BlueprintHealthController::class, "index"]
);
