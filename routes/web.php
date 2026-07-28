<?php

$router->get("/", function () {
    View::render(
        "home/index",
        [
            "pageTitle" => "BoardPrep"
        ]
    );
});

$router->get("/let", [LetController::class, "index"]);

$router->get("/english", function () {
    View::render(
        "english/index",
        [
            "pageTitle" => "English Major"
        ]
    );
});

$router->get("/grammar", function () {
    View::render(
        "grammar/index",
        [
            "pageTitle" => "Grammar"
        ]
    );
});

$router->get("/dashboard", [DashboardController::class, "index"]);

$router->get("/quiz", [QuizFlowController::class, "handle"]);

$router->get("/profile", [LearningProfileController::class, "index"]);

$router->get("/progress", [ProgressController::class, "index"]);
