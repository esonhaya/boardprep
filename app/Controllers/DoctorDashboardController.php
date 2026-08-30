<?php

declare(strict_types=1);

namespace App\Controllers;
final class DoctorDashboardController
{
    public static function index(): void
    {
        $report = [];

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

        require dirname(__DIR__) . '/Views/developer/doctor/index.php';
    }
}
