<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Services\RepositoryHealth\Engine\RepositoryHealthEvidenceService;

final class DoctorApiController
{
    public static function index(): void
    {
        if (App::config('environment') === 'production') {
            http_response_code(404);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['error' => 'Not found.']);
            return;
        }

        $healthRequested = isset($_GET['health']) && in_array(strtolower((string) $_GET['health']), ['1', 'true', 'yes'], true);
        if ($healthRequested) {
            $report = RepositoryHealthEvidenceService::currentReport();
            $report['cached_evidence'] = RepositoryHealthEvidenceService::classifyPersistedSnapshot(
                self::storagePath() . '/repository-health.json',
                dirname(__DIR__, 2)
            );
            self::writeJson($report);
            return;
        }
        $releaseRequested = isset($_GET['release']) && in_array(strtolower((string) $_GET['release']), ['1', 'true', 'yes'], true);
        if ($releaseRequested) {
            $path = self::storagePath() . '/release-readiness.json';
            $evidence = RepositoryHealthEvidenceService::classifyPersistedSnapshot($path, dirname(__DIR__, 2));
            if (($evidence['state'] ?? '') !== RepositoryHealthEvidenceService::CACHED) {
                http_response_code(($evidence['state'] ?? '') === RepositoryHealthEvidenceService::UNAVAILABLE ? 404 : 409);
                self::writeJson([
                    'error' => 'No current release snapshot is available. Run the release gate again.',
                    'evidence_state' => $evidence['state'] ?? RepositoryHealthEvidenceService::UNAVAILABLE,
                    'cached_evidence' => $evidence,
                ]);
                return;
            }
            $report = self::readJson($path);
            $report['evidence_state'] = RepositoryHealthEvidenceService::CACHED;
            $report['cached_evidence'] = $evidence;
            self::writeJson($report);
            return;
        }

        $file = self::storagePath() . '/doctor-report.json';

        $evidence = RepositoryHealthEvidenceService::classifyPersistedSnapshot($file, dirname(__DIR__, 2));

        header(
            'Content-Type: application/json'
        );

        if (($evidence['state'] ?? '') !== RepositoryHealthEvidenceService::CACHED) {
            http_response_code(($evidence['state'] ?? '') === RepositoryHealthEvidenceService::UNAVAILABLE ? 404 : 409);
            echo json_encode([
                'error' => 'No current Doctor report is available. Run Doctor again.',
                'evidence_state' => $evidence['state'] ?? RepositoryHealthEvidenceService::UNAVAILABLE,
                'cached_evidence' => $evidence,
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            return;
        }

        $report = self::readJson($file);
        $report['evidence_state'] = RepositoryHealthEvidenceService::CACHED;
        $report['cached_evidence'] = $evidence;
        self::writeJson($report);
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

    /** @param array<string, mixed> $payload */
    private static function writeJson(array $payload): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
