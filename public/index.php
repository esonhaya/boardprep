<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

session_start();

require_once __DIR__ . '/../app/Core/Autoloader.php';

\App\Core\Autoloader::register();

use App\Core\ExceptionHandler;
use App\Core\Router;

try {

    require_once __DIR__ . '/../bootstrap/app.php';

    $router = new Router();

    require_once __DIR__ . '/../routes/web.php';

    $method =
        $_SERVER['REQUEST_METHOD']
        ?? 'GET';

    $uri =
        $_SERVER['REQUEST_URI']
        ?? '/';

    $path =
        parse_url(
            $uri,
            PHP_URL_PATH
        );

    if (!is_string($path) || $path === '') {
        $path = '/';
    }

    $router->dispatch(
        $method,
        $path
    );

} catch (Throwable $exception) {

    (new ExceptionHandler())->handle(
        $exception
    );

}
