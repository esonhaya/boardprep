<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Board\BoardService;
use App\Services\Board\BoardViewService;

class BoardController extends BaseDeveloperController
{
    public static function index(): void
    {
        self::renderDeveloper(
            "developer/boards/index",
            [
                "boards" => BoardViewService::all(),
            ]
        );
    }

    public static function create(): void
    {
        self::renderDeveloper(
            "developer/boards/create"
        );
    }

    public static function save(): void
    {
        BoardService::create($_POST);

        self::developerRedirect("boards");
    }

    public static function archive(): void
    {
        $id = $_GET["id"] ?? "";

        BoardService::archive($id);

        self::developerRedirect("boards");
    }

    public static function activate(): void
    {
        $id = $_GET["id"] ?? "";

        BoardService::activate($id);

        self::developerRedirect("boards");
    }

    public static function view(): void
    {
        $id = $_GET["id"] ?? "";

        $board = BoardViewService::find($id);

        if (!$board) {
            self::developerRedirect("boards");

            return;
        }

        self::renderDeveloper(
            "developer/boards/view",
            [
                "board" => $board,
            ]
        );
    }
}
