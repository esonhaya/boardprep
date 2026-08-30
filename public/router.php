<?php

declare(strict_types=1);

// Development/smoke-test router for PHP's built-in server. Production web
// servers should apply the equivalent existing-file-or-front-controller rule.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$publicPath = is_string($path)
    ? __DIR__ . $path
    : __DIR__;

if ($path !== '/' && is_file($publicPath)) {
    return false;
}

require __DIR__ . '/index.php';
