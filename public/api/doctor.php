<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';

\App\Core\Autoloader::register();

try {
    require dirname(__DIR__, 2) . '/bootstrap/app.php';

    if (\App\Core\App::config('environment') === 'production') {
        http_response_code(404);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => 'Not found.']);
        exit;
    }

    \App\Controllers\DoctorApiController::index();
} catch (Throwable $exception) {
    (new \App\Core\ExceptionHandler())->handle($exception);
}
