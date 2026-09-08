<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\App;
use App\Core\Request;
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
        $boards = BoardViewService::all();
        $examCode = Request::queryString('exam');
        if ($examCode !== '') {
            foreach ($boards as $board) {
                if (strcasecmp((string) ($board['code'] ?? ''), $examCode) !== 0) {
                    continue;
                }

                View::render('home/exam', [
                    'pageTitle' => (string) ($board['name'] ?? 'Examination'),
                    'board' => $board,
                ]);
                return;
            }
        }

        View::render("home/exams", ["pageTitle" => "Choose an examination", "boards" => $boards]);
    }
}
