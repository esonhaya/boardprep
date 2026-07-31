<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\BoardRepository;
use App\Repositories\SubjectRepository;
use App\Services\BlueprintService;

class BlueprintController extends BaseDeveloperController
{
    public static function index(): void
    {
        self::renderDeveloper(
            "developer/blueprints",
            [
                "blueprints" => BlueprintService::all()
            ]
        );
    }

    public static function create(): void
    {
        self::renderDeveloper(
            "developer/blueprint-create",
            [
                "boards" => BoardRepository::all(),
                "subjects" => SubjectRepository::all()
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
                    "boards" => BoardRepository::all(),
                    "subjects" => SubjectRepository::all(),
                    "errors" => $result["errors"],
                    "old" => $_POST
                ]
            );

            return;
        }

        header("Location: ?page=blueprints");
        exit;
    }
}
