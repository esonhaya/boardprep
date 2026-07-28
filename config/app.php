<?php

declare(strict_types=1);

return [

    'name' => 'BoardPrep',

    'environment' => $_ENV['APP_ENV'] ?? 'development',

    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'UTC',

    'database' => [

        'driver' => $_ENV['DB_DRIVER'] ?? 'json',

        'path' => dirname(__DIR__) . '/storage',

        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',

        'database' => $_ENV['DB_DATABASE'] ?? 'boardprep',

        'username' => $_ENV['DB_USERNAME'] ?? 'root',

        'password' => $_ENV['DB_PASSWORD'] ?? '',

    ],

];
