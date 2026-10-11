<?php

declare(strict_types=1);

use App\Core\Environment;

$release = require __DIR__ . '/release.php';

return [

    'name' => 'BoardPrep',

    'version' => (string) ($release['product_version'] ?? '1.0.0'),

    'environment' => Environment::get('APP_ENV', 'production'),

    'timezone' => Environment::get('APP_TIMEZONE', 'UTC'),

    'privacy' => [
        'operator_name' => Environment::get('PRIVACY_OPERATOR_NAME', ''),
        'operator_contact' => Environment::get('PRIVACY_OPERATOR_CONTACT', ''),
        'retention_notice' => Environment::get('PRIVACY_RETENTION_NOTICE', ''),
        'hosting_notice' => Environment::get('PRIVACY_HOSTING_NOTICE', ''),
        'terms_approved' => filter_var(
            Environment::get('PRIVACY_TERMS_APPROVED', 'false'),
            FILTER_VALIDATE_BOOLEAN
        ),
    ],

    'database' => [

        'driver' => Environment::get('DB_DRIVER', 'json'),

        'path' => Environment::get(
            'APP_STORAGE_PATH',
            dirname(__DIR__) . '/storage'
        ),

        'sqlite_path' => Environment::get(
            'DB_SQLITE_PATH',
            dirname(__DIR__) . '/storage/runtime/boardprep.sqlite'
        ),

        'migration_path' => dirname(__DIR__) . '/database/migrations',

        'host' => Environment::get('DB_HOST', '127.0.0.1'),

        'port' => Environment::get('DB_PORT', '3306'),

        'database' => Environment::get('DB_DATABASE', 'boardprep'),

        'username' => Environment::get('DB_USERNAME', ''),

        'password' => Environment::get('DB_PASSWORD', ''),

    ],

];
