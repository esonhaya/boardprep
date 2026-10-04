<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;

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
            self::writeJsonSnapshot(
                dirname(__DIR__, 2) . '/storage/repository-health.json',
                'Repository Health snapshot not found.',
                'Repository Health snapshot is malformed.'
            );
            return;
        }
        $releaseRequested = isset($_GET['release']) && in_array(strtolower((string) $_GET['release']), ['1', 'true', 'yes'], true);
        if ($releaseRequested) {
            self::writeJsonSnapshot(
                dirname(__DIR__, 2) . '/storage/release-readiness.json',
                'Release Readiness snapshot not found.',
                'Release Readiness snapshot is malformed.'
            );
            return;
        }

        $file =
            dirname(__DIR__, 2) . '/storage/doctor-report.json';

        header(
            'Content-Type: application/json'
        );

        if (!is_file($file)) {

            http_response_code(404);

            echo json_encode([
                'error' => 'Doctor report not found.'
            ]);

            return;
        }

        $contents = file_get_contents($file);
        $report = $contents === false
            ? null
            : json_decode($contents, true);

        if (!is_array($report)) {
            http_response_code(500);
            echo json_encode([
                'error' => 'Doctor report is malformed.'
            ]);
            return;
        }

        echo json_encode(
            $report,
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }

    private static function writeJsonSnapshot(string $file, string $missingMessage, string $malformedMessage): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        if (!is_file($file)) {
            http_response_code(404);
            echo json_encode(['error' => $missingMessage]);
            return;
        }
        $contents = file_get_contents($file);
        $report = $contents === false ? null : json_decode($contents, true);
        if (!is_array($report)) {
            http_response_code(500);
            echo json_encode(['error' => $malformedMessage]);
            return;
        }
        echo json_encode($report, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
