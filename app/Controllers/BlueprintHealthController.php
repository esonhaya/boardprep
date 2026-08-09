<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Shared\BlueprintValidator;
use App\Services\Blueprint\BlueprintService;

class BlueprintHealthController extends BaseDeveloperController
{
    public static function index(): void
    {
        $results = [];

        foreach (BlueprintService::all() as $blueprint) {
            $results[] = [
                "blueprint" => $blueprint,
                "validation" => BlueprintValidator::validate($blueprint),
            ];
        }

        self::renderDeveloper(
            "developer/blueprint-health",
            [
                "pageTitle" => "Blueprint Health",
                "results" => $results,
            ]
        );
    }
}
