<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Services\Developer\ReadinessDashboardService;

final class DeveloperReadinessController extends BaseDeveloperController
{
    public static function index(): void
    {
        if (App::config('environment') === 'production') {
            http_response_code(404);
            return;
        }
        $root = dirname(__DIR__, 2);
        $readiness = (new ReadinessDashboardService())->build($root);
        self::renderDeveloper('developer/readiness', [
            'pageTitle' => 'Operational Readiness | BoardPrep',
            'readiness' => $readiness,
            'pageDescription' => 'Technical evidence, browser status, and academic review remain separate gates.',
        ]);
    }
}
