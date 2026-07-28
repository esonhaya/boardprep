<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Autoloader;
use App\Core\Router;

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';

Autoloader::register();

App::boot();

$router = new Router();

$router->dispatch();
