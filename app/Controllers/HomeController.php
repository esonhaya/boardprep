<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\App;
use App\Repositories\QuestionRepository;
use App\Services\Board\BoardViewService;

class HomeController extends BaseController
{
    public static function index(): void
    {
        View::render(
            "home/index",
            [
                "pageTitle" => "BoardPrep | Focused board-exam preparation",
                "boards" => BoardViewService::all(),
                "questionCount" => count(App::container()->get(QuestionRepository::class)->all()),
            ]
        );
    }

    public static function exams(): void
    {
        View::render("home/exams", ["pageTitle" => "Choose an examination", "boards" => BoardViewService::all()]);
    }
}
