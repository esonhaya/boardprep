<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;
use App\Services\Shared\CsrfTokenService;
use Tools\Doctor\Engine\DoctorRunner;

final class DoctorRunController
{
    public static function run(): void
    {
        if (App::config('environment') === 'production') {
            http_response_code(404);
            return;
        }

        if (Request::method() !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            return;
        }

        if (!CsrfTokenService::valid(Request::input('csrf_token'))) {
            http_response_code(419);
            echo 'The developer action expired. Reload the Doctor page and try again.';
            return;
        }

        (new DoctorRunner())->run();

        header(
            'Location: /developer/doctor'
        );

        exit;
    }
}
