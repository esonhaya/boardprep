<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Blueprint\BlueprintService;
use App\Services\Board\BoardViewService;
use App\Services\Subject\SubjectViewService;

class BlueprintController extends BaseDeveloperController
{
    public static function index(): void
    {
        self::renderDeveloper(
            "developer/blueprints",
            [
                "blueprints" => BlueprintService::all(),
            ]
        );
    }

    public static function create(): void
    {
        self::renderDeveloper(
            "developer/blueprint-create",
            [
                "boards" => BoardViewService::all(),
                "subjects" => SubjectViewService::all(),
            ]
        );
    }

    public static function save(): void
    {
        $result = BlueprintService::create($_POST);

        if (!$result["success"]) {

            self::renderDeveloper(
                "developer/blueprint-create",
                [
                    "boards" => BoardViewService::all(),
                    "subjects" => SubjectViewService::all(),
                    "errors" => $result["errors"],
                    "old" => $_POST,
                ]
            );

            return;
        }

        header("Location: /blueprints");
        exit;
    }
}
