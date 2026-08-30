<?php

declare(strict_types=1);

namespace App\Controllers;
use Tools\Doctor\Engine\DoctorRunner;

final class DoctorRunController
{
    public static function run(): void
    {
        (new DoctorRunner())->run();

        header(
            'Location: /developer/doctor'
        );

        exit;
    }
}
