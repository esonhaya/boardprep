<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';

\App\Core\Autoloader::register();

require dirname(__DIR__, 2) . '/bootstrap/app.php';

\App\Controllers\DoctorApiController::index();
