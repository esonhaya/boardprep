<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;
use App\Services\RepositoryHealth\DTO\HealthReport;

final class DoctorDashboardController
{
    public static function index(): void
    {
        if (App::config('environment') === 'production') {
            http_response_code(404);
            return;
        }

        $report = [];
        $repositoryHealth = [];
        $healthFilters = [
            'severity' => Request::queryString('severity'),
            'board' => Request::queryString('board'),
            'category' => Request::queryString('category'),
            'entity_type' => Request::queryString('entity_type'),
        ];

        $file = dirname(__DIR__, 2) . '/storage/doctor-report.json';

        if (is_file($file)) {

            $contents = file_get_contents($file);
            $decoded = $contents === false
                ? null
                : json_decode($contents, true);

            if (is_array($decoded)) {
                $report = $decoded;
            }

        }

        $healthFile = dirname(__DIR__, 2) . '/storage/repository-health.json';
        if (is_file($healthFile)) {
            $contents = file_get_contents($healthFile);
            $decoded = $contents === false ? null : json_decode($contents, true);
            if (is_array($decoded)) {
                $repositoryHealth = $decoded;
            }
        }

        $healthIssues = HealthReport::filterActionQueue(
            is_array($repositoryHealth['action_queue'] ?? null) ? $repositoryHealth['action_queue'] : [],
            $healthFilters
        );

        require dirname(__DIR__) . '/Views/developer/doctor/index.php';
    }
}
