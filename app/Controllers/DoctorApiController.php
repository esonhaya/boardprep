<?php

declare(strict_types=1);

namespace App\Controllers;
final class DoctorApiController
{
    public static function index(): void
    {
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
}
