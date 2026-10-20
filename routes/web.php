<?php

declare(strict_types=1);

use App\Controllers\BoardController;
use App\Controllers\AcceptanceCenterController;
use App\Controllers\AccountController;
use App\Controllers\BlueprintController;
use App\Controllers\BlueprintHealthController;
use App\Controllers\CoverageController;
use App\Controllers\DashboardController;
use App\Controllers\DataHandlingController;
use App\Controllers\FlashcardController;
use App\Controllers\DeveloperToolsController;
use App\Controllers\DoctorDashboardController;
use App\Controllers\DoctorRunController;
use App\Controllers\HomeController;
use App\Controllers\HistoryController;
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
use App\Controllers\StudyDashboardController;
use App\Controllers\StudyLibraryController;
use App\Controllers\TaxonomyController;
use App\Core\App;
use App\Services\Accounts\AccountFeatureGate;

$router->get(
    "/",
    [HomeController::class, "index"]
);
$router->get("/exams", [HomeController::class, "exams"]);
$router->get("/privacy", [DataHandlingController::class, "index"]);
$router->get("/privacy/export", [DataHandlingController::class, "export"]);
$router->post("/privacy/data/clear", [DataHandlingController::class, "clear"]);

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
    "/history",
    [HistoryController::class, "index"]
);

$router->get(
    "/history/result",
    [HistoryController::class, "result"]
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
    "/study",
    [StudyDashboardController::class, "index"]
);
$router->get("/study-library", [StudyLibraryController::class, "index"]);
$router->get("/study-library/practice", [StudyLibraryController::class, "practice"]);

$router->get(
    "/profile",
    [LearningProfileController::class, "index"]
);
$router->post('/profile/preferences', [LearningProfileController::class, 'savePreferences']);

$router->get(
    "/progress",
    [ProgressController::class, "index"]
);

$router->get('/flashcards', [FlashcardController::class, 'index']);
$router->get('/flashcards/missed', [FlashcardController::class, 'missed']);
$router->get('/flashcards/review', [FlashcardController::class, 'review']);
$router->post('/flashcards/review', [FlashcardController::class, 'submitReview']);

if (App::config('environment') !== 'production') {

if (AccountFeatureGate::enabled()) {
    $router->get('/account', [AccountController::class, 'index']);
    $router->get('/account/register', [AccountController::class, 'registrationForm']);
    $router->post('/account/register', [AccountController::class, 'register']);
    $router->get('/account/login', [AccountController::class, 'loginForm']);
    $router->post('/account/login', [AccountController::class, 'login']);
    $router->post('/account/logout', [AccountController::class, 'logout']);
    $router->get('/account/profile', [AccountController::class, 'profile']);
    $router->get('/account/security', [AccountController::class, 'security']);
    $router->post('/account/password', [AccountController::class, 'changePassword']);
    $router->post('/account/recovery-codes', [AccountController::class, 'rotateRecoveryCodes']);
    $router->get('/account/recover', [AccountController::class, 'recoveryForm']);
    $router->post('/account/recover', [AccountController::class, 'recover']);
    $router->post('/account/export', [AccountController::class, 'export']);
    $router->post('/account/deletion-request', [AccountController::class, 'requestDeletion']);
}

$router->get('/developer/acceptance', [AcceptanceCenterController::class, 'index']);
$router->get('/developer/acceptance/data', [AcceptanceCenterController::class, 'data']);
$router->get('/developer/acceptance/build', [AcceptanceCenterController::class, 'build']);
$router->get('/developer/acceptance/export', [AcceptanceCenterController::class, 'export']);
$router->post('/developer/acceptance/update', [AcceptanceCenterController::class, 'update']);

$router->get(
    "/developer",
    [DeveloperToolsController::class, "index"]
);
$router->get(
    "/developer/content-intelligence",
    [DeveloperToolsController::class, "contentIntelligence"]
);
$router->get("/developer/study-library", [StudyLibraryController::class, "developer"]);
$router->get('/developer/question-origin', [\App\Controllers\QuestionOriginDiagnosticController::class, 'index']);

$router->get(
    "/developer/doctor",
    [DoctorDashboardController::class, "index"]
);

$router->post(
    "/developer/doctor/run",
    [DoctorRunController::class, "run"]
);

$router->get(
    "/boards",
    [BoardController::class, "index"]
);

$router->get(
    "/board/create",
    [BoardController::class, "create"]
);

$router->get(
    "/board/view",
    [BoardController::class, "show"]
);

$router->post(
    "/board/save",
    [BoardController::class, "save"]
);

$router->post(
    "/board/archive",
    [BoardController::class, "archive"]
);

$router->post(
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

$router->post(
    "/subject/archive",
    [SubjectController::class, "archive"]
);

$router->post(
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

$router->post(
    "/question-editor/archive",
    [QuestionEditorController::class, "archive"]
);

$router->post(
    "/question-editor/restore",
    [QuestionEditorController::class, "restore"]
);

$router->post(
    "/question-editor/share",
    [QuestionEditorController::class, "share"]
);

$router->post(
    "/question-editor/placement",
    [QuestionEditorController::class, "placement"]
);

$router->post(
    "/question-editor/unshare",
    [QuestionEditorController::class, "unshare"]
);

$router->post(
    "/question-editor/duplicate",
    [QuestionEditorController::class, "duplicate"]
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
}
