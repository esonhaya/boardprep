<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
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
        BoardService::create(
            Request::input()
        );

        self::developerRedirect(
            "boards"
        );
    }

    public static function archive(): void
    {
        BoardService::archive(
            Request::queryString("id")
        );

        self::developerRedirect(
            "boards"
        );
    }

    public static function activate(): void
    {
        BoardService::activate(
            Request::queryString("id")
        );

        self::developerRedirect(
            "boards"
        );
    }

    public static function show(): void
    {
        $board =
            BoardViewService::find(
                Request::queryString("id")
            );

        if ($board === null) {
            self::developerRedirect(
                "boards"
            );
        }

        self::renderDeveloper(
            "developer/boards/view",
            [
                "board" => $board,
            ]
        );
    }
}
