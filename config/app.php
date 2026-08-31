<?php

declare(strict_types=1);

use App\Core\Environment;

return [

    'name' => 'BoardPrep',

    'version' => '0.1.0',

    'environment' => Environment::get('APP_ENV', 'production'),

    'timezone' => Environment::get('APP_TIMEZONE', 'UTC'),

    'database' => [

        'driver' => Environment::get('DB_DRIVER', 'json'),

        'path' => Environment::get(
            'APP_STORAGE_PATH',
            dirname(__DIR__) . '/storage'
        ),

        'host' => Environment::get('DB_HOST', '127.0.0.1'),

        'port' => Environment::get('DB_PORT', '3306'),

        'database' => Environment::get('DB_DATABASE', 'boardprep'),

        'username' => Environment::get('DB_USERNAME', ''),

        'password' => Environment::get('DB_PASSWORD', ''),

    ],

];
