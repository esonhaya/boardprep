<?php

declare(strict_types=1);

error_reporting(0);
ini_set('display_errors', '0');

require_once __DIR__ . '/../app/Core/Autoloader.php';

\App\Core\Autoloader::register();
\App\Core\SecurityHeaders::apply();

$health = \App\Services\Operations\ProductionReadinessService::publicHealth(dirname(__DIR__));
http_response_code(($health['status'] ?? '') === 'PASS' ? 200 : 503);
header('Content-Type: application/json; charset=UTF-8');
echo json_encode($health, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
