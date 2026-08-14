<?php

declare(strict_types=1);

namespace App\Controllers;
final class DoctorApiController
{
    public function index(): void
    {
        $file =
            './storage/doctor/latest-report.json';

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

        readfile($file);
    }
}
