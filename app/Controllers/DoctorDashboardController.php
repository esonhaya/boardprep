<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;
use App\Services\RepositoryHealth\DTO\HealthReport;
use App\Services\RepositoryHealth\Engine\RepositoryHealthEvidenceService;

final class DoctorDashboardController
{
    public static function index(): void
    {
        if (App::config('environment') === 'production') {
            http_response_code(404);
            return;
        }

        $storagePath = self::storagePath();
        $report = [];
        $repositoryHealth = RepositoryHealthEvidenceService::currentReport();
        $doctorEvidence = RepositoryHealthEvidenceService::classifyPersistedSnapshot(
            $storagePath . '/doctor-report.json',
            dirname(__DIR__, 2)
        );
        $cachedHealthEvidence = RepositoryHealthEvidenceService::classifyPersistedSnapshot(
            $storagePath . '/repository-health.json',
            dirname(__DIR__, 2)
        );
        $releaseEvidence = RepositoryHealthEvidenceService::classifyPersistedSnapshot(
            $storagePath . '/release-readiness.json',
            dirname(__DIR__, 2)
        );
        $releaseReadiness = [];
        $healthFilters = [
            'severity' => Request::queryString('severity'),
            'board' => Request::queryString('board'),
            'category' => Request::queryString('category'),
            'entity_type' => Request::queryString('entity_type'),
        ];

        $file = $storagePath . '/doctor-report.json';
        if (($doctorEvidence['state'] ?? '') === RepositoryHealthEvidenceService::CACHED) {
            $report = self::readJson($file);
        }

        $repositoryHealth['cached_evidence'] = $cachedHealthEvidence;

        $releaseFile = $storagePath . '/release-readiness.json';
        if (($releaseEvidence['state'] ?? '') === RepositoryHealthEvidenceService::CACHED) {
            $releaseReadiness = self::readJson($releaseFile);
        }

        $healthIssues = HealthReport::filterActionQueue(
            is_array($repositoryHealth['action_queue'] ?? null) ? $repositoryHealth['action_queue'] : [],
            $healthFilters
        );

        require dirname(__DIR__) . '/Views/developer/doctor/index.php';
    }

    private static function storagePath(): string
    {
        $database = App::config('database', []);
        $path = is_array($database) ? ($database['path'] ?? null) : null;
        return is_string($path) && trim($path) !== ''
            ? rtrim($path, DIRECTORY_SEPARATOR)
            : dirname(__DIR__, 2) . '/storage';
    }

    /** @return array<string, mixed> */
    private static function readJson(string $path): array
    {
        $contents = is_file($path) ? file_get_contents($path) : false;
        $decoded = $contents === false ? null : json_decode($contents, true);
        return is_array($decoded) ? $decoded : [];
    }
}
