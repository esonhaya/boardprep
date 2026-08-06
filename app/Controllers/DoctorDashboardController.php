<?php

declare(strict_types=1);

namespace App\Controllers;

final class DoctorDashboardController
{
    public function index(): void
    {
        $report = [];

        $file = './storage/doctor/latest-report.json';

        if (is_file($file)) {

            $report = json_decode(
                file_get_contents($file),
                true
            ) ?? [];

        }

        require './app/Views/developer/doctor/index.php';
    }
}
